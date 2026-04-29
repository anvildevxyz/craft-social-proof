<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\FatigueService;
use anvildev\socialproof\services\FatigueStore;
use PHPUnit\Framework\TestCase;

class FatigueServiceTest extends TestCase
{
    private InMemoryFatigueStore $store;
    private FatigueService $service;

    protected function setUp(): void
    {
        $this->store = new InMemoryFatigueStore();
        $this->service = new FatigueService($this->store);
    }

    public function testFreshVisitorIsNotFatigued(): void
    {
        $this->assertFalse($this->service->isFatigued(42, 'visitor-a', ['maxPerVisitor' => 3]));
    }

    public function testMaxPerVisitorEnforced(): void
    {
        $rules = ['maxPerVisitor' => 2];
        $this->store->set(42, 'visitor-a', ['shownCount' => 2]);
        $this->assertTrue($this->service->isFatigued(42, 'visitor-a', $rules));
        $this->store->set(42, 'visitor-a', ['shownCount' => 1]);
        $this->assertFalse($this->service->isFatigued(42, 'visitor-a', $rules));
    }

    public function testMinHoursBetweenEnforced(): void
    {
        $rules = ['minHoursBetween' => 24, 'maxPerVisitor' => 100];
        $utc = new \DateTimeZone('UTC');
        $now = new \DateTimeImmutable('now', $utc);
        $this->store->set(42, 'visitor-a', ['shownCount' => 1, 'lastShownAt' => $now->modify('-1 hour')->format('Y-m-d H:i:s')]);
        $this->assertTrue($this->service->isFatigued(42, 'visitor-a', $rules));
        $this->store->set(42, 'visitor-a', ['shownCount' => 1, 'lastShownAt' => $now->modify('-25 hours')->format('Y-m-d H:i:s')]);
        $this->assertFalse($this->service->isFatigued(42, 'visitor-a', $rules));
    }

    public function testMinHoursBetweenTreatsStoredTimeAsUtcRegardlessOfServerTz(): void
    {
        // Regression: MySQL stores naive UTC strings; PHP used to parse them in
        // the default local TZ, making the 24h window off by the TZ offset on
        // non-UTC servers. Verify the comparison is TZ-stable by temporarily
        // shifting PHP's default tz.
        $rules = ['minHoursBetween' => 24, 'maxPerVisitor' => 100];
        $previousTz = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            // 25 hours ago in UTC
            $lastShown = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify('-25 hours')
                ->format('Y-m-d H:i:s');
            $this->store->set(42, 'visitor-tz', ['shownCount' => 1, 'lastShownAt' => $lastShown]);
            $this->assertFalse(
                $this->service->isFatigued(42, 'visitor-tz', $rules),
                '25h ago in UTC must count as outside a 24h window even when PHP tz is America/New_York.',
            );

            // 1 hour ago in UTC — still fatigued regardless of server tz
            $recent = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify('-1 hour')
                ->format('Y-m-d H:i:s');
            $this->store->set(42, 'visitor-tz-2', ['shownCount' => 1, 'lastShownAt' => $recent]);
            $this->assertTrue(
                $this->service->isFatigued(42, 'visitor-tz-2', $rules),
                '1h ago in UTC must count as inside a 24h window regardless of server tz.',
            );
        } finally {
            date_default_timezone_set($previousTz);
        }
    }

    public function testStopAfterDismiss(): void
    {
        $rules = ['stopAfterDismiss' => true, 'maxPerVisitor' => 100];
        $this->store->set(42, 'visitor-a', ['dismissedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $this->assertTrue($this->service->isFatigued(42, 'visitor-a', $rules));
    }

    public function testStopAfterConvert(): void
    {
        $rules = ['stopAfterConvert' => true, 'maxPerVisitor' => 100];
        $this->store->set(42, 'visitor-a', ['convertedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $this->assertTrue($this->service->isFatigued(42, 'visitor-a', $rules));
    }

    public function testStopFlagsRespectConfiguration(): void
    {
        $rules = ['stopAfterDismiss' => false, 'maxPerVisitor' => 100];
        $this->store->set(42, 'visitor-a', ['dismissedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $this->assertFalse($this->service->isFatigued(42, 'visitor-a', $rules));
    }

    public function testRecordImpressionIncrementsCountAndSetsLastShown(): void
    {
        $this->service->recordImpression(42, 'visitor-a');
        $row = $this->store->get(42, 'visitor-a');
        $this->assertSame(1, $row['shownCount']);
        $this->assertNotEmpty($row['lastShownAt']);

        $this->service->recordImpression(42, 'visitor-a');
        $row = $this->store->get(42, 'visitor-a');
        $this->assertSame(2, $row['shownCount']);
    }

    public function testRecordDismissSetsDismissedAt(): void
    {
        $this->service->recordDismiss(42, 'visitor-a');
        $row = $this->store->get(42, 'visitor-a');
        $this->assertNotEmpty($row['dismissedAt']);
    }

    public function testRecordConvertSetsConvertedAt(): void
    {
        $this->service->recordConvert(42, 'visitor-a');
        $row = $this->store->get(42, 'visitor-a');
        $this->assertNotEmpty($row['convertedAt']);
    }
}
