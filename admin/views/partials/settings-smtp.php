<?php
/**
 * Settings Partial — SMTP & Email Delivery.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ggm-settings-section-header">
	<h3><?php esc_html_e( 'SMTP & Email Delivery', 'ggm-member-dashboard' ); ?></h3>
	<p><?php esc_html_e( 'Configure a custom SMTP server for all outgoing emails (OTP, welcome messages, etc.). Leave disabled to use the WordPress default mail handler.', 'ggm-member-dashboard' ); ?></p>
</div>

<div style="background:#eff6ff; border:1px solid #3b82f6; border-radius:6px; padding:12px 16px; margin-bottom:20px;">
	<label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600;">
		<input type="checkbox" name="settings[ggm_smtp_enabled]" id="ggm-smtp-enabled" value="1"
			<?php checked( '1', $settings['ggm_smtp_enabled'] ?? '0' ); ?>>
		<?php esc_html_e( 'Enable Custom SMTP', 'ggm-member-dashboard' ); ?>
	</label>
	<p style="margin:6px 0 0; color:#1e40af; font-size:13px;">
		<?php esc_html_e( 'When enabled, DZ LMS overrides WordPress default mailer with the SMTP settings below.', 'ggm-member-dashboard' ); ?>
	</p>
</div>

<div id="ggm-smtp-fields" style="<?php echo empty( $settings['ggm_smtp_enabled'] ) ? 'display:none;' : ''; ?>">
	<table class="form-table ggm-settings-table">
		<tr>
			<th><label for="ggm-smtp-host"><?php esc_html_e( 'SMTP Host', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<input type="text" name="settings[ggm_smtp_host]" id="ggm-smtp-host"
					value="<?php echo esc_attr( $settings['ggm_smtp_host'] ?? '' ); ?>"
					class="regular-text" placeholder="smtp.gmail.com">
				<p class="description"><?php esc_html_e( 'Your SMTP server hostname, e.g. smtp.gmail.com or mail.yourdomain.com.', 'ggm-member-dashboard' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="ggm-smtp-port"><?php esc_html_e( 'SMTP Port', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<input type="number" name="settings[ggm_smtp_port]" id="ggm-smtp-port"
					value="<?php echo esc_attr( $settings['ggm_smtp_port'] ?? '587' ); ?>"
					class="small-text" min="1" max="65535">
				<p class="description"><?php esc_html_e( 'Common ports: 465 (SSL), 587 (TLS/STARTTLS), 25 (unencrypted).', 'ggm-member-dashboard' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="ggm-smtp-encryption"><?php esc_html_e( 'Encryption', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<select name="settings[ggm_smtp_encryption]" id="ggm-smtp-encryption" class="regular-text">
					<option value="tls" <?php selected( $settings['ggm_smtp_encryption'] ?? 'tls', 'tls' ); ?>><?php esc_html_e( 'TLS (STARTTLS — recommended)', 'ggm-member-dashboard' ); ?></option>
					<option value="ssl" <?php selected( $settings['ggm_smtp_encryption'] ?? 'tls', 'ssl' ); ?>><?php esc_html_e( 'SSL', 'ggm-member-dashboard' ); ?></option>
					<option value="none" <?php selected( $settings['ggm_smtp_encryption'] ?? 'tls', 'none' ); ?>><?php esc_html_e( 'None (not recommended)', 'ggm-member-dashboard' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="ggm-smtp-auth"><?php esc_html_e( 'SMTP Authentication', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
					<input type="checkbox" name="settings[ggm_smtp_auth]" id="ggm-smtp-auth" value="1"
						<?php checked( '1', $settings['ggm_smtp_auth'] ?? '1' ); ?>>
					<?php esc_html_e( 'Require username and password', 'ggm-member-dashboard' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th><label for="ggm-smtp-username"><?php esc_html_e( 'SMTP Username', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<input type="text" name="settings[ggm_smtp_username]" id="ggm-smtp-username"
					value="<?php echo esc_attr( $settings['ggm_smtp_username'] ?? '' ); ?>"
					class="regular-text" placeholder="you@gmail.com" autocomplete="off">
			</td>
		</tr>
		<tr>
			<th><label for="ggm-smtp-password"><?php esc_html_e( 'SMTP Password', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<input type="password" name="settings[ggm_smtp_password]" id="ggm-smtp-password"
					value="<?php echo esc_attr( $settings['ggm_smtp_password'] ?? '' ); ?>"
					class="regular-text" autocomplete="new-password">
				<p class="description"><?php esc_html_e( 'For Gmail/Google Workspace, use an App Password — not your account password.', 'ggm-member-dashboard' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="ggm-smtp-from-email"><?php esc_html_e( 'From Email', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<input type="email" name="settings[ggm_smtp_from_email]" id="ggm-smtp-from-email"
					value="<?php echo esc_attr( $settings['ggm_smtp_from_email'] ?? '' ); ?>"
					class="regular-text" placeholder="noreply@yourdomain.com">
				<p class="description"><?php esc_html_e( 'The "From" address shown in outgoing emails. Must match or be authorised by your SMTP provider.', 'ggm-member-dashboard' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="ggm-smtp-from-name"><?php esc_html_e( 'From Name', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<input type="text" name="settings[ggm_smtp_from_name]" id="ggm-smtp-from-name"
					value="<?php echo esc_attr( $settings['ggm_smtp_from_name'] ?? '' ); ?>"
					class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</td>
		</tr>
		<tr>
			<th><label for="ggm-smtp-test-email"><?php esc_html_e( 'Send Test Email', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
					<input type="email" id="ggm-smtp-test-email" placeholder="<?php esc_attr_e( 'Enter recipient email', 'ggm-member-dashboard' ); ?>" class="regular-text" style="max-width:260px;">
					<button type="button" id="ggm-send-smtp-test-btn" class="button button-primary"><?php esc_html_e( 'Send Test Email', 'ggm-member-dashboard' ); ?></button>
					<span class="spinner" id="ggm-smtp-test-spinner" style="float:none; margin-top:0;"></span>
				</div>
				<div id="ggm-smtp-test-feedback" style="margin-top:8px; font-weight:600; font-size:13px;"></div>
				<p class="description"><?php esc_html_e( 'Sends a plain test email using the SMTP settings above. Save settings first.', 'ggm-member-dashboard' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<script>
(function($){
	$('#ggm-smtp-enabled').on('change', function(){
		$('#ggm-smtp-fields').toggle(this.checked);
	});

	$('#ggm-send-smtp-test-btn').on('click', function(){
		var email = $('#ggm-smtp-test-email').val().trim();
		if ( ! email ) {
			$('#ggm-smtp-test-feedback').css('color','#b91c1c').text('<?php echo esc_js( __( 'Please enter a recipient email address.', 'ggm-member-dashboard' ) ); ?>');
			return;
		}
		var $btn = $(this), $spinner = $('#ggm-smtp-test-spinner'), $fb = $('#ggm-smtp-test-feedback');
		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$fb.text('');

		$.post(ajaxurl, {
			action : 'ggm_send_smtp_test',
			nonce  : ggmAdmin.nonce,
			email  : email
		}, function(res){
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
			if ( res.success ) {
				$fb.css('color','#15803d').text(res.data.message);
			} else {
				$fb.css('color','#b91c1c').text(res.data.message || '<?php echo esc_js( __( 'Failed to send test email.', 'ggm-member-dashboard' ) ); ?>');
			}
		}).fail(function(){
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
			$fb.css('color','#b91c1c').text('<?php echo esc_js( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?>');
		});
	});
})(jQuery);
</script>
