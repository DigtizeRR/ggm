=== Digtize LMS System ===
Contributors: digtize
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private member dashboard, membership, workshop, lesson, payment, and OTP system.

== Changelog ==

= 1.1.6 =
* Show Form Builder progress indicators only when a form has multiple visible pages.
* Added per-price-card width controls (100%, 50%, or 30%) and a clean non-radio display for single-price forms.
* Added named multi-price choices to the Form Builder payment add-on. Visitors choose exactly one server-validated price before Razorpay checkout.
* Preserved existing fixed-price paid forms as a compatible single price choice.
* Added the resolved per-workshop or global section heading to all four Webinar Additional Block shortcodes.
* Kept empty repeaters completely hidden so a heading is never rendered without content.
* Styled Additional Block headings with centered Space Grotesk at 32px, semi-bold, and 1.1em line height.
* Kept standalone workshop detail blocks in an icon-left, stacked-text layout on mobile.
* Added show_heading="no" to Additional Block shortcodes for Elementor layouts that already contain a separate edited heading.

= 1.1.5 =
* Stabilized the workshop OTP state so background account lookups cannot reset a delivered code.
* Hid the Send OTP button after successful delivery and kept only the OTP input and Verify button visible.
* Redirected every successful workshop OTP login directly to /dashboard/.

= 1.1.4 =
* Reused the OTP verifier's bound user result instead of repeating email and user-meta lookups.
* Deferred new-account welcome email SMTP delivery so it no longer blocks OTP verification responses.
* Preserved WordPress login hooks, one-time OTP claims, session binding, and replay protection.

= 1.1.3 =
* Made the workshop email the authoritative OTP account identity.
* Prevented duplicate or stale imported phone records from selecting a different WordPress user.
* Kept the submitted phone as contact information without allowing it to redirect authentication.

= 1.1.2 =
* Fixed real workshop OTP delivery to use the exact registered email entered with the matching account phone.
* Rejected mismatched or unregistered workshop email addresses before sending a code.
* Displayed the masked real OTP destination and surfaced server delivery errors in the workshop form.

= 1.1.1 =
* Enforced email-only OTP delivery through Custom SMTP for login, workshop, checkout, and password-reset flows.
* Bound workshop OTP identifiers to the same resolved account to prevent cross-account verification.
* Disabled production fixed-code demo mode and removed SMS/WhatsApp OTP runtime configuration.
* Kept OTP codes hashed, session-bound, attempt-limited, expiring, and single-use.
* Masked recipient email addresses in SMTP and mail-failure diagnostics.

= 1.0.7 =
* Added a Featured YouTube Video URL to workshops.
* Added the GGM Workshop Featured Video URL Dynamic Tag for Elementor's native Video widget.
* Removed the former [ggm_workshop_featured_video] shortcode.
* Added strict shared validation for YouTube watch, share, embed, Shorts, mobile, and privacy-enhanced URLs.
* Added global Settings controls for Webinar Additional Block headings while preserving the existing block shortcodes.
* Added four Elementor text Dynamic Tags for the global workshop block headings.
* Restored the Discover, Why Different, Perfect For, and FAQ shortcodes to content-only output without automatic headings.
* Applied the four global heading settings to their matching Webinar Additional Block labels on the Workshop edit screen.
* Changed [ggm_workshop_duration] to output the saved Duration exactly, without automatic prefix or suffix text.
* Renamed the Workshop editor field label to “Label Duration” while preserving its existing duration meta key and saved values.

= 1.0.2 =
* Added course short-description shortcodes.
* Course images now consistently use the Featured Image.
* Added automatic lesson counts and multi-mentor course instructors.
* Added controlled course-language selection and YouTube URL lesson videos.
* Removed obsolete thumbnail, lesson-label, instructor, and duration fields.

= 1.0.1 =
* Fixed first-hover overlay rendering issue in [ggm_lesson_video].
* Improved video initialization and thumbnail rendering.
* Removed unnecessary overlay during pre-play state.
* Minor code cleanup and performance improvements.
