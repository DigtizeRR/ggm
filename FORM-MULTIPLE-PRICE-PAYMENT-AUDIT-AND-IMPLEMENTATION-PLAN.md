# Form Multiple-Price Payment Audit and Implementation Plan

## Purpose

Add multiple named paid price choices to a Form Builder form. A visitor will see the choices as a single-select radio group, must select exactly one choice, and will be charged only that server-approved amount through the existing Razorpay payment flow.

This plan applies to the Form Builder payment add-on in **GGM Admin → Health Forms → Form Settings**. It does not alter workshop/course contribution pricing, which is a separate feature with its own settings and checkout flow.

## Implementation status

Implemented on the approved scope. The code now saves named paid choices, renders a required single-choice radio group, resolves the selected ID server-side before Razorpay order creation, snapshots the chosen heading/amount on the existing payment record, and retains a legacy fixed-price fallback for older forms. PHP syntax checks passed; live Razorpay test-mode checks remain required in the WordPress environment.

## Audit findings

### Existing configuration

The form settings currently contain one fixed payment amount:

- `payment_enabled` enables the paid-submit replacement.
- `payment_amount` is one decimal amount.
- `payment_label`, `payment_currency`, `payment_description`, and `payment_success` control the checkout text and result message.
- `payment_flow` supports `submit` (complete fields, then pay) and `before_form` (pay, then reveal fields).

The settings are saved in `settings_json` by `GGM_Form_Builder::save_form()` in `modules/workshop/class-ggm-form-builder.php`.

### Current visitor/payment flow

1. `render_form()` determines whether payment is enabled and renders a single `Pay & Submit` button on the final page. With `before_form`, it first renders a payment-only screen.
2. The browser validates the current form page, posts all form data to `ggm_create_form_payment`, then opens Razorpay with the order returned by the server.
3. `create_submission_from_request('pending_payment')` creates a pending form submission. The order endpoint reads `payment_amount` from saved settings, creates the Razorpay order, and stores one `ggm_form_payments` record.
4. Razorpay returns the order ID, payment ID, and signature. `ggm_verify_form_payment` checks that the payment belongs to the current user/guest token, confirms the order ID, and validates the Razorpay signature before marking both payment and submission successful.
5. The Payment admin screen already combines Form payment records with purchase records, including the saved form payment label and description.

### Data available for price snapshots

`ggm_form_payments` already persists `payment_key`, `label`, `amount`, and `currency`. This is sufficient to retain the exact selected price after a later form edit; no database table change is required for the requested feature.

### Relevant safety and behaviour observations

- The server currently derives the amount only from the saved fixed amount, not from the browser, which is the correct trust boundary. The new selected choice must retain this property.
- A paid form may allow guests. Its pending-payment ownership is protected by a random token whose hash is stored with the temporary submission; the design must preserve that behaviour.
- Payment verification relies on the Razorpay signature and a pending payment record. The implementation should make the final status update conditional on `pending` to make a duplicate verification request idempotent.
- Existing pending transactions store the old single-price snapshot. Editing a form must never change their amount, label, or ability to be verified.
- There is no automated test suite in this repository, so the implementation must include targeted PHP linting plus manual Razorpay test-mode scenarios.

## Proposed product behaviour

When **Payment Button Add-on** is enabled, replace the single Amount field with **Payment price choices**:

| Admin input | Visitor display | Stored payment snapshot |
|---|---|---|
| Price heading (required) | Radio-choice label | `label` |
| Amount (required, greater than zero) | Formatted amount beside heading | `amount` |
| Currency (one setting for the form) | Currency used for every choice | `currency` |

- The admin can add, reorder, and remove choices.
- Each choice receives an opaque, stable ID when saved. It is not based on its row position, heading, or amount.
- On the visitor form, the choices appear before the payment button as one labelled `fieldset`/`legend` radio group. Only one can be selected at a time.
- No choice is preselected. A clear inline error is shown if the visitor tries to pay without selecting one.
- The selected heading and amount are shown in Razorpay’s description and saved with the payment record; the selected amount determines the Razorpay order amount.
- The same selector appears on both supported timings: `submit` and `before_form`.
- All choices are paid choices. A zero/free option is intentionally out of scope because this feature is for a form with payment enabled; free registration remains the normal no-payment form flow.

## Backward compatibility

Published forms using the current fixed amount will continue to work without an admin migration:

1. At runtime, if a paid form has no saved `payment_options`, derive one legacy choice from its existing `payment_amount` and `payment_label`.
2. When that form is next saved, persist the derived choice into the new options format.
3. Existing form-payment rows remain unchanged and remain visible in the Payment screen.
4. New code will continue to understand a fixed amount as the legacy fallback, so a failed/abandoned older page cannot be charged an arbitrary amount.

## Implementation plan

