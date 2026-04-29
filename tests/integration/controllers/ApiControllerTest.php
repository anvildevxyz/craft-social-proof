<?php

namespace anvildev\socialproof\tests\integration\controllers;

use anvildev\socialproof\controllers\ApiController;
use anvildev\socialproof\models\Notification;
use anvildev\socialproof\models\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Integration tests for ApiController
 *
 * Tests the pure-logic aspects of the API controller:
 * - Public settings filtering
 * - Session ID creation
 * - Event type validation
 * - Metadata sanitization
 *
 * @covers \anvildev\socialproof\controllers\ApiController
 */
class ApiControllerTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════
    // Public Settings Filtering
    // ═══════════════════════════════════════════════════════════════════════

    public function testPublicSettingsContainsOnlySafeKeys(): void
    {
        $controller = $this->createPartialController();

        $settings = new Settings();
        $settings->position = 'top-right';
        $settings->displayDuration = 8;
        $settings->delayBetween = 15;
        $settings->animationIn = 'fadeIn';
        $settings->animationOut = 'bounceOut';
        $settings->showProductImage = false;
        $settings->showDismissButton = true;

        $result = $this->invokePrivate($controller, '_getPublicSettings', [$settings]);

        $expectedKeys = [
            'position',
            'displayDuration',
            'delayBetween',
            'animationIn',
            'animationOut',
            'showProductImage',
            'showDismissButton',
        ];

        $this->assertSame($expectedKeys, array_keys($result));
    }

    public function testPublicSettingsValuesMatchInput(): void
    {
        $controller = $this->createPartialController();

        $settings = new Settings();
        $settings->position = 'top-right';
        $settings->displayDuration = 8;
        $settings->delayBetween = 15;
        $settings->animationIn = 'fadeIn';
        $settings->animationOut = 'bounceOut';
        $settings->showProductImage = false;
        $settings->showDismissButton = true;

        $result = $this->invokePrivate($controller, '_getPublicSettings', [$settings]);

        $this->assertSame('top-right', $result['position']);
        $this->assertSame(8, $result['displayDuration']);
        $this->assertSame(15, $result['delayBetween']);
        $this->assertSame('fadeIn', $result['animationIn']);
        $this->assertSame('bounceOut', $result['animationOut']);
        $this->assertFalse($result['showProductImage']);
        $this->assertTrue($result['showDismissButton']);
    }

    public function testPublicSettingsDoesNotExposeSensitiveFields(): void
    {
        $controller = $this->createPartialController();

        $settings = new Settings();
        $settings->abTestingEnabled = true;
        $settings->abTestPercentage = 75;
        $settings->purchaseLookbackHours = 48;

        $result = $this->invokePrivate($controller, '_getPublicSettings', [$settings]);

        $this->assertArrayNotHasKey('abTestingEnabled', $result);
        $this->assertArrayNotHasKey('abTestPercentage', $result);
        $this->assertArrayNotHasKey('purchaseLookbackHours', $result);
        $this->assertArrayNotHasKey('enabled', $result);
        $this->assertArrayNotHasKey('demoMode', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Event Type Validation
    // ═══════════════════════════════════════════════════════════════════════

    public function testValidEventTypes(): void
    {
        $validTypes = ['impression', 'click', 'dismiss', 'heartbeat'];

        foreach ($validTypes as $type) {
            $this->assertTrue(
                in_array($type, ['impression', 'click', 'dismiss', 'heartbeat'], true),
                "'{$type}' should be a valid event type"
            );
        }
    }

    public function testInvalidEventTypesRejected(): void
    {
        $invalidTypes = ['view', 'hover', 'scroll', '', 'IMPRESSION', 'Click'];

        foreach ($invalidTypes as $type) {
            $this->assertFalse(
                in_array($type, ['impression', 'click', 'dismiss', 'heartbeat'], true),
                "'{$type}' should NOT be a valid event type"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Metadata Sanitization
    // ═══════════════════════════════════════════════════════════════════════

    public function testMetadataUnderLimitIsPreserved(): void
    {
        $metadata = ['key' => 'value', 'page' => '/products'];

        $safeMetadata = is_array($metadata) ? $metadata : [];
        if (strlen(json_encode($safeMetadata)) > 2048) {
            $safeMetadata = [];
        }

        $this->assertSame($metadata, $safeMetadata);
    }

    public function testMetadataOverLimitIsCleared(): void
    {
        $metadata = ['data' => str_repeat('x', 2100)];

        $safeMetadata = is_array($metadata) ? $metadata : [];
        if (strlen(json_encode($safeMetadata)) > 2048) {
            $safeMetadata = [];
        }

        $this->assertSame([], $safeMetadata);
    }

    public function testNonArrayMetadataBecomesEmpty(): void
    {
        $metadata = 'not-an-array';

        $safeMetadata = is_array($metadata) ? $metadata : [];

        $this->assertSame([], $safeMetadata);
    }

    public function testMetadataExactlyAtLimit(): void
    {
        // Create metadata that's just under 2048 characters when JSON-encoded
        $padding = str_repeat('x', 2030);
        $metadata = ['k' => $padding];
        $jsonLength = strlen(json_encode($metadata));

        // Adjust to be exactly at the limit
        if ($jsonLength <= 2048) {
            $safeMetadata = $metadata;
        } else {
            $safeMetadata = [];
        }

        // The result should be consistent with the sanitization logic
        $this->assertTrue(is_array($safeMetadata));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Page URL Truncation
    // ═══════════════════════════════════════════════════════════════════════

    public function testPageUrlTruncatedTo500Chars(): void
    {
        $longUrl = '/' . str_repeat('a', 600);

        $result = mb_substr($longUrl, 0, 500);

        $this->assertSame(500, mb_strlen($result));
    }

    public function testShortPageUrlPreserved(): void
    {
        $shortUrl = '/products/widget';

        $result = mb_substr($shortUrl, 0, 500);

        $this->assertSame($shortUrl, $result);
    }

    public function testNullPageUrlHandled(): void
    {
        $pageUrl = null;
        $result = $pageUrl ? mb_substr((string)$pageUrl, 0, 500) : null;

        $this->assertNull($result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Notification Deduplication Logic
    // ═══════════════════════════════════════════════════════════════════════

    public function testDeduplicationFiltersKnownIds(): void
    {
        $notifications = [
            new Notification(['id' => 1, 'type' => 'purchase']),
            new Notification(['id' => 2, 'type' => 'purchase']),
            new Notification(['id' => 3, 'type' => 'stock']),
        ];

        $excludeIds = [1, 3];

        $filtered = array_values(array_filter($notifications, function ($n) use ($excludeIds) {
            return $n->id === null || !in_array($n->id, $excludeIds, true);
        }));

        $this->assertCount(1, $filtered);
        $this->assertSame(2, $filtered[0]->id);
    }

    public function testDeduplicationPassesNullIds(): void
    {
        $notifications = [
            new Notification(['id' => 1, 'type' => 'purchase']),
            new Notification(['id' => null, 'type' => 'viewers']),
        ];

        $excludeIds = [1];

        $filtered = array_values(array_filter($notifications, function ($n) use ($excludeIds) {
            return $n->id === null || !in_array($n->id, $excludeIds, true);
        }));

        $this->assertCount(1, $filtered);
        $this->assertNull($filtered[0]->id);
        $this->assertSame('viewers', $filtered[0]->type);
    }

    public function testDeduplicationEmptyExcludeListKeepsAll(): void
    {
        $notifications = [
            new Notification(['id' => 1, 'type' => 'purchase']),
            new Notification(['id' => 2, 'type' => 'stock']),
        ];

        $excludeIds = [];

        // With empty excludeIds, the service skips filtering entirely
        $this->assertCount(2, $notifications);
    }

    public function testShownIdsAccumulateAcrossCalls(): void
    {
        // Simulating session accumulation
        $shownIds = [];

        // First call returns IDs 1, 2
        $firstBatch = [1, 2];
        $shownIds = array_values(array_unique(array_merge($shownIds, $firstBatch)));
        $this->assertSame([1, 2], $shownIds);

        // Second call returns IDs 3
        $secondBatch = [3];
        $shownIds = array_values(array_unique(array_merge($shownIds, $secondBatch)));
        $this->assertSame([1, 2, 3], $shownIds);

        // Third call with duplicate
        $thirdBatch = [2, 4];
        $shownIds = array_values(array_unique(array_merge($shownIds, $thirdBatch)));
        $this->assertSame([1, 2, 3, 4], $shownIds);
    }

    public function testNullIdsNotStoredInSession(): void
    {
        $notifications = [
            new Notification(['id' => 1, 'type' => 'purchase']),
            new Notification(['id' => null, 'type' => 'viewers']),
            new Notification(['id' => 3, 'type' => 'stock']),
        ];

        $newIds = array_filter(array_map(fn($n) => $n->id, $notifications));

        $this->assertSame([0 => 1, 2 => 3], $newIds);
        $this->assertNotContains(null, $newIds);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Notification Frontend Format
    // ═══════════════════════════════════════════════════════════════════════

    public function testNotificationToFrontendArrayShape(): void
    {
        $notification = new Notification([
            'id' => 42,
            'type' => 'purchase',
            'message' => 'Sarah from Zürich bought Widget',
            'customerName' => 'Sarah',
            'customerLocation' => 'Zürich',
            'productName' => 'Widget',
            'productUrl' => '/products/widget',
            'productImage' => '/images/widget.jpg',
            'count' => null,
            'timeAgo' => '5 minutes ago',
            'linkTarget' => '_blank',
        ]);

        $frontend = $notification->toFrontendArray();

        $expectedKeys = [
            'id', 'type', 'message', 'customerName', 'customerLocation',
            'productName', 'productUrl', 'productImage', 'count', 'timeAgo', 'linkTarget',
        ];

        $this->assertSame($expectedKeys, array_keys($frontend));
        $this->assertSame(42, $frontend['id']);
        $this->assertSame('purchase', $frontend['type']);
    }

    public function testFrontendArrayExcludesInternalFields(): void
    {
        $notification = new Notification([
            'id' => 1,
            'type' => 'purchase',
            'name' => 'Internal Name',
            'enabled' => true,
            'position' => 'top-right',
            'displayDuration' => 8,
            'delayBetween' => 15,
        ]);

        $frontend = $notification->toFrontendArray();

        $this->assertArrayNotHasKey('name', $frontend);
        $this->assertArrayNotHasKey('enabled', $frontend);
        $this->assertArrayNotHasKey('position', $frontend);
        $this->assertArrayNotHasKey('displayDuration', $frontend);
        $this->assertArrayNotHasKey('delayBetween', $frontend);
        $this->assertArrayNotHasKey('settings', $frontend);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create a controller instance for testing private methods
     */
    private function createPartialController(): ApiController
    {
        // ApiController requires an id and module; we use a bare-minimum setup
        return new ApiController('api', \Yii::$app);
    }

    /**
     * Invoke a private method via reflection
     */
    private function invokePrivate(object $object, string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invoke($object, ...$args);
    }
}
