# Refund & Cancellation Handling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove cached purchase notifications when their underlying Craft Commerce order is refunded or transitions to a "cancelled-like" status, so visitors only see notifications for valid sales.

**Architecture:** Two new event listeners in `Plugin.php` (`Transactions::EVENT_AFTER_SAVE_TRANSACTION` and `Order::EVENT_AFTER_SAVE`) call into `CommerceService::removeOrderFromCache(int)`, which runs a single idempotent DELETE against `socialproof_orders`. Decision logic is extracted into two pure predicate methods on `CommerceService` (`shouldHandleRefundTransaction`, `shouldHandleStatusChange`) so it is unit-testable without a database. A new `excludedOrderStatusHandles` setting (default `['cancelled']`) drives the cancellation predicate.

**Tech Stack:** PHP 8.2, Craft CMS 5, Craft Commerce 5 (optional dep), PHPUnit 10.5, Yii2 events.

**Spec:** `docs/superpowers/specs/2026-04-29-refund-handling-design.md`

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `src/models/Settings.php` | Modify | New `excludedOrderStatusHandles` property + validation rule |
| `src/services/CommerceService.php` | Modify | Add `removeOrderFromCache`, `shouldHandleRefundTransaction`, `shouldHandleStatusChange` methods |
| `src/Plugin.php` | Modify | Two new listeners in `_registerCommerceEvents()` |
| `src/templates/settings.twig` | Modify | New multi-select on `#purchases` tab (Commerce-guarded) |
| `tests/unit/models/SettingsTest.php` | Modify | Cover new setting default + validation |
| `tests/unit/services/CommerceLogicTest.php` | Modify | Cover new predicate methods |
| `README.md` | Modify | Document refund/cancellation handling |
| `CHANGELOG.md` | Modify | Add unreleased entry |

All commands assume working directory is `plugins/craft-social-proof-dev/`. The test runner is invoked as `./vendor/bin/phpunit` (PHPUnit 10.5).

---

## Task 1: Add `excludedOrderStatusHandles` setting

**Files:**
- Modify: `src/models/Settings.php`
- Modify: `tests/unit/models/SettingsTest.php`

- [ ] **Step 1.1: Write failing test for default value**

Add to `tests/unit/models/SettingsTest.php` inside the `testDefaultsAreSet` method (just before the closing brace, after the existing `excludedUrlPatterns` assertion at line 37):

```php
        $this->assertSame(['cancelled'], $s->excludedOrderStatusHandles);
```

- [ ] **Step 1.2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/unit/models/SettingsTest.php --filter testDefaultsAreSet`

Expected: FAIL with `Undefined property: anvildev\socialproof\models\Settings::$excludedOrderStatusHandles`

- [ ] **Step 1.3: Add the property to `Settings.php`**

Add to `src/models/Settings.php` immediately after the existing `excludedProductTypes` declaration (line 89):

```php
    /**
     * @var array<int,string> Order status handles that purge cached purchase
     *   notifications when an order transitions to one of them. Default
     *   covers the standard "cancelled" status; stores can add `fraud`,
     *   `void`, `chargeback`, etc. via CP settings or config file.
     */
    public array $excludedOrderStatusHandles = ['cancelled'];
```

- [ ] **Step 1.4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/unit/models/SettingsTest.php --filter testDefaultsAreSet`

Expected: PASS

- [ ] **Step 1.5: Add validation rule and verify defaults still validate**

In `src/models/Settings.php`, modify the existing `each` rule on line 142 to include the new field:

```php
            [['excludedProductTypes', 'includedCategories', 'includedUrlPatterns', 'excludedUrlPatterns', 'excludedOrderStatusHandles'], 'each', 'rule' => ['string']],
```

Run: `./vendor/bin/phpunit tests/unit/models/SettingsTest.php --filter testDefaultsPassValidation`

Expected: PASS

- [ ] **Step 1.6: Add `toArray` and round-trip coverage**

