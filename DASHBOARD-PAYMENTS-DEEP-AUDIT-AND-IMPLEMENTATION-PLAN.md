# Dashboard Payments — Deep Audit and Implementation Plan

**Audit date:** 2026-09-14  
**Scope:** read-only source, repository, static-test, and reachable-update-endpoint audit. No production PHP/JS/CSS, database records, queries, or deployment artefacts were changed.

## 1. Executive summary

### Confirmed root cause

**A — deployment/version/source mismatch (confirmed for the available evidence).** The `/dashboard/` payment-management implementation found in this workspace is uncommitted local work. It is absent from `HEAD` and `origin/main` (`2713fbc`), whose plugin bootstrap is version `1.2.7` and does not load `GGM_Dashboard_Management`, `GGM_Payment_Admin_Service`, the Administrator Dashboard templates, or the payments JavaScript.

The local working copy advertises version `1.3.8` and adds those files, but its configured update ZIP endpoint, `https://digtize.com/plugins/ggm/ggm-member-dashboard.zip`, returned HTTP 404 during this audit. Therefore the live plugin path, installed version, loaded files, asset URLs, and AJAX handler cannot be verified from this workspace. The current live symptom cannot be attributed to the local candidate code without that deployment evidence.

The local candidate query is a `UNION ALL` of `{$wpdb->prefix}ggm_payments` and `{$wpdb->prefix}ggm_form_payments`; on one WordPress request it cannot return only form records if the same request can see approximately 567 purchase records in `ggm_payments`. Thus it does not explain the reported live `17` total. The live site is executing different code, a different handler, a different database/prefix, or a stale asset/response. Of those, the source/deployment mismatch is proven; the others remain unverified.

### Required approval gate

Do **not** implement or deploy until the live runtime fingerprint and database evidence listed in this document are collected. The smallest safe correction is to deploy one verified release containing the existing shared payment-query service, then prove parity against the native page; it is not to raise a limit or hard-code a count.

## 2. Current symptom

Reported live Dashboard state: `Showing 1–17 of 17 payments`; rows are `source=form`, IDs descend from 17, and some display `User #0`. Reported native page `/wp-admin/admin.php?page=ggm-lms-payments` shows approximately 567 payments. No live request/response capture, WordPress install, `wp-config.php`, database credential, or read-only database connection is present in this workspace, so the numerical source counts below are intentionally not invented.

## 3. Evidence collected

| Evidence | Result |
|---|---|
| Git `HEAD` / `origin/main` | `2713fbc`; main file version `1.2.7` |
| Working main file | version `1.3.8`; modified, not committed |
| Dashboard payments service | untracked: `modules/payment/class-ggm-payment-admin-service.php` |
| Dashboard AJAX handler | untracked: `modules/dashboard/class-ggm-dashboard-management.php` |
| Dashboard template and JS | untracked: `templates/dashboard/admin/payments.php`, `assets/js/ggm-dashboard-payments.js` |
| `HEAD` contents | none of the preceding Dashboard-payment files are tracked or loaded |
| Configured ZIP endpoint | HTTP 404; manifest request could not complete (connection closed) |
| Static tests | Dashboard payments and authorization scripts pass against the working tree |
| PHP syntax | service, handler, and native payment view parse successfully |

## 4. Architecture map

### Native Admin

`GGM_Admin::render_payments_page` (`admin/class-ggm-admin.php`) → `admin/views/payments.php` → `ggm_lms_payment_load_rows()` → two table queries → PHP merge/sort/filter/paginate → HTML.

Menu registration is `add_submenu_page(..., 'manage_options', 'ggm-lms-payments', array($this, 'render_payments_page'))` in `admin/class-ggm-admin.php`.

### Dashboard candidate (local only)

`templates/dashboard/layout.php` tab `admin-payments` → `templates/dashboard/admin/payments.php` → click handler in `assets/js/ggm-dashboard-payments.js` → POST to `admin-ajax.php` with action `ggm_dashboard_management_payments` → `GGM_Dashboard_Management::ajax_payments()` → `GGM_Payment_Admin_Service::query()` → JSON → JS renderer.

