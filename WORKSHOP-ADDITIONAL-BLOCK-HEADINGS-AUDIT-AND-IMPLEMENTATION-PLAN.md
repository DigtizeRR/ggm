# Workshop Additional Block Headings — Audit and Implementation Plan

## Implementation status (version 1.1.6)

The original audit below recorded the earlier content-only shortcode requirement. That requirement has now been superseded: all four Webinar Additional Block shortcodes render their resolved H2 section heading before the repeater content. Resolution remains workshop override, then global setting, then translated built-in fallback. Empty repeaters still return no markup, so an orphan heading is never shown.

The Workshop heading editor was also corrected to keep only the explicit per-workshop override in its input; the inherited global/built-in heading is shown as a placeholder. This preserves inheritance when the global setting changes.

## Request and intended behavior

Keep **Settings → Webinar Additional Block Headings** as the place where an administrator edits the four site-wide defaults. Add an optional override for each heading on every workshop.

The intended resolution order is:

1. Use the workshop's non-empty custom heading.
2. Otherwise use the non-empty heading configured in global settings.
3. Otherwise use the plugin's translated built-in fallback.

This gives existing and newly created workshops the global default automatically, while allowing an administrator to customize any combination of the four headings on one workshop. Clearing a workshop override restores inheritance from settings.

## Current-state audit

### Global settings are already implemented correctly as defaults

The settings UI in `admin/views/partials/settings-workshop-shortcodes.php` exposes these four values in the `ggm_settings` option:

| Section | Global setting key | Built-in fallback |
|---|---|---|
| Discover | `ggm_workshop_discover_heading` | You Will Discover |
| Why Different | `ggm_workshop_why_different_heading` | Why This Webinar Is Different |
| Perfect For | `ggm_workshop_perfect_for_heading` | Perfect For You If You Want To |
| FAQ | `ggm_workshop_faq_heading` | Frequently Asked Questions |

`admin/class-ggm-admin.php` explicitly sanitizes these settings as text. Blank values are valid and fall back to the translated built-in labels through `ggm_get_workshop_block_heading()`.

### The resolver is global-only

`includes/class-ggm-helpers.php` contains `ggm_get_workshop_block_heading( $section )`. It maps a section to its global setting and built-in fallback, but accepts no workshop ID and reads no post metadata. This is the central limitation.

### Workshop creation/editing only displays the global headings

`includes/class-ggm-meta-boxes.php` renders the four Additional Blocks repeaters. Their `<h4>` labels call the global-only resolver. There are no per-workshop heading controls and `save_workshop_fields()` has no heading fields to sanitize or persist.

The existing nonce, post-type dispatch, autosave/revision protection, and capability checks in the meta-box save path can be reused. No new table or schema migration is needed; WordPress post meta is appropriate.

### Front-end consumers

There are two heading-output paths which must use the same resolver:

- `templates/workshop/single.php` resolves all four headings for the bundled single-workshop template.
- `modules/integration/elementor/class-ggm-elementor-workshop-heading-tags.php` supplies four Elementor text dynamic tags.

The repeater shortcodes in `public/class-ggm-shortcodes.php` formerly output content only. As of version 1.1.6, each shortcode prints its resolved workshop-aware heading before valid repeater content.

### Portability gap

`modules/import-export/class-ggm-import-export.php` exports and imports workshop fields and all four repeaters, but naturally has no per-workshop heading fields today. New overrides must be included so moving or backing up workshops does not lose them. Older JSON files must continue to import without errors and simply inherit global defaults.

### Other observations

- The workshop custom post type is REST-enabled, but this plugin does not currently register its workshop fields with `register_post_meta()`. Adding only these headings to REST would be inconsistent and is not required for the classic meta-box workflow.
- `uninstall.php` does not enumerate individual workshop post-meta keys; WordPress removes post meta when its post is deleted. No uninstall change is required.
- No Git repository metadata exists in the supplied directory, so change isolation must be verified by file inspection rather than `git diff` unless the workspace is later placed inside its repository.

## Proposed data contract

Store only explicit overrides, using namespaced scalar post-meta keys:

| Section | Workshop override meta key |
|---|---|
| Discover | `ggm_workshop_discover_heading_override` |
| Why Different | `ggm_workshop_why_different_heading_override` |
| Perfect For | `ggm_workshop_perfect_for_heading_override` |
| FAQ | `ggm_workshop_faq_heading_override` |

Values will be trimmed and passed through `sanitize_text_field()`. An empty submitted value will **delete** its post-meta key rather than storing an empty string. This makes “no override” explicit, keeps the database clean, and ensures later edits to global settings continue to affect inherited workshops.

No migration is required because all existing workshops have no override keys and therefore already represent the inheritance state.

## Proposed implementation

### 1. Make heading resolution workshop-aware

