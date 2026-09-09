# Existing User OTP → Dashboard Payment and Workshop Enrollment

## Request

Improve `[ggm_workshop_join_form_full button_text="Submit Now"]` so that:

1. A new visitor can continue through the existing registration/payment flow.
2. A visitor whose account already exists can authenticate with OTP.
3. If that existing account does **not** own the workshop currently being viewed, successful OTP login takes the member to the dashboard and automatically opens the correct payment flow for that workshop.
4. After successful payment, the member is enrolled and remains on/returns to the dashboard with the newly enrolled workshop available.
5. If that account already owns the workshop, successful OTP login goes directly to the dashboard workshop without opening payment or offering a duplicate purchase.

This document is an audit and implementation plan only. No runtime implementation described below should be made until approval is given.

## Executive finding

The account lookup and OTP identity binding are mostly correct. The broken part is the post-login handoff.

The shortcode correctly detects an existing WordPress account and obtains the account's workshop access level. It also correctly requires OTP before that account can be used. However, after OTP verification, the browser always executes:

```js
window.location.assign(dashboardUrl);
```

The current workshop ID, desired action, selected currency, and any contribution choice are not passed to the dashboard. The shortcode also ignores the authoritative `redirect` returned by `ggm_verify_otp`. The member therefore reaches a generic dashboard with no direct continuation of the purchase they started.

The dashboard already exposes a server-backed purchase option for paid workshops without access:

- fixed-price workshops use the existing `.ggm-quick-buy-btn` Razorpay flow;
- contribution workshops link to the checkout page because an amount must be selected;
- workshops already owned use their normal access/start action;
- `GGM_Razorpay::do_create_order()` blocks duplicate workshop purchases server-side.

The safest fix is to preserve the selected workshop through OTP, route to the dashboard Workshops tab, and automatically invoke the matching workshop's existing payment flow when fresh server data confirms that access is absent. Payment should continue to use the dashboard's existing order creation and verification flow rather than introducing a second payment implementation.

## Current flow audit

### 1. Shortcode rendering

File: `public/class-ggm-shortcodes.php`

`sc_workshop_join_form_full()` resolves the workshop and renders name, phone, email, OTP controls, contribution controls when applicable, and the primary CTA.

For a logged-in account with durable paid access, the shortcode exits early with “Go to Dashboard.” For an anonymous visitor, access cannot be known until the entered contact details are resolved.

### 2. Existing-account lookup

File: `modules/payment/class-ggm-razorpay.php`

`ajax_check_workshop_contact_access()`:

- validates the configured lookup rule (`phone`, `email`, or `both`);
- resolves the existing WordPress account;
- calls `GGM_Workshop::get_user_access_level()` for the current workshop;
- returns `login_required`, `identifier`, `access_level`, and `hide_pricing`.

Important state meanings:

- `none`: account exists, but it does not own this workshop;
- `free`: the account joined through a free contribution/zero-value path;
- `paid`: durable paid/manual/membership/admin access exists;
- `global_free`: the fixed-price workshop is globally free.

This is the correct place to classify the account. The browser must not infer ownership by itself.

### 3. OTP flow

Files:

- `public/class-ggm-shortcodes.php`
- `modules/auth/class-ggm-auth.php`
- `modules/auth/class-ggm-otp.php`

The workshop form sends the OTP with `source: workshop_join_form_full`. The OTP is bound to the resolved account and delivered through the registered email flow. `ajax_verify_otp()` establishes the WordPress session and returns a validated redirect.

The defect occurs after successful verification: the shortcode ignores `res.data.redirect` and unconditionally sends the browser to the generic dashboard URL.

### 4. Lost enrollment context

The following information is lost during OTP login:

- workshop ID;
- the fact that the member intended to enroll/pay and that payment should resume automatically;
- selected currency;
- contribution option, if one was selected;
- whether the resolved account already owns the workshop.

`loginRedirect` is initialized to the generic dashboard URL. It is not workshop-specific. The hardcoded client redirect makes this worse by ignoring even the redirect returned from the authentication endpoint.

### 5. Dashboard capability already present

Files:

- `modules/dashboard/class-ggm-dashboard.php`
- `assets/js/ggm-dashboard.js`

The dashboard payload includes published workshops, `has_access`, the current price, selected currency, and a checkout URL.

For a paid workshop without access, `workshopCard()` renders:

- a `Purchase Now` quick-buy button for fixed-price workshops;
- a checkout link for contribution workshops.

The quick-buy handler calls `ggm_create_order`, opens Razorpay, verifies payment, and relies on the server to grant access. This is the correct payment implementation to reuse.

### 6. Server-side payment and duplicate-access controls

File: `modules/payment/class-ggm-razorpay.php`

`ajax_create_order()` authenticates the request. `do_create_order()` resolves the authoritative workshop price and blocks duplicate purchases when durable access already exists, except for the intentionally supported upgrade from a free contribution to a paid contribution.

These protections must remain authoritative. A query parameter or dashboard UI state must never grant access or decide price.

## Problems to correct

### Critical: generic redirect loses the selected workshop

After OTP, the member lands on the dashboard without a target workshop. They must discover the Workshops tab and locate the correct card manually.

### Critical: returned authentication redirect is ignored

`ajax_verify_otp()` returns `res.data.redirect`, including special handling for incomplete profiles, but the shortcode always uses `dashboardUrl`. This can bypass the intended profile-completion destination.

### High: no dashboard resume state

The dashboard has no supported parameter telling it “resume enrollment for workshop X.” Consequently it cannot select the Workshops tab, identify the correct card, or show a continuation message.

### Medium: pricing is hidden during every OTP send

Clicking “Login with OTP” calls `hidePricingForOtpLogin()` even when the existing account has no access to the new workshop. Hiding payment controls while authentication is in progress is reasonable, but the state must resume after login on the dashboard rather than end there.

### Medium: contribution choice cannot safely be resumed as-is

Contribution workshops require an explicit amount selection. The selected option can be carried through login as untrusted resume intent, but the server must revalidate it immediately before creating the order. If that option is missing, removed, or no longer valid, the dashboard must request a fresh selection instead of guessing an amount.

### Medium: target workshop availability can change

Between lookup, OTP verification, and dashboard load, a workshop can be unpublished, expire, change price, or become owned through another transaction. The dashboard must handle these states gracefully using fresh server data.

## Recommended user journeys

### A. New visitor

1. Enter contact details.
2. No account is matched.
3. Keep the current Pay/Join flow.
4. Server creates the account/order and grants access only after the existing verification rules succeed.

### B. Existing account, no access to this workshop

1. Enter registered contact details.
2. Show “Existing member account found.”
3. Authenticate with OTP.
4. Redirect to the dashboard with a validated automatic-payment target for this workshop.
5. Open the Workshops tab automatically and load fresh workshop/access data.
6. Confirm from fresh server data that the authenticated member still lacks access.
7. Scroll to and visually highlight the matching workshop card.
8. Automatically invoke its existing payment flow:
   - fixed-price workshop: create the order and open Razorpay;
   - contribution workshop: resume the selected contribution option when available and server-valid, otherwise require amount selection before payment.
9. After successful payment, grant access through the existing verified-payment path, refresh dashboard state, and show the newly enrolled workshop.
10. If payment is dismissed or fails, leave the member on the targeted card with a visible `Pay & Enroll` retry action.

### C. Existing account already owns this workshop

1. Enter registered contact details.
2. Show “Workshop already enrolled.”
3. Authenticate with OTP.
4. Redirect to the dashboard Workshops tab and focus the owned workshop card.
5. Show its normal Start/Access action; do not create an order or open Razorpay.

### D. Incomplete existing profile

1. OTP verification succeeds.
2. Respect the authentication endpoint's profile-completion requirement.
3. Preserve a safe resume destination so that completing the profile returns the member to the targeted workshop enrollment on the dashboard.

## Proposed technical design

### 1. Build a canonical dashboard automatic-payment resume URL

In `sc_workshop_join_form_full()`, build the dashboard URL from the configured dashboard page, not a hardcoded `/dashboard/` assumption. Add only validated values, for example:

```text
/dashboard/?ggm_enroll_workshop=123&ggm_resume_payment=1&ggm_currency=INR#tab-workshops
```

The workshop ID and resume flag identify UI intent only. They do not authorize purchase or access. When applicable, a contribution option ID may also be carried as untrusted intent and must be fully revalidated by `do_create_order()`.

For an account that already owns the workshop, the same target can be used; the fresh dashboard payload will render the access action instead of payment.

### 2. Send the resume URL through OTP verification

Set `loginRedirect` to the canonical enrollment-resume URL before calling `ggm_verify_otp`.

On success, use the server response:

```js
window.location.assign(res.data.redirect || loginRedirect);
```

Do not hardcode the generic dashboard URL.

### 3. Preserve resume state through profile completion

Audit and extend the profile-completion flow so a validated same-site continuation URL survives when `needs_profile` is true. The continuation must be allow-listed/validated with `wp_validate_redirect()` and must never accept an external URL.

### 4. Teach the dashboard to consume and execute the target

After dashboard data loads successfully:

- read `ggm_enroll_workshop` as an integer;
- select `#tab-workshops`;
- locate the card by a new stable attribute such as `data-workshop-id="123"`;
- scroll the card into view;
- apply a temporary highlight class;
- inspect the freshly returned `has_access`, workshop type, price, and availability;
- if access exists, focus the normal access/start action and do not initiate payment;
- if access is absent and `ggm_resume_payment=1`, invoke the existing fixed-price quick-buy flow automatically after dashboard rendering;
- show a short message appropriate to the fresh server state.

The dashboard should remove the resume query parameter with `history.replaceState()` after consuming it, while retaining the Workshops tab hash. This prevents repeat scrolling on refresh.

### 5. Render stable workshop-card identifiers

Add `data-workshop-id` to the workshop card markup in `workshopCard()`. Do not locate cards by title or button text.

### 6. Reuse the existing payment controls and permit one automatic invocation

- Fixed-price, no access: retain `.ggm-quick-buy-btn` and Razorpay.
- Contribution, no access: carry and revalidate the selected option when one exists; if no valid paid option is available, show the contribution selector rather than guessing an amount.
- Has access: retain Start/Access action.
- Globally free: use the existing free-enrollment path.

For the approved resume intent, automatically trigger the same payment handler once after the dashboard has rendered and fresh access data confirms payment is still required. Guard this with a one-time consumed flag so refresh/back navigation cannot repeatedly open payment. If the browser or Razorpay blocks automatic opening, retain the targeted card and expose a clear `Pay & Enroll` retry button as the fallback.

### 7. Handle changed or unavailable state

If the target workshop is absent from the fresh dashboard response:

- show a non-destructive notice that the workshop is no longer available;
- keep the Workshops tab visible;
- do not attempt order creation.

If fresh data says access already exists, show the access action. If it says payment is required, show payment. Fresh server data wins over the pre-login lookup.

## Implementation steps

1. Add a helper or scoped logic in `public/class-ggm-shortcodes.php` to construct a configured, same-site dashboard resume URL containing the current workshop ID, currency, and Workshops tab hash.
2. Set `loginRedirect` to that URL for existing-account OTP login.
3. Change OTP success handling to honor `res.data.redirect`, with the prepared resume URL as fallback.
4. Audit and update profile-completion redirect handling so incomplete profiles return to the enrollment-resume URL after completion.
5. Add `data-workshop-id` to workshop cards in `assets/js/ggm-dashboard.js`.
6. Refactor the dashboard quick-buy logic into a callable function used by both a normal button click and the one-time automatic resume path.
7. Add a dashboard resume handler that runs only after successful dashboard-data rendering, selects Workshops, finds the target card, rechecks its fresh access state, scrolls/focuses it, and automatically starts payment only when required.
8. Add a short-lived highlight style and payment-continuation notice in `assets/css/ggm-dashboard.css`, with reduced-motion-safe behavior.
9. Consume/remove the automatic-payment query parameter before opening Razorpay to avoid loops on refresh, while retaining enough in-memory state for retry after dismissal/failure.
10. Keep `GGM_Razorpay::do_create_order()` and `GGM_Workshop::get_user_access_level()` authoritative; add no client-side access shortcut.
11. Update user-facing copy so “Login with OTP” clearly explains that payment will open automatically after login when the workshop is not yet enrolled, while an already-enrolled member will go directly to the dashboard.

