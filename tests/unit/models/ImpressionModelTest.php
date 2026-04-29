<?php

namespace anvildev\socialproof\tests\unit\models;

use anvildev\socialproof\models\Impression;
use PHPUnit\Framework\TestCase;

/**
 * @covers \anvildev\socialproof\models\Impression
 */
class ImpressionModelTest extends TestCase
{
    // ─── Defaults ────────────────────────────────────────────────────────

    public function testDefaults(): void
    {
        $i = new Impression();

        $this->assertNull($i->id);
        $this->assertNull($i->notificationId);
        $this->assertSame('', $i->sessionId);
        $this->assertSame('impression', $i->eventType);
        $this->assertNull($i->pageUrl);
        $this->assertSame([], $i->metadata);
        $this->assertNull($i->dateCreated);
    }

    // ─── Validation ──────────────────────────────────────────────────────

    public function testValidImpressionPasses(): void
    {
        $i = new Impression([
            'sessionId' => 'abc123def456',
            'eventType' => 'impression',
        ]);
        $this->assertTrue($i->validate(), json_encode($i->getErrors()));
    }

    public function testSessionIdRequired(): void
    {
        $i = new Impression(['eventType' => 'impression']);
        $this->assertFalse($i->validate());
        $this->assertArrayHasKey('sessionId', $i->getErrors());
    }

    public function testEventTypeRequired(): void
    {
        $i = new Impression(['sessionId' => 'abc123', 'eventType' => '']);
        $this->assertFalse($i->validate());
        $this->assertArrayHasKey('eventType', $i->getErrors());
    }

    /**
     * @dataProvider validEventTypeProvider
     */
    public function testValidEventTypes(string $type): void
    {
        $i = new Impression(['sessionId' => 'abc123', 'eventType' => $type]);
        $this->assertTrue($i->validate(), "Event type '{$type}' should be valid");
    }

    public static function validEventTypeProvider(): array
    {
        return [
            'impression' => ['impression'],
            'click'      => ['click'],
            'dismiss'    => ['dismiss'],
            'heartbeat'  => ['heartbeat'],
        ];
    }

    public function testInvalidEventTypeRejected(): void
    {
        $i = new Impression(['sessionId' => 'abc123', 'eventType' => 'hover']);
        $this->assertFalse($i->validate());
        $this->assertArrayHasKey('eventType', $i->getErrors());
    }

    public function testSessionIdMaxLength(): void
    {
        $i = new Impression([
            'sessionId' => str_repeat('a', 65),
            'eventType' => 'impression',
        ]);
        $this->assertFalse($i->validate());
        $this->assertArrayHasKey('sessionId', $i->getErrors());
    }

    public function testSessionIdAtMaxLength(): void
    {
        $i = new Impression([
            'sessionId' => str_repeat('a', 64),
            'eventType' => 'impression',
        ]);
        $this->assertTrue($i->validate());
    }

    public function testPageUrlMaxLength(): void
    {
        $i = new Impression([
            'sessionId' => 'abc123',
            'eventType' => 'impression',
            'pageUrl' => str_repeat('x', 501),
        ]);
        $this->assertFalse($i->validate());
        $this->assertArrayHasKey('pageUrl', $i->getErrors());
    }

    public function testNotificationIdAcceptsNull(): void
    {
        $i = new Impression([
            'sessionId' => 'abc123',
            'eventType' => 'impression',
            'notificationId' => null,
        ]);
        $this->assertTrue($i->validate());
    }

    public function testNotificationIdAcceptsInteger(): void
    {
        $i = new Impression([
            'sessionId' => 'abc123',
            'eventType' => 'impression',
            'notificationId' => 42,
        ]);
        $this->assertTrue($i->validate());
    }

    // ─── Constructor population ──────────────────────────────────────────

    public function testConstructorPopulatesAllFields(): void
    {
        $i = new Impression([
            'notificationId' => 5,
            'sessionId' => 'session-xyz',
            'eventType' => 'click',
            'pageUrl' => '/products/widget',
            'metadata' => ['source' => 'popup'],
        ]);

        $this->assertSame(5, $i->notificationId);
        $this->assertSame('session-xyz', $i->sessionId);
        $this->assertSame('click', $i->eventType);
        $this->assertSame('/products/widget', $i->pageUrl);
        $this->assertSame(['source' => 'popup'], $i->metadata);
    }
}
