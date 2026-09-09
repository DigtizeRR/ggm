# Workshop WhatsApp, Health Information, Additional Details, and Payment-First Forms

## End-to-End Audit and Implementation Plan

Status: **Approved and implemented on 2026-08-25. Runtime verification on a WordPress staging site is still required.**

Follow-up: the payment-first gate heading and description are editable in Form Settings; the existing payment Button label controls its CTA text.

Follow-up: every File Upload field has an **Allow the user to view and download uploaded files** checkbox. It defaults off, applies to existing files with the same field key when the form is updated, and is enforced both when rendering the member file list and when authorizing the file request; administrators retain access.

Follow-up: the Health Information tab was rebuilt to the approved reference structure: a full-width registration summary with a dedicated edit/review panel, a medical-report card that hosts the real additional-field upload form and authorized file list, workshop-scoped WhatsApp access, and a responsive support card using the site administrator email.

Date: 2026-08-25

## 1. Requested outcomes

1. Add a workshop-level WhatsApp group URL in the workshop edit screen and show a **Join WhatsApp Group** button on an accessible workshop's dashboard card only when that URL exists.
2. Make post-submission/additional fields durable: validate required values, remember that they were completed, do not ask for them again after refresh, let members view their uploaded files, and add a builder option controlling whether additional details may be submitted again.
3. Add an optional payment-first form flow: payment occurs before form fields are revealed; after verified payment, the member fills and submits the form.
4. Replace the Health Information experience with three clear cards: Registration Complete, Upload Medical Reports (Optional), and Join the WhatsApp Group. Registration remains editable until an administrator reviews it.

## 2. Current architecture audited

### Workshop administration and dashboard

- `includes/class-ggm-meta-boxes.php` renders and saves native workshop fields.
- `modules/dashboard/class-ggm-dashboard.php` builds the workshop JSON returned to logged-in members.
- `assets/js/ggm-dashboard.js` renders workshop cards and their actions.
- Workshop access is checked server-side through `GGM_Workshop::user_has_access()` / `ggm_user_has_workshop_access()`.
- There is currently no workshop-level WhatsApp group metadata in the workshop meta box or dashboard payload.

### Health forms and submissions

- `templates/dashboard/tab-health.php` currently renders every published form assigned to the dashboard.
- `modules/workshop/class-ggm-form-builder.php` owns form settings, schema versions, rendering, validation, submission, post-submit fields, private uploads, and form payments.
- `admin/views/health-forms.php` owns the form builder and the submitted-forms administration screen.
- Form data is stored in `ggm_forms`, `ggm_form_versions`, `ggm_form_assignments`, `ggm_form_submissions`, `ggm_form_files`, and `ggm_form_payments`.
- `includes/class-ggm-database.php` creates/upgrades those tables via the plugin database version.
- `modules/workshop/class-ggm-health-intake.php` is a legacy user-meta health intake. The dashboard now uses the versioned form builder, but legacy submissions and files remain available to administrators.

### Existing additional-field behavior

- A builder field can be marked `show_after_submit`.
- These fields are excluded from the initial submission, then shown under **Submit additional details**.
- The AJAX update endpoint validates required additional values and uploads before saving.
- The submission record does not store a durable `additional_completed_at` state or equivalent.
- On refresh, the renderer sees a submitted main form and renders the additional-field disclosure again regardless of whether those fields were previously completed.
- Existing answers do not reliably distinguish “never completed” from a valid empty optional value.
- File rows exist in `ggm_form_files`, but the download/preview controller requires `manage_options`; members cannot see their own uploaded files.

### Existing payment behavior

- `payment_enabled` replaces the final submit button with **Pay & Submit**.
- The current request payload is fully validated and stored as a `pending_payment` submission before the Razorpay order is created.
- Successful signature verification changes the submission to `submitted`.
- Therefore this is payment-at-the-end, not payment-before-fields.
- The payment table currently requires a `submission_id`, so payment-first must still create a controlled placeholder submission or introduce a separate payment session. Reusing a placeholder submission is the smaller migration and keeps existing reporting intact.

### Existing review/edit behavior

