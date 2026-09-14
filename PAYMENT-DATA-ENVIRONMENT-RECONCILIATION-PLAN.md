# Payment Data Environment Reconciliation Plan

**Date:** 2026-09-14  
**Mode:** diagnostic and planning only. No database import, restore, table copy, record change, plugin/code change, or deployment was performed.

## 1. Current Local environment

| Property | Verified value |
|---|---|
| Local site | `http://ggm.local` |
| WordPress root | `C:\Users\Rakes\Local Sites\ggm\app\public` |
| Active database | `local` |
| Prefix | `wp_` |
| Active GGM plugin | `ggm-member-dashboard/ggm-member-dashboard.php`, version `1.3.8` |
| Main payment table | `wp_ggm_payments`: **0** rows |
| Form payment table | `wp_ggm_form_payments`: **17** rows |
| Combined canonical GGM payments | **17** rows |
| Main-table status breakdown | none |
| Form-table status breakdown | pending: 17 |

The current Local runtime is internally consistent: native GGM Payments and Dashboard Payments both read the same two canonical tables and both return 17.

## 2. Candidate source environments investigated

| Candidate | Identity | Evidence | Canonical GGM payment result | Candidate status |
|---|---|---|---:|---|
| Current Local MySQL | database `local`, prefix `wp_` | Local service configuration and live read-only SQL | 0 main + 17 form = 17 | Current target; not the expected dataset |
| Current Local SQL snapshot | `C:\Users\Rakes\Local Sites\ggm\app\sql\local.sql`, 52,221,692 bytes, 2026-09-14 | Snapshot contains empty `wp_ggm_payments` data section and form-payment inserts 1–17 | 17 | Same dataset as current Local |
| Local MySQL instance, other databases | `SHOW DATABASES` | Only application database is `local`; remaining schemas are MySQL system schemas | none | No candidate found |
| Other Local sites | `C:\Users\Rakes\Local Sites` | Only `ggm` site exists | none | No candidate found |
| Backuply archive | `wp_ggm.digtize.com_2026-02-02_16-33-01.tar.gz`, 230,723,904 bytes | Manifest: `https://ggm.digtize.com`, database `digtize_wp360`, prefix `wpd7_`, created 2026-02-02 | Not verified as canonical source; archive contains no `ggm-member-dashboard` plugin path | Too old / not suitable without further read-only extraction |

No accessible staging database, second Local database, current production database, recent database export, `.wpress` archive, or SQL dump with the expected GGM payment IDs was found in the inspected Local environment and common backup locations.

## 3. Exact source of the 567 records

**Not found in the available local assets.** The expected approximately 567 canonical GGM records do not exist in Local database `local`, its current `local.sql` snapshot, or any other MySQL application database on this Local instance. IDs 546, 528, 527, and 526 are absent from both current canonical tables.

The February Backuply archive establishes the existence of an older production environment (`digtize_wp360`, `wpd7_`), but it cannot be declared the source of the expected dataset: it predates the active GGM plugin layout and its archive file list contains no `ggm-member-dashboard` plugin path. Restoring it would be an unsupported guess and is expressly rejected.

The required source is therefore a **later production or staging database/export created after the GGM purchase-payment system was deployed**. It must be located through production hosting/database access, a newer Backuply/All-in-One export, or an approved current database export.

## 4. Database comparison

| Database / snapshot | Prefix | Main payments | Form payments | Combined | Known IDs 546/528/527/526 |
|---|---|---:|---:|---:|---|
| Current Local `local` | `wp_` | 0 | 17 | 17 | all absent |
| `local.sql` current snapshot | `wp_` | 0 | 17 | 17 | all absent |
| 2026-02-02 Backuply production archive | `wpd7_` | not safely verified; not a valid candidate | not safely verified; not a valid candidate | unknown | unknown |
| Required later production/staging source | unknown | **must verify** | **must verify** | expected ~567 only if proven | **must verify** |

The `local.sql` snapshot and live Local schema are compatible for the two GGM tables. The present live schema is:

- `wp_ggm_payments`: purchase fields including user/workshop/course/coupon IDs, original/discount/credit amounts, Razorpay order/payment/signature, amount/currency/status/created time; primary key `id`; indexes `user_id`, `status`; `utf8mb4_unicode_520_ci`.
- `wp_ggm_form_payments`: form/submission/user/payment-key/label/description, Razorpay fields, amount/currency/status/created/updated time; primary key `id`; indexes form, submission, user, Razorpay order, status; `utf8mb4_unicode_520_ci`.

## 5. Schema comparison requirement

Before any synchronization, obtain `SHOW CREATE TABLE` for both canonical tables from the intended source and compare:

1. exact columns and types;
2. primary/secondary indexes;
3. charset/collation;
4. source prefix;
5. maximum IDs and status vocabulary;
6. referenced user, form, submission, workshop, course, coupon, and access records.

