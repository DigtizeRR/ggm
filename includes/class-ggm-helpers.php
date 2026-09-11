<?php
/**
 * Global Helper Functions.
 *
 * Provides settings extraction, access control evaluations, and OTP delivery channels.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieve a GGM setting value.
 *
 * @param string $key     Settings array key.
 * @param mixed  $default Default value if key is not found.
 * @return mixed
 */
function ggm_get_setting( $key, $default = '' ) {
	$settings = get_option( 'ggm_settings', array() );
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Resolve a Webinar Additional Block heading.
 *
 * @param string $section discover, why_different, perfect_for, or faq.
 * @param int    $workshop_id Optional workshop whose override should be used.
 * @return string
 */
function ggm_get_workshop_block_heading( $section, $workshop_id = 0 ) {
	$headings = array(
		'discover'      => array( 'ggm_workshop_discover_heading', 'ggm_workshop_discover_heading_override', __( 'You Will Discover', 'ggm-member-dashboard' ) ),
		'why_different' => array( 'ggm_workshop_why_different_heading', 'ggm_workshop_why_different_heading_override', __( 'Why This Webinar Is Different', 'ggm-member-dashboard' ) ),
		'perfect_for'   => array( 'ggm_workshop_perfect_for_heading', 'ggm_workshop_perfect_for_heading_override', __( 'Perfect For You If You Want To', 'ggm-member-dashboard' ) ),
		'faq'           => array( 'ggm_workshop_faq_heading', 'ggm_workshop_faq_heading_override', __( 'Frequently Asked Questions', 'ggm-member-dashboard' ) ),
	);

	if ( ! isset( $headings[ $section ] ) ) {
		return '';
	}

	$workshop_id = absint( $workshop_id );
	if ( $workshop_id && in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
		$override_raw = get_post_meta( $workshop_id, $headings[ $section ][1], true );
		$override     = is_scalar( $override_raw ) ? trim( (string) $override_raw ) : '';
		if ( '' !== $override ) {
			return $override;
		}
	}

	$value_raw = ggm_get_setting( $headings[ $section ][0], '' );
	$value     = is_scalar( $value_raw ) ? trim( (string) $value_raw ) : '';
	return '' !== $value ? $value : $headings[ $section ][2];
}

/**
 * Verify an AJAX nonce, but — unlike check_ajax_referer()'s default
 * behavior — never die with a bare HTTP 403 and no body. That response has
 * no JSON for jQuery/fetch to parse, so the browser falls into a generic
 * network-failure handler with no useful message at all (typically shown
 * to the visitor as something like "Something went wrong. Please try
 * again."). Sends a clean, actionable JSON error instead and stops
 * execution, exactly like check_ajax_referer() would.
 *
 * The most common real cause of a nonce failure here is a page-cache
 * plugin serving a stale copy of a page with a nonce baked in from a
 * different visitor/session — see GGM_Public::prevent_caching_on_dynamic_pages().
 *
 * @param string $action  Nonce action name.
 * @param string $arg     $_REQUEST key the nonce is read from.
 */
function ggm_verify_ajax_nonce( $action = 'ggm_nonce', $arg = 'nonce' ) {
	if ( ! check_ajax_referer( $action, $arg, false ) ) {
		wp_send_json_error( array(
			'message' => __( 'Your session has expired. Please refresh the page and try again.', 'ggm-member-dashboard' ),
			'expired' => true,
		) );
	}
}

/**
 * Get the visitor's IP address for rate-limiting purposes.
 * Deliberately reads REMOTE_ADDR only — request headers like
 * X-Forwarded-For are attacker-controlled unless a trusted proxy strips
 * and re-sets them, and trusting them here would let an attacker forge a
 * fresh "IP" on every request to sidestep the rate limits below.
 *
 * @return string Empty string if unavailable/invalid.
 */
function ggm_get_client_ip() {
	$ip = $_SERVER['REMOTE_ADDR'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/**
 * Increment a transient-backed counter and report whether it has exceeded
 * a maximum within a time window — the same pattern GGM_OTP already used
 * inline for OTP send/verify limits, extracted here so password-login
 * lockout and other rate limits can reuse it.
 *
 * @param string $key            Unique transient key for this limiter.
 * @param int    $max            Maximum hits allowed within the window.
 * @param int    $window_seconds Window length in seconds.
 * @return bool True if this hit pushed the count over the limit.
 */
function ggm_rate_limit_hit( $key, $max, $window_seconds ) {
	$count = (int) get_transient( $key );
	if ( $count >= $max ) {
		return true;
	}
	set_transient( $key, $count + 1, $window_seconds );
	return false;
}

/**
 * Check if user has access to a specific course.
 *
 * @param int $course_id
 * @param int $user_id
 * @return bool
 */
function ggm_user_has_course_access( $course_id, $user_id ) {
	if ( ! $user_id ) {
		return false;
	}

	// 1. Admin bypass.
	if ( user_can( $user_id, 'administrator' ) ) {
		return true;
	}

	// 2. Direct one-time course purchase recorded in wp_ggm_course_access
	// (a course with its own price, bought via checkout — independent of
	// any membership plan).
	global $wpdb;
	$access_table = $wpdb->prefix . 'ggm_course_access';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $access_table ) ) === $access_table ) {
		$purchased = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$access_table}` WHERE user_id = %d AND course_id = %d AND (expires_at IS NULL OR expires_at > %s)",
			$user_id,
			$course_id,
			current_time( 'mysql' )
		) );
		if ( $purchased > 0 ) {
			return true;
		}
	}

	// 3. A course the admin explicitly marked "Free" or "0" (as opposed to
	// simply leaving the Course Price field blank/unconfigured) is open to
	// everyone. A blank price does NOT mean free; it stays locked unless the
	// user has bought the course directly.
	$course_price_raw = get_post_meta( $course_id, 'course_price', true );
	if ( '' !== trim( (string) $course_price_raw ) && class_exists( 'GGM_Workshop' ) && GGM_Workshop::parse_price( $course_price_raw ) <= 0.0 ) {
		return true;
	}

	return false;
}

/**
 * Check if user has access to a specific workshop.
 *
 * @param int $workshop_id
 * @param int $user_id
 * @return bool
 */
function ggm_user_has_workshop_access( $workshop_id, $user_id ) {
	if ( ! $user_id ) {
		return false;
	}
	return class_exists( 'GGM_Workshop' ) && GGM_Workshop::user_has_access( $user_id, $workshop_id );
}

/**
 * Return normalized custom SMTP settings.
 *
 * @return array
 */
function ggm_get_custom_smtp_settings() {
	$settings = get_option( 'ggm_settings', array() );

	$from_email = sanitize_email( $settings['ggm_smtp_from_email'] ?? '' );
	$username   = sanitize_text_field( $settings['ggm_smtp_username'] ?? '' );
	if ( ! $from_email && is_email( $username ) ) {
		$from_email = sanitize_email( $username );
	}

	return array(
		'enabled'    => ! empty( $settings['ggm_smtp_enabled'] ),
		'host'       => sanitize_text_field( $settings['ggm_smtp_host'] ?? '' ),
		'port'       => absint( $settings['ggm_smtp_port'] ?? 587 ),
		'encryption' => sanitize_key( $settings['ggm_smtp_encryption'] ?? 'tls' ),
		'auth'       => ! empty( $settings['ggm_smtp_auth'] ) || ( '' !== $username && '' !== (string) ( $settings['ggm_smtp_password'] ?? '' ) ),
		'username'   => $username,
		'password'   => (string) ( $settings['ggm_smtp_password'] ?? '' ),
		'from_email' => $from_email,
		'from_name'  => sanitize_text_field( $settings['ggm_smtp_from_name'] ?? get_bloginfo( 'name' ) ),
	);
}

/**
 * Whether custom SMTP has enough configuration to own outgoing mail.
 *
 * @param array|null $smtp Optional normalized SMTP settings.
 * @return bool
 */
function ggm_custom_smtp_enabled( $smtp = null ) {
	$smtp = is_array( $smtp ) ? $smtp : ggm_get_custom_smtp_settings();
	return ! empty( $smtp['enabled'] ) && ! empty( $smtp['host'] );
}

/**
 * Mask an email address before it is written to diagnostics.
 *
 * @param string $email Email address.
 * @return string
 */
function ggm_mask_email_address( $email ) {
	$email  = sanitize_email( $email );
	$at_pos = strpos( $email, '@' );
	if ( false === $at_pos || 0 === $at_pos ) {
		return '****';
	}

	return substr( $email, 0, 1 ) . str_repeat( '*', $at_pos - 1 ) . substr( $email, $at_pos );
}

/**
 * Configure PHPMailer with custom SMTP settings when enabled.
 * Hooked onto phpmailer_init so it runs for every wp_mail() call.
 */
function ggm_configure_phpmailer_smtp( $phpmailer, $smtp = null ) {
	// If ggm_send_plugin_mail() is actively running, skip global callback to prevent duplicate configuration.
	global $ggm_plugin_mail_smtp;
	if ( ! empty( $ggm_plugin_mail_smtp ) ) {
		return;
	}

	$smtp = is_array( $smtp ) ? $smtp : ggm_get_custom_smtp_settings();
	if ( ! ggm_custom_smtp_enabled( $smtp ) ) {
		return;
	}

	// Log the configuration being applied
	if ( class_exists( 'GGM_Meta_Boxes' ) && is_object( $phpmailer ) ) {
		GGM_Meta_Boxes::log_error( 'Configuring PHPMailer for SMTP', array(
			'level'        => 'info',
			'host'         => $smtp['host'],
			'port'         => $smtp['port'],
			'encryption'   => $smtp['encryption'],
			'auth'         => $smtp['auth'] ? 'yes' : 'no',
			'username_set' => ! empty( $smtp['username'] ) ? 'yes' : 'no',
			'from_email'   => $smtp['from_email'] ?? '',
		) );
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = $smtp['host'];
	$phpmailer->Port       = $smtp['port'];
	$phpmailer->SMTPAuth   = $smtp['auth'];
	$phpmailer->Username   = $smtp['username'];
	$phpmailer->Password   = $smtp['password'];

	if ( 'ssl' === $smtp['encryption'] ) {
		$phpmailer->SMTPSecure = 'ssl';
		$phpmailer->SMTPAutoTLS = false;
	} elseif ( 'tls' === $smtp['encryption'] ) {
		$phpmailer->SMTPSecure = 'tls';
		$phpmailer->SMTPAutoTLS = true;
	} else {
		$phpmailer->SMTPSecure = '';
		$phpmailer->SMTPAutoTLS = false;
	}

	if ( ! empty( $smtp['from_email'] ) ) {
		$phpmailer->setFrom( $smtp['from_email'], $smtp['from_name'], false );
		$phpmailer->Sender = $smtp['from_email'];
	}

	// Log the final mailer type
	if ( class_exists( 'GGM_Meta_Boxes' ) && is_object( $phpmailer ) ) {
		GGM_Meta_Boxes::log_error( 'PHPMailer configuration complete', array(
			'level'       => 'info',
			'mailer'      => isset( $phpmailer->Mailer ) ? $phpmailer->Mailer : 'unknown',
			'smtp_secure' => $phpmailer->SMTPSecure ?? '',
			'smtp_auth'   => $phpmailer->SMTPAuth ?? '',
		) );
	}
}
add_action( 'phpmailer_init', 'ggm_configure_phpmailer_smtp', 999 );

/**
 * Force WordPress-level From address to match custom SMTP when enabled.
 *
 * @param string $email Existing From email.
 * @return string
 */
function ggm_custom_smtp_mail_from( $email ) {
	$smtp = ggm_get_custom_smtp_settings();
	return ggm_custom_smtp_enabled( $smtp ) && ! empty( $smtp['from_email'] ) ? $smtp['from_email'] : $email;
}
add_filter( 'wp_mail_from', 'ggm_custom_smtp_mail_from', 999 );

/**
 * Force WordPress-level From name to match custom SMTP when enabled.
 *
 * @param string $name Existing From name.
 * @return string
 */
function ggm_custom_smtp_mail_from_name( $name ) {
	$smtp = ggm_get_custom_smtp_settings();
	return ggm_custom_smtp_enabled( $smtp ) && ! empty( $smtp['from_name'] ) ? $smtp['from_name'] : $name;
}
add_filter( 'wp_mail_from_name', 'ggm_custom_smtp_mail_from_name', 999 );

/**
 * Send plugin-owned email, forcing the saved Custom SMTP settings when enabled.
 *
 * Some hosts/plugins register mail hooks after this plugin, so this wrapper
 * applies the DZ LMS SMTP settings again for the current send at the highest
 * priority. That keeps OTP and other plugin emails on the same working route
 * as the SMTP test.
 *
 * @param string|array $to          Recipient email(s).
 * @param string       $subject     Email subject.
 * @param string       $message     Email body.
 * @param string|array $headers     Email headers.
 * @param string|array $attachments Attachment path(s).
 * @return bool
 */
function ggm_send_plugin_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
	$smtp       = ggm_get_custom_smtp_settings();
	$recipients = is_array( $to ) ? $to : preg_split( '/\s*,\s*/', (string) $to );
	$masked_to  = implode( ', ', array_map( 'ggm_mask_email_address', array_filter( $recipients ) ) );
	if ( ! ggm_custom_smtp_enabled( $smtp ) ) {
		if ( class_exists( 'GGM_Meta_Boxes' ) ) {
			GGM_Meta_Boxes::log_error( 'Plugin mail sent via default wp_mail (SMTP not enabled)', array(
				'to'           => $masked_to,
				'subject'      => $subject,
				'smtp_enabled' => 'no',
			) );
		}
		return wp_mail( $to, $subject, $message, $headers, $attachments );
	}

	// Log SMTP configuration being used
	if ( class_exists( 'GGM_Meta_Boxes' ) ) {
		GGM_Meta_Boxes::log_error( 'Plugin mail: applying SMTP configuration', array(
			'level'           => 'info',
			'to'              => $masked_to,
			'subject'         => $subject,
			'smtp_host'       => $smtp['host'] ?? '',
			'smtp_port'       => $smtp['port'] ?? '',
			'smtp_encryption' => $smtp['encryption'] ?? '',
			'smtp_auth'       => ! empty( $smtp['auth'] ) ? 'yes' : 'no',
			'smtp_username'   => $smtp['username'] ? 'set' : 'empty',
			'smtp_from'       => $smtp['from_email'] ?? '',
		) );
	}

	// Set global for the mailer callback
	global $ggm_plugin_mail_smtp;
	$ggm_plugin_mail_smtp = $smtp;

	// Initialize SMTP debug capture buffer if debug is active.
	// Uses a global flag (not define()) so it works across multiple requests.
	$debug_active = ! empty( $GLOBALS['ggm_smtp_debug_active'] );
	if ( $debug_active ) {
		$GLOBALS['ggm_smtp_debug_log'] = array();
	}

	// Use a static named callback to ensure proper removal
	$mailer_callback = 'ggm_configure_phpmailer_smtp_for_plugin_mail';
	if ( ! has_action( 'phpmailer_init', $mailer_callback ) ) {
		add_action( 'phpmailer_init', $mailer_callback, PHP_INT_MAX );
	}

	$from_callback = static function ( $email ) use ( $smtp ) {
		return ! empty( $smtp['from_email'] ) ? $smtp['from_email'] : $email;
	};
	$name_callback = static function ( $name ) use ( $smtp ) {
		return ! empty( $smtp['from_name'] ) ? $smtp['from_name'] : $name;
	};

	add_filter( 'wp_mail_from', $from_callback, PHP_INT_MAX );
	add_filter( 'wp_mail_from_name', $name_callback, PHP_INT_MAX );

	try {
		$result = wp_mail( $to, $subject, $message, $headers, $attachments );
		if ( class_exists( 'GGM_Meta_Boxes' ) ) {
			global $phpmailer;
			$mailer_error = isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ? sanitize_text_field( $phpmailer->ErrorInfo ) : '';
			$mailer_type  = isset( $phpmailer ) && is_object( $phpmailer ) && isset( $phpmailer->Mailer ) ? $phpmailer->Mailer : 'unknown';

			// ── PHPMailer final state ──────────────────────────────
			$final_state = array(
				'wp_mail_result' => $result ? 'true' : 'false',
			);
			if ( isset( $phpmailer ) && is_object( $phpmailer ) ) {
				$final_state['mailer']      = $phpmailer->Mailer ?? 'unknown';
				$final_state['host']        = $phpmailer->Host ?? '';
				$final_state['port']        = $phpmailer->Port ?? '';
				$final_state['smtp_secure'] = $phpmailer->SMTPSecure ?? '';
				$final_state['smtp_auth']   = ! empty( $phpmailer->SMTPAuth ) ? 'yes' : 'no';
				$final_state['from']        = $phpmailer->From ?? '';
				$final_state['from_name']   = $phpmailer->FromName ?? '';
				$final_state['sender']      = $phpmailer->Sender ?? '';

				// Correctly iterate getToAddresses() — returns [[email, name], …]
				if ( method_exists( $phpmailer, 'getToAddresses' ) ) {
					$to_addrs  = $phpmailer->getToAddresses();
					$masked_to = array();
					foreach ( $to_addrs as $entry ) {
						$addr = is_array( $entry ) ? ( $entry[0] ?? '' ) : (string) $entry;
						$at   = strpos( $addr, '@' );
						$masked_to[] = ( false !== $at && $at > 0 )
							? substr( $addr, 0, 1 ) . str_repeat( '*', $at - 1 ) . substr( $addr, $at )
							: '****';
					}
					$final_state['recipients'] = implode( ', ', $masked_to );
				}

				// Reply-To
				if ( method_exists( $phpmailer, 'getReplyToAddresses' ) ) {
					$reply_to = $phpmailer->getReplyToAddresses();
					$final_state['reply_to'] = ! empty( $reply_to ) ? implode( ', ', array_keys( $reply_to ) ) : 'none';
				}

				$msg_id = '';
				if ( method_exists( $phpmailer, 'getLastMessageID' ) && ! empty( $phpmailer->getLastMessageID() ) ) {
					$msg_id = $phpmailer->getLastMessageID();
				} elseif ( ! empty( $phpmailer->MessageID ) ) {
					$msg_id = $phpmailer->MessageID;
				}

				$final_state['content_type'] = $phpmailer->ContentType ?? '';
				$final_state['message_id']   = $msg_id;
				$final_state['mailer_error'] = $mailer_error;

				// Mailer sanity: if not 'smtp', the SMTP config is not controlling the email
				if ( 'smtp' !== ( $phpmailer->Mailer ?? '' ) ) {
					$final_state['WARNING'] = 'Mailer is not smtp — SMTP configuration is NOT controlling this email';
				}
			}

			$final_state['level'] = $result ? 'info' : 'error';
			GGM_Meta_Boxes::log_error( 'Plugin mail: wp_mail completed', $final_state );

			// ── SMTP Transaction Summary ──────────────────────────
			if ( $debug_active && ! empty( $GLOBALS['ggm_smtp_debug_log'] ) ) {
				$summary = ggm_parse_smtp_transaction_log( $GLOBALS['ggm_smtp_debug_log'] );
				$summary['level'] = ( 'SMTP server accepted message for delivery' === ( $summary['overall'] ?? '' ) ) ? 'success' : 'warning';
				GGM_Meta_Boxes::log_error( 'SMTP Transaction Summary', $summary );
			}
		}
		return $result;
	} finally {
		remove_action( 'phpmailer_init', $mailer_callback, PHP_INT_MAX );
		remove_filter( 'wp_mail_from', $from_callback, PHP_INT_MAX );
		remove_filter( 'wp_mail_from_name', $name_callback, PHP_INT_MAX );
		$ggm_plugin_mail_smtp = null;
		// Reset debug capture
		unset( $GLOBALS['ggm_smtp_debug_log'] );
	}
}

/**
 * PHPMailer configuration callback specifically for plugin mail.
 * Uses the global $ggm_plugin_mail_smtp to pass settings.
 *
 * @param PHPMailer $phpmailer
 */
function ggm_configure_phpmailer_smtp_for_plugin_mail( $phpmailer ) {
	global $ggm_plugin_mail_smtp;
	if ( empty( $ggm_plugin_mail_smtp ) || ! ggm_custom_smtp_enabled( $ggm_plugin_mail_smtp ) ) {
		return;
	}

	if ( class_exists( 'GGM_Meta_Boxes' ) && is_object( $phpmailer ) ) {
		GGM_Meta_Boxes::log_error( 'PHPMailer init fired (plugin-mail callback)', array(
			'host'         => $ggm_plugin_mail_smtp['host'],
			'port'         => $ggm_plugin_mail_smtp['port'],
			'encryption'   => $ggm_plugin_mail_smtp['encryption'],
			'auth'         => $ggm_plugin_mail_smtp['auth'] ? 'yes' : 'no',
			'username_set' => ! empty( $ggm_plugin_mail_smtp['username'] ) ? 'yes' : 'no',
			'from_email'   => $ggm_plugin_mail_smtp['from_email'] ?? '',
		) );
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = $ggm_plugin_mail_smtp['host'];
	$phpmailer->Port       = $ggm_plugin_mail_smtp['port'];
	$phpmailer->SMTPAuth   = $ggm_plugin_mail_smtp['auth'];
	$phpmailer->Username   = $ggm_plugin_mail_smtp['username'];
	$phpmailer->Password   = $ggm_plugin_mail_smtp['password'];

	if ( 'ssl' === $ggm_plugin_mail_smtp['encryption'] ) {
		$phpmailer->SMTPSecure  = 'ssl';
		$phpmailer->SMTPAutoTLS = false;
	} elseif ( 'tls' === $ggm_plugin_mail_smtp['encryption'] ) {
		$phpmailer->SMTPSecure  = 'tls';
		$phpmailer->SMTPAutoTLS = true;
	} else {
		$phpmailer->SMTPSecure  = '';
		$phpmailer->SMTPAutoTLS = false;
	}

	if ( ! empty( $ggm_plugin_mail_smtp['from_email'] ) ) {
		$phpmailer->setFrom( $ggm_plugin_mail_smtp['from_email'], $ggm_plugin_mail_smtp['from_name'], false );
		$phpmailer->Sender = $ggm_plugin_mail_smtp['from_email'];
	}

	// ── SMTP debug capture (buffered) ────────────────────────
	// Uses a resettable global flag so it works across multiple
	// requests in the same process (unlike define()).
	if ( ! empty( $GLOBALS['ggm_smtp_debug_active'] ) ) {
		$phpmailer->SMTPDebug   = 4; // 4 = low-level data, full conversation
		$phpmailer->Debugoutput = static function ( $str, $level ) {
			// Credential redaction: strip AUTH payloads, passwords, tokens
			$redacted = $str;
			if ( preg_match( '/^CLIENT\s*->\s*SERVER\s*:/i', $redacted ) ) {
				// Redact base64 auth payloads (lines after AUTH LOGIN/PLAIN)
				if ( preg_match( '/^(CLIENT\s*->\s*SERVER\s*:\s*)(?!EHLO|HELO|STARTTLS|MAIL|RCPT|DATA|QUIT|AUTH|RSET|NOOP)(.+)$/i', $redacted, $m ) ) {
					$redacted = $m[1] . '[CREDENTIALS REDACTED]';
				}
			}
			$redacted = preg_replace_callback(
				'/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
				static function ( $matches ) {
					return ggm_mask_email_address( $matches[0] );
				},
				$redacted
			);
			if ( isset( $GLOBALS['ggm_smtp_debug_log'] ) && is_array( $GLOBALS['ggm_smtp_debug_log'] ) ) {
				$GLOBALS['ggm_smtp_debug_log'][] = trim( $redacted );
			}
		};
	}

	// ── Pre-send PHPMailer state ─────────────────────────────
	if ( class_exists( 'GGM_Meta_Boxes' ) && is_object( $phpmailer ) ) {
		// Correctly extract recipients — getToAddresses() returns [[email, name], …]
		$pre_recipients = 'unknown';
		if ( method_exists( $phpmailer, 'getToAddresses' ) ) {
			$pre_masked = array();
			foreach ( $phpmailer->getToAddresses() as $entry ) {
				$addr = is_array( $entry ) ? ( $entry[0] ?? '' ) : (string) $entry;
				$at   = strpos( $addr, '@' );
				$pre_masked[] = ( false !== $at && $at > 0 )
					? substr( $addr, 0, 1 ) . str_repeat( '*', $at - 1 ) . substr( $addr, $at )
					: '****';
			}
			$pre_recipients = implode( ', ', $pre_masked );
		}

		GGM_Meta_Boxes::log_error( 'PHPMailer final state (pre-send)', array(
			'level'        => 'info',
			'mailer'       => $phpmailer->Mailer ?? 'unknown',
			'host'         => $phpmailer->Host ?? '',
			'port'         => $phpmailer->Port ?? '',
			'smtp_secure'  => $phpmailer->SMTPSecure ?? '',
			'smtp_auth'    => ! empty( $phpmailer->SMTPAuth ) ? 'yes' : 'no',
			'from'         => $phpmailer->From ?? '',
			'from_name'    => $phpmailer->FromName ?? '',
			'sender'       => $phpmailer->Sender ?? '',
			'recipients'   => $pre_recipients,
			'content_type' => $phpmailer->ContentType ?? '',
		) );
	}
}

/**
 * Parse a buffered SMTP debug log into a structured transaction summary.
 *
 * Extracts SMTP response codes for each stage:
 * connection, EHLO, STARTTLS, AUTH, MAIL FROM, RCPT TO, DATA, QUIT.
 * Redacts any credential material that slipped through.
 *
 * @param array $log_lines Buffered SMTP debug lines.
 * @return array Structured summary keyed by stage.
 */
function ggm_parse_smtp_transaction_log( $log_lines ) {
	$summary = array(
		'connection'   => 'not captured',
		'ehlo'         => 'not captured',
		'starttls'     => 'not attempted',
		'auth'         => 'not captured',
		'mail_from'    => 'not captured',
		'rcpt_to'      => 'not captured',
		'data'         => 'not captured',
		'quit'         => 'not captured',
		'total_lines'  => count( $log_lines ),
	);

	$client_queue = array();
	$raw_history  = array();

	foreach ( $log_lines as $line ) {
		$line = trim( (string) $line );
		if ( empty( $line ) ) {
			continue;
		}

		// Keep sanitized sample of transcript (up to 20 lines)
		if ( count( $raw_history ) < 20 ) {
			$raw_history[] = $line;
		}

		// Client command
		if ( preg_match( '/CLIENT\s*->\s*SERVER\s*:\s*(.+)/i', $line, $m ) ) {
			$cmd = strtoupper( trim( $m[1] ) );
			$client_queue[] = $cmd;
			continue;
		}

		// Server response (e.g. "SERVER -> CLIENT: 250-..." or "SERVER -> CLIENT: 250 ...")
		if ( preg_match( '/SERVER\s*->\s*CLIENT\s*:\s*(\d{3})([\s-]?.*)/i', $line, $m ) ) {
			$code     = $m[1];
			$is_cont  = ( isset( $m[2][0] ) && '-' === $m[2][0] ); // 250- continuation line
			$detail   = sanitize_text_field( trim( substr( $m[2], 1 ) ) );
			$response = $code . ( $detail ? ' ' . substr( $detail, 0, 120 ) : '' );

			// Initial connection banner (220)
			if ( '220' === $code && 'not captured' === $summary['connection'] && empty( $client_queue ) ) {
				$summary['connection'] = $response;
				continue;
			}

			// Get the current active client command
			$current_cmd = ! empty( $client_queue ) ? $client_queue[0] : '';

			// If it's a multi-line response (e.g. 250-), do not pop queue yet
			if ( ! $is_cont && ! empty( $client_queue ) ) {
				// Non-continuation response finishes this command
				array_shift( $client_queue );
			}

			// EHLO/HELO
			if ( 0 === strpos( $current_cmd, 'EHLO' ) || 0 === strpos( $current_cmd, 'HELO' ) ) {
				$summary['ehlo'] = $response;
				continue;
			}

			// STARTTLS
			if ( 0 === strpos( $current_cmd, 'STARTTLS' ) ) {
				$summary['starttls'] = $response;
				continue;
			}

			// AUTH
			if ( 0 === strpos( $current_cmd, 'AUTH' ) || 0 === strpos( $current_cmd, '[CREDENTIALS' ) ) {
				if ( in_array( $code, array( '235', '535', '534', '530' ), true ) ) {
					$summary['auth'] = $response;
				} elseif ( 'not captured' === $summary['auth'] ) {
					$summary['auth'] = $response;
				}
				continue;
			}

			// MAIL FROM
			if ( 0 === strpos( $current_cmd, 'MAIL FROM' ) || 0 === strpos( $current_cmd, 'MAIL' ) ) {
				$summary['mail_from'] = $response;
				continue;
			}

			// RCPT TO
			if ( 0 === strpos( $current_cmd, 'RCPT TO' ) || 0 === strpos( $current_cmd, 'RCPT' ) ) {
				$summary['rcpt_to'] = $response;
				continue;
			}

			// DATA
			if ( 0 === strpos( $current_cmd, 'DATA' ) || '354' === $code || 0 === strpos( $current_cmd, '.' ) ) {
				if ( '250' === $code ) {
					$summary['data'] = $response;
				} elseif ( 'not captured' === $summary['data'] ) {
					$summary['data'] = $response;
				}
				continue;
			}

			// QUIT
			if ( 0 === strpos( $current_cmd, 'QUIT' ) ) {
				$summary['quit'] = $response;
				continue;
			}

			// Late 250 acceptance after DATA payload
			if ( '250' === $code && ( 'not captured' === $summary['data'] || 0 === strpos( (string) $summary['data'], '354' ) ) ) {
				$summary['data'] = $response;
			}
		}

		// Connection errors
		if ( stripos( $line, 'Connection: closed' ) !== false
			|| stripos( $line, 'Connection failed' ) !== false
			|| stripos( $line, 'Connection timed out' ) !== false ) {
			if ( 'not captured' === $summary['connection'] ) {
				$summary['connection'] = 'FAILED: ' . sanitize_text_field( substr( $line, 0, 150 ) );
			}
		}
	}

	// Derive overall status
	$auth_ok     = ( 0 === strpos( (string) $summary['auth'], '235' ) );
	$rcpt_ok     = ( 'not captured' !== $summary['rcpt_to'] && ( 0 === strpos( (string) $summary['rcpt_to'], '250' ) || 0 === strpos( (string) $summary['rcpt_to'], '251' ) ) );
	$data_ok     = ( 'not captured' !== $summary['data'] && 0 === strpos( (string) $summary['data'], '250' ) );
	$conn_ok     = ( 0 === strpos( (string) $summary['connection'], '220' ) );
	$starttls_ok = ( 'not attempted' === $summary['starttls'] || 0 === strpos( (string) $summary['starttls'], '220' ) );

	if ( $conn_ok && $starttls_ok && $auth_ok && $rcpt_ok && $data_ok ) {
		$summary['overall'] = 'SMTP server accepted message for delivery';
	} elseif ( ! $conn_ok ) {
		$summary['overall'] = 'SMTP connection failed';
	} elseif ( ! $starttls_ok ) {
		$summary['overall'] = 'STARTTLS failed';
	} elseif ( ! $auth_ok ) {
		$summary['overall'] = 'SMTP authentication failed';
	} elseif ( 'not captured' !== $summary['rcpt_to'] && ! $rcpt_ok ) {
		$summary['overall'] = 'RCPT TO rejected by SMTP server';
	} elseif ( 'not captured' !== $summary['data'] && ! $data_ok ) {
		$summary['overall'] = 'DATA rejected by SMTP server';
	} else {
		$summary['overall'] = 'SMTP transaction completed';
	}

	if ( ! empty( $raw_history ) ) {
		$summary['transcript_sample'] = implode( ' | ', array_slice( $raw_history, 0, 10 ) );
	}

	return $summary;
}

/**
 * Save every WordPress email delivery failure in DZ LMS → Settings → Error
 * Log. WordPress fires wp_mail_failed for PHPMailer, SMTP, connection,
 * authentication, recipient, and attachment failures. Message bodies,
 * passwords, and SMTP usernames are deliberately never stored.
 *
 * @param WP_Error $error Mail failure raised by wp_mail().
 */
function ggm_log_wp_mail_failure( $error ) {
	if ( ! is_wp_error( $error ) || ! class_exists( 'GGM_Meta_Boxes' ) ) {
		return;
	}

	$data       = $error->get_error_data();
	$data       = is_array( $data ) ? $data : array();
	$recipients = $data['to'] ?? array();
	$recipients = is_array( $recipients ) ? $recipients : array( $recipients );
	$recipients = array_values( array_filter( array_map( 'sanitize_email', $recipients ) ) );
	$recipients = array_map( 'ggm_mask_email_address', $recipients );
	$subject    = sanitize_text_field( (string) ( $data['subject'] ?? '' ) );
	$settings   = get_option( 'ggm_settings', array() );
	$source     = '';

	// Record the first plugin call site so admins can distinguish OTP,
	// receipts, welcome messages, health-intake rules, and SMTP tests.
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$file = (string) ( $frame['file'] ?? '' );
		if ( $file && 0 === strpos( $file, GGM_PLUGIN_DIR ) && basename( $file ) !== basename( __FILE__ ) ) {
			$source = str_replace( GGM_PLUGIN_DIR, '', $file ) . ':' . absint( $frame['line'] ?? 0 );
			break;
		}
	}

	$attachments = array_map( 'basename', (array) ( $data['attachments'] ?? array() ) );
	$context = array(
		'category'       => 'email',
		'error_code'     => sanitize_key( (string) $error->get_error_code() ),
		'recipient'      => implode( ', ', $recipients ),
		'subject'        => $subject,
		'source'         => $source ?: 'WordPress wp_mail',
		'smtp_enabled'   => ! empty( $settings['ggm_smtp_enabled'] ) ? 'yes' : 'no',
		'smtp_host'      => ! empty( $settings['ggm_smtp_enabled'] ) ? sanitize_text_field( $settings['ggm_smtp_host'] ?? '' ) : '',
		'smtp_port'      => ! empty( $settings['ggm_smtp_enabled'] ) ? absint( $settings['ggm_smtp_port'] ?? 0 ) : '',
		'smtp_encryption'=> ! empty( $settings['ggm_smtp_enabled'] ) ? sanitize_key( $settings['ggm_smtp_encryption'] ?? '' ) : '',
		'attachments'    => $attachments ? implode( ', ', $attachments ) : '',
	);
	$context = array_filter( $context, static function ( $value ) { return '' !== $value && array() !== $value; } );

	GGM_Meta_Boxes::log_error( 'Email delivery failed: ' . $error->get_error_message(), $context );
}
add_action( 'wp_mail_failed', 'ggm_log_wp_mail_failure' );

/**
 * Send an OTP to an email address through Custom SMTP only.
 *
 * @param string $email Recipient email.
 * @param string $otp   Six-digit code.
 * @return bool
 */
function ggm_send_email_otp( $email, $otp ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) || ! preg_match( '/^[0-9]{6}$/', (string) $otp ) ) {
		ggm_log_otp_delivery_error( 'OTP email blocked: invalid recipient or code format', array(
			'stage'  => 'validation',
			'reason' => 'Recipient must be a valid email and OTP must contain six digits',
		) );
		return false;
	}

	$smtp = ggm_get_custom_smtp_settings();
	if ( ! ggm_custom_smtp_enabled( $smtp ) ) {
		ggm_log_otp_delivery_error( 'OTP email blocked: Custom SMTP not configured', array(
			'stage'             => 'provider_dispatch',
			'masked_identifier' => ggm_mask_email_address( $email ),
			'reason'            => 'Custom SMTP is disabled or host is missing',
		) );
		return false;
	}

	$settings = get_option( 'ggm_settings', array() );
	$subject  = sanitize_text_field( $settings['ggm_otp_email_subject'] ?? '' );
	if ( '' === $subject ) {
		$subject = __( 'Your OTP for GGM Login', 'ggm-member-dashboard' );
	}

	$raw_body = $settings['ggm_otp_email_body'] ?? '<p>Your One-Time Password (OTP) is:</p><h2 style="letter-spacing:4px;">{otp}</h2><p>This code is valid for <strong>10 minutes</strong>. Do not share it with anyone.</p>';
	if ( false === strpos( $raw_body, '{otp}' ) ) {
		ggm_log_otp_delivery_error( 'OTP email template repaired: missing {otp} placeholder', array(
			'level'             => 'warning',
			'stage'             => 'email_template',
			'masked_identifier' => ggm_mask_email_address( $email ),
			'reason'            => 'Configured body omitted the OTP placeholder; a secure code block was appended',
		) );
		$raw_body .= '<p>Your One-Time Password (OTP) is:</p><h2 style="letter-spacing:4px;">{otp}</h2>';
	}

	$body    = str_replace( '{otp}', $otp, $raw_body );
	$html    = ggm_wrap_email_html( get_bloginfo( 'name' ), wp_kses_post( $body ) );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$sent    = ggm_send_plugin_mail( $email, $subject, $html, $headers );

	if ( class_exists( 'GGM_Meta_Boxes' ) ) {
		global $phpmailer;
		$mailer_error = isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ? sanitize_text_field( $phpmailer->ErrorInfo ) : '';
		$mailer_type  = isset( $phpmailer ) && is_object( $phpmailer ) && isset( $phpmailer->Mailer ) ? $phpmailer->Mailer : 'unknown';
		$message_id   = '';
		if ( isset( $phpmailer ) && is_object( $phpmailer ) ) {
			if ( method_exists( $phpmailer, 'getLastMessageID' ) && ! empty( $phpmailer->getLastMessageID() ) ) {
				$message_id = $phpmailer->getLastMessageID();
			} elseif ( ! empty( $phpmailer->MessageID ) ) {
				$message_id = $phpmailer->MessageID;
			}
		}

		GGM_Meta_Boxes::log_error(
			$sent ? 'OTP email: SMTP transport accepted message (wp_mail=true)' : 'OTP email: wp_mail returned false — delivery failed',
			array(
				'level'              => $sent ? 'success' : 'error',
				'intended_recipient' => ggm_mask_email_address( $email ),
				'message_id'         => $message_id,
				'smtp_host'          => $smtp['host'],
				'smtp_port'          => $smtp['port'],
				'smtp_encryption'    => $smtp['encryption'],
				'mailer_type'        => $mailer_type,
				'mailer_error'       => $mailer_error,
				'wp_mail_result'     => $sent ? 'true' : 'false',
			)
		);
	}

	return $sent;
}

/**
 * Save an OTP delivery diagnostic without exposing full phone/email details.
 *
 * @param string $message Log message.
 * @param array  $context Extra context.
 * @return void
 */
function ggm_log_otp_delivery_error( $message, $context = array() ) {
	if ( ! class_exists( 'GGM_Meta_Boxes' ) ) {
		return;
	}

	$stage = $context['stage'] ?? '';
	if ( ! isset( $context['level'] ) ) {
		if ( 'wp_mail_success' === $stage || false !== stripos( $message, 'accepted' ) || false !== stripos( $message, 'success' ) ) {
			$context['level'] = 'success';
		} elseif ( in_array( $stage, array( 'workshop_otp_started', 'user_resolved', 'registered_email_resolved', 'smtp_configuration', 'smtp_dispatch_start' ), true ) ) {
			$context['level'] = 'info';
		} else {
			$context['level'] = 'error';
		}
	}

	$context = array_filter( array_merge( array( 'category' => 'otp_delivery' ), (array) $context ), static function ( $value ) {
		return '' !== $value && null !== $value && array() !== $value;
	} );

	GGM_Meta_Boxes::log_error( $message, $context );
}

/**
 * Wrap a block of admin-authored email body HTML (from Settings → Email
 * Templates) in the plugin's shared branded shell — light page background,
 * white card, site name heading, and a support/contact footer — so every
 * email the plugin sends looks consistent, not just the payment/registration
 * ones that already had their own fixed HTML template.
 *
 * @param string $site_name
 * @param string $body_html Already-sanitized HTML (e.g. via wp_kses_post()).
 * @return string
 */
function ggm_wrap_email_html( $site_name, $body_html ) {
	$page_bg     = '#eef4f8';
	$accent_text = '#0f8fa0';
	$body_text   = '#334155';
	$muted_text  = '#64748b';

	ob_start();
	?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $page_bg ); ?>;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 16px;background:<?php echo esc_attr( $page_bg ); ?>;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;max-width:600px;width:100%;box-shadow:0 4px 20px rgba(15,23,42,0.06);">
	<tr>
		<td style="padding:40px 40px 8px;text-align:center;">
			<h1 style="color:<?php echo esc_attr( $accent_text ); ?>;margin:0 0 24px;font-size:20px;font-weight:800;"><?php echo esc_html( $site_name ); ?></h1>
		</td>
	</tr>
	<tr>
		<td style="padding:0 40px 32px;color:<?php echo esc_attr( $body_text ); ?>;font-size:15px;line-height:1.6;text-align:center;">
			<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput -- caller runs this through wp_kses_post() first. ?>
		</td>
	</tr>
</table>
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;margin-top:24px;">
	<tr>
		<td style="text-align:center;padding:20px 20px 0;border-top:1px solid #e2e8f0;">
			<p style="color:<?php echo esc_attr( $muted_text ); ?>;font-size:13px;margin:16px 0;">
				<?php esc_html_e( 'If you have any questions or need help, please reach out to our support team.', 'ggm-member-dashboard' ); ?>
			</p>
			<p style="color:<?php echo esc_attr( $accent_text ); ?>;font-size:14px;font-weight:700;margin:0 0 4px;"><?php echo esc_html( $site_name ); ?></p>
			<p style="color:<?php echo esc_attr( $muted_text ); ?>;font-size:12px;margin:0 0 4px;"><?php echo esc_html( get_option( 'admin_email' ) ); ?></p>
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


/**
 * Mask a phone number for logs.
 *
 * @param string $phone Phone number.
 * @return string
 */
function ggm_mask_phone_number( $phone ) {
	$digits = preg_replace( '/\D/', '', (string) $phone );
	if ( strlen( $digits ) <= 4 ) {
		return '****';
	}
	return str_repeat( '*', max( 0, strlen( $digits ) - 4 ) ) . substr( $digits, -4 );
}

/**
 * Normalize a member phone number while keeping the country dial code separate.
 *
 * Indian OTP accounts use a canonical 10-digit national number. Other countries
 * accept a national number only when the complete E.164 value remains between
 * 7 and 15 digits. A pasted international prefix is removed only when retaining
 * it would make the combined value invalid, avoiding guesses for local numbers
 * that happen to begin with the same digits as their dial code.
 *
 * @param mixed  $phone        Raw phone value.
 * @param string $country_code Selected country dial code.
 * @return string Canonical national number, or an empty string when invalid.
 */
function ggm_normalize_member_phone( $phone, $country_code = '+91' ) {
	$raw_phone   = trim( (string) $phone );
	$digits      = preg_replace( '/\D+/', '', $raw_phone );
	$dial_digits = preg_replace( '/\D+/', '', (string) $country_code );
	$dial_digits = $dial_digits ?: '91';

	if ( '91' === $dial_digits ) {
		if ( 11 === strlen( $digits ) && '0' === substr( $digits, 0, 1 ) ) {
			$digits = substr( $digits, 1 );
		} elseif ( 12 === strlen( $digits ) && '91' === substr( $digits, 0, 2 ) ) {
			$digits = substr( $digits, 2 );
		}

		return preg_match( '/^[6-9][0-9]{9}$/', $digits ) ? $digits : '';
	}

	$max_national_length = max( 4, 15 - strlen( $dial_digits ) );
	$pasted_international = 0 === strpos( $raw_phone, '+' );
	if ( ( $pasted_international || strlen( $digits ) > $max_national_length ) && 0 === strpos( $digits, $dial_digits ) ) {
		$digits = substr( $digits, strlen( $dial_digits ) );
	}

	$complete_length = strlen( $dial_digits . $digits );
	return preg_match( '/^[0-9]{4,14}$/', $digits ) && $complete_length >= 7 && $complete_length <= 15 ? $digits : '';
}

/**
 * Return one canonical member phone using the same precedence everywhere.
 *
 * @param int $user_id WordPress user ID.
 * @return string
 */
function ggm_get_member_phone( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return '';
	}

	$country_code = get_user_meta( $user_id, 'ggm_whatsapp_country_code', true ) ?: '+91';
	foreach ( array( 'ggm_phone', 'billing_phone' ) as $meta_key ) {
		$phone = ggm_normalize_member_phone( get_user_meta( $user_id, $meta_key, true ), $country_code );
		if ( '' !== $phone ) {
			return $phone;
		}
	}

	return '';
}

/**
 * Repair deterministic legacy phone corruption once, retaining original data.
 *
 * @return array Repair summary.
 */
function ggm_repair_legacy_member_phones() {
	$complete = get_option( 'ggm_phone_integrity_repair_v1' );
	if ( is_array( $complete ) && ! empty( $complete['completed_at'] ) ) {
		return $complete;
	}

	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('ggm_phone','billing_phone','ggm_whatsapp_country_code') AND meta_value <> '' ORDER BY user_id ASC"
	); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$grouped = array();
	foreach ( $rows as $row ) {
		$grouped[ (int) $row->user_id ][ $row->meta_key ] = (string) $row->meta_value;
	}

	$summary = array( 'repaired' => 0, 'canonicalized' => 0, 'ambiguous' => 0, 'completed_at' => current_time( 'mysql' ) );
	foreach ( $grouped as $user_id => $values ) {
		$country_code = $values['ggm_whatsapp_country_code'] ?? '+91';
		$primary_raw  = $values['ggm_phone'] ?? '';
		$billing_raw  = $values['billing_phone'] ?? '';
		$primary      = ggm_normalize_member_phone( $primary_raw, $country_code );
		$billing      = ggm_normalize_member_phone( $billing_raw, $country_code );
		$canonical    = $primary ?: $billing;
		$repaired     = false;

		if ( $primary && $billing && $primary !== $billing ) {
			$summary['ambiguous']++;
			continue;
		}

		if ( ! $canonical && '91' === preg_replace( '/\D+/', '', (string) $country_code ) ) {
			$source_digits = preg_replace( '/\D+/', '', $primary_raw ?: $billing_raw );
			$candidate     = substr( $source_digits, 0, 10 );
			if ( strlen( $source_digits ) > 12 && preg_match( '/^[6-9][0-9]{9}$/', $candidate ) ) {
				$canonical = $candidate;
				$repaired  = true;
			}
		}

		if ( ! $canonical ) {
			if ( '' !== $primary_raw || '' !== $billing_raw ) {
				$summary['ambiguous']++;
			}
			continue;
		}

		if ( $primary_raw === $canonical && $billing_raw === $canonical ) {
			continue;
		}

		add_user_meta( $user_id, 'ggm_phone_integrity_backup_v1', array(
			'ggm_phone'     => $primary_raw,
			'billing_phone' => $billing_raw,
			'country_code'  => $country_code,
			'backed_up_at'  => current_time( 'mysql' ),
		), false );
		update_user_meta( $user_id, 'ggm_phone', $canonical );
		update_user_meta( $user_id, 'billing_phone', $canonical );
		$summary[ $repaired ? 'repaired' : 'canonicalized' ]++;
	}

	update_option( 'ggm_phone_integrity_repair_v1', $summary, false );
	return $summary;
}

/** Return the selected mentor names used as a course's instructors. */
function ggm_get_course_mentor_names( $course_id ) {
	$mentor_ids = get_post_meta( $course_id, 'ggm_mentor_ids', true );
	$mentor_ids = is_array( $mentor_ids ) ? array_values( array_filter( array_map( 'absint', $mentor_ids ) ) ) : array();
	if ( empty( $mentor_ids ) ) {
		$legacy_id = absint( get_post_meta( $course_id, 'ggm_mentor_id', true ) );
		if ( $legacy_id ) { $mentor_ids[] = $legacy_id; }
	}
	$names = array();
	foreach ( $mentor_ids as $mentor_id ) {
		if ( 'ggm_mentor' === get_post_type( $mentor_id ) && 'publish' === get_post_status( $mentor_id ) ) { $names[] = get_the_title( $mentor_id ); }
	}
	if ( empty( $names ) ) {
		$legacy_name = trim( (string) get_post_meta( $course_id, 'course_instructor', true ) );
		if ( $legacy_name ) { $names[] = $legacy_name; }
	}
	return array_values( array_unique( array_filter( $names ) ) );
}

/**
 * Sanitize and normalize a YouTube video URL.
 *
 * Accepts watch, share, embed, Shorts, mobile, and privacy-enhanced URLs, but
 * rejects channel/home/playlist-only URLs that do not identify one video.
 */
function ggm_sanitize_youtube_url( $url ) {
	$url = esc_url_raw( trim( (string) $url ), array( 'https' ) );
	if ( ! $url ) {
		return '';
	}

	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$host = preg_replace( '/^www\./', '', $host );
	if ( ! in_array( $host, array( 'youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com' ), true ) ) {
		return '';
	}

	$path     = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	$video_id = '';

	if ( 'youtu.be' === $host ) {
		$video_id = explode( '/', $path )[0] ?? '';
	} elseif ( preg_match( '#^(?:embed|shorts)/([^/]+)#', $path, $matches ) ) {
		$video_id = $matches[1];
	} elseif ( in_array( $path, array( 'watch', '' ), true ) ) {
		$query = array();
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$video_id = isset( $query['v'] ) && is_string( $query['v'] ) ? $query['v'] : '';
	}

	if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $video_id ) ) {
		return '';
	}

	return 'https://www.youtube.com/watch?v=' . rawurlencode( $video_id );
}
