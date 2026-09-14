# Workshop Dashboard Editor: Deep Audit and Implementation Plan

**Status:** Phase 1 audit only. No functional implementation changes were made for this request.

## 1. Executive Summary

The `/dashboard` Workshop editor is an incomplete prototype. It correctly targets real Workshop posts and existing slot/meta storage, but its visible JSON fields, attachment-ID field, and Excerpt field violate the intended administrator experience. The safe correction is a dashboard-native structured editor over the same posts, post meta, featured image, and `GGM_Workshop_Slot` rows—without changing public rendering, payment, checkout, access, Elementor, or the native editor.

## 2. Current Dashboard Editor Audit

`templates/dashboard/admin/workshop-editor.php` renders title/content/Excerpt plus a sparse dynamically generated field set. `assets/js/ggm-dashboard-management.js` serializes JSON textareas and posts a JSON payload to `GGM_Dashboard_Management::ajax_workshop_save()`. `GGM_Workshop_Data_Service` loads canonical data and saves selected meta/slots.

## 3. Current UX Problems

The editor is developer-oriented: it displays raw slot/repeater/meta JSON and attachment IDs; does not provide media previews/selectors; has no relationship selectors; omits most native fields; does not organize data into native-equivalent sections; and includes prohibited Excerpt.

## 4. Raw JSON Audit

| Current control | Origin | Actual storage | Correct replacement | Final visibility |
| --- | --- | --- | --- | --- |
| Time slots (JSON) | temporary dashboard payload shortcut | `wp_ggm_workshop_slots` | structured Slot cards | removed |
| Repeaters (JSON) | temporary JS serializer | canonical JSON meta | section-specific repeaters | removed |
| Advanced canonical Workshop data (JSON) | temporary fallback/debug escape hatch | selected canonical post meta | named proper controls | removed |
| Featured image attachment ID | temporary media fallback | `_thumbnail_id` | media modal with preview | removed |

JSON remains internal only where the established storage schema requires it.

## 5. Excerpt Audit

Excerpt is a WordPress core field, not a required Workshop-specific dashboard field. Its current template textarea and controller `post_excerpt` update are incorrect. It must be removed from markup, JS payload, and Dashboard save updates. Existing `post_excerpt` values remain unchanged.

## 6. Complete Workshop Field Inventory

Native Workshop-specific editable fields are: mentors; linked course; counter/preparatory/start/end dates; regular/sale prices; currency extras; contribution enabled/options/default; mode; header pill; Booking Hero header/items; CTA heading/text; testimonial/rating; WhatsApp URL; language; duration; free; featured video; bottom image; Form Text heading/content; slots; additional layouts; Discover, Why Different, Why Workshop Different, Journey, Perfect For, and FAQ fields/repeaters. Native core provides title/content/featured image/status. Compatibility-only fields include `workshop_date`, `workshop_money`, `ggm_mentor_id`, `workshop_short_desc`, and unprefixed repeater keys.

## 7. Native Workshop Architecture

`GGM_Meta_Boxes::render_workshop_details_mb()` is the authoritative native UI. `save_workshop_fields()` and `save_workshop_time_slots()` own the present native validation/sanitization. `GGM_Workshop_Slot` owns the slot table. `GGM_Workshop_Admin_Config` holds the shared logical section/field IDs and native visibility/order option. Core post title/content/status/thumbnail are ordinary WordPress data.

## 8. Dashboard Workshop Architecture

The dashboard uses an authenticated `admin-ajax.php` management controller and `GGM_Workshop_Data_Service`. The controller already checks a dedicated nonce, Administrator role, `manage_options`, post type, and `edit_post` on existing records. The editor must become an isolated structured dashboard form while retaining this endpoint/data-service architecture.

## 9. Field Parity Matrix

