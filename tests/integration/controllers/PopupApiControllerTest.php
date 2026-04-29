<?php

namespace anvildev\socialproof\tests\integration\controllers;

use anvildev\socialproof\controllers\PopupApiController;
use PHPUnit\Framework\TestCase;

class PopupApiControllerTest extends TestCase
{
    public function testCandidatesActionExists(): void
    {
        $this->assertTrue(method_exists(PopupApiController::class, 'actionCandidates'));
    }

    public function testEventActionExists(): void
    {
        $this->assertTrue(method_exists(PopupApiController::class, 'actionEvent'));
    }

    public function testEventValidatesEventTypeWhitelist(): void
    {
        $reflection = new \ReflectionClass(PopupApiController::class);
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString(
            'PopupService::EVENT_TYPES',
            $source,
            'actionEvent must whitelist event types via PopupService::EVENT_TYPES',
        );
        $this->assertStringContainsString(
            "'invalid eventType'",
            $source,
            'actionEvent must reject unknown event types',
        );
    }

    public function testEventDoesNotRequireAcceptsJson(): void
    {
        // sendBeacon() cannot set an Accept header, so the event endpoint must
        // not call requireAcceptsJson() — it'd 400 every beacon request.
        $reflection = new \ReflectionClass(PopupApiController::class);
        $source = $reflection->getMethod('actionEvent')->getDocComment()
            . "\n" . file_get_contents($reflection->getFileName());
        // Extract just the actionEvent body by locating the method start
        $all = file_get_contents($reflection->getFileName());
        $eventStart = strpos($all, 'public function actionEvent');
        $eventEnd = strpos($all, 'public function', $eventStart + 10);
        if ($eventEnd === false) {
            $eventEnd = strlen($all);
        }
        $eventBody = substr($all, $eventStart, $eventEnd - $eventStart);
        $this->assertStringNotContainsString(
            'requireAcceptsJson',
            $eventBody,
            'actionEvent must not require Accept: application/json (navigator.sendBeacon cannot set that header).',
        );
    }

    public function testEventRequiresPost(): void
    {
        $reflection = new \ReflectionClass(PopupApiController::class);
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString(
            '$this->requirePostRequest();',
            $source,
            'actionEvent must require POST',
        );
    }

    public function testAllowsAnonymous(): void
    {
        $reflection = new \ReflectionClass(PopupApiController::class);
        $prop = $reflection->getProperty('allowAnonymous');
        $this->assertNotNull($prop);
    }

    public function testVisitorCookieConstants(): void
    {
        $this->assertSame('social_proof_popup_visitor', PopupApiController::VISITOR_COOKIE);
        $this->assertGreaterThan(0, PopupApiController::VISITOR_COOKIE_TTL);
    }

    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(PopupApiController::class));
    }
}