The working bootstrap includes and initializes this candidate only in modified/untracked working-tree files. `HEAD` does not.

## 5. Database/table audit

The source schema in `includes/class-ggm-database.php` defines these canonical payment sources:

| Source | Table | Primary key | Customer | Monetary/gateway fields | Status/date | Indexes declared |
|---|---|---|---|---|---|---|
| Purchase payment | `{$wpdb->prefix}ggm_payments` | `id` | `user_id` | `original_amount`, `discount_amount`, `credit_amount`, `amount`, `currency`, Razorpay order/payment/signature | enum `pending/success/failed/refunded`; `created_at` | primary `id`, `user_id`, `status` |
| Form payment | `{$wpdb->prefix}ggm_form_payments` | `id` | `user_id` (zero permitted) | `payment_key`, `label`, `description`, `amount`, `currency`, Razorpay order/payment/signature | varchar status; `created_at`, `updated_at` | primary `id`, `form_id`, `submission_id`, `user_id`, `razorpay_order_id`, `status` |
| Form context | `{$wpdb->prefix}ggm_form_submissions` | `id` | `user_id` (zero permitted) | `answers_json`, schema snapshot | submission status / timestamps | `form_id`, `user_id`, context, review state, submitted time |

No third payment table was found by a complete repository text search. Payment creation uses `GGM_Payment::create()` for purchases and `GGM_Form_Builder::create_form_payment()` for forms. Refund operations deliberately apply only to `ggm_payments`.

### Source-by-source count matrix

| Source | Record count | Used by Native | Used by local Dashboard candidate |
|---|---:|---|---|
| `ggm_payments` | **Not observable** | Yes | Yes, union branch `purchase` |
| `ggm_form_payments` | **Not observable** | Yes | Yes, union branch `form` |
| Combined | **Not observable** | Yes, PHP merged | Yes, SQL `UNION ALL` |

Live read-only evidence needed:

```sql
SELECT COUNT(*) total, status FROM wp_ggm_payments GROUP BY status;
SELECT COUNT(*) total, status FROM wp_ggm_form_payments GROUP BY status;
SELECT MIN(id), MAX(id), COUNT(*) FROM wp_ggm_form_payments;
```

Replace `wp_` with the actual `$wpdb->prefix`; execute only against the confirmed live database.

## 6. Native Admin payment audit

`ggm_lms_payment_load_rows()` issues two independent selects:

```sql
SELECT 'purchase' AS source, p.*, u.display_name, u.user_email,
       NULL AS form_id, NULL AS form_title, NULL AS submission_id,
       NULL AS form_label, NULL AS form_description
FROM {$wpdb->prefix}ggm_payments p
LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
ORDER BY p.created_at DESC;

SELECT 'form' AS source, fp.id, fp.user_id, ... fp.razorpay_order_id,
       fp.razorpay_payment_id, fp.razorpay_signature, fp.amount,
       fp.currency, fp.status, fp.created_at, u.display_name, u.user_email,
       fp.form_id, f.title AS form_title, fp.submission_id,
       fp.label AS form_label, fp.description AS form_description
FROM {$wpdb->prefix}ggm_form_payments fp
LEFT JOIN {$wpdb->users} u ON fp.user_id = u.ID
LEFT JOIN {$wpdb->prefix}ggm_forms f ON f.id = fp.form_id
ORDER BY fp.created_at DESC;
```

It then concatenates, enriches form phones from the linked submission if necessary, sorts all rows by `created_at DESC`, applies status/search in PHP, counts with `count($filtered_payments)`, and slices 25 rows per page. The native page has **no SQL COUNT**, SQL `WHERE`, SQL `LIMIT`, or SQL `OFFSET`; it loads every row. This explains why it can see a combined total near 567, but is unsuitable at large scale.

Native search includes ID, name, email, phone, order ID, gateway payment ID, source, form title/label, workshop title, and course title. Its displayed guest fallback is `Guest` when `user_id` is zero.

