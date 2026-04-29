<?php

namespace anvildev\socialproof\tests\integration\controllers;

use anvildev\socialproof\controllers\PopupsController;
use anvildev\socialproof\Plugin;
use PHPUnit\Framework\TestCase;

class PopupsControllerTest extends TestCase
{
    public function testRequiresPermissionToAccessIndex(): void
    {
        $reflection = new \ReflectionClass(PopupsController::class);
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString(
            'requirePermission(Plugin::PERMISSION_MANAGE_POPUPS)',
            $source,
            'actionIndex must gate on managePopups permission',
        );
    }

    public function testSaveActionRequiresPost(): void
    {
        $reflection = new \ReflectionClass(PopupsController::class);
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString(
            '$this->requirePostRequest();',
            $source,
            'actionSave must require POST',
        );
    }

    public function testDeleteActionRequiresPost(): void
    {
        $reflection = new \ReflectionClass(PopupsController::class);
        $source = file_get_contents($reflection->getFileName());
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, '$this->requirePostRequest();'),
            'save and delete actions must both require POST',
        );
    }

    public function testPermissionConstantIsDefined(): void
    {
        $this->assertSame('socialProof-managePopups', Plugin::PERMISSION_MANAGE_POPUPS);
    }

    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(PopupsController::class));
    }
}
