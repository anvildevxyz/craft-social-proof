<?php

namespace anvildev\socialproof\elements;

use anvildev\socialproof\elements\concerns\PropagatesAcrossSites;
use anvildev\socialproof\elements\db\PopupQuery;
use anvildev\socialproof\Plugin;
use anvildev\socialproof\records\PopupRecord;
use anvildev\socialproof\records\PopupSiteRecord;
use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\actions\SetStatus;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

class PopupElement extends Element
{
    use PropagatesAcrossSites;

    public static function displayName(): string
    {
        return Craft::t('social-proof', 'Popup');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('social-proof', 'popup');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('social-proof', 'Popups');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('social-proof', 'popups');
    }

    public static function refHandle(): ?string
    {
        return 'popup';
    }

    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasContent(): bool
    {
        return false;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ENABLED => Craft::t('social-proof', 'Enabled'),
            self::STATUS_DISABLED => Craft::t('social-proof', 'Disabled'),
        ];
    }

    public static function find(): PopupQuery
    {
        return new PopupQuery(static::class);
    }

    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('social-proof', 'All Popups'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'layout:announcement',
                'label' => Craft::t('social-proof', 'Announcement'),
                'criteria' => ['layout' => 'announcement'],
            ],
            [
                'key' => 'layout:newsletter',
                'label' => Craft::t('social-proof', 'Newsletter'),
                'criteria' => ['layout' => 'newsletter'],
            ],
            [
                'key' => 'layout:discount',
                'label' => Craft::t('social-proof', 'Discount'),
                'criteria' => ['layout' => 'discount'],
            ],
            [
                'key' => 'layout:bottom-bar',
                'label' => Craft::t('social-proof', 'Bottom Bar'),
                'criteria' => ['layout' => 'bottom-bar'],
            ],
            [
                'key' => 'layout:custom',
                'label' => Craft::t('social-proof', 'Custom'),
                'criteria' => ['layout' => 'custom'],
            ],
        ];
    }

    protected static function defineActions(?string $source = null): array
    {
        return [
            SetStatus::class,
            Delete::class,
            Restore::class,
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'layout' => Craft::t('social-proof', 'Layout'),
            'priority' => Craft::t('social-proof', 'Priority'),
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('app', 'Title')],
            'layout' => ['label' => Craft::t('social-proof', 'Layout')],
            'priority' => ['label' => Craft::t('social-proof', 'Priority')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'layout',
            'priority',
            'dateCreated',
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'layout'];
    }

    /** @var string one of: 'announcement', 'newsletter', 'discount', 'bottom-bar', 'custom' */
    public string $layout = 'announcement';

    /** @var string|null Custom template path (used when layout = 'custom') */
    public ?string $customTemplate = null;

    /** @var array|string Trigger configuration (may arrive as JSON string from DB) */
    public array|string $trigger = [];

    /** @var array|string Targeting rules (may arrive as JSON string from DB) */
    public array|string $targeting = [];

    /** @var array|string Fatigue rules (may arrive as JSON string from DB) */
    public array|string $fatigueRules = [];

    /** @var array|string Per-site layout settings (may arrive as JSON string from DB) */
    public array|string $layoutSettings = [];

    /** @var int Display priority (0–100) */
    public int $priority = 50;

    public function init(): void
    {
        parent::init();

        // Element queries bypass ActiveRecord afterFind(), so JSON fields
        // arrive as strings from the DB — decode them here.
        foreach (['trigger', 'targeting', 'fatigueRules', 'layoutSettings'] as $field) {
            if (is_string($this->$field)) {
                $this->$field = json_decode($this->$field, true) ?: [];
            }
        }
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'layout' => Craft::t('social-proof', 'Layout'),
            'priority' => Craft::t('social-proof', 'Priority'),
            'propagationMethod' => Craft::t('social-proof', 'Propagation Method'),
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['layout'], 'required'];
        $rules[] = [['layout'], 'in', 'range' => \anvildev\socialproof\enums\PopupLayout::values()];
        $rules[] = [['priority'], 'integer', 'min' => 0, 'max' => 100];
        $rules[] = [['propagationMethod'], 'in', 'range' => self::propagationMethods()];

        return $rules;
    }

    protected function managePermission(): string
    {
        return Plugin::PERMISSION_MANAGE_POPUPS;
    }

    protected function siteRecordClass(): string
    {
        return PopupSiteRecord::class;
    }

    protected function siteRecordForeignKey(): string
    {
        return 'popupId';
    }

    protected function createSiteRecordForSite(int $siteId): ActiveRecord
    {
        $r = new PopupSiteRecord();
        $r->popupId = $this->id;
        $r->siteId = $siteId;
        $r->layoutSettings = json_encode($this->layoutSettings);
        return $r;
    }

    public function cpEditUrl(): ?string
    {
        $site = Craft::$app->getSites()->getSiteById($this->siteId);
        $siteHandle = $site?->handle;

        return UrlHelper::cpUrl("social-proof/popups/{$this->id}" . ($siteHandle ? "/{$siteHandle}" : ''));
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('social-proof/popups');
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'layout':
                return ucfirst($this->layout);

            case 'priority':
                return (string) $this->priority;
        }

        return parent::tableAttributeHtml($attribute);
    }

    public function afterSave(bool $isNew): void
    {
        // When propagating to other sites, the main record is already saved — just
        // seed a per-site layout-settings row and bail.
        if ($this->propagating) {
            $this->ensureSiteRecordExists($this->siteId);
            parent::afterSave($isNew);
            return;
        }

        if (!$isNew) {
            $record = PopupRecord::findOne($this->id);

            if (!$record) {
                throw new InvalidConfigException("Invalid popup ID: {$this->id}");
            }
        } else {
            $record = new PopupRecord();
            $record->id = $this->id;
        }

        $record->layout = $this->layout;
        $record->customTemplate = $this->customTemplate;
        $record->trigger = json_encode($this->trigger);
        $record->targeting = json_encode($this->targeting);
        $record->fatigueRules = json_encode($this->fatigueRules);
        $record->priority = $this->priority;
        $record->propagationMethod = $this->propagationMethod;

        $record->save(false);

        $siteRecord = PopupSiteRecord::findOne([
            'popupId' => $this->id,
            'siteId' => $this->siteId,
        ]);

        if (!$siteRecord) {
            $siteRecord = new PopupSiteRecord();
            $siteRecord->popupId = $this->id;
            $siteRecord->siteId = $this->siteId;
        }

        $siteRecord->layoutSettings = json_encode($this->layoutSettings);
        $siteRecord->save(false);

        parent::afterSave($isNew);

        // For siteGroup and language propagation on NEW popups, manually
        // create elements_sites entries because getSupportedSites() returns only
        // the current site on first save to avoid duplicate key errors.
        if ($isNew && in_array($this->propagationMethod, [
            self::PROPAGATION_METHOD_SITE_GROUP,
            self::PROPAGATION_METHOD_LANGUAGE,
        ])) {
            $this->_ensureElementSitesEntries();
        }
    }

    public function afterDelete(): void
    {
        PopupSiteRecord::deleteAll(['popupId' => $this->id]);
        parent::afterDelete();
    }

}
