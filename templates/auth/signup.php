<?php
/**
 * Signup Form — [ggm_signup] shortcode / Signup Page setting.
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

$login_page_id = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
$login_url     = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/workshop-login/' );
?>
<div class="ggm-login-step-panel">
	<?php if ( $logo_url ) : ?>
		<div class="ggm-login-logo">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Create Your Account', 'ggm-member-dashboard' ); ?></h2>
	<p><?php esc_html_e( 'Sign up to get started', 'ggm-member-dashboard' ); ?></p>

	<div class="ggm-login-field-group">
		<input type="text" id="ggm-signup-fname" placeholder="<?php esc_attr_e( 'First name', 'ggm-member-dashboard' ); ?>">
		<input type="text" id="ggm-signup-lname" placeholder="<?php esc_attr_e( 'Last name (optional)', 'ggm-member-dashboard' ); ?>">
		<input type="email" id="ggm-signup-email" placeholder="<?php esc_attr_e( 'Email address', 'ggm-member-dashboard' ); ?>">
		<div class="ggm-global-phone-control">
			<?php echo class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_picker_html( 'country_code', 'ggm-signup-country-code', '+91' ) : '<input type="hidden" id="ggm-signup-country-code" value="+91">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="tel" id="ggm-signup-phone" placeholder="<?php esc_attr_e( 'Phone number (optional)', 'ggm-member-dashboard' ); ?>" inputmode="tel" autocomplete="tel-national" maxlength="15">
		</div>
		<input type="password" id="ggm-signup-password" placeholder="<?php esc_attr_e( 'Password', 'ggm-member-dashboard' ); ?>">
		<input type="password" id="ggm-signup-confirm-password" placeholder="<?php esc_attr_e( 'Confirm password', 'ggm-member-dashboard' ); ?>">
		<button id="ggm-signup-submit" class="ggm-btn ggm-btn-primary ggm-btn-wide">
			<?php esc_html_e( 'Create Account', 'ggm-member-dashboard' ); ?>
		</button>
		<div id="ggm-signup-error" class="ggm-error" style="display: none;"></div>
	</div>

	<div class="ggm-login-divider"><?php esc_html_e( 'OR', 'ggm-member-dashboard' ); ?></div>

	<a href="<?php echo esc_url( $login_url ); ?>" class="ggm-btn-link">
		<?php esc_html_e( 'Already have an account? Login', 'ggm-member-dashboard' ); ?>
	</a>
</div>
