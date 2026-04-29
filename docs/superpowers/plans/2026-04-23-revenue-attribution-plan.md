# Revenue Attribution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Link popup `convert` events to completed Commerce orders so the stats surface can report revenue per popup.

**Architecture:** New `socialproof_popup_attributions` table (schema 2.5.0). `AttributionService` + `AttributionStore` interface follow the `FatigueService` / `FatigueStore` split so unit tests use an `InMemoryAttributionStore`. `PopupApiController::actionEvent` calls `recordConvert` on `convert` events. A second listener on `Order::EVENT_AFTER_COMPLETE_ORDER` calls `attributeOrder`. Stats widget + CLI gain a revenue column.

**Tech Stack:** PHP 8.2, Craft 5, Yii2 ActiveRecord (records), PHPUnit 10, Craft Commerce (optional).

---

### Task 1: Migration + PopupAttributionRecord

**Files:**
- Create: `src/migrations/m260423_000000_add_popup_attributions.php`
- Create: `src/records/PopupAttributionRecord.php`
- Modify: `src/Plugin.php` (bump `$schemaVersion` to `2.5.0`)

- [ ] **Step 1: Write the migration**

```php
<?php

namespace anvildev\socialproof\migrations;

use craft\db\Migration;

class m260423_000000_add_popup_attributions extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%socialproof_popup_attributions}}';
        if ($this->db->tableExists($table)) {
            return true;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'popupId' => $this->integer(),
            'visitorId' => $this->string(64)->notNull(),
            'sessionId' => $this->string(64),
            'orderId' => $this->integer(),
            'orderTotal' => $this->decimal(14, 4),
            'currency' => $this->string(3),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'convertedAt' => $this->dateTime()->notNull(),
            'attributedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, $table, ['visitorId', 'status', 'convertedAt']);
        $this->createIndex(null, $table, ['status']);
        $this->createIndex(null, $table, ['popupId']);

        $this->addForeignKey(null, $table, ['popupId'], '{{%socialproof_popups}}', ['id'], 'SET NULL');

        if ($this->db->tableExists('{{%commerce_orders}}')) {
            $this->addForeignKey(null, $table, ['orderId'], '{{%commerce_orders}}', ['id'], 'SET NULL');
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%socialproof_popup_attributions}}');
        return true;
    }
}
```

- [ ] **Step 2: Write PopupAttributionRecord**

```php
<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $popupId
 * @property string $visitorId
 * @property string|null $sessionId
 * @property int|null $orderId
 * @property string|null $orderTotal
 * @property string|null $currency
 * @property string $status
 * @property string $convertedAt
 * @property string|null $attributedAt
 */
class PopupAttributionRecord extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ATTRIBUTED = 'attributed';
    public const STATUS_EXPIRED = 'expired';

    public static function tableName(): string
    {
        return '{{%socialproof_popup_attributions}}';
    }
}
```

- [ ] **Step 3: Bump schema version**

In `src/Plugin.php` find the `$schemaVersion` declaration and set to `'2.5.0'`.

- [ ] **Step 4: Run the migration**

```bash
cd /Users/fh/Documents/experiments/craft-plugin-dev
ddev craft migrate/up --plugin=social-proof
```

Expected: migration runs cleanly, table exists. Verify:
```bash
ddev mysql -e "DESCRIBE craft_socialproof_popup_attributions;"
```

- [ ] **Step 5: Commit**

```bash
cd plugins/craft-social-proof-dev
git add src/migrations/m260423_000000_add_popup_attributions.php src/records/PopupAttributionRecord.php src/Plugin.php
git commit -m "feat(attribution): add socialproof_popup_attributions table + schema 2.5.0"
```

---

### Task 2: AttributionStore interface + InMemory fake

**Files:**
- Create: `src/services/AttributionStore.php`
- Create: `tests/unit/services/InMemoryAttributionStore.php`

- [ ] **Step 1: Define the interface**