## 7. Dashboard payment audit (local candidate)

The candidate accepts `page`, `search`, and `status`; `per_page` is fixed at 20. Exact request schema:

```text
POST {admin_url}/admin-ajax.php
action=ggm_dashboard_management_payments
nonce={wp_create_nonce('ggm_dashboard_management')}
page={1-based}
search={string}
status={pending|success|failed|refunded|empty}
```

`GGM_Dashboard_Management::ajax_payments()` authorizes, then calls `GGM_Payment_Admin_Service::query()`. Its source query is:

```sql
SELECT 'purchase' source, p.id, ... FROM {$wpdb->prefix}ggm_payments p
UNION ALL
SELECT 'form' source, fp.id, ...
FROM {$wpdb->prefix}ggm_form_payments fp
LEFT JOIN {$wpdb->prefix}ggm_forms f ON f.id = fp.form_id
```

The exact count shape is `SELECT COUNT(*) FROM ({union}) payments LEFT JOIN {$wpdb->users} u ON u.ID=payments.user_id WHERE ...`. The data select uses the same union and where predicate, adds user and phone joins, then `ORDER BY payments.created_at DESC LIMIT %d OFFSET %d`. Search is server-side against numeric ID, Razorpay order ID, Razorpay payment ID, user display name/email, and `ggm_phone`/`billing_phone` user meta. Status accepts only `pending`, `success`, `failed`, and `refunded`; empty means all.

The success response has `items`, `total`, `page`, `pages`, `per_page`, `status`, and `search`. The JS prints `total` verbatim in `Showing … of {total} payments`; it does not calculate 17 from the item list and does not impose a limit. It renders server failure as `Request failed.`; an empty successful result currently renders an empty table rather than an explicit “No payments found.” message.

## 8. 567 → 17 discrepancy trace

| Layer | Expected/actual count | Evidence |
|---|---:|---|
| Native source `ggm_payments` | Not observable; reported dominant source | Native query reads it; user reports ~567 native total |
| Native source `ggm_form_payments` | Not observable; reported candidate is 17 | Native query reads it; live Dashboard IDs/form labels are consistent with it |
| Native merged count | ~567 (reported) | User observation; no live capture available |
| Local Dashboard service COUNT | Not executed against live DB | Source proves combined `UNION ALL` |
| Local Dashboard SELECT | Not executed against live DB | Source proves same combined dataset + page 20 |
| Live AJAX JSON `total` | Not captured | Required diagnostic evidence |
| JavaScript received total | Not captured | JS displays server `d.total` unchanged |
| Live UI displayed total | 17 (reported) | User observation |

**Conclusion:** 17 is not caused by a candidate-code `LIMIT 17`, client-side truncation, or a count/select mismatch. Given a common WordPress `$wpdb`, the local candidate would include both sources. The location where 567 becomes 17 is the live, unverified handler/data connection/deployed asset boundary.

## 9. Form records and `User #0`

Form payment IDs are independent primary keys; their sequence can legitimately be 1–17 regardless of purchase IDs in the hundreds. Form builder code explicitly supports guest payment creation: unauthenticated paid forms store `user_id=0`, create a guest-token hash in `ggm_form_submissions`, and verify it before marking payment success. Therefore `user_id=0` is valid guest-form data, not proof of corruption.

The candidate JS fallback is `p.display_name || ('User #' + p.user_id)`, which converts a valid guest into **`User #0`**. This is a confirmed normalization/presentation defect in the candidate code (**M**), separate from the count discrepancy. Native Admin instead renders `Guest` for zero. Form customer fields may exist only in `answers_json`; candidate code does not inspect them, unlike native Admin's form-submission phone fallback.

## 10. Schema parity matrix

