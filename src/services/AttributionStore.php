<?php

namespace anvildev\socialproof\services;

/**
 * @phpstan-type RevenueByPopup array<int, array{revenue: string, orders: int}>
 */
interface AttributionStore
{
    public function recordPending(int $popupId, string $visitorId, ?string $sessionId, \DateTimeInterface $convertedAt): void;

    /**
     * Find the most recent pending attribution for this visitor within the window
     * and transition it to `attributed`. Returns the attributed row id, or null
     * if nothing matched.
     */
    public function attribute(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int;

    /** Transition pending rows older than the cutoff to `expired`. Returns count. */
    public function expireOlderThan(\DateTimeInterface $cutoff): int;

    /**
     * Aggregate attributed revenue per popupId within the lookback window.
     *
     * @return RevenueByPopup
     */
    public function revenueByPopup(\DateTimeInterface $since): array;
}
