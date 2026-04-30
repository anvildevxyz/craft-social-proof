# Refund & cancellation handling for cached purchase notifications

**Status:** Draft — pending implementation plan
**Date:** 2026-04-29

## Problem

When a Craft Commerce order completes, `CommerceService::handleOrderComplete` writes a row into `socialproof_orders` so the toast queue can show "Sarah from London bought Widget Pro." That cache row lives for 48 hours (configurable).

If the order is refunded or cancelled inside that 48-hour window, the cached row continues to show. Visitors keep seeing a notification advertising a sale that didn't actually happen. This undermines the marketing claim of "native Commerce integration that respects order state" and is the kind of detail a Fomo-comparison reviewer flags.

## Goal

Remove cached purchase notifications when their underlying order is refunded or cancelled, so visitors only ever see notifications for active, valid sales.

Non-goal: displaying refund or cancellation information to visitors. The behavior is purely subtractive — bad rows disappear from the cache, nothing new is shown.

## Decisions made during brainstorming

| Decision | Choice | Reasoning |
|---|---|---|
| Trigger events | Refunds **and** order status changes | Refund-only misses admin cancellations and fraud-marked orders, which are rare but visible failure cases. |
| Removal granularity | Whole order (delete every cache row matching `orderId`) | Refund transactions don't reliably carry per-line-item attribution across gateways. The cache only holds 48h of data, so partial-refund precision is not worth a schema migration. |
| Cancellation detection | Configurable `excludedOrderStatusHandles` setting, default `['cancelled']` | Stores name statuses differently (`cancelled`, `void`, `fraud`, `chargeback`). Mirrors the existing `excludedProductTypes` pattern. |
| `purchaseEnabled` gating | Removal is **not** gated on this setting | The cache should always be cleanable, even when notifications are disabled. Symmetric to the auto-cleanup job. |

## Architecture

The work fits into the existing `CommerceService` adapter; no new services or classes.

### Components changed

**`src/models/Settings.php`**
- Add `public array $excludedOrderStatusHandles = ['cancelled'];`
- Add validation rule: `[['excludedOrderStatusHandles'], 'each', 'rule' => ['string']]`

**`src/services/CommerceService.php`**
- Add one public method:
  ```php
  public function removeOrderFromCache(int $orderId): int
  ```
  Runs `DELETE FROM {{%socialproof_orders}} WHERE orderId = :orderId` and returns the affected row count. Idempotent — calling twice on the same id is a no-op the second time. Logs at info level.

**`src/Plugin.php`**
- Register two new event listeners alongside the existing `EVENT_AFTER_COMPLETE_ORDER` listener:
  1. `Transactions::EVENT_AFTER_SAVE_TRANSACTION` — calls `removeOrderFromCache` when transaction `type === TYPE_REFUND` and `status === STATUS_SUCCESS`.
  2. `Order::EVENT_AFTER_SAVE` — calls `removeOrderFromCache` when `getOrderStatus()?->handle` is in `excludedOrderStatusHandles`.
- Both listeners are registered only if `Plugin::isCommerceInstalled()`, matching the existing complete-order listener's gate.

**Settings UI (`src/templates/settings.twig`, `#purchases` tab)**
- One new multi-select field on the **Purchases** tab, placed immediately after the existing `excludedProductTypes` field (around line 268).
- Label: "Purge notifications when order moves to status"
- Help text: "Cached purchase notifications are removed when an order's status changes to one of these. Useful for cancelled, fraud, or chargeback orders."
- Options sourced from `\craft\commerce\Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses()`, displaying status name with handle as the value.
- Field is conditionally rendered — hidden if Commerce is not installed, matching existing tab behavior.

## Data flow

### Refund path

