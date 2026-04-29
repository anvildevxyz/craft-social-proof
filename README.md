# Social Proof for Craft CMS

Conversion messaging for Craft CMS with two surfaces:

- **Toast notifications** - small corner notifications for recent purchases, current viewer counts, and low-stock warnings
- **Popups** (v1.1+) - overlay modals and bottom bars with behavior-driven triggers (exit intent, scroll, element click, time on page) and Craft-native targeting (sections, entries, user groups, logged-in state)

Both surfaces share a server-side arbitration engine, per-visitor fatigue rules, and impression tracking.

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later
- Craft Commerce 5.0 or later (optional, for purchase notifications and stock warnings)

## Installation

1. Add the plugin to your project:

```bash
composer require anvildev/craft-social-proof
```

2. Install the plugin via the Control Panel or CLI:

```bash
php craft plugin/install social-proof
```

## Features

### Purchase Notifications
Show recent purchases to create urgency and social proof. Requires Craft Commerce.

> "Sarah from London purchased Widget Pro - 5 minutes ago"

### Viewer Count
Display how many people are currently viewing a page.

> "12 people are viewing this right now"

### Low Stock Warnings
Alert visitors when products are running low. Requires Craft Commerce.

> "Only 3 left in stock!"

### A/B Testing
Built-in A/B testing to measure the impact of notifications on conversions.

### Analytics Dashboard
Track impressions, clicks, and click-through rates with the built-in analytics dashboard and widget.

### Popups (v1.1+)

Overlay popups and modals with advanced targeting:

- **6 trigger types**: time on page, exit intent, scroll depth, element click, page match, user state
- **4 built-in layouts**: announcement, newsletter (with webhook form), discount code (with copy-to-clipboard), bottom bar (non-modal); plus `custom` for developer-authored Twig templates
- **Craft-native targeting**: URL glob patterns, sections, specific entries, user groups, logged-in state - all composable
- **Per-visitor fatigue**: max impressions per visitor, minimum hours between shows, stop-after-dismiss, stop-after-convert
- **Priority-based arbitration**: multiple eligible popups resolve by author-set priority
- **Live preview iframe** in the CP with Desktop/Tablet/Mobile width toggle
- **Demo mode** for QA - bypass fatigue for CP users without polluting production counts
- **Dashboard analytics widget** per popup (impressions, clicks, dismisses, converts, CTR)

## Usage

Add one or both of the following to your layout template, just before the closing `</body>` tag:

```twig
{# Toast notifications (social proof) #}
{{ craft.socialProof.init() }}

{# Popups / modals #}
{{ craft.socialProof.initPopups() }}

{# Or, for full-page-cached sites, the async variant that fetches candidates after load: #}
{# {{ craft.socialProof.initPopupsAsync() }} #}
```

Both surfaces are independent - enable only what you need. Each loads its own assets and does not interfere with the other.

### Template Options

You can pass display overrides directly from Twig:

```twig
{{ craft.socialProof.init({
    position: 'bottom-right',
    displayDuration: 8,
    delayBetween: 15,
    animationIn: 'fadeIn',
    animationOut: 'slideOut',
}) }}
```

### GDPR / Cookie Consent

The frontend JS supports a consent callback that runs before any tracking request. Return `false` to block the request:

```twig
<script>
window.socialProofConfig = {
    ...window.socialProofConfig,
    onBeforeTrack: function(eventType) {
        // Example: integrate with Cookiebot
        return Cookiebot?.consent?.statistics === true;
    }
};
</script>
```

The callback receives the event type (`'impression'`, `'click'`, `'dismiss'`, `'heartbeat'`) so you can selectively allow certain events.

### Heartbeat / Viewer Counting

The JS sends a heartbeat every 30 seconds (configurable) to support real-time viewer counting. You can adjust the interval:

```twig
{{ craft.socialProof.init({ heartbeatInterval: 60000 }) }}
```

Set `heartbeatInterval` to `0` to disable heartbeats entirely.

## Configuration

### Control Panel

Configure the plugin under **Social Proof -> Settings**:

- **General**: Enable/disable, max notifications per session, demo mode
- **Appearance**: Position, animation, timing, product images, dismiss button
- **Purchase**: Lookback period, customer anonymization, message template
- **Viewers**: Counting mode (real-time / calculated / static), minimum threshold
- **Stock**: Warning threshold, message template
- **A/B Testing**: Test percentage (1-99)
- **URL Targeting**: Include/exclude URL patterns with wildcard support

### Config File Overrides

For multi-environment or version-controlled settings, copy `config.example.php` to your Craft project:

```bash
cp vendor/anvildev/craft-social-proof/config.example.php config/social-proof.php
```

Values in this file override CP settings and support Craft's multi-environment config:

```php
// config/social-proof.php
return [
    '*' => [
        'enabled' => true,
        'maxNotificationsPerSession' => 10,
    ],
    'dev' => [
        'demoMode' => true,
    ],
    'production' => [
        'demoMode' => false,
    ],
];
```

See `config.example.php` for all available options.

## Permissions

The plugin registers five granular permissions under **Settings -> Users -> Permissions**:

| Permission | Controls |
|---|---|
| **Manage notifications** | Create, edit, delete notification elements |
| **View statistics** | Access the analytics dashboard |
| **Manage settings** | Change plugin settings, run cleanup/import |
| **Manage popups** | Create, edit, delete popup elements |
| **View popup statistics** | Add the popup analytics dashboard widget |

Admin users have all permissions by default.

## Template Variables

### Check if enabled
```twig
{% if craft.socialProof.isEnabled() %}
    ...
{% endif %}
```

### Get viewer count
```twig
{{ craft.socialProof.viewerCount() }} people viewing
{{ craft.socialProof.viewerCount('/products/widget') }} {# specific page #}
```

### Get recent purchase count
```twig
{{ craft.socialProof.recentPurchaseCount(24) }} orders in the last 24 hours
```

### Check if Commerce is installed
```twig
{% if craft.socialProof.isCommerceInstalled() %}
    ...
{% endif %}
```

### Render popups
```twig
{{ craft.socialProof.initPopups() }}           {# inline candidate payload - default #}
{{ craft.socialProof.initPopupsAsync() }}      {# JS-fetches candidates after load - for cached pages #}
```

## Console Commands

Maintenance commands for cron jobs and administration:

```bash
# Clean up old tracking data
craft social-proof/default/cleanup
craft social-proof/default/cleanup --impression-days=30 --order-hours=24

# Import recent Commerce orders into notification cache
craft social-proof/default/import-orders
craft social-proof/default/import-orders --limit=100

# View notification statistics
craft social-proof/default/stats

# Purge notification tracking data for a specific session (GDPR right to erasure)
craft social-proof/default/purge-session --session-id=abc123def456
```

### Popup commands (v1.1+)

```bash
# Prune old popup impression + fatigue rows
craft social-proof/popups/cleanup
craft social-proof/popups/cleanup --impression-days=30 --fatigue-days=60

# Tabular per-popup stats
craft social-proof/popups/stats
craft social-proof/popups/stats --popupId=42 --since=30

# GDPR right-to-erasure for a popup visitor
craft social-proof/popups/purge-session --session=<visitorId-uuid>
```

### Recommended Cron Job

Add a daily cleanup to prevent data bloat:

```cron
0 3 * * * cd /path/to/craft && php craft social-proof/default/cleanup
5 3 * * * cd /path/to/craft && php craft social-proof/popups/cleanup
```

Garbage collection also runs automatically via Craft's GC system.

## JavaScript Events

The plugin fires DOM events on the notification container for custom integrations:

- `socialproof:impression` - notification shown
- `socialproof:click` - notification clicked
- `socialproof:dismiss` - notification dismissed

```javascript
document.getElementById('social-proof-container')
    .addEventListener('socialproof:click', function(e) {
        console.log('Notification clicked:', e.detail);
    });
```

## Outbound Webhooks (v1.2+)

The plugin can POST a signed JSON payload to any HTTP endpoint when a popup event fires (`impression | click | dismiss | convert`). Manage subscriptions under **Social Proof → Settings → Webhooks**.

### Payload

