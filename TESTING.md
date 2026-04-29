# Social Proof Plugin - Testing Guide

This guide covers all features of the Social Proof Notifications plugin and how to test them.

---

## Prerequisites

- Plugin installed and enabled
- Access to Craft CMS admin panel
- Frontend page with `{{ craft.socialProof.init() }}` added (e.g., `home/_entry.twig`)

**Settings URL:** `/admin/settings/plugins/social-proof`

**Test Page URL:** `https://craft-starter.ddev.site/home`

---

## Test 1: Basic Setup Verification

### Goal
Verify the plugin is properly installed and can display notifications.

### Steps
1. Go to `/admin/settings/plugins/social-proof`
2. Confirm these settings:
   - [x] **Enable Social Proof** = ON
   - [x] **Demo Mode** = ON
   - [x] **Enable Purchase Notifications** = ON
3. Click **Save**
4. Open the test page in a new browser tab
5. Wait 5-10 seconds

### Expected Result
- A notification should slide in from the bottom-left corner
- Message format: "Sarah from Zürich purchased Premium Consultation Package"
- Notification should auto-dismiss after ~5 seconds
- Another notification should appear after ~10 seconds delay

### Troubleshooting
- Open browser DevTools (F12) → Console tab → Check for JavaScript errors
- Network tab → Check if `/actions/social-proof/api/get-notifications` returns data

---

## Test 2: Position Options

### Goal
Verify notifications can appear in all four corners.

### Steps
1. In settings, change **Position** to each option and test:
   - [ ] Bottom Left (default)
   - [ ] Bottom Right
   - [ ] Top Left
   - [ ] Top Right
2. Save after each change
3. Refresh the test page

### Expected Result
Notifications should appear in the selected corner with appropriate slide animation direction.

---

## Test 3: Timing Controls

### Goal
Verify display duration and delay between notifications work correctly.

### Steps
1. Set these values:
   - **Display Duration** = 3 seconds
   - **Delay Between** = 3 seconds
2. Save and refresh test page
3. Time the notifications with a stopwatch

### Expected Result
- Each notification visible for ~3 seconds
- ~3 second gap between notifications
- Notifications should cycle faster than default

### Reset
Set back to defaults: Display Duration = 5, Delay Between = 10

---

## Test 4: Animation Styles

### Goal
Test all animation combinations.

### Animation In Options
| Option | Effect |
|--------|--------|
| Slide In | Slides from the side |
| Fade In | Fades in place |
| Bounce In | Bounces/scales in |

### Animation Out Options
| Option | Effect |
|--------|--------|
| Slide Out | Slides to the side |
| Fade Out | Fades away |
| Bounce Out | Scales down and fades |

### Steps
1. Test each combination:
   - [ ] Slide In + Fade Out (recommended)
   - [ ] Fade In + Fade Out
   - [ ] Bounce In + Bounce Out
2. Save and refresh after each change

---

## Test 5: Viewer Count Notifications

### Goal
Test the "X people viewing this page" feature.

### Steps
1. In settings:
   - [x] **Enable Viewer Count** = ON
   - **Viewer Count Mode** = Static
   - **Minimum Viewers** = 12
2. Optionally customize the message template:
   ```
   {count} people are viewing this right now
   ```
3. Save and refresh test page

### Expected Result
- Should see notification: "12 people are viewing this right now"
- Should have a pulsing green "LIVE" indicator

### Viewer Count Modes

| Mode | Behavior |
|------|----------|
| **Static** | Always shows the minimum value |
| **Calculated** | Minimum + (recent activity × multiplier) |
| **Real** | Actual unique sessions in last 5 minutes |

---

## Test 6: Stock Warning Notifications

### Goal
Test low stock urgency notifications.

### Steps
1. In settings:
   - [x] **Enable Stock Warnings** = ON
   - [x] **Demo Mode** = ON (required without Commerce)
2. Save and refresh test page

### Expected Result
- Should see notifications like: "Only 3 Strategy Workshop Seats left!"
- Message should appear in red/urgent styling
- Should have a yellow "LOW STOCK" badge

---

## Test 7: Dismiss Button

### Goal
Test that users can manually close notifications.

### Steps
1. Ensure **Show Dismiss Button** = ON
2. Refresh test page
3. Hover over a notification
4. Click the X button that appears

### Expected Result
- X button should appear on hover (top-right of notification)
- Clicking X should immediately dismiss the notification
- Next notification should appear after the normal delay

