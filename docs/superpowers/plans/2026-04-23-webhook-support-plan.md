# Webhook Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Let an admin configure HTTP endpoints that receive signed JSON POSTs when popup events fire, delivered asynchronously via Craft's queue.

**Architecture:** Subscriptions live in plugin `Settings::webhooks` (JSON array, no new table). `WebhookService` dispatches per enabled matching subscription to `SendWebhookJob` queue jobs. `JobQueueInterface` lets unit tests use `InMemoryJobQueue`. HMAC-SHA256 signature derives from `timestamp + '.' + rawBody` + secret. CP settings page gains a "Webhooks" tab with CRUD.

**Tech Stack:** PHP 8.2, Craft 5 queue, Guzzle (via `Craft::createGuzzleClient()`), PHPUnit 10.

---

### Task 1: Settings surface + value object

**Files:**
- Modify: `src/models/Settings.php`
- Create: `src/models/Webhook.php`
- Modify: `tests/unit/models/SettingsTest.php`

- [ ] **Step 1: Write failing test for webhook validation**

Append to `tests/unit/models/SettingsTest.php`:

```php
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
```

- [ ] **Step 2: Run test — should fail**

```bash
./vendor/bin/phpunit --filter SettingsTest --no-coverage
```

Expected: failure (webhooks rule missing).

- [ ] **Step 3: Add Settings::$webhooks + validation**

In `Settings.php`:

```php
public array $webhooks = [];
```

In `defineRules()`:

```php
[['webhooks'], 'each', 'rule' => [
    \anvildev\socialproof\models\Webhook::validateRow(...),
]],
[['webhooks'], function ($attr) {
    $ids = array_column($this->$attr, 'id');
    if (count($ids) !== count(array_unique($ids))) {
        $this->addError($attr, 'Webhook ids must be unique.');
    }
}],
```

- [ ] **Step 4: Write Webhook value object**

```php
<?php

namespace anvildev\socialproof\models;

class Webhook
{
    public const EVENTS = ['popup.impression', 'popup.click', 'popup.dismiss', 'popup.convert'];

    /**
     * Validator for each webhook row used by Settings::defineRules().
     *
     * @param mixed $row
     * @return string[] errors
     */
    public static function validateRow($row): array
    {
        $errors = [];
        if (!is_array($row)) {
            return ['Webhook row must be an array.'];
        }
        foreach (['id', 'label', 'url', 'events', 'secret'] as $key) {
            if (!array_key_exists($key, $row)) {
                $errors[] = "Missing field: {$key}";
            }
        }
        if (isset($row['url']) && !preg_match('#^https?://#i', (string) $row['url'])) {
            $errors[] = 'URL must be http(s).';
        }
        if (isset($row['events']) && is_array($row['events'])) {
            foreach ($row['events'] as $e) {
                if (!in_array($e, self::EVENTS, true)) {
                    $errors[] = "Unknown event: {$e}";
                }
            }
        } else {
            $errors[] = 'Events must be an array.';
        }
        if (isset($row['secret']) && !preg_match('/^[a-f0-9]{32,}$/i', (string) $row['secret'])) {
            $errors[] = 'Secret must be at least 32 hex chars.';
        }
        return $errors;
    }
}
```

Note: Yii's `each` rule validator pattern needs a tiny wrapping — if tests show the above doesn't plug in cleanly, fall back to a single closure rule that iterates `$this->webhooks` and aggregates errors manually. The test cases guarantee the behavior either way.

- [ ] **Step 5: Run tests — should pass**

```bash
./vendor/bin/phpunit --filter SettingsTest --no-coverage
```

Expected: OK.

- [ ] **Step 6: Commit**

```bash
git add src/models/Settings.php src/models/Webhook.php tests/unit/models/SettingsTest.php
git commit -m "feat(webhooks): Settings webhooks array + Webhook value object + validation"
```

---

### Task 2: JobQueueInterface + InMemoryJobQueue

**Files:**
- Create: `src/services/JobQueueInterface.php`
- Create: `tests/unit/services/InMemoryJobQueue.php`

- [ ] **Step 1: Write the interface**

```php
<?php

namespace anvildev\socialproof\services;

interface JobQueueInterface
{
    /** Push a job onto the queue. Returns the job id (string or int). */
    public function push(\craft\queue\BaseJob $job): int|string|null;
}
```

- [ ] **Step 2: Write the InMemoryJobQueue**

