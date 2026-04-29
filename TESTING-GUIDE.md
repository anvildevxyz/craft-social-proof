# Social Proof Plugin - Manual Testing Guide

This guide covers every feature, setting, and variation. Work through each section and check the boxes as you go.

**Setup**: Make sure `{{ craft.socialProof.init() }}` is in your layout template before `</body>`.

---

## 1. General Settings

Go to **Social Proof -> Settings -> General**.

### 1.1 Enable/Disable Plugin
- [ ] **Enabled ON**: Notifications appear on the frontend
- [ ] **Enabled OFF**: No notifications appear, no JS errors in console
- [ ] **Enabled OFF**: The `{{ craft.socialProof.init() }}` call outputs nothing (empty string, no container div)

### 1.2 Max Notifications Per Session
- [ ] Set to **2** - only 2 notifications appear, then they stop (reload page to confirm no more appear)
- [ ] Set to **1** - only 1 notification appears
- [ ] Set back to **10** - up to 10 notifications appear across the session
- [ ] Open a new incognito window - counter resets (new session)

### 1.3 Demo Mode
- [ ] **Demo ON, Commerce installed**: Demo purchase notifications appear with Swiss-themed fake data (Sarah from Zurich, Michael from Basel, etc.)
- [ ] **Demo OFF, Commerce installed**: Only real Commerce orders appear (if any exist)
- [ ] **Demo ON, no Commerce**: Demo notifications still work (plugin is usable without Commerce)
- [ ] Check for the yellow warning banner in CP settings: *"Demo mode is enabled. Disable for production."*

---

## 2. Appearance Settings

### 2.1 Position (test all 4)
- [ ] **Bottom Left** - notification appears in bottom-left corner
- [ ] **Bottom Right** - notification appears in bottom-right corner
- [ ] **Top Left** - notification appears in top-left corner
- [ ] **Top Right** - notification appears in top-right corner
- [ ] Resize browser to mobile width - notification adapts (full width on small screens)

### 2.2 Display Duration
- [ ] Set to **3 seconds** - notification disappears quickly
- [ ] Set to **10 seconds** - notification stays longer
- [ ] Set to **1 second** (minimum) - still shows briefly before animating out

### 2.3 Delay Between Notifications
- [ ] Set to **3 seconds** - next notification appears shortly after the first hides
- [ ] Set to **30 seconds** - long pause between notifications
- [ ] Verify the delay timer starts AFTER the hide animation completes

### 2.4 Animation In (test all 3)
- [ ] **Slide In** - notification slides in from the side
- [ ] **Fade In** - notification fades in
- [ ] **Bounce In** - notification bounces in

### 2.5 Animation Out (test all 3)
- [ ] **Slide Out** - notification slides out
- [ ] **Fade Out** - notification fades out
- [ ] **Bounce Out** - notification bounces out

### 2.6 Mix and Match
- [ ] Try **Bounce In + Slide Out** - both animations work together
- [ ] Try **Fade In + Bounce Out** - no conflicts

### 2.7 Show Product Image
- [ ] **ON**: Product thumbnail appears in purchase/stock notifications (if Commerce provides one)
- [ ] **OFF**: No image shown, notification is text-only
- [ ] Demo mode: image is always `null`, so this only affects real Commerce orders

### 2.8 Show Dismiss Button
- [ ] **ON**: X button visible on notifications, clicking it dismisses
- [ ] **OFF**: No X button, notifications can only auto-dismiss or be dismissed via Escape key

---

## 3. Purchase Notifications

Enable: **Purchase Notifications ON**, **Demo Mode ON** (easiest to test).

### 3.1 Basic Display
- [ ] Purchase notification appears with customer name, location, product name
- [ ] Time ago text displays (e.g. "5 minutes ago")
- [ ] Message follows the template format

### 3.2 Purchase Message Template
- [ ] Default template: `{customer} from {location} purchased {product}` - all placeholders resolve
- [ ] Change to `{customer} just bought {product}!` - location is omitted, message renders correctly
- [ ] Change to `New sale: {product}` - only product shows
- [ ] Empty template - notification shows empty message (edge case)

### 3.3 Anonymize Customer Names
- [ ] **ON**: Only first names appear (Sarah, Michael, Anna)
- [ ] **OFF**: Full names appear (requires real Commerce orders to test properly)

