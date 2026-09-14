# Workshop Dashboard Editor: Complete Field Audit and Corrected Implementation Plan

**Status:** Audit and plan only. No Workshop editor, save path, native editor, public Workshop, checkout, payment, access, or Elementor code was changed for this request.

## 1. Current Dashboard Workshop Editor Audit

The current editor is `templates/dashboard/admin/workshop-editor.php`, populated by `assets/js/ggm-dashboard-management.js`, loaded/saved through `GGM_Dashboard_Management`, and persisted by `GGM_Workshop_Data_Service`. It is an incomplete administrative payload prototype, not a production Workshop editor.

## 2. Current Screenshot/UX Problems

It exposes developer-oriented JSON textareas, attachment IDs, a partial field list, and WordPress Excerpt. It lacks section hierarchy, media selection, named relationships, structured repeaters, slot controls, field-level validation, and configuration-driven visibility/order.

## 3. Raw JSON Field Audit

| Current UI | Why it exists | Underlying data | Correct dashboard UI | Internal JSON remains | Visible JSON removed |
| --- | --- | --- | --- | --- | --- |
| `Time slots (JSON)` | Temporary payload shortcut | `wp_ggm_workshop_slots` rows via `GGM_Workshop_Slot` | Repeatable Slot cards with type, start/end, meeting link, enabled, remove/reorder | Yes, only in AJAX payload | Yes |
| `Repeaters (JSON)` | Temporary fallback in management JS | Canonical JSON meta repeaters | Individual section-specific repeaters | Yes, in canonical meta | Yes |
| `Advanced canonical Workshop data (JSON)` | Debug/fallback field for arbitrary loaded meta | Selected canonical post meta | Named fields/repeaters/media controls | Yes, server-side only | Yes |
| Featured image attachment ID | Interim media fallback | WordPress featured-image attachment | WP media modal, preview, replace/remove | Attachment ID internally | Yes |

## 4. Complete Native Workshop Field Inventory

The authoritative source is `GGM_Meta_Boxes::render_workshop_details_mb()` and its save routines. Native core controls provide title, content, status, and featured image. The plugin meta box provides the fields below. Legacy read fallbacks (`workshop_date`, `workshop_money`, single `ggm_mentor_id`, unprefixed repeaters) remain compatibility reads, not new dashboard fields.

## 5. Complete Dashboard Workshop Field Inventory

The approved implementation will render every native editable Workshop-specific field below using normal controls. Post Excerpt is intentionally excluded. Existing legacy values are loaded and preserved, never rewritten into a new schema.

## 6. Missing Fields

Missing today: mentors, linked course, counter/preparatory dates, header pill, language select, free/contribution/currency controls, bottom image media UI, Booking Hero/CTA/social proof, rich form content, all individual additional-block controls, all repeaters, block heading overrides/bottom texts, Why Workshop Is Different fields, Journey footer boxes, and structured slot management.

## 7. Incorrectly Rendered Fields

Current scalar inputs cover only a subset and do not apply native labels/groups. Raw JSON fields, manual attachment IDs, and a textarea used for rich form content are incorrect. Workshop Mode is currently a plain input in native too; the dashboard will preserve that existing unconstrained field rather than inventing values.

## 8. Duplicate Fields

`workshop_short_desc` is retained for legacy/frontend compatibility but is intentionally no longer editable in native admin; dashboard will preserve it and not add a duplicate. `ggm_mentor_id` mirrors the first `ggm_mentor_ids` entry and is not an independent field. `workshop_date` and `workshop_money` are legacy fallbacks only.

## 9. Excerpt Exclusion

| Section | Field | Status |
| --- | --- | --- |
| WordPress Core | Excerpt | **EXCLUDED FROM DASHBOARD WORKSHOP EDITOR** |

The dashboard save endpoint will stop accepting/updating `post_excerpt` for Workshops. Existing excerpts remain untouched.

## 10. Workshop Section Hierarchy

1. Basic Information — Title, featured image, content.
2. Workshop Details — mentors, linked course, dates, mode, header pill, language, duration, video, bottom image.
3. Pricing — regular/sale, currency extras, contribution, free.
4. Booking Card — Hero, CTA, social proof, WhatsApp.
5. Form Text.
6. Workshop Time Slots.
7. Additional Block Layout.
8. Discover; Why Different; Why This Workshop Is Different; Journey; Perfect For; FAQ.

## 11. Field-by-Field UI Specification

