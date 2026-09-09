<?php
/**
 * OTP Manager.
 *
 * Handles OTP generation, transient storage, verification,
 * verification attempts limits, and requests rate limiting.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_OTP
 */
class GGM_OTP {

	/**
	 * Generate a 6-digit OTP.
	 *
	 * @return string
	 */
	public static function generate() {
		return str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Return the per-browser OTP session id, creating its HttpOnly cookie on
	 * the first request. The opaque request id is not enough on its own: this
	 * binding prevents a code/request pair captured in one browser session
	 * from being verified in another.
	 *
	 * @return string
	 */
	private static function session_id() {
		$cookie_name = 'ggm_otp_session';
		$session_id  = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ?? '' ) );

		if ( ! preg_match( '/^[a-f0-9]{64}$/', $session_id ) ) {
			$session_id = bin2hex( random_bytes( 32 ) );
			$path       = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
			$domain     = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
			setcookie( $cookie_name, $session_id, array(
				'expires'  => time() + DAY_IN_SECONDS,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			) );
			$_COOKIE[ $cookie_name ] = $session_id;
		}

		return $session_id;
	}

	/**
	 * Normalize an email/phone identifier identically for send and verify.
	 *
	 * @param string $identifier Raw identifier.
	 * @return array|false { clean_id: string, is_phone: bool } or false.
	 */
	public static function normalize_identifier( $identifier ) {
		$identifier = sanitize_text_field( $identifier );
		$email      = strtolower( sanitize_email( $identifier ) );

		// Email must be identified before inspecting digits. Otherwise an email
		// address containing ten digits can be treated as a phone number.
		if ( is_email( $email ) ) {
			return array( 'clean_id' => $email, 'is_phone' => false );
		}

		// OTP phone authentication currently supports Indian mobile numbers.
		// Only remove a prefix when the complete digit count proves it is a
		// prefix, so local numbers beginning with 91 remain intact. This is
		// intentionally idempotent: a normalized 10-digit value stays unchanged.
		$digits = preg_replace( '/\D/', '', $identifier );
		if ( 10 === strlen( $digits ) ) {
			return array( 'clean_id' => $digits, 'is_phone' => true );
		}
		if ( 11 === strlen( $digits ) && '0' === substr( $digits, 0, 1 ) ) {
			return array( 'clean_id' => substr( $digits, 1 ), 'is_phone' => true );
		}
		if ( 12 === strlen( $digits ) && '91' === substr( $digits, 0, 2 ) ) {
			return array( 'clean_id' => substr( $digits, 2 ), 'is_phone' => true );
		}

		return false;
	}

	/**
	 * Is explicit local-development demo mode active?
	 *
	 * Production settings can no longer bypass email delivery with a fixed
	 * code. Developers must opt in through GGM_DEMO_MODE and a non-production
	 * WordPress environment.
	 *
	 * @return bool
	 */
	public static function is_demo_mode() {
		if ( ! defined( 'GGM_DEMO_MODE' ) || ! GGM_DEMO_MODE ) {
			return false;
		}

		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		return in_array( $environment, array( 'local', 'development' ), true );
	}

	/**
	 * Send OTP to an identifier.
	 *
	 * @param string $identifier Email or phone number.
	 * @return array { success: bool, message: string, type: string, masked: string }
	 */
	public static function send( $identifier ) {
		$normalized = self::normalize_identifier( $identifier );
		if ( ! $normalized ) {
			ggm_log_otp_delivery_error( 'OTP request rejected: invalid identifier format', array(
				'stage'            => 'validation',
				'identifier_type'  => 'unknown',
				'masked_identifier'=> '****',
				'reason'           => 'Identifier is not a valid email or 10-digit phone number',
			) );
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid email address or 10-digit mobile number.', 'ggm-member-dashboard' ),
			);
		}
		$clean_id = $normalized['clean_id'];
		$is_phone = $normalized['is_phone'];
		$identifier_type = $is_phone ? 'phone' : 'email';
		$masked_id = $is_phone ? ggm_mask_phone_number( $clean_id ) : self::mask_email_for_log_static( $clean_id );

		// 0. Coarse IP-based limiter — guards against one visitor probing many
		// identifiers just to see which ones report "account not found" below.
		$client_ip = ggm_get_client_ip();
		if ( $client_ip && ggm_rate_limit_hit( 'ggm_otp_ip_' . md5( $client_ip ), 20, 10 * MINUTE_IN_SECONDS ) ) {
			ggm_log_otp_delivery_error( 'OTP request blocked: IP rate limit exceeded', array(
				'stage'            => 'rate_limit',
				'identifier_type'  => $identifier_type,
				'masked_identifier'=> $masked_id,
				'reason'           => 'Too many requests from this IP in 10 minutes',
			) );
			return array(
				'success' => false,
				'message' => __( 'Too many requests. Please wait a few minutes and try again.', 'ggm-member-dashboard' ),
			);
		}

		// 0b. Resolve the account before generating a code. OTP login never
		// silently creates an account, and the user id is bound into the stored
		// request so later identifier changes cannot redirect authentication.
		$resolved_user = null;
		if ( class_exists( 'GGM_Auth' ) ) {
			$resolved      = GGM_Auth::resolve_user_from_identifier( $clean_id );
			$resolved_user = $resolved['user'] ?? null;
		}
		if ( ! $resolved_user ) {
			ggm_log_otp_delivery_error( 'OTP request rejected: account not found', array(
				'stage'            => 'account_lookup',
				'identifier_type'  => $identifier_type,
				'masked_identifier'=> $masked_id,
				'reason'           => 'No WordPress account exists for this identifier',
			) );
			return array(
				'success'           => false,
				'account_not_found' => true,
				'message'           => __( 'Account not found. Please create an account first.', 'ggm-member-dashboard' ),
			);
		}

		// Live OTP delivery is email-only and fails closed unless Custom SMTP
		// is active and the matched account has a registered email address.
		$smtp_email = '';
		if ( ! self::is_demo_mode() ) {
			$smtp = ggm_get_custom_smtp_settings();
			if ( ! ggm_custom_smtp_enabled( $smtp ) ) {
				ggm_log_otp_delivery_error( 'OTP request blocked: Custom SMTP not configured', array(
					'stage'             => 'provider_dispatch',
					'identifier_type'   => $identifier_type,
					'masked_identifier' => $masked_id,
					'reason'            => 'Custom SMTP is disabled or host is missing',
				) );
				return array(
					'success' => false,
					'message' => __( 'OTP email service is not configured. Please contact the administrator.', 'ggm-member-dashboard' ),
				);
			}

			$email_result = self::get_user_registered_email( $resolved_user, $is_phone ? '' : $clean_id );
			$smtp_email   = $email_result ? $email_result['email'] : '';
			if ( ! $smtp_email ) {
				ggm_log_otp_delivery_error( 'OTP request blocked: matched account has no valid email', array(
					'stage'             => 'account_lookup',
					'identifier_type'   => $identifier_type,
					'masked_identifier' => $masked_id,
					'reason'            => 'Matched account has no valid registered email address',
				) );
				return array(
					'success' => false,
					'message' => __( 'We couldn\'t find a valid email address for this account. Please contact support.', 'ggm-member-dashboard' ),
				);
			}
		}

		$id_hash = hash( 'sha256', $clean_id );

		// 1. Rate Limit check: max 3 requests per 10 minutes.
		$rate_limit_key = 'ggm_otp_rate_' . $id_hash;
		$rate_count     = (int) get_transient( $rate_limit_key );
		$max_limit      = min( 20, max( 1, (int) ggm_get_setting( 'ggm_otp_rate_limit', 3 ) ) );
		if ( $rate_count >= $max_limit ) {
			ggm_log_otp_delivery_error( 'OTP request blocked: per-identifier rate limit exceeded', array(
				'stage'            => 'rate_limit',
				'identifier_type'  => $identifier_type,
				'masked_identifier'=> $masked_id,
				'reason'           => 'Maximum OTP requests per 10-minute window exceeded',
				'rate_limit_count' => $rate_count,
				'rate_limit_max'   => $max_limit,
			) );
			return array(
				'success' => false,
				'message' => __( 'Too many OTP requests. Please wait 10 minutes.', 'ggm-member-dashboard' ),
			);
		}

		// 2. Generate and store (hashed — never the raw code at rest).
		$otp          = self::is_demo_mode() ? '123456' : self::generate();
		$expiry       = min( 120, max( 1, (int) ggm_get_setting( 'ggm_otp_expiry', 10 ) ) );
		$session_hash = hash( 'sha256', self::session_id() );
		$request_id   = bin2hex( random_bytes( 32 ) );
		$request_hash = hash( 'sha256', $request_id );
		$current_key  = 'ggm_otp_current_' . hash( 'sha256', $clean_id . '|' . $session_hash );
		$previous     = get_transient( $current_key );
		$stored = set_transient( 'ggm_otp_request_' . $request_hash, array(
			'identifier_hash' => hash( 'sha256', $clean_id ),
			'session_hash'    => $session_hash,
			'user_id'         => (int) $resolved_user->ID,
			'otp_hash'        => self::hash_otp( $otp ),
			'expires_at'      => time() + ( $expiry * MINUTE_IN_SECONDS ),
			'attempts'        => 0,
		), $expiry * MINUTE_IN_SECONDS );
		if ( ! $stored ) {
			ggm_log_otp_delivery_error( 'OTP request failed: could not create secure transient', array(
				'stage'            => 'otp_generation',
				'identifier_type'  => $identifier_type,
				'masked_identifier'=> $masked_id,
				'reason'           => 'Failed to store OTP request in transient cache',
			) );
			return array(
				'success' => false,
				'message' => __( 'Unable to create a secure OTP request. Please try again.', 'ggm-member-dashboard' ),
			);
		}
		// 3. Dispatch only to the matched account's registered email through
		// Custom SMTP. There is deliberately no SMS or WhatsApp fallback.
		$sent              = self::is_demo_mode();
		$delivery_id       = $sent ? $clean_id : $smtp_email;
		$delivery_is_phone = $sent ? $is_phone : false;
		$provider_used     = $sent ? 'demo' : 'smtp';
		if ( ! $sent ) {
			$sent          = ggm_send_email_otp( $smtp_email, $otp );
		}

		if ( ! $sent ) {
			delete_transient( 'ggm_otp_request_' . $request_hash );
			ggm_log_otp_delivery_error( 'OTP delivery failed: Custom SMTP did not accept the email', array(
				'stage'             => 'provider_dispatch',
				'identifier_type'   => $identifier_type,
				'masked_identifier' => $masked_id,
				'provider_attempted'=> $provider_used,
				'reason'            => 'Custom SMTP failed to deliver the OTP email',
			) );
			return array(
				'success' => false,
				'message' => __( 'Failed to deliver OTP code. Please check details or try again later.', 'ggm-member-dashboard' ),
			);
		}

		// Keep a previously delivered request valid until this replacement has
		// been accepted by the transport. If another request completed while
		// this one was sending, do not delete that newer request.
		$current_before_replace = get_transient( $current_key );
		set_transient( $current_key, $request_hash, $expiry * MINUTE_IN_SECONDS );
		if ( is_string( $previous ) && preg_match( '/^[a-f0-9]{64}$/', $previous ) && $current_before_replace === $previous ) {
			delete_transient( 'ggm_otp_request_' . $previous );
		}

		// Count only successfully dispatched OTPs. Failed SMTP attempts have no
		// usable request_id and must not exhaust a legitimate user's quota.
		set_transient( $rate_limit_key, $rate_count + 1, 10 * MINUTE_IN_SECONDS );

		// Masked display.
		$masked = self::mask_identifier( $delivery_id, $delivery_is_phone );

		return array(
			'success'         => true,
			'type'            => $delivery_is_phone ? 'phone' : 'email',
			'identifier_type' => $is_phone ? 'phone' : 'email',
			'masked'          => $masked,
			'message'         => __( 'OTP sent successfully.', 'ggm-member-dashboard' ),
			'request_id'      => $request_id,
			'demo'            => self::is_demo_mode(),
		);
	}

	/**
	 * Resolve a registered account email, preferring the email identifier the
	 * member actually entered when that address already belongs to the account.
	 *
	 * @param WP_User $user            Account being authenticated.
	 * @param string  $preferred_email Optional email identifier.
	 * @return array|false Email data or false.
	 */
	private static function get_user_registered_email( $user, $preferred_email = '' ) {
		$candidates = array(
			'user_email'        => sanitize_email( $user->user_email ),
			'billing_email'     => sanitize_email( get_user_meta( $user->ID, 'billing_email', true ) ),
			'ggm_checkout_email'=> sanitize_email( get_user_meta( $user->ID, 'ggm_checkout_email', true ) ),
		);
		$preferred_email = strtolower( sanitize_email( $preferred_email ) );

		if ( is_email( $preferred_email ) ) {
			foreach ( $candidates as $source => $candidate ) {
				if ( self::is_eligible_otp_email( $candidate ) && strtolower( $candidate ) === $preferred_email ) {
					return array(
						'email'  => $candidate,
						'source' => $source,
					);
				}
			}
		}

		foreach ( $candidates as $source => $candidate ) {
			if ( self::is_eligible_otp_email( $candidate ) ) {
				return array(
					'email'  => $candidate,
					'source' => $source,
				);
			}
		}

		return false;
	}

	/**
	 * Synthetic phone-signup addresses are account placeholders, never OTP
	 * destinations. A real registered email is required for email delivery.
	 *
	 * @param string $email Candidate email address.
	 * @return bool
	 */
	private static function is_eligible_otp_email( $email ) {
		$email = strtolower( sanitize_email( $email ) );
		return is_email( $email ) && ! preg_match( '/@ggm-temp\.com$/', $email );
	}

	/**
	 * Send a workshop OTP to the resolved account's registered email.
	 *
	 * The identifier used for verification must resolve to the same account
	 * selected by the workshop validation rule. This prevents an email match
	 * from authorizing an unrelated phone account.
	 *
	 * @param WP_User $user       Resolved WordPress user.
	 * @param string  $identifier Email or normalized phone used to verify.
	 * @return array OTP send result.
	 */
	public static function send_workshop_email_otp( $user, $identifier ) {
		$normalized = self::normalize_identifier( $identifier );
		if ( ! $user || ! $normalized || ! class_exists( 'GGM_Auth' ) ) {
			ggm_log_otp_delivery_error( 'Workshop OTP blocked: invalid account binding', array(
				'stage'  => 'account_lookup',
				'reason' => 'Resolved user or OTP identifier is invalid',
			) );
			return array(
				'success' => false,
				'message' => __( 'Account details do not match. Please check them and try again.', 'ggm-member-dashboard' ),
			);
		}

		$clean_id = $normalized['clean_id'];
		$resolved = GGM_Auth::resolve_user_from_identifier( $clean_id );
		if ( empty( $resolved['user'] ) || (int) $resolved['user']->ID !== (int) $user->ID ) {
			ggm_log_otp_delivery_error( 'Workshop OTP blocked: identifier resolved to another account', array(
				'stage'             => 'account_lookup',
				'identifier_type'   => $normalized['is_phone'] ? 'phone' : 'email',
				'masked_identifier' => $normalized['is_phone'] ? ggm_mask_phone_number( $clean_id ) : self::mask_email_for_log_static( $clean_id ),
				'user_id'           => (int) $user->ID,
				'reason'            => 'OTP identifier does not resolve to the selected account',
			) );
			return array(
				'success' => false,
				'message' => __( 'Account details do not match. Please check them and try again.', 'ggm-member-dashboard' ),
			);
		}

		return self::send( $clean_id );
	}

	/**
	 * Mask email for safe logging (static version for use in static methods).
	 *
	 * @param string $email
	 * @return string
	 */
	private static function mask_email_for_log_static( $email ) {
		$at_pos = strpos( $email, '@' );
		if ( false !== $at_pos && $at_pos > 0 ) {
			return substr( $email, 0, 1 ) . str_repeat( '*', $at_pos - 1 ) . substr( $email, $at_pos );
		}
		return '****';
	}

	/**
	 * Verify an OTP code.
	 *
	 * @param string $identifier
	 * @param string $otp
	 * @param string $request_id Server-issued request id returned by send().
	 * @return array { success: bool, message: string }
	 */
	public static function verify( $identifier, $otp, $request_id = '' ) {
		$invalid = array(
			'success' => false,
			'message' => __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ),
		);
		$normalized = self::normalize_identifier( $identifier );
		if ( ! $normalized || ! preg_match( '/^[0-9]{6}$/', (string) $otp ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) $request_id ) ) {
			return $invalid;
		}

		$clean_id     = $normalized['clean_id'];
		$request_hash = hash( 'sha256', $request_id );
		$request_key  = 'ggm_otp_request_' . $request_hash;
		$record       = get_transient( $request_key );
		if ( ! is_array( $record )
			|| empty( $record['expires_at'] )
			|| time() > (int) $record['expires_at']
			|| empty( $record['identifier_hash'] )
			|| ! hash_equals( (string) $record['identifier_hash'], hash( 'sha256', $clean_id ) )
			|| empty( $record['session_hash'] )
			|| ! hash_equals( (string) $record['session_hash'], hash( 'sha256', self::session_id() ) )
			|| empty( $record['user_id'] )
		) {
			return $invalid;
		}

		$resolved = class_exists( 'GGM_Auth' ) ? GGM_Auth::resolve_user_from_identifier( $clean_id ) : array();
		if ( empty( $resolved['user'] ) || (int) $resolved['user']->ID !== (int) $record['user_id'] ) {
			return $invalid;
		}

		$attempts = (int) ( $record['attempts'] ?? 0 );
		if ( $attempts >= 3 ) {
			delete_transient( $request_key );
			return $invalid;
		}

		if ( empty( $record['otp_hash'] ) || ! hash_equals( (string) $record['otp_hash'], self::hash_otp( (string) $otp ) ) ) {
			$record['attempts'] = $attempts + 1;
			if ( $record['attempts'] >= 3 ) {
				delete_transient( $request_key );
			} else {
				$ttl = max( 1, (int) $record['expires_at'] - time() );
				set_transient( $request_key, $record, $ttl );
			}
			return $invalid;
		}

		// add_option() is an atomic unique insert at the database layer. It makes
		// simultaneous duplicate verification requests fail closed before either
		// can create a second login response.
		$claim_key = 'ggm_otp_claim_' . $request_hash;
		if ( ! add_option( $claim_key, time(), '', false ) ) {
			return $invalid;
		}
		wp_schedule_single_event( time() + DAY_IN_SECONDS, 'ggm_cleanup_otp_claim', array( $claim_key ) );

		delete_transient( $request_key );
		delete_transient( 'ggm_otp_current_' . hash( 'sha256', $clean_id . '|' . $record['session_hash'] ) );
		// Deliberately retain the send-rate counter for its full window. Clearing
		// it here would let repeated successful logins bypass request throttling.

		return array(
			'success'  => true,
			'message'  => __( 'OTP verified successfully.', 'ggm-member-dashboard' ),
			'user_id'  => (int) $record['user_id'],
			'is_phone' => (bool) $normalized['is_phone'],
		);
	}

	/** Delete a short-lived atomic verification claim after its race window. */
	public static function cleanup_claim( $claim_key ) {
		if ( is_string( $claim_key ) && 0 === strpos( $claim_key, 'ggm_otp_claim_' ) ) {
			delete_option( $claim_key );
		}
	}

	/**
	 * Hash an OTP code for at-rest storage in a transient.
	 * HMAC keyed on the site's own auth salt — never the raw code at rest.
	 *
	 * @param string $otp
	 * @return string
	 */
	private static function hash_otp( $otp ) {
		return hash_hmac( 'sha256', (string) $otp, wp_salt( 'auth' ) );
	}


	/**
	 * Helper to mask identifier values.
	 */
	private static function mask_identifier( $identifier, $is_phone ) {
		if ( $is_phone ) {
			return '+91 ' . substr( $identifier, 0, 5 ) . ' **' . substr( $identifier, 7 );
		}

		$parts = explode( '@', $identifier );
		$name  = $parts[0];
		$domain = $parts[1] ?? '';
		$len   = strlen( $name );

		if ( $len <= 2 ) {
			$masked_name = str_repeat( '*', $len );
		} else {
			$masked_name = substr( $name, 0, 1 ) . str_repeat( '*', $len - 2 ) . substr( $name, -1 );
		}

		return $masked_name . '@' . $domain;
	}
}
