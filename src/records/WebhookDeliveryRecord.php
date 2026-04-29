<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $subscriptionId
 * @property string $event
 * @property string $deliveryId
 * @property int|null $statusCode
 * @property string|null $responseSnippet
 * @property string|null $transportError
 * @property string $dispatchedAt
 * @property string|null $completedAt
 */
class WebhookDeliveryRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%socialproof_webhook_deliveries}}';
    }
}
