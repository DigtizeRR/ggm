# Workshop Booking Card Combined Summary and Form Text Section Plan

## Approval and implementation status

Approved and implemented on September 12, 2026. The combined booking summary, Form Text editor/shortcode, responsive styling, and regression contract test described below are now present in the working tree.

## Shortcode identification

The section in the supplied screenshots is rendered by:

`[ggm_workshop_booking_card]`

Registration and rendering are both in `public/class-ggm-shortcodes.php`:

- `GGM_Shortcodes::init()` registers `ggm_workshop_booking_card`.
- `GGM_Shortcodes::sc_workshop_booking_card()` builds the date range, workshop detail line, current/regular prices, savings, CTA, and optional testimonial.

The separate `[ggm_workshop_booking_card_hero_header]` shortcode is not the source of the date/price panels and will not be redesigned.

## Requested outcome

Replace the current separate date box and price box with one white rounded summary card matching the supplied reference:

1. Calendar icon and date information on the left.
2. A green money icon as the visual transition/divider in the middle.
3. Current price, struck-through regular price, and a green savings pill on the right.
4. One continuous white background and one outer border radius around the complete date/price summary.
5. The CTA remains a separate blue/primary button below the summary card.

Also add a new workshop content section named **Form Text**:

1. The Workshop edit page provides a Heading field.
2. It provides a full WordPress rich-text editor below the heading field.
3. A new `[ggm_workshop_form_text]` shortcode renders the saved heading followed by the formatted rich-text content.
4. Administrators can place that shortcode immediately above a form, or anywhere else in the workshop’s Elementor layout.
5. The rendered section follows the supplied reference: a strong left-aligned heading with formatted supporting copy below it.

## Audit findings

### 1. Root cause of the current two-box layout

The shortcode currently prints two sibling elements directly inside the booking-card grid:

- `.ggm-workshop-booking-card__schedule`
- `.ggm-workshop-booking-card__price`

Both selectors independently receive padding, background, and border radius. The parent `.ggm-workshop-booking-card` also applies a `10px` grid gap. This combination intentionally produces two visibly separate panels; CSS alone cannot create the reference’s semantic middle divider and money-icon placement cleanly without introducing a shared wrapper.

### 2. Current date source and formatting

The date uses:

- `workshop_start_date`, with legacy `workshop_date` fallback;
- `workshop_end_date`;
- the approved compact formatter, which already produces ranges such as `Sept 24–30, 2026` and avoids repeating the month/year.

The secondary schedule line currently joins a derived duration and Workshop Mode with ` | `. That is why the current screenshot can show text such as `Live | Live Online Workshop`.

The reference shows one concise secondary line (`Live on Zoom`). The new layout should display the configured Workshop Mode as the primary secondary line, fall back to duration when the mode is empty, and avoid joining two overlapping labels. It must not hardcode “Live on Zoom”; the administrator’s workshop value remains authoritative.

### 3. Current price source is payment-aware

The visual price data is derived from the same authoritative `GGM_Workshop` and `GGM_Currency` methods used by checkout:

- regular price;
- valid sale price;
- contribution ranges;
- free workshops;
- converted display currency;
- calculated savings.

The redesign must change presentation only. It must not recalculate the payable amount independently or alter checkout/payment values.

### 4. Current sale-price layout differs from the reference

The current sale display is split into two equal halves:

- left half: “Special Price” above the sale price;
- right half: regular price above plain savings text;
- a vertical border between those halves.

The reference instead places the sale price and struck-through regular price on the same top line, with the savings amount in a green rounded pill below. The visible “Special Price” label is absent.

### 5. Icon gap

The existing calendar is a small fixed inline SVG using `currentColor`. There was no money icon in the plugin’s shared icon map and no structural element between schedule and price.

The reference needs:

- a larger green calendar SVG;
- a fixed inline green money SVG;
- a vertical separator between the money icon and price block.

Both icons should be inline SVG code, decorative with `aria-hidden="true"`, and must not depend on Font Awesome, an external image, or a network request.

