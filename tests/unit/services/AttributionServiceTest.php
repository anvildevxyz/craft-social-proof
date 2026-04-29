<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\records\PopupAttributionRecord;
use anvildev\socialproof\services\AttributionService;
use PHPUnit\Framework\TestCase;

class AttributionServiceTest extends TestCase
{
    private InMemoryAttributionStore $store;
    private AttributionService $service;

    protected function setUp(): void
    {
        $this->store = new InMemoryAttributionStore();
        $this->service = new AttributionService($this->store);
    }

    public function testRecordConvertWritesPendingRow(): void
    {
        $this->service->recordConvert(popupId: 42, visitorId: 'v-1', sessionId: 's-1');
        $this->assertCount(1, $this->store->rows);
        $row = reset($this->store->rows);
        $this->assertSame(42, $row['popupId']);
        $this->assertSame('v-1', $row['visitorId']);
        $this->assertSame(PopupAttributionRecord::STATUS_PENDING, $row['status']);
    }

    public function testOrderWithinWindowGetsAttributed(): void
    {
        $this->service->recordConvert(42, 'v-1', 's-1');
        $result = $this->service->attributeOrder(
            visitorId: 'v-1',
            sessionId: 's-1',
            orderId: 99,
            orderTotal: '149.9900',
            currency: 'USD',
            completedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            windowHours: 24,
        );
        $this->assertSame(1, $result);
        $row = reset($this->store->rows);
        $this->assertSame(PopupAttributionRecord::STATUS_ATTRIBUTED, $row['status']);
        $this->assertSame(99, $row['orderId']);
    }

    public function testOrderOutsideWindowIsNotAttributed(): void
    {
        $this->store->rows[1] = [
            'id' => 1, 'popupId' => 42, 'visitorId' => 'v-1', 'sessionId' => 's-1',
            'orderId' => null, 'orderTotal' => null, 'currency' => null,
            'status' => PopupAttributionRecord::STATUS_PENDING,
            'convertedAt' => (new \DateTimeImmutable('-30 hours', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'attributedAt' => null,
        ];

        $result = $this->service->attributeOrder(
            visitorId: 'v-1',
            sessionId: null,
            orderId: 99,
            orderTotal: '10.00',
            currency: 'USD',
            completedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            windowHours: 24,
        );
        $this->assertNull($result);
        $this->assertSame(PopupAttributionRecord::STATUS_PENDING, $this->store->rows[1]['status']);
    }

    public function testLastClickWinsAmongMultiplePending(): void
    {
        $this->service->recordConvert(10, 'v-1', null); // older
        usleep(10_000);
        $this->service->recordConvert(20, 'v-1', null); // newer — should win

        $this->service->attributeOrder('v-1', null, 99, '50.00', 'USD', new \DateTimeImmutable('now', new \DateTimeZone('UTC')), 24);

        $attributed = array_filter($this->store->rows, fn ($r) => $r['status'] === PopupAttributionRecord::STATUS_ATTRIBUTED);
        $this->assertCount(1, $attributed);
        $winner = reset($attributed);
        $this->assertSame(20, $winner['popupId']);
    }

    public function testSessionIdFallbackWhenVisitorIdMissing(): void
    {
        $this->service->recordConvert(42, 'v-original', 's-shared');
        $result = $this->service->attributeOrder(
            visitorId: 'v-different',
            sessionId: 's-shared',
            orderId: 99, orderTotal: '10.00', currency: 'USD',
            completedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            windowHours: 24,
        );
        $this->assertSame(1, $result);
    }

    public function testRevenueByPopupAggregates(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->service->recordConvert(10, 'v-a', null);
        $this->service->attributeOrder('v-a', null, 1, '100.0000', 'USD', $now, 24);
        $this->service->recordConvert(10, 'v-b', null);
        $this->service->attributeOrder('v-b', null, 2, '50.0000', 'USD', $now, 24);
        $this->service->recordConvert(20, 'v-c', null);
        $this->service->attributeOrder('v-c', null, 3, '25.0000', 'USD', $now, 24);

        $out = $this->service->revenueByPopup(30);
        $this->assertCount(2, $out);
        $this->assertSame('150.0000', $out[10]['revenue']);
        $this->assertSame(2, $out[10]['orders']);
        $this->assertSame('25.0000', $out[20]['revenue']);
    }

    public function testCleanupExpiresOldPendingRows(): void
    {
        $this->store->rows[1] = [
            'id' => 1, 'popupId' => 1, 'visitorId' => 'v', 'sessionId' => null,
            'orderId' => null, 'orderTotal' => null, 'currency' => null,
            'status' => PopupAttributionRecord::STATUS_PENDING,
            'convertedAt' => (new \DateTimeImmutable('-45 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'attributedAt' => null,
        ];
        $count = $this->service->cleanupExpired(30);
        $this->assertSame(1, $count);
        $this->assertSame(PopupAttributionRecord::STATUS_EXPIRED, $this->store->rows[1]['status']);
    }
}