Do not import table data into the current schema merely because table names match. Any source-schema mismatch requires an approved, separately audited migration plan.

## 6. Known payment ID verification

Current Local read-only results:

| ID | `wp_ggm_payments` | `wp_ggm_form_payments` | Result |
|---:|---|---|---|
| 546 | absent | absent | not available |
| 528 | absent | absent | not available |
| 527 | absent | absent | not available |
| 526 | absent | absent | not available |

The described ID 546 payment for Savithri Varatharajan, INR 1,400, success, and Razorpay order/payment IDs cannot be verified locally because it is not in the current canonical data. It is a mandatory identity check on the later source before any approval to synchronize.

## 7. Recommended synchronization method

**Recommendation: D — create a separate Local clone from a verified, current full WordPress database snapshot; do not synchronize any data yet.**

Why this is safer than importing only payment tables:

- purchase rows reference WordPress users, workshops/courses, coupons, and access records;
- form payments reference forms and submissions;
- table-only import can create unresolved foreign relationships, misleading customers/forms, or duplicate current records;
- the current 17 form payments are legitimate Local data and must not be silently overwritten;
- the later source has not yet been identified or schema-verified.

Once a current candidate snapshot is available, restore it into a **new Local site/database** (for example, a separately named Local site) first. Verify all counts, source-qualified IDs, schema, and native/Dashboard parity there. Only after explicit approval should any decision be made to replace or reconcile `ggm.local`.

## 8. Data-loss and integrity risks

| Risk | Consequence | Mitigation |
|---|---|---|
| Restoring unknown/old archive into current Local | loses current 17 form payments and changes unrelated site state | never restore in place; use a separate clone |
| Importing only GGM payment tables | broken references to users/forms/submissions/access and mismatched schema | prefer verified full database clone |
| Copying records into existing tables | duplicate IDs, duplicate payments, altered payment integrity | do not merge or manually insert rows |
| Treating WooCommerce orders as GGM payments | corrupts canonical payment model and false parity | do not merge unrelated WooCommerce data |
| Source schema/prefix mismatch | failed import or silent semantic mismatch | compare `SHOW CREATE TABLE` and source prefix first |
| Unverified snapshot date | missing known records or stale payment state | require known-ID and exact-count validation |

## 9. Backup procedure before a future approved synchronization

Before any change to `local`:

1. Stop no services and change no data during planning.
2. Make a full logical backup of current database `local`, including routines/events if present, to a timestamped file outside the active WordPress tree.
3. Record SHA-256, byte size, database name/prefix, exact table counts, `SHOW CREATE TABLE` output, and active plugin version.
4. Verify the backup by restoring it only into a separate disposable database/site, never over `local`.
5. Preserve the existing `local.sql` snapshot and the 17 form-payment rows as the baseline comparison.

Current baseline for the change record: `local`, prefix `wp_`, 0 purchase + 17 pending form payments, combined 17.

## 10. Rollback procedure

1. If a future approved synchronization fails parity or integrity checks, stop testing against the changed target.
2. Restore the verified pre-sync full `local` backup to the same database only after confirming its checksum and target database name.
3. Confirm `wp_ggm_payments=0`, `wp_ggm_form_payments=17`, form status pending=17, and that Local bootstraps normally.
4. Re-run native/Dashboard parity and record the result.

For the recommended separate-clone method, rollback is simply discarding the clone; the current `ggm.local` remains untouched.

## 11. Post-sync validation

On the verified clone first, then only on an explicitly approved target:

1. Confirm database name/prefix and active plugin path/version.
2. Run read-only counts and status breakdowns for both canonical GGM tables.
3. Confirm known IDs 546, 528, 527, and 526, including source, user, amount, status, Razorpay IDs, and `created_at`.
4. Confirm ID 546 matches the documented Savithri Varatharajan payment without altering it.
5. Verify Native Admin all-status/no-search total equals the Dashboard AJAX `total`.
6. Verify Dashboard returns 20 rows maximum on page one, correct total/pages, and source-qualified rows match Native Admin.
7. Test search, each observed status, next/previous pages, payment details, refund authorization, guest rows, non-administrator rejection, and error behavior.
8. Confirm no duplicate payment IDs, no altered Razorpay IDs, no changed amounts/statuses/timestamps, and no payment-data writes occurred during validation.

## 12. Approval gate

This plan intentionally does not identify a usable 567-record source because no such source is accessible in the current Local environment. Do not restore the February archive, import tables, connect Local to another database, or modify payment/dashboard code.

Next required input: a current production/staging database connection or a post-GGM-system full database backup/export. After read-only verification proves canonical counts and IDs, update this plan with the source identity and choose the exact clone/restore procedure for approval.
