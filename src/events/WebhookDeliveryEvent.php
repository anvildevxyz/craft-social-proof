<?php

namespace anvildev\socialproof\events;

use yii\base\Event;

class WebhookDeliveryEvent extends Event
{
    public string $subscriptionId = '';
}
