# OTP Email Delivery Deep Audit

**Status:** Audit only — no implementation, mail configuration, DNS, database, or live-message changes have been made.

**Scope:** Why the login flow reports that an OTP was sent while the intended Gmail mailbox does not receive it.

## Executive finding

The supplied event is a successful **application-to-submission-server handoff**, not proof of end-to-end mailbox delivery.

At 2026-09-15 10:45:14, WordPress/PHPMailer reported `wp_mail=true`, no local PHPMailer error, and an SMTP submission to `mail.globalgoodhealthmission.com:465` over SSL. This demonstrates that the configured SMTP server accepted the message from the website. It does **not** demonstrate that the SMTP server subsequently relayed it to Gmail, that Gmail accepted it, or that Gmail placed it in Inbox, Spam, or All Mail.

The dashboard currently turns that submission result into the visible wording “OTP sent to …”. Consequently, the UI can state that an OTP was sent even when a later mail-server, recipient-provider, filtering, queue, or bounce event prevents the recipient from seeing it.

The exact downstream cause cannot be established from WordPress-side logs alone. The next decisive evidence is the outbound MTA queue/log entry for the supplied Message-ID and recipient, plus the Gmail-side disposition if Gmail accepted the message.

## Evidence reviewed

### Supplied delivery trace

The supplied trace records all of the following for the same message:

- SMTP host `mail.globalgoodhealthmission.com`, port `465`, SSL, authenticated SMTP.
- Envelope/from sender `info@globalgoodhealthmission.com`.
- PHPMailer mailer type `smtp`, no reported local mailer error.
- `wp_mail_result: true`.
- A generated Message-ID: `<d5ETXJtocC7OVDWsB9JoXrXoRobnYqTXGFDQnVW0eQ@globalgoodhealthmission.com>`.

It does not contain a Gmail SMTP response, a remote queue ID, a successful handoff to a Gmail MX host, a bounce/DSN, or Gmail message headers. Those omissions limit the claim to SMTP submission acceptance.

### Plugin behavior

The source confirms the same semantics as the log:

- `ggm_send_plugin_mail()` returns the boolean returned by WordPress `wp_mail()` ([includes/class-ggm-helpers.php](includes/class-ggm-helpers.php)).
- `ggm_send_email_otp()` labels a true result as `SMTP transport accepted message (wp_mail=true)` ([includes/class-ggm-helpers.php](includes/class-ggm-helpers.php)). The label is technically accurate about the local transport, but is not an end-to-end delivery receipt.
- `GGM_OTP::send()` accepts that boolean as successful OTP dispatch, keeps the OTP request, and returns `OTP sent successfully.`
- The login and password-reset interfaces turn any successful AJAX response into `OTP sent to …` ([assets/js/ggm-login.js](assets/js/ggm-login.js)).

This is the confirmed product-level cause of the misleading success indication. It is not evidence that the OTP generator, recipient address selection, or SMTP connection failed.

### Recipient selection

For live delivery, the plugin sends only to the registered email on the resolved WordPress account. It prefers the matching account email and otherwise can use the account's `user_email`, billing email, or checkout email. A phone-number login does not send to the phone number. The masked `intended_recipient` in the supplied trace is the address selected by that server-side rule.

### Read-only network and DNS checks on 2026-09-15

- TCP connectivity from this environment to `mail.globalgoodhealthmission.com:465` succeeded at `69.57.172.153`. This proves only that the submission port is reachable; no test email or authentication attempt was made.
- The domain's MX record points to `globalgoodhealthmission.com`, which resolves to `69.57.172.153`; `mail.globalgoodhealthmission.com` is a CNAME to that hostname.
- The published SPF record includes `+a`, `+mx`, `ip4:69.57.172.153`, and `include:spf.mysecurecloudhost.com`.
- The published DMARC record is `v=DMARC1; p=none;` (monitoring-only policy).
- The reverse DNS name for `69.57.172.153` is `s11776.bom1.stableserver.net`, not the visible mail hostname.

These records are useful clues, not a pass/fail delivery verdict. The audit could not determine the actual outbound sending IP, SMTP EHLO name, DKIM selector/signature, provider queue state, Gmail's reply, or Gmail filtering disposition. DKIM cannot be conclusively audited without a signed delivered message/header or mail-server configuration because the selector is not known from the supplied trace.

### Runtime evidence limitation

The local WordPress database service was unavailable during the read-only audit, so saved local SMTP settings were not read. The local `wp-content` search did not contain the supplied Message-ID or matching trace. This is consistent with the trace originating from another environment or a database-backed application log. No attempt was made to start services, modify settings, or send a probe message.