```json
{
  "event": "popup.convert",
  "delivery_id": "f0e8...",
  "timestamp": 1714000000,
  "plugin_version": "1.0.0",
  "data": {
    "popup": { "id": 42, "title": "Black Friday announcement", "layout": "announcement" },
    "visitor_id": "abc...",
    "session_id": "sess...",
    "page_url": "https://example.com/shop/widget",
    "event_time": "2026-04-23T10:00:00+00:00"
  }
}
```

### Signature verification

Every request carries headers:

| Header | Description |
|---|---|
| `X-SocialProof-Event` | Event name, e.g. `popup.convert` |
| `X-SocialProof-Delivery` | UUID v4, unique per delivery |
| `X-SocialProof-Timestamp` | Unix seconds when the delivery was composed |
| `X-SocialProof-Signature-256` | `sha256=<hex>` HMAC over `"{timestamp}.{rawBody}"` using the subscription's secret |

Node.js verification example:

```js
const crypto = require('crypto');

function verify(req, secret) {
    const signatureHeader = req.headers['x-socialproof-signature-256'] || '';
    const timestamp = req.headers['x-socialproof-timestamp'] || '';
    const expected = 'sha256=' + crypto
        .createHmac('sha256', secret)
        .update(timestamp + '.' + req.rawBody)
        .digest('hex');
    return crypto.timingSafeEqual(
        Buffer.from(signatureHeader),
        Buffer.from(expected),
    );
}
```

PHP example:

```php
$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
if (!hash_equals($expected, $signatureHeader)) {
    http_response_code(401);
    exit;
}
```

Receivers should also reject deliveries whose `X-SocialProof-Timestamp` is more than ~5 minutes off from the server's current time to prevent replay.

### Retry behaviour

Delivery runs through Craft's queue. Any non-2xx response or transport failure throws, and the queue retries per its configured defaults (typically 3 tries with exponential backoff). After exhaustion the failure is logged to `web.log` and the job moves to the failed queue.

> **Use HTTP status, not body content, to signal failure.** The job only inspects the response status code. A receiver that returns `200 OK` with `{ "error": "…" }` in the body is treated as a successful delivery and the event will not be retried.

### Circuit breaker

To prevent permanently broken receivers from piling up failed jobs, each subscription has an independent circuit breaker. After **`webhookBreakerThreshold`** consecutive failures (default `5`), the breaker opens and dispatch skips the subscription for **`webhookBreakerCooldownSeconds`** (default `300`). One success after cooldown clears the count. Set the threshold to `0` to disable the breaker.

The breaker state lives in Craft's cache, keyed under `socialproof.webhook.breaker:{subscription_id}`. To manually unblock a subscription, run `ddev craft clear-caches/data` or evict that single key.

## Accessibility

- The notification container uses `aria-live="polite"` so screen readers announce new notifications without interrupting the user
- Each notification has `role="status"` for assistive technology
- Dismiss buttons include `aria-label="Dismiss notification"`
- Pressing **Escape** dismisses the currently visible notification
- Decorative SVG icons use `aria-hidden="true"`
- CSS respects `prefers-reduced-motion` to disable animations

## Rate Limiting

Public API endpoints are rate-limited per IP:

| Endpoint | Limit |
|---|---|
| Track events | 60 requests/minute |
| Heartbeat | 4 requests/minute |

Exceeding the limit returns HTTP 429.

## Data & Privacy

- **Session-based tracking**: Uses server-side session IDs, not cookies
- **Customer anonymization**: Optional first-name-only display for privacy
- **Automatic cleanup**: Impression data pruned after 90 days, order cache after 48 hours (configurable)
- **GDPR right to erasure**: Use `craft social-proof/default/purge-session --session-id=<id>` to delete all tracking data for a visitor
- **Consent integration**: Frontend `onBeforeTrack` callback for cookie consent banner integration

## CSS Customization

All notification styles use scoped `.social-proof-*` classes. Override them in your site CSS:

```css
.social-proof-notification {
    font-family: inherit;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}
```

The plugin includes built-in support for:
- Dark mode via `prefers-color-scheme: dark`
- Mobile responsive layout
- Reduced motion via `prefers-reduced-motion`

## Support

For support, please contact support@anvildev.com or visit https://anvildev.com/plugins/social-proof

## License

This plugin requires a license. See [LICENSE.md](LICENSE.md) for details.
