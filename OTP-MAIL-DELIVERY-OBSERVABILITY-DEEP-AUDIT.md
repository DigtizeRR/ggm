# OTP Mail Delivery Observability — Deep Audit

**Status:** Audit only. No mail behavior, logging behavior, credentials, SMTP settings, DNS, or live messages were changed.

## Objective

Make the dashboard Error Log show the full, truthful lifecycle of an OTP or test email: where the application reached, which local SMTP stage failed when applicable, and whether a final recipient-delivery result is known or still external.

## Confirmed delivery incident

cPanel Track Delivery provides the missing downstream evidence for the recent failed sends:

```text
remote_smtp_smart_dkim → smtp.antispamcloud.com
550 Your account has been locked. Please contact your administrator.
```

This is a post-submission failure in the hosting provider's outbound smart relay. WordPress successfully hands the message to the local SMTP service, then the relay fails before delivery to Gmail. That exact outcome is not returned to `wp_mail()` and therefore is not available to the existing WordPress Error Log automatically.

## Current implementation audit

### What is already captured

The plugin already logs useful per-send information:

- intended recipient, masked;
- sender, host, port, encryption, authenticated-mailer state;
- PHPMailer Message-ID;
- `wp_mail()` boolean and local PHPMailer error;
- explicit `submitted_to_smtp` state for OTP sends;
- WordPress `wp_mail_failed` errors for local connection, authentication, recipient, content, and attachment failures.

The SMTP test also enables PHPMailer debug capture and parses the local SMTP conversation into connection, EHLO, STARTTLS, authentication, MAIL FROM, RCPT TO, DATA, and QUIT stages.

### Gaps

1. **OTP sends do not currently enable the same parsed SMTP transaction summary as the admin SMTP test.** A local failure during an OTP send can therefore have less stage detail than a test send.
2. **Records are separate, not one correlated lifecycle.** A single message can produce configuration, pre-send, completion, and OTP records without a shared attempt identifier or single status timeline.
3. **A local `250`/`wp_mail=true` is a submission result, not recipient delivery.** The current log correctly labels the OTP as submitted, but it cannot populate post-submission outcomes by itself.
4. **The Error Log is a shared 100-entry option-backed list.** Several records per email can push the relevant record out quickly and are not optimized for filtering a Message-ID or one mail attempt.
5. **There is no trusted inbound delivery-event channel.** cPanel Track Delivery/Exim and AntiSpamCloud have the relay result, but WordPress has no authenticated API, webhook, bounce mailbox, or log-reader integration configured to receive it.

## Hard boundary: what WordPress can and cannot fetch

| Lifecycle stage | Can current WordPress process observe it? | Source of truth |
| --- | --- | --- |
| Plugin creates OTP | Yes | Plugin request / transient creation |
| TCP, TLS, SMTP auth, MAIL FROM, RCPT TO, DATA response to configured SMTP server | Yes, with sanitized PHPMailer transaction capture | PHPMailer local SMTP session |
| Configured SMTP server accepts the message | Yes | PHPMailer / `wp_mail()` |
| Local server queues or relays to AntiSpamCloud | No | cPanel Exim / Track Delivery |
| AntiSpamCloud returns `550 account locked` | No, unless supplied through an authenticated external event source | AntiSpamCloud / Exim Track Delivery |
| Gmail accepts, defers, rejects, or filters the message | No, unless supplied through provider events or mailbox headers | Gmail / relay provider |

No PHP logging change can truthfully infer a later `550 account locked` from an earlier local `250` response. Displaying it as an automatic WordPress error without an external source would create false diagnostics.

## Privacy and security requirements

- Never log raw OTP values, email bodies, SMTP passwords, SMTP usernames, authorization headers, or AUTH payloads.
- Mask recipients consistently.
- Store only a sanitized SMTP-stage summary, not a full transcript. DATA payload content must never be retained.
- Restrict delivery-detail viewing to `manage_options` administrators.
- Any incoming provider/cPanel delivery event must be authenticated (HMAC/signature or provider API credential), validated against a known attempt Message-ID, rate-limited, and limited to an allowlisted event schema.
- Do not make cPanel, Exim, or AntiSpamCloud credentials browser-visible.

## Proposed implementation

### 1. One correlated mail-attempt record

