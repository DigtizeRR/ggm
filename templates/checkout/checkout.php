<?php
/**
 * Checkout template — Razorpay payment form.
 *
 * Rendered by the [ggm_checkout] shortcode. Supports two modes:
 *   - Workshop checkout ($workshop is set)
 *   - Course checkout ($course is set)
 *
 * Membership checkout was removed along with the Membership system —
 * every workshop/course is purchased directly. Time-slot selection was
 * also removed from checkout: a workshop purchase grants access to every
 * enabled slot automatically (see GGM_Workshop_Slot::get_current_or_next()
 * for how the dashboard picks which one "Start Learning" points at).
 *
 * Checkout is temporarily guest-enabled: visitors enter their contact
 * details and proceed without logging in or verifying an OTP.
 *
 * @package GGM_Member_Dashboard
 * @var int      $workshop_id
 * @var int      $course_id
 * @var WP_Post  $workshop
 * @var WP_Post  $course
 * @var string   $currency_symbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Pre-fill Name/Email/WhatsApp Number when arriving from
// [ggm_workshop_join_form] or [ggm_workshop_join_form_full] (or any link
// appending these params) — falls back to account data further below for
// logged-in visitors when not present. Reject non-scalar query values so a
// malformed array parameter cannot trigger a sanitization warning.
$raw_prefill_name  = isset( $_GET['ggm_name'] ) ? wp_unslash( $_GET['ggm_name'] ) : '';
$raw_prefill_email = isset( $_GET['ggm_email'] ) ? wp_unslash( $_GET['ggm_email'] ) : '';
$raw_prefill_phone = isset( $_GET['ggm_phone'] ) ? wp_unslash( $_GET['ggm_phone'] ) : '';
$raw_prefill_country = isset( $_GET['ggm_country_code'] ) ? wp_unslash( $_GET['ggm_country_code'] ) : '+91';
$prefill_name      = is_scalar( $raw_prefill_name ) ? sanitize_text_field( (string) $raw_prefill_name ) : '';
$prefill_email     = is_scalar( $raw_prefill_email ) ? sanitize_email( (string) $raw_prefill_email ) : '';
$prefill_phone     = is_scalar( $raw_prefill_phone ) ? preg_replace( '/\D/', '', (string) $raw_prefill_phone ) : '';
$prefill_country   = is_scalar( $raw_prefill_country ) ? sanitize_text_field( (string) $raw_prefill_country ) : '+91';

$is_logged_in = is_user_logged_in();
$is_contribution      = false;
$contribution_snapshot = null;
$contribution_error    = '';

// Common country codes for the WhatsApp Number selector (Task 10) —
// defaults to India. This is a lightweight, self-contained list rather
// than pulling in a third-party JS library, so checkout never depends on
// an external CDN being reachable.
$country_codes = array(
	'+91'  => '🇮🇳 +91',
	'+1'   => '🇺🇸 +1',
	'+44'  => '🇬🇧 +44',
	'+971' => '🇦🇪 +971',
	'+966' => '🇸🇦 +966',
	'+61'  => '🇦🇺 +61',
	'+65'  => '🇸🇬 +65',
	'+49'  => '🇩🇪 +49',
	'+33'  => '🇫🇷 +33',
	'+64'  => '🇳🇿 +64',
);

if ( ! empty( $workshop ) ) {
	$type           = 'workshop';
	$item_id        = $workshop->ID;
	$item_name      = $workshop->post_title;
	$regular_price  = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_regular_price( $workshop->ID ) : 0.0;
	$sale_price     = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_sale_price( $workshop->ID ) : 0.0;
	$base_price     = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::resolve_price( $workshop->ID ) : (float) get_post_meta( $workshop->ID, 'workshop_money', true );
	$mode           = get_post_meta( $workshop->ID, 'workshop_mode', true );
	$duration_label = get_post_meta( $workshop->ID, 'duration', true );
	$linked_course  = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_linked_course( $workshop->ID ) : null;
	$is_contribution = class_exists( 'GGM_Workshop' ) && GGM_Workshop::is_contribution( $workshop->ID );
	$contribution_id_supplied   = array_key_exists( 'contribution_option_id', $_GET );
	$raw_contribution_id        = $contribution_id_supplied ? wp_unslash( $_GET['contribution_option_id'] ) : '';
	$requested_contribution_id  = is_scalar( $raw_contribution_id ) ? sanitize_key( (string) $raw_contribution_id ) : '';

	if ( $is_contribution ) {
		// Older/direct checkout links do not contain an option ID. Preserve
		// those entry points by deliberately locking them to the configured
		// default; an explicitly supplied stale/invalid ID must never silently
		// change to a different contribution.
		if ( ! $contribution_id_supplied ) {
			$default_contribution      = GGM_Workshop::get_default_contribution( $workshop->ID );
			$requested_contribution_id = $default_contribution['id'] ?? '';
		}

		$contribution_snapshot = GGM_Workshop::resolve_purchase_price( $workshop->ID, $requested_contribution_id );
		if ( ! empty( $contribution_snapshot['valid'] ) ) {
			$base_price = (float) $contribution_snapshot['amount'];
		} else {
			$base_price          = 0.0;
			$contribution_error = $contribution_snapshot['message'] ?? __( 'The selected contribution is no longer available.', 'ggm-member-dashboard' );
		}
	}
} else {
	$type           = 'course';
	$item_id        = $course->ID;
	$item_name      = $course->post_title;
	$regular_price  = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::parse_price( get_post_meta( $course->ID, 'course_price', true ) ) : (float) get_post_meta( $course->ID, 'course_price', true );
	$sale_price     = 0.0;
	$base_price     = $regular_price;
	$instructor     = implode( ', ', ggm_get_course_mentor_names( $course->ID ) );
}

$is_workshop     = 'workshop' === $type;
$is_course       = 'course' === $type;
$checkout_can_pay = ! $is_contribution || ( is_array( $contribution_snapshot ) && ! empty( $contribution_snapshot['valid'] ) );
$has_valid_sale  = $is_workshop && ! $is_contribution && $sale_price > 0 && $sale_price < $regular_price;
$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
	? GGM_Currency::selected_currency()
	: ggm_get_setting( 'ggm_currency', 'INR' );
$currency       = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
	? GGM_Currency::symbol( $selected_currency )
	: ( $is_workshop && class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_currency( $item_id ) : $currency_symbol );
$display_price  = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
	? GGM_Currency::convert_amount( $base_price, $selected_currency, $item_id )
	: $base_price;
$display_regular_price = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
	? GGM_Currency::convert_amount( $regular_price, $selected_currency, $item_id )
	: $regular_price;
$display_sale_price = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
	? GGM_Currency::convert_amount( $sale_price, $selected_currency, $item_id )
	: $sale_price;
$display_sale_discount = $has_valid_sale ? max( 0, $display_regular_price - $display_sale_price ) : 0;
$amount_formatted = $currency . number_format( $display_price, 2 );
$selected_contribution_id = $checkout_can_pay && $is_contribution ? (string) $contribution_snapshot['option_id'] : '';
$selected_contribution_is_free = $checkout_can_pay && $is_contribution && ! empty( $contribution_snapshot['is_free'] );
$selected_contribution_display = $selected_contribution_is_free
	? ( $contribution_snapshot['label'] ?: __( 'Free', 'ggm-member-dashboard' ) )
	: $amount_formatted;
$checkout_is_free = $checkout_can_pay && $base_price <= 0;

// Deliberately NOT falling back to the current user's own name/email/phone
// here — this HTML can be served from a page-cache plugin to every visitor,
// and baking a specific logged-in visitor's personal details into that
// shared cached copy would leak them to everyone else who loads this page.
// The checkout script fetches the real current visitor's contact details
// live via AJAX instead (never itself cached) and fills these fields in
// after page load. Only an explicit query-string prefill (from a join-form
// elsewhere) is safe to render directly, since it isn't tied to a session.
$contact_name  = $prefill_name;
$contact_email = $prefill_email;
$contact_phone = $prefill_phone;
?>
<div class="ggm-checkout-wrap">
	<div class="ggm-checkout-card">
		<div class="ggm-checkout-heading">
			<span class="ggm-checkout-eyebrow"><span aria-hidden="true">♢</span> <?php esc_html_e( 'Secure checkout', 'ggm-member-dashboard' ); ?></span>
			<h2 class="ggm-checkout-title"><?php esc_html_e( 'Complete your purchase', 'ggm-member-dashboard' ); ?></h2>
			<p><?php esc_html_e( 'Enter your details below. No login or OTP verification required.', 'ggm-member-dashboard' ); ?></p>
		</div>
		<div class="ggm-checkout-progress" aria-label="<?php esc_attr_e( 'Checkout progress', 'ggm-member-dashboard' ); ?>">
			<div class="ggm-checkout-progress-step is-active"><span>1</span><strong><?php esc_html_e( 'Your Details', 'ggm-member-dashboard' ); ?></strong></div>
			<div class="ggm-checkout-progress-line"></div>
			<div class="ggm-checkout-progress-step"><span>2</span><strong><?php esc_html_e( 'Order Summary', 'ggm-member-dashboard' ); ?></strong></div>
		</div>

		<div class="ggm-checkout-grid">
		<section class="ggm-checkout-panel ggm-checkout-contact-panel" aria-labelledby="ggm-contact-title">
			<div class="ggm-checkout-section-title">
				<span class="ggm-section-icon ggm-section-icon-user" aria-hidden="true">●</span>
				<div>
					<h3 id="ggm-contact-title"><?php esc_html_e( 'Your details', 'ggm-member-dashboard' ); ?></h3>
					<p><?php esc_html_e( 'We will use these details for your purchase receipt and access.', 'ggm-member-dashboard' ); ?></p>
				</div>
			</div>

		<div class="ggm-checkout-details">
			<div class="ggm-checkout-field">
				<label for="ggm-checkout-name"><?php esc_html_e( 'Full Name', 'ggm-member-dashboard' ); ?></label>
				<input type="text" id="ggm-checkout-name" value="<?php echo esc_attr( $contact_name ); ?>" placeholder="<?php esc_attr_e( 'Your full name', 'ggm-member-dashboard' ); ?>" required>
			</div>
			<div class="ggm-checkout-field">
				<label for="ggm-checkout-phone"><?php esc_html_e( 'WhatsApp Number', 'ggm-member-dashboard' ); ?></label>
					<div class="ggm-global-phone-control">
						<?php echo class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_picker_html( '', 'ggm-checkout-country-code', $prefill_country ) : '<input type="hidden" id="ggm-checkout-country-code" value="+91">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input type="tel" id="ggm-checkout-phone" value="<?php echo esc_attr( $contact_phone ); ?>" placeholder="<?php esc_attr_e( 'Your WhatsApp number', 'ggm-member-dashboard' ); ?>" inputmode="tel" autocomplete="tel-national" required>
					</div>
			</div>
			<div class="ggm-checkout-field">
				<label for="ggm-checkout-email"><?php esc_html_e( 'Email Address', 'ggm-member-dashboard' ); ?></label>
				<input type="email" id="ggm-checkout-email" value="<?php echo esc_attr( $contact_email ); ?>" placeholder="<?php esc_attr_e( 'you@example.com', 'ggm-member-dashboard' ); ?>" required>
			</div>
		</div>
		</section>

		<section class="ggm-checkout-panel ggm-checkout-order-panel" aria-labelledby="ggm-order-title">
			<div class="ggm-checkout-section-title">
				<span class="ggm-section-icon ggm-section-icon-order" aria-hidden="true">▣</span>
				<div>
					<h3 id="ggm-order-title"><?php esc_html_e( 'Order summary', 'ggm-member-dashboard' ); ?></h3>
					<p><?php esc_html_e( 'Review your selection before payment.', 'ggm-member-dashboard' ); ?></p>
				</div>
			</div>
		<div class="ggm-checkout-summary">
			<div class="ggm-checkout-row ggm-checkout-product-row">
				<span class="ggm-product-summary-icon" aria-hidden="true">▱</span>
				<span class="ggm-checkout-label">
					<?php
					if ( $is_workshop ) {
						esc_html_e( 'Workshop', 'ggm-member-dashboard' );
					} else {
						esc_html_e( 'Course', 'ggm-member-dashboard' );
					}
					?>
				</span>
				<span class="ggm-checkout-value"><?php echo esc_html( $item_name ); ?></span>
			</div>

			<?php if ( $is_workshop ) : ?>
				<?php if ( $is_contribution ) : ?>
					<?php if ( $checkout_can_pay ) : ?>
					<div class="ggm-checkout-row ggm-checkout-contribution-row">
						<span class="ggm-checkout-label"><?php esc_html_e( 'Your contribution', 'ggm-member-dashboard' ); ?></span>
						<span class="ggm-checkout-value"><?php echo esc_html( $selected_contribution_display ); ?></span>
					</div>
					<?php else : ?>
					<div class="ggm-notice ggm-notice-error" role="alert">
						<?php echo esc_html( $contribution_error ); ?>
						<a href="<?php echo esc_url( get_permalink( $workshop->ID ) ); ?>"><?php esc_html_e( 'Return to the workshop and select an amount.', 'ggm-member-dashboard' ); ?></a>
					</div>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( $mode ) : ?>
				<div class="ggm-checkout-row">
					<span class="ggm-checkout-label"><?php esc_html_e( 'Mode', 'ggm-member-dashboard' ); ?></span>
					<span class="ggm-checkout-value"><?php echo esc_html( $mode ); ?></span>
				</div>
				<?php endif; ?>
				<?php if ( $duration_label ) : ?>
				<div class="ggm-checkout-row">
					<span class="ggm-checkout-label"><?php esc_html_e( 'Duration', 'ggm-member-dashboard' ); ?></span>
					<span class="ggm-checkout-value"><?php echo esc_html( $duration_label ); ?></span>
				</div>
				<?php endif; ?>
				<?php if ( $linked_course ) : ?>
				<div class="ggm-checkout-row">
					<span class="ggm-checkout-label"><?php esc_html_e( 'Includes Course', 'ggm-member-dashboard' ); ?></span>
					<span class="ggm-checkout-value"><?php echo esc_html( $linked_course->post_title ); ?></span>
				</div>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( ! empty( $instructor ) ) : ?>
				<div class="ggm-checkout-row">
					<span class="ggm-checkout-label"><?php esc_html_e( 'Instructor', 'ggm-member-dashboard' ); ?></span>
					<span class="ggm-checkout-value"><?php echo esc_html( $instructor ); ?></span>
				</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $checkout_can_pay ) : ?>
			<!-- Coupon -->
			<div class="ggm-checkout-row ggm-checkout-coupon-row" <?php echo !empty($is_contribution) ? 'style="display:none"' : ''; ?>>
				<span class="ggm-checkout-label"><?php esc_html_e( 'Coupon Code', 'ggm-member-dashboard' ); ?></span>
				<span class="ggm-checkout-value">
					<input type="text" id="ggm-coupon-input" placeholder="<?php esc_attr_e( 'Enter code', 'ggm-member-dashboard' ); ?>" style="text-transform:uppercase;">
					<button type="button" id="ggm-apply-coupon-btn" class="ggm-btn ggm-btn-secondary"><?php esc_html_e( 'Apply Coupon', 'ggm-member-dashboard' ); ?></button>
				</span>
			</div>
			<div id="ggm-coupon-message" class="ggm-notice" style="display:none; margin:6px 0 0;"></div>

			<div class="ggm-checkout-row">
				<span class="ggm-checkout-label"><?php esc_html_e( 'Subtotal', 'ggm-member-dashboard' ); ?></span>
				<span class="ggm-checkout-value" id="ggm-checkout-subtotal">
					<?php if ( $has_valid_sale ) : ?>
						<del><?php echo esc_html( $currency . number_format( $display_regular_price, 2 ) ); ?></del>
						<?php echo esc_html( $currency . number_format( $display_sale_price, 2 ) ); ?>
					<?php else : ?>
						<?php echo esc_html( $amount_formatted ); ?>
					<?php endif; ?>
				</span>
			</div>
			<div class="ggm-checkout-row" id="ggm-checkout-discount-row" style="<?php echo $has_valid_sale ? '' : 'display:none;'; ?>">
				<span class="ggm-checkout-label"><?php esc_html_e( 'Discount', 'ggm-member-dashboard' ); ?></span>
				<span class="ggm-checkout-value" id="ggm-checkout-discount" style="color:#43a322;">-<?php echo esc_html( $currency . number_format( $display_sale_discount, 2 ) ); ?></span>
			</div>
			<div class="ggm-checkout-row" id="ggm-checkout-credit-row" style="display:none;">
				<span class="ggm-checkout-label"><?php esc_html_e( 'Account Credit', 'ggm-member-dashboard' ); ?><small id="ggm-credit-balance-label" style="display:block"></small></span>
				<span class="ggm-checkout-value" id="ggm-checkout-credit" style="color:#16803c;">-<?php echo esc_html( $currency ); ?>0.00</span>
			</div>
			<div class="ggm-checkout-row ggm-checkout-total">
				<span class="ggm-checkout-label"><?php esc_html_e( 'Final Total', 'ggm-member-dashboard' ); ?></span>
				<span class="ggm-checkout-value ggm-checkout-amount" id="ggm-checkout-final">
					<?php echo esc_html( $currency . number_format( $display_price, 2 ) ); ?>
				</span>
			</div>
			<?php endif; ?>
		</div>

		<div id="ggm-checkout-feedback" class="ggm-notice" style="display:none; margin-bottom:15px;"></div>

		</section>
		</div>

		<?php if ( $checkout_can_pay ) : ?>
			<button
				id="ggm-pay-btn"
				class="ggm-btn ggm-btn-primary ggm-btn-full ggm-pay-btn"
				data-type="<?php echo esc_attr( $type ); ?>"
				data-workshop="<?php echo esc_attr( $is_workshop ? $item_id : 0 ); ?>"
				data-course="<?php echo esc_attr( $is_course ? $item_id : 0 ); ?>"
				data-amount="<?php echo esc_attr( number_format( $display_price, 2, '.', '' ) ); ?>"
				data-base-amount="<?php echo esc_attr( number_format( $base_price, 2, '.', '' ) ); ?>"
				data-currency="<?php echo esc_attr( $selected_currency ); ?>"
				data-display-discount="<?php echo esc_attr( number_format( $display_sale_discount, 2, '.', '' ) ); ?>"
				data-name="<?php echo esc_attr( $item_name ); ?>"
				data-needs-verify="0"
				data-contribution="<?php echo esc_attr( !empty($is_contribution) ? '1' : '0' ); ?>"
				data-contribution-option="<?php echo esc_attr( $selected_contribution_id ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'ggm_nonce' ) ); ?>">
				<span aria-hidden="true">&#128274;</span>
				<?php if ( $checkout_is_free ) : ?>
					<?php esc_html_e( 'Enroll for Free', 'ggm-member-dashboard' ); ?>
				<?php else : ?>
					<?php printf( esc_html__( 'Pay %s Securely', 'ggm-member-dashboard' ), esc_html( $amount_formatted ) ); ?>
				<?php endif; ?>
			</button>
			<div class="ggm-checkout-trust"><span aria-hidden="true">➤</span> <?php esc_html_e( 'Secured by Razorpay', 'ggm-member-dashboard' ); ?></div>
		<?php endif; ?>
	</div>
</div>

<?php if ( false ) : // OTP checkout is temporarily disabled. ?>
<script>
(function ($) {
	'use strict';
	var checkoutOtpRequest = '';
	var checkoutOtpInFlight = false;

	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	// This page's own HTML can be served from a page-cache plugin to every
	// visitor — a nonce baked directly into that HTML would belong to
	// whoever's session it was cached from, not the real visitor. Fetch one
	// generated right now instead; this AJAX call itself is never cached.
	var nonce = <?php echo wp_json_encode( wp_create_nonce( 'ggm_nonce' ) ); ?>;
	$.post(ajaxUrl, { action: 'ggm_get_checkout_nonce' }).done(function (res) {
		if (res && res.success && res.data && res.data.nonce) {
			nonce = res.data.nonce;
		}
	});

	function showFeedback(type, msg) {
		$('#ggm-phone-verify-feedback')
			.removeClass('ggm-notice-success ggm-notice-error')
			.addClass(type === 'error' ? 'ggm-notice-error' : 'ggm-notice-success')
			.text(msg)
			.show();
	}

	function markVerified() {
		// Only hide the "Verify" button — #ggm-phone-verify-row also
		// contains the phone number input itself, so hiding the whole row
		// was blanking out the number the visitor had just verified.
		$('#ggm-checkout-phone').prop('readonly', true).data('verified', true);
		$('#ggm-checkout-country-code').prop('disabled', true);
		$('#ggm-phone-verify-btn').hide();
		$('#ggm-phone-otp-row').hide();
		$('#ggm-phone-verify-feedback').hide();
		$('#ggm-phone-verified-badge').show();

		// Tell ggm-razorpay.js this visitor just went from guest to logged-in
		// — its Pay Now nonce (fetched while they were still anonymous) is
		// now stale, since a WordPress nonce is tied to the current user ID.
		$(document).trigger('ggm:contact-verified');
	}

	$('#ggm-phone-verify-btn').on('click', function () {
		var $btn   = $(this);
		var digits = $('#ggm-checkout-phone').val().trim().replace(/\D/g, '');

		if (digits.length < 10) {
			showFeedback('error', <?php echo wp_json_encode( __( 'Please enter a valid WhatsApp number.', 'ggm-member-dashboard' ) ); ?>);
			return;
		}

		$btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Sending…', 'ggm-member-dashboard' ) ); ?>);

		$.post(ajaxUrl, {
			action:     'ggm_send_otp',
			nonce:      nonce,
			identifier: digits
		}).done(function (res) {
			$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Resend', 'ggm-member-dashboard' ) ); ?>);
			if (res.success) {
				checkoutOtpRequest = (res.data && res.data.request_id) ? res.data.request_id : '';
				$('#ggm-phone-otp-row').css('display', 'flex');
				var destination = (res.data && res.data.masked) ? ' ' + res.data.masked : '';
				showFeedback('success', <?php echo wp_json_encode( __( 'OTP sent by email to', 'ggm-member-dashboard' ) ); ?> + destination + '. ' + <?php echo wp_json_encode( __( 'Enter the code.', 'ggm-member-dashboard' ) ); ?>);
			} else {
				showFeedback('error', (res.data && res.data.message) || <?php echo wp_json_encode( __( 'Could not send OTP.', 'ggm-member-dashboard' ) ); ?>);
			}
		}).fail(function () {
			$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Verify', 'ggm-member-dashboard' ) ); ?>);
			showFeedback('error', <?php echo wp_json_encode( __( 'Something went wrong.', 'ggm-member-dashboard' ) ); ?>);
		});
	});

	$('#ggm-phone-otp-confirm-btn').on('click', function () {
		var $btn  = $(this);
		var phone = $('#ggm-checkout-phone').val().trim().replace(/\D/g, '');
		var otp   = $('#ggm-phone-otp-input').val().trim();
		var name  = $('#ggm-checkout-name').val().trim();
		var email = $('#ggm-checkout-email').val().trim();

		if (otp.length !== 6) {
			showFeedback('error', <?php echo wp_json_encode( __( 'Please enter the 6-digit OTP.', 'ggm-member-dashboard' ) ); ?>);
			return;
		}
		if (checkoutOtpInFlight || !checkoutOtpRequest) {
			return;
		}

		checkoutOtpInFlight = true;
		$btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Verifying…', 'ggm-member-dashboard' ) ); ?>);

		$.post(ajaxUrl, {
			action:     'ggm_verify_otp',
			nonce:      nonce,
			identifier: phone,
			otp:        otp,
			request_id: checkoutOtpRequest
		}).done(function (res) {
			if (!res || res.success !== true || !res.data || res.data.authenticated !== true) {
				checkoutOtpInFlight = false;
				$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Confirm', 'ggm-member-dashboard' ) ); ?>);
				showFeedback('error', (res && res.data && res.data.message) || <?php echo wp_json_encode( __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ) ); ?>);
				return;
			}

			// A brand-new visitor's auth cookie (just set server-side by
			// ggm_verify_otp) isn't reliable to depend on for the very next
			// AJAX call — ggm-razorpay.js uses this signed uid/token pair
			// instead of a session-bound nonce for Pay Now, Apply Coupon,
			// etc., exactly like ggm_complete_profile already does below.
			window.ggmVerifiedAuth = {
				uid:   res.data.uid        || 0,
				token: res.data.auth_token || ''
			};

			if (res.data && res.data.needs_profile) {
				// Brand-new account — save the name/email already typed
				// above as the profile, silently, without leaving this page.
				var parts     = name.split(' ');
				var firstName = parts.shift() || phone;
				var lastName  = parts.join(' ');

				$.post(ajaxUrl, {
					action:        'ggm_complete_profile',
					nonce:         nonce,
					uid:           res.data.uid        || 0,
					auth_token:    res.data.auth_token || '',
					first_name:    firstName,
					last_name:     lastName,
					email:         email,
					phone:         phone,
					country_code:  $('#ggm-checkout-country-code').val()
				}).always(markVerified);
			} else {
				markVerified();
			}
		}).fail(function () {
			checkoutOtpInFlight = false;
			$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Confirm', 'ggm-member-dashboard' ) ); ?>);
			showFeedback('error', <?php echo wp_json_encode( __( 'Something went wrong.', 'ggm-member-dashboard' ) ); ?>);
		});
	});
})(jQuery);
</script>
<?php endif; ?>