| Section | Field | Native Storage | Native UI | Dashboard UI | Same Storage | Same Validation | Same Sanitization | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Core | Title | `wp_posts.post_title` | core | text | Yes | WP | WP | build |
| Core | Content | `wp_posts.post_content` | core | rich editor | Yes | Yes | `wp_kses_post` | build |
| Core | Featured image | `_thumbnail_id` | core sidebar | media modal | Yes | attachment image | attachment image | build |
| Core | Excerpt | `post_excerpt` | core if enabled | **excluded** | preserve only | N/A | N/A | exclude |
| Details | Mentors | `ggm_mentor_ids`, mirror `ggm_mentor_id` | checkboxes | searchable multi-select | Yes | post type | `absint` | build |
| Details | Linked Course | `linked_course_id` | select | searchable select | Yes | `course` type | `absint` | build |
| Details | Counter date/time | `Counter_Start_Date` | datetime | datetime-local | Yes | native format | text | build |
| Details | Preparatory date | `workshop_preparatory_date` | date | date | Yes | native format | text | build |
| Details | Start/end dates | `workshop_start_date`, `workshop_end_date` | date range | two dates | Yes | end >= start | text | build |
| Details | Mode | `workshop_mode` | text | text | Yes | existing | text | build |
| Details | Header pill | `ggm_workshop_header_pill_text` | text | text | Yes | existing | text | build |
| Details | Language | `workshop_language` | select | native-options select | Yes | English/Hindi | key | build |
| Details | Duration | `duration` | text | text | Yes | existing | text | build |
| Details | Video | `ggm_workshop_featured_video_url` | URL | URL | Yes | YouTube URL | existing sanitizer | build |
| Details | Bottom image | `ggm_workshop_bottom_image_id` | media | media modal | Yes | image attachment | absint | build |
| Pricing | Regular/sale | price meta | decimals | currency controls | Yes | sale <= regular | float | build |
| Pricing | Currency extras | currency service/meta | inputs | per-currency inputs | Yes | currency service | currency service | build |
| Pricing | Contribution | contribution meta | repeater | structured rows | Yes | native rules | native rules | build |
| Pricing | Is Free | `is_free` | checkbox | toggle | Yes | contribution interaction | bool | build |
| Booking | Hero header | hero header meta | rich editor | rich editor | Yes | native KSES | native KSES | build |
| Booking | Hero 1–4 | hero-items array | image/text groups | four image/text cards | Yes | native shape | URL/text | build |
| Booking | CTA/proof | CTA/testimonial/rating meta | inputs | named fields | Yes | existing | text | build |
| Booking | WhatsApp | WhatsApp URL meta | URL | URL | Yes | URL | URL | build |
| Form | Heading/content | form-text meta | input/editor | input/rich editor | Yes | native KSES | native KSES | build |
| Slots | rows/order/status | slot table | table | cards + move buttons | Yes | slot service | slot service | build |
| Layout | columns/rows | layout array | selects | selects | Yes | bounds | absint | build |
| Discover | override/items/bottom | Discover meta | repeater | cards | Yes | native | native | build |
| Why Different | override/items/bottom | Why meta | repeater | cards | Yes | native | native | build |
| Why Workshop Different | heading/intro/points/image | dedicated meta | fields/repeater | fields/cards/media | Yes | native | native | build |
| Journey | heading/days/footer | Journey meta | repeaters | cards | Yes | native | native | build |
| Perfect For | override/items/bottom | Perfect meta | repeater | cards | Yes | native | native | build |
| FAQ | override/question/answer | FAQ meta | repeater | cards | Yes | native | native | build |
| Legacy | short description | `workshop_short_desc` | retired | preserve only | Yes | N/A | N/A | preserve |

## 10. Section Hierarchy

Basic Information; Workshop Details; Pricing; Booking Card; Form Text; Workshop Time Slots; Additional Block Layout; You Will Discover; Why Different; Why This Workshop Is Different; Workshop Journey; Perfect For You; FAQ.

## 11. Control Type for Every Field

Use text, URL, date, datetime-local, time, select, checkbox/toggle, searchable relationship selector, media modal, rich editor, card repeater, and slot cards as specified in the parity matrix. No normal administrator field uses a raw JSON textarea.

## 12. Media Architecture

Use the WordPress media library on demand. Featured/bottom image use canonical attachment IDs. Existing repeater icon/image URL schema remains unchanged, but media selection supplies the canonical URL and a preview. Every media control supports Select, Preview, Replace, and Remove. Server validates image attachments where IDs are stored.

## 13. Relationship Architecture

Bootstrap only authorized mentor/course options. Mentors are named multi-select chips; linked Course is a named select. Save validates each selected mentor is `ggm_mentor` and the linked post is `course` before updating canonical keys.

## 14. Repeater Architecture