### 6. Responsive risk

The reference is wide enough for a horizontal summary. Forcing the same four-column arrangement into a narrow Elementor/mobile container would compress the date and price or cause overflow. The combined card must remain one white box while adapting internally on small screens.

### 7. Existing uncommitted work must be preserved

The working tree currently contains the approved shared left-aligned shortcode-heading work plus an earlier user change to mentor CSS. The implementation will preserve both and modify only the booking-card, Form Text, and version files described below.

### 8. Workshop editor already supports WordPress rich-text fields

`includes/class-ggm-meta-boxes.php` owns the Workshop Details meta box and already initializes `wp_editor()` for the Booking Card Hero. The mentor editor also uses the standard expanded Visual/Text editor pattern. Therefore, Form Text can use WordPress’s native editor without introducing another editor library or front-end dependency.

The existing Workshop save path already provides:

- a meta-box nonce;
- autosave protection;
- `edit_post` capability validation;
- a workshop-specific save callback.

The two new values should be added to this protected path rather than saved through a separate endpoint.

### 9. Rich Form Text needs a broader content contract than short labels

The plugin’s short-description and Booking Card Hero fields intentionally allow only a narrow subset of inline HTML. That allowlist is too restrictive for the requested full rich-text editor because it would remove legitimate paragraphs, headings, lists, links, quotes, and other normal WordPress post content.

Form Text content should instead be sanitized with `wp_kses_post()`. The standalone heading remains plain text and should use `sanitize_text_field()`.

### 10. No existing Form Text shortcode or metadata exists

There is currently no registered `ggm_workshop_form_text` shortcode and no Form Text heading/content meta key. A new shortcode handler is required; reusing the Booking Card Hero shortcode would incorrectly couple two independently placeable sections.

Workshop package export already includes all workshop post metadata and scans metadata content for local media URLs. Consequently, the new text fields will be included automatically; no dedicated package schema or database-table migration is required.

## Proposed implementation

### Phase A: introduce one semantic summary wrapper

In `GGM_Shortcodes::sc_workshop_booking_card()`:

1. Wrap schedule and price output in one `.ggm-workshop-booking-card__summary` element whenever at least one of them has content.
2. Keep the schedule as a left-side child containing:
   - calendar icon;
   - compact date H/data line;
   - one concise workshop mode/detail line.
3. Add a middle transition area containing the fixed inline money SVG plus a separate decorative separator element.
4. Move the price output into the right-side child of the same summary wrapper.
5. Keep the CTA and testimonial outside the summary wrapper so their current vertical flow remains intact.

Proposed desktop structure:

```text
One white rounded summary card
┌──────────────────────────────────────────────────────────────┐
│ [calendar]  Sept 24–30, 2026   [money] │ ₹1,400  ₹7,000 │
│             Live on Zoom                    │ You Save ₹5,600 │
└──────────────────────────────────────────────────────────────┘
```

### Phase B: match the reference hierarchy

Use a desktop grid with four internal tracks:

1. Flexible schedule column.
2. Fixed icon column for the money symbol.
3. One-pixel vertical separator.
4. Flexible price column.

The summary card will use:

- a single solid white background;
- approximately `18px` outer corner radius;
- balanced horizontal/vertical padding matching the reference proportions;
- no separate backgrounds or radii on schedule/price children;
- green icon colour inherited by both inline SVGs;
- vertically centered content.

The final values will be scoped to `.ggm-workshop-booking-card` so no other calendar, price, or SVG components change.

### Phase C: restructure sale-price presentation

For a valid sale:

1. Place current sale price and regular struck-through price on the same line.
2. Give the sale price the strongest weight/size.
3. Keep the regular price smaller, muted, and struck through.
4. Render `You Save {amount}` as a compact green-tinted pill below.
5. Remove the visible “Special Price” label from this booking-card presentation.

Fallback states:

