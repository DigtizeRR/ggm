# Workshop Booking Card Date Line — Audit and Implementation Plan

## Scope

Refine the schedule area of `[ggm_workshop_booking_card]` to match this structure:

1. Calendar icon plus a long date range, for example `September 24, 2026 – September 30, 2026`.
2. A separate lower line containing only the duration's numeric day portion, followed by the Workshop Mode when available, for example `7 Days | Live Online Workshop`.

## Audit findings

1. The booking card reads the Workshop start/end dates correctly but defaults to WordPress's global date setting, so its format can differ between sites.
2. The lower line directly combines the complete `duration` and `workshop_mode` values.
3. A full duration value such as `7 Days Live Online Workshop` therefore duplicates the Mode when Mode is also `Live Online Workshop`.
4. The schedule CSS already renders the intended icon + two-line structure, so no colour or layout restyle is necessary.

## Implementation plan

1. Change the booking-card default date format to `F j, Y` while keeping its explicit `date_format` shortcode attribute as an override.
2. Extract a leading/embedded numeric day phrase such as `7 Days` or `21-Day` from the Duration field for this card only.
3. Combine that reduced duration and the Mode into the second line, omitting either blank component.
4. Preserve a non-day duration value when no numeric day phrase can be found.
5. Lint PHP and test range formatting plus the full-duration/no-duplicate case.
