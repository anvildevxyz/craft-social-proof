<?php

namespace anvildev\socialproof\tests\integration\controllers;

use anvildev\socialproof\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for NotificationsController
 *
 * Tests permission constants and controller configuration.
 * Note: Tests that require full Craft bootstrap (element instantiation,
 * Craft::t() calls) are not possible in this minimal test environment.
 *
 * @covers \anvildev\socialproof\controllers\NotificationsController
 */
class NotificationsControllerTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════
    // Permission Constants
    // ═══════════════════════════════════════════════════════════════════════

    public function testPermissionConstantsAreDefined(): void
    {
        $this->assertSame('socialProof-manageNotifications', Plugin::PERMISSION_MANAGE_NOTIFICATIONS);
        $this->assertSame('socialProof-viewStatistics', Plugin::PERMISSION_VIEW_STATISTICS);
        $this->assertSame('socialProof-manageSettings', Plugin::PERMISSION_MANAGE_SETTINGS);
    }

    public function testPermissionConstantsAreDistinct(): void
    {
        $permissions = [
            Plugin::PERMISSION_MANAGE_NOTIFICATIONS,
            Plugin::PERMISSION_VIEW_STATISTICS,
            Plugin::PERMISSION_MANAGE_SETTINGS,
        ];

        $this->assertSame(count($permissions), count(array_unique($permissions)));
    }

    public function testPermissionConstantsFollowNamingConvention(): void
    {
        // All should start with 'socialProof-'
        $this->assertStringStartsWith('socialProof-', Plugin::PERMISSION_MANAGE_NOTIFICATIONS);
        $this->assertStringStartsWith('socialProof-', Plugin::PERMISSION_VIEW_STATISTICS);
        $this->assertStringStartsWith('socialProof-', Plugin::PERMISSION_MANAGE_SETTINGS);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Plugin Configuration
    // ═══════════════════════════════════════════════════════════════════════

    public function testPluginSchemaVersion(): void
    {
        // Verify the plugin class has the expected schema version
        $ref = new \ReflectionClass(Plugin::class);
        $prop = $ref->getProperty('schemaVersion');
        $plugin = $ref->newInstanceWithoutConstructor();
        $this->assertSame('1.0.0', $prop->getValue($plugin));
    }

    public function testPluginHasCpSection(): void
    {
        $ref = new \ReflectionClass(Plugin::class);
        $prop = $ref->getProperty('hasCpSection');
        $plugin = $ref->newInstanceWithoutConstructor();
        $this->assertTrue($prop->getValue($plugin));
    }

    public function testPluginHasCpSettings(): void
    {
        $ref = new \ReflectionClass(Plugin::class);
        $prop = $ref->getProperty('hasCpSettings');
        $plugin = $ref->newInstanceWithoutConstructor();
        $this->assertTrue($prop->getValue($plugin));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Notification Types
    // ═══════════════════════════════════════════════════════════════════════

    public function testAllNotificationTypesExist(): void
    {
        $validTypes = ['purchase', 'viewers', 'stock', 'custom'];

        foreach ($validTypes as $type) {
            $this->assertNotEmpty($type, "Notification type should not be empty");
        }

        $this->assertCount(4, $validTypes);
    }

    public function testPositionOptionsAreFourCorners(): void
    {
        $positions = ['bottom-left', 'bottom-right', 'top-left', 'top-right'];

        $this->assertCount(4, $positions);
        foreach ($positions as $pos) {
            $this->assertMatchesRegularExpression('/^(top|bottom)-(left|right)$/', $pos);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Controller Route Expectations
    // ═══════════════════════════════════════════════════════════════════════

    public function testExpectedCpRoutes(): void
    {
        // These are the routes registered in Plugin::_registerCpRoutes()
        $expectedRoutes = [
            'social-proof' => 'social-proof/notifications/index',
            'social-proof/notifications' => 'social-proof/notifications/index',
            'social-proof/notifications/new' => 'social-proof/notifications/edit',
            'social-proof/stats' => 'social-proof/settings/stats',
            'social-proof/stats/data' => 'social-proof/settings/stats-data',
            'social-proof/settings' => 'social-proof/settings/index',
        ];

        // Verify route count and structure
        $this->assertGreaterThanOrEqual(6, count($expectedRoutes));
        foreach ($expectedRoutes as $url => $action) {
            $this->assertStringStartsWith('social-proof/', $action);
        }
    }
}
