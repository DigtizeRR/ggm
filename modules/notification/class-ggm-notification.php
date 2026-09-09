<?php
/**
 * Notification handler — sends HTML emails for enrollment, payment, and new user login events.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Notification
 */
class GGM_Notification {

	/**
	 * Register hooks via loader.
	 *
	 * @param GGM_Loader $loader
	 */
	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'ggm_payment_success',      $this, 'send_payment_receipt',          10, 2 );
		$loader->add_action( 'ggm_workshop_registered', $this, 'send_workshop_registration_email', 10, 3 );
		$loader->add_action( 'wp_login',                 $this, 'schedule_welcome_email',        10, 2 );
		$loader->add_action( 'ggm_send_welcome_email',   $this, 'send_welcome_email',            10, 1 );
	}

	// -------------------------------------------------------------------------
	// Public Hook Handlers
	// -------------------------------------------------------------------------

	/**
	 * Send payment receipt email to the user.
	 *
	 * Fired by the ggm_payment_success action.
	 *
	 * @param int $user_id
	 * @param int $payment_id
	 */
	public function send_payment_receipt( $user_id, $payment_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$payment       = null;
		$amount        = '';
		$workshop_name = '';
		$coupon_code   = '';
		$discount      = '';
		$symbol        = get_option( 'ggm_currency_symbol', '&#8377;' );

		if ( class_exists( 'GGM_Payment' ) ) {
			$payment = GGM_Payment::get( $payment_id );
		}

		if ( $payment ) {
			$amount = $symbol . number_format( (float) $payment->amount, 2 );

			if ( ! empty( $payment->workshop_id ) ) {
				$workshop_name = get_the_title( $payment->workshop_id );
			}

			if ( ! empty( $payment->coupon_id ) && class_exists( 'GGM_Coupon' ) ) {
				$coupon = GGM_Coupon::get( $payment->coupon_id );
				if ( $coupon ) {
					$coupon_code = $coupon->code;
					$discount    = $symbol . number_format( (float) $payment->discount_amount, 2 );
				}
			}
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: %s: site name */
			__( 'Payment Receipt — %s', 'ggm-member-dashboard' ),
			$site_name
		);

		$body = $this->payment_receipt_template( array(
			'name'          => $user->display_name ? $user->display_name : $user->user_login,
			'amount'        => $amount,
			'workshop'      => $workshop_name,
			'coupon_code'   => $coupon_code,
			'discount'      => $discount,
			'payment_id'    => $payment ? $payment->id : $payment_id,
			'site_name'     => $site_name,
			'dashboard'     => get_permalink( get_option( 'ggm_dashboard_page_id' ) ),
		) );

		ggm_send_plugin_mail( $user->user_email, $subject, $body, $this->get_email_headers() );
	}

	/**
	 * Queue a welcome email without holding the login/OTP response open for an
	 * SMTP round trip.
	 *
	 * @param string  $user_login Login name.
	 * @param WP_User $user       Authenticated user.
	 */
	public function schedule_welcome_email( $user_login, $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$registered_time = strtotime( $user->user_registered );
		if ( ! $registered_time || ( time() - $registered_time ) > 60 ) {
			return;
		}

		$args = array( (int) $user->ID );
		if ( ! wp_next_scheduled( 'ggm_send_welcome_email', $args ) ) {
			wp_schedule_single_event( time() + 1, 'ggm_send_welcome_email', $args );
		}
	}

	/**
	 * Send the queued welcome email.
	 *
	 * @param int $user_id New user ID.
	 */
	public function send_welcome_email( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user ) {
			return;
		}

		$site_name    = get_bloginfo( 'name' );
		$subject      = sprintf(
			/* translators: %s: site name */
			__( 'Welcome to %s', 'ggm-member-dashboard' ),
			$site_name
		);
		$display_name = $user->display_name ? $user->display_name : $user->user_login;
		$dashboard    = get_permalink( get_option( 'ggm_dashboard_page_id' ) );

		$body = $this->welcome_email_template( array(
			'name'      => $display_name,
			'site_name' => $site_name,
			'dashboard' => $dashboard,
		) );

		ggm_send_plugin_mail( $user->user_email, $subject, $body, $this->get_email_headers() );
	}

	/**
	 * Send a confirmation email for a free workshop registration (no payment involved).
	 *
	 * Fired by the ggm_workshop_registered action.
	 *
	 * @param int      $user_id
	 * @param int      $workshop_id
	 * @param int|null $slot_id
	 */
	public function send_workshop_registration_email( $user_id, $workshop_id, $slot_id = null ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$workshop_name = get_the_title( $workshop_id );
		$slot_time     = '';
		if ( $slot_id && class_exists( 'GGM_Workshop_Slot' ) ) {
			$slot = GGM_Workshop_Slot::get( $slot_id );
			if ( $slot ) {
				$slot_time = GGM_Workshop_Slot::format_range( $slot );
			}
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: %s: workshop name */
			__( 'You are registered — %s', 'ggm-member-dashboard' ),
			$workshop_name
		);

		$body = $this->workshop_registration_email_template( array(
			'name'      => $user->display_name ? $user->display_name : $user->user_login,
			'workshop'  => $workshop_name,
			'slot_time' => $slot_time,
			'site_name' => $site_name,
			'dashboard' => get_permalink( get_option( 'ggm_dashboard_page_id' ) ),
		) );

		ggm_send_plugin_mail( $user->user_email, $subject, $body, $this->get_email_headers() );
	}

	// -------------------------------------------------------------------------
	// Private Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return standard HTML email headers array.
	 *
	 * @return string[]
	 */
	private function get_email_headers() {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( function_exists( 'ggm_custom_smtp_enabled' ) && ggm_custom_smtp_enabled() ) {
			return $headers;
		}

		$settings     = get_option( 'ggm_settings', array() );
		$from_name    = sanitize_text_field( $settings['ggm_smtp_from_name'] ?? get_bloginfo( 'name' ) );
		$from_address = sanitize_email( $settings['ggm_smtp_from_email'] ?? get_option( 'admin_email' ) );
		if ( $from_address ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_address . '>';
		}

		return $headers;
	}

	// -------------------------------------------------------------------------
	// Shared Email Shell
	//
	// Every plugin email (payment receipt, workshop registration, welcome)
	// renders through this one shell so they all share the same look: a
	// light page background, a white card with a bold greeting, a teal-
	// headed dark info card for the actual details, a pill-shaped CTA
	// button, and a plain-text footer with support/contact info.
	// -------------------------------------------------------------------------

	/** Brand colors shared by every email. */
	const COLOR_ACCENT      = '#1dbfd1';
	const COLOR_ACCENT_TEXT = '#0f8fa0';
	const COLOR_NAVY        = '#0f172a';
	const COLOR_BODY_TEXT   = '#334155';
	const COLOR_MUTED_TEXT  = '#64748b';
	const COLOR_GREEN       = '#16a34a';
	const COLOR_BOX_BG      = '#1e2733';
	const COLOR_BOX_BORDER  = 'rgba(255,255,255,0.08)';
	const COLOR_PAGE_BG     = '#eef4f8';

	/**
	 * Assemble one info row for the dark details card.
	 *
	 * @param string $label
	 * @param string $value       Already-escaped HTML (caller's responsibility).
	 * @param bool   $last        Omit the row divider when true.
	 * @param string $value_color Defaults to white.
	 * @param bool   $bold_value  Larger/bolder value text (used for the final amount).
	 * @return string
	 */
	private function email_row( $label, $value, $last = false, $value_color = '#ffffff', $bold_value = false ) {
		$border = $last ? '' : 'border-bottom:1px solid ' . self::COLOR_BOX_BORDER . ';';
		return '
				<tr>
					<td style="' . $border . 'padding:14px 4px;color:#94a3b8;font-size:14px;">' . esc_html( $label ) . '</td>
					<td style="' . $border . 'padding:14px 4px;color:' . esc_attr( $value_color ) . ';text-align:right;font-size:' . ( $bold_value ? '18px' : '14px' ) . ';font-weight:' . ( $bold_value ? '800' : '600' ) . ';">' . $value . '</td>
				</tr>';
	}

	/**
	 * Render the shared email shell around a card title + rows + CTA button.
	 *
	 * @param array $args {
	 *     @type string $name       Recipient's display name.
	 *     @type string $intro      One-line intro sentence under the greeting.
	 *     @type string $card_title Title shown on the teal card header bar.
	 *     @type string $rows_html  Pre-built <tr> markup from email_row().
	 *     @type string $cta_label  Button label.
	 *     @type string $cta_url    Button destination.
	 *     @type string $site_name
	 * }
	 * @return string
	 */
	private function render_email( array $args ) {
		$site_name = $args['site_name'];
		ob_start();
		?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( self::COLOR_PAGE_BG ); ?>;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 16px;background:<?php echo esc_attr( self::COLOR_PAGE_BG ); ?>;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;max-width:600px;width:100%;box-shadow:0 4px 20px rgba(15,23,42,0.06);">
	<tr>
		<td style="padding:40px 40px 8px;text-align:center;">
			<h1 style="color:<?php echo esc_attr( self::COLOR_ACCENT_TEXT ); ?>;margin:0 0 24px;font-size:20px;font-weight:800;"><?php echo esc_html( $site_name ); ?></h1>
			<h2 style="color:<?php echo esc_attr( self::COLOR_NAVY ); ?>;margin:0 0 12px;font-size:26px;font-weight:800;">
				<?php
				printf(
					/* translators: %s: member display name */
					esc_html__( 'Hi %s,', 'ggm-member-dashboard' ),
					esc_html( $args['name'] )
				);
				?>
			</h2>
			<p style="color:<?php echo esc_attr( self::COLOR_BODY_TEXT ); ?>;margin:0 0 28px;font-size:15px;"><?php echo esc_html( $args['intro'] ); ?></p>
		</td>
	</tr>
	<tr>
		<td style="padding:0 40px;">
			<table width="100%" cellpadding="0" cellspacing="0" style="border-radius:14px;overflow:hidden;">
				<tr>
					<td style="background:<?php echo esc_attr( self::COLOR_ACCENT ); ?>;padding:18px 24px;text-align:center;">
						<span style="color:#ffffff;font-size:18px;font-weight:800;"><?php echo esc_html( $args['card_title'] ); ?></span>
					</td>
				</tr>
				<tr>
					<td style="background:<?php echo esc_attr( self::COLOR_BOX_BG ); ?>;padding:8px 20px;">
						<table width="100%" cellpadding="0" cellspacing="0">
							<?php echo $args['rows_html']; // phpcs:ignore WordPress.Security.EscapeOutput -- built only by email_row(), which escapes label/value itself. ?>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php if ( ! empty( $args['cta_label'] ) && ! empty( $args['cta_url'] ) ) : ?>
	<tr>
		<td style="padding:32px 40px 40px;text-align:center;">
			<a href="<?php echo esc_url( $args['cta_url'] ); ?>"
			   style="display:inline-block;background:<?php echo esc_attr( self::COLOR_ACCENT ); ?>;color:<?php echo esc_attr( self::COLOR_NAVY ); ?>;text-decoration:none;font-weight:800;font-size:16px;padding:14px 44px;border-radius:999px;">
				<?php echo esc_html( $args['cta_label'] ); ?>
			</a>
		</td>
	</tr>
	<?php endif; ?>
</table>
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;margin-top:24px;">
	<tr>
		<td style="text-align:center;padding:20px 20px 0;border-top:1px solid #e2e8f0;">
			<p style="color:<?php echo esc_attr( self::COLOR_MUTED_TEXT ); ?>;font-size:13px;margin:16px 0;">
				<?php esc_html_e( 'If you have any questions or need help, please reach out to our support team.', 'ggm-member-dashboard' ); ?>
			</p>
			<p style="color:<?php echo esc_attr( self::COLOR_ACCENT_TEXT ); ?>;font-size:14px;font-weight:700;margin:0 0 4px;"><?php echo esc_html( $site_name ); ?></p>
			<p style="color:<?php echo esc_attr( self::COLOR_MUTED_TEXT ); ?>;font-size:12px;margin:0 0 4px;"><?php echo esc_html( get_option( 'admin_email' ) ); ?></p>
			<p style="color:#a0aec0;font-size:11px;margin:0;">&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . $site_name ); ?> <?php esc_html_e( 'All Rights Reserved.', 'ggm-member-dashboard' ); ?></p>
		</td>
	</tr>
