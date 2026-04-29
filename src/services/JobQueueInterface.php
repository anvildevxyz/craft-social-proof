<?php

namespace anvildev\socialproof\services;

interface JobQueueInterface
{
    /** Push a job onto the queue. Returns the job id (string|int) or null. */
    public function push(\craft\queue\BaseJob $job): int|string|null;
}
