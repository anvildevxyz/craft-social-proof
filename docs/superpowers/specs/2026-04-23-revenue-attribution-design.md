# Revenue Attribution — Design Spec

## Goal

Connect popup `convert` events to completed Craft Commerce orders so the stats surface can answer: *"how much revenue did each popup drive?"*

## Non-Goals

- Multi-touch attribution (equal-split, time-decay). v1.2 uses **last-click**: the most recent pending attribution for a visitor wins.
- Revenue attribution for toast notifications (`NotificationElement`). Scope is popups only — the click/convert pipeline for toasts is different and not yet behavioral-metric rich enough to warrant it.
- Attribution for non-Commerce conversions (newsletter signups, etc). Those already count as `convert` events in stats — this feature is purely Commerce revenue.

## Behavior

1. When a popup `convert` event is recorded (`PopupApiController::actionEvent`), write a row to a new `socialproof_popup_attributions` table with status `pending`. It captures `popupId`, `visitorId`, `sessionId`, `convertedAt`.
2. When Commerce fires `Order::EVENT_AFTER_COMPLETE_ORDER`, find the most recent `pending` attribution for the visitor (by cookie `social_proof_popup_visitor` if present on the request, falling back to `sessionId` when the completion happens in the same session). Link it: set `orderId`, `orderTotal`, `currency`, `attributedAt`, status `attributed`.
3. Attribution window is configurable (`Settings::popupAttributionWindowHours`, default 24, max 720). Pending rows older than the window are never attributed and get swept to status `expired` by the GC hook.
4. Stats surfaces (widget + CLI) gain a revenue column summing attributed orderTotals per popup within the selected period.
5. Commerce is optional. With Commerce not installed: the attribution table still exists, conversion events still record pending rows (harmless), but no listener is registered and nothing ever moves out of `pending`. The GC hook still expires them. Stats surfaces show revenue = 0.

## Schema

New table `socialproof_popup_attributions` (schemaVersion bump 2.4.0 → 2.5.0):

```
id                INT PK, auto
popupId           INT, nullable, FK → socialproof_popups(id) ON DELETE SET NULL
visitorId         STRING(64), NOT NULL, indexed
sessionId         STRING(64), nullable, indexed
orderId           INT, nullable, FK → commerce_orders(id) ON DELETE SET NULL
orderTotal        DECIMAL(14,4), nullable
currency          STRING(3), nullable
status            STRING(20), NOT NULL, default 'pending'  -- pending|attributed|expired
convertedAt       DATETIME, NOT NULL, indexed
attributedAt      DATETIME, nullable
dateCreated       DATETIME, NOT NULL
dateUpdated       DATETIME, NOT NULL
uid               CHAR(36)

idx_socialproof_popup_attributions_lookup (visitorId, status, convertedAt DESC)
idx_socialproof_popup_attributions_status  (status)
idx_socialproof_popup_attributions_popup   (popupId)
```

The `orderId` FK is only created when Commerce is installed (guard in migration).

### Why a separate table

- Attribution has its own lifecycle (`pending → attributed | expired`) that doesn't fit the append-only impressions table.
- The write pattern of the hot path (`actionEvent`) stays lean — no update-in-place, just a second `INSERT`.
- Attribution queries are fundamentally different from impression-count queries and want their own indexes.

## Service Surface

New `AttributionService` with a `AttributionStore` interface (same pattern as `FatigueService` / `FatigueStore`). Pure logic stays testable without Craft bootstrap.

```php
interface AttributionStore {
    public function recordPending(int $popupId, string $visitorId, ?string $sessionId, \DateTimeInterface $convertedAt): void;

    /** Returns the winning attribution id, or null if nothing eligible. */
    public function attribute(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int;

    public function expireOlderThan(\DateTimeInterface $cutoff): int;

    /** For stats: attributed revenue grouped by popupId within a date range. */
    public function revenueByPopup(\DateTimeInterface $since): array; // [popupId => ['revenue' => string, 'orders' => int]]
}

class AttributionService {
    public function __construct(private AttributionStore $store) {}
    public function recordConvert(int $popupId, string $visitorId, ?string $sessionId): void;
    public function attributeOrder(Order $order, string $visitorId, ?string $sessionId, int $windowHours): ?int;
    public function cleanupExpired(int $days): int;
    public function revenueByPopup(int $days): array;
}
```

`attributeOrder()` pulls the visitor cookie from the request (`Craft::$app->getRequest()->getCookies()`) because `EVENT_AFTER_COMPLETE_ORDER` fires during a checkout request where the cookie is available. If no cookie (edge cases: CLI-completed orders, admin-placed orders), falls back to session. If neither, skip.

## Config

Add to `Settings`:
- `popupAttributionWindowHours` (int, default 24, min 1, max 720)
- `popupAttributionEnabled` (bool, default true) — lets admins disable the Commerce hook without uninstalling

Surface both on the popup tab of the settings screen, under a new "Attribution" fieldset.

## Integration Points

- `Plugin.php`: register `attribution` service. Inside `_registerCommerceEvents()`, add a second listener on `Order::EVENT_AFTER_COMPLETE_ORDER` → `attribution->attributeOrder(...)`. Guard on `popupAttributionEnabled`.
- `PopupApiController::actionEvent`: after `popups->recordEvent(...)`, if `eventType === EVENT_CONVERT`, call `attribution->recordConvert(...)`. Non-fatal: wrap in try/catch and log.
- `PopupStatsWidget`: extend the stats query to left-join revenue via `attribution->revenueByPopup($dayRange)` (separate query; don't join in SQL across schema boundaries). Add a `revenue` column to the widget template.
- `console/controllers/PopupsController::actionStats`: same — add revenue column to the output table.
- `Gc::EVENT_RUN` handler in Plugin.php: call `attribution->cleanupExpired(90)` alongside the existing `tracking->cleanupOldData(90)`.

## Testing

- `tests/unit/services/AttributionServiceTest.php` with `InMemoryAttributionStore` — cover:
  - Fresh convert → pending row
  - Order within window + matching visitor → attributed
  - Order outside window → no attribution
  - Order with matching visitor but already-attributed row → picks next pending
  - Last-click: two pending rows, older ignored
  - sessionId fallback when no visitorId on the request
  - revenueByPopup aggregation
- `tests/integration/controllers/PopupApiControllerTest.php`: regression for "convert event records pending attribution"

## Rollout

- Schema: 2.4.0 → 2.5.0
- Release: v1.2.0 (new feature, backward compatible)
- CHANGELOG + README updates
- No migration-time backfill — historical `convert` events don't get retroactive attribution rows. New pending rows start from v1.2.0 deploy.
