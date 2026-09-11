<?php
/**
 * Authentication AJAX Endpoints.
 *
 * Implements AJAX actions for OTP delivery, validation,
 * password credential lookups, and profile creation.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Auth
 */
class GGM_Auth {

	/**
	 * Register AJAX hooks via loader.
	 *
	 * @param GGM_Loader $loader
	 */
	public function init( GGM_Loader $loader ) {
		// OTP actions
		$loader->add_action( 'wp_ajax_ggm_send_otp',        $this, 'ajax_send_otp' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_send_otp', $this, 'ajax_send_otp' );

		$loader->add_action( 'wp_ajax_ggm_verify_otp',        $this, 'ajax_verify_otp' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_verify_otp', $this, 'ajax_verify_otp' );

		$loader->add_action( 'wp_ajax_ggm_resend_otp',        $this, 'ajax_resend_otp' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_resend_otp', $this, 'ajax_resend_otp' );

		// Password login
		$loader->add_action( 'wp_ajax_ggm_password_login',        $this, 'ajax_password_login' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_password_login', $this, 'ajax_password_login' );

		// Save profile
		$loader->add_action( 'wp_ajax_ggm_complete_profile',        $this, 'ajax_complete_profile' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_complete_profile', $this, 'ajax_complete_profile' );

		// Signup
		$loader->add_action( 'wp_ajax_ggm_signup',        $this, 'ajax_signup' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_signup', $this, 'ajax_signup' );

		// Forgot password (reset via OTP)
		$loader->add_action( 'wp_ajax_ggm_reset_password',        $this, 'ajax_reset_password' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_reset_password', $this, 'ajax_reset_password' );
	}

	/**
	 * Resolve a WP_User from an OTP identifier (email or 10-digit phone).
	 * This is the shared account resolver used by send, verify, login, and
	 * password reset so every stage binds the same identifier to the same user.
	 *
	 * @param string $identifier
	 * @return array { user: WP_User|null, is_phone: bool, clean_id: string }
	 */
	public static function resolve_user_from_identifier( $identifier ) {
		$normalized = class_exists( 'GGM_OTP' ) ? GGM_OTP::normalize_identifier( $identifier ) : false;
		if ( ! $normalized ) {
			return array( 'user' => null, 'is_phone' => false, 'clean_id' => '' );
		}
		$is_phone = $normalized['is_phone'];
		$clean_id = $normalized['clean_id'];

		$user = null;
		if ( $is_phone ) {
			$user = self::find_user_by_phone( $clean_id );
		} else {
			$user = self::find_user_by_email( $clean_id );
		}

		return array( 'user' => $user, 'is_phone' => $is_phone, 'clean_id' => $clean_id );
	}

	/**
	 * Resolve a user by a normalized 10-digit phone number, tolerating older
	 * stored values that include country codes or formatting.
	 *
	 * @param string $clean_phone 10-digit phone number.
	 * @return WP_User|null
	 */
	public static function find_user_by_phone( $clean_phone ) {
		$clean_phone = preg_replace( '/\D/', '', (string) $clean_phone );
		if ( strlen( $clean_phone ) !== 10 ) {
			return null;
		}

		$variants = array_unique( array(
			$clean_phone,
			'91' . $clean_phone,
			'+91' . $clean_phone,
			'0' . $clean_phone,
		) );

		$users = get_users( array(
			'number'     => 10,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => 'billing_phone',
					'value'   => $variants,
					'compare' => 'IN',
				),
				array(
					'key'     => 'ggm_phone',
					'value'   => $variants,
					'compare' => 'IN',
				),
				array(
					'key'     => 'billing_phone',
					'value'   => $clean_phone,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'ggm_phone',
					'value'   => $clean_phone,
					'compare' => 'LIKE',
				),
			),
		) );

		foreach ( $users as $user ) {
			$stored_values = array(
				get_user_meta( $user->ID, 'billing_phone', true ),
				get_user_meta( $user->ID, 'ggm_phone', true ),
			);
			foreach ( $stored_values as $stored ) {
				$digits = preg_replace( '/\D/', '', (string) $stored );
				if ( $digits === $clean_phone || substr( $digits, -10 ) === $clean_phone ) {
					return $user;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize a phone number to 10-digit canonical form.
	 *
	 * Strips all non-digits and returns the last 10 digits.
	 * Used to ensure consistent phone matching regardless of
	 * country code prefix (+91, 91, 0) or formatting.
	 *
	 * @param string $value Raw phone input.
	 * @return string 10-digit phone or empty string if invalid.
	 */
	public static function normalize_phone( $value ) {
		$digits = preg_replace( '/\D/', '', (string) $value );
		return strlen( $digits ) >= 10 ? substr( $digits, -10 ) : '';
	}

	/**
	 * Resolve a user by email address, checking all possible email storage locations.
	 *
	 * The plugin may store emails in:
	 * - wp_users.user_email (primary)
	 * - billing_email (user meta, used during checkout)
	 * - ggm_checkout_email (user meta, used during checkout)
	 *
	 * @param string $email Email address to look up.
	 * @return WP_User|null
	 */
	public static function find_user_by_email( $email ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return null;
		}

		// Normalize to lowercase for case-insensitive comparison
		$email = strtolower( $email );

		// 1. Check wp_users.user_email (primary)
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return $user;
		}

		// 2. Check billing_email and ggm_checkout_email user meta
		$users = get_users( array(
			'number'     => 10,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => 'billing_email',
					'value'   => $email,
					'compare' => '=',
				),
				array(
					'key'     => 'ggm_checkout_email',
					'value'   => $email,
					'compare' => '=',
				),
			),
		) );

		foreach ( $users as $user ) {
			$stored_emails = array(
				get_user_meta( $user->ID, 'billing_email', true ),
				get_user_meta( $user->ID, 'ggm_checkout_email', true ),
			);
			foreach ( $stored_emails as $stored_email ) {
				if ( strtolower( sanitize_email( $stored_email ) ) === $email ) {
					return $user;
				}
			}
		}

		return null;
	}

	/**
	 * AJAX: Send OTP.
	 *
	 * Workshop requests use the submitted email as the authoritative account
	 * identity and SMTP destination. The phone remains contact information and
	 * never selects which user is authenticated.
	 */
	public function ajax_send_otp() {
		ggm_verify_ajax_nonce();

		$identifier = sanitize_text_field( wp_unslash( $_POST['identifier'] ?? '' ) );
		$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$source     = sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) );
		$country_code = sanitize_text_field( wp_unslash( $_POST['country_code'] ?? '+91' ) );

		$is_workshop_join = ( 'workshop_join_form_full' === $source );
		$has_phone_and_email = ( '' !== $phone && '' !== $email );

		// Workshop join form flow: requires source parameter AND both phone and email
		if ( $is_workshop_join ) {
			// Validate phone
			$clean_phone = ggm_normalize_member_phone( $phone, $country_code );
			if ( '' === $clean_phone ) {
				ggm_log_otp_delivery_error( 'OTP request rejected: invalid phone number', array(
					'stage'            => 'validation',
					'identifier_type'  => 'phone',
					'masked_identifier'=> ggm_mask_phone_number( $phone ),
					'reason'           => 'Phone number is invalid for the selected country',
				) );
				wp_send_json_error( array(
					'message' => __( 'Please enter a valid phone number.', 'ggm-member-dashboard' ),
					'field'   => 'phone',
				) );
			}

			// Validate email
			if ( ! is_email( $email ) ) {
				ggm_log_otp_delivery_error( 'OTP request rejected: invalid email address', array(
					'stage'            => 'validation',
					'identifier_type'  => 'email',
					'masked_identifier'=> $this->mask_email_for_log( $email ),
					'reason'           => 'Email format is invalid',
				) );
				wp_send_json_error( array(
					'message' => __( 'Please enter a valid email address.', 'ggm-member-dashboard' ),
					'field'   => 'email',
				) );
			}

			// Email-only OTP must authenticate the account that owns the email.
			// Phone records may be duplicated or stale after imports, so using a
			// global phone lookup here can select a different WordPress user.
			$email_user = self::find_user_by_email( $email );
			if ( ! $email_user ) {
				ggm_log_otp_delivery_error( 'Workshop OTP request rejected: email account not found', array(
					'stage'        => 'account_lookup',
					'masked_phone' => ggm_mask_phone_number( $clean_phone ),
					'masked_email' => $this->mask_email_for_log( $email ),
				) );
				wp_send_json_error( array(
					'message'           => __( 'No account is registered with this email address. Please use your registered email or create an account first.', 'ggm-member-dashboard' ),
					'account_not_found' => true,
					'field'             => 'email',
				) );
			}

			$resolved_user       = $email_user;
			$resolved_identifier = strtolower( $email );

			// Bind the OTP to the identifier that selected this exact account.
			// Delivery always goes to the account's registered email via SMTP.
			$res = GGM_OTP::send_workshop_email_otp( $resolved_user, $resolved_identifier );

			if ( $res['success'] ) {
				$res['identifier'] = $resolved_identifier;
				wp_send_json_success( $res );
			} else {
				// GGM_OTP::send_workshop_email_otp() already logs the failure
				wp_send_json_error( $res );
			}
		} elseif ( $has_phone_and_email ) {
			// Phone and email provided but no workshop source - reject to prevent misuse
			ggm_log_otp_delivery_error( 'OTP request rejected: missing workshop source parameter', array(
				'stage'   => 'validation',
				'reason'  => 'Phone and email provided without workshop_join_form_full source',
			) );
			wp_send_json_error( array(
				'message' => __( 'Invalid request. Please refresh the page and try again.', 'ggm-member-dashboard' ),
			) );
		} else {
			// Backward compatible flow: identifier only (login page, forgot password, etc.)
			if ( empty( $identifier ) ) {
				ggm_log_otp_delivery_error( 'OTP request rejected: identifier required', array(
					'stage'   => 'validation',
					'reason'  => 'No identifier provided',
				) );
				wp_send_json_error( array( 'message' => __( 'Phone number or email is required.', 'ggm-member-dashboard' ) ) );
			}

			$res = GGM_OTP::send( $identifier );

			if ( $res['success'] ) {
				wp_send_json_success( $res );
			} else {
				// GGM_OTP::send() already logs the failure
				wp_send_json_error( $res );
			}
		}
	}

	/**
	 * Mask email for safe logging.
	 *
	 * @param string $email
	 * @return string
	 */
	private function mask_email_for_log( $email ) {
		$at_pos = strpos( $email, '@' );
		if ( false !== $at_pos && $at_pos > 0 ) {
			return substr( $email, 0, 1 ) . str_repeat( '*', $at_pos - 1 ) . substr( $email, $at_pos );
		}
		return '****';
	}

	/**
	 * AJAX: Resend OTP.
	 *
	 * Workshop resends retain the same email-authoritative account binding and
	 * SMTP destination as the initial request.
	 */
	public function ajax_resend_otp() {
		ggm_verify_ajax_nonce();

		$identifier = sanitize_text_field( wp_unslash( $_POST['identifier'] ?? '' ) );
		$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$source     = sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) );
		$country_code = sanitize_text_field( wp_unslash( $_POST['country_code'] ?? '+91' ) );

		$is_workshop_join = ( 'workshop_join_form_full' === $source );
		$has_phone_and_email = ( '' !== $phone && '' !== $email );

		// Workshop join form flow: requires source parameter AND both phone and email
		if ( $is_workshop_join ) {
			// Validate phone
			$clean_phone = ggm_normalize_member_phone( $phone, $country_code );
			if ( '' === $clean_phone ) {
				ggm_log_otp_delivery_error( 'OTP resend rejected: invalid phone number', array(
					'stage'            => 'validation',
					'identifier_type'  => 'phone',
					'masked_identifier'=> ggm_mask_phone_number( $phone ),
					'reason'           => 'Phone number is invalid for the selected country',
				) );
				wp_send_json_error( array(
					'message' => __( 'Please enter a valid phone number.', 'ggm-member-dashboard' ),
					'field'   => 'phone',
				) );
			}

			// Validate email
			if ( ! is_email( $email ) ) {
				ggm_log_otp_delivery_error( 'OTP resend rejected: invalid email address', array(
					'stage'            => 'validation',
					'identifier_type'  => 'email',
					'masked_identifier'=> $this->mask_email_for_log( $email ),
					'reason'           => 'Email format is invalid',
				) );
				wp_send_json_error( array(
					'message' => __( 'Please enter a valid email address.', 'ggm-member-dashboard' ),
					'field'   => 'email',
				) );
			}

			// Resend to the exact email account selected by the initial request.
			// The submitted phone never changes the authenticated user.
			$email_user = self::find_user_by_email( $email );
			if ( ! $email_user ) {
				ggm_log_otp_delivery_error( 'Workshop OTP resend rejected: email account not found', array(
					'stage'        => 'account_lookup',
					'masked_phone' => ggm_mask_phone_number( $clean_phone ),
					'masked_email' => $this->mask_email_for_log( $email ),
				) );
				wp_send_json_error( array(
					'message'           => __( 'No account is registered with this email address. Please use your registered email or create an account first.', 'ggm-member-dashboard' ),
					'account_not_found' => true,
					'field'             => 'email',
				) );
			}

			$resolved_user       = $email_user;
			$resolved_identifier = strtolower( $email );

			// Bind the replacement OTP to the identifier that selected this
			// exact account; delivery remains email-only through Custom SMTP.
			$res = GGM_OTP::send_workshop_email_otp( $resolved_user, $resolved_identifier );

			if ( $res['success'] ) {
				wp_send_json_success( array(
					'countdown'  => 30,
					'message'    => __( 'OTP resent successfully.', 'ggm-member-dashboard' ),
					'request_id' => $res['request_id'],
					'identifier' => $resolved_identifier,
				) );
			} else {
				wp_send_json_error( $res );
			}
		} elseif ( $has_phone_and_email ) {
			// Phone and email provided but no workshop source - reject
			ggm_log_otp_delivery_error( 'OTP resend rejected: missing workshop source parameter', array(
				'stage'   => 'validation',
				'reason'  => 'Phone and email provided without workshop_join_form_full source',
			) );
			wp_send_json_error( array(
				'message' => __( 'Invalid request. Please refresh the page and try again.', 'ggm-member-dashboard' ),
			) );
		} else {
			// Backward compatible flow: identifier only
			if ( empty( $identifier ) ) {
				ggm_log_otp_delivery_error( 'OTP resend rejected: identifier required', array(
					'stage'   => 'validation',
					'reason'  => 'No identifier provided',
				) );
				wp_send_json_error( array( 'message' => __( 'Phone number or email is required.', 'ggm-member-dashboard' ) ) );
			}

			$res = GGM_OTP::send( $identifier );

			if ( $res['success'] ) {
				wp_send_json_success( array(
					'countdown'  => 30,
					'message'    => __( 'OTP resent successfully.', 'ggm-member-dashboard' ),
					'request_id' => $res['request_id'],
				) );
			} else {
				wp_send_json_error( $res );
			}
		}
	}

	/**
	 * AJAX: Verify OTP.
	 */
	public function ajax_verify_otp() {
		ggm_verify_ajax_nonce();

		$identifier = sanitize_text_field( wp_unslash( $_POST['identifier'] ?? '' ) );
		$otp        = sanitize_text_field( wp_unslash( $_POST['otp'] ?? '' ) );
		$request_id = sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );

		if ( empty( $identifier ) || empty( $otp ) || empty( $request_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ) ) );
		}

		// GGM_OTP::verify() resolves the identifier and checks its stored user
		// binding before consuming the code. Reuse that result instead of
		// repeating the same email/meta lookup in this request.
		$res = GGM_OTP::verify( $identifier, $otp, $request_id );
		if ( ! $res['success'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ) ) );
		}

		$user     = get_user_by( 'id', (int) $res['user_id'] );
		$is_phone = ! empty( $res['is_phone'] );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ) ) );
		}

		// Profile is incomplete if first_name is empty — still relevant for
		// accounts created via other paths (e.g. checkout).
		$needs_profile = empty( $user->first_name );

		// Log user in
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
		if ( get_current_user_id() !== (int) $user->ID ) {
			wp_clear_auth_cookie();
			wp_send_json_error( array( 'message' => __( 'Unable to create a login session. Please try again.', 'ggm-member-dashboard' ) ) );
		}

		// Custom Redirect — honor redirect_to (e.g. back to a specific checkout
		// page) when present and pointing at this site, otherwise fall back to
		// the generic post-login setting.
		$redirect_to = sanitize_text_field( wp_unslash( $_POST['redirect_to'] ?? '' ) );
		$redirect_to = $redirect_to ? wp_validate_redirect( $redirect_to, '' ) : '';
		$redirect    = $redirect_to ?: ggm_get_setting( 'ggm_redirect_after_login', '/dashboard/' );
		// An explicit, same-site continuation (for example the workshop
		// enrollment resume URL) must survive OTP login. The destination flow
		// can collect/update any missing contact data without losing purchase
		// intent. Generic logins still use the normal profile-completion step.
		if ( $needs_profile && ! $redirect_to ) {
			$redirect = '#profile-completion';
		}

		// Signed one-time token so complete_profile can auth even if cookie hasn't arrived yet.
		$auth_token = wp_hash( 'ggm_profile_' . $user->ID );

		wp_send_json_success( array(
			'authenticated' => true,
			'message'       => __( 'Login successful!', 'ggm-member-dashboard' ),
			'needs_profile' => $needs_profile,
			'redirect'      => $redirect,
			'identifier'    => $identifier,
			'is_phone'      => $is_phone,
			'uid'           => $user->ID,
			'auth_token'    => $auth_token,
		) );
	}

	/**
	 * AJAX: Password Login.
	 */
	public function ajax_password_login() {
		ggm_verify_ajax_nonce();

		// Accept either 'login' or 'email' key from the form.
		$login    = sanitize_text_field( wp_unslash( $_POST['login'] ?? $_POST['email'] ?? '' ) );
		$password = wp_unslash( $_POST['password'] ?? '' );

		if ( empty( $login ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Email and password are required.', 'ggm-member-dashboard' ) ) );
		}

		// Brute-force lockout — keyed on identifier + IP so one attacker can't
		// exhaust a real user's budget from a different address, nor grind
		// through many accounts from one address unnoticed.
		$client_ip   = ggm_get_client_ip();
		$lockout_key = 'ggm_pw_attempts_' . md5( strtolower( $login ) . '|' . $client_ip );
		if ( ggm_rate_limit_hit( $lockout_key, 5, 15 * MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many login attempts. Please try again later.', 'ggm-member-dashboard' ) ) );
		}

		$creds = array(
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => true,
		);

		$user = wp_signon( $creds, false );

		if ( is_wp_error( $user ) ) {
			// Never reveal WordPress's own "unknown username" vs "incorrect
			// password" distinction — that's a user-enumeration oracle.
			wp_send_json_error( array( 'message' => __( 'Invalid email or password.', 'ggm-member-dashboard' ) ) );
		}

		// Successful login — clear the lockout counter for this identifier/IP.
		delete_transient( $lockout_key );

		$redirect_to = sanitize_text_field( wp_unslash( $_POST['redirect_to'] ?? '' ) );
		$redirect_to = $redirect_to ? wp_validate_redirect( $redirect_to, '' ) : '';
		$redirect    = $redirect_to ?: ggm_get_setting( 'ggm_redirect_after_login', '/dashboard/' );
		wp_send_json_success( array(
			'redirect' => $redirect,
			'message'  => __( 'Login successful!', 'ggm-member-dashboard' ),
		) );
	}

	/**
	 * AJAX: Complete Profile.
	 * Auth: cookie-based (primary) with signed user_id fallback for the
	 * edge case where the browser hasn't sent the new cookie yet.
	 */
	public function ajax_complete_profile() {
		$user_id = get_current_user_id();

		// Fallback: browser may not have sent the new cookie yet in this request.
		// Accept a signed user_id token we included in the verify response.
		if ( ! $user_id ) {
			$token   = sanitize_text_field( wp_unslash( $_POST['auth_token'] ?? '' ) );
			$uid_raw = (int) ( $_POST['uid'] ?? 0 );
			if ( $uid_raw && $token && hash_equals( wp_hash( 'ggm_profile_' . $uid_raw ), $token ) ) {
				$user_id = $uid_raw;
				wp_set_current_user( $user_id );
			}
		}

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Please log in again.', 'ggm-member-dashboard' ) ) );
		}
		$first_name   = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name    = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$phone        = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$country_code = sanitize_text_field( wp_unslash( $_POST['country_code'] ?? '' ) );
		$email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$clean_phone  = '' !== $phone ? ggm_normalize_member_phone( $phone, $country_code ?: '+91' ) : '';

		if ( empty( $first_name ) ) {
			wp_send_json_error( array( 'message' => __( 'First name is required.', 'ggm-member-dashboard' ) ) );
		}
		if ( '' !== $phone && '' === $clean_phone ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid phone number for the selected country.', 'ggm-member-dashboard' ) ) );
		}

		$update_args = array(
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
		);

		// A phone-signup account gets a placeholder @ggm-temp.com email — swap
		// it for a real one if the guest checkout/profile form provided one
		// and it isn't already claimed by a different account.
		if ( $email && is_email( $email ) ) {
			$existing = get_user_by( 'email', $email );
			if ( ! $existing || (int) $existing->ID === (int) $user_id ) {
				$update_args['user_email'] = $email;
			}
		}

		wp_update_user( $update_args );

		update_user_meta( $user_id, 'billing_first_name', $first_name );
		update_user_meta( $user_id, 'billing_last_name', $last_name );

		if ( ! empty( $phone ) ) {
			update_user_meta( $user_id, 'billing_phone', $clean_phone );
			update_user_meta( $user_id, 'ggm_phone', $clean_phone );
		}
		if ( ! empty( $country_code ) ) {
			update_user_meta( $user_id, 'ggm_whatsapp_country_code', $country_code );
		}

		$redirect_to = sanitize_text_field( wp_unslash( $_POST['redirect_to'] ?? '' ) );
		$redirect_to = $redirect_to ? wp_validate_redirect( $redirect_to, '' ) : '';
		$redirect    = $redirect_to ?: ggm_get_setting( 'ggm_redirect_after_login', '/dashboard/' );
		wp_send_json_success( array(
			'redirect' => $redirect,
			'message'  => __( 'Profile completed successfully!', 'ggm-member-dashboard' ),
		) );
	}

	/**
	 * AJAX: Signup — creates a new account and logs the visitor straight in.
	 * Backs the [ggm_signup] shortcode / Signup Page setting.
	 */
	public function ajax_signup() {
		ggm_verify_ajax_nonce();

		// Coarse per-IP throttle against automated mass account creation.
		$client_ip = ggm_get_client_ip();
		if ( $client_ip && ggm_rate_limit_hit( 'ggm_signup_ip_' . md5( $client_ip ), 10, HOUR_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many signup attempts. Please try again later.', 'ggm-member-dashboard' ) ) );
		}

		$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone_raw  = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$country_code = class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::sanitize_country_dial_value( $_POST['country_code'] ?? '+91' ) : '+91';
		$password   = wp_unslash( $_POST['password'] ?? '' );
		$confirm    = wp_unslash( $_POST['confirm_password'] ?? '' );

		if ( empty( $first_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'ggm-member-dashboard' ) ) );
		}
		if ( strlen( $password ) < 6 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters.', 'ggm-member-dashboard' ) ) );
		}
		if ( $password !== $confirm ) {
			wp_send_json_error( array( 'message' => __( 'Passwords do not match.', 'ggm-member-dashboard' ) ) );
		}

		if ( get_user_by( 'email', $email ) ) {
			wp_send_json_error( array( 'message' => __( 'An account with this email already exists. Please log in instead.', 'ggm-member-dashboard' ) ) );
		}

		$clean_phone = $phone_raw ? ggm_normalize_member_phone( $phone_raw, $country_code ) : '';
		if ( $phone_raw && '' === $clean_phone ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid phone number for the selected country.', 'ggm-member-dashboard' ) ) );
		}

		$username = 'user_' . strstr( $email, '@', true ) . '_' . wp_rand( 100, 999 );
		$user_id  = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		wp_update_user( array(
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
		) );
		update_user_meta( $user_id, 'billing_first_name', $first_name );
		update_user_meta( $user_id, 'billing_last_name', $last_name );

		if ( $clean_phone ) {
			update_user_meta( $user_id, 'billing_phone', $clean_phone );
			update_user_meta( $user_id, 'ggm_phone', $clean_phone );
			update_user_meta( $user_id, 'ggm_whatsapp_country_code', $country_code );
		}

		$user = get_user_by( 'id', $user_id );
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect_to = sanitize_text_field( wp_unslash( $_POST['redirect_to'] ?? '' ) );
		$redirect_to = $redirect_to ? wp_validate_redirect( $redirect_to, '' ) : '';
		$redirect    = $redirect_to ?: ggm_get_setting( 'ggm_redirect_after_login', '/dashboard/' );

		wp_send_json_success( array(
			'message'  => __( 'Account created successfully!', 'ggm-member-dashboard' ),
			'redirect' => $redirect,
		) );
	}

	/**
	 * AJAX: Reset Password via OTP.
	 * "Forgot Password" flow — the OTP itself is requested via the existing
	 * ggm_send_otp action (same account-must-exist gate, rate limiting, and
	 * hashed storage as OTP login), this endpoint only verifies the code and
	 * applies the new password.
	 */
	public function ajax_reset_password() {
		ggm_verify_ajax_nonce();

		$identifier       = sanitize_text_field( wp_unslash( $_POST['identifier'] ?? '' ) );
		$otp              = sanitize_text_field( wp_unslash( $_POST['otp'] ?? '' ) );
		$request_id       = sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );
		$new_password     = wp_unslash( $_POST['new_password'] ?? '' );
		$confirm_password = wp_unslash( $_POST['confirm_password'] ?? '' );

		if ( empty( $identifier ) || empty( $otp ) || empty( $request_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Identifier and OTP code are required.', 'ggm-member-dashboard' ) ) );
		}
		if ( strlen( $new_password ) < 6 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters.', 'ggm-member-dashboard' ) ) );
		}
		if ( $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => __( 'Passwords do not match.', 'ggm-member-dashboard' ) ) );
		}

		$res = GGM_OTP::verify( $identifier, $otp, $request_id );
		if ( ! $res['success'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ) ) );
		}

		$user = get_user_by( 'id', (int) $res['user_id'] );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ) ) );
		}

		wp_set_password( $new_password, $user->ID );

		// wp_set_password() destroys any existing sessions for this user —
		// there shouldn't be one yet (this runs from the logged-out login
		// page), but log them straight in either way, same as every other
		// successful verification flow in this plugin.
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect_to = sanitize_text_field( wp_unslash( $_POST['redirect_to'] ?? '' ) );
		$redirect_to = $redirect_to ? wp_validate_redirect( $redirect_to, '' ) : '';
		$redirect    = $redirect_to ?: ggm_get_setting( 'ggm_redirect_after_login', '/dashboard/' );

		wp_send_json_success( array(
			'message'  => __( 'Password reset successfully!', 'ggm-member-dashboard' ),
			'redirect' => $redirect,
		) );
	}
}