| Field | Native | Candidate Dashboard | Same? |
|---|---|---|---|
| ID/source | merged `purchase`/`form` rows | SQL union aliases same | Yes |
| Customer | WP user; `Guest` for zero | WP user; `User #0` for zero | No |
| Email | WP user email | WP user email | Yes where user exists |
| Phone | user meta then form submission fallback | user-meta joins only | No |
| Amount/currency | both source schemas | both source schemas | Yes |
| Order/gateway IDs | both source schemas | both source schemas | Yes |
| Status/date | both source schemas | both source schemas | Yes structurally |
| Item/form detail | titles and submission answers/files | item label only | No |
| Refund | purchase only | purchase only | Yes |

## 11. Security, performance, and error handling

Candidate server boundary requires logged-in user, `manage_options`, and an `administrator` role; it checks the management nonce. There is no `nopriv` registration. Refund additionally verifies a per-payment nonce and the service only updates successful purchase payments. The payment IDs are not a cross-source collision issue for details because the DOM key includes `source`, but refund payload lacks source and is safely limited to purchases.

Performance is improved over native by server pagination, but not ready for 50k–500k rows: both tables lack `created_at` indexes; union/count search with `CAST(id AS CHAR)`, wildcard LIKE, correlated usermeta EXISTS, and duplicated usermeta joins will be expensive. The two meta joins can multiply rows when duplicate phone metadata exists, causing count/select disagreement. This is a P1/P2 design risk to address only after the deployment gate and with `EXPLAIN` on production-like data. Do not load all payments into JavaScript.

SQL/AJAX failure is not separately identified from a generic request failure; empty success lacks an explicit empty state. Nonce/authorization responses are server errors and are not misreported as 17 by the candidate JS.

## 12. Deployment and database verification gate

Before code changes, a live administrator must collect read-only evidence:

1. Plugin basename/path and active plugin version from WP Admin → Plugins, plus `GGM_PLUGIN_FILE`, `GGM_PLUGIN_DIR`, `GGM_VERSION`, and `GGM_DB_VERSION` from a temporary read-only diagnostic or WP-CLI.
2. Checksums/timestamps of main plugin file, payment service, management handler, payments template, and payments JS; browser Network asset URL/version.
3. Browser Network capture of the Dashboard AJAX request and complete JSON response (redact nonce); confirm action, hostname, `total`, item source distribution, and `per_page`.
4. On the same request, log/read `$wpdb->dbname` and `$wpdb->prefix`; run source counts and status breakdowns using that prefix only.
5. Run the native page against identical filters and compare combined totals plus IDs 546, 528, 527, and 526 if present.

Until these are supplied, exact live source counts, known-record availability, actual AJAX response, installed plugin path, and whether 17 is exactly the form-table total are **unknown**.

## 13. Findings and implementation plan

### P0-01

**ID:** P0-01  
**Area:** deployment/runtime parity  
**Current behavior:** live Dashboard reports 17 while the checked-in candidate would query both sources.  
**Expected behavior:** one identifiable, versioned plugin release and asset set serves `/dashboard/`.  
**Root cause:** confirmed local/remote/deployed-source mismatch; active live artifact is not proven to be the inspected code.  
**Evidence:** untracked candidate files; `HEAD` 1.2.7 lacks them; ZIP endpoint 404.  
**Impact/Priority:** incorrect payment dataset, P0.  
**Files/components:** release pipeline, main plugin bootstrap, deployed plugin directory—not database data.  
**Proposed minimal fix:** package/deploy the reviewed version atomically only after live fingerprinting; verify plugin path and cache invalidation.  
**Database/API/UI impact:** none by itself; validates the correct endpoint.  
**Security/performance:** preserve current authorization; no broad data load.  
**Tests/acceptance:** active file hashes match release; asset URL resolves and carries new version; Network response uses combined service.  
**Dependencies:** live administrative read-only access and deployment owner.  
**Rollback:** restore the previous plugin ZIP/directory atomically and purge only relevant asset cache.

### P1-01

