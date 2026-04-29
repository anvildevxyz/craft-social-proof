<?php

namespace anvildev\socialproof\models;

use craft\base\Model;
use DateTime;

class Impression extends Model
{
    public ?int $id = null;

    public ?int $notificationId = null;

    public string $sessionId = '';

    /** @var string one of: 'impression', 'click', 'dismiss', 'heartbeat' */
    public string $eventType = 'impression';

    public ?string $pageUrl = null;

    public array $metadata = [];

    public ?DateTime $dateCreated = null;

    public function defineRules(): array
    {
        return [
            [['sessionId', 'eventType'], 'required'],
            [['sessionId'], 'string', 'max' => 64],
            [['eventType'], 'in', 'range' => \anvildev\socialproof\enums\ImpressionEvent::values()],
            [['pageUrl'], 'string', 'max' => 500],
            [['notificationId'], 'integer'],
        ];
    }
}