```php
<?php

namespace anvildev\socialproof\services;

interface AttributionStore
{
    public function recordPending(int $popupId, string $visitorId, ?string $sessionId, \DateTimeInterface $convertedAt): void;

    /**
     * Find the most recent pending attribution for this visitor within the window
     * and transition it to `attributed`. Returns the attributed row id, or null
     * if nothing matched.
     */
    public function attribute(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int;

    /** Transition pending rows older than the cutoff to `expired`. Returns count. */
    public function expireOlderThan(\DateTimeInterface $cutoff): int;

    /**
     * Aggregate attributed revenue per popupId within the lookback window.
     *
     * @return array<int, array{revenue: string, orders: int}>
     */
    public function revenueByPopup(\DateTimeInterface $since): array;
}
```

- [ ] **Step 2: Write in-memory fake**

```php
<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\records\PopupAttributionRecord;
use anvildev\socialproof\services\AttributionStore;

class InMemoryAttributionStore implements AttributionStore
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    private int $nextId = 1;

    public function recordPending(int $popupId, string $visitorId, ?string $sessionId, \DateTimeInterface $convertedAt): void
    {
        $this->rows[$this->nextId] = [
            'id' => $this->nextId,
            'popupId' => $popupId,
            'visitorId' => $visitorId,
            'sessionId' => $sessionId,
            'orderId' => null,
            'orderTotal' => null,
            'currency' => null,
            'status' => PopupAttributionRecord::STATUS_PENDING,
            'convertedAt' => $convertedAt->format('Y-m-d H:i:s'),
            'attributedAt' => null,
        ];
        $this->nextId++;
    }

    public function attribute(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int {
        $cutoff = (clone $completedAt)->modify("-{$windowHours} hours")->format('Y-m-d H:i:s');
        $candidates = array_filter(
            $this->rows,
            fn ($r) =>
                $r['status'] === PopupAttributionRecord::STATUS_PENDING
                && ($r['visitorId'] === $visitorId || ($sessionId !== null && $r['sessionId'] === $sessionId))
                && $r['convertedAt'] >= $cutoff,
        );
        if (!$candidates) {
            return null;
        }
        usort($candidates, fn ($a, $b) => strcmp($b['convertedAt'], $a['convertedAt']));
        $winner = $candidates[0];
        $this->rows[$winner['id']] = [
            ...$winner,
            'status' => PopupAttributionRecord::STATUS_ATTRIBUTED,
            'orderId' => $orderId,
            'orderTotal' => $orderTotal,
            'currency' => $currency,
            'attributedAt' => $completedAt->format('Y-m-d H:i:s'),
        ];
        return $winner['id'];
    }

    public function expireOlderThan(\DateTimeInterface $cutoff): int
    {
        $count = 0;
        $c = $cutoff->format('Y-m-d H:i:s');
        foreach ($this->rows as $id => $r) {
            if ($r['status'] === PopupAttributionRecord::STATUS_PENDING && $r['convertedAt'] < $c) {
                $this->rows[$id]['status'] = PopupAttributionRecord::STATUS_EXPIRED;
                $count++;
            }
        }
        return $count;
    }

    public function revenueByPopup(\DateTimeInterface $since): array
    {
        $s = $since->format('Y-m-d H:i:s');
        $out = [];
        foreach ($this->rows as $r) {
            if ($r['status'] !== PopupAttributionRecord::STATUS_ATTRIBUTED) {
                continue;
            }
            if ($r['attributedAt'] === null || $r['attributedAt'] < $s) {
                continue;
            }
            $pid = $r['popupId'];
            if (!isset($out[$pid])) {
                $out[$pid] = ['revenue' => '0.0000', 'orders' => 0];
            }
            $out[$pid]['revenue'] = bcadd($out[$pid]['revenue'], $r['orderTotal'] ?? '0', 4);
            $out[$pid]['orders']++;
        }
        return $out;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/services/AttributionStore.php tests/unit/services/InMemoryAttributionStore.php
git commit -m "feat(attribution): AttributionStore interface + in-memory fake"
```

---

### Task 3: AttributionService (TDD)

