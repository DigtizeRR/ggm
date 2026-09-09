<?php
/**
 * Settings Partial — Razorpay.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ggm-settings-section-header">
	<h3><?php esc_html_e( 'Razorpay Gateway Settings', 'ggm-member-dashboard' ); ?></h3>
	<p><?php esc_html_e( 'Configure Razorpay API credentials for paid workshop/membership checkouts.', 'ggm-member-dashboard' ); ?></p>
</div>

<table class="form-table ggm-settings-table">
	<tr>
		<th><label for="ggm-razorpay-mode"><?php esc_html_e( 'Mode', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<select name="settings[ggm_razorpay_mode]" id="ggm-razorpay-mode" class="regular-text">
				<option value="test" <?php selected( $settings['ggm_razorpay_mode'] ?? 'test', 'test' ); ?>><?php esc_html_e( 'Test Mode', 'ggm-member-dashboard' ); ?></option>
				<option value="live" <?php selected( $settings['ggm_razorpay_mode'] ?? 'test', 'live' ); ?>><?php esc_html_e( 'Live Mode', 'ggm-member-dashboard' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Which key pair below is actually used for checkout. Keep both sets saved and just flip this when you go live.', 'ggm-member-dashboard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-razorpay-test-key-id"><?php esc_html_e( 'Test Key ID', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="text" name="settings[ggm_razorpay_test_key_id]" id="ggm-razorpay-test-key-id" value="<?php echo esc_attr( $settings['ggm_razorpay_test_key_id'] ?? '' ); ?>" class="regular-text" placeholder="rzp_test_...">
		</td>
	</tr>
	<tr>
		<th><label for="ggm-razorpay-test-secret"><?php esc_html_e( 'Test Key Secret', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="password" name="settings[ggm_razorpay_test_key_secret]" id="ggm-razorpay-test-secret" value="<?php echo esc_attr( $settings['ggm_razorpay_test_key_secret'] ?? '' ); ?>" class="regular-text">
		</td>
	</tr>
	<tr>
		<th><label for="ggm-razorpay-live-key-id"><?php esc_html_e( 'Live Key ID', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="text" name="settings[ggm_razorpay_live_key_id]" id="ggm-razorpay-live-key-id" value="<?php echo esc_attr( $settings['ggm_razorpay_live_key_id'] ?? '' ); ?>" class="regular-text" placeholder="rzp_live_...">
		</td>
	</tr>
	<tr>
		<th><label for="ggm-razorpay-live-secret"><?php esc_html_e( 'Live Key Secret', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="password" name="settings[ggm_razorpay_live_key_secret]" id="ggm-razorpay-live-secret" value="<?php echo esc_attr( $settings['ggm_razorpay_live_key_secret'] ?? '' ); ?>" class="regular-text">
		</td>
	</tr>
</table>