- Submitted form rows use `status='submitted'`; there is no reviewed timestamp, reviewer ID, review action, or unreview action.
- Members cannot edit the main submitted answers.
- The existing builder `allow_multiple` option controls creation of separate complete submissions; it is not suitable for editing one health registration.

## 3. Confirmed defects and design gaps

### High priority

1. **Additional completion is not persisted.** Required additional fields are validated during the AJAX call, but a refresh reopens the same workflow.
2. **Members cannot access their own files.** The only form-file endpoint is administrator-only.
3. **No review state exists.** The requested edit-until-reviewed behavior cannot be implemented safely as a visual-only toggle.
4. **Current paid forms collect answers before payment.** A separate state transition is required to reveal fields only after verified payment.

### Security and integrity risks to address during implementation

- Every file view/download must authorize either an administrator or the owning logged-in member; a file ID and nonce alone are not sufficient ownership checks.
- WhatsApp URLs must be sanitized on save, escaped on output, opened with `target="_blank"`, and use `rel="noopener noreferrer"`.
- Review/edit/update actions require capability checks, nonces, ownership checks, context checks, schema-snapshot validation, and server-side validation.
- Payment verification must be idempotent so a retry cannot charge twice or create multiple unlocked form sessions.
- A client-side “paid” flag must never unlock fields; only a server-verified successful payment may do so.
- Private files must continue to be served through PHP with no-cache, `nosniff`, safe inline MIME restrictions, and a safe filename.

## 4. Proposed data model and migration

Raise `GGM_DB_VERSION` and update `ggm_form_submissions` with:

- `additional_completed_at datetime NULL` — durable completion marker for post-submit fields.
- `reviewed_at datetime NULL` — review lock timestamp.
- `reviewed_by bigint(20) UNSIGNED NULL` — administrator who reviewed the registration.

Use submission statuses as an explicit state machine:

- `payment_pending` (or retain the compatible `pending_payment`) — order created, not paid.
- `paid_awaiting_submission` — payment verified; form fields may be shown.
- `submitted` — form data validated and completed.

Migration/backfill rules:

- Existing `submitted` rows remain submitted and unreviewed.
- Existing successful form payments remain valid.
- For existing submissions with after-submit fields, infer completion only when every required after-submit answer/file is present. Optional-only historical additional sections remain incomplete unless evidence of an additional save exists; this avoids falsely hiding an unfinished task.
- Add indexes only if query profiling shows a need; likely candidates are `(form_id,user_id,context_type,context_id,status)` and review state.
- Do not alter or delete legacy `ggm_health_intakes` user-meta data or legacy private reports.

## 5. Detailed implementation plan

### Phase A — Workshop WhatsApp configuration and workshop-card CTA

1. Add a **WhatsApp Group Link** URL field to the workshop details meta box.
2. Save it as dedicated post meta (proposed key: `ggm_workshop_whatsapp_group_url`) using `esc_url_raw()`; delete/blank the meta when the input is empty.
3. Include the sanitized URL in the workshop payload only for users who have workshop access.
4. In `workshopCard()`, render **Join WhatsApp Group** as a separate secondary action when:
   - `w.has_access` is true; and
   - the URL is non-empty and valid.
5. Do not render an empty, disabled, or placeholder button when no URL is configured.
6. Preserve existing View Details, purchase, and Start Learning behavior.
7. Consider adding the meta key to workshop import/export so copied workshops retain the group link; audit the import/export whitelist during implementation.

### Phase B — Durable additional-details completion

1. Add a form-level builder setting: **Allow members to submit/update additional details more than once** (proposed key: `allow_additional_resubmission`, default false).
2. Keep this distinct from:
   - `allow_multiple`, which creates multiple complete form submissions; and
   - a file field's `max_files`, which controls files in one save.
3. When additional details save successfully:
   - validate every required post-submit field on the server;
   - validate required file fields against both newly uploaded files and already stored files;
   - store answers/files atomically as far as WordPress permits, rolling back newly stored files on failure;
   - set `additional_completed_at` only after all required data and files succeed.
4. On refresh:
   - if incomplete, show the additional-details action;
   - if complete and repeat/update is disabled, show a completion message and uploaded-file list, not the form;
   - if complete and repeat/update is enabled, show an **Add / Update Additional Details** action plus existing values/files.
