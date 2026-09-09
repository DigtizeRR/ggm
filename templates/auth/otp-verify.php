<?php
/**
 * Auth Step 2 — OTP Verification.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="ggm-login-step-2" class="ggm-login-step-panel" style="display: none;">
	<h2><?php esc_html_e( 'Enter OTP', 'ggm-member-dashboard' ); ?></h2>
	<p id="ggm-otp-sent-to"><?php esc_html_e( 'OTP sent to xxxxxxxxxx', 'ggm-member-dashboard' ); ?></p>

	<!-- 6 individual digit inputs -->
	<div class="ggm-otp-inputs-row">
		<input type="tel" maxlength="1" class="ggm-otp-digit" pattern="[0-9]*" autocomplete="one-time-code">
		<input type="tel" maxlength="1" class="ggm-otp-digit" pattern="[0-9]*">
		<input type="tel" maxlength="1" class="ggm-otp-digit" pattern="[0-9]*">
		<input type="tel" maxlength="1" class="ggm-otp-digit" pattern="[0-9]*">
		<input type="tel" maxlength="1" class="ggm-otp-digit" pattern="[0-9]*">
		<input type="tel" maxlength="1" class="ggm-otp-digit" pattern="[0-9]*">
	</div>

	<button id="ggm-verify-otp" class="ggm-btn ggm-btn-primary ggm-btn-wide" style="margin-top: 20px;">
		<?php esc_html_e( 'Verify OTP', 'ggm-member-dashboard' ); ?>
	</button>
	<div id="ggm-verify-error" class="ggm-error" style="display: none; margin-top: 10px;"></div>

	<div class="ggm-otp-actions-wrap" style="margin-top: 20px;">
		<button id="ggm-resend-otp" class="ggm-btn ggm-btn-secondary ggm-btn-wide" disabled>
			<?php esc_html_e( 'Resend OTP (30s)', 'ggm-member-dashboard' ); ?>
		</button>
		<button id="ggm-back-to-step1" class="ggm-btn-link" style="margin-top: 15px; display: block; width: 100%; text-align: center;">
			<?php esc_html_e( '← Change number/email', 'ggm-member-dashboard' ); ?>
		</button>
	</div>
</div>
