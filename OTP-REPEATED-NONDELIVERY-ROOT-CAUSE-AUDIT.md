# OTP Repeated Non-Delivery — Root-Cause Audit

**Status:** Audit only. No OTP code, SMTP setting, DNS record, mail-server setting, provider account, or test message was changed.

**Incident:** At 2026-09-15 11:05:13, an OTP and a plain SMTP test both reported successful submission but did not arrive at the intended Gmail mailbox.

## Executive conclusion

The repeated incident confirms that the website is successfully submitting mail to `mail.globalgoodhealthmission.com`, but it provides no evidence that this server is delivering mail to Gmail. The OTP code path is not the distinguishing cause: the plain test email fails in the same way.

The fault boundary is therefore the outbound mail server or the server-to-Gmail delivery path after SMTP submission. An application-only change cannot make Gmail receive mail when the configured SMTP server accepts messages but does not relay them successfully. The next root-cause record must come from the mail server's delivery queue/logs using the supplied Message-ID.

## Evidence reviewed

### Repeated message trace

The new trace for `<VN1W0hP8r5POM6XnPZqH3jByZ8d1WduhF88yoJE4fIg@globalgoodhealthmission.com>` shows:

- `wp_mail_result: true`, SMTP mailer, no local PHPMailer error.
- Authenticated SSL submission to `mail.globalgoodhealthmission.com:465`.
- Sender `info@globalgoodhealthmission.com` and the intended masked Gmail recipient.
- `delivery_status: submitted_to_smtp` and `stage: smtp_submission`.

This is consistent with the previous incident and with the updated application diagnostic semantics: **submitted** means the configured SMTP server accepted the message from WordPress. It is not a Gmail delivery receipt.

The same non-arrival occurs for the plugin's plain SMTP test. That removes OTP generation, OTP template content, OTP expiry, login flow, and recipient-selection logic as likely causes of the delivery failure.

### Application path

`ggm_send_email_otp()` calls `ggm_send_plugin_mail()`, which returns the result of `wp_mail()`. The plugin correctly records the Message-ID, SMTP host/port, masked recipient, and submission state. WordPress does not receive a later relay result from a conventional SMTP server, so it cannot report Gmail acceptance, deferral, bounce, or mailbox placement.

No WordPress-side error appears in the supplied trace. Repeatedly resending from the dashboard is unlikely to diagnose this and can make delayed messages harder to distinguish.

### Read-only external checks on 2026-09-15

| Check | Result | Meaning |
| --- | --- | --- |
| SMTP TCP reachability | `mail.globalgoodhealthmission.com:465` reachable at `69.57.172.153` | The website can reach the submission service; this does not test mail delivery. |
| SMTP TLS certificate | TLS 1.3 certificate is valid on the audit date; its SAN includes `mail.globalgoodhealthmission.com` | The hostname is covered by the presented certificate. |
| MX | `globalgoodhealthmission.com` resolves to `69.57.172.153` | Inbound/MX and submission service are colocated on the same host. |
| SPF | Includes `+a`, `+mx`, `ip4:69.57.172.153`, and `spf.mysecurecloudhost.com` | The public record appears to authorize the known host, but cannot prove the actual outbound relay IP or alignment. |
| DKIM | A public `default._domainkey` key exists | A public key does not prove the submitted message was signed with that selector or that its signature passed. |
| DMARC | `p=none` | Monitoring-only policy; it does not establish deliverability. |
| Reverse DNS | `69.57.172.153` resolves to `s11776.bom1.stableserver.net` | The reverse name differs from the visible mail hostname. This may be normal for shared hosting, but it must be assessed with the server's EHLO identity and Gmail response. |

## Confirmed versus unconfirmed facts

| Confirmed | Not yet evidenced |
| --- | --- |
| WordPress authenticates to and submits the message to the configured SMTP server. | The SMTP server attempted delivery to Gmail. |
| The submission server accepts both OTP and plain test messages. | Gmail accepted the message, deferred it, rejected it, or placed it in Spam/another mailbox category. |
| A Message-ID is available for correlation. | The server queue ID, outbound IP, EHLO name, TLS result to Gmail, SPF/DKIM/DMARC result, or bounce response. |

## Most likely root-cause categories

1. The cPanel/shared-host SMTP server has queued, deferred, rejected, or discarded the message after accepting it from WordPress.
2. Gmail rejects or defers the host's outbound connection because of IP reputation, reverse DNS/EHLO identity, rate/abuse limits, authentication alignment, or content policy.
3. The message is accepted but filtered into Spam, Promotions, All Mail, or a Gmail rule.
4. The sender or recipient is subject to a provider suppression, quota, or abuse control.

There is insufficient evidence to select one category. DNS records alone cannot determine the outcome.

## Required root-cause evidence

The mail-host administrator must use cPanel **Track Delivery**, Exim logs, or hosting-provider support to search for the exact Message-ID and full recipient address. Capture the following non-secret operational facts:

1. The local queue ID and accepted timestamp.
2. Each remote delivery attempt to Gmail and its SMTP code/text.
3. Final state: delivered, deferred, bounced, discarded, or still queued.
4. The actual outbound IP, EHLO/HELO identity, envelope sender, TLS result, and DKIM selector/result.
5. Any suppression, rate-limit, spam, or abuse event.

On Gmail, search Inbox, Spam, All Mail, Promotions, and filters for the Message-ID/subject/sender. If found, inspect raw headers for `Authentication-Results` and the `Received` chain. Do not expose the OTP value or SMTP credentials.

## Approval-gated remediation options

### Option A — Repair the existing mail host

Use the mail-server trace to correct the exact queue, relay, reverse-DNS/EHLO, DKIM-signing, SPF/DMARC-alignment, suppression, or reputation issue. This is the least disruptive route if the hosting provider can demonstrate reliable Gmail acceptance. It requires access to the hosting/cPanel mail logs and potentially DNS/hosting changes.

### Option B — Move transactional OTP mail to a dedicated provider

Configure an approved transactional provider for the plugin's SMTP route. The provider must expose accepted, delivered, deferred, bounced, and suppressed events. This avoids relying on a shared-host outbound relay for login-critical email, but requires the user to choose a provider and supply/configure its verified sender and credentials. No provider or credentials have been selected in this audit.

### Option C — Application diagnostic enhancement only

Add deeper application correlation/reporting. This can improve support workflows but cannot repair server-to-Gmail delivery, so it is not a standalone fix for this incident. The current logs already record the required Message-ID and submission boundary.

## Recommended sequence

1. Obtain the server's Track Delivery/Exim result for the Message-ID above.
2. If the server reports a precise Gmail rejection/defer, repair that exact mail-host/DNS/provider condition and retest.
3. If the server cannot provide a reliable delivery trace or continues failing Gmail delivery, select a transactional provider and route OTP mail through it.
4. After remediation, send one controlled OTP and one plain test to Gmail and another provider; verify final mail-server status and mailbox headers before relying on the route for login.

## Conclusion

The application changes are working as designed: they now show SMTP submission rather than falsely claiming recipient delivery. The actual non-delivery is downstream of WordPress and needs mail-server evidence or a dedicated transactional delivery service. No remediation has been implemented in this audit phase.
