# Form Price-Choice Flow Alignment Audit and Plan

## Requested outcome

Price choices must use the same placement grid as normal form fields. When the preceding field leaves available space on its row, a price-choice card with a compatible width should occupy that space. For example, after a 50% Email field, a 50% price-choice card should begin in the remaining right half of that same row.

## Audit findings

### Current form-field layout

The public form page is a ten-column CSS grid with a 16px column gap:

- A 100% field spans all ten columns.
- A 50% field spans five columns.
- A 30% field spans three columns.
- Grid items naturally fill remaining columns in their row before moving to the next row.

This produces the desired Full Name / Phone Number pairing and leaves the right half of the Email row available when Email is configured as 50%.

### Current price-choice layout

The price choices are inside a separate nested grid. The recent single-column requirement deliberately anchors every 50% and 30% price card to column one of that nested grid. Consequently, every card begins on a new row and cannot occupy the available right half beside Email.

The screenshot matches that implementation: Email consumes the first five columns, then the complete price-choice group begins on a new full-width grid row.

### Root cause

The payment group is currently treated as one form-grid item, rather than allowing the individual price cards to participate in the parent form page grid. The forced one-column placement is incompatible with the new requirement to fill unused field-grid space.

## Recommended design

Use the public form's parent ten-column grid as the single placement system for both fields and price-choice cards.

1. Render each multi-price card as a direct grid item immediately after the regular fields.
2. Apply the saved 100%, 50%, or 30% width class directly to that card.
3. Allow normal CSS-grid placement to fill any available columns before starting a new row.
4. Keep the radio inputs and server-side option-ID validation unchanged.
5. Keep the selection-group label accessible without reserving a visible full-width row before the first card. The label can be visually associated with the group without preventing a 50% card from occupying the remaining half of Email's row.
6. Retain the single-price path: it remains a non-radio card, and its saved width participates in the same parent grid.
7. On narrow screens, retain the existing mobile rule that expands cards to full width.

## Expected result for the screenshot

With Email at 50% and the first price card at 50%, the first price choice appears in the right half of Email's row. Subsequent cards continue through open grid slots according to their own saved widths. A 100% price card always starts a full row.

## Compatibility and validation plan

- Existing saved widths remain unchanged.
- Legacy fixed-price forms retain their 100% default card.
- Multi-price checkout continues to use only the server-approved selected option.
- Test 100%, 50%, and 30% price cards after 100%, 50%, and 30% normal fields.
- Test both payment flows, desktop and mobile behavior, one-price non-radio rendering, and multi-price required selection.
- Run PHP lint and whitespace validation.

## Implementation status

Revised after UI feedback. Width is now a single setting on the complete price-selection group, not on individual cards. The group participates in the parent form grid, and all prices remain in one vertical native-radio list within that group.