## Expected files

- `public/class-ggm-shortcodes.php` — construct and use the enrollment-resume redirect; update OTP continuation copy.
- `modules/auth/class-ggm-auth.php` — only if required to carry the validated resume destination through profile completion.
- `assets/js/ggm-dashboard.js` — identify and focus the target workshop after data loads.
- `assets/css/ggm-dashboard.css` — target-card highlight and continuation notice styling.
- The profile-completion template/script — only if the audit during implementation confirms it currently discards the continuation URL.

No database schema change should be necessary.

## Security and data-integrity requirements

- Validate the workshop ID as an integer and verify its post type before generating resume state.
- Validate redirect destinations as same-site URLs.
- Never use the query parameter to grant access, set a price, or mark payment successful.
- Re-resolve price, currency eligibility, access, contribution option, and workshop availability on the server at order creation.
- Preserve nonce verification for lookup, OTP, order creation, and payment verification.
- Preserve duplicate-purchase prevention.
- Preserve the OTP's exact account binding; do not select an account solely from untrusted client state.
- Escape dashboard HTML and attributes as currently required.

## Acceptance criteria

1. A genuinely new visitor can still pay/register through the shortcode.
2. An existing account with no access is offered OTP login.
3. After successful OTP login, that member lands on the dashboard Workshops tab and the intended workshop's payment flow opens automatically when fresh data confirms no access.
4. A fixed-price workshop is paid through the existing dashboard Razorpay flow without requiring a second `Pay` click after OTP.
5. A contribution workshop with a preserved valid selection resumes payment automatically; a missing or stale selection falls back to a fresh amount-selection step.
6. After successful payment, the workshop changes to the enrolled/access state and no duplicate purchase is offered.
7. An existing account that already owns the workshop lands on the same target card but sees access/start controls; no order is created and Razorpay does not open.
8. An incomplete profile is completed first and then resumes the intended workshop enrollment.
9. Invalid, unpublished, expired, or removed workshop targets fail safely with a useful notice.
10. Refreshing the dashboard does not repeatedly open Razorpay or force-scroll after the resume state has been consumed.
11. Directly editing `ggm_enroll_workshop` in the URL cannot grant access, change price, or bypass payment.
12. Keyboard focus and mobile scrolling land on the intended card; dismissal or failure leaves an accessible `Pay & Enroll` retry action.

## Test matrix

### Account/access states

- new contact, fixed paid workshop;
- existing account, no access, fixed paid workshop;
- existing account, paid access already present;
- existing account, free access on a contribution workshop, upgrading to paid;
- existing account, no access, contribution workshop;
- logged-in account, no access;
- incomplete existing profile;
- administrator/manual access account.

### Workshop states

- published and active;
- multiple slots;
- no slots;
- unpublished after OTP begins;
- expired/hidden after OTP begins;
- price changed after OTP begins;
- globally free;
- contribution pricing.

### Failure paths

- invalid/expired OTP;
- stale nonce;
- mismatched phone/email under `both` validation;
- payment gateway fails to load;
- payment dismissed;
- payment fails;
- duplicate purchase attempted in another tab;
- resume URL contains an invalid workshop ID;
- dashboard AJAX fails, then succeeds on retry.

## Out of scope unless separately approved

- Storing enrollment intent permanently in user meta.
- Redesigning the dashboard workshop cards.
- Changing workshop prices, contribution rules, OTP delivery method, or access-grant rules.
- Creating a new payment endpoint when the dashboard quick-buy endpoint already covers the fixed-price case.

## Approval checkpoint

Implementation should begin only after approval of this updated plan. The approved target behavior is: authenticate first; route to the exact workshop on the dashboard; use fresh server data to decide whether access already exists; automatically open the existing payment flow only when payment is still required; and show the enrolled workshop after verified payment.
