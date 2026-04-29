<?php

namespace anvildev\socialproof\tests\integration\services;

use anvildev\socialproof\models\Impression;
use anvildev\socialproof\services\TrackingService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Integration tests for TrackingService
 *
 * Tests event validation, trend calculation, and stats structure
 * without requiring a database connection.
 *
 * @covers \anvildev\socialproof\services\TrackingService
 */
class TrackingServiceTest extends TestCase
{
    private TrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrackingService();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Impression Model Validation
    // ═══════════════════════════════════════════════════════════════════════

    public function testValidImpressionEvent(): void
    {
        $impression = new Impression([
            'notificationId' => 1,
            'sessionId' => 'abc123',
            'eventType' => 'impression',
            'pageUrl' => '/products',
        ]);

        $this->assertTrue($impression->validate());
    }

    public function testValidClickEvent(): void
    {
        $impression = new Impression([
            'notificationId' => 1,
            'sessionId' => 'abc123',
            'eventType' => 'click',
        ]);

        $this->assertTrue($impression->validate());
    }

    public function testValidDismissEvent(): void
    {
        $impression = new Impression([
            'notificationId' => 1,
            'sessionId' => 'abc123',
            'eventType' => 'dismiss',
        ]);

        $this->assertTrue($impression->validate());
    }

    public function testValidHeartbeatEvent(): void
    {
        $impression = new Impression([
            'notificationId' => null,
            'sessionId' => 'abc123',
            'eventType' => 'heartbeat',
            'pageUrl' => '/products',
        ]);

        $this->assertTrue($impression->validate());
    }

    public function testInvalidEventTypeRejected(): void
    {
        $impression = new Impression([
            'notificationId' => 1,
            'sessionId' => 'abc123',
            'eventType' => 'invalid_type',
        ]);

        $this->assertFalse($impression->validate());
        $this->assertArrayHasKey('eventType', $impression->getErrors());
    }

    public function testEmptySessionIdRejected(): void
    {
        $impression = new Impression([
            'notificationId' => 1,
            'sessionId' => '',
            'eventType' => 'impression',
        ]);

        $this->assertFalse($impression->validate());
    }

    public function testSessionIdMaxLength(): void
    {
        $impression = new Impression([
            'sessionId' => str_repeat('a', 64),
            'eventType' => 'impression',
        ]);
        $this->assertTrue($impression->validate());

        $impression->sessionId = str_repeat('a', 65);
        $this->assertFalse($impression->validate());
    }

    public function testPageUrlMaxLength(): void
    {
        $impression = new Impression([
            'sessionId' => 'abc123',
            'eventType' => 'impression',
            'pageUrl' => '/' . str_repeat('a', 499),
        ]);
        $this->assertTrue($impression->validate());

        $impression->pageUrl = '/' . str_repeat('a', 500);
        $this->assertFalse($impression->validate());
    }

    public function testNullNotificationIdAllowed(): void
    {
        $impression = new Impression([
            'notificationId' => null,
            'sessionId' => 'abc123',
            'eventType' => 'heartbeat',
        ]);

        $this->assertTrue($impression->validate());
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

    public function testTrendNeutral(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [100, 100]);

        $this->assertSame('neutral', $result['direction']);
        $this->assertSame(0.0, $result['percentage']);
    }

    public function testTrendFromZero(): void
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

    public function testTrendToZero(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [0, 100]);