1. Any transaction is saved (purchase, capture, refund, authorize) → `Transactions::EVENT_AFTER_SAVE_TRANSACTION` fires
2. Listener filters: skip unless `type === TransactionRecord::TYPE_REFUND && status === TransactionRecord::STATUS_SUCCESS`
3. Listener filters: skip if `$transaction->orderId` is null (defensive — shouldn't happen, but transaction events vary across gateways)
4. Call `CommerceService::removeOrderFromCache($transaction->orderId)`
5. All `socialproof_orders` rows for that order are deleted

We do not check refund amount. Any successful refund — full or partial — purges all rows for the order. This is consistent with the "whole order" granularity decision.

### Cancellation path

1. Any order is saved (status change, line item edit, address update, etc.) → `Order::EVENT_AFTER_SAVE` fires
2. Listener filters: skip if `$order->getOrderStatus()?->handle` is null or not in `$settings->excludedOrderStatusHandles`
3. Call `CommerceService::removeOrderFromCache($order->id)`
4. All `socialproof_orders` rows for that order are deleted

`EVENT_AFTER_SAVE` fires often. We deliberately do not check `isAttributeChanged('orderStatusId')` — `removeOrderFromCache` is idempotent, the additional code path adds risk for no observable benefit, and a `WHERE orderId=?` delete on a small table is cheap.

### Re-entry & flip-flop behavior

If an order is cancelled then reverted to `processing`, the cache row is gone permanently — it is not re-created. This is the correct behavior: visitors stopped seeing the notification once it was cancelled, and re-displaying it after revert would be unexpected. Furthermore, the 48-hour window means any "revived" order is likely past notification relevance anyway.

If an order completes with status already in the excluded list (e.g., immediately fraud-flagged), `EVENT_AFTER_COMPLETE_ORDER` writes the row, then the same request's `EVENT_AFTER_SAVE` fires and deletes it. Wasteful but harmless.

## Error handling & defensive programming

- Null check on `$transaction->orderId` before calling the service.
- Null-safe operator on `$order->getOrderStatus()?->handle`.
- All listeners are wrapped in the existing `Plugin::isCommerceInstalled()` gate so they never load Commerce classes when Commerce is absent.
- `removeOrderFromCache` does not throw on "no rows matched" — it returns 0 and logs at info level.

No try/catch. Database errors should bubble up; a failing cache delete is a real failure worth surfacing.

## Testing plan

Two new unit tests in `tests/services/CommerceServiceTest.php`:

1. **`testRemoveOrderFromCacheDeletesAllRowsForOrder`**
   Seed two `socialproof_orders` rows for `orderId=42`, one for `orderId=99`. Call `removeOrderFromCache(42)`. Assert: only the `orderId=99` row remains, return value is 2.

2. **`testRemoveOrderFromCacheIsIdempotent`**
   Call `removeOrderFromCache(999)` twice on an empty table. Assert: no error, both calls return 0.

The listener wiring (refund event filter, status-handle filter) is Yii event-binding glue. Cover it with one listener-level test per path:

3. **`testRefundTransactionTriggersCacheRemoval`** — mock a refund transaction with `type=REFUND`, `status=SUCCESS`, `orderId=42`. Assert `removeOrderFromCache(42)` is called.

4. **`testNonRefundTransactionDoesNotTriggerCacheRemoval`** — mock a `TYPE_PURCHASE` transaction. Assert `removeOrderFromCache` is not called.

5. **`testOrderStatusChangeToCancelledTriggersCacheRemoval`** — mock an order with `orderStatus->handle = 'cancelled'` while `excludedOrderStatusHandles = ['cancelled']`. Assert `removeOrderFromCache($order->id)` is called.

6. **`testOrderStatusChangeToProcessingDoesNotTriggerCacheRemoval`** — same fixture, but status `processing`. Assert no call.

End-to-end Commerce fixture tests are explicitly skipped — too much setup cost for what is fundamentally event-binding wiring.

## Migration & backward compatibility

- No database migration required. All cache schema and tables are unchanged.
- New `excludedOrderStatusHandles` setting defaults to `['cancelled']` — existing installs immediately get conservative cancellation handling without any user action.
- Settings serialization already supports array fields (existing `excludedProductTypes`), so the existing settings save path handles this addition without changes.
- Config file override (`config/social-proof.php`) works automatically through Craft's settings layer.

## Out of scope

- Per-line-item refund granularity (would require schema migration + gateway-specific parsing).
- Visitor-facing refund or "this sale was reversed" UI (intentionally never displayed).
- Order deletion via admin (rare; 48h cleanup catches it).
- Cancellation/refund handling for orders that completed *before* the plugin was installed (no cache rows exist; no-op).
