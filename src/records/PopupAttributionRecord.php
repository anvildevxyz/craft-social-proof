<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $popupId
 * @property string $visitorId
 * @property string|null $sessionId
 * @property int|null $orderId
 * @property string|null $orderTotal
 * @property string|null $currency
 * @property string $status
 * @property string $convertedAt
 * @property string|null $attributedAt
 */
class PopupAttributionRecord extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ATTRIBUTED = 'attributed';
    public const STATUS_EXPIRED = 'expired';

    public static function tableName(): string
    {
        return '{{%socialproof_popup_attributions}}';
    }
}
