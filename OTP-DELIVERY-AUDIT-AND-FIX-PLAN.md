# OTP delivery audit and proposed fix plan

Date: 2026-09-06  
Status: Audit complete; approved confirmed fixes implemented locally on 2026-09-06. Production rollout remains separate.

## Conclusion

The source contained reproducible defects that could prevent OTP requests for particular identifiers, make resends appear to fail silently, and invalidate a previously delivered OTP when its replacement could not be sent. The approved local fixes address those defects. They do not establish which defect or SMTP condition caused the reported production incidents: no live WordPress database, provider events, browser traces, or production logs were available in this workspace.

Production OTP delivery is currently **registered-account email through Custom SMTP only**, including when the visitor enters a phone number. SMS/WhatsApp provider classes exist but are not called by the active send path. Preserve this routing and the existing account/session binding.

## Scope and validation

Reviewed the OTP manager, authentication/account resolution, shared SMTP helpers, login and legacy auth JavaScript, checkout OTP UI, workshop join OTP UI, caching checks, OTP settings, and error-log storage. The earlier workshop enrollment plan was read for context; findings here follow current source, not that older report.

- Executed an isolated PHP CLI harness against the actual `GGM_OTP` and `GGM_Auth` classes, with in-memory WordPress/transient stubs and a simulated mail result. No email was sent.
- Reproduced normalization failures, email misclassification, failed-resend invalidation, and placeholder email selection. These are unit-level reproductions, not a live WordPress or SMTP integration test.
- PHP syntax checks passed for the OTP manager, auth controller, mail helpers, workshop shortcodes, and checkout template. JavaScript syntax checks passed for both login/auth scripts.
- No test suite, production log files, or WordPress configuration was found by the targeted workspace searches. The workspace is not a Git checkout, so Git history/status comparison was unavailable.
- The approved local code fixes for F1, F2, F3, F4, and the checkout-message portion of F7 were applied. No configuration, account data, delivery settings, production deployment, or live email was changed.

## Confirmed findings

### F1 — High: identifier normalization is incorrect and not idempotent

Evidence: `modules/auth/class-ggm-otp.php:63`, `modules/auth/class-ggm-auth.php:60`, `assets/js/ggm-login.js:45`.

Both PHP methods remove a leading `91` without first checking whether it is a country code or part of a ten-digit local number. The OTP manager then passes the normalized value to the auth resolver, which normalizes it again. They also try phone recognition before email recognition by removing all non-digits from the entire identifier.

Observed harness results:

| Input | Actual behavior | Impact |
| --- | --- | --- |
| `9123456789` | OTP normalization returns false | Valid ten-digit local shape rejected because leading `91` is stripped |
| `+919123456789` | First normalization returns `9123456789`; resolver classifies that value as email | Prefixed input still fails phone account lookup |
| `+919876543210` | Both stages retain phone `9876543210` | Control case explains why other numbers work |
| `audit1234567890@example.test` | Classified as phone `1234567890` | Valid email can resolve through an unrelated phone instead of its email account |
| `+91+919876543210` | Rejected | Login UI can produce this effective double prefix when a user pastes a full number while a country code is selected |

The UI always prepends the selected country code to all entered phone digits. Backend recognition is India-specific even though country selectors are presented. This is a contract mismatch; do not fix it by blindly taking the last ten digits of every international number.

Proposed fix: one shared, email-first, idempotent identifier parser; length-aware handling of Indian local/prefixed forms; avoid prepending an already supplied country code. Explicitly reject unsupported phone formats with useful guidance to use registered email. Broader international identity support requires a separate, consistent storage/matching design.

### F2 — High: failed resend destroys a working OTP; overlapping sends are unsafe

Evidence: `modules/auth/class-ggm-otp.php:224` through the storage and dispatch failure branches around line 264.

The previous request is deleted before the replacement has been stored and accepted by the mail transport. If replacement storage or mail dispatch fails, the old request is already gone. The browser retains the old request ID after a failed resend, so a previously delivered code now fails verification.

Harness: first send succeeded; previous request existed; simulated second SMTP send returned false; previous request no longer existed.

There is also no server-side serialization around read-current, replace, send, and cleanup. An overlapping failure can delete another request's current pointer; out-of-order responses can leave the browser holding a superseded request. These concurrency cases are inferred from the code sequence, not load-tested.

Proposed fix: an expiring lock scoped to canonical identifier/browser session, checked storage writes, and conditional ownership checks for replacement/cleanup. Keep the previous usable request until the replacement is successfully accepted and committed. On failure, remove only the failed candidate. Coordinate verification with replacement so a successfully consumed code is never restored. Define response-loss handling and idempotency before introducing automatic retries.

### F3 — High: login resends hide server and network errors

Evidence: `assets/js/ggm-login.js:205`.