| Price state | Summary-card output |
| --- | --- |
| Valid sale | Sale price + struck regular price; savings pill below |
| Regular price only | One emphasized current price; no empty regular-price slot or savings pill |
| Free | Existing translated Free badge/value; no empty sale metadata |
| Contribution | Existing translated contribution/range output; no false struck price or savings pill |
| Currency conversion enabled | All displayed current, regular, and saving amounts use the same selected converted currency |

Payment creation and the value charged by Razorpay remain unchanged.

### Phase D: simplify the schedule detail line

1. Keep the existing compact date formatter unchanged.
2. Show Workshop Mode alone when configured.
3. Use the existing duration-derived label only as a fallback when mode is empty.
4. Suppress an empty second line rather than printing separators or placeholder text.
5. Preserve the explicit shortcode `date_format` override.

### Phase E: add the money icon as trusted inline SVG

1. Extend the existing `icon_svg()` map with a `money` entry.
2. Use an original/simple fixed path suitable for `currentColor` styling.
3. Render it only inside the combined booking summary.
4. Keep both icons decorative so the date and price text remain the accessible content.

### Phase F: responsive behavior without splitting the card

At wider widths, retain the reference’s horizontal layout.

At narrow/mobile widths:

1. Keep one white rounded outer summary card.
2. Stack the schedule and price into two internal rows rather than creating two external boxes.
3. Convert the desktop vertical separator into a horizontal internal separator.
4. Position the money icon with the price row so it remains meaningful without consuming a narrow standalone column.
5. Allow long date, mode, converted-price, and savings strings to wrap inside the card.
6. Do not introduce fixed heights; the summary height must grow naturally with its content.

The existing CTA width, link target, hero items, testimonial, and surrounding Elementor container remain unchanged.

### Phase G: add the Form Text controls to Workshop Details

In `GGM_Meta_Boxes::render_workshop_details_mb()`:

1. Add a visually separate **Form Text** section near the existing workshop page-content controls.
2. Add a plain Heading input backed by `ggm_workshop_form_text_heading`.
3. Add a full WordPress rich-text editor backed by `ggm_workshop_form_text_content` with:
   - Visual and Text tabs;
   - standard format, bold, italic, list, alignment, blockquote, link, undo, and redo controls;
   - normal paragraph/block markup support;
   - the WordPress media button so an administrator can insert supported Media Library content when needed.
4. Show the exact shortcode `[ggm_workshop_form_text]` in the field description so the administrator knows how to place it in Elementor.
5. Load existing metadata into both fields on edit so content is not lost between saves.

In `GGM_Meta_Boxes::save_workshop_fields()`:

1. Reuse the existing nonce, autosave, and capability protections.
2. Save the heading with `sanitize_text_field()`.
3. Save rich content with `wp_kses_post()`.
4. Delete each metadata key when its submitted value is empty, preventing stale/blank records.
5. Do not run `sanitize_text_field()` over the editor content because that would flatten formatting.

### Phase H: register and render the Form Text shortcode

In `GGM_Shortcodes`:

1. Register `[ggm_workshop_form_text]` in the existing workshop shortcode group.
2. Resolve the workshop with the same `id` attribute/current-workshop logic used by the other workshop content shortcodes.
3. Return an empty string when both heading and rich text are blank.
4. Render a semantic section:
   - `.ggm-ws-form-text` as the section wrapper;
   - an H2 using the newly standardized `ggm-ws-section-heading` class when a heading exists;
   - `.ggm-ws-form-text__content` for the rich body.
5. Preserve safe WordPress post formatting on the frontend with `wp_kses_post()` and paragraph formatting, including headings, paragraphs, lists, emphasis, links, quotes, and Media Library images.
6. Render the body even when the heading is blank, and render the heading even when the body is blank.
7. Do not execute arbitrary PHP or unfiltered HTML from the editor.

The shortcode contract will be:

`[ggm_workshop_form_text id=""]`

The optional `id` keeps it usable in Elementor previews and explicit workshop contexts. With no `id`, it resolves the current workshop like the other workshop shortcodes.

