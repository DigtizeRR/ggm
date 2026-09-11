# Elementor Workshop Bottom Image Dynamic Tag Plan

## Implementation status

Approved and implemented on 2026-09-11. Targeted PHP syntax checks, whitespace checks, metadata/tag path inspection, and the Bottom Image Dynamic Tag contract tests passed. Live Workshop editor, Elementor editor, and ZIP package round-trip checks remain deployment smoke tests because this workspace does not include the production WordPress/Elementor runtime and the local PHP ZIP extension is unavailable.

## Requested outcome

Add a workshop-level image field named **Bottom Image** that an administrator can set in the Workshop editor. Expose that value in Elementor under **Dynamic Tags -> GGM Workshop -> Bottom Image**, so the native Elementor Image widget and supported background/media controls render the image belonging to the current workshop.

This plan interprets “Elementor widget dynamic field” as an Elementor **media/image Dynamic Tag**. That is the native Elementor mechanism for feeding a dynamic image into an existing Image widget; a separate custom image widget would duplicate Elementor’s sizing, responsive, link, alt-text, and style controls.

## Audit findings

### 1. Existing Elementor integration

- `modules/integration/class-ggm-elementor.php` waits for `elementor/loaded`, then registers tags through Elementor’s supported `elementor/dynamic_tags/register` hook.
- It already creates the **GGM Workshop** Dynamic Tags group and registers workshop video and workshop heading tags from separate class files.
- Workshop tags resolve the active workshop from singular, loop, queried-object, and Elementor editor/document contexts.
- The integration is optional: Elementor classes are not loaded or referenced until Elementor itself is available. The Bottom Image tag must preserve this no-Elementor safety.

Elementor’s current developer documentation confirms that dynamic tags should be registered through this manager hook and that image/media categories determine which Elementor controls can select a tag.

### 2. Existing workshop admin image handling

- `includes/class-ggm-meta-boxes.php` owns the **Workshop Details** editor, nonce/capability checks, saving, and WordPress Media Library loading.
- The Workshop editor already has a reusable thumbnail-style media control with **Upload** and **Clear** actions.
- Existing workshop image fields save URLs. For a new Elementor media value, an attachment ID is the stronger canonical value because Elementor can use the WordPress attachment record for image sizes and metadata.
- There is currently no workshop meta key or Elementor tag for a bottom image, so Elementor has no dynamic source to select.

### 3. Workshop context and compatibility

- The live editor currently attaches Workshop Details to the `workshop` post type.
- Existing front-end workshop resolution deliberately recognizes both `workshop` and the legacy/internal `ggm_workshop` type.
- The new tag should follow the same two-post-type read behavior. The admin field will be placed in the existing Workshop Details screen and will not add settings to unrelated posts, courses, forms, or mentors.

### 4. Import/export consideration

- Workshop packages export all workshop metadata and discover media primarily from featured/attached images and URLs inside metadata.
- A raw attachment ID selected from an existing Media Library item may not be attached to the workshop and therefore may not enter the package automatically.
- The implementation should explicitly include and remap the Bottom Image attachment ID during workshop package export/import so the new field remains portable.

## Proposed implementation

### Phase A: add the Bottom Image administrator field

1. Add a **Bottom Image** row to the existing Workshop Details meta box, beside the current featured-media settings.
2. Use the WordPress Media Library restricted to images only.
3. Display:
   - a bounded thumbnail preview;
   - **Select / Replace Image**;
   - **Remove Image**;
   - brief help text explaining that Elementor exposes it through the GGM Workshop dynamic tag.
4. Store the selected WordPress attachment ID in one dedicated meta key: `ggm_workshop_bottom_image_id`.
5. On save:
   - reuse the existing meta-box nonce and `edit_post` capability gate;
   - normalize with `absint()`;
   - accept only a real attachment for which `wp_attachment_is_image()` is true;
   - delete the metadata when the administrator clears the field;
   - reject forged non-image attachment IDs rather than rendering them later.

No default image will be silently assigned. A workshop with no Bottom Image remains empty.

### Phase B: register the Elementor image/media Dynamic Tag

