<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $popupId
 * @property string $visitorId
 * @property int $shownCount
 * @property string|null $lastShownAt
 * @property string|null $dismissedAt
 * @property string|null $convertedAt
 */
class PopupFatigueRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%socialproof_popup_fatigue}}';
    }
}
