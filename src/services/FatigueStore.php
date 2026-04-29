<?php

namespace anvildev\socialproof\services;

/**
 * Abstraction over the socialproof_popup_fatigue table so FatigueService is
 * unit-testable without a database. Implemented by DbFatigueStore for production.
 *
 * @phpstan-type FatigueRow array{shownCount:int,lastShownAt:?string,dismissedAt:?string,convertedAt:?string}
 */
interface FatigueStore
{
    /**
     * @return FatigueRow|null
     */
    public function get(int $popupId, string $visitorId): ?array;

    /** @param array<string,mixed> $data */
    public function set(int $popupId, string $visitorId, array $data): void;

    public function increment(int $popupId, string $visitorId, string $timestamp): void;
}