1. Add a dedicated tag class under `modules/integration/elementor/` named for the workshop Bottom Image.
2. Register it in the existing **GGM Workshop** group only after Elementor is loaded.
3. Give it a stable internal slug such as `ggm-workshop-bottom-image` and the visible title **Bottom Image**.
4. Implement it as Elementor structured dynamic data, returning an image array containing the validated attachment `id` and full image `url`.
5. Advertise Elementor’s image/media categories so it appears in compatible native controls, including the Image widget’s image selector and supported background/media selectors.
6. Resolve the workshop using the same singular/loop/editor rules as the current workshop tags. To avoid another copy of that logic diverging later, move the resolver into one reusable integration helper and have the existing workshop video fallback and the new tag use it without changing their output.
7. Return an empty image value when:
   - there is no current workshop;
   - the field is unset;
   - the attachment was deleted;
   - the attachment is not an image.

The tag will return data only. Elementor remains responsible for HTML, responsive sizing, links, lazy loading, alt text, and visual styling.

### Phase C: keep workshop packages portable

1. Include the Bottom Image attachment explicitly in the package media collection even when it was selected from existing library media and is not attached to that workshop.
2. Remap the exported attachment ID to the newly imported attachment ID when restoring a workshop package.
3. Leave all existing URL replacement and featured-image behavior unchanged.

### Phase D: version and compatibility housekeeping

1. Bump the plugin patch version so updated assets/code are identifiable after deployment.
2. Do not add CSS to the public site: placement and styling remain controlled by the Elementor widget/container where the Dynamic Tag is selected.
3. Do not create a shortcode or a second standalone Elementor widget unless separately requested.

## Files expected to change after approval

- `includes/class-ggm-meta-boxes.php` — Bottom Image selector, preview, image-only media behavior, and validated save/delete logic.
- `modules/integration/class-ggm-elementor.php` — load/register the tag and share workshop-context resolution safely.
- `modules/integration/elementor/class-ggm-elementor-workshop-bottom-image-tag.php` — new structured Elementor image/media Dynamic Tag.
- `modules/import-export/class-ggm-workshop-package.php` — package and remap the selected Bottom Image attachment.
- `ggm-member-dashboard.php` — patch-version bump.
- `scripts/test-elementor-bottom-image-tag.php` — repeatable tag identity, category, valid-image, invalid-image, and missing-context contract checks.

No database table or destructive migration is required; WordPress post metadata is created only when an administrator chooses an image.

## Verification plan

### Static checks

1. Run `php -l` on every changed PHP file.
2. Run `git diff --check`.
3. Confirm Elementor classes are referenced only after `elementor/loaded` and that the site still loads when Elementor is inactive.
4. Search for the new meta key and tag slug to verify there is exactly one writer and one authoritative rendering path.

### Workshop editor tests

1. Select an image, update the workshop, reload the editor, and confirm the same thumbnail remains selected.
2. Replace the image and confirm the tag returns the replacement.
3. Remove the image, update, and confirm the metadata is deleted and the preview is empty.
4. Submit a forged non-image attachment ID and verify it is not saved.
5. Confirm other Workshop Details fields and their media controls still save normally.

### Elementor tests

1. In an Elementor Image widget, select **Dynamic Tags -> GGM Workshop -> Bottom Image** and confirm the editor preview renders the current workshop image.
2. Confirm the same image renders on the published workshop page.
3. Test a supported background/media control and confirm the tag is available and returns the same image.
4. Test a workshop without an image and confirm there is no broken URL, PHP warning, or unwanted placeholder.
5. Test a deleted attachment and confirm output fails closed to empty.
6. Test Elementor editor/document preview, singular workshop view, and loop context to prevent one workshop’s image leaking into another.
7. Confirm ordinary pages, posts, forms, courses, and existing Elementor dynamic tags are unchanged.

### Package round-trip test

1. Select an existing Media Library image that is not attached to the workshop.
2. Export the workshop package and verify the image is included.
3. Import into a clean target and verify the Bottom Image meta points to the newly imported attachment and renders through Elementor.

## Acceptance criteria

- Administrators can select, replace, and remove one Bottom Image per workshop.
- Elementor shows one **Bottom Image** tag in the **GGM Workshop** group for compatible image/media controls.
- The correct workshop image renders in both Elementor preview and the public page.
- Empty, deleted, or invalid images produce empty output rather than broken markup.
- Other widgets, dynamic tags, post types, and existing workshop fields do not change.
- Workshop export/import preserves the Bottom Image.
- Syntax, whitespace, optional-Elementor safety, editor persistence, front-end rendering, and package round-trip checks pass.
