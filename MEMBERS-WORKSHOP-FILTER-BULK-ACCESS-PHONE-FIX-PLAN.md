# Members Workshop Filter, Bulk Access, and Phone Integrity Plan

## Implementation status

Approved and implemented on 2026-09-11. Targeted PHP syntax checks, JavaScript syntax checks, whitespace checks, unsafe-writer searches, and phone-normalization cases passed. Live WordPress database/browser verification remains a deployment smoke test because this workspace does not include the production WordPress runtime or database.

## Requested outcome

On **DZ LMS -> Members** (`admin.php?page=ggm-lms-users`):

1. Administrators can filter members by a specific workshop.
2. Administrators can select multiple visible members and run **Assign New Access** once for the selection.
3. Search, pagination, member count, and CSV export respect the selected workshop.
4. Repeated/concatenated phone numbers stop being created, and existing malformed values are handled safely.
5. Existing per-member **View Profile** and **Assign Access** actions continue to work.

## Audit findings

### 1. Members list architecture

- `admin/views/users.php` owns the list query, count, pagination, HTML table, access modal, and its inline JavaScript.
- The unfiltered list is a union of users with workshop access, course access, `ggm_phone`, or `ggm_member` metadata.
- There is currently no workshop request parameter or workshop condition in the list query.
- Search is repeated across all three union branches. Pagination retains search but has no filter state to retain.
- `ggm-member-dashboard.php::ggm_export_members_csv()` contains a separate member query, so a filter added only to the screen would make the export disagree with the visible results.

### 2. Access assignment architecture

- The current modal and AJAX request accept one `user_id` only.
- `modules/import-export/class-ggm-access-manager.php` contains the authoritative grant methods.
- Workshop assignment already grants the selected workshop and its linked course.
- Database uniqueness is enforced by `UNIQUE KEY user_workshop (user_id, workshop_id)` and `UNIQUE KEY user_course (user_id, course_id)`, so bulk processing cannot create duplicate access rows.
- Assigning access that already exists currently refreshes the grant record and, for a course, recalculates expiry. Bulk behavior must report this as a renewal/update rather than claiming a second row was created.

### 3. Root cause of repeated phone numbers

The Members page does **not** concatenate phone values. It prints the saved `ggm_phone` metadata unchanged.

Malformed values can enter the database because phone validation is inconsistent:

- The full workshop join form accepts any digit string with `length >= 10`.
- Guest checkout accepts any digit string with at least 10 digits and then stores the complete value unchanged.
- Razorpay order/contact persistence strips non-digits but applies no maximum length before updating `ggm_phone` and `billing_phone`.
- Admin **Add Member**, dashboard profile, auth profile/signup, and REST profile writers also strip characters without one shared, authoritative length/country rule.
- The country dial code is stored separately, but the national-number writers do not consistently reject a pasted country code or an accidentally duplicated value.

This explains values such as the 19- and 20-digit strings in the screenshot: a duplicated/autofilled/pasted value was accepted upstream and persisted. The list then exposed the corrupt stored value exactly as saved.

There is also a read inconsistency:

- Members list reads only `ggm_phone`.
- Profile display prefers `billing_phone` and falls back to `ggm_phone`.
- CSV export uses SQL `MAX()` across the two values, which is a lexical choice rather than a deliberate precedence rule.

## Implementation plan

### Phase A: workshop filter

1. Add a sanitized `workshop_id` GET parameter to `admin/views/users.php`.
2. Populate a WordPress-style **All Workshops** selector from workshop and legacy `ggm_workshop` posts that can have access records.
3. When a workshop is selected, use one prepared `EXISTS`/join condition against `ggm_workshop_access` for that exact workshop. Do not let OTP-only users or unrelated course access leak into filtered results.
4. Apply name/email/phone search on top of the workshop condition.
5. Preserve the filter through search, pagination, clear/reset actions, and member totals. A filter change starts again on page 1.
6. Pass the same sanitized filter into CSV export and apply the same membership condition there.
7. Keep **All Workshops** as the default so the current unfiltered list remains unchanged.

### Phase B: bulk selection and Assign New Access

1. Add a WordPress list-table checkbox column, including **Select all on this page** in the header and footer.
2. Add a bulk toolbar button named **Assign New Access** with a live selected-member count. It remains disabled until at least one member is checked.
3. Reuse the existing access modal in two explicit modes:
   - Single-member mode keeps the current access list and removal controls.
   - Bulk mode shows the number of selected members and hides the single-member current-access list, because a merged list would be ambiguous.