The active login resend clears the error area, but on JSON failure or network failure only re-enables the button. SMTP failure, rate limiting, and expired nonce messages are discarded. The visitor sees no explanation and may retry repeatedly.

`assets/js/ggm-auth.js:195` also lacks a resend failure handler and an in-flight resend guard. No enqueue reference for this legacy script was found; verify actual usage before spending effort on compatibility changes.

Proposed fix: show actionable failure messages, preserve existing request state on failure, guard send/resend/verify transitions, and use structured server error codes and retry timing. Handle malformed/non-JSON responses without claiming that no message was sent.

### F4 — Medium: phone login can send to a legacy placeholder email

Evidence: `modules/auth/class-ggm-otp.php:309`; legacy placeholder documented in `modules/auth/class-ggm-auth.php:616`.

For phone input, registered-email selection chooses the first syntactically valid candidate, starting with `user_email`. A synthetic `phone@ggm-temp.com` address qualifies even if a real `billing_email` is present. The isolated harness selected the placeholder over `real@example.test`. Whether affected legacy records exist in production remains unverified.

Proposed fix: explicitly exclude known synthetic account addresses from OTP destinations, preserve the entered registered email preference, and fail clearly when no eligible registered destination exists. Audit ambiguous or duplicate contact associations before any data migration; do not send to arbitrary newly submitted addresses.

### F5 — Medium: phone lookup does not reliably support stored formatting

Evidence: `modules/auth/class-ggm-auth.php:92`.

The query searches exact variants or a contiguous ten-digit substring, then strips formatting only after retrieving candidates. A stored value such as `98765 43210` cannot match `9876543210` in either query branch when neither phone meta field has a canonical value. The later normalization never sees that account. Queries cap candidates at ten and return the first accepted match, so duplicate/ambiguous phone associations also need deliberate handling.

Proposed fix: establish canonical phone lookup metadata for supported numbers, plan a dry-run backfill and collision report, and refuse ambiguous authentication matches. Keep data migration separate and reviewable; avoid an unbounded full-table scan on every OTP request.

### F6 — Medium: retry controls and rate limits are not coordinated

Evidence: `modules/auth/class-ggm-otp.php:127`, `:197`, `:278`; `includes/class-ggm-helpers.php:93`, `:109`; `assets/js/ggm-login.js:235`.

The UI enables resending after 30 seconds, while the identifier limit defaults to three successful sends with a ten-minute TTL renewed on each successful increment. Three quick sends make the next attempt fail even though the UI offers another resend. No authoritative `retry_after` is returned. The coarse IP bucket allows twenty admitted requests and counts failed delivery/account lookup attempts as well. Read-then-write transient counters are not atomic.

Proposed fix: explicit window end and retry timing, atomic admission accounting, and UI countdown based on the server. Preserve abuse protection. Verify whether origin `REMOTE_ADDR` is a real client or shared proxy before changing proxy handling; never trust arbitrary forwarded headers.

### F7 — Medium: checkout does not tell visitors the actual OTP destination

Evidence: `templates/checkout/checkout.php:366` through `:396`.

The checkout verifies a field described as a WhatsApp number, sends its digits, and displays only “OTP sent! Enter the code.” It ignores the returned masked email destination and does not use the selected country code in the request. A visitor can reasonably wait for a phone message while the code was emailed.

Proposed fix: display the returned masked email and clearly label email delivery; use the same supported identifier contract as login. Do not describe email authentication as proof of ownership of the submitted phone number.

### F8 — Medium: SMTP diagnostics are insufficient for reliable incident correlation

Evidence: `includes/class-ggm-helpers.php:333`, `:797`; `includes/class-ggm-meta-boxes.php:1547`; `modules/auth/class-ggm-otp.php:259`.

Mail hooks and transport error logging already exist, so this is not a lack of all logging. However:

- Send failure overwrites the attempted provider with `none`, obscuring that SMTP was attempted.
- There is no single safe attempt/correlation ID linking frontend error, validation, limiter, storage, and transport records.
- The shared log retains only 100 entries. A successful SMTP OTP can generate approximately seven entries, so unrelated mail and OTP traffic can quickly evict the evidence. Concurrent option read/append/write can lose entries.
- The synchronous send has no plugin-defined SMTP timeout budget. Browser handlers also lack a coordinated request timeout/recovery contract.
- Readiness checks only require enabled + host; incomplete credentials, port, encryption, or sender settings are not classified up front.

Proposed fix: structured, bounded diagnostics with stage, safe correlation ID, attempted transport, elapsed time, mail result, and sanitized provider status where available. Record transport acceptance separately from inbox delivery. Reduce routine log volume and avoid concurrent loss. Validate settings without exposing secrets or assuming every SMTP server requires authentication. Define timeout behavior from hosting limits and measured provider latency; do not blindly retry after an ambiguous SMTP outcome.

### F9 — Low: email expiry wording can disagree with actual expiry

