# Dashboard Payments — Local Runtime Deep Audit

**Date:** 2026-09-14  
**Mode:** read-only local-runtime audit. No production code, plugin code, database records, schema, payment state, Razorpay logic, or deployment was changed.

## 1. Current symptom

The local Dashboard was reported to have shown `17` rows and, at other times, an empty/failed state. The reported native total of approximately 567 is **not reproducible in the current local WordPress database**. Current runtime evidence establishes that the canonical GGM tables contain 17 total rows: zero purchase payments and 17 form payments.

## 2. Local runtime fingerprint

| Item | Actual value |
|---|---|
| Site | `http://ggm.local/` |
| WordPress root | `C:\Users\Rakes\Local Sites\ggm\app\public` |
| Database | `local` |
| `$wpdb->prefix` | `wp_` |
| Active plugin basename | `ggm-member-dashboard/ggm-member-dashboard.php` |
| Main plugin file | `C:\Users\Rakes\Local Sites\ggm\app\public\wp-content\plugins\ggm-member-dashboard\ggm-member-dashboard.php` |
| `GGM_PLUGIN_FILE` | same main plugin file |
| `GGM_PLUGIN_DIR` | `C:\Users\Rakes\Local Sites\ggm\app\public\wp-content\plugins\ggm-member-dashboard\` |
| `GGM_VERSION` | `1.3.8` |
| `GGM_DB_VERSION` | `3.3.0` |

The duplicate directory `ggm-member-dashboard-1.2.9-admin-dashboard-management` is present on disk but is **not active**. The active-plugin option contains only `ggm-member-dashboard/ggm-member-dashboard.php`.

## 3. Active plugin/version

The loaded active plugin is version 1.3.8. Its bootstrap includes and initializes `GGM_Payment_Admin_Service` and `GGM_Dashboard_Management`. Runtime inspection found exactly one callback for `wp_ajax_ggm_dashboard_management_payments`:

```text
priority 10: GGM_Dashboard_Management::ajax_payments
```

No second active callback or handler override was found.

## 4. Actual loaded files

All required files exist under the active plugin directory and were loaded by the WordPress bootstrap:

| File | SHA-256 | Runtime mtime (UTC) |
|---|---|---|
| `modules/payment/class-ggm-payment-admin-service.php` | `01619c655ad8e6f0847ab6da82c0d678261e6fabe71607a3abec45044e0d492a` | `2026-09-14T06:35:04+00:00` |
| `modules/dashboard/class-ggm-dashboard-management.php` | `5bf879a43d6b7b8e1a645e383cbcdfcf8096f00fa92df55efd769b8d22367f36` | `2026-09-14T06:35:04+00:00` |
| `templates/dashboard/admin/payments.php` | `07902974f871abffa3d29d70171a19597c9db84a31a3839621fae08388e390f2` | `2026-09-14T06:35:04+00:00` |
| `assets/js/ggm-dashboard-payments.js` | `2c634cc5ac60647a216f5e667af196dbf9ca0f00189e29585443c2da4d0a06c7` | `2026-09-14T06:35:04+00:00` |

The active payment service, management handler, and payments JS hashes match the workspace copies audited previously.

## 5. Local database/prefix

The active runtime uses database `local`, prefix `wp_`, and these canonical GGM payment tables:

```text
wp_ggm_payments
wp_ggm_form_payments
```

Both tables exist. No other GGM payment table exists. `wp_woocommerce_payment_tokens` and `wp_woocommerce_payment_tokenmeta` are unrelated WooCommerce token tables and are not queried by either GGM payment UI.

## 6. Payment table counts

Read-only query results:

| Source | Count | ID range | Status counts |
|---|---:|---|---|
| `wp_ggm_payments` | 0 | min `NULL`, max `NULL` | none |
| `wp_ggm_form_payments` | 17 | 1–17 | pending: 17 |
| Combined canonical GGM set | 17 | source-qualified IDs | pending: 17 |

This proves that the Dashboard sequence `17 → 16 → … → 1` maps exactly to all records in `wp_ggm_form_payments`. The newest row is form ID 17, created `2026-09-04 13:46:41`, amount `INR 500.00`, status `pending`, and has a Razorpay order ID. Several valid guest-form rows have `user_id=0`.

## 7. Native payment architecture

`GGM_Admin::render_payments_page()` renders `admin/views/payments.php`. Its local `ggm_lms_payment_load_rows()` executes:

1. a purchase query from `{$wpdb->prefix}ggm_payments` with a user join;
2. a form-payment query from `{$wpdb->prefix}ggm_form_payments` with user and form joins;
3. PHP array merge, timestamp descending sort, status/search filtering, then `array_slice` pagination.

Native pagination is 25 rows per page. Search is PHP-side and examines ID, user fields, phone, Razorpay order/payment IDs, source, form fields, and workshop/course titles. With the actual local source counts, the native page’s exact total is **17**: 0 purchase + 17 form. It cannot obtain 567 rows from this implementation and database.

## 8. Dashboard payment architecture

The active execution path is:

```text
Dashboard tab template
→ assets/js/ggm-dashboard-payments.js
→ POST /wp-admin/admin-ajax.php
→ ggm_dashboard_management_payments
→ GGM_Dashboard_Management::ajax_payments()
→ GGM_Payment_Admin_Service::query()
→ wp_ggm_payments UNION ALL wp_ggm_form_payments
→ JSON
→ JS renderer
```

The service uses 20-row server-side pagination. It builds the count and data selects from the same union and optional status/search predicate. It orders by `created_at DESC`, then uses `LIMIT 20 OFFSET 0` for page one. There is no `LIMIT 17` and no client-side row cap.

## 9. Actual AJAX request

Expected request emitted by the active JS when the tab is opened:

```text
POST http://ggm.local/wp-admin/admin-ajax.php
action=ggm_dashboard_management_payments
nonce=[session nonce; redacted]
page=1
search=
status=
```

`per_page` is not sent by the client; the server fixes it at 20. The available terminal environment has no logged-in browser session to extract a DevTools HTTP transaction. To validate the actual registered callback without bypassing the database or query service, the loaded service was executed through the active WordPress bootstrap. Its response data is recorded in the next section. The runtime callback and data result are therefore confirmed; an administrator-browser HTTP status/headers capture remains a final optional UI-level check.

## 10. Actual AJAX response

The active `GGM_Payment_Admin_Service::query(array('page'=>1,'search'=>'','status'=>''))` returned:

```json
{
  "total": 17,
  "page": 1,
  "pages": 1,
  "per_page": 20,
  "status": "",
  "search": "",
  "items": [
    {"source":"form","id":17,"status":"pending"},
    {"source":"form","id":16,"status":"pending"},
    {"source":"form","id":15,"status":"pending"},
    {"source":"form","id":14,"status":"pending"},
    {"source":"form","id":13,"status":"pending"},
    {"source":"form","id":12,"status":"pending"},
    {"source":"form","id":11,"status":"pending"},
    {"source":"form","id":10,"status":"pending"},
    {"source":"form","id":9,"status":"pending"},
    {"source":"form","id":8,"status":"pending"},
    {"source":"form","id":7,"status":"pending"},
    {"source":"form","id":6,"status":"pending"},
    {"source":"form","id":5,"status":"pending"},
    {"source":"form","id":4,"status":"pending"},
    {"source":"form","id":3,"status":"pending"},
    {"source":"form","id":2,"status":"pending"},
    {"source":"form","id":1,"status":"pending"}
  ]
}
```

`$wpdb->last_error` was empty. The JS writes `d.total` directly into the visible “Showing … of … payments” label. Thus the Dashboard’s 17 is a correct representation of the active local canonical query, not a JS rendering defect.

## 11. Native vs Dashboard comparison

Conditions: all statuses, empty search, page 1.

| Measure | Native Admin | Dashboard |
|---|---:|---:|
| Canonical total | 17 | 17 |
| Purchase source rows | 0 | 0 |
| Form source rows | 17 | 17 |
| Returned IDs | 17→1 (native page can show all 17) | 17→1 |
| Returned source per ID | form | form |
| Newest timestamp | `2026-09-04 13:46:41` | `2026-09-04 13:46:41` |
| Page size | 25 | 20 |

The parity contract passes for the actual local database. The claimed 567 native records do not exist in the same local canonical GGM dataset.

## 12. Known payment ID verification

| ID | `wp_ggm_payments` | `wp_ggm_form_payments` | Dashboard endpoint/service |
|---:|---|---|---|
| 546 | absent | absent | not returnable |
| 528 | absent | absent | not returnable |
| 527 | absent | absent | not returnable |
| 526 | absent | absent | not returnable |

## 13. SQL comparison

Native Admin issues two separate unbounded selects, merges them in PHP, then counts/slices. Dashboard uses the equivalent source set in SQL:

```sql
(SELECT 'purchase' source, ... FROM wp_ggm_payments p
 UNION ALL
 SELECT 'form' source, ... FROM wp_ggm_form_payments fp
 LEFT JOIN wp_ggm_forms f ON f.id = fp.form_id) payments
