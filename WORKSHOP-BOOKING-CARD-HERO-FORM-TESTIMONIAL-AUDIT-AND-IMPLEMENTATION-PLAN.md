# Workshop Booking Card: Hero, Full Form CTA, and Testimonial — Audit and Plan

## Requested outcome

Extend `[ggm_workshop_booking_card]` with:

1. A Workshop-configured Hero section containing at most four icon-and-text boxes.
2. The Workshop start/end date followed by the reference-style price layout.
3. A configurable CTA heading and supporting button text that scrolls to the
   current Workshop's separately placed full join form:
   `[ggm_workshop_join_form_full button_text="Submit Now"]`.
4. A Workshop-configured testimonial text and rating text beneath the CTA.
5. No filled background on any card section except the CTA, which uses the
   active site primary color when it exists.

## Audit findings

- The current booking card already has validated Workshop date, price, and
  per-workshop icon metadata, but its first section is schedule-oriented
  rather than the requested four-box Hero layout.
- It currently invokes `[ggm_workshop_enroll]`, which performs direct access,
  free-registration, or checkout behavior. That is incompatible with a CTA
  that must navigate to the full join form located elsewhere on the page.
- The full join form has a generated internal ID only. The card cannot safely
  link to it until the form exposes a stable, Workshop-specific default anchor.
- The Workshop editor already has a media-library image control and secure URL
  sanitizer suitable for fixed icon fields. A fixed four-row editor is safer
  and clearer than an unbounded repeater for this design.

## Implementation plan

1. Add fixed Hero controls to Workshop Details: exactly four icon-and-text
   entries. Save only indices 0–3 and render only configured entries.
2. Add Workshop controls for CTA heading, CTA supporting text, testimonial
   text, and rating text. Sanitize all as plain text.
3. Give `[ggm_workshop_join_form_full]` a default anchor ID of
   `ggm-workshop-join-form-full-{workshop ID}`, with an optional explicit
   `anchor_id` override.
4. Change the booking-card CTA into an anchor to that ID. The supplied full
   join-form shortcode remains responsible for validation, OTP, payment, and
   free/contribution logic.
5. Restructure the booking card as Hero → date range/detail → price → CTA →
   testimonial. Keep all non-CTA areas transparent and use only layout,
   borders, and typography. The CTA background reads Elementor global primary,
   then WordPress global primary; without either it has no fill.
6. Update the shortcode reference and run PHP syntax checks.

## Setup

Place both shortcodes in the same Single Workshop template, in this order:

```text
[ggm_workshop_booking_card]
[ggm_workshop_join_form_full button_text="Submit Now"]
```

The card button scrolls to the full form automatically for the current
Workshop. Do not manually assign an ID unless using a custom `anchor_id` on
the form.

## Acceptance matrix

| Case | Expected result |
| --- | --- |
| 0–4 configured Hero entries | Only configured entries display; a fifth cannot be added or saved |
| CTA click | Jumps to the matching Workshop full join form |
| Sale price | Current price left; regular price and savings right |
| No theme primary token | CTA has no fill; all other sections remain unfilled |
| Theme primary token exists | Only CTA receives that primary background |
| Testimonial/rating blank | The corresponding line is hidden without an empty shell |

