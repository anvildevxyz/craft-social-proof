<?php

namespace anvildev\socialproof\services;

/**
 * Per-subscription circuit-breaker state for webhook dispatch. Implementations
 * must be tolerant of concurrent updates (multiple queue workers calling
 * markFailure simultaneously); the production CacheBreakerStore relies on
 * Craft's cache + a small read-modify-write window — collisions just smear
 * the count by 1 or 2, which is acceptable for an N-strike breaker.
 *
 * @phpstan-type BreakerState array{failures: int, openUntil: int|null}
 */
interface BreakerStore
{
    /**
     * True if the circuit for this subscription is currently OPEN — i.e.
     * dispatch should skip the subscription. False otherwise (CLOSED or
     * HALF-OPEN, both of which permit the next delivery attempt).
     */
    public function isOpen(string $subscriptionId, int $now): bool;

    /**
     * Reset the state for this subscription: zero the consecutive-failure
     * count and clear any open deadline. Called after a delivery succeeds.
     */
    public function markSuccess(string $subscriptionId): void;

    /**
     * Increment the consecutive-failure count. If the new count is at or
     * above the threshold, set the circuit OPEN until $now + $cooldownSeconds.
     * Each subsequent failure during OPEN extends the deadline by another
     * cooldown window so a permanently broken receiver doesn't dispatch
     * faster than 1 attempt per cooldown.
     */
    public function markFailure(string $subscriptionId, int $threshold, int $cooldownSeconds, int $now): void;

    /**
     * Diagnostic accessor — returns ['failures' => int, 'openUntil' => ?int].
     * Intended for tests and CP debug surfaces; production code should rely
     * on isOpen / markSuccess / markFailure.
     *
     * @return BreakerState
     */
    public function inspect(string $subscriptionId): array;
}
