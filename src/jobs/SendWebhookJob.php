<?php

namespace anvildev\socialproof\jobs;

use anvildev\socialproof\events\WebhookDeliveryEvent;
use anvildev\socialproof\helpers\Time;
use anvildev\socialproof\records\WebhookDeliveryRecord;
use anvildev\socialproof\services\WebhookService;
use Craft;
use craft\queue\BaseJob;
use yii\base\Event;

class SendWebhookJob extends BaseJob
{
    public const EVENT_DELIVERY_SUCCEEDED = 'socialproof.webhook.delivery.succeeded';
    public const EVENT_DELIVERY_FAILED = 'socialproof.webhook.delivery.failed';

    public string $subscriptionId = '';
    public string $url = '';
    public string $event = '';
    public string $deliveryId = '';
    public int $timestamp = 0;
    public string $body = '';
    public string $secret = '';
    public string $pluginVersion = '';

    public function execute($queue): void
    {
        $client = Craft::createGuzzleClient([
            'timeout' => 5,
            'connect_timeout' => 3,
        ]);

        $sig = WebhookService::buildSignature((string) $this->timestamp, $this->body, $this->secret);
        $dispatchedAt = Time::utcNow();

        try {
            $res = $client->post($this->url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-SocialProof-Event' => $this->event,
                    'X-SocialProof-Delivery' => $this->deliveryId,
                    'X-SocialProof-Timestamp' => (string) $this->timestamp,
                    'X-SocialProof-Signature-256' => 'sha256=' . $sig,
                    'User-Agent' => 'AnvilDev-SocialProof/' . $this->pluginVersion,
                ],
                'body' => $this->body,
                'http_errors' => false,
            ]);
        } catch (\Throwable $transportError) {
            $this->recordDelivery(
                dispatchedAt: $dispatchedAt,
                transportError: mb_substr($transportError->getMessage(), 0, 500),
            );
            $this->fireOutcome(self::EVENT_DELIVERY_FAILED);
            throw $transportError;
        }

        $status = $res->getStatusCode();
        $bodyText = (string) $res->getBody();
        $snippet = $bodyText !== '' ? mb_substr($bodyText, 0, 500) : null;

        $this->recordDelivery(
            dispatchedAt: $dispatchedAt,
            statusCode: $status,
            responseSnippet: $snippet,
        );

        if ($status >= 300) {
            $this->fireOutcome(self::EVENT_DELIVERY_FAILED);
            throw new \RuntimeException("Webhook POST to {$this->url} failed: HTTP {$status}");
        }

        $this->fireOutcome(self::EVENT_DELIVERY_SUCCEEDED);
    }

    protected function defaultDescription(): ?string
    {
        return "Deliver social-proof webhook ({$this->event})";
    }

    private function fireOutcome(string $eventName): void
    {
        Event::trigger(self::class, $eventName, new WebhookDeliveryEvent([
            'subscriptionId' => $this->subscriptionId,
        ]));
    }

    private function recordDelivery(
        \DateTimeImmutable $dispatchedAt,
        ?int $statusCode = null,
        ?string $responseSnippet = null,
        ?string $transportError = null,
    ): void {
        $record = new WebhookDeliveryRecord();
        $record->subscriptionId = $this->subscriptionId;
        $record->event = $this->event;
        $record->deliveryId = $this->deliveryId;
        $record->statusCode = $statusCode;
        $record->responseSnippet = $responseSnippet;
        $record->transportError = $transportError;
        $record->dispatchedAt = $dispatchedAt->format('Y-m-d H:i:s');
        $record->completedAt = Time::utcNow()->format('Y-m-d H:i:s');
        $record->save(false);
    }
}