5. Avoid duplicate files caused by AJAX retries by using the submission/field/file record as the server-side source of truth.
6. Return the durable completion state in AJAX responses so the UI updates immediately without relying on a refresh.

### Phase C — Member-visible uploaded files

1. Extend the form-file controller to support two authorization paths:
   - administrators with `manage_options`; or
   - the logged-in owner of the parent submission.
2. Generate purpose-specific nonces for member preview/download links.
3. Render a member file list grouped by field label, with original filename, upload date, Preview (safe PDFs/images only), and Download.
4. Show files in the Upload Medical Reports card and after completed additional details.
5. Do not expose storage paths or direct private-upload URLs.
6. Decide file replacement behavior as follows:
   - default: retain previous reports and append new reports up to the configured workflow limit;
   - when editing the main registration, existing files remain unless the member explicitly removes them;
   - reviewed submissions cannot add, remove, or replace files.

### Phase D — Payment-first form mode

1. Replace the single payment checkbox with a backward-compatible payment flow selector:
   - **Pay when submitting form** — current behavior and default for existing paid forms.
   - **Pay before showing form fields** — new behavior.
2. Save a new setting (proposed key: `payment_flow`) while retaining `payment_enabled`, amount, currency, labels, description, and success message.
3. For payment-first rendering:
   - initially render only the form title/description, amount, and payment CTA;
   - create a minimal placeholder submission with no answers and status `pending_payment`;
   - create the Razorpay order/payment row linked to that placeholder;
   - after verified payment, change the placeholder to `paid_awaiting_submission` and return the server-authorized form HTML/unlock response;
   - validate and save answers/files into that same submission, then change it to `submitted`.
4. On refresh, a logged-in member with a successful `paid_awaiting_submission` row resumes directly at the form without paying again.
5. If a completed `submitted` row exists and multiple submissions are disabled, show the completed state.
6. Clean up or expire abandoned unpaid placeholder rows without deleting successful payment history.
7. Make create/verify/final-submit handlers idempotent and reject amount, currency, form version, user, context, order, or status mismatches.
8. For guest-enabled payment-first forms, use a short-lived cryptographically random session token tied to the placeholder; never rely only on `user_id=0`. If this cannot be made reliable with the current page context, disable guest payment-first in the builder with an explicit admin explanation.
9. Continue using the configured Razorpay mode and credentials; no new gateway is introduced.

### Phase E — Health Information three-card experience

Refactor `templates/dashboard/tab-health.php` and supporting form-builder helpers into a state-driven health summary.

#### Box 1: Registration Complete

Copy:

> Your registration has been submitted successfully. You can edit your details until they have been reviewed by our team.

- Before initial submission, show the form completion CTA/state rather than falsely showing “Registration Complete.”
- After submission and before review, show **Edit Submitted Details**.
- After review, disable the edit action and display **Reviewed by GGM Team**.
- Editing updates the existing submission rather than creating a second row.
- Use the saved schema snapshot so old submissions remain editable even after the form definition changes.
- Revalidate all answers and files server-side on edit.

#### Box 2: Upload Medical Reports (Optional)

Copy:

> Upload any recent medical reports to help Dr. Dhiren better understand your health and offer more personalised guidance.

- Show **Upload Reports**.
- Use the form builder's post-submit file field(s), not the legacy intake upload UI.
- Show existing uploaded files to the member with authorized Preview/Download actions.
- Respect the builder's additional-resubmission setting and the reviewed lock.
- If no post-submit file field exists, show a clear administrator configuration warning only in wp-admin; the member UI should not render a broken upload action.

#### Box 3: Join the WhatsApp Group

Copy:

> All important updates, session links and group coaching communication will be shared here. Please join the group so you don’t miss anything.

- Show **Join WhatsApp Group** using the workshop-level URL from Phase A.
- Proposed deterministic selection when several workshops are accessible: use the nearest current/upcoming accessible workshop with a configured link; if dates tie, use the newest access grant.
- Do not render the CTA when no eligible workshop link exists. Prefer hiding the whole third box rather than showing a dead action.
- The workshop card still shows its own workshop-specific button, so members with several workshops can reach every configured group.

