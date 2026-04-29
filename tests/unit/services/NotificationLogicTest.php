<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\models\Settings;
use anvildev\socialproof\services\NotificationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests the pure-logic private methods of NotificationService
 * using reflection (no DB or Craft app required).
 *
 * @covers \anvildev\socialproof\services\NotificationService
 */
class NotificationLogicTest extends TestCase
{
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Instantiate without Craft bootstrap — we only test pure methods
        $this->service = new NotificationService();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Template Rendering
    // ═══════════════════════════════════════════════════════════════════════

    public function testRenderTemplateBasicSubstitution(): void
    {
        $result = $this->invokePrivate('_renderTemplate', [
            '{customer} from {location} purchased {product}',
            ['customer' => 'Sarah', 'location' => 'Zürich', 'product' => 'Widget'],
        ]);

        $this->assertSame('Sarah from Zürich purchased Widget', $result);
    }

    public function testRenderTemplateNoPlaceholders(): void
    {
        $result = $this->invokePrivate('_renderTemplate', [
            'Static message with no variables',
            ['customer' => 'Sarah'],
        ]);

        $this->assertSame('Static message with no variables', $result);
    }

    public function testRenderTemplateEmptyVariables(): void
    {
        $result = $this->invokePrivate('_renderTemplate', [
            '{customer} bought {product}',
            [],
        ]);

        $this->assertSame('{customer} bought {product}', $result);
    }

    public function testRenderTemplateNumericValues(): void
    {
        $result = $this->invokePrivate('_renderTemplate', [
            '{count} people are viewing this',
            ['count' => 42],
        ]);

        $this->assertSame('42 people are viewing this', $result);
    }

    public function testRenderTemplatePartialMatch(): void
    {
        $result = $this->invokePrivate('_renderTemplate', [
            '{customer} bought {product}',
            ['customer' => 'Sarah'],
        ]);

        $this->assertSame('Sarah bought {product}', $result);
    }

    public function testRenderTemplateSpecialCharacters(): void
    {
        $result = $this->invokePrivate('_renderTemplate', [
            '{customer} from {location}',
            ['customer' => 'José', 'location' => 'São Paulo'],
        ]);

        $this->assertSame('José from São Paulo', $result);
    }

    public function testRenderTemplateEmptyString(): void
    {
        $result = $this->invokePrivate('_renderTemplate', [
            '',
            ['customer' => 'Sarah'],
        ]);

        $this->assertSame('', $result);
    }