Evidence: `modules/auth/class-ggm-otp.php:220`; `includes/class-ggm-helpers.php:825`.

The record expiry is configurable from 1 to 120 minutes; the default email body always says 10 minutes. Expiry starts before synchronous SMTP completes, so a slow send further reduces the usable time.

Proposed fix: render an expiry placeholder from the same effective expiry value and account for dispatch latency. Keep template compatibility and never extend an already expired/consumed request accidentally.

## Production hypotheses requiring evidence

These are investigation targets, not confirmed explanations of the reported incidents:

| Hypothesis | Evidence needed |
| --- | --- |
| SMTP transient rejection, connection/TLS/auth failure, or provider throttling | Sanitized error log entries, provider status/queue events, timestamps, and request duration |
| Message accepted but delayed, suppressed, bounced, or filtered | Provider events correlated by message ID and affected recipient domain; sender authentication configuration |
| Proxy/server timeout after the message was accepted | Browser HTTP status/timing plus web-server/PHP and provider timestamps |
| Shared proxy/NAT exhausting the IP limit | Trusted origin network configuration and anonymized limiter distribution |
| Stale nonce on a long-open/cached page | Exact AJAX response, page age, cache headers, and page/cache configuration |
| Cookie/session mismatch or early transient eviction | Browser cookie scope/persistence, cache backend, request/session correlations |
| Mail plugin intercepting or altering the route | Installed mail plugins/hooks and actual transport result for the same attempt |

Cache prevention exists for detected login/checkout/dashboard/signup pages (`public/class-ggm-public.php:72`). Workshop-only pages are not explicitly covered by that condition, but workshop login also receives a nonce from its access-lookup flow. Test that whole path before calling workshop caching a proven OTP failure. Login currently displays expired-session messages but has no automatic nonce recovery.

## Implementation sequence proposed for approval

1. **Capture a baseline.** Preserve current source/configuration and collect a few sanitized failed/successful production attempt records where available. Add focused regression cases reproducing F1–F4 before fixes. Do not wait on incident logs to fix reproducible code defects after approval.
2. **Correct identity and request lifecycle (F1, F2, F4).** Shared parsing, safe destination eligibility, serialized replacement/cleanup, and preserved session/account bindings. Review collision behavior before phone lookup changes.
3. **Fix feedback and timing (F3, F6, F7, F9).** Consistent errors and masked destination across login, reset, checkout, and workshop flows; server-directed retry countdown; protect against overlapping UI operations.
4. **Improve transport diagnostics and bounded recovery (F8).** Safe correlation, accurate transport classification, setting validation, lower log volume, and explicit timeout/recovery semantics. No automatic resend after an ambiguous acceptance until idempotency is established.
5. **Prepare contact lookup migration (F5).** Produce a dry-run collision/placeholder report and reversible backfill proposal. Production data changes require review of that concrete result.
6. **Validate on staging and prepare rollout.** Run the acceptance matrix below, review the diff, and prepare rollback instructions. Any live email tests need explicitly authorized test recipients. Production deployment and contact migration are separate from approval to edit local code.

## Acceptance matrix

| Area | Required checks |
| --- | --- |
| Parsing | Local numbers beginning `91`; `+91`, bare `91` and leading-zero forms; spaces/hyphens; pasted full numbers; email with ten digits; invalid and unsupported international input; repeated parsing returns the same identity |
| Account lookup | Primary and registered alternate email; formatted phones; duplicate metadata; placeholder primary with valid fallback; no eligible destination; unknown account |
| Replacement | Successful resend; storage failure; SMTP false; two concurrent sends; send versus verify; failure cleanup cannot delete another request; old code survives failed replacement |
| Authentication | Wrong code; expiry; three wrong attempts; consumed-code replay; cross-browser request theft; no account/session binding regression |
| Limits | Default fourth send blocked with accurate retry time; boundary/window expiry; failed SMTP accounting; concurrent admission; shared-IP behavior without trusting spoofed headers |
| Transport | Controlled success, rejection, slow server, timeout, exception and ambiguous outcome; mail-hook interference; no blind duplicate sends |
| UI | Visible resend failures; expired nonce; malformed/HTML error response; disconnected browser; correct masked email; request ID consistent after failure and contact edits |
| Entry points | Login, password reset, checkout, workshop join, and any confirmed legacy UI usage; preserve intended post-login continuation |
| Diagnostics | Correlation across stages; correct attempted provider; bounded retention/volume; no OTP, password, auth token, raw request ID, message body, or unnecessary personal data in logs |

Release criteria: regression and staging checks pass, no account-binding regression, failures are visible and diagnosable, and an accepted OTP remains usable through a failed replacement. Confirm production incident improvement using failure counts by stage and send latency after rollout; source changes alone cannot prove inbox delivery reliability.

## Approval requested

Approved local implementation completed. Production deployment, live test emails, and account-data migration are not included in this change.