### Phase F — Administrator review workflow

1. Add review state and actions to Submitted Forms:
   - **Mark Reviewed** for unreviewed submissions;
   - reviewed badge, reviewer, and reviewed timestamp;
   - optional **Reopen for Editing** action for corrections.
2. Register nonce-protected `admin-post.php` handlers restricted to `manage_options`.
3. The review lock must be checked server-side by every member edit/additional-upload endpoint, not only by disabling a button.
4. Keep review independent from submission `status` so existing queries for `status='submitted'` continue to work.
5. Include review state in search/filter display and preserve filters/pagination after admin actions.

### Phase G — Styling, accessibility, and copy

1. Add responsive styles for the three health cards and workshop WhatsApp action using existing dashboard design tokens/classes where possible.
2. Maintain keyboard focus, visible focus styles, semantic headings, `aria-live` feedback, and disabled-state semantics.
3. Escape plain text, allow only intended rich confirmation HTML, and localize every user-facing string.
4. Avoid placing sensitive health answers or filenames in JavaScript-localized global data.

## 6. Files expected to change

- `ggm-member-dashboard.php` — database version bump if schema changes.
- `includes/class-ggm-database.php` — submission review/additional-completion columns and migration.
- `includes/class-ggm-meta-boxes.php` — workshop WhatsApp field and save logic.
- `modules/dashboard/class-ggm-dashboard.php` — access-scoped workshop WhatsApp payload and health summary data if needed.
- `assets/js/ggm-dashboard.js` — workshop CTA and any health-card interactions.
- `assets/css/ggm-dashboard.css` — responsive card/action styling.
- `modules/workshop/class-ggm-form-builder.php` — state machine, payment-first mode, durable additional completion, edit endpoints, member file authorization/listing, and review enforcement.
- `admin/views/health-forms.php` — builder settings, submission review controls/status, and admin notices.
- `templates/dashboard/tab-health.php` — three-card state-driven Health Information UI.
- `modules/import-export/class-ggm-import-export.php` — only if the workshop meta whitelist requires an explicit WhatsApp key.
- `uninstall.php` — only if new standalone options/meta need cleanup; table columns require no separate uninstall entry.

## 7. Acceptance criteria

### Workshop WhatsApp

- Saving a valid group URL on Workshop Edit persists it.
- Accessible workshop cards show the correct link.
- Locked/unpurchased workshops never leak the group URL.
- Blank/invalid links produce no button.
- Existing workshop actions still work.

### Additional details and files

- Required text, selection, and file fields cannot be bypassed client-side or by direct AJAX.
- A successful additional save remains complete after refresh/login/logout.
- A failed partial upload does not mark completion.
- Repeat/update behavior follows the new builder setting.
- Members can preview/download only their own files; another member receives 403/404.
- Administrators retain preview/download access.

### Payment-first

- Fields are absent/locked before verified payment.
- A forged client response cannot unlock the form.
- Successful payment reveals the form and survives refresh.
- Final form submission does not charge again.
- Failed/cancelled payment leaves no submitted response.
- Duplicate callbacks/retries do not create duplicate successful payments or submissions.
- Existing paid forms remain on the current Pay & Submit flow until an administrator selects payment-first.

### Health cards and review

- Box 1 accurately reflects not submitted, submitted/editable, and reviewed/locked states.
- Editing changes the existing registration and is blocked server-side after review.
- Box 2 uploads and lists reports without exposing private URLs.
- Box 3 uses the correct workshop group URL and has no dead button.
- Mark Reviewed and Reopen actions are permission- and nonce-protected.
- Mobile, keyboard, and screen-reader behavior remains usable.

## 8. Verification plan