Create a privacy-safe attempt record for every plugin-owned OTP and SMTP test email. It will use the generated Message-ID as the primary correlation value plus an internal opaque attempt ID. The record will group:

- type (`otp` or `smtp_test`), masked recipient, subject class, created time;
- configured local SMTP endpoint metadata (host/port/encryption only);
- local stages: `created`, `smtp_connecting`, `smtp_authenticated`, `recipient_accepted`, `submitted_to_smtp`, or `submission_failed`;
- sanitized response code/text for each observed local SMTP stage;
- Message-ID and local PHPMailer error, if any;
- final delivery state initially set to `unknown_after_submission` when the local server accepts the message.

### 2. Capture safe SMTP stage diagnostics for OTP sends

Use the existing PHPMailer debug callback for OTP sends as well as test sends, but retain only the parsed stage summary. Explicit redaction and a strict size limit will ensure DATA/body/OTP content, credentials, and unrecognized client payloads are never stored.

This will reveal actionable local failures such as connection timeout, TLS error, bad authentication, sender rejection, recipient rejection, or DATA rejection in the same OTP attempt record.

### 3. Replace ambiguous visual states

The Error Log will present a compact lifecycle status:

| Status | Meaning |
| --- | --- |
| `SUBMISSION FAILED` | WordPress/PHPMailer has a concrete local failure and its sanitized response. |
| `SUBMITTED — EXTERNAL DELIVERY UNKNOWN` | The configured SMTP server accepted the message; WordPress has no final relay/recipient result. |
| `DELIVERED` / `DEFERRED` / `BOUNCED` / `REJECTED` | Only shown when received from a trusted external delivery source. |

The current cPanel fact would be represented as `REJECTED` with source `cPanel/Exim relay`, remote host `smtp.antispamcloud.com`, and sanitized reason `550 Your account has been locked. Please contact your administrator.`

### 4. Add a trusted downstream event integration, selected by provider

To fetch results after submission, one of these must be chosen and configured:

- **Transactional-provider webhook/API (recommended):** A provider such as Postmark, SES, Brevo, or Mailgun posts signed delivered/deferred/bounced events to a new protected endpoint. This is the reliable automated path.
- **Hosting-provider/API integration:** Only if the hosting provider exposes an authenticated, documented Track Delivery/Exim event API. No such access is currently configured or assumed.
- **Manual cPanel outcome attachment:** An administrator can attach a sanitized Track Delivery result to a matching Message-ID. This accurately records the known cause but does not automatically fetch it.

The existing AntiSpamCloud/cPanel route is locked. It cannot supply automatic WordPress delivery status unless the hosting provider offers and authorizes a suitable integration.

### 5. Storage, UI, and retention

Use a dedicated mail-attempt storage model rather than relying solely on the generic 100-entry Error Log. The Error Log will retain a concise linked summary, while a Mail Delivery view will allow filtering by attempt ID, Message-ID, type, state, and date. Retention will be configurable with a safe default and include a deletion control.

## What this will and will not fix

This implementation will make local failures and external outcomes visible when an external event source is configured. It will not unlock the current AntiSpamCloud relay account or force Gmail delivery. The hosting provider must first restore the locked outbound relay or the site must use a verified transactional SMTP provider.

## Approval-gated delivery plan

1. Implement the correlated, redacted local SMTP lifecycle for OTP and test sends.
2. Add the Error Log/Mail Delivery UI with explicit `external delivery unknown` state.
3. Add a manual, administrator-only way to record a cPanel Track Delivery outcome against a Message-ID.
4. Add the provider-event endpoint only after the user chooses a provider or supplies documented hosting API/webhook details; do not create an unauthenticated generic endpoint.
5. Add automated checks for redaction, local failure stage mapping, default state, authorization, and retention.
6. Verify using a controlled local SMTP failure and, after the host unlocks the relay, a real message correlated through cPanel Track Delivery.

## Conclusion

The requested complete log is feasible only as a two-boundary lifecycle: WordPress can provide detailed local submission diagnostics; the mail host/provider must provide the final downstream result. The current exact cause—AntiSpamCloud returning `550 account locked`—must be imported from cPanel/Exim or delivered through a trusted provider integration. No implementation has been made in this audit phase.