### Phase I: style Form Text as an Elementor-friendly section

1. Keep the section background transparent so it inherits the Elementor/container design shown in the reference.
2. Use the common `ggm-ws-section-heading` typography and left alignment rather than creating another main-heading class.
3. Give the rich body readable left-aligned spacing and line height while allowing Elementor/theme colours to inherit.
4. Normalize first/last child margins so the section does not create unexplained top or bottom space.
5. Make embedded images responsive with `max-width: 100%` and `height: auto`.
6. Avoid fixed heights, absolute positioning, and width assumptions so the text grows naturally on mobile.

## Files expected to change after approval

- `public/class-ggm-shortcodes.php` — combined summary markup, concise detail fallback, sale hierarchy, and inline money SVG.
- `assets/css/ggm-public.css` — combined desktop card, reference styling, and responsive one-card layout.
- `includes/class-ggm-meta-boxes.php` — Form Text administrator fields, full rich-text editor, and secure persistence.
- `ggm-member-dashboard.php` — one version bump covering both approved features for cache/deployment identification.

The plan file will be updated to mark implementation/testing status.

## Verification plan

### Static and syntax checks

1. Run `php -l` on the changed PHP files.
2. Run `git diff --check`.
3. Confirm the new summary wrapper contains schedule, divider/money icon, and price, while CTA remains outside.
4. Confirm only one white background and one border-radius owner exist for the combined summary.
5. Confirm no external icon URL or image file is introduced.
6. Confirm the pre-existing heading and mentor CSS changes are preserved.
7. Confirm `ggm_workshop_form_text` is registered exactly once and has one authoritative renderer.
8. Confirm Form Text heading/content have one protected writer each and no plain-text sanitizer is applied to rich content.
9. Confirm no duplicate editor IDs or HTML IDs are introduced on the Workshop edit page.

### Price-state tests

1. Sale price `₹1,400`, regular price `₹7,000` -> same price row plus `You Save ₹5,600` pill.
2. Regular price only -> one current price, no empty metadata or divider inside the price column.
3. Free workshop -> clean Free output.
4. Contribution workshop with one and multiple options -> existing contribution label/range remains correct.
5. Multi-currency enabled -> current, regular, and savings amounts all use the same selected currency.
6. Multi-currency disabled -> configured workshop/global currency symbol remains correct.
7. Confirm the server-side payable amount is unchanged for every case.

### Date-state tests

1. Same-month range remains compact (`Sept 24–30, 2026`).
2. Cross-month and cross-year compact ranges remain correct.
3. Single/identical dates render once.
4. Legacy `workshop_date` fallback still works.
5. Explicit `date_format` remains honored.
6. Mode appears as the single secondary line; duration appears only when mode is empty.
7. Empty date/detail values do not leave blank visual columns.

### Responsive visual tests

Check at:

- approximately 840px component width to match the supplied reference;
- 50% Elementor desktop container widths;
- 768px boundary;
- 430px, 390px, 360px, and 320px mobile widths.

Verify:

1. Desktop has one white rounded card with schedule left, money icon/divider center, and price right.
2. Calendar and money icons are green and optically aligned.
3. Sale/current price and struck regular price share one line where space permits.
4. Savings uses a green rounded pill below the price line.
5. Mobile remains one card, stacks internally, and has no clipping or horizontal overflow.
6. Long translated or converted amounts wrap without collision.
7. The CTA remains separate and unchanged below the summary.
8. No fixed/min-height creates extra bottom space.

### Form Text administrator tests

1. Confirm Workshop Details shows one **Form Text** panel with Heading and full Visual/Text editor controls.
2. Save a heading and formatted paragraphs, reload the editor, and confirm both values persist exactly within the allowed HTML contract.
3. Test bold, italic, links, ordered/unordered lists, blockquote, subheadings, line breaks, and a Media Library image.
4. Clear the heading and content independently and confirm only the cleared metadata key is deleted.
5. Clear both fields and confirm the shortcode produces no empty wrapper or spacing.
6. Submit unsafe script/event-handler markup in Text mode and confirm `wp_kses_post()` removes it.
7. Confirm autosave/revision or an unauthorized request cannot bypass the existing save guards.

