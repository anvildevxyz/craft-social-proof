<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $notificationId
 * @property int $siteId
 * @property string|array $settings
 */
class NotificationSiteRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%socialproof_notification_sites}}';
    }

    public function rules(): array
    {
        return [
            [['notificationId', 'siteId'], 'required'],
            [['notificationId', 'siteId'], 'integer'],
            [['settings'], 'safe'],
        ];
    }

    public function beforeSave($insert): bool
    {
        if (is_array($this->settings)) {
            $this->settings = json_encode($this->settings);
        }
        return parent::beforeSave($insert);
    }

    public function afterFind(): void
    {
        parent::afterFind();
        if (is_string($this->settings)) {
            $this->settings = json_decode($this->settings, true) ?: [];
        }
    }
}
