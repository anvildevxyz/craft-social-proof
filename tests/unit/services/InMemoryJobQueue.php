<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\JobQueueInterface;

class InMemoryJobQueue implements JobQueueInterface
{
    /** @var list<\craft\queue\BaseJob> */
    public array $jobs = [];

    public function push(\craft\queue\BaseJob $job): int|string|null
    {
        $this->jobs[] = $job;
        return count($this->jobs);
    }
}