**Files:**
- Create: `tests/unit/services/AttributionServiceTest.php`
- Create: `src/services/AttributionService.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\records\PopupAttributionRecord;
use anvildev\socialproof\services\AttributionService;
use PHPUnit\Framework\TestCase;

class AttributionServiceTest extends TestCase
{
    private InMemoryAttributionStore $store;
    private AttributionService $service;

    protected function setUp(): void
    {
        $this->store = new InMemoryAttributionStore();
        $this->service = new AttributionService($this->store);
    }

    public function testRecordConvertWritesPendingRow(): void
    {
        $this->service->recordConvert(popupId: 42, visitorId: 'v-1', sessionId: 's-1');
        $this->assertCount(1, $this->store->rows);
        $row = reset($this->store->rows);
        $this->assertSame(42, $row['popupId']);
        $this->assertSame('v-1', $row['visitorId']);
        $this->assertSame(PopupAttributionRecord::STATUS_PENDING, $row['status']);
    }

    public function testOrderWithinWindowGetsAttributed(): void
    {
        $this->service->recordConvert(42, 'v-1', 's-1');
        $result = $this->service->attributeOrder(
            visitorId: 'v-1',
            sessionId: 's-1',
            orderId: 99,
            orderTotal: '149.9900',
            currency: 'USD',
            completedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            windowHours: 24,
        );
        $this->assertSame(1, $result);
        $row = reset($this->store->rows);
        $this->assertSame(PopupAttributionRecord::STATUS_ATTRIBUTED, $row['status']);
        $this->assertSame(99, $row['orderId']);
    }

    public function testOrderOutsideWindowIsNotAttributed(): void
    {
        $this->store->rows[1] = [
            'id' => 1, 'popupId' => 42, 'visitorId' => 'v-1', 'sessionId' => 's-1',
            'orderId' => null, 'orderTotal' => null, 'currency' => null,
            'status' => PopupAttributionRecord::STATUS_PENDING,
            'convertedAt' => (new \DateTimeImmutable('-30 hours', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'attributedAt' => null,
        ];

        $result = $this->service->attributeOrder(
            visitorId: 'v-1',
            sessionId: null,
            orderId: 99,
            orderTotal: '10.00',
            currency: 'USD',
            completedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            windowHours: 24,
        );
        $this->assertNull($result);
        $this->assertSame(PopupAttributionRecord::STATUS_PENDING, $this->store->rows[1]['status']);
    }

    public function testLastClickWinsAmongMultiplePending(): void
    {
        $this->service->recordConvert(10, 'v-1', null); // older
        usleep(10_000);
        $this->service->recordConvert(20, 'v-1', null); // newer — should win

        $this->service->attributeOrder('v-1', null, 99, '50.00', 'USD', new \DateTimeImmutable('now', new \DateTimeZone('UTC')), 24);

        $attributed = array_filter($this->store->rows, fn ($r) => $r['status'] === PopupAttributionRecord::STATUS_ATTRIBUTED);
        $this->assertCount(1, $attributed);
        $winner = reset($attributed);
        $this->assertSame(20, $winner['popupId']);
    }

    public function testSessionIdFallbackWhenVisitorIdMissing(): void
    {
        $this->service->recordConvert(42, 'v-original', 's-shared');
        // A second visitor on the same session (cookie cleared mid-flow, same browser session)
        $result = $this->service->attributeOrder(
            visitorId: 'v-different',
            sessionId: 's-shared',
            orderId: 99, orderTotal: '10.00', currency: 'USD',
            completedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            windowHours: 24,
        );
        $this->assertSame(1, $result);
    }

    public function testRevenueByPopupAggregates(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->service->recordConvert(10, 'v-a', null);
        $this->service->attributeOrder('v-a', null, 1, '100.0000', 'USD', $now, 24);
        $this->service->recordConvert(10, 'v-b', null);
        $this->service->attributeOrder('v-b', null, 2, '50.0000', 'USD', $now, 24);
        $this->service->recordConvert(20, 'v-c', null);
        $this->service->attributeOrder('v-c', null, 3, '25.0000', 'USD', $now, 24);

        $out = $this->service->revenueByPopup(30);
        $this->assertCount(2, $out);
        $this->assertSame('150.0000', $out[10]['revenue']);
        $this->assertSame(2, $out[10]['orders']);
        $this->assertSame('25.0000', $out[20]['revenue']);
    }

    public function testCleanupExpiresOldPendingRows(): void
    {
        $this->store->rows[1] = [
            'id' => 1, 'popupId' => 1, 'visitorId' => 'v', 'sessionId' => null,
            'orderId' => null, 'orderTotal' => null, 'currency' => null,
            'status' => PopupAttributionRecord::STATUS_PENDING,
            'convertedAt' => (new \DateTimeImmutable('-45 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'attributedAt' => null,
        ];
        $count = $this->service->cleanupExpired(30);
        $this->assertSame(1, $count);
        $this->assertSame(PopupAttributionRecord::STATUS_EXPIRED, $this->store->rows[1]['status']);
    }
}
```