### 3.4 Lookback Hours
- [ ] Set to **1 hour** - only very recent orders appear
- [ ] Set to **48 hours** - orders from the last 2 days appear
- [ ] With demo mode, this doesn't affect demo data (demo is always generated fresh)

### 3.5 With Real Commerce Orders
*(Only if Commerce is installed and has orders)*
- [ ] Complete a test order - purchase notification appears for that product
- [ ] Product name matches the order line item
- [ ] Customer name comes from the billing/shipping address
- [ ] Product URL links to the product page
- [ ] Product image shows if the product has an asset field (productImage, image, etc.)

### 3.6 Excluded Product Types
- [ ] Add a product type handle to the exclusion list
- [ ] Complete an order for that product type - no notification appears
- [ ] Complete an order for a different product type - notification appears

---

## 4. Viewer Count Notifications

Enable: **Viewer Count ON**.

### 4.1 Static Mode
- [ ] Set mode to **Static** - always shows `viewersMinimum` (default: 5)
- [ ] Change `viewersMinimum` to **12** - shows "12 people are viewing this right now"
- [ ] Change `viewersMinimum` to **0** - no viewer notification appears (count < minimum)

### 4.2 Real-Time Mode
- [ ] Set mode to **Real-time** - counts unique sessions in last 5 minutes
- [ ] With `viewersMinimum = 1`: shows actual count (likely 1 on local)
- [ ] With `viewersMinimum = 5`: shows 5 even if only 1 real visitor (floor applied)
- [ ] Open 3 different browsers/incognito windows - count increases (may take a heartbeat cycle)

### 4.3 Calculated Mode
- [ ] Set mode to **Calculated** - shows `viewersMinimum + (recentSessions * multiplier)`
- [ ] Set `viewersMultiplier = 3` - count inflates based on recent activity
- [ ] With no recent activity: shows just `viewersMinimum`

### 4.4 Viewer Message Template
- [ ] Default: `{count} people are viewing this right now` - count placeholder resolves
- [ ] Change to `{count} visitors online` - custom message works
- [ ] The notification shows a pulsing "Live" indicator

### 4.5 Viewer Notification Appearance
- [ ] Has a people/group SVG icon on the left
- [ ] Has a pulsing green dot with "Live" label
- [ ] Dismiss button works (if enabled)

---

## 5. Stock Warning Notifications

Enable: **Stock Warnings ON**, **Demo Mode ON**.

### 5.1 Demo Stock Warnings
- [ ] Stock warning notifications appear (e.g., "Only 3 left in stock!")
- [ ] Shows a "Low Stock" badge
- [ ] Product name displays below the message

### 5.2 Stock Message Template
- [ ] Default: `Only {count} left in stock!` - both placeholders work
- [ ] Change to `Hurry! {product} - just {count} remaining` - custom template renders

### 5.3 Stock Threshold (requires Commerce)
- [ ] Set threshold to **5** - only products with stock <= 5 trigger warnings
- [ ] Set threshold to **50** - more products qualify
- [ ] Products with unlimited stock are excluded
- [ ] Products with 0 stock are excluded (only shows > 0)

### 5.4 With Real Commerce Products
*(Only if Commerce is installed with inventory-tracked products)*
- [ ] Set a variant's stock to 3 (below threshold) - stock warning appears
- [ ] Set stock to 100 (above threshold) - no warning
- [ ] Set stock to 0 - no warning (sold out, not "low")

---

## 6. A/B Testing

Enable: **A/B Testing ON**.

### 6.1 Test Percentage
- [ ] Set to **50%** - roughly half of visitors see notifications (test with multiple incognito windows)
- [ ] Set to **1%** - almost no one sees notifications
- [ ] Set to **99%** - almost everyone sees notifications
- [ ] Same visitor always gets the same assignment (consistent based on session ID hash)

### 6.2 A/B Testing OFF
- [ ] **Disabled**: All visitors see notifications (no filtering)

---

## 7. URL Targeting

### 7.1 Include Patterns
- [ ] Add `/products/*` - notifications only appear on product pages
- [ ] Add `/` (just the homepage) - only appears on homepage
- [ ] Leave empty - appears on ALL pages (default)
- [ ] Add multiple patterns (one per line) - matches any of them

