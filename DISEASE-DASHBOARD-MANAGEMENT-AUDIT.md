# Disease Dashboard Management Audit

## 1. Scope and audit boundary

This is a read-only audit of the existing GGM Member Dashboard code and the active local WordPress runtime. No PHP, JavaScript, CSS, database schema, WordPress content, or configuration has been changed.

The requested outcome is an administrator-only Disease-management area in `/dashboard/` that uses the existing Disease records and their native fields. The requested operation is limited to listing, creating, and editing Diseases; the existing member-facing Disease view must remain intact.

## 2. Executive finding

Disease is already a native plugin entity, but it is **not** a WordPress post type, taxonomy, or post-meta model. It is a plugin-managed custom-table record.

The current dashboard has a normal `Disease` navigation item and `tab-disease.php`, but it is a logged-in member's read-only view of Diseases selected through submitted forms and explicit assignments. It is not inside the Administrator section and contains no management controls. The Administrator navigation currently contains Workshops, Courses, Members, and Payments only.

Therefore, implementation should add a new, separate `admin-diseases` dashboard tab, reusing the existing `wp_ggm_diseases` data model and the dashboard-management authorization/nonce pattern. It must not repurpose or replace the member-facing `disease` tab.

## 3. Native Disease architecture

### Storage model

`GGM_Diseases::table()` resolves to `{$wpdb->prefix}ggm_diseases`. In the local runtime this is `wp_ggm_diseases`; it contains eight active records.

The table is created by `GGM_Database::create_tables()` in `includes/class-ggm-database.php` and has these columns:

| Column | Type / behavior |
| --- | --- |
| `id` | Unsigned bigint primary key, auto-increment |
| `title` | Required `varchar(255)` |
| `description` | `longtext`, saved as permitted HTML |
| `status` | Required `varchar(20)`, defaults to `active` |
| `created_by` | Unsigned user ID, set during creation |
| `created_at` | Required datetime, set during creation |
| `updated_at` | Required datetime, updated on save/delete |

There are indexes for `title` and `status`. There is no unique-title constraint, no taxonomy, no attachment/media field, and no separate meta table for Disease records.

### Native service and registration

`modules/diseases/class-ggm-diseases.php` owns the entity. It is loaded and initialized by `ggm-member-dashboard.php`. The module:

- creates the table on `admin_init` if absent;
- supplies active options to the health-form builder;
- reads assigned Diseases for users;
- provides the existing wp-admin list/create/edit/delete UI;
- exposes wp-admin AJAX and `admin-post` handlers for native CRUD.

The wp-admin submenu is registered in `admin/class-ggm-admin.php` as `ggm-lms-diseases` and delegates rendering to `GGM_Diseases::render_admin_page()`.

## 4. Native UI and exact fields

The existing wp-admin page is **Diseases List**. It has List and Create/Edit views.

The Create/Edit form renders exactly these user-editable fields:

| UI field | Database field | Required | Native handling |
| --- | --- | --- | --- |
| Title | `title` | Yes | Plain text, maximum 255 characters, `sanitize_text_field()` |
| Description | `description` | Yes | WordPress editor textarea, `wp_kses_post()` |

The native save method rejects an empty title or a description whose stripped text is empty. It does not expose editable status, created-by, created-at, updated-at, taxonomy, featured image, media, slug, or custom metadata fields. `status` is set to `active` on create and save; timestamps and creator are system-managed.

The native list is limited to active records, searches title and description, displays Title / truncated Text / Updated date, paginates at ten records, and offers Edit, single Delete, and bulk Delete. Delete is a soft delete that changes `status` to `deleted` and updates `updated_at`.

There is no duplicate-title validation today. The table permits duplicates. The dashboard feature should preserve this native behavior unless a separately approved data-rule change is requested; silently introducing a new uniqueness rule would make the two management surfaces behave differently.

## 5. Current CRUD routes and constraints

Native Disease CRUD is already implemented, but its internal persistence helpers are private and request-coupled:

| Operation | Existing route | Authorization / nonce |
| --- | --- | --- |
| Load native List/Create tab | `wp_ajax_ggm_disease_tab` | `manage_options`; `ggm_disease_admin` nonce |
| Create/Edit via AJAX | `wp_ajax_ggm_save_disease_ajax` | `manage_options`; `ggm_save_disease` nonce |
| Delete via AJAX | `wp_ajax_ggm_delete_disease_ajax` | `manage_options`; dashboard-style nonce plus per-row delete nonce |
| Bulk delete via AJAX | `wp_ajax_ggm_bulk_delete_diseases_ajax` | `manage_options`; `ggm_bulk_delete_diseases` nonce |
| Non-AJAX create/edit/delete/bulk-delete | `admin-post.php` handlers | `manage_options`; matching WordPress admin nonce |

`save_disease_from_request()`, `get_disease()`, and list rendering are private methods. A dashboard endpoint therefore should not duplicate SQL or try to invoke these private methods. The lowest-risk implementation is to extract a small public/shared Disease data API used by both the existing native handler and the dashboard handler, while retaining the existing native validation and sanitization.

## 6. Disease associations and existing behavior

Disease data affects members in two independent ways:

1. **Health-form selections.** The form builder can configure a `search_select` field with `disease_source`. It hydrates that field from active `wp_ggm_diseases` records. Submitted answers and the form schema snapshot are stored in `wp_ggm_form_submissions`; `GGM_Diseases::selected_for_user()` finds the selected labels in submitted forms and resolves them to active Disease records by title.
2. **Direct administrator assignment.** `GGM_Diseases::assign_to_users()` saves active Disease IDs in user meta key `ggm_assigned_disease_ids`. The same `selected_for_user()` method includes those assigned IDs.

The member dashboard file `templates/dashboard/tab-disease.php` calls `selected_for_user( get_current_user_id() )` and displays each resulting active Disease's title and description as read-only cards. It has no create, edit, delete, or assignment action.

Two older, separate user-meta usages also exist: `ggm_diseases` is written by health intake/import flows. That legacy value is not the source read by `tab-disease.php`; the dashboard member view depends on form submissions plus `ggm_assigned_disease_ids`.

Deletion is deliberately soft, and all entity resolution methods filter to `status='active'`. Thus a deleted Disease no longer appears in form choices, direct assignment resolution, or a member's dashboard, even though its row and historical submitted text remain stored. An edit changes the content seen anywhere that resolves the same active Disease record. Renaming has a particular compatibility risk: form-derived resolution matches submitted values to current titles, so a renamed Disease can cease to resolve from older title-based responses. Direct ID assignments remain stable across a rename.

## 7. Dashboard management architecture to reuse

The existing administrator dashboard pattern is centered on `modules/dashboard/class-ggm-dashboard-management.php`:

- `ggm_dashboard_user_is_administrator()` requires a logged-in user, `manage_options`, and the `administrator` role.
- All dashboard management endpoints are authenticated `wp_ajax_ggm_dashboard_management_*` actions.
- Each handler calls `authorize()`, which validates nonce action `ggm_dashboard_management` and then invokes the administrator guard.
- `public/class-ggm-public.php` loads `wp_enqueue_editor()` and administrator management scripts only for this administrator predicate and localizes `ggmDashboardManagement` with the shared AJAX URL and nonce.
- `templates/dashboard/layout.php` conditionally renders existing administration nav items and sections only for the same predicate.

The current generic management script (`assets/js/ggm-dashboard-management.js`) supplies the list/filter/pagination shell for post-backed Workshops, Courses, and Members. Workshop and Course each have dedicated editor templates/scripts because their schemas are complex. Payments has a dedicated script because its list/actions differ.

Disease is a small custom-table entity, so it should use the same protected dashboard endpoint family and visual management-panel conventions, but needs a Disease-specific list/editor request and rendering path rather than the generic post-list implementation.

## 8. Recommended implementation design

### Dashboard UX