```

The Dashboard count is `SELECT COUNT(*) FROM ({union}) payments LEFT JOIN wp_users u ... WHERE ...`; the row select applies the same source union/where predicate, orders descending by `created_at`, and paginates. With blank search/status, neither path has a source, user, date, or status restriction. There is no missing union, incorrect count, incorrect prefix, or 17-row limit.

## 14. Root cause

**Confirmed local root cause: R — local dataset/environment mismatch with the reported expectation.** The actual local `wp_ggm_payments` table is empty, while `wp_ggm_form_payments` contains exactly 17 rows. Both native Admin and Dashboard correctly operate on these two sources and return 17. There is no Dashboard data-query discrepancy in this local runtime.

The assertion that the same native GGM page shows approximately 567 cannot be true for the currently active plugin/database combination. That count belongs to another environment, an earlier database snapshot, or a different payment system; it is not present in the active local GGM tables. It must be reconciled by importing/connecting the intended dataset through an approved data-environment process, not by changing the Dashboard query.

## 15. Contributing causes

1. **P1 — historic runtime instability:** local PHP/nginx logs contain repeated PHP memory-exhaustion fatals, chiefly Elementor-related, and upstream connection failures/timeouts. Those can produce generic Dashboard load failures before an AJAX response is returned. No payment-service SQL error appears in the current successful runtime.
2. **P1 — historic duplicate-plugin conflict:** a log entry on 2026-09-13 reports `Cannot redeclare ggm_run()` when the duplicate 1.2.9 directory was loaded alongside the active plugin. It is presently inactive, but its presence is a deployment hygiene risk.
3. **P2 — guest presentation:** valid guest form records (`user_id=0`) render as `User #0` in the Dashboard candidate. Native Admin renders `Guest`. This does not affect count parity.
4. **P2 — service performance:** the Dashboard query is paginated but has potential usermeta-join multiplicity at large scale; native Admin still loads all rows. Neither explains 17 in this data set.

