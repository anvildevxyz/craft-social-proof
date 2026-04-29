<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\helpers\Time;

/**
 * Decides whether a popup is fatigued for a given visitor and records
 * impression / dismiss / convert events to the fatigue store.
 */
class FatigueService
{
    public function __construct(private FatigueStore $store)
    {
    }

    /**
     * @param array{stopAfterDismiss?: bool, stopAfterConvert?: bool, maxPerVisitor?: int|null, minHoursBetween?: int|null} $rules popup.fatigueRules JSON-decoded
     */
    public function isFatigued(int $popupId, string $visitorId, array $rules): bool
    {
        $row = $this->store->get($popupId, $visitorId);
        if ($row === null) {
            return false;
        }

        if (($rules['stopAfterDismiss'] ?? false) && !empty($row['dismissedAt'])) {
            return true;
        }

        if (($rules['stopAfterConvert'] ?? false) && !empty($row['convertedAt'])) {
            return true;
        }

        $maxPerVisitor = $rules['maxPerVisitor'] ?? null;
        if ($maxPerVisitor !== null && ($row['shownCount'] ?? 0) >= $maxPerVisitor) {
            return true;
        }

        $minHoursBetween = $rules['minHoursBetween'] ?? null;
        if ($minHoursBetween !== null && !empty($row['lastShownAt'])) {
            // Interpret the stored datetime as UTC (that's how MySQL stores it for this plugin),
            // and compare against "now" in UTC. Without the explicit UTC zone, PHP would parse
            // the bare string in its default local TZ, shifting the comparison by the offset.
            $lastShown = new \DateTimeImmutable($row['lastShownAt'], new \DateTimeZone('UTC'));
            $cutoff = Time::utcNow()->modify('-' . (int) $minHoursBetween . ' hours');
            if ($lastShown > $cutoff) {
                return true;
            }
        }

        return false;
    }

    public function recordImpression(int $popupId, string $visitorId): void
    {
        $this->store->increment($popupId, $visitorId, $this->now());
    }

    public function recordDismiss(int $popupId, string $visitorId): void
    {
        $this->store->set($popupId, $visitorId, ['dismissedAt' => $this->now()]);
    }

    public function recordConvert(int $popupId, string $visitorId): void
    {
        $this->store->set($popupId, $visitorId, ['convertedAt' => $this->now()]);
    }

    private function now(): string
    {
        // Always write UTC so reads in isFatigued() round-trip regardless of server tz.
        return Time::utcNow()->format('Y-m-d H:i:s');
    }
}