| Section | Field | Control Type | Required | Native Meta/Storage | Dashboard UI | Notes |
| --- | --- | --- | --- | --- | --- |
| Basic | Title | text | Yes | `wp_posts.post_title` | text field | core WP sanitization |
| Basic | Content | rich editor | No | `wp_posts.post_content` | dashboard rich editor | `wp_kses_post` |
| Basic | Featured Image | media modal | No | `_thumbnail_id` | preview/select/replace/remove | attachment image validation |
| Details | Mentors | searchable multi-select | No | `ggm_mentor_ids`, mirror `ggm_mentor_id` | named chips/select | valid `ggm_mentor` IDs |
| Details | Linked Course | searchable select | No | `linked_course_id` | course title selector | valid `course` ID |
| Details | Counter Start Date & Time | `datetime-local` | No | `Counter_Start_Date` | date/time control | preserve native format/timezone |
| Details | Preparatory Date | date | No | `workshop_preparatory_date` | date control | optional |
| Details | Start Date | date | No | `workshop_start_date` | date control | legacy `workshop_date` read fallback |
| Details | End Date | date | No | `workshop_end_date` | date control | must be >= start |
| Details | Workshop Mode | text | No | `workshop_mode` | labeled text field | no unapproved option list |
| Details | Header Pill Text | text | No | `ggm_workshop_header_pill_text` | text field | shortcode fallback applies |
| Details | Language | select | No | `workshop_language` | English/Hindi select | native options only |
| Details | Duration | text | No | `duration` | text field | displayed verbatim |
| Details | Featured YouTube Video URL | URL | No | `ggm_workshop_featured_video_url` | URL field | native YouTube sanitizer |
| Details | Bottom Image | media modal | No | `ggm_workshop_bottom_image_id` | preview/select/remove | image attachment only |
| Pricing | Regular Price | decimal | No | `workshop_regular_price` | currency input | legacy `workshop_money` read fallback |
| Pricing | Sale Price | decimal | No | `workshop_sale_price` | currency input | <= regular price |
| Pricing | Manual Currency Extras | currency inputs | No | existing currency adjustment meta/service | one field per enabled non-base currency | `GGM_Currency` service |
| Pricing | Contribution Enabled | toggle | No | `ggm_contribution_enabled` | switch | preserves options when switched off |
| Pricing | Contribution Options | repeater | Conditional | `ggm_contribution_options` | amount/label/free/default rows | maximum one free, one default |
| Pricing | Is Free | checkbox | No | `is_free` | checkbox | ignored while contribution enabled |
| Booking Hero | Hero Header | rich editor | No | `ggm_workshop_booking_card_hero_header` | dashboard rich editor | native KSES rules |
| Booking Hero | Hero Box 1–4 icon | media modal | No | `ggm_workshop_booking_card_hero_items[*].icon` | fixed four image pickers | canonical URL schema retained |
| Booking Hero | Hero Box 1–4 text | text | No | `ggm_workshop_booking_card_hero_items[*].text` | four labeled fields | blank fields hidden publicly |
| Booking CTA | CTA Heading | text | No | `ggm_workshop_booking_card_cta_heading` | text field | |
| Booking CTA | Button Text | text | No | `ggm_workshop_booking_card_cta_text` | text field | |
| Booking Proof | Testimonial | text | No | `ggm_workshop_booking_card_testimonial_text` | text field | |
| Booking Proof | Rating | text | No | `ggm_workshop_booking_card_rating_text` | text field | |
| Booking | WhatsApp Group Link | URL | No | `ggm_workshop_whatsapp_group_url` | URL field | members only on frontend |
| Form Text | Heading | text | No | `ggm_workshop_form_text_heading` | text field | |
| Form Text | Content | rich editor | No | `ggm_workshop_form_text_content` | dashboard rich editor | native KSES rules |
| Slots | Slot ID | hidden internal | N/A | slot table `id` | not visible | retained for updates |
| Slots | Type | select | Conditional | `slot_type` | type select from `GGM_Workshop_Slot::types()` | preserves unknown legacy type |
| Slots | Start/End | time | Conditional | `start_time`, `end_time` | time controls | end > start |
| Slots | Meeting Link | URL | No | `meeting_link` | URL field | |
| Slots | Enabled | toggle | No | `status` | switch | active/inactive |
| Slots | Order | drag/reorder | N/A | `sort_order` | move controls/drag handle | payload order only |
| Layout | Discover columns/rows | selects | No | `ggm_workshop_additional_block_layouts.discover` | columns/rows selects | 0/default, columns 1–8, rows 1–10 |
| Layout | Why Different columns/rows | selects | No | `...why_different` | columns/rows selects | same |
| Layout | Perfect For columns/rows | selects | No | `...perfect_for` | columns/rows selects | same |
| Discover | Heading Override | text | No | `ggm_workshop_discover_heading_override` | text field | blank = configured default |
| Discover | Items | repeater | No | `ggm_you_will_discover` JSON | heading/description/image cards | add/remove/reorder |
| Discover | Bottom Text | textarea | No | `ggm_workshop_discover_bottom_text` | textarea | |
| Why Different | Heading Override | text | No | `ggm_workshop_why_different_heading_override` | text field | |
| Why Different | Items | repeater | No | `ggm_why_different_points` JSON | title/icon cards | legacy `why_different_points` read fallback |
| Why Different | Bottom Text | textarea | No | `ggm_workshop_why_different_bottom_text` | textarea | |
| Why Workshop Different | Heading | text | No | `ggm_workshop_why_workshop_different_heading` | text field | blank uses dynamic title |
| Why Workshop Different | Intro | textarea | No | `ggm_workshop_why_workshop_different_intro` | textarea | |
| Why Workshop Different | Tick Points | repeater | No | `ggm_workshop_why_workshop_different_points` JSON | text rows | add/remove/reorder |
| Why Workshop Different | Right Image | media modal | No | `ggm_workshop_why_workshop_different_image` | preview/select/remove | canonical URL schema retained |
| Journey | Heading | text | No | `ggm_workshop_journey_heading` | text field | |
| Journey | Days | repeater | No | `ggm_workshop_journey_days` JSON | day/description/image cards | add/remove/reorder |
| Journey | Footer Boxes 1–4 | fixed repeater | No | `ggm_workshop_journey_footer_items` | icon/text controls | four canonical items |
| Perfect For | Heading Override | text | No | `ggm_workshop_perfect_for_heading_override` | text field | |
| Perfect For | Items | repeater | No | `ggm_perfect_for_you` JSON | title/description/image cards | legacy read fallback |
| Perfect For | Bottom Text | textarea | No | `ggm_workshop_perfect_for_bottom_text` | textarea | |
| FAQ | Heading Override | text | No | `ggm_workshop_faq_heading_override` | text field | |
| FAQ | Items | repeater | No | `ggm_faq` JSON | question/answer cards | legacy `faq` read fallback |
| Legacy | Workshop Short Description | preserve-only | N/A | `workshop_short_desc` | not rendered | native intentionally retired it |
| WordPress Core | Excerpt | excluded | N/A | `wp_posts.post_excerpt` | **not rendered** | preserved, never updated |