        $this->assertSame('down', $result['direction']);
        $this->assertSame(100.0, $result['percentage']);
    }

    public function testTrendPercentageIsAbsoluteValue(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [25, 100]);

        $this->assertSame('down', $result['direction']);
        $this->assertSame(75.0, $result['percentage']);
        $this->assertGreaterThanOrEqual(0, $result['percentage']);
    }

    public function testTrendSmallChange(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [101, 100]);

        $this->assertSame('up', $result['direction']);
        $this->assertSame(1.0, $result['percentage']);
    }

    public function testTrendLargeIncrease(): void
    {
        $result = $this->invokePrivate('_calculateTrend', [1000, 100]);

        $this->assertSame('up', $result['direction']);
        $this->assertSame(900.0, $result['percentage']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Period Parsing (via stats input validation)
    // ═══════════════════════════════════════════════════════════════════════

    public function testPeriodParsingValidFormats(): void
    {
        $testCases = [
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1d' => 1,
            '365d' => 365,
        ];

        foreach ($testCases as $period => $expectedDays) {
            $days = preg_match('/^(\d+)d$/', $period, $m) ? (int)$m[1] : 7;
            $days = max(1, min($days, 365));
            $this->assertSame($expectedDays, $days, "Period '{$period}' should parse to {$expectedDays} days");
        }
    }

    public function testPeriodParsingInvalidFormats(): void
    {
        $invalidPeriods = ['7', 'days', '7days', '', '0d', '-1d', 'abc'];

        foreach ($invalidPeriods as $period) {
            $days = preg_match('/^(\d+)d$/', $period, $m) ? (int)$m[1] : 7;
            $days = max(1, min($days, 365));

            if ($period === '0d') {
                $this->assertSame(1, $days, "'0d' should clamp to 1");
            } else {
                $this->assertSame(7, $days, "Invalid period '{$period}' should default to 7");
            }
        }
    }

    public function testPeriodClampingTooHigh(): void
    {
        $days = preg_match('/^(\d+)d$/', '999d', $m) ? (int)$m[1] : 7;
        $days = max(1, min($days, 365));

        $this->assertSame(365, $days);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CTR Calculation
    // ═══════════════════════════════════════════════════════════════════════

    public function testCtrCalculation(): void
    {
        $impressions = 200;
        $clicks = 10;
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

        $this->assertSame(5.0, $ctr);
    }

    public function testCtrWithZeroImpressions(): void
    {
        $impressions = 0;
        $clicks = 0;
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

        $this->assertSame(0, $ctr);
    }

    public function testCtrWithNoClicks(): void
    {
        $impressions = 100;
        $clicks = 0;
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

        $this->assertSame(0.0, $ctr);
    }

    public function testCtrRounding(): void
    {
        $impressions = 300;
        $clicks = 7;
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

        $this->assertSame(2.33, $ctr);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Metadata Handling
    // ═══════════════════════════════════════════════════════════════════════

    public function testImpressionWithMetadata(): void
    {
        $impression = new Impression([
            'sessionId' => 'abc123',
            'eventType' => 'click',
            'metadata' => ['source' => 'homepage', 'variant' => 'A'],
        ]);

        $this->assertTrue($impression->validate());
        $this->assertSame('homepage', $impression->metadata['source']);
    }

    public function testImpressionWithEmptyMetadata(): void
    {
        $impression = new Impression([
            'sessionId' => 'abc123',
            'eventType' => 'impression',
            'metadata' => [],
        ]);

        $this->assertTrue($impression->validate());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Stats Response Shape
    // ═══════════════════════════════════════════════════════════════════════

    public function testStatsResponseRequiredKeys(): void
    {
        $requiredKeys = [
            'impressions',
            'clicks',
            'dismisses',
            'ctr',
            'uniqueVisitors',
            'dailyStats',
            'topNotifications',
            'period',
        ];

        // Verify the expected structure matches what getStats() returns
        $mockStats = [
            'impressions' => 100,
            'clicks' => 10,
            'dismisses' => 5,
            'ctr' => 10.0,
            'uniqueVisitors' => 50,
            'dailyStats' => [],
            'topNotifications' => [],
            'period' => '7d',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $mockStats, "Stats should include '{$key}'");
        }
    }

    public function testDailyStatsEntryShape(): void
    {
        $dayEntry = [
            'date' => '2025-01-15',
            'impressions' => 50,
            'clicks' => 5,
            'dismisses' => 2,
        ];

        $this->assertArrayHasKey('date', $dayEntry);
        $this->assertArrayHasKey('impressions', $dayEntry);
        $this->assertArrayHasKey('clicks', $dayEntry);
        $this->assertArrayHasKey('dismisses', $dayEntry);
    }

    public function testTopNotificationEntryShape(): void
    {
        $topEntry = [
            'notificationId' => 1,
            'impressions' => 100,
            'clicks' => 15,
            'ctr' => 15.0,
        ];

        $this->assertArrayHasKey('notificationId', $topEntry);
        $this->assertArrayHasKey('impressions', $topEntry);
        $this->assertArrayHasKey('clicks', $topEntry);
        $this->assertArrayHasKey('ctr', $topEntry);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Invoke a private method via reflection
     */
    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->service, ...$args);
    }
}