- [ ] **Step 2: Run tests — should fail**

```bash
./vendor/bin/phpunit --filter AttributionServiceTest --no-coverage
```

Expected: FAIL — "class AttributionService not found".

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace anvildev\socialproof\services;

class AttributionService
{
    public function __construct(private AttributionStore $store) {}

    public function recordConvert(int $popupId, string $visitorId, ?string $sessionId): void
    {
        $this->store->recordPending($popupId, $visitorId, $sessionId, $this->now());
    }

    public function attributeOrder(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int {
        return $this->store->attribute($visitorId, $sessionId, $orderId, $orderTotal, $currency, $completedAt, $windowHours);
    }

    public function cleanupExpired(int $days): int
    {
        $cutoff = $this->now()->modify("-{$days} days");
        return $this->store->expireOlderThan($cutoff);
    }

    public function revenueByPopup(int $days): array
    {
        $since = $this->now()->modify("-{$days} days");
        return $this->store->revenueByPopup($since);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
```

- [ ] **Step 4: Run tests — should pass**

```bash
./vendor/bin/phpunit --filter AttributionServiceTest --no-coverage
```

Expected: OK, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add src/services/AttributionService.php tests/unit/services/AttributionServiceTest.php
git commit -m "feat(attribution): AttributionService with last-click + UTC window"
```

---

### Task 4: DbAttributionStore (production backing)

**Files:**
- Create: `src/services/DbAttributionStore.php`

- [ ] **Step 1: Implement the DB-backed store**

```php
<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\records\PopupAttributionRecord;
use craft\db\Query;
use craft\helpers\Db;

class DbAttributionStore implements AttributionStore
{
    public function recordPending(int $popupId, string $visitorId, ?string $sessionId, \DateTimeInterface $convertedAt): void
    {
        $rec = new PopupAttributionRecord();
        $rec->popupId = $popupId;
        $rec->visitorId = $visitorId;
        $rec->sessionId = $sessionId;
        $rec->status = PopupAttributionRecord::STATUS_PENDING;
        $rec->convertedAt = Db::prepareDateForDb($convertedAt);
        $rec->save(false);
    }

    public function attribute(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int {
        $cutoff = (clone $completedAt)->modify("-{$windowHours} hours");
        $query = PopupAttributionRecord::find()
            ->where(['status' => PopupAttributionRecord::STATUS_PENDING])
            ->andWhere(['>=', 'convertedAt', Db::prepareDateForDb($cutoff)])
            ->orderBy(['convertedAt' => SORT_DESC])
            ->limit(1);

        if ($sessionId !== null) {
            $query->andWhere(['or', ['visitorId' => $visitorId], ['sessionId' => $sessionId]]);
        } else {
            $query->andWhere(['visitorId' => $visitorId]);
        }

        /** @var PopupAttributionRecord|null $row */
        $row = $query->one();
        if (!$row) {
            return null;
        }

        $row->status = PopupAttributionRecord::STATUS_ATTRIBUTED;
        $row->orderId = $orderId;
        $row->orderTotal = $orderTotal;
        $row->currency = $currency;
        $row->attributedAt = Db::prepareDateForDb($completedAt);
        $row->save(false);

        return (int) $row->id;
    }

    public function expireOlderThan(\DateTimeInterface $cutoff): int
    {
        return PopupAttributionRecord::updateAll(
            ['status' => PopupAttributionRecord::STATUS_EXPIRED],
            ['and', ['status' => PopupAttributionRecord::STATUS_PENDING], ['<', 'convertedAt', Db::prepareDateForDb($cutoff)]],
        );
    }

    public function revenueByPopup(\DateTimeInterface $since): array
    {
        $rows = (new Query())
            ->select(['popupId', 'revenue' => 'SUM(orderTotal)', 'orders' => 'COUNT(*)'])
            ->from(PopupAttributionRecord::tableName())
            ->where(['status' => PopupAttributionRecord::STATUS_ATTRIBUTED])
            ->andWhere(['>=', 'attributedAt', Db::prepareDateForDb($since)])
            ->groupBy('popupId')
            ->all();

        $out = [];
        foreach ($rows as $r) {
            if ($r['popupId'] !== null) {
                $out[(int) $r['popupId']] = [
                    'revenue' => (string) $r['revenue'],
                    'orders' => (int) $r['orders'],
                ];
            }
        }
        return $out;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/services/DbAttributionStore.php
git commit -m "feat(attribution): DbAttributionStore for production"
```

---

### Task 5: Wire service into Plugin.php + hook into event pipeline

**Files:**
- Modify: `src/Plugin.php`
- Modify: `src/controllers/PopupApiController.php`

- [ ] **Step 1: Register the service**

In `Plugin.php` `setComponents()` add:

```php
'attribution' => fn () => new \anvildev\socialproof\services\AttributionService(
    new \anvildev\socialproof\services\DbAttributionStore(),
),
```

- [ ] **Step 2: Add Commerce listener for attribution**

In `_registerCommerceEvents()` (the existing method that wires the notifications handler), add a second event handler for the same event:

```php
\yii\base\Event::on(
    \craft\commerce\elements\Order::class,
    \craft\commerce\elements\Order::EVENT_AFTER_COMPLETE_ORDER,
    function (\yii\base\Event $event) {
        $settings = $this->getSettings();
        if (!$settings->popupAttributionEnabled) {
            return;
        }
        /** @var \craft\commerce\elements\Order $order */
        $order = $event->sender;
        $request = \Craft::$app->getRequest();
        $visitorId = null;
        $sessionId = null;
        if (!$request->getIsConsoleRequest()) {
            $visitorId = $request->getCookies()->getValue('social_proof_popup_visitor');
            $sessionId = \Craft::$app->getSession()->getId();
        }
        if ($visitorId === null && $sessionId === null) {
            return;
        }
        try {
            $this->attribution->attributeOrder(
                visitorId: $visitorId ?? '',
                sessionId: $sessionId,
                orderId: (int) $order->id,
                orderTotal: (string) $order->getTotalPrice(),
                currency: $order->currency,
                completedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                windowHours: $settings->popupAttributionWindowHours,
            );
        } catch (\Throwable $e) {
            \Craft::error('Popup attribution failed: ' . $e->getMessage(), __METHOD__);
        }
    },
);
```

- [ ] **Step 3: Add GC cleanup call**

In the existing `Gc::EVENT_RUN` handler, after the `tracking->cleanupOldData(90)` call, add:

```php
try {
    $this->attribution->cleanupExpired(90);
} catch (\Throwable $e) {
    \Craft::warning('Attribution GC failed: ' . $e->getMessage(), __METHOD__);
}
```

- [ ] **Step 4: Call recordConvert from the API controller**

In `PopupApiController::actionEvent`, after the existing `Plugin::getInstance()->popups->recordEvent(...)` call, add:

```php
if ($eventType === PopupService::EVENT_CONVERT) {
    try {
        Plugin::getInstance()->attribution->recordConvert((int) $popupId, $visitorId, $sessionId);
    } catch (\Throwable $e) {
        Craft::warning('Popup attribution record failed: ' . $e->getMessage(), __METHOD__);
    }
}
```

- [ ] **Step 5: Run full suite — should still pass**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: all previous tests plus the 7 new attribution tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Plugin.php src/controllers/PopupApiController.php
git commit -m "feat(attribution): wire service + Commerce listener + GC hook"
```

---

### Task 6: Settings + CP UI

**Files:**
- Modify: `src/models/Settings.php`
- Modify: `src/templates/settings.twig`

- [ ] **Step 1: Add settings fields**

In `Settings.php`:

```php
public bool $popupAttributionEnabled = true;
public int $popupAttributionWindowHours = 24;
```

Add to `defineRules()`:
```php
[['popupAttributionEnabled'], 'boolean'],
[['popupAttributionWindowHours'], 'integer', 'min' => 1, 'max' => 720],
```

- [ ] **Step 2: Expose on settings template**

Find the popup-related tab in `src/templates/settings.twig` and add a new fieldset after the existing popup-section block:

```twig
<hr>
<h3>{{ 'Attribution'|t('social-proof') }}</h3>
<p class="light">{{ 'Link popup convert events to completed Craft Commerce orders within a lookback window.'|t('social-proof') }}</p>

{{ forms.lightswitchField({
    label: 'Enable revenue attribution'|t('social-proof'),
    id: 'popupAttributionEnabled',
    name: 'popupAttributionEnabled',
    on: settings.popupAttributionEnabled,
}) }}

{{ forms.textField({
    label: 'Attribution window (hours)'|t('social-proof'),
    instructions: 'How long after a popup convert event an order may still be attributed to it.'|t('social-proof'),
    id: 'popupAttributionWindowHours',
    name: 'popupAttributionWindowHours',
    value: settings.popupAttributionWindowHours,
    type: 'number',
    min: 1,
    max: 720,
}) }}
```

- [ ] **Step 3: Run settings test to confirm validation**

```bash
./vendor/bin/phpunit --filter SettingsTest --no-coverage
```

Expected: passes (no regressions).

- [ ] **Step 4: Commit**

```bash
git add src/models/Settings.php src/templates/settings.twig
git commit -m "feat(attribution): CP settings for attribution window + enable toggle"
```

---

### Task 7: Surface revenue in widget + console stats

**Files:**
- Modify: `src/widgets/PopupStatsWidget.php`
- Modify: `src/templates/_widgets/popup-stats-body.twig`
- Modify: `src/console/controllers/PopupsController.php`

- [ ] **Step 1: Inject revenue into widget body**

In `PopupStatsWidget::getBodyHtml()` after `$rows = $this->fetchStats();`, add:

```php
$revenue = Plugin::getInstance()->attribution->revenueByPopup($this->dayRange);
foreach ($rows as &$row) {
    $rid = (int) ($row['popupId'] ?? 0);
    $row['revenue'] = $revenue[$rid]['revenue'] ?? '0.0000';
    $row['orders'] = $revenue[$rid]['orders'] ?? 0;
}
unset($row);
```

Pass the amended rows to the template.

- [ ] **Step 2: Render revenue column in template**

In `src/templates/_widgets/popup-stats-body.twig`, add `<th>Revenue</th>` header and a `<td>{{ row.revenue|currency(row.currency ?? 'USD') }}</td>` cell — if `|currency` is unavailable use `{{ row.revenue }}` with a note.

- [ ] **Step 3: Add revenue column to CLI stats**

In `PopupsController::actionStats()`, before the loop, prefetch:
```php
$revenue = Plugin::getInstance()->attribution->revenueByPopup($this->since);
```

Then widen the header + row printf:
```php
$this->stdout(sprintf("%-6s %-24s %10s %8s %10s %10s %6s %12s\n", 'id', 'title', 'imps', 'clicks', 'dismisses', 'converts', 'CTR', 'revenue'));
```

In the loop:
```php
$rev = $revenue[(int) $row['popupId']]['revenue'] ?? '0.00';
$this->stdout(sprintf("%-6s %-24s %10s %8s %10s %10s %6s %12s\n",
    $row['popupId'] ?? '-',
    $title,
    $imp,
    (int) $row['clicks'],
    (int) $row['dismisses'],
    (int) $row['converts'],
    $ctr,
    $rev,
));
```

- [ ] **Step 4: Run full suite**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/widgets/PopupStatsWidget.php src/templates/_widgets/popup-stats-body.twig src/console/controllers/PopupsController.php
git commit -m "feat(attribution): surface revenue in popup-stats widget + CLI"
```

---

### Task 8: Release notes

**Files:**
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Add changelog entry**

Prepend a new `## [1.2.0] - 2026-04-23` section with a "Revenue attribution" subsection summarising: new table, last-click model, configurable window, Commerce-optional, GC cleanup, revenue column on widget + CLI, 7 new unit tests.

- [ ] **Step 2: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: CHANGELOG entry for v1.2.0 revenue attribution"
```

---
