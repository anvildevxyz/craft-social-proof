# Outbound Webhooks — Design Spec

## Goal

Let an admin configure HTTP endpoints that receive signed JSON POSTs whenever a popup event fires (`impression | click | dismiss | convert`). Delivery is asynchronous via Craft's queue with exponential-backoff retry.

## Non-Goals

- Inbound webhooks (no one is calling us).
- Per-popup / per-site webhook filtering in v1. Subscriptions are global; if the admin needs Slack only for `convert` events, they subscribe only to that event type.
- Webhook delivery logging UI. v1.2 logs failures to `web.log`. A `socialproof_webhook_deliveries` table + delivery history view can come in v1.3 if there's demand.
- Hitting the queue synchronously in tests. The dispatch call records an intent; job execution is the queue's problem.

## Behavior

1. Admin configures webhook subscriptions under a new "Webhooks" tab on the plugin settings page. Each subscription has: `label`, `url`, `events` (subset of `impression|click|dismiss|convert`), `secret` (auto-generated), `enabled`.
2. When `PopupApiController::actionEvent` records an event, after `popups->recordEvent(...)` it calls `webhooks->dispatchForEvent($eventType, $contextData)`. The service enqueues one `SendWebhookJob` per matching enabled subscription. Dispatch cost on the hot path: constant-time array scan; actual HTTP is out-of-band.
3. The queue job executes a POST to the configured URL with:
   ```
   Content-Type: application/json
   X-SocialProof-Event: popup.convert
   X-SocialProof-Delivery: <uuid>
   X-SocialProof-Timestamp: <unix>
   X-SocialProof-Signature-256: sha256=<hex>
   User-Agent: AnvilDev-SocialProof/1.2.0
   ```
   Signature is `hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret)`.
4. Non-2xx response or transport exception → throw, let Craft's queue retry per its defaults (3 retries with exponential backoff). After exhaustion the failure is logged and the job is moved to the failed queue.

## Payload

```json
{
  "event": "popup.convert",
  "delivery_id": "f0e8…",
  "timestamp": 1714000000,
  "plugin_version": "1.2.0",
  "data": {
    "popup": { "id": 42, "title": "Black Friday announcement", "layout": "announcement" },
    "visitor_id": "abc…",
    "session_id": "sess…",
    "page_url": "https://example.com/shop/widget",
    "event_time": "2026-04-23T10:00:00+00:00"
  }
}
```

Deterministic JSON encoding (`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`) so receivers can recompute signatures without surprises.

## Storage

Subscriptions live in plugin settings (JSON array on `Settings::webhooks`) — not a new DB table. Rationale:

- Settings are already persisted by Craft's plugin framework and cached.
- No query patterns (subscriptions are enumerated fully per event, not filtered via SQL).
- Keeps the feature scope tight. If we eventually want delivery history or cross-env sync, we add a table then.

The `secret` field is stored in plaintext in settings (same trust level as other Craft plugin secrets). The CP UI masks it by default with a "reveal" toggle.

## Service Surface

```php
class WebhookService {
    /** @return int number of jobs enqueued */
    public function dispatchForEvent(string $event, array $data): int;

    /** Pure, for testing. */
    public static function buildSignature(string $timestamp, string $body, string $secret): string;

    public static function generateSecret(): string; // 32 random hex chars
}
```

`dispatchForEvent` is kept testable by taking a `JobQueueInterface $queue` constructor arg (same pattern as `FatigueService(FatigueStore)`). Production wiring passes `Craft::$app->getQueue()`. Unit tests pass an `InMemoryJobQueue` that appends pushed jobs to an array.

## Queue Job

`SendWebhookJob extends \craft\queue\BaseJob`:

```php
public string $url;
public string $event;
public string $deliveryId;
public int $timestamp;
public string $body;     // already-encoded JSON
public string $secret;

public function execute($queue): void {
    $client = Craft::createGuzzleClient(['timeout' => 5, 'connect_timeout' => 3]);
    $sig = WebhookService::buildSignature((string)$this->timestamp, $this->body, $this->secret);

    $res = $client->post($this->url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-SocialProof-Event' => $this->event,
            'X-SocialProof-Delivery' => $this->deliveryId,
            'X-SocialProof-Timestamp' => (string)$this->timestamp,
            'X-SocialProof-Signature-256' => 'sha256=' . $sig,
            'User-Agent' => 'AnvilDev-SocialProof/' . Plugin::getInstance()->getVersion(),
        ],
        'body' => $this->body,
        'http_errors' => false,
    ]);

    if ($res->getStatusCode() >= 300) {
        throw new \RuntimeException("Webhook POST failed: HTTP {$res->getStatusCode()}");
    }
}
```

Timeout is **5s request + 3s connect** to contain hot-path-adjacent queue workers. `http_errors => false` + explicit check lets us distinguish transport failures from HTTP failures in the thrown message.

## CP UI

New tab on `src/templates/settings.twig`: "Webhooks".

- Table: each row shows label, URL, subscribed events (badges), enabled toggle, "Copy secret" button, "Delete" button.
- "Add webhook" form below the table: label (text), URL (text, URL validator), events (4 checkboxes), enabled (yes/no). Secret is auto-generated on save.
- Edit flow: click label to open an edit form (same fields). Secret isn't editable from here; a separate "Rotate secret" button generates a new one.

Controller additions to `SettingsController`:
- `actionSaveWebhook` — upsert
- `actionDeleteWebhook`
- `actionRotateWebhookSecret`

All POST-only, permission-gated on `socialProof-manageSettings`.

## Config

Add to `Settings`:
```php
public array $webhooks = []; // [['id', 'label', 'url', 'events', 'secret', 'enabled'], ...]
```

Validation rules:
- Each entry must have `id` (UUID), `label` (string, max 80), `url` (URL validator, must be https in non-dev), `events` (subset of allowed 4), `secret` (32-hex), `enabled` (bool).
- Reject duplicate `id`s.

## Testing

- `tests/unit/services/WebhookServiceTest.php`:
  - `buildSignature` — known inputs → expected HMAC (regression for signing protocol; receivers depend on this)
  - `generateSecret` — returns 32 hex chars, different per call
  - `dispatchForEvent` with an `InMemoryJobQueue` — matches only enabled subscriptions, matches only subscribed events, produces one job per match with correct url/body
- `tests/unit/models/SettingsTest.php` — extend with webhook validation cases (valid row, missing fields, duplicate id, bad URL)

## Security Notes

- HMAC timestamp is part of the signed content — receivers can reject stale deliveries (typical: ±5 minutes).
- Secrets are min-32-chars of hex (128 bits of entropy) via `random_bytes(16)`.
- URLs must be HTTP(S); no `javascript:` or `file:`. CP form URL input + server-side URL validator enforce.

## Rollout

- Schema version unchanged (no new tables).
- Release: v1.2.0 bundled with revenue attribution, or v1.3.0 if we ship attribution alone first.
- CHANGELOG + README (new "Webhooks" section under "Events").