Discover: heading/description/image. Why Different: title/icon. Why Workshop Different: tick text. Journey: day/description/image and four footer icon/text boxes. Perfect For: title/description/image. FAQ: question/answer. Booking Hero: fixed four icon/text boxes. Contribution: amount/label/free/default. All use Add/Edit/Remove/Move Up/Move Down; order is the canonical submitted array order.

## 15. Time Slot Architecture

Use existing slot IDs internally, `GGM_Workshop_Slot::types()` for options, and `GGM_Workshop_Slot` create/update/delete-missing semantics. Section absence means preserve slots; an enabled section explicitly submitted as an empty list means remove all. Existing unknown legacy slot types remain selectable.

## 16. Booking Card Architecture

One dashboard section renders rich Hero Header, four Hero cards, CTA Heading, Button Text, Testimonial, Rating, and WhatsApp URL as individual controls. It writes the established Booking Card meta only.

## 17. Workshop Configuration Compatibility

The dashboard currently receives `GGM_Workshop_Admin_Config::get()` but does not apply it. The correction must use this same registry for logical section/field IDs, order, and visibility. Dashboard layout remains different; option meaning remains unchanged. Disabled fields must not be submitted or cleared.

## 18. Hidden Field Preservation

Service update behavior must be presence-based: only an explicitly present, allowlisted key is written. Missing key means preserve. An enabled/visible repeater or slot section that explicitly submits zero rows means delete its existing rows. This distinction is mandatory for every field, media value, contribution, repeater, and slot.

## 19. Data Service Architecture

Refactor `GGM_Workshop_Data_Service` into explicit `load`, `normalize`, `validate`, and `save` paths with a strict field allowlist. Remove `canonical_meta` from the normal dashboard request. It remains non-rendering and has no dashboard-only data model.

## 20. Save Architecture

Structured dashboard form → JSON transport containing named allowed fields (not serialized field JSON) → management nonce/role/capability/type checks → service normalize/validate → canonical post/meta/attachment/slot service. Dashboard must not fabricate `$_POST` or call native `save_post` callbacks.

## 21. Validation

Validate post status allowlist, dates/range, sale price, contributions/default/free option, known relationship types, image attachment type, YouTube/URL fields, bounded layout values, each repeater shape, and slot range/type/ownership. Return keyed field errors.

## 22. Sanitization

Mirror native `sanitize_text_field`, `sanitize_textarea_field`, `wp_kses`/`wp_kses_post`, `esc_url_raw`, `absint`, `sanitize_key`, the existing YouTube sanitizer, currency service, and slot normalizer. Do not silently change legacy canonical URL/JSON storage shape.

## 23. Security

Every management request retains: authenticated user, dedicated nonce, Administrator role, `manage_options`, Workshop type/ID checks, `edit_post` for edits, strict payload allowlist, relationship/media verification, server validation, and no `wp_ajax_nopriv` handler.

## 24. Responsive Design

Scope styles to `#ggm-dash`; use one-column sections/cards on mobile, wrapping control rows, touch-sized actions, and no required horizontal table scroll. Desktop may use compact multi-column field grids.

## 25. Accessibility

Use labels, fieldsets/legends, visible focus, status/error live regions, `aria-describedby` field errors, semantic buttons, keyboard Move Up/Down controls, and never drag-only reordering.

## 26. Performance

Keep manager pagination. Load full Workshop only after edit opens. Load mentor/course options only in authorized bootstrap and media modal assets only when invoked. Avoid complete Workshop/repeater loading in list requests.

## 27. Exact Files to Modify

### `templates/dashboard/admin/workshop-editor.php`

Current responsibility: sparse JSON-based editor markup. Exact change: replace Excerpt/manual-ID/raw-JSON controls with semantic sections, field containers, error areas, structured repeaters, slot cards, and media controls. Risk: markup/JS contract. Test: DOM/no-JSON/excerpt contract and mobile rendering.

### `assets/js/ggm-dashboard-management.js`

Current responsibility: manager lists plus prototype editor. Exact change: remove JSON UI/serializer; add structured data hydration, media modal, repeater/slot add/remove/reorder, config ordering/visibility, field errors, and unsaved changes. Risk: payload compatibility. Test: JS syntax and editor fixture tests.

### `assets/css/ggm-dashboard.css`