Extend the helper to accept an optional workshop ID:

```php
ggm_get_workshop_block_heading( $section, $workshop_id = 0 )
```

When a valid workshop ID is supplied, read the corresponding override first. If it is absent or trims to empty, retain the existing global-setting and built-in-fallback behavior. Keep the second argument optional so existing callers and third-party integrations remain backward compatible.

Use one central section map for the global key, override key, and built-in fallback so rendering and persistence cannot drift into different naming rules.

### 2. Add four optional fields to Workshop Details

In the **Webinar Additional Blocks (Repeaters)** area, add a clearly labelled “Block heading” text input above each repeater table.

- The input value contains only that workshop's saved override.
- When there is no override, its placeholder shows the currently resolved global/built-in default.
- Help text states: “Leave blank to use the default: {current default}.”
- The surrounding repeater title should display the effective resolved heading, so administrators can see what the front end will use.

This avoids pre-populating the actual input with inherited text. Pre-population would accidentally turn the default into a permanent workshop override whenever an unrelated workshop field is saved.

### 3. Save overrides safely

In `save_workshop_fields()`:

- Process a strict allow-list of the four request-field/meta-key pairs.
- Apply `wp_unslash()`, `sanitize_text_field()`, and `trim()`.
- Update post meta for non-empty values.
- Delete post meta for empty values, restoring inheritance.

The existing meta-box nonce and authorization pipeline remains authoritative. No HTML should be allowed in headings because every current output path escapes them as plain text.

### 4. Apply overrides to all heading renderers

- Pass `$workshop_id` from `templates/workshop/single.php` into the helper for all four sections.
- In each Elementor dynamic tag, resolve the current workshop context and pass its ID into the helper. Use the queried/current post only when it is a workshop; otherwise fall back to the global default. This protects Elementor editor/preview contexts where the current post may be a template rather than the previewed workshop.
- Render the same workshop-aware heading in each of the four repeater shortcodes.

### 5. Preserve overrides through export/import

Add the four override values to the exported workshop `meta` object. Import them through the same allow-list and sanitization rules used by the editor, deleting/omitting empty values. Missing keys in older exports must leave existing behavior intact and must not generate warnings.

For an import that updates an existing workshop, the safest compatibility rule is:

- key present: update or clear that override according to its value;
- key absent: leave the existing override untouched.

That avoids an older export unexpectedly erasing newer per-workshop customization.

### 6. Update administrator guidance

Revise the settings description so it says these values are global defaults and that an individual workshop can override them. Update the Help Center's workshop-field documentation to mention the four optional override controls and the blank-to-inherit behavior.

## Files expected to change after approval

1. `includes/class-ggm-helpers.php`
2. `includes/class-ggm-meta-boxes.php`
3. `templates/workshop/single.php`
4. `modules/integration/elementor/class-ggm-elementor-workshop-heading-tags.php`
5. `modules/import-export/class-ggm-import-export.php`
6. `admin/views/partials/settings-workshop-shortcodes.php`
7. `admin/views/page-help-center.php`

No database-table installer, upgrader, public shortcode markup, CSS, JavaScript, or uninstall change is expected.

## Acceptance criteria

1. A new workshop with blank override fields displays the current global headings in its editor context and on the bundled front end.
2. An existing workshop with no overrides automatically reflects a later global-setting change.
3. Setting one workshop's Discover override changes only that workshop and leaves the other three headings inherited.
4. Clearing and saving an override deletes its meta and immediately restores the current global default.
5. All four bundled-template headings use workshop overrides when present.
6. All four Elementor heading dynamic tags use workshop overrides in a valid workshop/preview context and global defaults outside one.
7. Repeater content markup remains intact, with a semantic H2 heading and section wrapper added around it.
8. Exported workshops contain explicit overrides; importing them restores the overrides.
9. Older exports without override keys still import successfully.
10. Text is sanitized on save/import and escaped on every output path.
11. Existing workshops require no bulk migration or re-save.

## Verification plan

- Run PHP syntax checks on every changed PHP file.
- Search all calls to `ggm_get_workshop_block_heading()` and confirm each context intentionally uses either workshop-aware or global-only resolution.
- Inspect a new and existing workshop in wp-admin: inherited placeholders, custom values, saving, and clearing.
- Test bundled single-workshop output for inherited, overridden, and cleared states.
- Test Elementor front-end and editor preview for each dynamic tag, including a non-workshop context.
- Change a global heading after creating workshops and confirm only workshops without that override update.
- Export/import a workshop with a mixture of inherited and overridden headings, then compare results.
- Import an older fixture/payload with no heading override fields and confirm compatibility.

## Approval gate

This document is the audit and proposed design only. No application PHP, template, settings, import/export, CSS, or JavaScript behavior has been changed. Implementation should begin only after explicit approval.
