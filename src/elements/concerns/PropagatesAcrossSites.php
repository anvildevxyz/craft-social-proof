<?php

namespace anvildev\socialproof\elements\concerns;

use Craft;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\IntegrityException;

/**
 * Multi-site propagation scaffolding shared by NotificationElement and PopupElement.
 *
 * Each element declares the per-site record class + FK column it pivots through;
 * the trait owns the rest: propagation-method constants, getSupportedSites(),
 * permission-gated can* checks, and the post-save elements_sites seeding for
 * siteGroup/language propagation.
 *
 * @phpstan-require-extends \craft\base\Element
 */
trait PropagatesAcrossSites
{
    public const PROPAGATION_METHOD_NONE = 'none';
    public const PROPAGATION_METHOD_ALL = 'all';
    public const PROPAGATION_METHOD_SITE_GROUP = 'siteGroup';
    public const PROPAGATION_METHOD_LANGUAGE = 'language';

    public string $propagationMethod = self::PROPAGATION_METHOD_ALL;

    /** @return list<string> */
    public static function propagationMethods(): array
    {
        return [
            self::PROPAGATION_METHOD_NONE,
            self::PROPAGATION_METHOD_ALL,
            self::PROPAGATION_METHOD_SITE_GROUP,
            self::PROPAGATION_METHOD_LANGUAGE,
        ];
    }

    public function getSupportedSites(): array
    {
        return match ($this->propagationMethod) {
            self::PROPAGATION_METHOD_ALL => $this->_getAllSitesConfig(),
            self::PROPAGATION_METHOD_SITE_GROUP => $this->_getSiteGroupConfig(),
            self::PROPAGATION_METHOD_LANGUAGE => $this->_getLanguageSitesConfig(),
            default => [['siteId' => $this->siteId, 'enabledByDefault' => true]],
        };
    }

    public function canView(?\craft\elements\User $user): bool
    {
        return $this->_userCanManage($user);
    }

    public function canSave(?\craft\elements\User $user): bool
    {
        return $this->_userCanManage($user);
    }

    public function canDelete(?\craft\elements\User $user): bool
    {
        return $this->_userCanManage($user);
    }

    /** Permission constant the element checks against `$user->can()`. */
    abstract protected function managePermission(): string;

    /** @return class-string<ActiveRecord> ActiveRecord class for the per-site pivot table. */
    abstract protected function siteRecordClass(): string;

    /** Foreign key column on the site-record pointing back at this element. */
    abstract protected function siteRecordForeignKey(): string;

    /** Build a fresh per-site pivot record (with element-specific column data) for the given site. */
    abstract protected function createSiteRecordForSite(int $siteId): ActiveRecord;

    /**
     * Insert the per-site record for $siteId if it doesn't already exist.
     * Used during the propagating branch of afterSave().
     */
    protected function ensureSiteRecordExists(int $siteId): void
    {
        $cls = $this->siteRecordClass();
        $fk = $this->siteRecordForeignKey();

        $exists = $cls::find()->where([$fk => $this->id, 'siteId' => $siteId])->exists();
        if (!$exists) {
            $this->createSiteRecordForSite($siteId)->save(false);
        }
    }

    private function _userCanManage(?\craft\elements\User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->admin || $user->can($this->managePermission());
    }

    /** @return list<array{siteId: int, enabledByDefault: bool, propagate: bool}> */
    private function _getAllSitesConfig(): array
    {
        return array_map(static function ($site) {
            return [
                'siteId' => $site->id,
                'enabledByDefault' => true,
                'propagate' => true,
            ];
        }, Craft::$app->getSites()->getAllSites());
    }

    /** @return list<array{siteId: int, enabledByDefault: bool}> */
    private function _getSiteGroupConfig(): array
    {
        $currentSiteId = $this->siteId ?: Craft::$app->getSites()->getCurrentSite()->id;
        $currentSite = Craft::$app->getSites()->getSiteById($currentSiteId);

        if (!$currentSite) {
            return [['siteId' => $currentSiteId, 'enabledByDefault' => true]];
        }

        // For new elements, only return the current site to avoid duplicate-key errors.
        // _ensureElementSitesEntries() adds other sites after save.
        if (!$this->id) {
            return [['siteId' => $this->siteId, 'enabledByDefault' => true]];
        }

        $sitesInGroup = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if ($site->groupId === $currentSite->groupId) {
                $sitesInGroup[] = [
                    'siteId' => $site->id,
                    'enabledByDefault' => true,
                ];
            }
        }

        return $sitesInGroup ?: [['siteId' => $currentSiteId, 'enabledByDefault' => true]];
    }

    /** @return list<array{siteId: int, enabledByDefault: bool}> */
    private function _getLanguageSitesConfig(): array
    {
        $currentSite = Craft::$app->getSites()->getSiteById($this->siteId);
        if (!$currentSite) {
            return [['siteId' => $this->siteId, 'enabledByDefault' => true]];
        }

        // Same new-element guard as siteGroup propagation above.
        if (!$this->id) {
            return [['siteId' => $this->siteId, 'enabledByDefault' => true]];
        }

        $matchingSites = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if ($site->language === $currentSite->language) {
                $matchingSites[] = [
                    'siteId' => $site->id,
                    'enabledByDefault' => true,
                ];
            }
        }

        return $matchingSites;
    }

    /**
     * Create missing elements_sites + per-site pivot rows for siteGroup / language
     * propagation on initial save. getSupportedSites() returns only the current site
     * for new elements (to dodge duplicate-key errors during the first insert), so
     * the remaining sites have to be backfilled here once we have an element id.
     */
    private function _ensureElementSitesEntries(): void
    {
        $supportedSites = match ($this->propagationMethod) {
            self::PROPAGATION_METHOD_SITE_GROUP => $this->_getSiteGroupConfig(),
            self::PROPAGATION_METHOD_LANGUAGE => $this->_getLanguageSitesConfig(),
            default => [],
        };

        foreach ($supportedSites as $siteConfig) {
            $siteId = $siteConfig['siteId'];

            $exists = (new \craft\db\Query())
                ->select('id')
                ->from('{{%elements_sites}}')
                ->where(['elementId' => $this->id, 'siteId' => $siteId])
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $slug = ElementHelper::generateSlug($this->title ?? '');
                $slugCount = 0;
                $testSlug = $slug;
                while ((new \craft\db\Query())
                    ->from('{{%elements_sites}}')
                    ->where(['siteId' => $siteId, 'slug' => $testSlug])
                    ->andWhere(['!=', 'elementId', $this->id])
                    ->exists()
                ) {
                    $slugCount++;
                    $testSlug = $slug . '-' . $slugCount;
                }

                Craft::$app->getDb()->createCommand()->insert('{{%elements_sites}}', [
                    'elementId' => $this->id,
                    'siteId' => $siteId,
                    'slug' => $testSlug,
                    'uri' => null,
                    'enabled' => $this->enabled,
                    'dateCreated' => new Expression('NOW()'),
                    'dateUpdated' => new Expression('NOW()'),
                    'uid' => StringHelper::UUID(),
                ])->execute();

                $this->ensureSiteRecordExists($siteId);
            } catch (IntegrityException) {
                // Race — entry already exists, silently continue
            }
        }
    }
}