Current responsibility: dashboard styling. Exact change: scoped editor cards, grid, media preview, slot/repeater/action/error/focus/mobile styles. Risk: dashboard style bleed. Test: desktop/tablet/mobile visual checks.

### `modules/workshop/class-ggm-workshop-data-service.php`

Current responsibility: partial canonical load/save. Exact change: complete structured allowlist, validation, sanitization, presence preservation, relationship/media checks, canonical repeaters, slot safety; remove public use of `canonical_meta`. Risk: high data parity. Test: fixture and native/dashboard parity.

### `modules/dashboard/class-ggm-dashboard-management.php`

Current responsibility: authorized management AJAX. Exact change: selector/bootstrap data, keyed errors, Workshop payload/type validation; stop updating `post_excerpt`. Risk: endpoint contract. Test: authorization, malformed payload, invalid IDs.

### `scripts/test-dashboard-management.php`

Current responsibility: static management checks. Exact change: assert Excerpt/JSON/attachment-ID controls absent, all registered dashboard fields allowlisted, no nopriv action. Risk: test coverage only. Test: execute script.

## 28. Exact Files to Add

### `templates/dashboard/admin/partials/workshop-editor-schema.php`

Purpose: server-side shared dashboard field descriptors/selector values, not save logic. Dependencies: config registry/services. Security: administrator-rendered only. Tests: descriptor IDs map to configuration IDs.

### `assets/js/ggm-dashboard-workshop-editor.js`

Purpose: isolated structured Workshop editor rather than expanding the already broad management bundle. Dependencies: localized management data, WordPress media APIs. Security: no authority; server remains authoritative. Tests: syntax and DOM fixtures.

### `scripts/test-workshop-dashboard-editor-parity.php`

Purpose: field inventory, raw JSON exclusion, payload allowlist, preservation, and parity contract tests. Dependencies: source fixtures/stubs. Tests: run locally and against a WordPress fixture site when available.

## 29. Exact Code Areas

Affected: dashboard Workshop template and JS, management bootstrap/save endpoint, Workshop data service, scoped dashboard CSS, focused tests. Native save extraction is deferred behind parity fixtures. No new database code, post type, table, or shortcode is needed.

## 30. Files That Must Not Change

`templates/workshop/single.php`, `templates/checkout/checkout.php`, `modules/payment/`, `modules/import-export/class-ggm-access-manager.php`, `modules/workshop/class-ggm-workshop-slot.php` semantics, public Workshop shortcode rendering, Elementor integration, Course/Member systems, and access schemas. They are protected because this is an alternative editor only.

## 31. Testing Matrix

| Area | Test/result required |
| --- | --- |
| Native → Dashboard | every field loads identically |
| Dashboard → Native | every field/slot/repeater persists identically |
| Excerpt | absent and unchanged after dashboard save |
| Raw JSON | zero visible Workshop JSON editing controls |
| Repeaters | add/edit/remove/reorder both directions |
| Slots | add/edit/remove/reorder with hidden-section preservation |
| Media | select/replace/remove and attachment validation |
| Configuration | visibility/order and preservation of hidden values |
| Security | non-admin/nonce/type/ID/relationship/media/payload failures denied |
| Regression | native editor/public Workshop/booking/checkout/payment/access/Elementor unchanged |
| UI | desktop/tablet/mobile and keyboard/accessibility checks |

## 32. Implementation Phases

1. Schema and raw-control removal.
2. Basic/Details/relationships.
3. Pricing/media.
4. Booking Card/Form Text.
5. Slots.
6. Layout/repeaters.
7. Service validation/preservation.
8. Native/dashboard parity.
9. Security/performance/responsive/accessibility.
10. Full regression and report.

## 33. Risks

Highest risks are accidental overwriting of omitted hidden fields, slot deletion on hidden sections, legacy repeater compatibility, media schema mismatch, and divergence from native validation. Mitigations are presence-based saves, explicit section markers, canonical storage reuse, fixture parity, and deferred native save refactor.

## 34. Rollback Strategy

All changes are code/UI only; no migration is planned. Keep the existing native editor untouched. If deployment fails, restore the prior plugin ZIP; existing posts, meta, slots, access rows, and attachments have not been migrated or transformed. Do not run destructive cleanup on rollback.

## 35. Approval Gate

The deep Workshop Dashboard Editor audit and implementation plan are ready. Do you approve proceeding with implementation?