### 7.2 Exclude Patterns
- [ ] Add `/admin/*` - no notifications on admin pages
- [ ] Add `/checkout*` - no notifications during checkout
- [ ] Exclude takes priority over include

### 7.3 Wildcard Matching
- [ ] `*` matches any characters: `/products/*` matches `/products/widget` and `/products/foo/bar`
- [ ] Exact URL: `/about` only matches `/about`, not `/about-us`

---

## 8. Notification Elements (CP Management)

Go to **Social Proof -> Notifications**.

### 8.1 Element Index
- [ ] Index page loads with all notifications listed
- [ ] Source sidebar shows: All, Purchase, Viewers, Low Stock, Custom
- [ ] Clicking a source filters the list
- [ ] Table columns show: Title, Type, Position, Duration, Date Created
- [ ] Sorting works on all columns

### 8.2 Create Notification
- [ ] Click "New Notification" - edit form loads
- [ ] Fill in title, select type, save - redirects to index
- [ ] Success flash message: "Notification saved."
- [ ] New notification appears in the list

### 8.3 Edit Notification
- [ ] Click existing notification - edit form loads with populated values
- [ ] Change title and save - updates correctly
- [ ] Change type - type-specific settings panel toggles
- [ ] Change position - saves the new position

### 8.4 Notification Types
- [ ] **Purchase** type - shows purchase-specific settings panel
- [ ] **Viewers** type - shows viewer-specific settings panel
- [ ] **Stock** type - shows stock-specific settings panel
- [ ] **Custom** type - shows custom message/URL fields
- [ ] Without Commerce: Purchase and Stock types are disabled with "(requires Commerce)" label

### 8.5 Display Settings (per notification)
- [ ] Display Duration: set to 3s - saves correctly
- [ ] Delay Between: set to 5s - saves correctly
- [ ] Position override: per-notification position works

### 8.6 Enable/Disable
- [ ] Toggle notification enabled/disabled via the lightswitch
- [ ] Disabled notifications don't appear on the frontend
- [ ] Bulk actions: select multiple, use "Set Status" to enable/disable

### 8.7 Delete
- [ ] Delete a notification - confirmation prompt appears
- [ ] Deleted notification removed from list
- [ ] Bulk delete works

---

## 9. Statistics Dashboard

Go to **Social Proof -> Statistics**.

### 9.1 Overview Cards
- [ ] Impressions count displays
- [ ] Clicks count displays
- [ ] Dismisses count displays
- [ ] CTR (click-through rate) calculated correctly
- [ ] Unique Visitors count displays

### 9.2 Period Selector
- [ ] **7 Days** - shows last 7 days of data
- [ ] **30 Days** - shows last 30 days
- [ ] **90 Days** - shows last 90 days
- [ ] Switching periods updates all stats

### 9.3 Daily Activity Chart
- [ ] Chart shows bars/data for each day in the period
- [ ] Days with no activity show as 0
- [ ] Hover/tooltips show daily breakdown

### 9.4 Top Performing Notifications
- [ ] Lists notifications with highest CTR
- [ ] Only shows notifications with > 10 impressions
- [ ] Shows notification ID, impressions, clicks, CTR

### 9.5 Commerce Stats
*(Only if Commerce installed)*
- [ ] "Recent Orders (24h)" count displays
- [ ] Count matches actual orders in the cache table

---

## 10. Dashboard Widget

### 10.1 Add Widget
- [ ] Go to Craft Dashboard - click "New Widget"
- [ ] "Social Proof Stats" appears in the widget list
- [ ] Add it - widget displays on dashboard

### 10.2 Widget Content
- [ ] Shows impressions, clicks, CTR for last 7 days
- [ ] Shows trend arrows (up/down/neutral) compared to previous 7 days
- [ ] "View Details" link goes to the full statistics page

---

## 11. Console Commands

Run these via `ddev exec craft <command>` or your local CLI.

### 11.1 Stats
```bash
craft social-proof/default/stats
```
- [ ] Displays impressions, clicks, dismisses, CTR, unique visitors
- [ ] Shows order count if Commerce installed

### 11.2 Cleanup
```bash
craft social-proof/default/cleanup
craft social-proof/default/cleanup --impression-days=30 --order-hours=24
```
- [ ] Reports number of impression records removed
- [ ] Reports number of order cache records removed
- [ ] Custom retention periods work via flags