</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Email Templates
	// -------------------------------------------------------------------------

	/**
	 * Build payment receipt email HTML.
	 *
	 * @param array $data Keys: name, amount, workshop, coupon_code, discount, payment_id, site_name, dashboard.
	 * @return string
	 */
	private function payment_receipt_template( array $data ) {
		$rows = '';
		if ( ! empty( $data['workshop'] ) ) {
			$rows .= $this->email_row( __( 'Workshop', 'ggm-member-dashboard' ), esc_html( $data['workshop'] ) );
		}
		if ( ! empty( $data['coupon_code'] ) ) {
			$rows .= $this->email_row( __( 'Coupon Applied', 'ggm-member-dashboard' ), esc_html( $data['coupon_code'] ) );
			$rows .= $this->email_row( __( 'Discount', 'ggm-member-dashboard' ), '-' . esc_html( $data['discount'] ), false, self::COLOR_GREEN );
		}
		if ( ! empty( $data['amount'] ) ) {
			$rows .= $this->email_row( __( 'Amount', 'ggm-member-dashboard' ), esc_html( $data['amount'] ), false, '#ffffff', true );
		}
		if ( ! empty( $data['payment_id'] ) ) {
			$rows .= $this->email_row( __( 'Reference', 'ggm-member-dashboard' ), esc_html( $data['payment_id'] ), true );
		}

		return $this->render_email( array(
			'name'       => $data['name'],
			'intro'      => __( 'Thank you! Your payment has been processed successfully.', 'ggm-member-dashboard' ),
			'card_title' => __( 'Payment Received', 'ggm-member-dashboard' ),
			'rows_html'  => $rows,
			'cta_label'  => __( 'Go to Dashboard', 'ggm-member-dashboard' ),
			'cta_url'    => $data['dashboard'],
			'site_name'  => $data['site_name'],
		) );
	}

	/**
	 * Build free-workshop registration confirmation email HTML.
	 *
	 * @param array $data Keys: name, workshop, slot_time, site_name, dashboard.
	 * @return string
	 */
	private function workshop_registration_email_template( array $data ) {
		$rows  = $this->email_row( __( 'Workshop', 'ggm-member-dashboard' ), esc_html( $data['workshop'] ), empty( $data['slot_time'] ) );
		$rows .= empty( $data['slot_time'] ) ? '' : $this->email_row( __( 'Time Slot', 'ggm-member-dashboard' ), esc_html( $data['slot_time'] ), true );

		return $this->render_email( array(
			'name'       => $data['name'],
			'intro'      => __( 'You are registered for the following workshop.', 'ggm-member-dashboard' ),
			'card_title' => __( 'Registration Confirmed!', 'ggm-member-dashboard' ),
			'rows_html'  => $rows,
			'cta_label'  => __( 'Go to Dashboard', 'ggm-member-dashboard' ),
			'cta_url'    => $data['dashboard'],
			'site_name'  => $data['site_name'],
		) );
	}

	/**
	 * Build welcome email HTML for brand-new members.
	 *
	 * @param array $data Keys: name, site_name, dashboard.
	 * @return string
	 */
	private function welcome_email_template( array $data ) {
		$rows = $this->email_row( __( 'Account', 'ggm-member-dashboard' ), esc_html( $data['name'] ), true );

		return $this->render_email( array(
			'name'       => $data['name'],
			/* translators: %s: site name */
			'intro'      => sprintf( __( 'Your account on %s has been created. Head to your member dashboard to explore your benefits.', 'ggm-member-dashboard' ), $data['site_name'] ),
			'card_title' => __( 'Welcome Aboard!', 'ggm-member-dashboard' ),
			'rows_html'  => $rows,
			'cta_label'  => __( 'Visit Your Dashboard', 'ggm-member-dashboard' ),
			'cta_url'    => $data['dashboard'],
			'site_name'  => $data['site_name'],
		) );
	}
}
