# Launch Readiness - v1.0.0 Plugin Store submission

Single source of truth for what's left between the working tree and the
Plugin Store push. Treat the boxes as gating: every unchecked item is
something a reviewer or first-day operator could legitimately notice.

Current state:

- Plugin version: **1.0.0**
- Schema version: **1.0.0**
- Test suite: **265 PHPUnit tests, 637 assertions, all green**
- Origin: `https://github.com/anvildevxyz/craft-social-proof.git`

## Code

- [x] Toast notifications (purchase, viewer-count, low-stock)
- [x] Popup subsystem (4 layouts + custom, 6 triggers, Craft-native targeting, fatigue, CP UI)
- [x] Outbound webhooks (HMAC-SHA256, queue-delivered, CP CRUD, secret rotation)
- [x] Per-subscription circuit breaker with CP "Auto-paused" badge (default 5 failures / 300s cooldown)
- [x] Recent-deliveries log per subscription, GC-pruned after 30 days
- [x] Revenue attribution to completed Commerce orders (last-click, configurable 1..720h window)
- [x] Best-effort dispatch: webhook + attribution failures are caught and logged, never break the API response

## Tests

- [x] `./vendor/bin/phpunit` → 265 tests, 0 failures
- [x] Smoke-test scenarios captured under `docs/smoke-tests/test-runs/`

## Documentation

- [x] `CHANGELOG.md` collapsed to a single 1.0.0 entry
- [x] `README.md` covers both surfaces, webhooks payload + signature, circuit breaker

## Plugin Store metadata

- [x] `composer.json` `version` = `1.0.0`
- [x] `composer.json` `extra.handle` / `extra.name` / `extra.class` set
- [x] `composer.json` `support.docs` / `extra.documentationUrl` / `extra.changelogUrl` set
- [x] `icon.svg` + `icon-mask.svg` present
- [x] 8 screenshots in `screenshots/` covering popup layouts + CP targeting + preview + custom layout
- [x] `composer.json` URLs all point at the public GitHub repo:
  - `support.docs` → `https://github.com/anvildevxyz/craft-social-proof`
  - `extra.documentationUrl` → same
  - `extra.changelogUrl` → `https://raw.githubusercontent.com/anvildevxyz/craft-social-proof/main/CHANGELOG.md`
- [ ] **Optional listing screenshots** (not blocking a technical release):
  - Webhooks tab with at least one configured subscription
  - Attribution settings fieldset on the plugin settings page
  - Dashboard widget with the Revenue column populated
- [ ] **License decision** - `composer.json` declares `proprietary`. Confirm this matches the chosen distribution path

## Host project hygiene

- [ ] Re-run `ddev composer update anvildev/craft-social-proof` so the host `composer.lock` reflects 1.0.0