### 11.3 Import Orders
```bash
craft social-proof/default/import-orders
craft social-proof/default/import-orders --limit=100
```
- [ ] *(Commerce only)* Imports recent orders into notification cache
- [ ] Reports count of imported orders
- [ ] Skips already-imported orders (no duplicates)
- [ ] Without Commerce: shows error message

### 11.4 Purge Session (GDPR)
```bash
craft social-proof/default/purge-session --session-id=<id>
```
- [ ] Deletes all tracking records for the given session ID
- [ ] Reports number of deleted records
- [ ] Shows "No records found" for non-existent session
- [ ] Without `--session-id`: shows error message

---

## 12. Permissions

Go to **Settings -> Users** and edit a non-admin user's permissions.

### 12.1 Permission Registration
- [ ] "Social Proof" section appears in user permissions
- [ ] Three checkboxes: Manage notifications, View statistics, Manage settings

### 12.2 Manage Notifications Permission
- [ ] **Granted**: User sees "Notifications" in subnav, can create/edit/delete
- [ ] **Denied**: "Notifications" hidden from subnav, direct URL returns 403

### 12.3 View Statistics Permission
- [ ] **Granted**: User sees "Statistics" in subnav, can view stats
- [ ] **Denied**: "Statistics" hidden from subnav, direct URL returns 403

### 12.4 Manage Settings Permission
- [ ] **Granted**: User sees "Settings" in subnav, can change settings
- [ ] **Denied**: "Settings" hidden from subnav, direct URL returns 403

### 12.5 Admin Override
- [ ] Admin users see everything regardless of permissions

---

## 13. Frontend JavaScript Features

### 13.1 Keyboard Accessibility
- [ ] Press **Escape** while a notification is visible - it dismisses
- [ ] Press Escape with no notification visible - no error

### 13.2 Screen Reader Accessibility
- [ ] Container has `aria-live="polite"` (inspect in DevTools)
- [ ] Each notification has `role="status"`
- [ ] Dismiss button has `aria-label="Dismiss notification"`
- [ ] SVG icons have `aria-hidden="true"`

### 13.3 CSRF Token Refresh
- [ ] Let your session expire (or clear session cookies)
- [ ] Click a notification - should get 419, auto-refresh token, retry successfully
- [ ] Check browser console - no persistent 419 errors

### 13.4 Consent Hook
Add this to your template before `{{ craft.socialProof.init() }}`:
```html
<script>
window.socialProofConfig = {
    ...window.socialProofConfig,
    onBeforeTrack: function(eventType) {
        console.log('Consent check for:', eventType);
        return false; // Block all tracking
    }
};
</script>
```
- [ ] Notifications still DISPLAY (consent only blocks tracking)
- [ ] No tracking requests sent to server (check Network tab)
- [ ] Console logs show the consent check being called
- [ ] Change `return false` to `return true` - tracking resumes

