<?php
/**
 * Settings Partial — Email Templates.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ggm-settings-section-header">
	<h3><?php esc_html_e( 'Email Notification Templates', 'ggm-member-dashboard' ); ?></h3>
	<p><?php esc_html_e( 'Customize email titles and rich content templates sent by DZ LMS. Use the editor toolbar to add headings, bold text, bullet points, and links.', 'ggm-member-dashboard' ); ?></p>
</div>

<table class="form-table ggm-settings-table">

	<!-- OTP Email -->
	<tr>
		<td colspan="2">
			<h4 style="margin:10px 0 5px; border-bottom:1px solid #ddd; padding-bottom:5px;">
				<?php esc_html_e( 'One-Time Password (OTP) Email', 'ggm-member-dashboard' ); ?>
			</h4>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-otp-subject"><?php esc_html_e( 'OTP Email Subject', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="text" name="settings[ggm_otp_email_subject]" id="ggm-otp-subject"
				value="<?php echo esc_attr( $settings['ggm_otp_email_subject'] ?? 'Your OTP for Login' ); ?>"
				class="large-text">
		</td>
	</tr>
	<tr>
		<th>
			<label><?php esc_html_e( 'OTP Email Body', 'ggm-member-dashboard' ); ?></label>
			<p class="description" style="font-weight:400; margin-top:6px;">
				<?php esc_html_e( 'Use {otp} to embed the code.', 'ggm-member-dashboard' ); ?>
			</p>
		</th>
		<td>
			<?php
			$otp_body_default = '<p>Your One-Time Password (OTP) is:</p><h2 style="letter-spacing:4px;">{otp}</h2><p>This code is valid for <strong>10 minutes</strong>. Do not share it with anyone.</p>';
			$otp_body_value   = $settings['ggm_otp_email_body'] ?? $otp_body_default;
			wp_editor(
				$otp_body_value,
				'ggm_otp_email_body',
				array(
					'textarea_name' => 'settings[ggm_otp_email_body]',
					'textarea_rows' => 8,
					'media_buttons' => false,
					'teeny'         => false,
					'quicktags'     => true,
				)
			);
			?>
		</td>
	</tr>

	<!-- Welcome Email -->
	<tr>
		<td colspan="2">
			<h4 style="margin:20px 0 5px; border-bottom:1px solid #ddd; padding-bottom:5px;">
				<?php esc_html_e( 'New Member Welcome Email', 'ggm-member-dashboard' ); ?>
			</h4>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-welcome-subject"><?php esc_html_e( 'Welcome Email Subject', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="text" name="settings[ggm_welcome_email_subject]" id="ggm-welcome-subject"
				value="<?php echo esc_attr( $settings['ggm_welcome_email_subject'] ?? 'Welcome to DZ LMS!' ); ?>"
				class="large-text">
		</td>
	</tr>
	<tr>
		<th>
			<label><?php esc_html_e( 'Welcome Email Body', 'ggm-member-dashboard' ); ?></label>
			<p class="description" style="font-weight:400; margin-top:6px;">
				<?php esc_html_e( 'Use {name} and {email} for member details.', 'ggm-member-dashboard' ); ?>
			</p>
		</th>
		<td>
			<?php
			$welcome_body_default = "<p>Hi <strong>{name}</strong>,</p><p>Welcome! Your account has been registered with: <strong>{email}</strong>.</p><p>You can now log in to access all your courses and content.</p><p>Thank you for joining us!</p>";
			$welcome_body_value   = $settings['ggm_welcome_email_body'] ?? $welcome_body_default;
			wp_editor(
				$welcome_body_value,
				'ggm_welcome_email_body',
				array(
					'textarea_name' => 'settings[ggm_welcome_email_body]',
					'textarea_rows' => 10,
					'media_buttons' => false,
					'teeny'         => false,
					'quicktags'     => true,
				)
			);
			?>
		</td>
	</tr>

</table>
