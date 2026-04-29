<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $popupId
 * @property string $visitorId
 * @property string|null $sessionId
 * @property string $eventType
 * @property string|null $pageUrl
 */
class PopupImpressionRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%socialproof_popup_impressions}}';
    }
}
