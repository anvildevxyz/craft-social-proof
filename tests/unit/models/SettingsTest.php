<?php

namespace anvildev\socialproof\tests\unit\models;

use anvildev\socialproof\models\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \anvildev\socialproof\models\Settings
 */
class SettingsTest extends TestCase
{
    // ─── Defaults ────────────────────────────────────────────────────────

    public function testDefaultsAreSet(): void
    {
        $s = new Settings();

        $this->assertTrue($s->enabled);
        $this->assertSame(10, $s->maxNotificationsPerSession);
        $this->assertTrue($s->purchaseEnabled);
        $this->assertSame(24, $s->purchaseLookbackHours);
        $this->assertTrue($s->anonymizeCustomers);
        $this->assertSame('bottom-left', $s->position);
        $this->assertSame(5, $s->displayDuration);
        $this->assertSame(10, $s->delayBetween);
        $this->assertSame('slideIn', $s->animationIn);
        $this->assertSame('fadeOut', $s->animationOut);
        $this->assertTrue($s->showProductImage);
        $this->assertTrue($s->showDismissButton);
        $this->assertFalse($s->abTestingEnabled);
        $this->assertSame(50, $s->abTestPercentage);
        $this->assertTrue($s->demoMode);
        $this->assertSame([], $s->excludedProductTypes);
        $this->assertSame([], $s->includedCategories);
        $this->assertSame([], $s->includedUrlPatterns);
        $this->assertSame([], $s->excludedUrlPatterns);
    }

    public function testDefaultPurchaseTemplate(): void
    {
        $s = new Settings();
        $this->assertStringContainsString('{customer}', $s->purchaseTemplate);
        $this->assertStringContainsString('{location}', $s->purchaseTemplate);
        $this->assertStringContainsString('{product}', $s->purchaseTemplate);
    }

    public function testDefaultViewersTemplate(): void
    {
        $s = new Settings();
        $this->assertStringContainsString('{count}', $s->viewersTemplate);
    }

    public function testDefaultStockTemplate(): void
    {
        $s = new Settings();
        $this->assertStringContainsString('{count}', $s->stockTemplate);
    }

    // ─── Validation: valid values ────────────────────────────────────────

    public function testDefaultsPassValidation(): void
    {
        $s = new Settings();
        $this->assertTrue($s->validate(), 'Defaults must validate: ' . json_encode($s->getErrors()));
    }

    /**
     * @dataProvider validPositionProvider
     */
    public function testValidPositions(string $pos): void
    {
        $s = new Settings();
        $s->position = $pos;
        $this->assertTrue($s->validate());
    }

    public static function validPositionProvider(): array
    {
        return [
            'bottom-left'  => ['bottom-left'],
            'bottom-right' => ['bottom-right'],
            'top-left'     => ['top-left'],
            'top-right'    => ['top-right'],
        ];
    }

    /**
     * @dataProvider validViewersModeProvider
     */
    public function testValidViewersModes(string $mode): void
    {
        $s = new Settings();
        $s->viewersMode = $mode;
        $this->assertTrue($s->validate());
    }

    public static function validViewersModeProvider(): array
    {
        return [
            'real'       => ['real'],
            'calculated' => ['calculated'],
            'static'     => ['static'],
        ];
    }

    /**
     * @dataProvider validAnimationInProvider
     */
    public function testValidAnimationIn(string $anim): void
    {
        $s = new Settings();
        $s->animationIn = $anim;
        $this->assertTrue($s->validate());
    }

    public static function validAnimationInProvider(): array
    {
        return [
            'slideIn'  => ['slideIn'],
            'fadeIn'   => ['fadeIn'],
            'bounceIn' => ['bounceIn'],
        ];
    }

    /**
     * @dataProvider validAnimationOutProvider
     */
    public function testValidAnimationOut(string $anim): void
    {
        $s = new Settings();
        $s->animationOut = $anim;
        $this->assertTrue($s->validate());
    }

    public static function validAnimationOutProvider(): array
    {
        return [
            'slideOut'  => ['slideOut'],
            'fadeOut'   => ['fadeOut'],
            'bounceOut' => ['bounceOut'],
        ];
    }

    // ─── Validation: invalid values ──────────────────────────────────────

    public function testInvalidPositionFails(): void
    {
        $s = new Settings();
        $s->position = 'center';
        $this->assertFalse($s->validate());
        $this->assertArrayHasKey('position', $s->getErrors());
    }

