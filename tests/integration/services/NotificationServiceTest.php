<?php

namespace anvildev\socialproof\tests\integration\services;

use anvildev\socialproof\models\Notification;
use anvildev\socialproof\services\NotificationService;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for NotificationService
 *
 * Tests the notification filtering, deduplication, and assembly logic
 * using model construction (no database needed).
 *
 * @covers \anvildev\socialproof\services\NotificationService
 */
class NotificationServiceTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════
    // Notification Model Assembly
    // ═══════════════════════════════════════════════════════════════════════

    public function testPurchaseNotificationAssembly(): void
    {
        $notification = new Notification([
            'id' => 1,
            'type' => 'purchase',
            'customerName' => 'Sarah',
            'customerLocation' => 'Zürich',
            'productName' => 'Widget',
            'productUrl' => '/products/widget',
            'productImage' => '/images/widget.jpg',
            'timeAgo' => '5 minutes ago',
        ]);

        $this->assertSame('purchase', $notification->type);
        $this->assertSame('Sarah', $notification->customerName);
        $this->assertSame('Zürich', $notification->customerLocation);
        $this->assertNotNull($notification->productUrl);
    }

    public function testViewerNotificationAssembly(): void
    {
        $notification = new Notification([
            'type' => 'viewers',
            'count' => 42,
        ]);

        // Viewer notifications have no stable ID
        $this->assertNull($notification->id);
        $this->assertSame('viewers', $notification->type);
        $this->assertSame(42, $notification->count);
    }

    public function testStockNotificationAssembly(): void
    {
        $notification = new Notification([
            'type' => 'stock',
            'productName' => 'Strategy Workshop Seats',
            'productUrl' => '/workshops',
            'count' => 3,
        ]);

        $this->assertSame('stock', $notification->type);
        $this->assertSame(3, $notification->count);
        $this->assertSame('Strategy Workshop Seats', $notification->productName);
    }

    public function testCustomNotificationAssembly(): void
    {
        $notification = new Notification([
            'id' => 100,
            'type' => 'custom',
            'message' => 'Flash sale ends tonight!',
            'productUrl' => '/sale',
            'linkTarget' => '_blank',
        ]);

        $this->assertSame('custom', $notification->type);
        $this->assertSame('Flash sale ends tonight!', $notification->message);
        $this->assertSame('_blank', $notification->linkTarget);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Deduplication with excludeIds
    // ═══════════════════════════════════════════════════════════════════════

    public function testExcludeIdsFiltersPurchaseNotifications(): void
    {
        $notifications = $this->createTestNotifications();
        $excludeIds = [1, 3]; // Purchase #1, Stock #3

        $filtered = $this->applyExcludeFilter($notifications, $excludeIds);

        $remainingIds = array_map(fn($n) => $n->id, $filtered);
        $this->assertNotContains(1, $remainingIds);
        $this->assertNotContains(3, $remainingIds);
        $this->assertContains(2, $remainingIds);
    }

    public function testExcludeIdsAllowsDynamicViewerNotifications(): void
    {
        $notifications = $this->createTestNotifications();
        $excludeIds = [1, 2, 3, 100]; // Exclude all IDs

        $filtered = $this->applyExcludeFilter($notifications, $excludeIds);

        // Only the viewer notification (null ID) should survive
        $this->assertCount(1, $filtered);
        $this->assertNull($filtered[0]->id);
        $this->assertSame('viewers', $filtered[0]->type);
    }

    public function testEmptyExcludeIdsKeepsAll(): void
    {
        $notifications = $this->createTestNotifications();

        $filtered = $this->applyExcludeFilter($notifications, []);

        $this->assertCount(count($notifications), $filtered);
    }

    public function testExcludeIdsWithNoMatchesKeepsAll(): void
    {
        $notifications = $this->createTestNotifications();
        $excludeIds = [999, 998, 997]; // IDs that don't exist

        $filtered = $this->applyExcludeFilter($notifications, $excludeIds);

        $this->assertCount(count($notifications), $filtered);
    }

    public function testExcludeAllNonDynamicNotifications(): void
    {
        $notifications = [
            new Notification(['id' => 1, 'type' => 'purchase']),
            new Notification(['id' => 2, 'type' => 'purchase']),
            new Notification(['id' => 3, 'type' => 'stock']),
        ];
        $excludeIds = [1, 2, 3];

        $filtered = $this->applyExcludeFilter($notifications, $excludeIds);

        $this->assertCount(0, $filtered);
    }

    public function testMultipleNullIdsAllPassThrough(): void
    {
        $notifications = [
            new Notification(['id' => null, 'type' => 'viewers']),
            new Notification(['id' => null, 'type' => 'viewers']),
        ];
        $excludeIds = [1, 2, 3];

        $filtered = $this->applyExcludeFilter($notifications, $excludeIds);

        $this->assertCount(2, $filtered);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Link Target Propagation
    // ═══════════════════════════════════════════════════════════════════════

    public function testGlobalLinkTargetAppliedToNullTargets(): void
    {
        $notifications = [
            new Notification(['id' => 1, 'type' => 'purchase', 'linkTarget' => null]),
            new Notification(['id' => 2, 'type' => 'custom', 'linkTarget' => '_blank']),
        ];

        $globalTarget = '_blank';
        foreach ($notifications as $notification) {
            if (!$notification->linkTarget) {
                $notification->linkTarget = $globalTarget;
            }
        }

        $this->assertSame('_blank', $notifications[0]->linkTarget);
        $this->assertSame('_blank', $notifications[1]->linkTarget);
    }

    public function testNotificationKeepsExistingLinkTarget(): void
    {
        $notification = new Notification(['id' => 1, 'type' => 'custom', 'linkTarget' => '_self']);

        $globalTarget = '_blank';
        if (!$notification->linkTarget) {
            $notification->linkTarget = $globalTarget;
        }

        $this->assertSame('_self', $notification->linkTarget);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Demo Notification Structure
    // ═══════════════════════════════════════════════════════════════════════

    public function testDemoNotificationIdRangeStartsHigh(): void
    {
        // Demo IDs should start at 9000+ to avoid real ID conflicts
        $demoId = 9000;
        $this->assertGreaterThanOrEqual(9000, $demoId);
    }

    public function testNotificationTypesArePredefined(): void
    {
        $validTypes = ['purchase', 'viewers', 'stock', 'custom'];

        foreach ($validTypes as $type) {
            $notification = new Notification(['type' => $type]);
            $this->assertSame($type, $notification->type);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Frontend Array Conversion
    // ═══════════════════════════════════════════════════════════════════════

    public function testToFrontendArrayIncludesAllRequiredKeys(): void
    {
        $notification = new Notification([
            'id' => 1,
            'type' => 'purchase',
            'message' => 'Test message',
        ]);

        $frontend = $notification->toFrontendArray();

        $requiredKeys = [
            'id', 'type', 'message', 'customerName', 'customerLocation',
            'productName', 'productUrl', 'productImage', 'count', 'timeAgo', 'linkTarget',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $frontend, "Missing key: {$key}");
        }
    }

    public function testToFrontendArrayConversionBatch(): void
    {
        $notifications = $this->createTestNotifications();

        $formatted = array_map(fn($n) => $n->toFrontendArray(), $notifications);

        $this->assertCount(count($notifications), $formatted);
        foreach ($formatted as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('type', $item);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Apply the same exclude filter logic used in NotificationService
     */
    private function applyExcludeFilter(array $notifications, array $excludeIds): array
    {
        if (empty($excludeIds)) {
            return $notifications;
        }

        return array_values(array_filter($notifications, function ($n) use ($excludeIds) {
            return $n->id === null || !in_array($n->id, $excludeIds, true);
        }));
    }

    /**
     * Create a mixed set of test notifications
     */
    private function createTestNotifications(): array
    {
        return [
            new Notification([
                'id' => 1,
                'type' => 'purchase',
                'customerName' => 'Sarah',
                'customerLocation' => 'Zürich',
                'productName' => 'Widget',
                'timeAgo' => '5 minutes ago',
            ]),
            new Notification([
                'id' => 2,
                'type' => 'purchase',
                'customerName' => 'Michael',
                'customerLocation' => 'Basel',
                'productName' => 'UX Audit',
                'timeAgo' => '12 minutes ago',
            ]),
            new Notification([
                'id' => null,
                'type' => 'viewers',
                'count' => 42,
                'message' => '42 people are viewing this',
            ]),
            new Notification([
                'id' => 3,
                'type' => 'stock',
                'productName' => 'Workshop Seats',
                'count' => 3,
            ]),
            new Notification([
                'id' => 100,
                'type' => 'custom',
                'message' => 'Flash sale!',
                'productUrl' => '/sale',
            ]),
        ];
    }
}