## 16. Performance findings

Dashboard correctly limits the server response to 20 records per page and independently computes `total`. Do not change it to fetch 567 rows or all data into JavaScript. If/when the intended larger GGM dataset is loaded, benchmark count/search with `EXPLAIN`, retain server pagination, and address duplicate-meta joins/indexes only with evidence.

## 17. Security findings

The endpoint is registered only as `wp_ajax_ggm_dashboard_management_payments`; no unauthenticated (`nopriv`) action exists. `GGM_Dashboard_Management::authorize()` checks the management nonce, requires a logged-in user, `manage_options`, and the `administrator` role. Refund additionally requires a per-payment nonce and the shared service refuses non-success or non-purchase records. No security weakening is recommended.

## 18. Exact implementation plan

### P0 — data-environment reconciliation (not a code fix)

Identify the environment/database backup that actually contains the expected 567 canonical GGM payment rows. Verify its WordPress prefix and the counts of both GGM tables before any import/restore. Obtain explicit approval for any database synchronization; this audit authorizes no data operation.

### P1 — runtime reliability (only if reproducible after data reconciliation)

Investigate Local’s memory-exhaustion and upstream failures outside the payment query. Confirm a normal authenticated Dashboard request receives HTTP 200 with the JSON recorded above. Do not add retries or suppress server errors in the payment UI.

### P2 — approved future presentation parity

When implementation is authorized, normalize `user_id=0` to `Guest` and use the existing form-submission contact fallback in a shared normalizer. Preserve all values as server-side paginated responses.

## 19. Exact files/functions to modify

**No file requires a change to make the current local Dashboard and native GGM page agree; they already agree at 17.**

Only after explicit approval and only for the P2 guest-display improvement:

- `modules/payment/class-ggm-payment-admin-service.php` — shared row/customer normalization.
- `assets/js/ggm-dashboard-payments.js` — render a normalized guest label rather than constructing `User #0`.
- `scripts/test-dashboard-payments.php` — parity/guest regression coverage.

Do not change `modules/payment/class-ggm-razorpay.php`, `modules/workshop/class-ggm-form-builder.php`, `includes/class-ggm-database.php`, refund handling, or native payment behavior for this finding.

## 20. Tests

- Re-run runtime counts and assert native/Dashboard totals equal each source total.
- Test blank search/all status and every actual status.
- Test search by customer, email, phone, local ID, Razorpay order ID, and payment ID.
- Test first, middle, last, and empty pages against a large approved fixture—server-side only.
- Test normal user, guest `user_id=0`, deleted user, administrator, subscriber, logged-out, invalid nonce, and forged refund request.
- After environment reconciliation, verify IDs 546/528/527/526 only if they exist in the intended database.
- Verify PHP/nginx logs remain free of relevant fatal/SQL errors during the authenticated AJAX request.

## 21. Acceptance criteria

For a confirmed single database/prefix, native and Dashboard totals and source-qualified rows match under identical filters. The Dashboard response uses a maximum of 20 rows per page and accurate server `total`. `User #0` is not presented as an identity once P2 is approved. The intended larger data set is present before any 567-based acceptance assertion is made.

## 22. Rollback plan

No changes were made. For future approved presentation changes, deploy a versioned plugin artifact, retain the prior artifact, verify read-only parity, and restore the prior artifact if parity/security regress. Database restore/import is outside this plan and requires its own backup, validation, and explicit approval.