4. Add a dedicated authenticated AJAX action for bulk assignment instead of overloading the single-user payload.
5. Server-side safeguards:
   - verify `ggm_admin_nonce`;
   - require `manage_options`;
   - accept integer user IDs only, remove duplicates, and cap the request to the current page size of 20;
   - verify every user and the selected workshop/course post type;
   - use `GGM_Access_Manager::grant_course()` or `grant_workshop_bundle()` for every member;
   - never construct access SQL from unchecked request values.
6. Return a structured summary: newly assigned, renewed/updated, and failed. Display it in the modal, then refresh the filtered page.
7. Keep selection deliberately limited to the current 20-row page. This avoids an accidental access grant to thousands of members hidden across pagination.

### Phase C: canonical phone handling

1. Add one shared member-phone normalizer/validator in the common helpers layer and use it for both display and writes.
2. Treat the phone input as a national number because the country dial code is already a separate field:
   - for `+91`, accept a valid 10-digit national number and normalize supported `0`/`91` prefixes;
   - for other dial codes, enforce the international E.164 total limit of 15 digits after combining dial code and national number;
   - reject overlong, undersized, or empty normalized values on required flows;
   - never silently save an arbitrary `>= 10` digit string.
3. Apply the server-side rule to every current member-phone writer: guest checkout, payment contact persistence, admin Add Member, dashboard/auth profile updates, signup, REST profile update, and health intake. Client-side limits/errors will mirror the rule but will not replace server validation.
4. Standardize reads to explicit precedence: valid `ggm_phone`, then valid `billing_phone`; use the same helper for Members, profile-related output touched by this work, search/export, and future writes.
5. Existing-data repair will be conservative and recoverable:
   - identify values exceeding the valid limit;
   - automatically repair only deterministic Indian cases where the stored value starts with one valid 10-digit mobile number and has trailing duplicated/junk digits;
   - copy the original value to a timestamped backup user-meta key before changing `ggm_phone`/`billing_phone`;
   - flag ambiguous values for manual review rather than guessing;
   - report repaired and skipped counts to the administrator.

## Files expected to change after approval

- `admin/views/users.php` - filter UI/query state, checkbox column, bulk modal mode, and client interaction.
- `modules/import-export/class-ggm-access-manager.php` - bulk AJAX registration/handler and access result accounting.
- `ggm-member-dashboard.php` - workshop-aware CSV export using the same filter semantics.
- `includes/class-ggm-helpers.php` - canonical phone validation/read helper and reversible legacy repair support.
- The existing phone-writing controllers listed in Phase C, only where needed to call the shared helper.
- A focused migration/version hook only if required for the approved existing-data repair; it will be idempotent and preserve backups.

## Verification plan

### Static and syntax checks

- Run `php -l` on every changed PHP file.
- Search for remaining member-phone writes that bypass the shared normalizer.
- Verify every dynamic workshop/user value is prepared or escaped.
- Verify no changes affect unrelated admin pages or front-end shortcode layouts.

### Workshop filter tests

- All Workshops reproduces the current member set.
- Workshop A shows only users with Workshop A access.
- A member with only a linked course, another workshop, or OTP metadata is excluded.
- Search + workshop filter returns their intersection.
- Pagination and Clear retain/reset the correct parameters.
- CSV row membership matches the filtered screen.
- Empty workshop results render cleanly.

### Bulk access tests

- Select one, several, and all 20 visible rows.
- Header/footer select-all and indeterminate states work.
- Assign a workshop with and without a linked course.
- Assign a course with finite and lifetime access.
- Reassign existing access and verify renewal is reported without duplicate rows.
- Include a deleted/invalid user in a forged request and verify it fails safely.
- Verify non-admin and invalid-nonce requests are rejected.
- Confirm single-member assignment/removal still works unchanged.

### Phone tests

- Valid Indian national, `0`-prefixed, and `+91`-prefixed inputs normalize to one 10-digit stored value.
- A 19/20-digit concatenated value is rejected on every new write path.
- Valid non-Indian inputs stay compatible with their selected dial code and E.164 length.
- Members list, profile, search, and CSV show the same canonical value.
- Deterministic legacy repair creates a backup before updating both metadata keys.
- Ambiguous legacy values are reported and left untouched.

## Acceptance criteria

- Workshop filtering is exact and remains active through search, paging, and export.
- Bulk assignment works for checked members on the current page and produces no duplicate access rows.
- Workshop bundles still grant linked-course access.
- No current phone-entry path can store an overlong concatenated number.
- Existing phone repairs are reversible and ambiguous records are never guessed.
- Existing single-member actions and the default unfiltered Members page remain compatible.