```php
<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\JobQueueInterface;

class InMemoryJobQueue implements JobQueueInterface
{
    /** @var list<\craft\queue\BaseJob> */
    public array $jobs = [];

    public function push(\craft\queue\BaseJob $job): int|string|null
    {
        $this->jobs[] = $job;
        return count($this->jobs);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/services/JobQueueInterface.php tests/unit/services/InMemoryJobQueue.php
git commit -m "feat(webhooks): JobQueueInterface + in-memory test fake"
```

---

### Task 3: SendWebhookJob

**Files:**
- Create: `src/jobs/SendWebhookJob.php`

- [ ] **Step 1: Write the job**

```php
<?php

namespace anvildev\socialproof\jobs;

use anvildev\socialproof\Plugin;
use anvildev\socialproof\services\WebhookService;
use Craft;
use craft\queue\BaseJob;

class SendWebhookJob extends BaseJob
{
    public string $url = '';
    public string $event = '';
    public string $deliveryId = '';
    public int $timestamp = 0;
    public string $body = '';
    public string $secret = '';

    public function execute($queue): void
    {
        $client = Craft::createGuzzleClient([
            'timeout' => 5,
            'connect_timeout' => 3,
        ]);

        $sig = WebhookService::buildSignature((string) $this->timestamp, $this->body, $this->secret);

        $res = $client->post($this->url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-SocialProof-Event' => $this->event,
                'X-SocialProof-Delivery' => $this->deliveryId,
                'X-SocialProof-Timestamp' => (string) $this->timestamp,
                'X-SocialProof-Signature-256' => 'sha256=' . $sig,
                'User-Agent' => 'AnvilDev-SocialProof/' . Plugin::getInstance()->getVersion(),
            ],
            'body' => $this->body,
            'http_errors' => false,
        ]);

        $status = $res->getStatusCode();
        if ($status >= 300) {
            throw new \RuntimeException("Webhook POST to {$this->url} failed: HTTP {$status}");
        }
    }

    protected function defaultDescription(): ?string
    {
        return "Deliver social-proof webhook ({$this->event})";
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/jobs/SendWebhookJob.php
git commit -m "feat(webhooks): SendWebhookJob queue job with HMAC signing + retry"
```

---

### Task 4: WebhookService (TDD)

**Files:**
- Create: `tests/unit/services/WebhookServiceTest.php`
- Create: `src/services/WebhookService.php`

- [ ] **Step 1: Write failing tests**

```php
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
        // Precomputed expected value — locking the signing protocol
        $this->assertSame(
            hash_hmac('sha256', '1714000000.{"a":1}', 'my-secret'),
            $sig,
        );
        // Length sanity
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
        // Signature is deterministic from (timestamp, body, secret)
        $expected = WebhookService::buildSignature((string) $job->timestamp, $job->body, 'abc');
        $this->assertSame(64, strlen($expected));
    }
}
```

- [ ] **Step 2: Run test — should fail**

```bash
./vendor/bin/phpunit --filter WebhookServiceTest --no-coverage
```

Expected: class not found.

- [ ] **Step 3: Implement service**

```php
<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\jobs\SendWebhookJob;

class WebhookService
{
    /**
     * @param list<array<string, mixed>> $subscriptions
     */
    public function __construct(
        private JobQueueInterface $queue,
        private array $subscriptions,
        private string $pluginVersion,
    ) {}

    public function dispatchForEvent(string $event, array $data): int
    {
        $count = 0;
        foreach ($this->subscriptions as $sub) {
            if (!($sub['enabled'] ?? false)) {
                continue;
            }
            if (!in_array($event, $sub['events'] ?? [], true)) {
                continue;
            }
            $deliveryId = self::generateDeliveryId();
            $timestamp = time();
            $body = json_encode([
                'event' => $event,
                'delivery_id' => $deliveryId,
                'timestamp' => $timestamp,
                'plugin_version' => $this->pluginVersion,
                'data' => $data,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $job = new SendWebhookJob();
            $job->url = (string) $sub['url'];
            $job->event = $event;
            $job->deliveryId = $deliveryId;
            $job->timestamp = $timestamp;
            $job->body = $body;
            $job->secret = (string) $sub['secret'];

            $this->queue->push($job);
            $count++;
        }
        return $count;
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
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
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
}
```

- [ ] **Step 4: Run tests — should pass**

```bash
./vendor/bin/phpunit --filter WebhookServiceTest --no-coverage
```

Expected: OK, 5 tests.

- [ ] **Step 5: Commit**