1. Run PHP syntax checks on every changed PHP file.
2. Run available project lint/tests; if no automated suite exists, document that gap.
3. Test database upgrade on a copy containing old submissions and payments.
4. Test roles: administrator, owning member, different member, logged-out guest.
5. Test contexts: dashboard, workshop-specific, shortcode, Elementor popup, and course assignment where applicable.
6. Test form modes: free, existing Pay & Submit, new payment-first, single submission, multiple submissions, additional repeat disabled/enabled.
7. Test files: no file, required file, multiple files, oversize, invalid MIME/extension, interrupted upload, safe preview, forced download, unauthorized file ID.
8. Test payment: success, cancel, failure, invalid signature, duplicate verify, refresh after payment, refresh before final submission, stale form version, abandoned order.
9. Test review: edit before review, concurrent review/edit attempt, edit after review, reopen, and audit display.
10. Test multiple accessible workshops and deterministic WhatsApp selection.

## 9. Recommended implementation order

1. Database migration and submission state helpers.
2. Workshop WhatsApp admin field, save logic, and access-scoped payload.
3. Durable additional completion and member file authorization/listing.
4. Registration edit and administrator review lock.
5. Three-card Health Information UI.
6. Payment-first state machine and resume behavior.
7. Import/export compatibility, styling, accessibility, and regression testing.

## 10. Approval assumptions

Approval of this plan will also approve these implementation choices:

1. The additional-repeat option is **form-level**, because all post-submit fields are saved and validated as one additional-details transaction. File `max_files` remains a separate per-field setting.
2. Review applies to a **submission**, and an administrator may reopen it.
3. Editing updates the existing submission rather than creating submission history versions.
4. If multiple WhatsApp groups qualify in the Health tab, Box 3 uses the nearest current/upcoming accessible workshop; individual workshop cards expose the others.
5. Box 3 is hidden when no group URL is configured.
6. Existing paid forms retain their current payment-at-submit behavior unless explicitly switched to payment-first.

Owner approval was received and implementation proceeded from this document.

## 11. Follow-up audit — reports falsely succeed, reopen after refresh, and do not list

### Reproduction and root cause

The completed-registration renderer builds its post-submit controls from the form's **current published schema**, while `update_after_submit_fields()` was reading only `schema_snapshot_json` from the original submission. A registration created before the Medical Reports field was changed to **Show this field after form submit** therefore produced two conflicting states:

1. the current schema rendered the Medical Reports uploader;
2. the older submission snapshot contained no post-submit fields;
3. the AJAX endpoint returned a successful `No additional details are required` response before validating or storing the selected file;
4. no row was inserted into `ggm_form_files` and `additional_completed_at` was not written;
5. refresh rendered the uploader again, while Uploaded Reports correctly had no stored row to display.

This is a data-contract defect, not only a Health-tab presentation defect. A successful response must never be sent unless the same effective field definition shown to the member was validated and persisted.

### Remediation plan

1. Add one server-side resolver for a submission's effective post-submit schema:
   - prefer the current published form schema so builder changes intentionally apply to existing registrations;
   - fall back to the immutable submission snapshot if the form/current version is unavailable;
   - normalize both paths before extracting post-submit fields.
2. Use that resolver in the additional-details AJAX endpoint, aligning save behavior with refresh rendering.
3. Reject an upload request when no effective post-submit fields exist instead of returning a misleading success.
4. Treat the operation as successful only after answers, every file row, and `additional_completed_at` have been persisted; check the completion update result.
5. Return refreshed member-file markup from the successful endpoint and replace/update the Uploaded Reports card immediately; reload as a safe fallback so the durable server state is authoritative.
6. Preserve the builder's visibility control: only fields with `show_uploaded_files_to_user` render for the owner, while administrators retain access.
7. Preserve repeat behavior: the uploader stays available after completion only when `allow_additional_resubmission` is enabled and the submission is not reviewed.

### Regression and recovery scrutiny

- Existing submissions whose snapshot predates the post-submit setting must accept and retain new report uploads.
- A response cannot claim success if no file row was written or the completion marker failed.
- Refresh after a successful save must show `Additional details saved` (or retain the uploader only when repeat submission is enabled).
- Uploaded Reports must list the owning member's files when the field visibility option is enabled.
- Files remain inaccessible to another member, and Preview/Download nonce and ownership checks remain enforced.
- Reports affected by the old false-success path were never stored, so they cannot be reconstructed; the member must upload them once after this repair.

Implementation of this follow-up was requested explicitly after the audit and plan update.