    public function testInvalidViewersModeFails(): void
    {
        $s = new Settings();
        $s->viewersMode = 'magic';
        $this->assertFalse($s->validate());
        $this->assertArrayHasKey('viewersMode', $s->getErrors());
    }

    public function testInvalidAnimationInFails(): void
    {
        $s = new Settings();
        $s->animationIn = 'explodeIn';
        $this->assertFalse($s->validate());
        $this->assertArrayHasKey('animationIn', $s->getErrors());
    }

    public function testInvalidAnimationOutFails(): void
    {
        $s = new Settings();
        $s->animationOut = 'explodeOut';
        $this->assertFalse($s->validate());
        $this->assertArrayHasKey('animationOut', $s->getErrors());
    }

    // ─── Validation: numeric bounds ──────────────────────────────────────

    public function testDisplayDurationTooHighFails(): void
    {
        $s = new Settings();
        $s->displayDuration = 61;
        $this->assertFalse($s->validate());
        $this->assertArrayHasKey('displayDuration', $s->getErrors());
    }

    public function testDisplayDurationTooLowFails(): void
    {
        $s = new Settings();
        $s->displayDuration = 0;
        $this->assertFalse($s->validate());
        $this->assertArrayHasKey('displayDuration', $s->getErrors());
    }

    public function testDelayBetweenTooHighFails(): void
    {
        $s = new Settings();
        $s->delayBetween = 121;
        $this->assertFalse($s->validate());
        $this->assertArrayHasKey('delayBetween', $s->getErrors());
    }

    public function testMaxNotificationsBounds(): void
    {
        $s = new Settings();
        $s->maxNotificationsPerSession = 0;
        $this->assertFalse($s->validate());

        $s->maxNotificationsPerSession = 101;
        $this->assertFalse($s->validate());

        $s->maxNotificationsPerSession = 50;
        $this->assertTrue($s->validate());
    }

    public function testLookbackHoursBounds(): void
    {
        $s = new Settings();
        $s->purchaseLookbackHours = 0;
        $this->assertFalse($s->validate());

        $s->purchaseLookbackHours = 169;  // > 168
        $this->assertFalse($s->validate());

        $s->purchaseLookbackHours = 72;
        $this->assertTrue($s->validate());
    }

    public function testAbTestPercentageBounds(): void
    {
        $s = new Settings();
        $s->abTestPercentage = 0;
        $this->assertFalse($s->validate());

        $s->abTestPercentage = 100;
        $this->assertFalse($s->validate());

        $s->abTestPercentage = 1;
        $this->assertTrue($s->validate());

        $s->abTestPercentage = 99;
        $this->assertTrue($s->validate());
    }

    public function testStockThresholdBounds(): void
    {
        $s = new Settings();
        $s->stockThreshold = 0;
        $this->assertFalse($s->validate());

        $s->stockThreshold = 1001;
        $this->assertFalse($s->validate());

        $s->stockThreshold = 25;
        $this->assertTrue($s->validate());
    }

    public function testViewersMinimumBounds(): void
    {
        $s = new Settings();
        $s->viewersMinimum = -1;
        $this->assertFalse($s->validate());

        $s->viewersMinimum = 1001;
        $this->assertFalse($s->validate());

        $s->viewersMinimum = 0;
        $this->assertTrue($s->validate());
    }

    public function testViewersMultiplierBounds(): void
    {
        $s = new Settings();
        $s->viewersMultiplier = 0;
        $this->assertFalse($s->validate());

        $s->viewersMultiplier = 11;
        $this->assertFalse($s->validate());

        $s->viewersMultiplier = 5;
        $this->assertTrue($s->validate());
    }

    // ─── Round-trip serialization ────────────────────────────────────────