In `tests/unit/models/SettingsTest.php`, append `'excludedOrderStatusHandles'` to the `$expected` array inside `testToArrayContainsAllSettings` (the array currently ending with `'excludedUrlPatterns',` on line 301):

```php
            'excludedUrlPatterns', 'excludedOrderStatusHandles',
```

Add a new test method right after `testSetAttributesRoundTrip` (around line 330):

```php
    public function testExcludedOrderStatusHandlesRoundTrip(): void
    {
        $original = new Settings();
        $original->excludedOrderStatusHandles = ['cancelled', 'fraud', 'chargeback'];

        $arr = $original->toArray();

        $restored = new Settings();
        $restored->setAttributes($arr, false);

        $this->assertSame(
            ['cancelled', 'fraud', 'chargeback'],
            $restored->excludedOrderStatusHandles,
        );
        $this->assertTrue($restored->validate());
    }
```

- [ ] **Step 1.7: Run the full Settings test class**

Run: `./vendor/bin/phpunit tests/unit/models/SettingsTest.php`

Expected: All tests pass.

- [ ] **Step 1.8: Commit**

```bash
git add src/models/Settings.php tests/unit/models/SettingsTest.php
git commit -m "feat(settings): add excludedOrderStatusHandles for refund/cancellation purge"
```

---

## Task 2: Add `shouldHandleRefundTransaction` predicate

The predicate is a pure function so it can be unit-tested without bootstrapping Commerce. It compares string-literal type/status values that match `\craft\commerce\records\Transaction::TYPE_REFUND` (`'refund'`) and `STATUS_SUCCESS` (`'success'`). String literals are intentional — Commerce 5's API freezes these values, and using them keeps the service testable when Commerce is not loaded.

**Files:**
- Modify: `src/services/CommerceService.php`
- Modify: `tests/unit/services/CommerceLogicTest.php`

- [ ] **Step 2.1: Write failing tests for the predicate**

Add to `tests/unit/services/CommerceLogicTest.php` immediately before the `// Helpers` section (line 49):

```php
    // ═══════════════════════════════════════════════════════════════════════
    // Refund transaction predicate
    // ═══════════════════════════════════════════════════════════════════════

    public function testRefundWithSuccessStatusShouldBeHandled(): void
    {
        $this->assertTrue(
            $this->service->shouldHandleRefundTransaction('refund', 'success'),
        );
    }

    public function testRefundWithFailedStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('refund', 'failed'),
        );
    }

    public function testRefundWithProcessingStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('refund', 'processing'),
        );
    }

    public function testPurchaseWithSuccessStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('purchase', 'success'),
        );
    }

    public function testCaptureWithSuccessStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('capture', 'success'),
        );
    }

    public function testAuthorizeWithSuccessStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleRefundTransaction('authorize', 'success'),
        );
    }
```

- [ ] **Step 2.2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/unit/services/CommerceLogicTest.php --filter shouldHandleRefundTransaction`

Expected: FAIL with `Error: Call to undefined method ...::shouldHandleRefundTransaction()`

- [ ] **Step 2.3: Implement the predicate**

In `src/services/CommerceService.php`, add this public method immediately before the existing `getRecentOrderCount` method (line 244):

```php
    /**
     * Returns true when a saved transaction represents a successful refund and
     * its order's cached notifications should be purged.
     *
     * Compared as string literals (not Commerce constants) so the service is
     * testable when Commerce is not loaded. Values match
     * \craft\commerce\records\Transaction::TYPE_REFUND ('refund') and
     * STATUS_SUCCESS ('success').
     */
    public function shouldHandleRefundTransaction(string $type, string $status): bool
    {
        return $type === 'refund' && $status === 'success';
    }
```

- [ ] **Step 2.4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/unit/services/CommerceLogicTest.php --filter shouldHandleRefundTransaction`

Expected: 6 tests pass.

- [ ] **Step 2.5: Commit**

```bash
git add src/services/CommerceService.php tests/unit/services/CommerceLogicTest.php
git commit -m "feat(commerce): add shouldHandleRefundTransaction predicate"
```

