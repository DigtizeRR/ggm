<?php
/**
 * Auth Step 3 — Profile Completion.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="ggm-login-step-3" class="ggm-login-step-panel" style="display: none;">
	<h2><?php esc_html_e( 'Complete Your Profile', 'ggm-member-dashboard' ); ?></h2>
	<p><?php esc_html_e( 'Please tell us your name to continue', 'ggm-member-dashboard' ); ?></p>

	<div class="ggm-profile-fields-wrap">
		<input type="text" id="ggm-profile-fname" placeholder="<?php esc_attr_e( 'First Name *', 'ggm-member-dashboard' ); ?>" autocomplete="given-name">
		<input type="text" id="ggm-profile-lname" placeholder="<?php esc_attr_e( 'Last Name', 'ggm-member-dashboard' ); ?>" autocomplete="family-name">
		<div id="ggm-profile-phone-wrap" class="ggm-global-phone-control">
			<?php echo class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_picker_html( 'country_code', 'ggm-profile-country-code', '+91' ) : '<input type="hidden" id="ggm-profile-country-code" value="+91">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div style="flex:1;">
				<input type="tel" id="ggm-profile-phone" placeholder="<?php esc_attr_e( 'WhatsApp Number', 'ggm-member-dashboard' ); ?>" autocomplete="tel" inputmode="tel" maxlength="15" style="width:100%;">
				<small id="ggm-profile-phone-note" style="display:none; color:#64748b; font-size:12px; margin-top:4px;">
					<?php esc_html_e( 'Logged in with this number', 'ggm-member-dashboard' ); ?>
				</small>
			</div>
		</div>
		<button id="ggm-save-profile-step3" class="ggm-btn ggm-btn-primary ggm-btn-wide" style="margin-top: 8px;">
			<?php esc_html_e( 'Save & Continue →', 'ggm-member-dashboard' ); ?>
		</button>
		<div id="ggm-profile-step3-error" class="ggm-error" style="display: none; margin-top: 10px;"></div>
	</div>
</div>
