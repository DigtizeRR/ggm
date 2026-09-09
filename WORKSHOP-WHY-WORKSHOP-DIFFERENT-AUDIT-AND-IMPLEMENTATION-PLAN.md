# Why {Workshop Name} Is Different Shortcode — Audit and Implementation Plan

## Scope

Create `[ggm_workshop_why_workshop_is_different]`, an independent two-column Workshop section:

- Left: dynamic heading, introductory text, and tick-mark points.
- Right: one Workshop-configured image.
- Background: a primary-to-secondary gradient using website colour tokens.

The reference is used for structure only. Its maroon palette is not copied.

## Audit findings

1. `[ggm_workshop_why_different]` already exists, but it is a three-card repeater grid. Altering it would break current pages, so this feature needs its own shortcode and data fields.
2. `GGM_Shortcodes::init()` is the shortcode registration point.
3. The Workshop editor uses post meta, supports media-upload controls, and saves values in `save_workshop_meta()`.
4. Existing public styles load from `assets/css/ggm-public.css`; CSS custom-property fallbacks can inherit Elementor/WordPress website colour tokens without copying the reference colours.

## Data design

- `ggm_workshop_why_workshop_different_intro`: optional multiline left-column text.
- `ggm_workshop_why_workshop_different_heading`: optional editable section heading; blank uses `Why {Workshop Name} is Different`.
- `ggm_workshop_why_workshop_different_points`: optional add/remove point list.
- `ggm_workshop_why_workshop_different_image`: optional right-column image URL.

The section background always follows the website primary/secondary CSS tokens. Blank intro, points, or image elements are simply omitted. The shortcode returns no section at all if no meaningful content has been configured.

## Implementation plan

1. Add the new shortcode registration.
2. Add a clearly labelled Workshop editor block with editable heading, intro, add/remove tick points, and image upload.
3. Sanitize text and image URLs on save; remove blank values.
4. Render a semantic two-column section with the saved heading (or dynamic default) and an inline SVG tick per point.
5. Add responsive CSS using layout-only styling plus website theme colour variables; do not hard-code the reference palette.
6. Lint PHP and verify shortcode registration, every saved field, blank-content protection, and the website-colour path.
