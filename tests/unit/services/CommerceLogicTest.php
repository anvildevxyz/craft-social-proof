<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\CommerceService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests the pure-logic private methods of CommerceService.
 *
 * @covers \anvildev\socialproof\services\CommerceService
 */
class CommerceLogicTest extends TestCase
{
    private CommerceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommerceService();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Country Name Lookup
    // ═══════════════════════════════════════════════════════════════════════

    public function testKnownCountryCodes(): void
    {
        $this->assertSame('United States', $this->invokePrivate('_getCountryName', ['US']));
        $this->assertSame('United Kingdom', $this->invokePrivate('_getCountryName', ['GB']));
        $this->assertSame('Canada', $this->invokePrivate('_getCountryName', ['CA']));
        $this->assertSame('Australia', $this->invokePrivate('_getCountryName', ['AU']));
        $this->assertSame('Germany', $this->invokePrivate('_getCountryName', ['DE']));
        $this->assertSame('France', $this->invokePrivate('_getCountryName', ['FR']));
        $this->assertSame('Netherlands', $this->invokePrivate('_getCountryName', ['NL']));
        $this->assertSame('Belgium', $this->invokePrivate('_getCountryName', ['BE']));
        $this->assertSame('Switzerland', $this->invokePrivate('_getCountryName', ['CH']));
        $this->assertSame('Austria', $this->invokePrivate('_getCountryName', ['AT']));
    }

    public function testUnknownCountryCodeReturnsSelf(): void
    {
        $this->assertSame('JP', $this->invokePrivate('_getCountryName', ['JP']));
        $this->assertSame('BR', $this->invokePrivate('_getCountryName', ['BR']));
        $this->assertSame('XX', $this->invokePrivate('_getCountryName', ['XX']));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Refund transaction predicate
    // ═══════════════════════════════════════════════════════════════════════

    public function testRefundWithSuccessStatusShouldBeHandled(): void
    {
        $this->assertTrue(
            $this->service->shouldHandleRefundTransaction('refund', 'success'),
        );
    }

    public function testRefundWithFailedStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('refund', 'failed'),
        );
    }

    public function testRefundWithProcessingStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('refund', 'processing'),
        );
    }

    public function testPurchaseWithSuccessStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('purchase', 'success'),
        );
    }

    public function testCaptureWithSuccessStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('capture', 'success'),
        );
    }

    public function testAuthorizeWithSuccessStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('authorize', 'success'),
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Order status change predicate
    // ═══════════════════════════════════════════════════════════════════════

    public function testStatusInExcludedListShouldBeHandled(): void
    {
        $this->assertTrue(
            $this->service->shouldHandleStatusChange('cancelled', ['cancelled']),
        );
    }

    public function testStatusInLargerExcludedListShouldBeHandled(): void
    {
        $this->assertTrue(
            $this->service->shouldHandleStatusChange('fraud', ['cancelled', 'fraud', 'chargeback']),
        );
    }

    public function testStatusNotInExcludedListShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleStatusChange('processing', ['cancelled']),
        );
    }

    public function testNullStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleStatusChange(null, ['cancelled']),
        );
    }

    public function testEmptyExcludedListShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleStatusChange('cancelled', []),
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->service, ...$args);
    }
}
