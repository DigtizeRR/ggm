# Workshop Booking Card Shortcode — Audit and Implementation Plan

## Requested outcome

Provide one shortcode that presents a compact workshop booking card in this order:

1. Workshop date range
2. Workshop timing
3. Workshop mode
4. Price (including regular/sale display)
5. A workshop-configured call-to-action button

The visual hierarchy follows the supplied reference: a clear schedule panel,
a separate price panel, and a full-width final CTA. Icons and CTA wording must
be editable when the Workshop is created or edited. The component must follow
the active site theme rather than hard-code a brand palette.

## Audit findings

- Workshop date range is already stored in `workshop_start_date` and
  `workshop_end_date`, with `workshop_date` available as a legacy fallback.
- Active timings come from `GGM_Workshop_Slot`, which already formats ranges
  in the site's timezone and configured time format.
- Workshop mode is stored in `workshop_mode`.
- `GGM_Workshop::price_html()` is the authoritative price renderer: it handles
  regular price, valid sale price, free workshops, contribution pricing, and
  selected multi-currency display.
- `[ggm_workshop_enroll]` is the authoritative access-aware CTA. It directs an
  enrolled member to content, registers a free workshop safely, and sends a
  paid workshop to checkout. Its labels were fixed and therefore could not
  honor workshop-specific CTA copy.
- Existing detail and icon-list shortcodes use fixed SVGs. They cannot meet the
  requested per-workshop icon configuration without new saved metadata.

## Design decisions

- New shortcode: `[ggm_workshop_booking_card]`, with optional `id`, `class`,
  and `date_format` attributes. Without `id`, it resolves the current Workshop,
  so it can be placed directly in an Elementor Shortcode widget in a Single
  Workshop template.
- Four optional image icons are saved per workshop: date, time, mode, and
  price. A blank icon deliberately renders no substitute; this avoids hidden
  hard-coded iconography.
- The CTA text is saved per workshop. It customizes only the pre-enrolment CTA;
  an enrolled visitor still sees the accurate `Start Watching` action.
- The revised visual treatment keeps the schedule and price panels explicitly
  white, matching the supplied reference. Only the CTA background uses the
  Elementor global primary color first and the WordPress global primary preset
  second; when neither is available, it remains white.
- The existing price and enrollment implementations are reused, preventing
  divergence in sale, currency, contribution, access, and checkout behavior.

## Implementation steps

1. Add Workshop Details controls for card CTA text and the four media-library
   icon uploads, then validate and save their post meta.
2. Allow `[ggm_workshop_enroll]` to receive workshop-specific button copy for
   an un-enrolled visitor while retaining access-aware labels and links.
3. Register and render `[ggm_workshop_booking_card]` using the existing date,
   slot, mode, price, and enrollment services.
4. Add responsive component CSS based solely on site theme color variables.
5. Document the shortcode in Workshop Shortcodes settings and lint all changed
   PHP files.

## Acceptance checks

| Workshop state | Expected result |
| --- | --- |
| Date range, one slot, mode, sale price | Date range; one formatted time; mode; struck-through regular price and sale price; configured CTA |
| Multiple active slots | All formatted slot ranges remain visible in the timing line |
| Free workshop | Existing safe free-registration behavior remains available from the card CTA |
| Contribution pricing | Existing contribution price representation is used; no fabricated fixed price |
| Enrolled member | CTA remains `Start Watching` and points at available lesson content |
| Blank custom icon | The relevant row has no icon rather than a hard-coded replacement |
| Elementor/WordPress global colors | Card surfaces, text, and CTA inherit those theme tokens |
