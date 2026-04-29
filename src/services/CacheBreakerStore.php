<?php

namespace anvildev\socialproof\services;

use Craft;
use yii\caching\CacheInterface;

/**
 * @phpstan-import-type BreakerState from BreakerStore
 */
class CacheBreakerStore implements BreakerStore
{
    private const KEY_PREFIX = 'socialproof.webhook.breaker:';
    // Long enough to outlive normal cooldowns but bounded so abandoned
    // subscriptions don't pin entries forever. 7 days is plenty for an
    // N-strike breaker.
    private const ENTRY_TTL_SECONDS = 7 * 86400;

    public function __construct(private ?CacheInterface $cache = null) {}

    public function isOpen(string $subscriptionId, int $now): bool
    {
        $state = $this->load($subscriptionId);
        return $state['openUntil'] !== null && $state['openUntil'] > $now;
    }

    public function markSuccess(string $subscriptionId): void
    {
        $this->cache()->delete(self::KEY_PREFIX . $subscriptionId);
    }

    public function markFailure(string $subscriptionId, int $threshold, int $cooldownSeconds, int $now): void
    {
        $state = $this->load($subscriptionId);
        $state['failures']++;
        if ($state['failures'] >= $threshold) {
            $state['openUntil'] = $now + $cooldownSeconds;
        }
        $this->cache()->set(self::KEY_PREFIX . $subscriptionId, $state, self::ENTRY_TTL_SECONDS);
    }

    public function inspect(string $subscriptionId): array
    {
        return $this->load($subscriptionId);
    }

    /**
     * @return BreakerState
     */
    private function load(string $subscriptionId): array
    {
        $state = $this->cache()->get(self::KEY_PREFIX . $subscriptionId);
        if (!is_array($state)) {
            return ['failures' => 0, 'openUntil' => null];
        }
        return [
            'failures' => (int) ($state['failures'] ?? 0),
            'openUntil' => isset($state['openUntil']) ? (int) $state['openUntil'] : null,
        ];
    }

    private function cache(): CacheInterface
    {
        return $this->cache ?? Craft::$app->getCache();
    }
}