---

## Task 3: Add `shouldHandleStatusChange` predicate

**Files:**
- Modify: `src/services/CommerceService.php`
- Modify: `tests/unit/services/CommerceLogicTest.php`

- [ ] **Step 3.1: Write failing tests for the predicate**

Add to `tests/unit/services/CommerceLogicTest.php` immediately after the refund predicate tests added in Task 2:

```php
    // ═══════════════════════════════════════════════════════════════════════
    // Order status change predicate
    // ═══════════════════════════════════════════════════════════════════════

    public function testStatusInExcludedListShouldBeHandled(): void
    {
        $this->assertTrue(
            $this->service->shouldHandleStatusChange('cancelled', ['cancelled']),
        );
    }

    public function testStatusInLargerExcludedListShouldBeHandled(): void
    {
        $this->assertTrue(
            $this->service->shouldHandleStatusChange('fraud', ['cancelled', 'fraud', 'chargeback']),
        );
    }

    public function testStatusNotInExcludedListShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleStatusChange('processing', ['cancelled']),
        );
    }

    public function testNullStatusShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleStatusChange(null, ['cancelled']),
        );
    }

    public function testEmptyExcludedListShouldNotBeHandled(): void
    {
        $this->assertFalse(
            $this->service->shouldHandleStatusChange('cancelled', []),
        );
    }
```

- [ ] **Step 3.2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/unit/services/CommerceLogicTest.php --filter shouldHandleStatusChange`

Expected: FAIL with `Error: Call to undefined method ...::shouldHandleStatusChange()`

- [ ] **Step 3.3: Implement the predicate**

In `src/services/CommerceService.php`, add this public method immediately after the `shouldHandleRefundTransaction` method added in Task 2:

```php
    /**
     * Returns true when an order has just transitioned into a status whose
     * cached notifications should be purged. Null status (e.g. an order with
     * no orderStatus assigned) is never handled.
     *
     * @param array<int,string> $excludedStatusHandles
     */
    public function shouldHandleStatusChange(?string $statusHandle, array $excludedStatusHandles): bool
    {
        if ($statusHandle === null) {
            return false;
        }

        return in_array($statusHandle, $excludedStatusHandles, true);
    }
```

- [ ] **Step 3.4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/unit/services/CommerceLogicTest.php --filter shouldHandleStatusChange`

Expected: 5 tests pass.

- [ ] **Step 3.5: Commit**

```bash
git add src/services/CommerceService.php tests/unit/services/CommerceLogicTest.php
git commit -m "feat(commerce): add shouldHandleStatusChange predicate"
```

---

## Task 4: Add `removeOrderFromCache` method

This method runs a single DELETE on `socialproof_orders`. The current test bootstrap does not provide a real database (see `tests/bootstrap.php` — minimal Yii only), so the method itself is not directly unit-tested; it is a 5-line wrapper around the existing `Craft::$app->getDb()` pattern already used by `_storeOrderData` (line 220) and `cleanupOldOrders` (line 229) in the same file.

**Files:**
- Modify: `src/services/CommerceService.php`

- [ ] **Step 4.1: Add the method to `CommerceService.php`**

Add this public method immediately after the `shouldHandleStatusChange` method added in Task 3:

```php
    /**
     * Remove every cached purchase notification belonging to an order. Called
     * when the order is refunded or transitions to a status that should
     * suppress its notifications. Idempotent — calling on an order with no
     * cached rows is a no-op.
     *
     * @return int Number of rows deleted.
     */
    public function removeOrderFromCache(int $orderId): int
    {
        $deleted = (int) Craft::$app->getDb()->createCommand()
            ->delete('{{%socialproof_orders}}', ['orderId' => $orderId])
            ->execute();

        if ($deleted > 0) {
            Craft::info(
                "Removed {$deleted} cached notification row(s) for order {$orderId}",
                __METHOD__,
            );
        }

        return $deleted;
    }
```

