<?php
/**
 * Social Proof EN translations
 *
 * Translators: copy this file to your Craft project at
 *   translations/<locale>/social-proof.php
 * and modify the values on the right side.
 */

return [
    // General
    '{name} plugin loaded' => '{name} plugin loaded',
    'Social Proof' => 'Social Proof',
    'Social Proof Settings' => 'Social Proof Settings',
    'Social Proof Statistics' => 'Social Proof Statistics',
    'Social Proof Stats' => 'Social Proof Stats',

    // Navigation & sections
    'Notifications' => 'Notifications',
    'Statistics' => 'Statistics',
    'Settings' => 'Settings',
    'Appearance' => 'Appearance',
    'Purchase Notifications' => 'Purchase Notifications',
    'Viewer Count Notifications' => 'Viewer Count Notifications',
    'Stock Warning Notifications' => 'Stock Warning Notifications',
    'A/B Testing' => 'A/B Testing',
    'URL Targeting' => 'URL Targeting',

    // Elements
    'Notification' => 'Notification',
    'notification' => 'notification',
    'notifications' => 'notifications',
    'All Notifications' => 'All Notifications',
    'New Notification' => 'New Notification',
    'Notification saved.' => 'Notification saved.',
    "Couldn't save notification." => "Couldn't save notification.",

    // Element types
    'Purchase' => 'Purchase',
    'Viewers' => 'Viewers',
    'Low Stock' => 'Low Stock',
    'Custom' => 'Custom',

    // Element statuses
    'Enabled' => 'Enabled',
    'Disabled' => 'Disabled',

    // Element table attributes
    'Type' => 'Type',
    'Position' => 'Position',
    'Duration' => 'Duration',
    'Delay' => 'Delay',
    'Display Duration' => 'Display Duration',
    'Delay Between' => 'Delay Between',

    // Settings: General
    'Enable Social Proof' => 'Enable Social Proof',
    'Turn notifications on or off globally.' => 'Turn notifications on or off globally.',
    'Max Notifications Per Session' => 'Max Notifications Per Session',
    'Maximum number of notifications to show each visitor per session.' => 'Maximum number of notifications to show each visitor per session.',
    'Demo Mode' => 'Demo Mode',
    'Show demo notifications with fake data. Perfect for testing without Commerce or real orders.' => 'Show demo notifications with fake data. Perfect for testing without Commerce or real orders.',
    'Demo mode is enabled. Disable for production.' => 'Demo mode is enabled. Disable for production.',

    // Settings: Appearance
    'Where notifications appear on the screen.' => 'Where notifications appear on the screen.',
    'Seconds to show each notification.' => 'Seconds to show each notification.',
    'Seconds to wait between notifications.' => 'Seconds to wait between notifications.',
    'Seconds before showing next notification.' => 'Seconds before showing next notification.',
    'Animation In' => 'Animation In',
    'How the notification appears.' => 'How the notification appears.',
    'Animation Out' => 'Animation Out',
    'How the notification disappears.' => 'How the notification disappears.',
    'Show Product Image' => 'Show Product Image',
    'Display product thumbnail in notifications.' => 'Display product thumbnail in notifications.',
    'Show Dismiss Button' => 'Show Dismiss Button',
    'Allow users to close notifications.' => 'Allow users to close notifications.',

    // Positions
    'Bottom Left' => 'Bottom Left',
    'Bottom Right' => 'Bottom Right',
    'Top Left' => 'Top Left',
    'Top Right' => 'Top Right',

    // Animations
    'Slide In' => 'Slide In',
    'Fade In' => 'Fade In',
    'Bounce In' => 'Bounce In',
    'Slide Out' => 'Slide Out',
    'Fade Out' => 'Fade Out',
    'Bounce Out' => 'Bounce Out',

    // Settings: Purchase
    'Enable Purchase Notifications' => 'Enable Purchase Notifications',
    'Show recent purchase activity to visitors.' => 'Show recent purchase activity to visitors.',
    'Lookback Hours' => 'Lookback Hours',
    'How far back to look for purchases.' => 'How far back to look for purchases.',
    'Anonymize Customer Names' => 'Anonymize Customer Names',
    'Only show first name for privacy.' => 'Only show first name for privacy.',
    'Purchase Message Template' => 'Purchase Message Template',
    'Use {customer}, {location}, and {product} as placeholders.' => 'Use {customer}, {location}, and {product} as placeholders.',
    'Excluded Product Types' => 'Excluded Product Types',
    'Product types to exclude from notifications.' => 'Product types to exclude from notifications.',

    // Settings: Viewers
    'Enable Viewer Count' => 'Enable Viewer Count',
    'Show how many people are viewing a page.' => 'Show how many people are viewing a page.',
    'Viewer Count Mode' => 'Viewer Count Mode',
    'How to calculate the viewer count.' => 'How to calculate the viewer count.',
    'Real-time (actual visitors)' => 'Real-time (actual visitors)',
    'Calculated (based on traffic)' => 'Calculated (based on traffic)',
    'Static (minimum value)' => 'Static (minimum value)',
    'Minimum Viewers' => 'Minimum Viewers',
    'Minimum number to display.' => 'Minimum number to display.',
    'Viewer Multiplier' => 'Viewer Multiplier',
    'Multiplier for calculated mode.' => 'Multiplier for calculated mode.',
    'Viewer Message Template' => 'Viewer Message Template',
    'Use {count} as a placeholder.' => 'Use {count} as a placeholder.',

    // Settings: Stock
    'Enable Stock Warnings' => 'Enable Stock Warnings',
    'Show low stock warnings for products.' => 'Show low stock warnings for products.',
    'Stock Threshold' => 'Stock Threshold',
    'Show warning when stock is at or below this number.' => 'Show warning when stock is at or below this number.',
    'Stock Message Template' => 'Stock Message Template',
    'Use {count} and {product} as placeholders.' => 'Use {count} and {product} as placeholders.',

    // Settings: A/B Testing
    'Enable A/B Testing' => 'Enable A/B Testing',
    'Only show notifications to a percentage of visitors for conversion testing.' => 'Only show notifications to a percentage of visitors for conversion testing.',
    'Test Percentage' => 'Test Percentage',
    'Percentage of visitors who will see notifications (1-99).' => 'Percentage of visitors who will see notifications (1-99).',

    // Settings: URL Targeting
    'Include URL Patterns' => 'Include URL Patterns',
    'Only show on URLs matching these patterns (one per line). Leave empty for all pages. Use * as wildcard.' => 'Only show on URLs matching these patterns (one per line). Leave empty for all pages. Use * as wildcard.',
    'Exclude URL Patterns' => 'Exclude URL Patterns',
    'Never show on URLs matching these patterns (one per line). Use * as wildcard.' => 'Never show on URLs matching these patterns (one per line). Use * as wildcard.',

    // Commerce integration
    '(requires Commerce)' => '(requires Commerce)',
    'Commerce is not installed.' => 'Commerce is not installed.',
    'Craft Commerce is not installed. Enable Demo Mode above to show sample notifications.' => 'Craft Commerce is not installed. Enable Demo Mode above to show sample notifications.',
    'Craft Commerce is not installed. Enable Demo Mode above to show sample stock warnings.' => 'Craft Commerce is not installed. Enable Demo Mode above to show sample stock warnings.',
    'Imported {count} orders.' => 'Imported {count} orders.',

    // Settings controller
    'Settings saved.' => 'Settings saved.',
    "Couldn't save settings." => "Couldn't save settings.",
    'Cleaned up {impressions} impression records and {orders} order records.' => 'Cleaned up {impressions} impression records and {orders} order records.',

    // Statistics page
    'Impressions' => 'Impressions',
    'Clicks' => 'Clicks',
    'Dismisses' => 'Dismisses',
    'CTR' => 'CTR',
    'Click-Through Rate' => 'Click-Through Rate',
    'Unique Visitors' => 'Unique Visitors',
    'Daily Activity' => 'Daily Activity',
    'Top Performing Notifications' => 'Top Performing Notifications',
    'View Details' => 'View Details',
    '7 Days' => '7 Days',
    '30 Days' => '30 Days',
    '90 Days' => '90 Days',
    'Last 7 days' => 'Last 7 days',
    'Date' => 'Date',

    // Notification edit form
    'The type of notification to display.' => 'The type of notification to display.',
    'Internal name to identify this notification.' => 'Internal name to identify this notification.',
    'Message' => 'Message',
    'The message to display.' => 'The message to display.',
    'Link URL' => 'Link URL',
    'Optional URL to link to when clicked.' => 'Optional URL to link to when clicked.',
    'Display Settings' => 'Display Settings',
    'Custom Notification Settings' => 'Custom Notification Settings',

    // Propagation
    'Propagation Method' => 'Propagation Method',
    'Which sites should this notification be saved to?' => 'Which sites should this notification be saved to?',
    'Changing this may result in data loss.' => 'Changing this may result in data loss.',
    'Only save to the {site} site' => 'Only save to the {site} site',
    'Save to all sites' => 'Save to all sites',
    'Save to sites in the same site group' => 'Save to sites in the same site group',
    'Save to sites with the same language' => 'Save to sites with the same language',
    'Content below is specific to the current site.' => 'Content below is specific to the current site.',
    'Settings below are shared across all sites.' => 'Settings below are shared across all sites.',

    // Missing template keys
    'Social Proof Notifications' => 'Social Proof Notifications',
    'Seconds to show this notification.' => 'Seconds to show this notification.',

    // Permissions
    'Manage notifications' => 'Manage notifications',
    'View statistics' => 'View statistics',
    'Manage settings' => 'Manage settings',

    // Time ago
    'just now' => 'just now',
    '1 minute ago' => '1 minute ago',
    '{n} minutes ago' => '{n} minutes ago',
    '1 hour ago' => '1 hour ago',
    '{n} hours ago' => '{n} hours ago',
    '1 day ago' => '1 day ago',
    '{n} days ago' => '{n} days ago',

    // Export
    'Export CSV' => 'Export CSV',
    'Orders (24h)' => 'Orders (24h)',
];