Add a desktop sidebar Administrator item labeled `Diseases` using the existing heart icon or another existing Dashicon. Add a protected `#tab-admin-diseases` section. Keep the normal `Disease` nav item and `#tab-disease` section unchanged.

The new page should follow the existing dashboard management visual language:

- heading `Diseases` and `Add Disease` primary button;
- search field for title/description, with active records listed in the current native order (updated newest first);
- a responsive table/card list with Title, short Description, Updated, and Edit action;
- a dashboard editor state with Back/Cancel, title input, WordPress rich-text Description editor, and one Save Disease primary action;
- visible inline success/error feedback and accessible live-result region;
- mobile behavior through the existing dashboard content area, without adding the new management item to the already compact bottom navigation unless separately designed and approved.

This reproduces the native entity fields and the dashboard's manager/editor experience. It does not add status editing, delete controls, bulk actions, attachments, taxonomies, or assignment tooling, because they are outside the requested scope.

### Server contract

Add two authenticated dashboard-management actions:

| Proposed action | Purpose | Request fields |
| --- | --- | --- |
| `ggm_dashboard_management_diseases` | Paginated active-record list and search | `page`, `search` |
| `ggm_dashboard_management_disease_save` | Create or update a Disease | `id`, `title`, `description` (preferably JSON payload consistent with existing editors) |

Both must call the existing `authorize()` first. Server-side validation must retain the native rules: title and non-empty stripped description are required; title is text-sanitized and description passes through `wp_kses_post()`.

Refactor the existing Disease module only enough to expose reusable, data-oriented operations (for example, an active-record query/load and a save method accepting explicit values). Then change native handlers to call that same API. This produces one validation/persistence path for wp-admin and dashboard management and keeps table writes, timestamps, `created_by`, status behavior, and error messages consistent.

## 9. Exact implementation touch points

The expected implementation set is:

| File | Intended change |
| --- | --- |
| `templates/dashboard/layout.php` | Add protected Administrator nav item and `tab-admin-diseases` section; leave the ordinary member Disease tab unchanged. |
| `templates/dashboard/admin/diseases.php` | New protected dashboard management-list template. |
| `templates/dashboard/admin/disease-editor.php` | New protected create/edit template using only native Title and Description fields. |
| `modules/dashboard/class-ggm-dashboard-management.php` | Register, authorize, and implement disease list/load/save dashboard actions. |
| `modules/diseases/class-ggm-diseases.php` | Small refactor to make common Disease query/load/save behavior reusable by both existing wp-admin CRUD and dashboard actions; no schema change. |
| `assets/js/ggm-dashboard-diseases.js` | New dashboard Disease list/editor behavior, including rich-text editor lifecycle and safe text rendering. |
| `public/class-ggm-public.php` | Enqueue the new script only for eligible dashboard administrators, after the management base script and with editor support already present. |
| Existing dashboard stylesheet only if required after visual inspection | Add narrowly scoped responsive Disease-manager styles; prefer existing `.ggm-management-*` styles and do not restyle the whole dashboard. |

Files explicitly not expected to change: `includes/class-ggm-database.php`, member `templates/dashboard/tab-disease.php`, health-form schema/storage, form-submission history, user association metadata, and payment/workshop/course behavior.

## 10. Security, validation, and output handling

All new endpoints must be `wp_ajax_` only, never `wp_ajax_nopriv_`. A browser-side hidden tab is not sufficient authorization; every list, load, create, and update request must validate the shared dashboard nonce and enforce `ggm_dashboard_user_is_administrator()` server-side.

The dashboard must not trust browser-supplied IDs or fields. IDs require `absint()`. The implementation must confirm an edit target exists and is active before update. The native update currently does not check that an ID is active before calling `$wpdb->update`; the shared API should preserve expected update semantics but should return a clear not-found error for a non-active/missing ID so dashboard feedback is deterministic. This refinement must be applied consistently to the native surface if adopted.

Sanitize and escape at the appropriate boundaries:

- `sanitize_text_field()` for title and search;
- `wp_kses_post()` for submitted description;
- `esc_html()` for titles and plain-text list excerpts;
- `wp_kses_post()` only when rendering saved rich description in the member-facing card/editor context;
- `wp_json_encode()`/WordPress localization for JavaScript configuration;
- DOM text escaping rather than string interpolation for user-originated list values.

No new capability, role, data exposure, or unauthenticated access should be introduced.

## 11. Compatibility and data risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Writing a second Disease persistence implementation | Native and dashboard validation/timestamps can drift. | Use one shared data-oriented Disease API. |
| Changing native fields or schema | Existing form choices and member cards may stop matching expectations. | Use the existing table and exactly Title + Description UI fields. |
| Rename of a Disease used by historical form answers | Older title-based form selections may not resolve after a rename. | Do not change the form-resolution model in this feature; document this existing behavior and test it. |
| Soft deletion | Deleted records disappear from member cards and dynamic choices. | Do not expose delete in this first dashboard scope; preserve native soft-delete semantics. |
| Loading TinyMCE in a hidden tab | Editor can fail to initialize or fail to sync contents. | Initialize only on entering the editor, trigger editor save before AJAX submission, and tear down/reinitialize cleanly. |
| Generic manager assumes posts | Custom table fields/status/date differ from post fields. | Use a Disease-specific renderer/endpoints, while reusing manager conventions and authorization. |
| Mobile navigation crowding | Adding another bottom-nav action would reduce usability. | Add the item in the Administrator sidebar; retain the current mobile nav until a separate mobile-information-architecture decision. |

## 12. Test and acceptance plan after approval

### Automated/static checks

1. Run PHP lint on each changed PHP file with the Local PHP binary.
2. Run JavaScript syntax/lint checks available in the repository.
3. Run `git diff --check` to catch whitespace errors.
4. Verify no database migration/schema change was introduced.

### Local runtime administrator checks

1. Log in as an actual administrator and load `/dashboard/`.
2. Confirm the new Diseases item is inside Administrator and opens the new manager.
3. Confirm the existing ordinary Disease tab still shows only the current user's selected Diseases.
4. Search with matching/non-matching title and description text; verify paging and empty states.
5. Create a record with title and rich description; confirm it appears in wp-admin Diseases List, in active form-builder disease options, and via a dashboard refresh.
6. Edit the dashboard-created record; confirm the same record changes in wp-admin and member-facing resolution where assigned/selected.
7. Test validation for missing title, blank rich description, oversized title, invalid/missing edit ID, and server errors; verify usable messages and no partial save.
8. Verify existing active local records remain readable and editable.
9. Exercise narrow desktop and mobile widths; verify tables/forms are usable and no hidden editor overlap occurs.

### Authorization and regression checks

1. As a non-administrator, confirm the nav item and section are absent.
2. As a non-administrator and logged out, POST directly to each new AJAX action; expect rejection without record disclosure or writes.
3. As an administrator, repeat requests with a missing/invalid nonce; expect rejection.
4. Confirm Workshop, Course, Member, Payment, health-form, wp-admin Disease, and normal member Disease experiences continue to work.
5. Confirm create/edit does not alter existing `ggm_assigned_disease_ids`, historical form submissions, or legacy `ggm_diseases` user meta.

## 13. Implementation sequencing after approval

1. Extract and test the small shared Disease query/load/save API without changing native behavior.
2. Add protected dashboard actions and their response contracts.
3. Add the protected layout navigation and manager/editor templates.
4. Add the Disease dashboard script and only the minimum scoped style rules needed.
5. Run lint/static checks, then complete the local administrator/non-administrator runtime matrix above.
6. Report changed files, behavior verified, and any limitation discovered before considering a deletion/assignment enhancement.

## 14. Audit conclusion

The requested dashboard feature is feasible without a migration and should use the existing `wp_ggm_diseases` table as the single source of truth. The primary design requirement is separation of the new administrator manager from the pre-existing member-only Disease tab, coupled with reuse of the native validation/persistence logic rather than duplicated SQL.

This audit intentionally does not authorize or perform implementation.