### 13.5 Heartbeat
- [ ] Open Network tab - heartbeat POST sent every 30 seconds
- [ ] Switch to another tab - heartbeats STOP (visibilitychange)
- [ ] Switch back - heartbeats RESUME
- [ ] Rate limited: max 4/minute (fast tab switching won't flood)

### 13.6 Click Tracking
- [ ] Click a notification with a `productUrl` - navigates to the URL
- [ ] Click a notification without `productUrl` - no navigation
- [ ] Click the dismiss X - tracks dismiss event, does NOT navigate

### 13.7 XSS Prevention
- [ ] Notification messages are HTML-escaped (no raw HTML renders)
- [ ] Product names with `<script>` tags are escaped safely

---

## 14. CSS & Visual

### 14.1 Dark Mode
- [ ] Enable dark mode in OS/browser preferences
- [ ] Notification background changes to dark (#1f2937)
- [ ] Text is light colored and readable
- [ ] Dismiss button adapts to dark theme
- [ ] Pulse indicator is still visible

### 14.2 Mobile/Responsive
- [ ] On mobile width (< 480px): notification is full-width with margin
- [ ] Touch targets are large enough (dismiss button)
- [ ] No horizontal overflow

### 14.3 Reduced Motion
- [ ] Enable "Reduce motion" in OS accessibility settings
- [ ] Animations are disabled (instant show/hide)
- [ ] Pulse animation stops

### 14.4 CSS Overrides
Add custom CSS to your site:
```css
.social-proof-notification {
    border-radius: 0;
    font-family: serif;
}
```
- [ ] Custom styles apply without `!important`
- [ ] Plugin styles don't leak outside `.social-proof-*` scope

### 14.5 Focus Visibility
- [ ] Tab to the dismiss button - visible blue focus ring appears
- [ ] Focus ring is clearly visible on both light and dark backgrounds

---

## 15. Config File Overrides

Copy `config.example.php` to `config/social-proof.php`.

### 15.1 Basic Override
```php
return ['*' => ['position' => 'top-right']];
```
- [ ] Notifications now appear top-right, regardless of CP setting
- [ ] CP settings page still shows the old value (config override takes precedence)

### 15.2 Multi-Environment
```php
return [
    '*' => ['enabled' => true],
    'dev' => ['demoMode' => true],
    'production' => ['demoMode' => false],
];
```
- [ ] On dev environment: demo mode is on
- [ ] On production: demo mode is off

### 15.3 Remove Config File
- [ ] Delete `config/social-proof.php` - CP settings take effect again

---

## 16. Twig Template Variables

### 16.1 `craft.socialProof.init()`
```twig
{{ craft.socialProof.init() }}
```
- [ ] Outputs container div
- [ ] Registers JS and CSS assets
- [ ] With options: `{{ craft.socialProof.init({ position: 'top-left' }) }}` - overrides position

### 16.2 `craft.socialProof.isEnabled()`
```twig
{% if craft.socialProof.isEnabled() %}Enabled{% else %}Disabled{% endif %}
```
- [ ] Returns `true` when plugin enabled
- [ ] Returns `false` when disabled

### 16.3 `craft.socialProof.viewerCount()`
```twig
{{ craft.socialProof.viewerCount() }}
```
- [ ] Returns integer viewer count
- [ ] Returns 0 when viewer counting is disabled

### 16.4 `craft.socialProof.recentPurchaseCount(24)`
```twig
{{ craft.socialProof.recentPurchaseCount(24) }}
```
- [ ] Returns count of cached orders in last 24 hours
- [ ] Returns 0 if no orders or no Commerce

### 16.5 `craft.socialProof.isCommerceInstalled()`
- [ ] Returns `true` if Commerce plugin is installed
- [ ] Returns `false` otherwise

### 16.6 Security: Options Whitelist
```twig
{{ craft.socialProof.init({ csrfToken: 'hacked', endpoint: 'https://evil.com' }) }}
```
- [ ] `csrfToken` and `endpoint` are NOT overridden (whitelist blocks them)
- [ ] Only `position`, `displayDuration`, `delayBetween`, `animationIn`, `animationOut` are accepted

---

## 17. Edge Cases & Error Handling

### 17.1 No Notifications Available
- [ ] All notification types disabled - no errors, no JS console errors, empty queue handled gracefully

### 17.2 Commerce Not Installed
- [ ] Plugin loads without errors
- [ ] Purchase and Stock types disabled in notification edit form
- [ ] Settings page shows Commerce warning messages
- [ ] Demo mode still works for purchase notifications

### 17.3 Multiple Notification Types
- [ ] Enable Purchase + Viewers + Stock simultaneously
- [ ] All three types appear in the queue (shuffled order)
- [ ] Queue respects `maxNotificationsPerSession` across all types

### 17.4 Rapid Page Navigation
- [ ] Navigate between pages quickly - no duplicate notifications, no JS errors
- [ ] Heartbeat timer properly stops and restarts

### 17.5 Browser Back/Forward
- [ ] Use browser back/forward buttons - notifications work on cached pages

### 17.6 Rate Limiting
- [ ] Open browser console, manually fire 61+ tracking requests - should get 429 response
- [ ] Normal usage never hits rate limits

---

## Quick Smoke Test Checklist

For a fast sanity check, verify these core paths:

- [ ] Fresh install: plugin installs without errors
- [ ] Enable demo mode: fake notifications appear on frontend
- [ ] Change position to each corner: visual position changes
- [ ] Create a custom notification element: saves and appears in index
- [ ] Statistics page loads: shows zeros or real data
- [ ] Console `craft social-proof/default/stats`: outputs stats
- [ ] Disable plugin: no notifications, no JS errors
- [ ] Re-enable: notifications resume
