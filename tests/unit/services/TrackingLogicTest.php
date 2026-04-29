<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\TrackingService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests the pure-logic private methods of TrackingService.
 *
 * @covers \anvildev\socialproof\services\TrackingService
 */
class TrackingLogicTest extends TestCase
{
    private TrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrackingService();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Trend Calculation
    // ═══════════════════════════════════════════════════════════════════════

    public function testTrendUpward(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [150, 100]);

        $this->assertSame('up', $result['direction']);
        $this->assertSame(50.0, $result['percentage']);
    }

    public function testTrendDownward(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [50, 100]);

        $this->assertSame('down', $result['direction']);
        $this->assertSame(50.0, $result['percentage']);
    }

    public function testTrendNeutralSameValues(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [100, 100]);

        $this->assertSame('neutral', $result['direction']);
        $this->assertSame(0.0, $result['percentage']);
    }

    public function testTrendFromZeroPrevious(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [50, 0]);

        $this->assertSame('up', $result['direction']);
        $this->assertSame(100, $result['percentage']);
    }

    public function testTrendBothZero(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [0, 0]);

        $this->assertSame('neutral', $result['direction']);
        $this->assertSame(0, $result['percentage']);
    }

    public function testTrendCurrentZeroPreviousPositive(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [0, 100]);

        $this->assertSame('down', $result['direction']);
        $this->assertSame(100.0, $result['percentage']);
    }

    public function testTrendSmallChange(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [103, 100]);

        $this->assertSame('up', $result['direction']);
        $this->assertSame(3.0, $result['percentage']);
    }

    public function testTrendLargeIncrease(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [1000, 10]);

        $this->assertSame('up', $result['direction']);
        $this->assertSame(9900.0, $result['percentage']);
    }

    public function testTrendPercentageIsAbsolute(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [25, 100]);

        $this->assertSame('down', $result['direction']);
        // Percentage should be positive (absolute value) even for downward trends
        $this->assertGreaterThan(0, $result['percentage']);
        $this->assertSame(75.0, $result['percentage']);
    }

    public function testTrendRoundsToOneDecimal(): void
    {
        // 33.333...% change
        $result = $this->invokePrivate('_calculateTrend', [4, 3]);

        $this->assertSame('up', $result['direction']);
        $this->assertSame(33.3, $result['percentage']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Period Validation (tests the regex + clamping logic from getStats)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider periodParsingProvider
     */
    public function testPeriodParsing(string $input, int $expected): void
    {
        // Mirrors the validation logic in getStats()
        $days = preg_match('/^(\d+)d$/', $input, $m) ? (int)$m[1] : 7;
        $days = max(1, min($days, 365));
        $this->assertSame($expected, $days);
    }

    public static function periodParsingProvider(): array
    {
        return [
            'valid 7d'         => ['7d', 7],
            'valid 30d'        => ['30d', 30],
            'valid 90d'        => ['90d', 90],
            'garbage input'    => ['abc', 7],       // falls back to 7
            'negative'         => ['-7d', 7],       // regex rejects
            'zero'             => ['0d', 1],        // clamped to 1
            'huge value'       => ['9999d', 365],   // clamped to 365
            'empty string'     => ['', 7],          // falls back to 7
            'just number'      => ['30', 7],        // missing 'd' suffix
            'decimal'          => ['7.5d', 7],      // regex rejects decimal
        ];
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