**ID:** P1-01  
**Area:** canonical query and native/Dashboard parity  
**Current behavior:** native loads/merges all rows in PHP; candidate uses a SQL union.  
**Expected behavior:** same canonical source set and totals under equivalent filters.  
**Root cause:** duplicate listing implementations; not confirmed as the live 17 cause.  
**Evidence:** exact queries in sections 6–7.  
**Impact/Priority:** parity and scalability, P1.  
**Files/components:** `modules/payment/class-ggm-payment-admin-service.php`; later, `admin/views/payments.php`.  
**Proposed minimal fix:** after P0 verification, make the existing service the authoritative query/normalization layer for both UIs, retaining native rendering and purchase-only refunds.  
**Database impact:** no migration; consider only evidence-backed composite indexes after `EXPLAIN`.  
**API/UI impact:** preserve candidate JSON schema; native gets server-side pagination.  
**Security impact:** preserve `manage_options`, role, and nonce checks.  
**Performance impact:** avoid PHP full-table merge; test counts/search plans.  
**Tests/acceptance:** same filters return identical source-qualified records and total in both UIs.  
**Dependencies:** live counts and query-plan evidence.  
**Rollback:** retain native view wiring and revert service caller change.

### P1-02

**ID:** P1-02  
**Area:** guest/form normalization  
**Current behavior:** valid `user_id=0` becomes `User #0`; no form-answer phone fallback.  
**Expected behavior:** `Guest` (optionally a safely extracted submitted name/email/phone when present), never invented identity.  
**Root cause:** candidate renderer fallback and incomplete normalizer.  
**Evidence:** JS fallback and native `Guest` branch.  
**Impact/Priority:** misleading customer identity, P1.  
**Files/components:** payment service/presenter and `assets/js/ggm-dashboard-payments.js`.  
**Database/API/UI impact:** no writes; add normalized guest display fields only.  
**Security/performance:** expose only fields already authorized in native Admin; batch submission lookups to avoid N+1.  
**Tests/acceptance:** guest row shows Guest; normal/deleted users remain accurate; form phone parity with native.  
**Rollback:** renderer fallback can be reverted independently.

### P2-01

**ID:** P2-01  
**Area:** empty/error states and query hardening  
**Current behavior:** generic request error and blank successful empty table; phone joins can duplicate rows.  
**Expected behavior:** distinct no-results/load-error messages and count/data cardinality parity.  
**Root cause:** incomplete client-state handling and unrestricted meta joins.  
**Evidence:** candidate JS/service.  
**Impact/Priority:** reliability/performance, P2.  
**Files/components:** service, payments JS, tests.  
**Tests/acceptance:** forced SQL/AJAX/nonce failures render errors; duplicate-meta fixture does not duplicate totals.  
**Rollback:** presentation-only changes can roll back separately.

## 14. Files that would change / must not change

Likely change after approval and live verification:

- `modules/payment/class-ggm-payment-admin-service.php` — shared canonical query/normalizer only.
- `modules/dashboard/class-ggm-dashboard-management.php` — only error propagation if evidence requires it.
- `assets/js/ggm-dashboard-payments.js` — guest/empty/error presentation only.
- `admin/views/payments.php` — only when routing native Admin through the confirmed shared service.
- `scripts/test-dashboard-payments.php` and new focused parity/integration tests.

Must not change without new evidence: Razorpay verification/creation (`modules/payment/class-ggm-razorpay.php`), form payment verification (`modules/workshop/class-ggm-form-builder.php`), database payment records, access/enrollment logic, payment schema/migrations, refund semantics, or unrelated Dashboard/CSS code.

## 15. Test matrix and acceptance criteria

| Area | Required checks |
|---|---|
| Dataset | native total = Dashboard total; main/form/combined counts and statuses recorded |
| Known IDs | source-qualified lookup for 546, 528, 527, 526 if they exist |
| Search | name, email, phone, order ID, local ID, Razorpay payment ID; server-side only |
| Status | all, success, pending, failed, refunded, every observed additional form status |
| Pagination | first/second/final/empty pages; 567, 5k, 50k+, 500k+ fixtures/query plans |
| Customer | WP user, deleted/missing user, guest form, zero ID, form-submitted phone |
| Security | administrator, customer, subscriber, instructor, shop manager, logged-out, forged nonce, direct AJAX |
| Failures | SQL failure, network failure, invalid nonce, malformed response, invalid refund ID |
| Regression | native Admin, Razorpay checkout/webhook, form payment, refunds, enrollment/access, Workshops/Courses, ordinary member Dashboard |