---

## Test 8: Message Templates

### Goal
Customize notification messages using placeholders.

### Purchase Template Placeholders
- `{customer}` - Customer first name
- `{location}` - Customer city
- `{product}` - Product name

### Example Templates
```
{customer} just bought {product}!

Someone in {location} purchased {product}

New order: {product} - {customer} from {location}
```

### Steps
1. Change **Purchase Message Template** to a custom format
2. Save and refresh test page
3. Verify the new format is used

---

## Test 9: A/B Testing

### Goal
Verify that only a percentage of visitors see notifications.

### Steps
1. Enable **A/B Testing** = ON
2. Set **Test Percentage** = 50
3. Save settings
4. Open test page in multiple incognito/private windows
5. Some windows should show notifications, others shouldn't

### Expected Result
Approximately 50% of sessions should see notifications. The assignment is based on session ID hash, so the same session will consistently be in or out of the test group.

### Note
For accurate testing, use different browsers or clear cookies between tests.

---

## Test 10: URL Targeting

### Goal
Test include/exclude URL patterns.

### Test A: Include Patterns
1. Set **Include URL Patterns** to:
   ```
   /home*
   ```
2. Save settings
3. Visit `/home` → Should see notifications
4. Visit `/leistungen` → Should NOT see notifications

### Test B: Exclude Patterns
1. Clear Include patterns
2. Set **Exclude URL Patterns** to:
   ```
   /kontakt*
   /admin*
   ```
3. Save settings
4. Visit `/home` → Should see notifications
5. Visit `/kontakt` → Should NOT see notifications

### Wildcard Examples
| Pattern | Matches |
|---------|---------|
| `/products/*` | /products/anything |
| `*/checkout*` | anything with "checkout" |
| `/shop/category/*` | /shop/category/anything |

---

## Test 11: Session Limits

### Goal
Verify max notifications per session works.

### Steps
1. Set **Max Notifications Per Session** = 3
2. Save and refresh test page
3. Wait for 3 notifications to appear
4. Continue waiting

### Expected Result
After 3 notifications, no more should appear until you start a new session (clear cookies or incognito).

---

## Test 12: Mobile Responsiveness

### Goal
Test notifications on mobile devices.

### Steps
1. Open browser DevTools (F12)
2. Toggle device toolbar (mobile view)
3. Select iPhone or Android device
4. Refresh test page

### Expected Result
- Notifications should span full width on mobile
- Dismiss button should always be visible (not just on hover)
- Text should be readable

---

## Test 13: Dark Mode

### Goal
Test dark mode appearance (if system prefers dark mode).

### Steps
1. Enable dark mode in your OS settings
2. Refresh the test page

### Expected Result
- Notification background should be dark (#1f2937)
- Text should be light colored
- Shadows should be more prominent

---

## Test 14: Reduced Motion

### Goal
Test accessibility for users who prefer reduced motion.

### Steps
1. Enable "Reduce motion" in your OS accessibility settings
   - **macOS**: System Preferences → Accessibility → Display → Reduce motion
   - **Windows**: Settings → Ease of Access → Display → Show animations
2. Refresh test page

### Expected Result
- Notifications should fade in/out without sliding or bouncing
- No pulse animation on the live indicator

---

## Debugging

### Check API Response
```bash
curl -X GET "https://craft-starter.ddev.site/actions/social-proof/api/get-notifications?url=/home" \
  -H "Accept: application/json"
```

### Browser Console Commands
```javascript
// Check if Social Proof is loaded
console.log(window.socialProofConfig);

// Check container
document.querySelector('.social-proof-container');
```

### Clear Craft Caches
```bash
ddev craft clear-caches/all
```

---

## Feature Checklist

- [ ] Plugin enabled and settings accessible
- [ ] Demo mode showing fake notifications
- [ ] All 4 position options working
- [ ] Display duration configurable
- [ ] Delay between notifications configurable
- [ ] All animation in options working
- [ ] All animation out options working
- [ ] Viewer count notifications working
- [ ] Stock warning notifications working
- [ ] Dismiss button functional
- [ ] Custom message templates working
- [ ] A/B testing splitting traffic
- [ ] URL include patterns working
- [ ] URL exclude patterns working
- [ ] Session limit respected
- [ ] Mobile responsive
- [ ] Dark mode supported
- [ ] Reduced motion supported