### Form Text frontend tests

1. `[ggm_workshop_form_text]` renders the current workshop’s content.
2. `[ggm_workshop_form_text id="123"]` renders only workshop `123` when it is a valid workshop context.
3. The heading uses only `ggm-ws-section-heading` and is left-aligned at desktop and mobile sizes.
4. Rich formatting from the Visual editor appears equivalently on the frontend without escaped HTML tags.
5. Links remain functional, lists retain structure, and images stay within the container.
6. Heading-only and content-only configurations render cleanly.
7. Long text at 320px–430px wraps naturally with no horizontal overflow or fixed extra height.
8. Multiple workshop pages do not leak one workshop’s Form Text into another.
9. Workshop package export/import preserves the heading, rich content, and referenced local media through the existing generic metadata/media handling.

## Acceptance criteria

- The supplied date/price area is confirmed and documented as `[ggm_workshop_booking_card]`.
- Date and price render inside one continuous white rounded box.
- Desktop composition matches the reference hierarchy: calendar/date, money icon/divider, price/regular price, savings pill.
- The compact dynamic date and payment-authoritative pricing remain intact.
- Mobile uses one auto-height card with internal stacking and no overflow.
- CTA, hero, testimonial, payment behavior, unrelated shortcodes, approved heading work, and mentor customization remain unchanged.
- Workshop Details contains a dedicated Form Text heading field and full WordPress rich-text editor.
- `[ggm_workshop_form_text]` renders the saved workshop heading and safe formatted body independently.
- The Form Text heading reuses `ggm-ws-section-heading`; no additional main-heading class or duplicate typography rule is introduced.
- Form Text is auto-height, responsive, safely sanitized, and empty when no content is configured.
- Syntax, state, responsive, accessibility, and whitespace checks pass.

## Implementation result

- `[ggm_workshop_booking_card]` now renders its schedule and price inside one white rounded summary surface.
- The schedule uses the compact date range plus one detail line: Workshop Mode first, with the duration-derived day label only as a fallback.
- A trusted inline `currentColor` money SVG and internal separator connect the schedule and price areas.
- Sale, regular, free, contribution, and converted-currency displays continue to use `GGM_Workshop`/`GGM_Currency`; no checkout amount logic was changed.
- `[ggm_workshop_form_text]` is registered and renders the optional saved heading and safe rich content.
- `[ggm_workshop_booking_summary]` provides the compact date-and-price section as a separately placeable shortcode without changing or duplicating the existing booking card’s CTA, hero, or testimonial output.
- The date/price fragment inside `[ggm_workshop_booking_card]` now uses that exact same compact component markup and CSS. Both outputs remain one horizontal row at phone and desktop widths; only the surrounding booking-card content differs.
- The shared grid uses equal flexible halves with the divider in the exact centre column; the money icon belongs to the price half and therefore renders immediately after the divider in both shortcodes.
- Workshop Details now contains a Form Text heading field and the full native WordPress Visual/Text editor with Media Library support.
- The shared `ggm-ws-section-heading` class is reused; the Form Text feature does not introduce another main-heading typography class.
- Both shortcodes use the same `ggm-workshop-booking-summary` CSS class system. Component-relative typography and spacing keep the complete summary in one horizontal row at desktop and phone widths, with no summary-specific breakpoint or duplicate booking-card stylesheet. The combined card itself has no fixed or minimum height.
- Plugin/cache version was advanced to `1.2.5`.
- Added `scripts/test-workshop-booking-card-form-text.php`, covering compact/single/cross-month dates, detail fallback, sale/regular/free/contribution/converted price states, one-summary markup, inline money-icon presence, Form Text formatting/security/empty states, and required CSS/admin-editor contracts.
