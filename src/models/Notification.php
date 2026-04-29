<?php

namespace anvildev\socialproof\models;

use craft\base\Model;
use DateTime;

class Notification extends Model
{
    public ?int $id = null;

    public string $name = '';

    /** @var string one of: 'purchase', 'viewers', 'stock', 'custom' */
    public string $type = 'purchase';

    public bool $enabled = true;

    public array $settings = [];

    public string $position = 'bottom-left';

    public int $displayDuration = 5;

    public int $delayBetween = 10;

    public ?int $siteId = null;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    public ?string $uid = null;

    // Runtime properties (not stored in DB)

    public ?string $customerName = null;

    public ?string $customerLocation = null;

    public ?string $productName = null;

    public ?string $productUrl = null;

    public ?string $productImage = null;

    /** @var int|null Count (for viewers or stock) */
    public ?int $count = null;

    public ?string $message = null;

    public ?string $timeAgo = null;

    public ?string $linkTarget = null;

    public function defineRules(): array
    {
        return [
            [['name', 'type'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['type'], 'in', 'range' => \anvildev\socialproof\enums\NotificationType::values()],
            [['enabled'], 'boolean'],
            [['position'], 'in', 'range' => \anvildev\socialproof\enums\NotificationPosition::values()],
            [['displayDuration'], 'integer', 'min' => 1, 'max' => 60],
            [['delayBetween'], 'integer', 'min' => 1, 'max' => 120],
            [['siteId'], 'integer'],
        ];
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'message' => $this->message,
            'customerName' => $this->customerName,
            'customerLocation' => $this->customerLocation,
            'productName' => $this->productName,
            'productUrl' => $this->productUrl,
            'productImage' => $this->productImage,
            'count' => $this->count,
            'timeAgo' => $this->timeAgo,
            'linkTarget' => $this->linkTarget,
        ];
    }
}