    public function testRenderTemplateEmptyValue(): void
    {
        $result = $this->invokePrivate('_renderTemplate', [
            '{customer} from {location}',
            ['customer' => '', 'location' => 'Zürich'],
        ]);

        $this->assertSame(' from Zürich', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Time Formatting
    // ═══════════════════════════════════════════════════════════════════════

    public function testFormatTimeAgoJustNow(): void
    {
        $date = (new \DateTime())->format('Y-m-d H:i:s');
        $result = $this->invokePrivate('_formatTimeAgo', [$date]);

        $this->assertSame('just now', $result);
    }

    public function testFormatTimeAgoMinutes(): void
    {
        $date = (new \DateTime())->modify('-5 minutes')->format('Y-m-d H:i:s');
        $result = $this->invokePrivate('_formatTimeAgo', [$date]);

        $this->assertSame('5 minutes ago', $result);
    }

    public function testFormatTimeAgoSingleMinute(): void
    {
        $date = (new \DateTime())->modify('-1 minute')->format('Y-m-d H:i:s');
        $result = $this->invokePrivate('_formatTimeAgo', [$date]);

        $this->assertSame('1 minute ago', $result);
    }

    public function testFormatTimeAgoHours(): void
    {
        $date = (new \DateTime())->modify('-3 hours')->format('Y-m-d H:i:s');
        $result = $this->invokePrivate('_formatTimeAgo', [$date]);

        $this->assertSame('3 hours ago', $result);
    }

    public function testFormatTimeAgoSingleHour(): void
    {
        $date = (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');
        $result = $this->invokePrivate('_formatTimeAgo', [$date]);

        $this->assertSame('1 hour ago', $result);
    }

    public function testFormatTimeAgoDays(): void
    {
        $date = (new \DateTime())->modify('-2 days')->format('Y-m-d H:i:s');
        $result = $this->invokePrivate('_formatTimeAgo', [$date]);

        $this->assertSame('2 days ago', $result);
    }

    public function testFormatTimeAgoSingleDay(): void
    {
        $date = (new \DateTime())->modify('-1 day')->format('Y-m-d H:i:s');
        $result = $this->invokePrivate('_formatTimeAgo', [$date]);

        $this->assertSame('1 day ago', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // A/B Testing Bucket Assignment
    // ═══════════════════════════════════════════════════════════════════════

    public function testABTestingDeterministic(): void
    {
        $result1 = $this->invokePrivate('_isInTestGroup', ['session-abc', 50]);
        $result2 = $this->invokePrivate('_isInTestGroup', ['session-abc', 50]);

        $this->assertSame($result1, $result2, 'Same session should get same bucket');
    }

    public function testABTestingDifferentSessionsCanDiffer(): void
    {
        // Generate enough sessions to statistically get both true and false
        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $this->invokePrivate('_isInTestGroup', ["session-{$i}", 50]);
        }

        $this->assertContains(true, $results, '50% should include some true');
        $this->assertContains(false, $results, '50% should include some false');
    }

    public function testABTesting100PercentAlwaysIncluded(): void
    {
        // At 99% (max valid), almost all should be included
        $included = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($this->invokePrivate('_isInTestGroup', ["session-{$i}", 99])) {
                $included++;
            }
        }

        $this->assertGreaterThan(80, $included, '99% should include most sessions');
    }

    public function testABTesting1PercentMostExcluded(): void
    {
        $included = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($this->invokePrivate('_isInTestGroup', ["session-{$i}", 1])) {
                $included++;
            }
        }

        $this->assertLessThan(20, $included, '1% should exclude most sessions');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // URL Pattern Matching
    // ═══════════════════════════════════════════════════════════════════════

    public function testUrlMatchingNoPatterns(): void
    {
        $settings = new Settings();
        // Empty patterns = match all
        $result = $this->invokePrivate('_matchesUrlPatterns', ['/any/page', $settings]);

        $this->assertTrue($result);
    }

    public function testUrlMatchingIncludePatternMatch(): void
    {
        $settings = new Settings();
        $settings->includedUrlPatterns = ['/products/*', '/shop/*'];

        $this->assertTrue($this->invokePrivate('_matchesUrlPatterns', ['/products/widget', $settings]));
        $this->assertTrue($this->invokePrivate('_matchesUrlPatterns', ['/shop/category', $settings]));
    }

    public function testUrlMatchingIncludePatternNoMatch(): void
    {
        $settings = new Settings();
        $settings->includedUrlPatterns = ['/products/*'];

        $this->assertFalse($this->invokePrivate('_matchesUrlPatterns', ['/about', $settings]));
        $this->assertFalse($this->invokePrivate('_matchesUrlPatterns', ['/checkout', $settings]));
    }

    public function testUrlMatchingExcludePattern(): void
    {
        $settings = new Settings();
        $settings->excludedUrlPatterns = ['/checkout/*', '/account/*'];

        $this->assertTrue($this->invokePrivate('_matchesUrlPatterns', ['/products/widget', $settings]));
        $this->assertFalse($this->invokePrivate('_matchesUrlPatterns', ['/checkout/payment', $settings]));
        $this->assertFalse($this->invokePrivate('_matchesUrlPatterns', ['/account/orders', $settings]));
    }

    public function testUrlMatchingIncludeAndExcludeCombined(): void
    {
        $settings = new Settings();
        $settings->includedUrlPatterns = ['/shop/*'];
        $settings->excludedUrlPatterns = ['/shop/checkout'];

        $this->assertTrue($this->invokePrivate('_matchesUrlPatterns', ['/shop/products', $settings]));
        $this->assertFalse($this->invokePrivate('_matchesUrlPatterns', ['/shop/checkout', $settings]));
        $this->assertFalse($this->invokePrivate('_matchesUrlPatterns', ['/about', $settings]));
    }

    public function testUrlMatchingExactPattern(): void
    {
        $settings = new Settings();
        $settings->includedUrlPatterns = ['/products'];

        $this->assertTrue($this->invokePrivate('_matchesUrlPatterns', ['/products', $settings]));
        $this->assertFalse($this->invokePrivate('_matchesUrlPatterns', ['/products/widget', $settings]));
    }

    public function testUrlMatchingWildcardOnly(): void
    {
        $settings = new Settings();
        $settings->includedUrlPatterns = ['*'];

        $this->assertTrue($this->invokePrivate('_matchesUrlPatterns', ['/anything', $settings]));
        $this->assertTrue($this->invokePrivate('_matchesUrlPatterns', ['/deep/nested/path', $settings]));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Invoke a private method on the service via reflection
     */
    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->service, ...$args);
    }
}
