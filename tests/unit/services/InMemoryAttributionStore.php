<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\records\PopupAttributionRecord;
use anvildev\socialproof\services\AttributionStore;

class InMemoryAttributionStore implements AttributionStore
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    private int $nextId = 1;

    public function recordPending(int $popupId, string $visitorId, ?string $sessionId, \DateTimeInterface $convertedAt): void
    {
        $this->rows[$this->nextId] = [
            'id' => $this->nextId,
            'popupId' => $popupId,
            'visitorId' => $visitorId,
            'sessionId' => $sessionId,
            'orderId' => null,
            'orderTotal' => null,
            'currency' => null,
            'status' => PopupAttributionRecord::STATUS_PENDING,
            'convertedAt' => $convertedAt->format('Y-m-d H:i:s'),
            'attributedAt' => null,
        ];
        $this->nextId++;
    }

    public function attribute(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int {
        $cutoff = (clone $completedAt)->modify("-{$windowHours} hours")->format('Y-m-d H:i:s');
        $candidates = array_filter(
            $this->rows,
            fn ($r) =>
                $r['status'] === PopupAttributionRecord::STATUS_PENDING
                && ($r['visitorId'] === $visitorId || ($sessionId !== null && $r['sessionId'] === $sessionId))
                && $r['convertedAt'] >= $cutoff,
        );
        if (!$candidates) {
            return null;
        }
        usort($candidates, fn ($a, $b) => strcmp($b['convertedAt'], $a['convertedAt']) ?: ($b['id'] <=> $a['id']));
        $winner = $candidates[0];
        $this->rows[$winner['id']] = [
            ...$winner,
            'status' => PopupAttributionRecord::STATUS_ATTRIBUTED,
            'orderId' => $orderId,
            'orderTotal' => $orderTotal,
            'currency' => $currency,
            'attributedAt' => $completedAt->format('Y-m-d H:i:s'),
        ];
        return $winner['id'];
    }

    public function expireOlderThan(\DateTimeInterface $cutoff): int
    {
        $count = 0;
        $c = $cutoff->format('Y-m-d H:i:s');
        foreach ($this->rows as $id => $r) {
            if ($r['status'] === PopupAttributionRecord::STATUS_PENDING && $r['convertedAt'] < $c) {
                $this->rows[$id]['status'] = PopupAttributionRecord::STATUS_EXPIRED;
                $count++;
            }
        }
        return $count;
    }

    public function revenueByPopup(\DateTimeInterface $since): array
    {
        $s = $since->format('Y-m-d H:i:s');
        $out = [];
        foreach ($this->rows as $r) {
            if ($r['status'] !== PopupAttributionRecord::STATUS_ATTRIBUTED) {
                continue;
            }
            if ($r['attributedAt'] === null || $r['attributedAt'] < $s) {
                continue;
            }
            $pid = $r['popupId'];
            if (!isset($out[$pid])) {
                $out[$pid] = ['revenue' => '0.0000', 'orders' => 0];
            }
            $out[$pid]['revenue'] = bcadd($out[$pid]['revenue'], $r['orderTotal'] ?? '0', 4);
            $out[$pid]['orders']++;
        }
        return $out;
    }
}