1. **Create one shared price-choice normalizer/resolver** in `modules/workshop/class-ggm-form-builder.php`.
   - Validate an array of choices: stable ID, non-empty heading, amount rounded to two decimals and greater than zero.
   - Reject duplicate IDs and duplicate normalized headings.
   - Provide the legacy fixed-price fallback and a lookup by selected option ID.
   - Keep the resolver server-side and use it for save, render, and order creation so pricing cannot drift across code paths.

2. **Update the Form Builder admin UI** in `admin/views/health-forms.php`.
   - Replace the single Amount input with an accessible repeater: heading, amount, remove control, and “Add price choice.”
   - Keep the existing currency, button label, checkout description, success message, and payment-flow controls.
   - Use the existing form-builder client-side state pattern to generate temporary client IDs for added rows; the server will assign/retain canonical IDs.
   - Add concise guidance that visitors must select one price and that the heading is saved on the payment record.

3. **Save and version the new settings safely** in `save_form()`.
   - Read `payment_options` from the form submission, sanitize and validate it, and persist it in `settings_json`.
   - Disable payment if the add-on is off or no valid paid option exists.
   - Preserve `payment_amount` only as a compatibility fallback; new configurations use `payment_options` as the authority.
   - Return the administrator to the editor with an error if enabled payment has no valid choice rather than silently creating a broken paid form.

4. **Render the required single-choice selector** in `render_form()`.
   - Render a semantic radio group with unique input IDs and a visible price heading plus formatted amount.
   - Place it inside the active form page so it participates in both client-side validation and the existing `FormData` request.
   - Include the selector on the payment-first screen, before the payment button.
   - Add responsive styles and keyboard/focus states consistent with the existing built-form controls.

5. **Resolve the selected option exclusively on the server** in `create_form_payment()`.
   - Receive only `payment_option_id` from the browser.
   - Resolve it against the form’s saved, normalized options; reject missing, stale, forged, or disabled values before creating a submission/order.
   - Use the resolved snapshot—not a submitted amount—to create the Razorpay order.
   - Store the option ID in `payment_key`, and snapshot its heading, amount, and currency in the existing `ggm_form_payments` row.
   - Ensure error handling removes only the newly created pending submission when order creation cannot proceed, as it does today.

6. **Preserve verification correctness and improve idempotency** in `verify_form_payment()`.
   - Continue verifying payment ownership/guest token, matching Razorpay order ID, and Razorpay HMAC signature.
   - Finalize a payment only when its database status is still `pending`; return an appropriate already-processed result for repeats rather than applying submission effects twice.
   - Drive the confirmation view from the payment record’s saved snapshot, so changing price choices while checkout is open does not corrupt an in-flight purchase.

7. **Keep administrative reporting accurate** in `admin/views/payments.php`.
   - Verify that the existing Form payment source row and detail view display the saved selected heading and amount.
   - Adjust labels only if needed to clearly distinguish a price-choice heading from the payment button label.

8. **Update documentation** in `readme.txt` and the Health Forms/help text to explain configuration and the one-choice rule.

## Verification checklist

### Automated/static checks

- Run `php -l` on each changed PHP file.
- Search for all uses of `payment_amount` and confirm each is either legacy compatibility or intentionally removed from new-price resolution.
- Search for all form payment order/verification calls and confirm `payment_option_id` is handled only by the form-specific flow.

### Manual test-mode checks

1. Create a guest-enabled paid form with three choices, e.g. `Basic — ₹499`, `Standard — ₹999`, and `Premium — ₹1,499`.
2. Confirm the admin cannot save enabled payment with a blank heading, zero/negative amount, or no choices.
3. Confirm one radio choice can be selected at a time and the pay attempt is blocked until one is selected.
4. For each option, use Razorpay test mode and verify the Razorpay amount, saved payment amount, selected heading, and form submission status all match.
5. Test both `Pay when submitting form` and `Pay before showing form fields`, for logged-in users and guests where guests are enabled.
6. Cancel checkout, retry with a different choice, and verify no false successful submission appears.
7. Change/remove a choice after an order is created but before it is verified; verify that the original pending payment still completes using its stored snapshot.
8. Re-submit/refresh the Razorpay success callback and verify the payment/submission is not double-finalized.
9. Confirm an existing single-price paid form renders and charges exactly as before, then save it and verify its migrated single choice behaves identically.

## Files expected to change

- `modules/workshop/class-ggm-form-builder.php`
- `admin/views/health-forms.php`
- `admin/views/payments.php` only if its labels need clarification
- `readme.txt`
- Any existing form-builder public CSS/JS location that is necessary after implementation

No workshop contribution-pricing code or payment table migration is expected.

## Approval required

Please approve this plan before I implement it. Approval authorizes the Form Builder-only scope above, including the backward-compatible settings format, server-side price resolution, and the verification/idempotency hardening.
