<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\FatigueStore;

/**
 * In-memory FatigueStore for unit tests. Not autoloaded in production.
 */
class InMemoryFatigueStore implements FatigueStore
{
    /** @var array<string, array<string,mixed>> */
    private array $rows = [];

    private function key(int $popupId, string $visitorId): string
    {
        return $popupId . ':' . $visitorId;
    }

    public function get(int $popupId, string $visitorId): ?array
    {
        return $this->rows[$this->key($popupId, $visitorId)] ?? null;
    }

    public function set(int $popupId, string $visitorId, array $data): void
    {
        $existing = $this->get($popupId, $visitorId) ?? [
            'shownCount' => 0,
            'lastShownAt' => null,
            'dismissedAt' => null,
            'convertedAt' => null,
        ];
        $this->rows[$this->key($popupId, $visitorId)] = array_merge($existing, $data);
    }

    public function increment(int $popupId, string $visitorId, string $timestamp): void
    {
        $existing = $this->get($popupId, $visitorId) ?? ['shownCount' => 0];
        $this->set($popupId, $visitorId, [
            'shownCount' => ($existing['shownCount'] ?? 0) + 1,
            'lastShownAt' => $timestamp,
        ]);
    }
}
