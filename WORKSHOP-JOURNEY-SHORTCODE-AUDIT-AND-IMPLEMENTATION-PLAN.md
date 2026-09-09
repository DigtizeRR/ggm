# Workshop Journey Shortcode — Audit and Implementation Plan

## Scope

Create `[ggm_workshop_journey]`, a Workshop-configured journey section with:

- An optional editable heading.
- Unlimited linked day cards, each with day label, short description, and optional image/icon.
- Seven cards visible per desktop slide.
- Previous/next arrows only when more than seven day cards exist.
- Up to four configurable footer boxes, each with optional image/icon and text.

The supplied reference establishes the sequence and connected-card layout only; no reference colours are copied.

## Audit findings

1. Existing Workshop repeaters use JSON post meta, reusable media controls, generic sanitization, and a single add/remove-row script.
2. The script can be extended with one row template for the new day-card repeater.
3. Public CSS is conditionally enqueued by a shortcode allowlist in `GGM_Public`; the new shortcode must be added there.
4. An inline, instance-scoped script is appropriate for the slider because it has no external dependency and must work in Elementor content.

## Data design

- `ggm_workshop_journey_heading`: optional heading.
- `ggm_workshop_journey_days`: JSON repeater rows: `day`, `description`, `image`.
- `ggm_workshop_journey_footer_items`: a maximum of four rows: `icon`, `text`.

## Implementation plan

1. Register the shortcode and add it to public-asset detection.
2. Add Workshop editor controls for the heading, dynamic day repeater, and exactly four footer-box slots.
3. Save/sanitize the heading and footer rows; include day rows in the existing repeater save flow.
4. Render linked card pages in groups of seven. Render slider arrows only for multiple pages.
5. Use accessible buttons and scoped JavaScript for previous/next navigation. Keep a responsive layout for narrow screens.
6. Add structure-only CSS using inherited/theme colour tokens and no copied reference palette.
7. Lint PHP and test the seven-item static path and eight-item arrow/slider path.
