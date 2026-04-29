<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\BreakerStore;

class InMemoryBreakerStore implements BreakerStore
{
    /** @var array<string, array{failures: int, openUntil: int|null}> */
    public array $state = [];

    public function isOpen(string $subscriptionId, int $now): bool
    {
        $s = $this->state[$subscriptionId] ?? null;
        return $s !== null && $s['openUntil'] !== null && $s['openUntil'] > $now;
    }

    public function markSuccess(string $subscriptionId): void
    {
        unset($this->state[$subscriptionId]);
    }

    public function markFailure(string $subscriptionId, int $threshold, int $cooldownSeconds, int $now): void
    {
        $s = $this->state[$subscriptionId] ?? ['failures' => 0, 'openUntil' => null];
        $s['failures']++;
        if ($s['failures'] >= $threshold) {
            $s['openUntil'] = $now + $cooldownSeconds;
        }
        $this->state[$subscriptionId] = $s;
    }

    public function inspect(string $subscriptionId): array
    {
        return $this->state[$subscriptionId] ?? ['failures' => 0, 'openUntil' => null];
    }
}
