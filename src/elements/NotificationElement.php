<?php

namespace anvildev\socialproof\elements;

use anvildev\socialproof\elements\concerns\PropagatesAcrossSites;
use anvildev\socialproof\elements\db\NotificationQuery;
use anvildev\socialproof\Plugin;
use anvildev\socialproof\records\NotificationRecord;
use anvildev\socialproof\records\NotificationSiteRecord;
use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\actions\SetStatus;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

class NotificationElement extends Element
{
    use PropagatesAcrossSites;

    public static function displayName(): string
    {
        return Craft::t('social-proof', 'Notification');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('social-proof', 'notification');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('social-proof', 'Notifications');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('social-proof', 'notifications');
    }

    public static function refHandle(): ?string
    {
        return 'notification';
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

    public static function find(): NotificationQuery
    {
        return new NotificationQuery(static::class);
    }

    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('social-proof', 'All Notifications'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'type:purchase',
                'label' => Craft::t('social-proof', 'Purchase'),
                'criteria' => ['type' => 'purchase'],
            ],
            [
                'key' => 'type:viewers',
                'label' => Craft::t('social-proof', 'Viewers'),
                'criteria' => ['type' => 'viewers'],
            ],
            [
                'key' => 'type:stock',
                'label' => Craft::t('social-proof', 'Low Stock'),
                'criteria' => ['type' => 'stock'],
            ],
            [
                'key' => 'type:custom',
                'label' => Craft::t('social-proof', 'Custom'),
                'criteria' => ['type' => 'custom'],
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
            'type' => Craft::t('social-proof', 'Type'),
            'position' => Craft::t('social-proof', 'Position'),
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
            'type' => ['label' => Craft::t('social-proof', 'Type')],
            'position' => ['label' => Craft::t('social-proof', 'Position')],
            'displayDuration' => ['label' => Craft::t('social-proof', 'Duration')],
            'delayBetween' => ['label' => Craft::t('social-proof', 'Delay')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'type',
            'position',
            'displayDuration',
            'dateCreated',
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'type'];
    }

    /** @var string one of: 'purchase', 'viewers', 'stock', 'custom' */
    public string $type = 'purchase';

    /** @var array|string Type-specific settings (may arrive as JSON string from DB) */
    public array|string $settings = [];

    public string $position = 'bottom-left';

    public int $displayDuration = 5;

    public int $delayBetween = 10;

    public function init(): void
    {
        parent::init();

        // Element queries bypass ActiveRecord afterFind(), so settings
        // arrives as a JSON string from the DB — decode it here.
        // Note: init() is called after Yii::configure() sets properties,
        // so settings is already populated from the query row at this point.
        if (is_string($this->settings)) {
            $this->settings = json_decode($this->settings, true) ?: [];
        }
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'type' => Craft::t('social-proof', 'Type'),
            'position' => Craft::t('social-proof', 'Position'),
            'displayDuration' => Craft::t('social-proof', 'Display Duration'),
            'delayBetween' => Craft::t('social-proof', 'Delay Between'),
            'propagationMethod' => Craft::t('social-proof', 'Propagation Method'),
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['type'], 'required'];
        $rules[] = [['type'], 'in', 'range' => \anvildev\socialproof\enums\NotificationType::values()];
        $rules[] = [['position'], 'in', 'range' => \anvildev\socialproof\enums\NotificationPosition::values()];
        $rules[] = [['displayDuration'], 'integer', 'min' => 1, 'max' => 60];
        $rules[] = [['delayBetween'], 'integer', 'min' => 1, 'max' => 120];
        $rules[] = [['propagationMethod'], 'in', 'range' => self::propagationMethods()];

        return $rules;
    }

    protected function managePermission(): string
    {
        return Plugin::PERMISSION_MANAGE_NOTIFICATIONS;
    }

    protected function siteRecordClass(): string
    {
        return NotificationSiteRecord::class;
    }

    protected function siteRecordForeignKey(): string
    {
        return 'notificationId';
    }

    protected function createSiteRecordForSite(int $siteId): ActiveRecord
    {
        $r = new NotificationSiteRecord();
        $r->notificationId = $this->id;
        $r->siteId = $siteId;
        $r->settings = $this->settings;
        return $r;
    }

    public function cpEditUrl(): ?string
    {
        $site = Craft::$app->getSites()->getSiteById($this->siteId);
        $siteHandle = $site?->handle;

        return UrlHelper::cpUrl("social-proof/notifications/{$this->id}" . ($siteHandle ? "/{$siteHandle}" : ''));
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('social-proof/notifications');
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'type':
                return ucfirst($this->type);

            case 'position':
                return ucwords(str_replace('-', ' ', $this->position));

            case 'displayDuration':
                return $this->displayDuration . 's';

            case 'delayBetween':
                return $this->delayBetween . 's';
        }

        return parent::tableAttributeHtml($attribute);
    }

    public function afterSave(bool $isNew): void
    {
        // When propagating to other sites, the main record is already saved — just
        // seed a per-site settings row and bail.
        if ($this->propagating) {
            $this->ensureSiteRecordExists($this->siteId);
            parent::afterSave($isNew);
            return;
        }

        if (!$isNew) {
            $record = NotificationRecord::findOne($this->id);

            if (!$record) {
                throw new InvalidConfigException("Invalid notification ID: {$this->id}");
            }
        } else {
            $record = new NotificationRecord();
            $record->id = $this->id;
        }

        $record->type = $this->type;
        $record->position = $this->position;
        $record->displayDuration = $this->displayDuration;
        $record->delayBetween = $this->delayBetween;
        $record->propagationMethod = $this->propagationMethod;

        $record->save(false);

        $siteRecord = NotificationSiteRecord::findOne([
            'notificationId' => $this->id,
            'siteId' => $this->siteId,
        ]);

        if (!$siteRecord) {
            $siteRecord = new NotificationSiteRecord();
            $siteRecord->notificationId = $this->id;
            $siteRecord->siteId = $this->siteId;
        }

        $siteRecord->settings = $this->settings;
        $siteRecord->save(false);

        parent::afterSave($isNew);

        // For siteGroup and language propagation on NEW notifications, manually
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
        NotificationSiteRecord::deleteAll(['notificationId' => $this->id]);
        parent::afterDelete();
    }

}
