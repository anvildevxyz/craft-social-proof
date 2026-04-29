# Popup Accessibility Audit - 2026-04-22

Automated scan using `axe-core` v4 against the rendered DOM of each Phase 2 popup layout.

## Method

1. Render the popup via a synthetic `page-match` trigger on a smoke template
2. Inject `axe-core` from CDN into the page
3. Run `axe.run($popupElement, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa'] } })`
4. Collect violations + passing rules

## Results

| Layout | Violations | Passes |
|---|---|---|
| announcement | 0 | 13 |
| newsletter | 0 | 14 |
| discount | 0 | 13 |
| bottom-bar | 0 | 12 |

**Zero WCAG 2.0 Level A or AA violations across all four layouts.**

## Covered rules

Each layout passes tests including (but not limited to):

- `aria-valid-attr` / `aria-valid-attr-value` / `aria-required-children` / `aria-required-parent`
- `button-name` (dismiss + CTA buttons have accessible names)
- `color-contrast` (text meets 4.5:1 / 3:1 per text size)
- `label` / `form-field-multiple-labels` (newsletter email input)
- `duplicate-id` (unique ids per popup instance via `data-popup-id`)
- `html-has-lang` (inherited from host page)
- `image-alt` (no decorative images without alt; no content images in current layouts)
- `link-name` (CTA link has text)
- `valid-lang` (inherited from host)

## Known non-tested

- **Focus trap keyboard flow** - verified manually (Phase 1 smoke test 09)
- **ESC / backdrop dismiss** - verified manually
- **Screen reader announcements** - not tested automatically; manual VoiceOver pass recommended
- **prefers-reduced-motion** - verified via CSS `@media` inspection (Phase 1 smoke test 16)
- **prefers-color-scheme: dark** - verified via CSS `@media` inspection (Phase 1 smoke test 15)

## Manual supplementary checks

- **Dialog vs region roles**: announcement/newsletter/discount use `role="dialog"` with `aria-modal="true"` (correct for modal overlays). Bottom bar uses `role="region"` (correct - it's not modal). ✓
- **aria-labelledby**: each dialog popup's `aria-labelledby` attribute points at the headline's `id` which is unique per popup instance (`smart-popup-{popup.id}-headline`). ✓
- **Dismiss button**: every layout has `aria-label="Dismiss"` (translated to DE/FR via the `social-proof` domain). ✓
- **Form inputs**: newsletter layout binds `<label for="...">` explicitly to the email input id. ✓

## Conclusion

The popup subsystem passes automated WCAG 2.0 A/AA rules across all shipping layouts. No remediation needed for v1.1 Plugin Store submission.

For v1.2+, consider adding:
- Automated keyboard-flow tests (Playwright + Tab navigation assertions)
- Screen-reader-announcement tests (requires tooling beyond axe-core)
- Mobile touch-target sizing audit (minimum 44×44 hit areas)
