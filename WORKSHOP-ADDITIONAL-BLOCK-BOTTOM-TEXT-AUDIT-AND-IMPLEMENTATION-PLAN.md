# Workshop Additional Block Bottom Text — Audit and Implementation Plan

## Scope

Add an optional, workshop-specific trailing text area to these shortcodes:

- `[ggm_workshop_discover]`
- `[ggm_workshop_why_different]`
- `[ggm_workshop_perfect_for]`

The intended order is heading, configured items, then optional bottom text. The supplied reference informs that structure only; no reference colours are copied.

## Audit findings

1. Each shortcode already renders its heading before its configured items.
2. The Workshop editor has a dedicated repeater section for each shortcode and has no bottom-text setting.
3. No saved meta key or frontend markup currently exists for this text.
4. Each shortcode returns no output when it has no valid items. This behaviour remains unchanged, so a bottom text cannot create an otherwise empty section.

## Implementation plan

1. Add one clearly labelled optional textarea beneath each matching Workshop repeater.
2. Save the values as plain multiline workshop meta, deleting the meta when it is blank.
3. Read the matching value in each shortcode and output it immediately after that shortcode's items only when non-empty.
4. Add one neutral shared style: centred text, normal inherited colours, and responsive spacing. No hard-coded palette is introduced.
5. Verify PHP syntax and inspect the renderer conditions to confirm blank values emit no bottom-text markup.

## Meta keys

- `ggm_workshop_discover_bottom_text`
- `ggm_workshop_why_different_bottom_text`
- `ggm_workshop_perfect_for_bottom_text`
