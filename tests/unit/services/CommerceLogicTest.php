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
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->service, ...$args);
    }
}