## 12. Repeater UI Specification

Each repeater uses dashboard cards with a labeled ordinal, move-up/down controls (drag enhancement optional), Add, Remove, and an inline media component where required. Rows serialize only into the existing canonical arrays/JSON meta; they are never rendered as JSON text. Remove is explicit. Hidden/disabled sections omit their payload key and preserve existing stored rows.

## 13. Time Slot UI Specification

Slots render as repeatable cards with hidden existing ID, type select, start/end time, meeting URL, enabled toggle, remove, and reorder. `GGM_Workshop_Slot::get_for_workshop()`, `types()`, `validate_range()`, `create()`, `update()`, and `delete_missing()` remain authoritative. A hidden section never submits an empty `slots` list.

## 14. Media UI Specification

Add an administrator-capability-checked WordPress media modal adapter. Attachment-ID fields (featured/bottom) store IDs; native URL-based repeater image fields retain their canonical URL storage but are selected from the media library and display previews. No base64, typed attachment ID, or visible image JSON.

## 15. Workshop Admin Configuration Compatibility

`GGM_Workshop_Admin_Config` is the one registry for section/field IDs, visibility, and order. The dashboard renderer will map this audit matrix to those IDs and consume the same normalized configuration. It will not reinterpret the option: disabled fields/sections are hidden and their existing values are preserved. The native configuration remains intact.

## 16. Native/Dashboard Data Parity

Both interfaces load the actual Workshop ID, the same meta keys, featured image, and slot rows. Dashboard creation produces `post_type=workshop`; edits use the same ID. Native legacy fallbacks remain read-compatible. No synchronization process, duplicate post, slot table, or dashboard-only metadata is permitted.

## 17. Shared Data Service

Keep `GGM_Workshop_Data_Service` as non-rendering load/normalize/validate/save code. Replace its temporary `canonical_meta` escape hatch with an explicit allowlisted structured payload. Native extraction is a separate, high-risk parity subphase: native save must delegate only after fixture parity proves identical behavior.

## 18. Shared Validation

Reuse native date range, sale-price, contribution, URL, attachment, rich content, repeater, mentor/course ID, and slot-range rules. Return keyed errors for dashboard fields. Never rely on browser validation.

## 19. Shared Sanitization

