# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-04-29

Initial public release. Two conversion-messaging surfaces sharing one
arbitration engine, fatigue model, and impression pipeline.

### Toast notifications

- Purchase notifications, viewer-count, and low-stock toasts
- Optional Craft Commerce integration for purchase + stock data
- Configurable position, animation, and viewer-count mode
- Per-visitor fatigue and impression tracking

### Popups

- Four built-in layouts: announcement, newsletter, discount code, bottom
  bar; plus `custom` for developer-authored Twig templates
- Six triggers: page load, time on page, scroll depth, exit intent,
  element click, inactivity
- Craft-native targeting: URL patterns, sections, entries, user groups,
  logged-in state
- CP edit form with live preview iframe and device-width toggle
- Multi-site support with configurable propagation

### Outbound webhooks

- Signed HTTP POSTs (HMAC-SHA256) for `popup.impression`, `popup.click`,
  `popup.dismiss`, `popup.convert`
- CP CRUD with inline enable/disable, secret reveal, and rotation
- Async delivery via Craft's queue with 5s request / 3s connect timeout
- Per-subscription circuit breaker (default 5 failures / 300s cooldown,
  `webhookBreakerThreshold = 0` disables) with CP "Auto-paused" badge
- Recent-deliveries log per subscription, pruned by GC after 30 days

### Revenue attribution

- Last-click attribution to completed Commerce orders within a
  configurable window (1..720h, default 24h)
- Revenue surfaced in the `social-proof/popups/stats` CLI and the
  `PopupStatsWidget` dashboard widget, formatted to two decimals
- Best-effort dispatch: webhook + attribution failures are caught and
  logged, never break the API response

### Operational

- 265 unit + integration tests (PHPUnit), 637 assertions
- German and French translations
- GC handlers for impressions, fatigue, attributions, delivery log
- Permissions: `socialProof-manageSettings` gates all settings CRUD
