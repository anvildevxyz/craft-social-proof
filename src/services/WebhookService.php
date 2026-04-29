<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\helpers\Time;
use anvildev\socialproof\jobs\SendWebhookJob;
use Craft;

/**
 * @phpstan-import-type WebhookSubscription from \anvildev\socialproof\models\Webhook
 */
class WebhookService
{
    /**
     * @param list<WebhookSubscription> $subscriptions
     */
    public function __construct(
        private JobQueueInterface $queue,
        private array $subscriptions,
        private string $pluginVersion,
        private ?BreakerStore $breaker = null,
        private int $breakerThreshold = 5,
        private int $breakerCooldownSeconds = 300,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function dispatchForEvent(string $event, array $data): int
    {
        $count = 0;
        $now = time();
        foreach ($this->subscriptions as $sub) {
            if (!($sub['enabled'] ?? false)) {
                continue;
            }
            if (!in_array($event, $sub['events'] ?? [], true)) {
                continue;
            }
            $subId = (string) ($sub['id'] ?? '');
            if ($this->breakerEnabled() && $subId !== '' && $this->breaker->isOpen($subId, $now)) {
                continue;
            }
            $deliveryId = self::generateDeliveryId();
            $timestamp = $now;
            $body = json_encode([
                'event' => $event,
                'delivery_id' => $deliveryId,
                'timestamp' => $timestamp,
                'plugin_version' => $this->pluginVersion,
                'data' => $data,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $job = new SendWebhookJob();
            $job->subscriptionId = $subId;
            $job->url = (string) $sub['url'];
            $job->event = $event;
            $job->deliveryId = $deliveryId;
            $job->timestamp = $timestamp;
            $job->body = $body;
            $job->secret = (string) $sub['secret'];
            $job->pluginVersion = $this->pluginVersion;

            $this->queue->push($job);
            $count++;
        }
        return $count;
    }

    public function markDeliverySuccess(string $subscriptionId): void
    {
        if ($this->breakerEnabled() && $subscriptionId !== '') {
            $this->breaker->markSuccess($subscriptionId);
        }
    }

    public function markDeliveryFailure(string $subscriptionId): void
    {
        if ($this->breakerEnabled() && $subscriptionId !== '') {
            $this->breaker->markFailure(
                $subscriptionId,
                $this->breakerThreshold,
                $this->breakerCooldownSeconds,
                time(),
            );
        }
    }

    public function getBreaker(): ?BreakerStore
    {
        return $this->breaker;
    }

    private function breakerEnabled(): bool
    {
        return $this->breaker !== null && $this->breakerThreshold > 0;
    }

    public static function buildSignature(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function generateDeliveryId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 4122
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    public function cleanupOldDeliveries(int $daysToKeep = 30): int
    {
        $cutoff = Time::utcNow()->modify("-{$daysToKeep} days")->format('Y-m-d H:i:s');

        return (int) Craft::$app->getDb()->createCommand()
            ->delete('{{%socialproof_webhook_deliveries}}', ['<', 'dispatchedAt', $cutoff])
            ->execute();
    }
}
