# Workshop Booking Card Compact Date Range Plan

## Request and Interpretation

Update the date row shown by `[ggm_workshop_booking_card]` so that:

- A month name uses its first four characters (`September` becomes `Sept`).
- When the start and end dates are in the same month and year, the month and year are printed only once.

This plan interprets "first 4 digit" as the first four characters of the localized full month name. The change is limited to the booking card shown in the supplied screenshot.

## Audit Findings

### Current output path

The date label is assembled inside `GGM_Shortcodes::sc_workshop_booking_card()` in `public/class-ggm-shortcodes.php`.

The shortcode reads:

- `workshop_start_date`, falling back to the legacy `workshop_date` key.
- `workshop_end_date` for the range end.

### Root cause of the repeated month

The current default format is `F j, Y`. The code formats the start and end dates independently and joins the two complete strings with a dash. It does not compare their calendar month/year before building the label.

Current same-month result:

`September 24, 2026 - September 30, 2026`

Requested result:

`Sept 24-30, 2026`

### Existing compatibility requirement

The shortcode publicly accepts `date_format="..."`. That is an intentional formatting override and may already be used by existing pages. The new compact logic should become the default while an explicitly supplied `date_format` continues to produce the administrator-requested custom format.

### Date parsing concern

The current path uses `strtotime()` plus `date_i18n()`. Workshop values are date-only `Y-m-d` strings. A strict date-only parser using the WordPress site timezone is safer because it rejects malformed values and avoids relying on the server's timezone interpretation.

## Proposed Implementation

### 1. Add one reusable booking-card date-range formatter

Add a private helper to `GGM_Shortcodes` that:

1. Strictly parses `Y-m-d` start/end values in `wp_timezone()`.
2. Uses `wp_date()` for localized month names, day numbers, and years.
3. Shortens each localized full month name to its first four characters, using multibyte-safe truncation when available.
4. Compares the actual `Y-m` values rather than comparing translated labels.
5. Returns an empty string for unusable data instead of emitting PHP warnings.

### 2. Apply deterministic range rules

| Date case | Example output |
| --- | --- |
| Start date only | `Sept 24, 2026` |
| Same start and end date | `Sept 24, 2026` |
| Same month and year | `Sept 24-30, 2026` |
| Different months, same year | `Sept 30 - Octo 2, 2026` |
| Different years | `Dece 30, 2026 - Janu 2, 2027` |

The final implementation will use a typographic en dash in the rendered label; the ASCII hyphens above keep this plan file encoding-neutral.

### 3. Preserve the explicit shortcode override

- `[ggm_workshop_booking_card]` uses the new compact/smart default.
- `[ggm_workshop_booking_card date_format="..."]` keeps the existing explicit-format behavior.
- Even in the explicit-format path, identical start and end dates will still render once.

This avoids silently breaking pages that intentionally supplied a custom PHP/WordPress date format.

### 4. Keep the change isolated

No changes are planned for:

- Stored workshop dates or the workshop editor.
- Expiry/status calculations.
- `[ggm_workshop_start_date_detail]`.
- `[ggm_workshop_date]`.
- `[ggm_workshop_icon_list]`.
- Dashboard/API date data.
- Booking-card CSS, icons, pricing, payment, duration, or mode output.

## Files in Scope

- `public/class-ggm-shortcodes.php`

No CSS, JavaScript, database migration, or metadata rewrite is required.

## Verification Plan

Test the formatter and booking-card integration with these cases:

1. `2026-09-24` to `2026-09-30` -> `Sept 24-30, 2026`.
2. `2026-09-24` to `2026-09-24` -> one date only.
3. `2026-09-30` to `2026-10-02` -> both shortened month names, year once.
4. `2026-12-30` to `2027-01-02` -> both shortened month names and both years.
5. Start date with no end date -> one compact date.
6. Legacy `workshop_date` fallback still works.
7. Empty or malformed dates do not generate a broken label or warning.
8. Explicit `date_format` remains honored.
9. Duration and workshop mode remain unchanged below the date.
10. Run PHP syntax checks and `git diff --check` after implementation.

## Implementation Status

Approved and implemented in `public/class-ggm-shortcodes.php`. The change is scoped to the booking card's date label and retains the explicit `date_format` override.
