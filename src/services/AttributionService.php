<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\helpers\Time;

/**
 * @phpstan-import-type RevenueByPopup from AttributionStore
 */
class AttributionService
{
    public function __construct(private AttributionStore $store) {}

    public function recordConvert(int $popupId, string $visitorId, ?string $sessionId): void
    {
        $this->store->recordPending($popupId, $visitorId, $sessionId, Time::utcNow());
    }

    public function attributeOrder(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int {
        return $this->store->attribute($visitorId, $sessionId, $orderId, $orderTotal, $currency, $completedAt, $windowHours);
    }

    public function cleanupExpired(int $days): int
    {
        $cutoff = Time::utcNow()->modify("-{$days} days");
        return $this->store->expireOlderThan($cutoff);
    }

    /**
     * @return RevenueByPopup
     */
    public function revenueByPopup(int $days): array
    {
        $since = Time::utcNow()->modify("-{$days} days");
        return $this->store->revenueByPopup($since);
    }
}