- [ ] **Step 4.2: Run the full test suite to confirm no regressions**

Run: `./vendor/bin/phpunit`

Expected: existing tests still pass; no new tests for this method (covered manually in Task 7).

- [ ] **Step 4.3: Commit**

```bash
git add src/services/CommerceService.php
git commit -m "feat(commerce): add removeOrderFromCache for refund/cancellation purge"
```

---

## Task 5: Wire the refund event listener

**Files:**
- Modify: `src/Plugin.php`

- [ ] **Step 5.1: Add the listener inside `_registerCommerceEvents()`**

In `src/Plugin.php`, append this listener inside `_registerCommerceEvents()`, immediately before the closing brace of the method. Place it after the existing attribution listener that ends on line 296.

The full new block to add:

```php
        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        Event::on(
            \craft\commerce\services\Transactions::class,
            \craft\commerce\services\Transactions::EVENT_AFTER_SAVE_TRANSACTION,
            function (\craft\commerce\events\TransactionEvent $event): void {
                $txn = $event->transaction;
                if ($txn->orderId === null) {
                    return;
                }
                if (!$this->commerce->shouldHandleRefundTransaction((string) $txn->type, (string) $txn->status)) {
                    return;
                }
                try {
                    $this->commerce->removeOrderFromCache((int) $txn->orderId);
                } catch (\Throwable $e) {
                    Craft::error(
                        'Social-proof refund purge failed: ' . $e->getMessage(),
                        __METHOD__,
                    );
                }
            },
        );
```

- [ ] **Step 5.2: Run the full test suite**

Run: `./vendor/bin/phpunit`

Expected: all existing tests still pass.

- [ ] **Step 5.3: Run static analysis to ensure Commerce-optional typing is correct**

Run from repo root: `cd /Users/fh/Documents/experiments/craft-plugin-dev && composer check`

Expected: no new errors introduced. (PHPStan may report ~428 pre-existing errors per project memory; verify the new lines do not add to the count.)

- [ ] **Step 5.4: Commit**

```bash
git add src/Plugin.php
git commit -m "feat(plugin): listen for refund transactions and purge cached notifications"
```

---

## Task 6: Wire the order-status-change event listener

**Files:**
- Modify: `src/Plugin.php`

- [ ] **Step 6.1: Add the listener inside `_registerCommerceEvents()`**

In `src/Plugin.php`, append this listener inside `_registerCommerceEvents()` immediately after the refund listener added in Task 5:

```php
        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        Event::on(
            \craft\commerce\elements\Order::class,
            \craft\commerce\elements\Order::EVENT_AFTER_SAVE,
            function (Event $event): void {
                /** @var \craft\commerce\elements\Order $order */
                $order = $event->sender;
                $statusHandle = $order->getOrderStatus()?->handle;
                $excluded = $this->getSettings()->excludedOrderStatusHandles;
                if (!$this->commerce->shouldHandleStatusChange($statusHandle, $excluded)) {
                    return;
                }
                try {
                    $this->commerce->removeOrderFromCache((int) $order->id);
                } catch (\Throwable $e) {
                    Craft::error(
                        'Social-proof status-change purge failed: ' . $e->getMessage(),
                        __METHOD__,
                    );
                }
            },
        );
```

- [ ] **Step 6.2: Run the full test suite**

Run: `./vendor/bin/phpunit`

Expected: all existing tests still pass.

- [ ] **Step 6.3: Run static analysis**

Run from repo root: `cd /Users/fh/Documents/experiments/craft-plugin-dev && composer check`

Expected: no new errors introduced.

- [ ] **Step 6.4: Commit**

```bash
git add src/Plugin.php
git commit -m "feat(plugin): listen for order status changes and purge cached notifications"
```

---

## Task 7: End-to-end manual verification in the dev environment

This task validates the listeners against a real Craft + Commerce stack, since `removeOrderFromCache` and the listener wiring cannot be exercised by the current test bootstrap.

**Files:**
- None modified. Output documented inline in this checklist.