Use native-equivalent `sanitize_text_field`, `sanitize_textarea_field`, `wp_kses`/`wp_kses_post`, `esc_url_raw`, `absint`, attachment image checks, and slot normalizers. Preserve native URL-based repeater image schema and JSON encoding exactly.

## 20. Save Architecture

`Dashboard form → structured allowlisted payload → management nonce/Administrator/edit_post/type checks → workshop data service → wp_posts/wp_postmeta/featured attachment/GGM_Workshop_Slot`. No fabricated `$_POST`, no visible JSON editor, and no single JSON blob save.

## 21. Hidden Field Preservation

The renderer sends only enabled, changed fields. The service updates only keys present in the structured payload; absent keys preserve existing values. Empty repeaters explicitly submitted by enabled sections mean removal. Hidden fields/sections never mean removal.

## 22. Security

Retain authenticated AJAX only, dedicated nonce, Administrator role plus `manage_options`, `edit_post`, Workshop type verification, strict payload allowlist, validated relationship IDs, image validation, server URL/date/price checks, escaping, and no `wp_ajax_nopriv` action.

## 23. Responsive Design

Use scoped dashboard cards and one-column form sections below the dashboard breakpoint. Repeater cards stack fields rather than forcing a wide table. Slot actions remain reachable on touch devices.

## 24. Accessibility

Use associated labels, fieldsets/legends, descriptive errors linked with `aria-describedby`, keyboard-accessible move/remove buttons, visible focus, non-colour-only notices, and media previews with meaningful/removeable state.

## 25. Performance

Load mentor/course selector options in the authorized bootstrap response; load complete Workshop data only on editor open. Media modal loads on demand. Do not load all Workshops/repeaters for manager lists.

## 26. Exact Files to Modify

- `templates/dashboard/admin/workshop-editor.php` — replace Excerpt/JSON/ID fields with structured sections.
- `assets/js/ggm-dashboard-management.js` — structured rendering, media adapter, repeaters, slots, payload and errors.
- `assets/css/ggm-dashboard.css` — scoped cards, repeaters, slot/mobile/accessibility states.
- `modules/workshop/class-ggm-workshop-data-service.php` — explicit loader/normalizer/validator/save allowlist and preserve semantics.
- `modules/dashboard/class-ggm-dashboard-management.php` — enriched bootstrap selectors/media nonce, keyed errors, remove Excerpt update.
- `scripts/test-dashboard-management.php` — complete field/JSON/excerpt/security tests.

## 27. Exact Files to Add

- `templates/dashboard/admin/partials/workshop-fields.php` — server-rendered field/schema data or reusable markup helpers.
- `assets/js/ggm-dashboard-workshop-editor.js` — isolated structured Workshop editor module (if separation is cleaner than extending the management bundle).
- `scripts/test-workshop-dashboard-editor-parity.php` — focused static/fixture contract coverage.

## 28. Exact Files to Remove

No entire file needs removal. Remove only the raw JSON, manual attachment-ID, and Excerpt controls from `workshop-editor.php`, and their JS serialization branches.

## 29. Testing Matrix

| Test | Required result |
| --- | --- |
| Native create → dashboard load | every matrix field equals native value; Excerpt absent |
| Dashboard create → native load | same canonical fields/slots/repeaters visible |
| Native/dashboard alternating edits | latest values survive in both interfaces |
| Each repeater | add/edit/remove/reorder parity both directions |
| Slots | add/edit/remove/reorder parity and no accidental deletion |
| Hidden configuration | hidden data remains after unrelated saves |
| Validation | invalid date/price/URL/contribution/slot/ID rejected with field error |
| Security | bad nonce/non-admin/bad type/bad ID denied |
| Public regression | Workshop, booking, checkout, payment, access, Elementor unchanged |
| Responsive/accessibility | keyboard, mobile stacking, labels, focus, notices verified |

## 30. Implementation Phases

1. Replace raw JSON/Excerpt/manual-ID controls with editor schema and structured form foundation.
2. Build details, pricing, relationships, and media.
3. Build Booking Card and rich Form Text.
4. Build Time Slot cards.
5. Build all content repeaters/layout controls.
6. Complete service allowlist, native/dashboard fixture parity, and configuration preservation.
7. Perform security, responsive, accessibility, and public/native regression tests.

## 31. Approval Gate

This audit identifies the current dashboard editor as incomplete and unsuitable for administrators. The corrected implementation will have zero visible raw JSON Workshop controls, no Excerpt, and one shared canonical data model with the native editor.

**The complete Workshop Dashboard Editor field audit and corrected implementation plan are ready. Do you approve proceeding with the implementation?**
