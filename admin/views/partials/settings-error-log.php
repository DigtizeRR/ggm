<?php
/**
 * Settings partial — Error Log.
 *
 * Shows PHP warnings/notices/exceptions captured while saving Workshop,
 * Course, Lesson, and Mentor meta box fields (see
 * GGM_Meta_Boxes::save_meta_boxes()). On a live site display_errors is
 * normally off, so a save that silently fails partway through — e.g. a
 * repeater row that "doesn't save" — otherwise leaves no visible trace.
 * This log is the way to find the exact cause.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ggm_error_log = get_option( 'ggm_error_log', array() );
$ggm_error_log = array_reverse( $ggm_error_log );
?>

<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:20px;">
	<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
		<div>
			<h3 style="margin:0 0 6px;"><?php esc_html_e( 'Error Log', 'ggm-member-dashboard' ); ?></h3>
			<p style="color:#666; margin:0;">
				<?php esc_html_e( 'Email delivery failures plus PHP warnings, notices, and exceptions. Email entries include the recipient, subject, SMTP connection settings, and originating plugin feature—never the email body, password, or SMTP username.', 'ggm-member-dashboard' ); ?>
			</p>
		</div>
		<div>
			<button type="button" class="button" id="ggm-clear-error-log-btn"><?php esc_html_e( 'Clear Log', 'ggm-member-dashboard' ); ?></button>
			<span class="spinner" id="ggm-clear-error-log-spinner" style="float:none; vertical-align:middle;"></span>
		</div>
	</div>

	<div id="ggm-error-log-feedback" style="display:none; margin-bottom:12px; padding:8px 12px; border-radius:4px;"></div>

	<details style="margin:0 0 16px; padding:12px; border:1px solid #dcdcde; border-radius:4px; background:#fff;">
		<summary style="cursor:pointer; font-weight:600;"><?php esc_html_e( 'Record a cPanel or mail-provider delivery outcome', 'ggm-member-dashboard' ); ?></summary>
		<p style="margin:10px 0; color:#50575e; font-size:13px;">
			<?php esc_html_e( 'Use Track Delivery or a trusted provider result to attach the downstream outcome to the Message-ID. This records what happened after WordPress submitted the message to SMTP; it does not claim that inbox delivery was verified locally.', 'ggm-member-dashboard' ); ?>
		</p>
		<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:10px; align-items:end;">
			<p style="margin:0;"><label for="ggm-mail-outcome-message-id"><strong><?php esc_html_e( 'Message-ID', 'ggm-member-dashboard' ); ?></strong></label><br><input class="regular-text" type="text" id="ggm-mail-outcome-message-id" placeholder="abc123@example.com" style="width:100%;"></p>
			<p style="margin:0;"><label for="ggm-mail-outcome-status"><strong><?php esc_html_e( 'Outcome', 'ggm-member-dashboard' ); ?></strong></label><br><select id="ggm-mail-outcome-status" style="width:100%;"><option value="rejected"><?php esc_html_e( 'Rejected', 'ggm-member-dashboard' ); ?></option><option value="deferred"><?php esc_html_e( 'Deferred', 'ggm-member-dashboard' ); ?></option><option value="bounced"><?php esc_html_e( 'Bounced', 'ggm-member-dashboard' ); ?></option><option value="delivered"><?php esc_html_e( 'Delivered', 'ggm-member-dashboard' ); ?></option></select></p>
			<p style="margin:0;"><label for="ggm-mail-outcome-host"><strong><?php esc_html_e( 'Remote host (optional)', 'ggm-member-dashboard' ); ?></strong></label><br><input class="regular-text" type="text" id="ggm-mail-outcome-host" placeholder="smtp.example.com" style="width:100%;"></p>
		</div>
		<p style="margin:10px 0 0;"><label for="ggm-mail-outcome-reason"><strong><?php esc_html_e( 'Provider result', 'ggm-member-dashboard' ); ?></strong></label><br><textarea id="ggm-mail-outcome-reason" rows="3" maxlength="500" style="width:100%;" placeholder="550 Your account has been locked. Please contact your administrator."></textarea></p>
		<p style="margin:10px 0 0;"><button type="button" class="button button-secondary" id="ggm-record-mail-outcome-btn"><?php esc_html_e( 'Record delivery outcome', 'ggm-member-dashboard' ); ?></button> <span id="ggm-mail-outcome-feedback" style="font-size:13px;"></span></p>
	</details>

	<div id="ggm-error-log-wrap">
		<?php if ( empty( $ggm_error_log ) ) : ?>
			<p style="color:#888; font-size:13px; margin:0;"><?php esc_html_e( 'No errors logged yet.', 'ggm-member-dashboard' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:150px;"><?php esc_html_e( 'Time', 'ggm-member-dashboard' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Post', 'ggm-member-dashboard' ); ?></th>
						<th><?php esc_html_e( 'Message', 'ggm-member-dashboard' ); ?></th>
						<th style="width:220px;"><?php esc_html_e( 'Location', 'ggm-member-dashboard' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$ggm_known_context_keys = array( 'post_id', 'post_type', 'file', 'line', 'trace', 'level', 'category' );
					foreach ( $ggm_error_log as $entry ) :
						$context   = is_array( $entry['context'] ?? null ) ? $entry['context'] : array();
						$post_id   = $context['post_id'] ?? '';
						$post_type = $context['post_type'] ?? '';
						$file      = $context['file'] ?? '';
						$line      = $context['line'] ?? '';
						$trace     = $context['trace'] ?? '';
						$level     = $context['level'] ?? 'error';
						$category  = $context['category'] ?? '';
						$edit_link = $post_id ? get_edit_post_link( (int) $post_id ) : '';
						$extra     = array_diff_key( $context, array_flip( $ggm_known_context_keys ) );
						?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
							<td>
								<?php if ( $edit_link ) : ?>
									<a href="<?php echo esc_url( $edit_link ); ?>" target="_blank" rel="noopener">#<?php echo esc_html( $post_id ); ?></a>
								<?php elseif ( $post_id ) : ?>
									#<?php echo esc_html( $post_id ); ?>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
								<?php if ( $post_type ) : ?>
									<br><span style="color:#888; font-size:11px;"><?php echo esc_html( $post_type ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$msg        = $entry['message'] ?? '';
								$stage      = $context['stage'] ?? '';
								$is_submission = ( 'submitted_to_smtp' === ( $context['delivery_status'] ?? '' ) );
								$is_success = ( ! $is_submission && ( 'success' === $level || 'wp_mail_success' === $stage || false !== stripos( $msg, 'accepted' ) || ( false !== stripos( $msg, 'success' ) && false === stripos( $msg, 'error' ) && false === stripos( $msg, 'failed' ) ) ) );
								$is_info    = ( ! $is_success && ( 'info' === $level || 'debug' === $level || in_array( $stage, array( 'workshop_otp_started', 'user_resolved', 'registered_email_resolved', 'smtp_configuration', 'smtp_dispatch_start' ), true ) || false !== stripos( $msg, 'pre-send' ) || false !== stripos( $msg, 'preparation' ) || false !== stripos( $msg, 'complete' ) || false !== stripos( $msg, 'fired' ) ) );
								$is_warning = ( 'warning' === $level || false !== stripos( $msg, 'warning' ) );
								?>
								<?php if ( $is_submission ) : ?>
									<span style="background:#e0f2fe; color:#0369a1; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600; margin-right:6px;"><?php esc_html_e( 'SUBMITTED', 'ggm-member-dashboard' ); ?></span>
								<?php elseif ( $is_success ) : ?>
									<span style="background:#dcfce7; color:#15803d; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600; margin-right:6px;"><?php esc_html_e( 'SUCCESS', 'ggm-member-dashboard' ); ?></span>
								<?php elseif ( 'email' === $category ) : ?>
									<span style="background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600; margin-right:6px;"><?php esc_html_e( 'EMAIL', 'ggm-member-dashboard' ); ?></span>
								<?php elseif ( $is_info ) : ?>
									<span style="background:#e0f2fe; color:#0369a1; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600; margin-right:6px;"><?php esc_html_e( 'INFO', 'ggm-member-dashboard' ); ?></span>
								<?php elseif ( $is_warning ) : ?>
									<span style="background:#fef9c3; color:#854d0e; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600; margin-right:6px;"><?php esc_html_e( 'WARNING', 'ggm-member-dashboard' ); ?></span>
								<?php else : ?>
									<span style="background:#fee2e2; color:#b91c1c; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600; margin-right:6px;"><?php esc_html_e( 'ERROR', 'ggm-member-dashboard' ); ?></span>
								<?php endif; ?>
								<?php echo esc_html( $entry['message'] ?? '' ); ?>
								<?php if ( ! empty( $extra ) ) : ?>
									<ul style="margin:6px 0 0; padding-left:18px; font-size:12px; color:#444;">
										<?php foreach ( $extra as $extra_key => $extra_val ) : ?>
											<li><strong><?php echo esc_html( $extra_key ); ?>:</strong> <?php echo esc_html( is_scalar( $extra_val ) ? $extra_val : wp_json_encode( $extra_val ) ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								<?php if ( $trace ) : ?>
									<details style="margin-top:6px;">
										<summary style="cursor:pointer; color:#888; font-size:11px;"><?php esc_html_e( 'Stack trace', 'ggm-member-dashboard' ); ?></summary>
										<pre style="white-space:pre-wrap; font-size:11px; color:#555; margin-top:6px;"><?php echo esc_html( $trace ); ?></pre>
									</details>
								<?php endif; ?>
							</td>
							<td style="font-family:monospace; font-size:12px; color:#555; word-break:break-all;">
								<?php echo $file ? esc_html( basename( (string) $file ) . ':' . $line ) : '&mdash;'; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<script>
( function () {
	'use strict';

	var btn      = document.getElementById( 'ggm-clear-error-log-btn' );
	var spinner  = document.getElementById( 'ggm-clear-error-log-spinner' );
	var feedback = document.getElementById( 'ggm-error-log-feedback' );
	var wrap     = document.getElementById( 'ggm-error-log-wrap' );
	var outcomeButton = document.getElementById( 'ggm-record-mail-outcome-btn' );

	if ( outcomeButton ) {
		outcomeButton.addEventListener( 'click', function () {
			var outcomeFeedback = document.getElementById( 'ggm-mail-outcome-feedback' );
			var messageId = document.getElementById( 'ggm-mail-outcome-message-id' );
			var status = document.getElementById( 'ggm-mail-outcome-status' );
			var host = document.getElementById( 'ggm-mail-outcome-host' );
			var reason = document.getElementById( 'ggm-mail-outcome-reason' );
			var body = new URLSearchParams();

			body.set( 'action', 'ggm_record_mail_delivery_outcome' );
			body.set( 'nonce', '<?php echo esc_js( wp_create_nonce( 'ggm_admin_nonce' ) ); ?>' );
			body.set( 'message_id', messageId ? messageId.value : '' );
			body.set( 'status', status ? status.value : '' );
			body.set( 'remote_host', host ? host.value : '' );
			body.set( 'reason', reason ? reason.value : '' );
			outcomeButton.disabled = true;

			fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString()
			} )
				.then( function ( res ) { return res.json(); } )
				.then( function ( res ) {
					outcomeButton.disabled = false;
					if ( outcomeFeedback ) {
						outcomeFeedback.style.color = res.success ? '#0e9e6e' : '#b91c1c';
						outcomeFeedback.textContent = ( res.data && res.data.message ) || '<?php echo esc_js( __( 'Unable to record the delivery outcome.', 'ggm-member-dashboard' ) ); ?>';
					}
				} )
				.catch( function () {
					outcomeButton.disabled = false;
					if ( outcomeFeedback ) {
						outcomeFeedback.style.color = '#b91c1c';
						outcomeFeedback.textContent = '<?php echo esc_js( __( 'Server error.', 'ggm-member-dashboard' ) ); ?>';
					}
				} );
		} );
	}

	if ( ! btn ) {
		return;
	}

	btn.addEventListener( 'click', function () {
		if ( ! window.confirm( '<?php echo esc_js( __( 'Clear the error log? This cannot be undone.', 'ggm-member-dashboard' ) ); ?>' ) ) {
			return;
		}

		btn.disabled = true;
		if ( spinner ) {
			spinner.classList.add( 'is-active' );
		}

		var body = new URLSearchParams();
		body.set( 'action', 'ggm_clear_error_log' );
		body.set( 'nonce', '<?php echo esc_js( wp_create_nonce( 'ggm_admin_nonce' ) ); ?>' );

		fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( res ) {
				btn.disabled = false;
				if ( spinner ) {
					spinner.classList.remove( 'is-active' );
				}
				feedback.style.display = 'block';
				if ( res.success ) {
					feedback.style.background = '#e8faf3';
					feedback.style.color = '#0e9e6e';
					feedback.textContent = res.data.message;
					if ( wrap ) {
						wrap.innerHTML = '<p style="color:#888; font-size:13px; margin:0;"><?php echo esc_js( __( 'No errors logged yet.', 'ggm-member-dashboard' ) ); ?></p>';
					}
				} else {
					feedback.style.background = '#fdecea';
					feedback.style.color = '#b91c1c';
					feedback.textContent = ( res.data && res.data.message ) || '<?php echo esc_js( __( 'Failed to clear log.', 'ggm-member-dashboard' ) ); ?>';
				}
			} )
			.catch( function () {
				btn.disabled = false;
				if ( spinner ) {
					spinner.classList.remove( 'is-active' );
				}
				feedback.style.display = 'block';
				feedback.style.background = '#fdecea';
				feedback.style.color = '#b91c1c';
				feedback.textContent = '<?php echo esc_js( __( 'Server error.', 'ggm-member-dashboard' ) ); ?>';
			} );
	} );
}() );
</script>