Acceptance: for every common filter, source-qualified native and Dashboard records/totals match; Dashboard never presents `User #0` as a real customer; it loads only one server page; non-admins receive no payment data; live artifact/version/path and assets are demonstrably the reviewed release.

## 16. Approval gate

The audit is complete to the evidence available in this workspace. Implementation is deliberately not started. Supply or collect the live verification evidence in section 12, then approve the minimal correction described above.

## LIVE VERIFICATION RESULTS

**Phase status: blocked before live-runtime evidence collection.** This diagnostic session has read-only access to the plugin source workspace and public web only. It has no live WordPress filesystem/runtime, WordPress Administrator browser session, SSH/SFTP connection, database connection, `wp-config.php`, verified production URL, or WordPress-specific connector. A bounded tool-capability check found no available browser, WordPress, server, or MySQL capability. Public web searching did not identify a verified Global Good Health Mission production site. No authenticated endpoint was attempted and no production state was changed.

| Required live evidence | Result | Reason |
|---|---|---|
| Active basename/path/main file/version/constants | Not collected | No live filesystem/runtime access |
| Presence and hashes of Dashboard payment files | Not collected | No live filesystem access |
| Database name / `$wpdb->prefix` / table names | Not collected | No live WordPress or database connection |
| Main/form totals, statuses, ID ranges | Not collected | No database connection; no SQL executed |
| Dashboard Network request and complete JSON response | Not collected | No authenticated Administrator browser session or verified site URL |
| IDs 546, 528, 527, 526 | Not collected | No database or authenticated endpoint access |
| Native vs Dashboard exact totals | Not collected | Neither live UI is accessible |

### What remains confirmed

The source-level conclusion remains unchanged: the working-tree Dashboard candidate is untracked and absent from `HEAD`/`origin/main`; its local `UNION ALL` service would read both payment tables. The reported browser label `User #0` is still explained by the candidate renderer’s explicit `User #` fallback for `user_id=0`. These are **not substitutes for live evidence** and do not establish the actual live callback or its table counts.

### Required read-only handoff

Run the following as an Administrator in the actual production WordPress environment (via WP-CLI, an approved one-time read-only diagnostic, and browser DevTools), then provide the resulting redacted output:

```php
// Runtime fingerprint: do not expose DB_PASSWORD or authentication values.
global $wpdb;
printf("basename=%s\nfile=%s\ndir=%s\nversion=%s\ndb_version=%s\ndb=%s\nprefix=%s\n",
    GGM_PLUGIN_BASENAME, GGM_PLUGIN_FILE, GGM_PLUGIN_DIR, GGM_VERSION,
    GGM_DB_VERSION, $wpdb->dbname, $wpdb->prefix);
foreach (array(
    'modules/payment/class-ggm-payment-admin-service.php',
    'modules/dashboard/class-ggm-dashboard-management.php',
    'templates/dashboard/admin/payments.php',
    'assets/js/ggm-dashboard-payments.js',
) as $relative) {
    $file = GGM_PLUGIN_DIR . $relative;
    printf("%s exists=%s mtime=%s sha256=%s\n", $relative,
        file_exists($file) ? 'yes' : 'no',
        file_exists($file) ? gmdate('c', filemtime($file)) : '-',
        file_exists($file) ? hash_file('sha256', $file) : '-');
}
```

Then use the discovered prefix, not a guessed `wp_` prefix, for the six read-only count/status/ID-range queries specified in section 5. Capture the `ggm_dashboard_management_payments` Network request and complete JSON response after opening Dashboard Payments; redact only nonce, cookies, credentials, and other secrets.

### Revised implementation status

No implementation recommendation has changed, and implementation remains unauthorized. The P0 deployment/runtime fingerprint gate is now an explicit blocking dependency: it must establish the actual live callback before deciding whether the eventual correction is a deployment, handler/query, database-prefix, or cache fix.
