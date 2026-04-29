<?php

namespace anvildev\socialproof\tests\unit\models;

use anvildev\socialproof\models\Notification;
use PHPUnit\Framework\TestCase;

/**
 * @covers \anvildev\socialproof\models\Notification
 */
class NotificationModelTest extends TestCase
{
    // ─── Defaults ────────────────────────────────────────────────────────

    public function testDefaults(): void
    {
        $n = new Notification();

        $this->assertNull($n->id);
        $this->assertSame('', $n->name);
        $this->assertSame('purchase', $n->type);
        $this->assertTrue($n->enabled);
        $this->assertSame('bottom-left', $n->position);
        $this->assertSame(5, $n->displayDuration);
        $this->assertSame(10, $n->delayBetween);
        $this->assertNull($n->customerName);
        $this->assertNull($n->customerLocation);
        $this->assertNull($n->productName);
        $this->assertNull($n->productUrl);
        $this->assertNull($n->productImage);
        $this->assertNull($n->count);
        $this->assertNull($n->message);
        $this->assertNull($n->timeAgo);
    }

    // ─── Constructor population ──────────────────────────────────────────

    public function testConstructorPopulatesProperties(): void
    {
        $n = new Notification([
            'id' => 42,
            'type' => 'viewers',
            'customerName' => 'Sarah',
            'customerLocation' => 'Zürich',
            'productName' => 'Widget',
            'productUrl' => '/products/widget',
            'productImage' => '/images/widget.jpg',
            'count' => 15,
            'message' => 'Someone bought Widget',
            'timeAgo' => '3 minutes ago',
        ]);

        $this->assertSame(42, $n->id);
        $this->assertSame('viewers', $n->type);
        $this->assertSame('Sarah', $n->customerName);
        $this->assertSame('Zürich', $n->customerLocation);
        $this->assertSame('Widget', $n->productName);
        $this->assertSame('/products/widget', $n->productUrl);
        $this->assertSame('/images/widget.jpg', $n->productImage);
        $this->assertSame(15, $n->count);
        $this->assertSame('Someone bought Widget', $n->message);
        $this->assertSame('3 minutes ago', $n->timeAgo);
    }

    // ─── toFrontendArray ─────────────────────────────────────────────────

    public function testToFrontendArrayContainsAllFields(): void
    {
        $n = new Notification([
            'id' => 1,
            'type' => 'purchase',
            'message' => 'Sarah from Zürich purchased Widget',
            'customerName' => 'Sarah',
            'customerLocation' => 'Zürich',
            'productName' => 'Widget',
            'productUrl' => '/products/widget',
            'productImage' => '/images/widget.jpg',
            'count' => null,
            'timeAgo' => '5 minutes ago',
        ]);

        $arr = $n->toFrontendArray();

        $this->assertSame(1, $arr['id']);
        $this->assertSame('purchase', $arr['type']);
        $this->assertSame('Sarah from Zürich purchased Widget', $arr['message']);
        $this->assertSame('Sarah', $arr['customerName']);
        $this->assertSame('Zürich', $arr['customerLocation']);
        $this->assertSame('Widget', $arr['productName']);
        $this->assertSame('/products/widget', $arr['productUrl']);
        $this->assertSame('/images/widget.jpg', $arr['productImage']);
        $this->assertNull($arr['count']);
        $this->assertSame('5 minutes ago', $arr['timeAgo']);
    }

    public function testToFrontendArrayWithNullFields(): void
    {
        $n = new Notification([
            'type' => 'viewers',
            'count' => 42,
            'message' => '42 people viewing',
        ]);

        $arr = $n->toFrontendArray();

        $this->assertNull($arr['id']);
        $this->assertNull($arr['customerName']);
        $this->assertNull($arr['productUrl']);
        $this->assertNull($arr['productImage']);
        $this->assertSame(42, $arr['count']);
    }

    public function testToFrontendArrayHasExactKeys(): void
    {
        $n = new Notification();
        $arr = $n->toFrontendArray();

        $expected = ['id', 'type', 'message', 'customerName', 'customerLocation',
                     'productName', 'productUrl', 'productImage', 'count', 'timeAgo', 'linkTarget'];

        $this->assertSame($expected, array_keys($arr));
    }

    public function testToFrontendArrayExcludesInternalFields(): void
    {
        $n = new Notification([
            'name' => 'internal-name',
            'enabled' => true,
            'settings' => ['foo' => 'bar'],
            'siteId' => 1,
        ]);

        $arr = $n->toFrontendArray();

        $this->assertArrayNotHasKey('name', $arr);
        $this->assertArrayNotHasKey('enabled', $arr);
        $this->assertArrayNotHasKey('settings', $arr);
        $this->assertArrayNotHasKey('siteId', $arr);
        $this->assertArrayNotHasKey('position', $arr);
    }

    // ─── Validation ──────────────────────────────────────────────────────

    public function testValidTypesAccepted(): void
    {
        foreach (['purchase', 'viewers', 'stock', 'custom'] as $type) {
            $n = new Notification(['name' => 'test', 'type' => $type]);
            $this->assertTrue($n->validate(), "Type '{$type}' should be valid");
        }
    }

    public function testInvalidTypeRejected(): void
    {
        $n = new Notification(['name' => 'test', 'type' => 'spam']);
        $this->assertFalse($n->validate());
        $this->assertArrayHasKey('type', $n->getErrors());
    }

    public function testNameIsRequired(): void
    {
        $n = new Notification(['type' => 'purchase']);
        // name defaults to '' which should fail required validation
        $this->assertFalse($n->validate());
        $this->assertArrayHasKey('name', $n->getErrors());
    }

    public function testValidPositions(): void
    {
        foreach (['bottom-left', 'bottom-right', 'top-left', 'top-right'] as $pos) {
            $n = new Notification(['name' => 'test', 'type' => 'purchase', 'position' => $pos]);
            $this->assertTrue($n->validate(), "Position '{$pos}' should be valid");
        }
    }

    public function testInvalidPositionRejected(): void
    {
        $n = new Notification(['name' => 'test', 'type' => 'purchase', 'position' => 'middle']);
        $this->assertFalse($n->validate());
        $this->assertArrayHasKey('position', $n->getErrors());
    }

    public function testDisplayDurationBounds(): void
    {
        $n = new Notification(['name' => 'test', 'type' => 'purchase']);

        $n->displayDuration = 0;
        $this->assertFalse($n->validate());

        $n->displayDuration = 61;
        $this->assertFalse($n->validate());

        $n->displayDuration = 30;
        $this->assertTrue($n->validate());
    }

    public function testDelayBetweenBounds(): void
    {
        $n = new Notification(['name' => 'test', 'type' => 'purchase']);

        $n->delayBetween = 0;
        $this->assertFalse($n->validate());

        $n->delayBetween = 121;
        $this->assertFalse($n->validate());

        $n->delayBetween = 60;
        $this->assertTrue($n->validate());
    }
}
