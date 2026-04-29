<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\jobs\SendWebhookJob;
use anvildev\socialproof\services\WebhookService;
use PHPUnit\Framework\TestCase;

class WebhookServiceTest extends TestCase
{
    public function testBuildSignatureIsStable(): void
    {
        $sig = WebhookService::buildSignature('1714000000', '{"a":1}', 'my-secret');
        // Locking the protocol: HMAC-SHA256 over "timestamp.body"
        $this->assertSame(
            hash_hmac('sha256', '1714000000.{"a":1}', 'my-secret'),
            $sig,
        );
        $this->assertSame(64, strlen($sig));
    }

    public function testGenerateSecretIsHexAndUnique(): void
    {
        $a = WebhookService::generateSecret();
        $b = WebhookService::generateSecret();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $a);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $b);
        $this->assertNotSame($a, $b);
    }

    public function testDispatchForEventOnlyMatchesEnabledSubscriptions(): void
    {
        $queue = new InMemoryJobQueue();
        $subs = [
            ['id' => '1', 'label' => 'x', 'url' => 'https://a.test', 'events' => ['popup.convert'], 'secret' => str_repeat('a', 32), 'enabled' => true],
            ['id' => '2', 'label' => 'y', 'url' => 'https://b.test', 'events' => ['popup.convert'], 'secret' => str_repeat('b', 32), 'enabled' => false],
        ];
        $service = new WebhookService($queue, $subs, '1.2.0');
        $count = $service->dispatchForEvent('popup.convert', ['popup' => ['id' => 1]]);
        $this->assertSame(1, $count);
        $this->assertCount(1, $queue->jobs);
        $this->assertInstanceOf(SendWebhookJob::class, $queue->jobs[0]);
        $this->assertSame('https://a.test', $queue->jobs[0]->url);
    }

    public function testDispatchForEventOnlyMatchesSubscribedEvents(): void
    {
        $queue = new InMemoryJobQueue();
        $subs = [
            ['id' => '1', 'label' => 'x', 'url' => 'https://a.test', 'events' => ['popup.impression'], 'secret' => str_repeat('a', 32), 'enabled' => true],
        ];
        $service = new WebhookService($queue, $subs, '1.2.0');
        $this->assertSame(0, $service->dispatchForEvent('popup.convert', []));
        $this->assertCount(0, $queue->jobs);
    }

    public function testDispatchedJobCarriesSignableBody(): void
    {
        $queue = new InMemoryJobQueue();
        $subs = [
            ['id' => '1', 'label' => 'x', 'url' => 'https://a.test', 'events' => ['popup.convert'], 'secret' => 'abc', 'enabled' => true],
        ];
        $service = new WebhookService($queue, $subs, '1.2.0');
        $service->dispatchForEvent('popup.convert', ['popup' => ['id' => 42]]);
        $job = $queue->jobs[0];

        $decoded = json_decode($job->body, true);
        $this->assertSame('popup.convert', $decoded['event']);
        $this->assertSame($job->deliveryId, $decoded['delivery_id']);
        $this->assertSame(42, $decoded['data']['popup']['id']);
        $expected = WebhookService::buildSignature((string) $job->timestamp, $job->body, 'abc');
        $this->assertSame(64, strlen($expected));
    }

    public function testBreakerOpensAfterThresholdFailures(): void
    {
        $queue = new InMemoryJobQueue();
        $breaker = new InMemoryBreakerStore();
        $subs = [
            ['id' => 'sub-1', 'label' => 'x', 'url' => 'https://a.test', 'events' => ['popup.impression'], 'secret' => str_repeat('a', 32), 'enabled' => true],
        ];
        $service = new WebhookService($queue, $subs, '1.2.0', $breaker, 3, 60);

        $service->markDeliveryFailure('sub-1');
        $service->markDeliveryFailure('sub-1');
        $this->assertFalse($breaker->isOpen('sub-1', time()));
        $service->markDeliveryFailure('sub-1');
        $this->assertTrue($breaker->isOpen('sub-1', time()));

        $count = $service->dispatchForEvent('popup.impression', []);
        $this->assertSame(0, $count);
        $this->assertCount(0, $queue->jobs);

        $futureNow = time() + 120;
        $this->assertFalse($breaker->isOpen('sub-1', $futureNow));
    }

    public function testBreakerSuccessClearsState(): void
    {
        $queue = new InMemoryJobQueue();
        $breaker = new InMemoryBreakerStore();
        $subs = [
            ['id' => 'sub-1', 'label' => 'x', 'url' => 'https://a.test', 'events' => ['popup.impression'], 'secret' => str_repeat('a', 32), 'enabled' => true],
        ];
        $service = new WebhookService($queue, $subs, '1.2.0', $breaker, 3, 60);

        $service->markDeliveryFailure('sub-1');
        $service->markDeliveryFailure('sub-1');
        $this->assertSame(2, $breaker->inspect('sub-1')['failures']);

        $service->markDeliverySuccess('sub-1');
        $this->assertSame(0, $breaker->inspect('sub-1')['failures']);
        $this->assertNull($breaker->inspect('sub-1')['openUntil']);
    }

    public function testBreakerThresholdZeroDisablesBreaker(): void
    {
        $queue = new InMemoryJobQueue();
        $breaker = new InMemoryBreakerStore();
        $subs = [
            ['id' => 'sub-1', 'label' => 'x', 'url' => 'https://a.test', 'events' => ['popup.impression'], 'secret' => str_repeat('a', 32), 'enabled' => true],
        ];
        $service = new WebhookService($queue, $subs, '1.2.0', $breaker, 0, 60);

        for ($i = 0; $i < 100; $i++) {
            $service->markDeliveryFailure('sub-1');
        }
        $this->assertSame(0, $breaker->inspect('sub-1')['failures']);
        $this->assertSame(1, $service->dispatchForEvent('popup.impression', []));
    }

    public function testJobCarriesSubscriptionId(): void
    {
        $queue = new InMemoryJobQueue();
        $subs = [
            ['id' => 'sub-xyz', 'label' => 'x', 'url' => 'https://a.test', 'events' => ['popup.click'], 'secret' => str_repeat('a', 32), 'enabled' => true],
        ];
        $service = new WebhookService($queue, $subs, '1.2.0');
        $service->dispatchForEvent('popup.click', []);
        $this->assertSame('sub-xyz', $queue->jobs[0]->subscriptionId);
    }
}
