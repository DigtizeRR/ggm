# Workshop Additional Block Grid Layout — Audit and Implementation Plan

## Scope

Add per-Workshop column and row controls for:

- `[ggm_workshop_discover]`
- `[ggm_workshop_why_different]`
- `[ggm_workshop_perfect_for]`

## Audit findings

- Each shortcode loads its own Workshop repeater JSON, removes empty entries,
  and emits one item per remaining entry.
- Current layouts are hard-coded in public CSS: Discover is one vertical
  column; Why Different and Perfect For are three-column grids on desktop,
  with their existing responsive rules on narrower displays.
- There are no per-Workshop layout fields today. Editing a global CSS rule
  would change every Workshop and cannot restrict the rendered row count.
- Because the shortcodes already filter empty rows before rendering, limiting
  the filtered item list to `columns × rows` gives a predictable visible grid.

## Design

- Add one Workshop Details table with a Columns and Rows selector for each of
  the three sections.
- `Default (current layout)` is the untouched state. It preserves existing
  templates: Discover remains one-column; Why Different and Perfect For keep
  their current three-column desktop layout; all configured repeater entries
  remain visible.
- Explicit columns support 1–4. Explicit rows support 1–10. Values are
  integer-clamped server-side.
- When at least one setting is explicit, the shortcode limits its filtered
  items to the selected grid capacity and adds a scoped CSS custom property
  that drives only that Workshop instance's grid columns.
- Custom layout CSS is appended after legacy responsive rules and uses a
  component-specific class, ensuring a selected column count is not silently
  overwritten by older breakpoint rules.

## Verification cases

| Layout setting | Expected output |
| --- | --- |
| All Default | Existing layout and all configured entries unchanged |
| 2 columns, 2 rows | At most four valid entries, arranged in two columns |
| 1 column, 3 rows | At most three valid entries in one column |
| 4 columns, 1 row | At most four valid entries in one row/grid line |
| Fewer entries than capacity | All valid entries shown; no blank cards |
| Invalid saved value | Clamped or treated as Default; no unsafe CSS emitted |