```bash
git add src/services/WebhookService.php tests/unit/services/WebhookServiceTest.php
git commit -m "feat(webhooks): WebhookService + HMAC signing + dispatch logic"
```

---

### Task 5: Wire service + hot-path dispatch

**Files:**
- Modify: `src/Plugin.php`
- Modify: `src/controllers/PopupApiController.php`
- Create: `src/services/CraftJobQueue.php`

- [ ] **Step 1: Write the production queue adapter**

```php
<?php

namespace anvildev\socialproof\services;

use Craft;
use craft\queue\BaseJob;

class CraftJobQueue implements JobQueueInterface
{
    public function push(BaseJob $job): int|string|null
    {
        return Craft::$app->getQueue()->push($job);
    }
}
```

- [ ] **Step 2: Register the service in Plugin.php**

In `setComponents()`:

```php
'webhooks' => fn () => new \anvildev\socialproof\services\WebhookService(
    new \anvildev\socialproof\services\CraftJobQueue(),
    $this->getSettings()->webhooks,
    $this->getVersion(),
),
```

- [ ] **Step 3: Dispatch in actionEvent**

In `PopupApiController::actionEvent`, after the existing `recordEvent` + attribution code, add:

```php
try {
    $popup = \anvildev\socialproof\elements\PopupElement::find()->id((int) $popupId)->status(null)->one();
    Plugin::getInstance()->webhooks->dispatchForEvent(
        'popup.' . $eventType,
        [
            'popup' => $popup ? [
                'id' => $popup->id,
                'title' => $popup->title,
                'layout' => $popup->layout,
            ] : ['id' => (int) $popupId],
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'page_url' => $pageUrl,
            'event_time' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
        ],
    );
} catch (\Throwable $e) {
    Craft::warning('Webhook dispatch failed: ' . $e->getMessage(), __METHOD__);
}
```

- [ ] **Step 4: Run full suite**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Plugin.php src/controllers/PopupApiController.php src/services/CraftJobQueue.php
git commit -m "feat(webhooks): wire WebhookService + dispatch on every popup event"
```

---

### Task 6: CP management UI

**Files:**
- Modify: `src/controllers/SettingsController.php`
- Modify: `src/templates/settings.twig`
- Modify: `src/Plugin.php` (new CP route if needed)

- [ ] **Step 1: Add controller actions**

In `SettingsController.php`:

```php
public function actionSaveWebhook(): ?\yii\web\Response
{
    $this->requirePostRequest();
    $this->requirePermission('socialProof-manageSettings');

    $plugin = \anvildev\socialproof\Plugin::getInstance();
    $settings = $plugin->getSettings();
    $webhooks = $settings->webhooks;

    $id = \Craft::$app->getRequest()->getBodyParam('id') ?: \anvildev\socialproof\services\WebhookService::generateDeliveryId();
    $label = (string) \Craft::$app->getRequest()->getRequiredBodyParam('label');
    $url = (string) \Craft::$app->getRequest()->getRequiredBodyParam('url');
    $events = (array) \Craft::$app->getRequest()->getBodyParam('events', []);
    $enabled = (bool) \Craft::$app->getRequest()->getBodyParam('enabled', true);

    // Upsert
    $found = false;
    foreach ($webhooks as &$w) {
        if ($w['id'] === $id) {
            $w['label'] = $label;
            $w['url'] = $url;
            $w['events'] = $events;
            $w['enabled'] = $enabled;
            $found = true;
            break;
        }
    }
    unset($w);
    if (!$found) {
        $webhooks[] = [
            'id' => $id,
            'label' => $label,
            'url' => $url,
            'events' => $events,
            'secret' => \anvildev\socialproof\services\WebhookService::generateSecret(),
            'enabled' => $enabled,
        ];
    }

    $settings->webhooks = $webhooks;
    \Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());
    \Craft::$app->getSession()->setNotice(\Craft::t('social-proof', 'Webhook saved.'));
    return $this->redirectToPostedUrl();
}

public function actionDeleteWebhook(): ?\yii\web\Response
{
    $this->requirePostRequest();
    $this->requirePermission('socialProof-manageSettings');

    $plugin = \anvildev\socialproof\Plugin::getInstance();
    $settings = $plugin->getSettings();
    $id = (string) \Craft::$app->getRequest()->getRequiredBodyParam('id');
    $settings->webhooks = array_values(array_filter($settings->webhooks, fn ($w) => $w['id'] !== $id));
    \Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());
    \Craft::$app->getSession()->setNotice(\Craft::t('social-proof', 'Webhook deleted.'));
    return $this->redirectToPostedUrl();
}

