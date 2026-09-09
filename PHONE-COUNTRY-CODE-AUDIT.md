# Phone and WhatsApp Country-Code Audit

## Standard

Every member-facing phone or WhatsApp input must:

- render the country code and local number as one combined control;
- provide a searchable country list with flag, country name, ISO code, and dial code;
- default to India (`+91`) when no valid saved selection exists;
- submit/store the selected dial code separately from the local number;
- retain the existing validation, authentication, checkout, and enrollment flow.

## Audited collection points

| Surface | Previous state | Current state |
|---|---|---|
| `[ggm_workshop_join_form]` | Plain phone input | Shared searchable picker; defaults `+91`; forwards dial code to checkout |
| `[ggm_workshop_join_form_full]` | Plain phone input and hardcoded `+91` during payment | Shared searchable picker; selected dial code sent to order/payment flow |
| Checkout contact details | Small fixed `<select>` with only ten countries | Full searchable country list; defaults/prefills `+91` |
| Signup | Plain optional phone input | Shared searchable picker; selected code saved to user metadata |
| Login by phone/email | Mixed plain identifier input | Searchable picker shown for phone entry and hidden automatically for email entry |
| Forgot password by phone/email | Mixed plain identifier input | Same searchable phone behavior as login |
| First-login profile completion | Fixed ten-country `<select>` | Full searchable country list; defaults `+91` |
| Dashboard profile | Already searchable | Retained; saved country code defaults safely to `+91` |
| Legacy workshop health intake | Plain WhatsApp input | Shared searchable picker; country code saved with intake and user profile |
| Generated health forms | Picker existed only for a special numeric-field toggle | All fields with `Phone` validation now automatically render and validate with the searchable picker |

## Shared implementation

- Country source and markup: `GGM_Form_Builder::country_calling_codes()` and `country_picker_html()`.
- Shared interaction: `assets/js/ggm-country-picker.js`.
- Shared presentation: `assets/css/ggm-country-picker.css`.
- Asset versions use file modification times to prevent stale cached controls.

## Data handling

- Local phone digits remain in the existing phone fields/meta keys to preserve account matching and integrations.
- The selected dial code is stored separately in `ggm_whatsapp_country_code` where profile data is involved.
- Workshop health-intake records now include `country_code`.
- Workshop order creation now receives the selected `contact_country_code` instead of a hardcoded `+91`.
- Invalid/missing dial selections fall back server-side to `+91`.

## Out of scope

Administrative CSV import columns, API payloads, WhatsApp provider configuration numbers, and phone numbers displayed only as read-only text are not interactive phone-number collection fields and therefore do not receive a UI picker.