## Probable failure boundary

```text
Browser → WordPress OTP code → authenticated SMTP submission server  [confirmed]
                                      ↓
                             outbound MTA queue / relay              [not evidenced]
                                      ↓
                             Gmail SMTP acceptance                   [not evidenced]
                                      ↓
                             Gmail filtering / mailbox placement     [not evidenced]
```

Most likely categories to investigate at the unconfirmed boundary are:

1. The submission server queued, deferred, or bounced the message after accepting it from WordPress.
2. Gmail rejected or deferred the server's outbound connection because of sender authentication, alignment, IP reputation, reverse DNS/EHLO identity, TLS, or rate/abuse controls.
3. Gmail accepted the message but placed it in Spam, Promotions, All Mail, or a rule-filtered location.
4. A delayed bounce or provider-side suppression list exists for the recipient.

No category above should be treated as the root cause until the message is traced at the MTA using the Message-ID and recipient.

## Risks in the current behavior

- Members can be locked out even though the UI tells them an OTP was sent.
- Resends can invalidate a prior code after each new successful SMTP submission, while the recipient may receive delayed or reordered messages.
- Staff may interpret a green application log as proof of Gmail delivery and investigate the wrong system.
- The current log's Message-ID is valuable for correlation but there is no visible delivery-status lifecycle (submitted, accepted by remote, deferred, bounced, delivered) in the dashboard.

## Required evidence before changing infrastructure

The mail-server administrator should search the logs/queue for the exact supplied Message-ID and the full recipient address, then capture only the operational result (not credentials):

1. Time accepted from WordPress and the MTA queue ID.
2. Every relay attempt and the Gmail SMTP response code/text.
3. Whether it was delivered, deferred, bounced, discarded, or remains queued.
4. The outbound IP, EHLO/HELO hostname, TLS result, envelope sender, and DKIM signing selector/result.
5. Any suppression, quota, spam, or abuse event for the recipient.

On the Gmail account, search Inbox, Spam, All Mail, Promotions, and filters for the Message-ID/subject/sender. If a copy is found, inspect its raw headers for `Authentication-Results` (SPF, DKIM, and DMARC) and the `Received` chain. Do not share the OTP code or SMTP password.

## Proposed implementation plan — pending approval

### Application changes

1. Change customer-facing success wording from a delivery assertion to a truthful submission state, for example: “We’ve requested an OTP email to … Check Inbox and Spam; delivery can take a few minutes.”
2. Update the admin SMTP/OTP test feedback with the same distinction: accepted by the configured SMTP service versus confirmed recipient delivery.
3. Add a privacy-safe delivery correlation record for each OTP submission: timestamp, masked recipient, generated Message-ID where available, SMTP host, and state `submitted_to_smtp`. Do not log OTP values, passwords, or usernames.
4. Improve the administrator troubleshooting panel to show the Message-ID and explicitly direct staff to the MTA queue/logs for downstream delivery status.
5. Preserve existing failure behavior: when `wp_mail()` returns false, do not issue an OTP success response and do not retain the newly generated request.

### Mail infrastructure remediation (only after MTA evidence identifies the failure)

1. Correct the specific relay, queue, suppression, or Gmail response identified in the server logs.
2. Verify SPF alignment for the actual outbound IP and envelope sender, configure/verify DKIM signing, and review DMARC alignment/reporting.
3. Ensure outbound reverse DNS and EHLO identity are coherent with the sending domain and provider requirements.
4. If the current host cannot reliably deliver transactional OTP mail, move only the outbound transactional route to an approved provider that exposes delivery, defer, bounce, and suppression events.

No DNS, provider, credential, or server change is proposed as an automatic fix; the MTA trace must select the appropriate remediation.

## Approval-gated verification plan after implementation

1. Run a controlled OTP send to an approved test Gmail mailbox and a second mailbox on another provider.
2. Record the application correlation ID/Message-ID and inspect the MTA outcome for both sends.
3. Confirm Gmail placement and raw authentication headers without exposing OTP values.
4. Test a forced SMTP submission failure to ensure the UI does not report success.
5. Test resend ordering and confirm only the intended current OTP remains valid.
6. Run existing plugin checks and confirm no non-OTP email behavior regresses.

## Audit conclusion

The supplied success records are internally consistent: the dashboard successfully submitted the message to its configured SMTP server. They do not prove Gmail delivery. The confirmed application issue is an overconfident success label; the unconfirmed delivery issue lies downstream of SMTP submission and requires the MTA/Gmail trace identified above. No implementation has been performed.
