<?php
/**
 * Auth Step 1 — Identifier Screen.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_url = ggm_get_setting( 'ggm_dashboard_logo', '' );
if ( ! $logo_url ) {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
	}
}
?>
<div id="ggm-login-step-1" class="ggm-login-step-panel">
	<?php if ( $logo_url ) : ?>
		<div class="ggm-login-logo">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Welcome to GGM', 'ggm-member-dashboard' ); ?></h2>

	<!-- OTP login view -->
	<div id="ggm-otp-login-view">
		<p><?php esc_html_e( 'Login with your phone number or email', 'ggm-member-dashboard' ); ?></p>

		<div class="ggm-login-field-group">
			<div class="ggm-global-phone-control ggm-auth-identifier-control">
				<?php echo class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_picker_html( '', 'ggm-login-country-code', '+91' ) : '<input type="hidden" id="ggm-login-country-code" value="+91">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="text" id="ggm-identifier" placeholder="<?php esc_attr_e( 'Phone number or email', 'ggm-member-dashboard' ); ?>" autocomplete="username">
			</div>
			<button id="ggm-send-otp" class="ggm-btn ggm-btn-primary ggm-btn-wide">
				<?php esc_html_e( 'Send OTP', 'ggm-member-dashboard' ); ?>
			</button>
			<div id="ggm-otp-error" class="ggm-error" style="display: none;"></div>
			<div id="ggm-account-not-found" class="ggm-account-not-found" style="display: none;">
				<p><?php esc_html_e( 'Account not found. Please create an account first.', 'ggm-member-dashboard' ); ?></p>
				<a href="#" id="ggm-create-account-link" class="ggm-btn ggm-btn-secondary ggm-btn-wide">
					<?php esc_html_e( 'Create Account', 'ggm-member-dashboard' ); ?>
				</a>
			</div>
		</div>

		<div class="ggm-login-divider"><?php esc_html_e( 'OR', 'ggm-member-dashboard' ); ?></div>

		<button id="ggm-toggle-password-login" class="ggm-btn-link">
			<?php esc_html_e( 'Login with Password', 'ggm-member-dashboard' ); ?>
		</button>
	</div>

	<!-- Password login view (hidden by default) -->
	<div id="ggm-password-login-view" class="ggm-password-form-wrap" style="display: none;">
		<p><?php esc_html_e( 'Login with your email and password', 'ggm-member-dashboard' ); ?></p>
		<input type="email" id="ggm-pw-email" placeholder="<?php esc_attr_e( 'Email address', 'ggm-member-dashboard' ); ?>">
		<input type="password" id="ggm-pw-password" placeholder="<?php esc_attr_e( 'Password', 'ggm-member-dashboard' ); ?>">
		<button id="ggm-pw-login" class="ggm-btn ggm-btn-primary ggm-btn-wide" style="margin-top: 10px;">
			<?php esc_html_e( 'Login', 'ggm-member-dashboard' ); ?>
		</button>
		<div id="ggm-pw-error" class="ggm-error" style="display: none; margin-top: 10px;"></div>

		<button id="ggm-forgot-password-link" class="ggm-btn-link" style="margin-top: 12px; display: block; width: 100%; text-align: center;">
			<?php esc_html_e( 'Forgot Password?', 'ggm-member-dashboard' ); ?>
		</button>

		<button id="ggm-back-to-otp-login" class="ggm-btn-link" style="margin-top: 15px; display: block; width: 100%; text-align: center;">
			<?php esc_html_e( '← Back to Login with OTP', 'ggm-member-dashboard' ); ?>
		</button>
	</div>

	<!-- Forgot Password view (hidden by default) -->
	<div id="ggm-forgot-password-view" style="display: none;">
		<h2><?php esc_html_e( 'Reset Password', 'ggm-member-dashboard' ); ?></h2>

		<!-- Stage 1: request OTP -->
		<div id="ggm-fp-request-stage">
			<p><?php esc_html_e( 'Enter your phone number or email to receive a reset code', 'ggm-member-dashboard' ); ?></p>
			<div class="ggm-login-field-group">
				<div class="ggm-global-phone-control ggm-auth-identifier-control">
					<?php echo class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_picker_html( '', 'ggm-fp-country-code', '+91' ) : '<input type="hidden" id="ggm-fp-country-code" value="+91">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="text" id="ggm-fp-identifier" placeholder="<?php esc_attr_e( 'Phone number or email', 'ggm-member-dashboard' ); ?>" autocomplete="username">
				</div>
				<button id="ggm-fp-send-otp" class="ggm-btn ggm-btn-primary ggm-btn-wide">
					<?php esc_html_e( 'Send OTP', 'ggm-member-dashboard' ); ?>
				</button>
				<div id="ggm-fp-request-error" class="ggm-error" style="display: none;"></div>
				<div id="ggm-fp-account-not-found" class="ggm-account-not-found" style="display: none;">
					<p><?php esc_html_e( 'Account not found. Please create an account first.', 'ggm-member-dashboard' ); ?></p>
					<a href="#" id="ggm-fp-create-account-link" class="ggm-btn ggm-btn-secondary ggm-btn-wide">
						<?php esc_html_e( 'Create Account', 'ggm-member-dashboard' ); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Stage 2: enter OTP + new password (hidden until OTP sent) -->
		<div id="ggm-fp-reset-stage" style="display: none;">
			<p id="ggm-fp-otp-sent-to"></p>
			<div class="ggm-login-field-group">
				<input type="text" id="ggm-fp-otp" placeholder="<?php esc_attr_e( '6-digit OTP', 'ggm-member-dashboard' ); ?>" maxlength="6" inputmode="numeric" pattern="[0-9]*">
				<input type="password" id="ggm-fp-new-password" placeholder="<?php esc_attr_e( 'New password', 'ggm-member-dashboard' ); ?>">
				<input type="password" id="ggm-fp-confirm-password" placeholder="<?php esc_attr_e( 'Confirm new password', 'ggm-member-dashboard' ); ?>">
				<button id="ggm-fp-reset-submit" class="ggm-btn ggm-btn-primary ggm-btn-wide">
					<?php esc_html_e( 'Reset Password', 'ggm-member-dashboard' ); ?>
				</button>
				<div id="ggm-fp-reset-error" class="ggm-error" style="display: none;"></div>
			</div>
			<button id="ggm-fp-change-identifier" class="ggm-btn-link" style="margin-top: 12px; display: block; width: 100%; text-align: center;">
				<?php esc_html_e( '← Use a different phone number or email', 'ggm-member-dashboard' ); ?>
			</button>
		</div>

		<button id="ggm-back-to-password-login" class="ggm-btn-link" style="margin-top: 15px; display: block; width: 100%; text-align: center;">
			<?php esc_html_e( '← Back to Login', 'ggm-member-dashboard' ); ?>
		</button>
	</div>
</div>
