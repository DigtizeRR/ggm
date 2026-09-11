# Workshop Shortcode Mobile Typography and Discover Grid Plan

## Request

Optimize mobile heading sizes for these shortcodes without changing their desktop presentation:

- `[ggm_workshop_booking_card_hero_header]`
- `[ggm_workshop_why_different]`
- `[ggm_workshop_journey]`
- `[ggm_workshop_why_workshop_is_different]`
- `[ggm_workshop_discover]`

On mobile, `[ggm_workshop_discover]` must use a two-column layout so four items form a 2 x 2 block. If more than four items exist, they will continue in additional two-column rows; this plan does not add a carousel or hide content.

## Audit Findings

### 1. Booking-card hero header has no mobile typography rule

The standalone header uses `.ggm-workshop-booking-card__hero-header` with `font-size: 2.6em`. Because this is relative to the surrounding Elementor/theme font size and has no mobile override, the rendered size can be disproportionately large and varies by placement.

### 2. Discover and Why Different share a fixed 32px section heading

Both shortcodes render an H2 with `.ggm-ws-block__heading`. Its current `32px` size applies at every viewport width. A generic change to that selector would also affect unrelated Additional Block shortcodes, so mobile rules must include the specific Discover and Why Different wrapper classes.

### 3. Discover's mobile columns are currently ineffective

The base `.ggm-ws-discover` rule sets `display: flex`, while the existing responsive rule only sets `grid-template-columns: repeat(2, 1fr)`. Grid columns do nothing on a flex container. The component also has no wrapping rule, so a dependable 2 x 2 arrangement is not currently guaranteed.

### 4. Admin-selected Discover columns can override responsive behavior

When a custom column count is saved, `.ggm-ws-layout--custom` applies `display:grid !important` and a variable desktop column count. This rule appears after the current responsive rules and uses `!important`, so it can keep three or more columns on a phone. The new mobile selector must be scoped and more specific so mobile always resolves to two columns while the saved desktop setting remains intact.

### 5. Why Different increases card-title size on small phones

Its desktop card title is `16px`, but the current `max-width:480px` rule increases it to `18px`. That works against the requested mobile optimization and makes two-column cards more likely to wrap excessively.

### 6. Journey and Why Workshop Is Different lack explicit compact mobile headings

`.ggm-ws-journey__heading` inherits its size from the active theme, making it inconsistent between pages. `.ggm-ws-why-workshop-different__heading` uses `clamp(26px, 3vw, 42px)`, whose 26px minimum prevents it becoming more compact on narrow phones.

## Proposed Implementation

All changes will be added to `assets/css/ggm-public.css` inside `@media (max-width: 767px)`. Existing desktop declarations and shortcode PHP output will remain unchanged.

### Mobile heading scale

| Shortcode/content | Selector | Proposed mobile size |
| --- | --- | --- |
| Booking-card hero header | `.ggm-workshop-booking-card__hero-header` and its rich-text paragraph | `clamp(28px, 8vw, 34px)`, line-height `1.15` |
| Discover section heading | `.ggm-ws-block--discover .ggm-ws-block__heading` | `clamp(24px, 7vw, 28px)`, line-height `1.2` |
| Why Different section heading | `.ggm-ws-block--why-different .ggm-ws-block__heading` | `clamp(24px, 7vw, 28px)`, line-height `1.2` |
| Journey section heading | `.ggm-ws-journey__heading` | `clamp(24px, 7vw, 28px)`, line-height `1.2` |
| Why Workshop Is Different heading | `.ggm-ws-why-workshop-different__heading` | `clamp(24px, 7vw, 28px)`, line-height `1.2` |
| Discover item heading | `.ggm-ws-block--discover .ggm-ws-discover__heading` | `16px`, line-height `1.3` |
| Why Different card title | `.ggm-ws-block--why-different .ggm-ws-why-different__title` | `16px`, line-height `1.35` |
| Journey day/card title | `.ggm-ws-journey__card strong` | `16px`, line-height `1.3` |

Body descriptions, list text, buttons, images, colours, and desktop typography will not be changed.

### Discover 2 x 2 mobile grid

At `767px` and below:

- Force the Discover list to `display:grid`.
- Force `grid-template-columns: repeat(2, minmax(0, 1fr))`.
- Use mobile-safe row and column gaps.
- Set each item and its text wrapper to `min-width:0` so long headings wrap inside their own column rather than widening the page.
- Override `.ggm-ws-layout--custom` only within the Discover mobile scope so saved desktop column settings continue working above `767px`.
- Retain the current stacked icon-above-text presentation inside each grid cell.

## Files in Scope

- `assets/css/ggm-public.css` -- mobile-only responsive rules.

No PHP, JavaScript, admin settings, stored workshop data, or unrelated shortcode styles need to change.

## Verification Plan

### Viewports

- 320 x 568
- 360 x 800
- 390 x 844
- 430 x 932
- 767px boundary
- 768px boundary
- Desktop check at 1024px and 1440px

### Checks

1. Every listed heading fits without clipping, horizontal scrolling, or one-character columns.
2. Long headings wrap naturally and retain their intended weight and alignment.
3. Discover shows two equal columns on all tested mobile widths; four items form two rows.
4. Discover also stays two columns on mobile when an admin custom desktop column count is configured.
5. Five or more Discover items continue into paired rows without being hidden.
6. Why Different two-column cards no longer enlarge their title text at narrow widths.
7. Journey card titles remain readable in its existing responsive grid.
8. At 768px and above, computed font sizes and layouts match the current desktop implementation.
9. Run CSS/static selector checks and `git diff --check` after implementation.

## Implementation Status

Approved and implemented in `assets/css/ggm-public.css`. The production change is confined to a final `@media (max-width: 767px)` block; no desktop rules or shortcode markup were changed.

### Follow-up typography specification

The mobile main-heading rules were subsequently standardized across the plugin's shortcode section headings to the supplied computed style: `Space Grotesk`, normal style, `600` weight, `25px` size, and `28px` line height. Item/card headings remain independently sized, and desktop typography remains unchanged.
