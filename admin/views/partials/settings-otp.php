<?php
/**
 * Settings Partial — Email OTP.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$smtp_ready = ggm_custom_smtp_enabled();
?>
<div class="ggm-settings-section-header">
	<h3><?php esc_html_e( 'Email OTP Settings', 'ggm-member-dashboard' ); ?></h3>
	<p><?php esc_html_e( 'OTP codes are delivered only to the matched account\'s registered email through Custom SMTP. SMS, WhatsApp, and production demo-code delivery are disabled.', 'ggm-member-dashboard' ); ?></p>
</div>

<div style="background:<?php echo esc_attr( $smtp_ready ? '#ecfdf5' : '#fef2f2' ); ?>; border:1px solid <?php echo esc_attr( $smtp_ready ? '#10b981' : '#ef4444' ); ?>; border-radius:6px; padding:12px 16px; margin-bottom:20px;">
	<strong>
		<?php echo $smtp_ready
			? esc_html__( 'Custom SMTP is enabled for OTP delivery.', 'ggm-member-dashboard' )
			: esc_html__( 'Custom SMTP is not ready. Configure and save it in the SMTP Settings tab before OTP login can send codes.', 'ggm-member-dashboard' ); ?>
	</strong>
</div>

<table class="form-table ggm-settings-table">
	<tr>
		<th><label for="ggm-otp-expiry"><?php esc_html_e( 'OTP Expiry (Minutes)', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="number" name="settings[ggm_otp_expiry]" id="ggm-otp-expiry" value="<?php echo esc_attr( $settings['ggm_otp_expiry'] ?? 10 ); ?>" min="1" max="120" class="small-text">
		</td>
	</tr>
	<tr>
		<th><label for="ggm-otp-rate-limit"><?php esc_html_e( 'Max OTP Requests Window', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="number" name="settings[ggm_otp_rate_limit]" id="ggm-otp-rate-limit" value="<?php echo esc_attr( $settings['ggm_otp_rate_limit'] ?? 3 ); ?>" min="1" max="20" class="small-text">
			<p class="description"><?php esc_html_e( 'Maximum successful OTP emails permitted per identifier in a 10-minute cooldown window.', 'ggm-member-dashboard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-test-otp-email"><?php esc_html_e( 'Test Email OTP', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
				<input type="email" id="ggm-test-otp-email" placeholder="<?php esc_attr_e( 'Enter email address', 'ggm-member-dashboard' ); ?>" class="regular-text" style="max-width:260px;" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
				<button type="button" id="ggm-send-test-otp-btn" class="button button-primary"><?php esc_html_e( 'Send Test Email OTP', 'ggm-member-dashboard' ); ?></button>
				<span class="spinner" id="ggm-test-otp-spinner" style="float:none; margin-top:0;"></span>
			</div>
			<div id="ggm-test-otp-feedback" style="margin-top:8px; font-weight:600; font-size:13px;"></div>
			<p class="description"><?php esc_html_e( 'Sends a real OTP through the saved Custom SMTP route.', 'ggm-member-dashboard' ); ?></p>
		</td>
	</tr>
</table>
