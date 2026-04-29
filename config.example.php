<?php
/**
 * Social Proof config file
 *
 * Copy this file to your Craft project at:
 *   config/social-proof.php
 *
 * Values defined here override plugin settings from the CP.
 * Supports multi-environment configuration.
 *
 * @see \anvildev\socialproof\models\Settings
 */

return [
    // Global defaults
    '*' => [
        // Master on/off switch
        // 'enabled' => true,

        // Max notifications shown per visitor session
        // 'maxNotificationsPerSession' => 10,

        // Show demo notifications with fake data (no Commerce required)
        // 'demoMode' => false,

        // ── Appearance ──────────────────────────────────────────
        // 'position' => 'bottom-left',        // bottom-left, bottom-right, top-left, top-right
        // 'displayDuration' => 5,             // seconds per notification
        // 'delayBetween' => 10,               // seconds between notifications
        // 'animationIn' => 'slideIn',         // slideIn, fadeIn, bounceIn
        // 'animationOut' => 'fadeOut',         // slideOut, fadeOut, bounceOut
        // 'showProductImage' => true,
        // 'showDismissButton' => true,

        // ── Purchase Notifications (requires Commerce) ──────────
        // 'purchaseEnabled' => true,
        // 'purchaseLookbackHours' => 24,
        // 'anonymizeCustomers' => true,        // show first name only
        // 'purchaseTemplate' => '{customer} from {location} purchased {product}',
        // 'excludedProductTypes' => [],        // product type handles to exclude

        // ── Viewer Count ────────────────────────────────────────
        // 'viewersEnabled' => false,
        // 'viewersMode' => 'real',            // real, calculated, static
        // 'viewersMinimum' => 5,
        // 'viewersMultiplier' => 1,           // multiplier for calculated mode
        // 'viewersTemplate' => '{count} people are viewing this right now',

        // ── Stock Warnings (requires Commerce) ──────────────────
        // 'stockEnabled' => false,
        // 'stockThreshold' => 10,
        // 'stockTemplate' => 'Only {count} left in stock!',

        // ── A/B Testing ─────────────────────────────────────────
        // 'abTestingEnabled' => false,
        // 'abTestPercentage' => 50,           // 1-99

        // ── URL Targeting ───────────────────────────────────────
        // 'includedUrlPatterns' => [],         // only show on matching URLs (* wildcard)
        // 'excludedUrlPatterns' => [],         // never show on matching URLs (* wildcard)

        // ── Phase 2 Popup Settings ──────────────────────────────
        // Show popup previews with fake triggers (no real visitor data)
        'popupDemoMode' => false,
        // Minimum seconds between popup event writes per visitor (rate limiting)
        'popupEventRateLimit' => 60,
    ],

    // Development overrides
    'dev' => [
        'demoMode' => true,
        'popupDemoMode' => true,
        'popupEventRateLimit' => 0,  // no rate limiting in dev
    ],

    // Staging overrides
    'staging' => [
        'demoMode' => true,
        'popupDemoMode' => true,
    ],

    // Production overrides
    'production' => [
        'demoMode' => false,
        'popupDemoMode' => false,
        'popupEventRateLimit' => 60,
    ],
];