    public function testToArrayContainsAllSettings(): void
    {
        $s = new Settings();
        $arr = $s->toArray();

        $expected = [
            'enabled', 'maxNotificationsPerSession', 'purchaseEnabled',
            'purchaseLookbackHours', 'anonymizeCustomers', 'purchaseTemplate',
            'viewersEnabled', 'viewersMode', 'viewersMinimum', 'viewersMultiplier',
            'viewersTemplate', 'stockEnabled', 'stockThreshold', 'stockTemplate',
            'position', 'displayDuration', 'delayBetween', 'animationIn', 'animationOut',
            'showProductImage', 'showDismissButton', 'abTestingEnabled', 'abTestPercentage',
            'demoMode', 'excludedProductTypes', 'includedCategories',
            'includedUrlPatterns', 'excludedUrlPatterns',
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $arr, "toArray() is missing key: {$key}");
        }
    }

    public function testSetAttributesRoundTrip(): void
    {
        $original = new Settings();
        $original->enabled = false;
        $original->position = 'top-right';
        $original->displayDuration = 8;
        $original->demoMode = false;
        $original->excludedProductTypes = ['digital', 'subscription'];
        $original->includedUrlPatterns = ['/products/*', '/shop/*'];

        $arr = $original->toArray();

        $restored = new Settings();
        $restored->setAttributes($arr, false);

        $this->assertSame($original->enabled, $restored->enabled);
        $this->assertSame($original->position, $restored->position);
        $this->assertSame($original->displayDuration, $restored->displayDuration);
        $this->assertSame($original->demoMode, $restored->demoMode);
        $this->assertSame($original->excludedProductTypes, $restored->excludedProductTypes);
        $this->assertSame($original->includedUrlPatterns, $restored->includedUrlPatterns);
    }

    public function testConfigFileOverrideSimulation(): void
    {
        // Simulates what Craft does: create model, apply config overrides
        $s = new Settings();
        $overrides = [
            'enabled' => false,
            'position' => 'top-right',
            'demoMode' => false,
        ];
        $s->setAttributes($overrides, false);

        $this->assertFalse($s->enabled);
        $this->assertSame('top-right', $s->position);
        $this->assertFalse($s->demoMode);
        // Non-overridden properties keep defaults
        $this->assertSame(5, $s->displayDuration);
        $this->assertTrue($s->validate());
    }

    public function testPartialOverridePreservesDefaults(): void
    {
        $s = new Settings();
        $s->setAttributes(['position' => 'bottom-right'], false);

        $this->assertTrue($s->enabled, 'enabled should keep default');
        $this->assertSame('bottom-right', $s->position);
        $this->assertSame(5, $s->displayDuration, 'displayDuration should keep default');
    }

    // ─── Template strings ────────────────────────────────────────────────

    public function testTemplateLengthValidation(): void
    {
        $s = new Settings();
        $s->purchaseTemplate = str_repeat('x', 501);
        $this->assertFalse($s->validate());
        $this->assertArrayHasKey('purchaseTemplate', $s->getErrors());
    }

    public function testWebhookValidRow(): void
    {
        $s = new \anvildev\socialproof\models\Settings();
        $s->webhooks = [[
            'id' => 'b2a1c3d4-e5f6-7890-1234-567890abcdef',
            'label' => 'Slack alerts',
            'url' => 'https://hooks.example.com/abc',
            'events' => ['popup.convert'],
            'secret' => str_repeat('a', 32),
            'enabled' => true,
        ]];
        $this->assertTrue($s->validate(['webhooks']));
    }

    public function testWebhookRejectsBadUrl(): void
    {
        $s = new \anvildev\socialproof\models\Settings();
        $s->webhooks = [[
            'id' => 'b2a1c3d4-e5f6-7890-1234-567890abcdef',
            'label' => 'bad',
            'url' => 'ftp://example.com',
            'events' => ['popup.convert'],
            'secret' => str_repeat('a', 32),
            'enabled' => true,
        ]];
        $this->assertFalse($s->validate(['webhooks']));
    }

    public function testWebhookRejectsUnknownEvent(): void
    {
        $s = new \anvildev\socialproof\models\Settings();
        $s->webhooks = [[
            'id' => 'b2a1c3d4-e5f6-7890-1234-567890abcdef',
            'label' => 'bad',
            'url' => 'https://x.test',
            'events' => ['popup.somethingelse'],
            'secret' => str_repeat('a', 32),
            'enabled' => true,
        ]];
        $this->assertFalse($s->validate(['webhooks']));
    }

    public function testWebhookRejectsDuplicateIds(): void
    {
        $s = new \anvildev\socialproof\models\Settings();
        $s->webhooks = [
            ['id' => 'x', 'label' => 'a', 'url' => 'https://x.test', 'events' => ['popup.convert'], 'secret' => str_repeat('a', 32), 'enabled' => true],
            ['id' => 'x', 'label' => 'b', 'url' => 'https://x.test', 'events' => ['popup.convert'], 'secret' => str_repeat('a', 32), 'enabled' => true],
        ];
        $this->assertFalse($s->validate(['webhooks']));
    }
}
