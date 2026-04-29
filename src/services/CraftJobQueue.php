<?php

namespace anvildev\socialproof\services;

use Craft;
use craft\queue\BaseJob;

class CraftJobQueue implements JobQueueInterface
{
    public function push(BaseJob $job): int|string|null
    {
        return Craft::$app->getQueue()->push($job);
    }
}