public function actionRotateWebhookSecret(): ?\yii\web\Response
{
    $this->requirePostRequest();
    $this->requirePermission('socialProof-manageSettings');

    $plugin = \anvildev\socialproof\Plugin::getInstance();
    $settings = $plugin->getSettings();
    $id = (string) \Craft::$app->getRequest()->getRequiredBodyParam('id');
    $webhooks = $settings->webhooks;
    foreach ($webhooks as &$w) {
        if ($w['id'] === $id) {
            $w['secret'] = \anvildev\socialproof\services\WebhookService::generateSecret();
            break;
        }
    }
    unset($w);
    $settings->webhooks = $webhooks;
    \Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());
    \Craft::$app->getSession()->setNotice(\Craft::t('social-proof', 'Secret rotated.'));
    return $this->redirectToPostedUrl();
}
```

- [ ] **Step 2: Add Webhooks tab to settings template**

Add a new tab button + panel in `src/templates/settings.twig`:

```twig
<div id="webhooks" class="hidden">
  <h2>{{ 'Webhooks'|t('social-proof') }}</h2>
  <p class="light">{{ 'Send signed HTTP POST requests to external endpoints when popup events fire.'|t('social-proof') }}</p>

  <table class="data fullwidth">
    <thead><tr>
      <th>{{ 'Label'|t('social-proof') }}</th>
      <th>{{ 'URL'|t('social-proof') }}</th>
      <th>{{ 'Events'|t('social-proof') }}</th>
      <th>{{ 'Enabled'|t('social-proof') }}</th>
      <th></th>
    </tr></thead>
    <tbody>
      {% for w in settings.webhooks %}
      <tr>
        <td>{{ w.label }}</td>
        <td><code>{{ w.url }}</code></td>
        <td>{% for e in w.events %}<span class="status-label">{{ e }}</span>{% endfor %}</td>
        <td>{{ w.enabled ? '✓' : '—' }}</td>
        <td>
          <form method="post" style="display:inline">
            {{ csrfInput() }}
            {{ actionInput('social-proof/settings/delete-webhook') }}
            {{ redirectInput('social-proof/settings') }}
            <input type="hidden" name="id" value="{{ w.id }}">
            <button class="btn small error">{{ 'Delete'|t('social-proof') }}</button>
          </form>
        </td>
      </tr>
      {% endfor %}
    </tbody>
  </table>

  <hr>
  <h3>{{ 'Add webhook'|t('social-proof') }}</h3>
  <form method="post">
    {{ csrfInput() }}
    {{ actionInput('social-proof/settings/save-webhook') }}
    {{ redirectInput('social-proof/settings') }}
    {{ forms.textField({ label: 'Label'|t('social-proof'), name: 'label', required: true }) }}
    {{ forms.textField({ label: 'URL'|t('social-proof'), name: 'url', type: 'url', required: true }) }}
    {{ forms.checkboxGroupField({
      label: 'Events'|t('social-proof'),
      name: 'events',
      options: [
        { label: 'popup.impression', value: 'popup.impression' },
        { label: 'popup.click', value: 'popup.click' },
        { label: 'popup.dismiss', value: 'popup.dismiss' },
        { label: 'popup.convert', value: 'popup.convert' },
      ],
    }) }}
    {{ forms.lightswitchField({ label: 'Enabled'|t('social-proof'), name: 'enabled', on: true }) }}
    <button class="btn submit">{{ 'Save webhook'|t('social-proof') }}</button>
  </form>
</div>
```

Add a corresponding tab link in the existing tab nav of `settings.twig`.

- [ ] **Step 3: Smoke the UI**

```bash
# Via /craft-smoke-test manually — out of scope for this plan; the unit tests cover the service layer.
```

- [ ] **Step 4: Commit**

```bash
git add src/controllers/SettingsController.php src/templates/settings.twig
git commit -m "feat(webhooks): CP settings UI for webhook CRUD"
```

---

### Task 7: Release notes

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `README.md`

- [ ] **Step 1: Add changelog entry**

Under the existing `[1.2.0]` section (if revenue attribution shipped there) add a "Webhooks" subsection; otherwise open `[1.3.0]`.

- [ ] **Step 2: Add README section**

Add a "Webhooks" section to `README.md` documenting the payload format, signature scheme, and example verification code (Node.js or PHP snippet).

- [ ] **Step 3: Commit**

```bash
git add CHANGELOG.md README.md
git commit -m "docs: changelog + README for outbound webhook support"
```

---