- [ ] **Step 7.1: Verify the dev environment is running**

Run from repo root: `cd /Users/fh/Documents/experiments/craft-plugin-dev && ddev describe | head -5`

Expected: ddev project is running. If not, run `ddev start`.

- [ ] **Step 7.2: Seed a test order via Commerce CP**

Open the CP at `https://craft-plugin-dev.ddev.site/admin` (credentials in `memory/reference_cp_credentials.md`).

1. Navigate to **Commerce → Orders → New order**.
2. Add any line item, set billing address (first name "TestPurger"), and complete the order.
3. Note the resulting order ID (visible in the URL).

- [ ] **Step 7.3: Verify the cache row was created**

Run from repo root: `ddev exec "php /var/www/html/craft social-proof/default/stats"` and confirm the new order is reflected; alternatively, run a direct query:

```bash
ddev mysql -e "SELECT id, orderId, productName FROM socialproof_orders WHERE orderId = <ORDER_ID>;"
```

Expected: at least one row matching the order ID.

- [ ] **Step 7.4: Refund the order and verify the row is gone**

In the CP order detail screen, use **Transactions → Refund** for the existing capture transaction. Confirm the refund.

Re-run the same `SELECT` from Step 7.3.

Expected: zero rows. The Craft logs (`storage/logs/web.log`) should contain a line `Removed N cached notification row(s) for order <ORDER_ID>` from `CommerceService::removeOrderFromCache`.

- [ ] **Step 7.5: Seed a second order, then change its status to Cancelled**

Repeat Step 7.2 with a fresh order ("TestCanceller"). Confirm the cache row exists per Step 7.3. Then, in the CP order detail screen, change **Order Status → Cancelled** and save.

Re-run the `SELECT`.

Expected: zero rows. Logs again show the purge line.

- [ ] **Step 7.6: Negative case — verify a non-excluded status does NOT purge**

Repeat Step 7.2 with a third order. Change its status to **Processing** (or any non-cancelled status).

Run the `SELECT`.

Expected: the row is still present. No purge log line.

- [ ] **Step 7.7: Commit (no code changes — checklist only)**

No commit needed for this task. Document the run results inline in the PR description when the work ships.

---

## Task 8: Add the CP settings UI field

The controller already follows a `_get*Options` private-helper pattern (e.g. `_getProductTypes` at line 295). The new options helper mirrors that.

**Files:**
- Modify: `src/controllers/SettingsController.php`
- Modify: `src/templates/settings.twig`

- [ ] **Step 8.1: Add `_getOrderStatusOptions()` helper to the controller**

In `src/controllers/SettingsController.php`, immediately after the existing `_getProductTypes()` method (which ends on line 313), add:

```php
    /**
     * @return list<array{label: string, value: string}>
     */
    private function _getOrderStatusOptions(): array
    {
        if (!Plugin::isCommerceInstalled()) {
            return [];
        }

        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        $statuses = \craft\commerce\Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        $options = [];

        foreach ($statuses as $status) {
            $options[] = [
                'label' => $status->name,
                'value' => $status->handle,
            ];
        }

        return $options;
    }
```

- [ ] **Step 8.2: Pass `orderStatusOptions` to both render calls**

In `src/controllers/SettingsController.php`, modify the `renderTemplate` array in `actionIndex` (currently lines 73–83). Append a new array entry inside the closing `]`:

```php
            'orderStatusOptions' => $this->_getOrderStatusOptions(),
```

Then do the same for the validation-failure render call inside `actionSave` (currently lines 137–145):

```php
            'orderStatusOptions' => $this->_getOrderStatusOptions(),
```

- [ ] **Step 8.3: Read the body param in `actionSave`**

In `src/controllers/SettingsController.php`, locate the `excludedProductTypes` line (line 124) inside `actionSave`. Add immediately below it:

```php
        $settings->excludedOrderStatusHandles = $request->getBodyParam('excludedOrderStatusHandles', []) ?: [];
```

- [ ] **Step 8.4: Add the multi-select field to `settings.twig`**

In `src/templates/settings.twig`, locate the existing `excludedProductTypes` field (`forms.checkboxSelectField` block starting around line 263, with `id: 'excludedProductTypes'` on line 266). It is wrapped by a `{% if commerceInstalled %}` guard.

Immediately after the closing `}) }}` of that field — but still **inside** the same `{% if commerceInstalled %}` block — add:

```twig
            {{ forms.checkboxSelectField({
                label: 'Purge notifications when order moves to status'|t('social-proof'),
                instructions: 'Cached purchase notifications are removed when an order\'s status changes to one of these. Useful for cancelled, fraud, or chargeback orders.'|t('social-proof'),
                id: 'excludedOrderStatusHandles',
                name: 'excludedOrderStatusHandles',
                values: settings.excludedOrderStatusHandles,
                options: orderStatusOptions,
            }) }}
```

- [ ] **Step 8.5: Manually verify the field renders and saves**

Reload `https://craft-plugin-dev.ddev.site/admin/settings/plugins/social-proof`.

Expected:
- A new "Purge notifications when order moves to status" field appears on the Purchases tab, immediately after "Excluded Product Types".
- The `cancelled` checkbox is selected by default (matches `['cancelled']`).
- Toggling additional statuses (e.g. `fraud`) and saving persists the selection.
- Reloading the page shows the saved selection.

- [ ] **Step 8.6: Run the full test + lint pipeline**

Run: `./vendor/bin/phpunit && cd /Users/fh/Documents/experiments/craft-plugin-dev && composer check`

Expected: all tests pass; no new ECS or PHPStan errors.

- [ ] **Step 8.7: Commit**

```bash
git add src/controllers/SettingsController.php src/templates/settings.twig
git commit -m "feat(cp): add 'purge on status change' multi-select to social-proof settings"
```

---

## Task 9: Document the new behavior

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 9.1: Add a "Refund & cancellation handling" subsection to README.md**

Open `README.md` and find the **Purchase Notifications** section (around line 32). Append immediately after that section:

```markdown
### Refund & Cancellation Handling

Cached purchase notifications are automatically removed in two cases:

- **Refund**: when a successful refund transaction is recorded against the order in Craft Commerce.
- **Cancellation / fraud / chargeback**: when the order's status changes to any handle listed in the **Purge notifications when order moves to status** setting (default: `cancelled`).

Visitors stop seeing notifications for affected orders within seconds of the refund or status change. The plugin does not display any refund-related UI to visitors — affected notifications simply disappear from the rotation.
```

- [ ] **Step 9.2: Add a CHANGELOG entry**

Open `CHANGELOG.md`. Add a new entry at the top under the latest unreleased section (or create a new `## Unreleased` section if none exists):

```markdown
### Added

- Cached purchase notifications are now purged when an order is refunded (`Transactions::EVENT_AFTER_SAVE_TRANSACTION`, `type=refund`, `status=success`).
- Cached purchase notifications are now purged when an order's status changes to a configurable allowlist of handles (default: `['cancelled']`). Configure under **Social Proof → Settings → Purchases → Purge notifications when order moves to status**.
- New setting `excludedOrderStatusHandles` (overridable via `config/social-proof.php`).
```

- [ ] **Step 9.3: Commit**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: document refund and cancellation handling"
```

---

## Final verification

- [ ] **Step F.1: Confirm all tests pass and the lint pipeline is clean**

Run: `./vendor/bin/phpunit && cd /Users/fh/Documents/experiments/craft-plugin-dev && composer check`

Expected:
- PHPUnit: every test passes (per project memory: 945 tests + the 11 new ones added in Tasks 1–3 = 956, with 38 still skipped due to missing Craft init).
- ECS: clean.
- PHPStan: no new errors introduced relative to the pre-existing ~428.

- [ ] **Step F.2: Confirm git history is clean and well-segmented**

Run: `git log --oneline -10`

Expected: each task produced a single focused commit, with imperative-mood subjects matching the patterns used in existing history.
