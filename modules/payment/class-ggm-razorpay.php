<?php
/**
 * Razorpay Integration — order creation, payment verification, AJAX endpoints.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Razorpay {

	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'wp_ajax_ggm_create_order',        $this, 'ajax_create_order' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_create_order', $this, 'ajax_create_order' );

		$loader->add_action( 'wp_ajax_ggm_verify_payment',        $this, 'ajax_verify_payment' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_verify_payment', $this, 'ajax_verify_payment' );

		$loader->add_action( 'wp_ajax_ggm_apply_coupon',        $this, 'ajax_apply_coupon' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_apply_coupon', $this, 'ajax_apply_coupon' );

		$loader->add_action( 'wp_ajax_ggm_register_workshop',        $this, 'ajax_register_workshop' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_register_workshop', $this, 'ajax_register_workshop' );

		$loader->add_action( 'wp_ajax_ggm_get_checkout_nonce',        $this, 'ajax_get_checkout_nonce' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_get_checkout_nonce', $this, 'ajax_get_checkout_nonce' );

		$loader->add_action( 'wp_ajax_ggm_check_workshop_contact_access',        $this, 'ajax_check_workshop_contact_access' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_check_workshop_contact_access', $this, 'ajax_check_workshop_contact_access' );
	}

	/**
	 * Authenticate a checkout AJAX request, returning the user ID — or
	 * sending a clean JSON error and terminating if that's not possible.
	 *
	 * A standard WordPress nonce is hashed together with the visitor's
	 * *session token*, which comes from their auth cookie — not just their
	 * user ID. For a brand-new visitor who verifies their phone mid-page
	 * (see checkout.php's inline OTP script), that cookie was only just set
	 * by wp_set_auth_cookie() a moment ago, and in practice its propagation
	 * to the very next AJAX call isn't reliable enough to depend on (a proxy
	 * or cache layer stripping/delaying Set-Cookie, timing, etc.) — every
	 * such visitor was hitting "Your session has expired" here no matter how
	 * quickly the nonce itself was refreshed.
	 *
	 * So: if a normal session is present, verify the normal nonce (real CSRF
	 * protection for the common case). Otherwise fall back to the exact same
	 * signed, cookie-independent one-time token ajax_complete_profile()
	 * already relies on for this identical handoff window — it's tied to a
	 * specific user ID via HMAC, so it can't be reused for anyone else's
	 * account, without needing a session to exist yet.
	 *
	 * @return int User ID (terminates the request itself on failure).
	 */
	private function authenticate_checkout_request() {
		$uid   = absint( $_POST['uid'] ?? 0 );
		$token = sanitize_text_field( wp_unslash( $_POST['auth_token'] ?? '' ) );

		// Accept the signed checkout handoff before checking a newly-set login
		// cookie, because the nonce in the open page still belongs to a guest.
		if ( $uid && $token && hash_equals( wp_hash( 'ggm_profile_' . $uid ), $token ) ) {
			wp_set_current_user( $uid );
			return $uid;
		}

		if ( is_user_logged_in() ) {
			if ( ! check_ajax_referer( 'ggm_nonce', 'nonce', false ) ) {
				wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'ggm-member-dashboard' ) ) );
			}
			return get_current_user_id();
		}

		// Temporary guest checkout: valid contact details create an isolated
		// customer record without requiring OTP or a login form.
		if ( ! check_ajax_referer( 'ggm_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'ggm-member-dashboard' ) ) );
		}

		$country_code = sanitize_text_field( wp_unslash( $_POST['contact_country_code'] ?? '+91' ) );
		$phone        = ggm_normalize_member_phone( wp_unslash( $_POST['contact_phone'] ?? '' ), $country_code );
		$email        = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
		$name         = sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) );
		if ( '' === $phone || ! is_email( $email ) || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter valid contact details to proceed.', 'ggm-member-dashboard' ) ) );
		}

		$existing = class_exists( 'GGM_Auth' ) ? GGM_Auth::resolve_user_from_identifier( $phone ) : array( 'user' => null, 'clean_id' => '' );
		if ( empty( $existing['user'] ) && is_email( $email ) && class_exists( 'GGM_Auth' ) ) {
			$existing = GGM_Auth::resolve_user_from_identifier( $email );
		}
		if ( ! empty( $existing['user'] ) ) {
			$existing_user = $existing['user'];
			$workshop_id   = absint( $_POST['workshop_id'] ?? 0 );
			$access_level  = ( $workshop_id && class_exists( 'GGM_Workshop' ) )
				? GGM_Workshop::get_user_access_level( $existing_user->ID, $workshop_id )
				: 'none';
			$has_paid_access = in_array( $access_level, array( 'paid', 'global_free' ), true );
			$message = $has_paid_access
				? __( 'You already have access to this workshop. Login with OTP to continue.', 'ggm-member-dashboard' )
				: __( 'An account already exists for these details. Login with OTP to continue.', 'ggm-member-dashboard' );

			wp_send_json_error( array(
				'message'        => $message,
				'login_required' => true,
				'identifier'     => $existing['clean_id'] ?: $phone,
				'access_level'   => $access_level,
				'hide_pricing'   => $has_paid_access,
			) );
		}

		$base   = 'ggm_guest_' . $phone;
		$login  = $base;
		$suffix = 1;
		while ( username_exists( $login ) ) {
			$login = $base . '_' . $suffix++;
		}
		$account_email = email_exists( $email )
			? $login . '@guest.invalid'
			: $email;
		$user_id = wp_create_user( $login, wp_generate_password( 32, true, true ), $account_email );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not prepare checkout. Please try again.', 'ggm-member-dashboard' ) ) );
		}

		$parts = preg_split( '/\s+/', $name, 2 );
		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $name,
			'first_name'   => $parts[0] ?? $name,
			'last_name'    => $parts[1] ?? '',
		) );
		update_user_meta( $user_id, 'ggm_phone', $phone );
		update_user_meta( $user_id, 'billing_phone', $phone );
		update_user_meta( $user_id, 'ggm_whatsapp_country_code', $country_code );
		update_user_meta( $user_id, 'ggm_checkout_email', $email );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		return $user_id;
	}

	/**
	 * Return a nonce generated right now, for whoever is actually making this
	 * request — server-side, based on their real cookies, completely
	 * unaffected by any page-cache plugin serving a stale/stranger's copy of
	 * the checkout page HTML. Also hands back this visitor's own contact
	 * details, since the checkout page deliberately doesn't server-render
	 * them (see checkout.php).
	 */
	public function ajax_get_checkout_nonce() {
		$contact = array( 'name' => '', 'email' => '', 'phone' => '' );
		$currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$base_currency = class_exists( 'GGM_Currency' )
			? GGM_Currency::base_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$credit_currency_allowed = strtoupper( $currency ) === strtoupper( $base_currency );

		// Also hand back the *actual current visitor's* own contact details —
		// the checkout template deliberately no longer server-renders these
		// (a cached copy of that HTML would otherwise leak whoever's session
		// it was generated from, exactly like the admin's info showing up
		// for other visitors did).
		if ( is_user_logged_in() ) {
			$user             = wp_get_current_user();
			$contact['name']  = $user->display_name;
			$checkout_email   = get_user_meta( $user->ID, 'ggm_checkout_email', true );
			$contact['email'] = ( $checkout_email && '@guest.invalid' === substr( $user->user_email, -14 ) )
				? $checkout_email
				: $user->user_email;
			$contact['phone'] = ggm_get_member_phone( $user->ID );
		}

		wp_send_json_success( array(
			'nonce'   => wp_create_nonce( 'ggm_nonce' ),
			'contact' => $contact,
			'credit_balance' => $credit_currency_allowed && is_user_logged_in() ? GGM_Credit::get_balance( get_current_user_id() ) : 0,
			'credit_currency_allowed' => $credit_currency_allowed,
		) );
	}

	/**
	 * AJAX: Read-only check for a typed mobile number on workshop pages.
	 *
	 * This lets the inline workshop form switch paid members to OTP login as
	 * soon as their mobile number is entered, instead of waiting until they
	 * click Pay/Join and hit order creation.
	 */
	public function ajax_check_workshop_contact_access() {
		if ( ! check_ajax_referer( 'ggm_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'ggm-member-dashboard' ) ) );
		}

		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );
		$phone       = preg_replace( '/\D/', '', wp_unslash( $_POST['phone'] ?? '' ) );
		$email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone_only_lookup = ! empty( $_POST['phone_only'] );

		if ( ! $workshop_id || ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
			wp_send_json_success( array( 'matched' => false ) );
		}

		// Get the configured validation method from settings (server-side authoritative)
		$validation_method = ggm_get_setting( 'ggm_workshop_validation_method', 'phone' );
		if ( ! in_array( $validation_method, array( 'phone', 'email', 'both' ), true ) ) {
			$validation_method = 'phone';
		}
		// The full inline join form has a country picker for contact metadata,
		// but existing-account lookup must use only the number typed into its tel
		// input. This scoped flag prevents the global email/both setting from
		// blocking that phone lookup and never combines a dial code with $phone.
		if ( $phone_only_lookup ) {
			$validation_method = 'phone';
		}

		// Validate inputs based on the configured method
		$phone_identity  = class_exists( 'GGM_OTP' ) ? GGM_OTP::normalize_identifier( $phone ) : false;
		$normalized_phone_input = is_array( $phone_identity ) && ! empty( $phone_identity['is_phone'] ) ? $phone_identity['clean_id'] : '';
		$has_valid_phone = '' !== $normalized_phone_input;
		$has_valid_email = is_email( $email );

		// For 'phone' mode: require valid phone
		// For 'email' mode: require valid email
		// For 'both' mode: require both valid phone and valid email
		$can_lookup = false;
		switch ( $validation_method ) {
			case 'phone':
				$can_lookup = $has_valid_phone;
				break;
			case 'email':
				$can_lookup = $has_valid_email;
				break;
			case 'both':
				$can_lookup = $has_valid_phone && $has_valid_email;
				break;
		}

		if ( ! $can_lookup ) {
			wp_send_json_success( array( 'matched' => false ) );
		}

		// Resolve user based on validation method
		$resolved = array( 'user' => null, 'clean_id' => '', 'matched_user_id' => 0 );

		if ( class_exists( 'GGM_Auth' ) ) {
			$phone_user  = null;
			$email_user  = null;
			$normalized_phone = '';

			if ( $has_valid_phone ) {
				// Normalize phone to 10-digit canonical form before lookup
				$normalized_phone = $normalized_phone_input;
				if ( $normalized_phone ) {
					$phone_user = GGM_Auth::find_user_by_phone( $normalized_phone );
				}
			}

			if ( $has_valid_email ) {
				// Use dedicated email lookup that checks all email storage locations
				$email_user = GGM_Auth::find_user_by_email( $email );
			}

			switch ( $validation_method ) {
				case 'phone':
					if ( $phone_user ) {
						$resolved = array(
							'user'           => $phone_user,
							'clean_id'       => $normalized_phone,
							'matched_user_id' => $phone_user->ID,
						);
					}
					break;

				case 'email':
					if ( $email_user ) {
						$resolved = array(
							'user'           => $email_user,
							'clean_id'       => $email,
							'matched_user_id' => $email_user->ID,
						);
					}
					break;

				case 'both':
					if ( $phone_user && $email_user && $phone_user->ID === $email_user->ID ) {
						$resolved = array(
							'user'           => $phone_user,
							'clean_id'       => $normalized_phone, // Use normalized phone as identifier for OTP
							'matched_user_id' => $phone_user->ID,
						);
					}
					break;
			}
		}

		if ( empty( $resolved['user'] ) || ! class_exists( 'GGM_Workshop' ) ) {
			wp_send_json_success( array( 'matched' => false ) );
		}

		$access_level = GGM_Workshop::get_user_access_level( $resolved['user']->ID, $workshop_id );
		$has_paid_access = in_array( $access_level, array( 'paid', 'global_free' ), true );
		$free_option_unavailable = 'free' === $access_level;
		if ( $has_paid_access ) {
			$message = __( 'Account already registered. Log in with OTP to continue, or choose a contribution amount below.', 'ggm-member-dashboard' );
		} elseif ( 'free' === $access_level ) {
			$message = __( 'Account already registered. Log in with OTP to continue, or choose a contribution amount below.', 'ggm-member-dashboard' );
		} else {
			$message = __( 'Account already registered. Log in with OTP to continue, or choose a contribution amount below.', 'ggm-member-dashboard' );
		}

		wp_send_json_success( array(
			'matched'                => true,
			'login_required'         => true,
			'identifier'             => $resolved['clean_id'],
			'access_level'           => $access_level,
			'hide_pricing'           => $has_paid_access,
			'free_option_unavailable' => $free_option_unavailable,
			'message'                => $message,
		) );
	}

	// ─── API Helpers ──────────────────────────────────────────────────────────

	public function get_key_id() {
		$settings = get_option( 'ggm_settings', array() );
		$mode     = $settings['ggm_razorpay_mode'] ?? 'test';
		return 'live' === $mode
			? ( $settings['ggm_razorpay_live_key_id'] ?? '' )
			: ( $settings['ggm_razorpay_test_key_id'] ?? '' );
	}

	private function get_key_secret() {
		$settings = get_option( 'ggm_settings', array() );
		$mode     = $settings['ggm_razorpay_mode'] ?? 'test';
		return 'live' === $mode
			? ( $settings['ggm_razorpay_live_key_secret'] ?? '' )
			: ( $settings['ggm_razorpay_test_key_secret'] ?? '' );
	}

	/**
	 * Create a Razorpay order via REST API.
	 *
	 * @param float  $amount       Amount in rupees.
	 * @param string $currency
	 * @param string $receipt_id
	 * @return array|WP_Error
	 */
	public function create_order( $amount, $currency = 'INR', $receipt_id = '' ) {
		$key_id     = $this->get_key_id();
		$key_secret = $this->get_key_secret();
		$currency   = class_exists( 'GGM_Currency' )
			? GGM_Currency::normalize_code( $currency, GGM_Currency::base_currency() )
			: ( preg_match( '/^[A-Z]{3}$/', strtoupper( trim( (string) $currency ) ) ) ? strtoupper( trim( (string) $currency ) ) : 'INR' );

		if ( empty( $key_id ) || empty( $key_secret ) ) {
			return new WP_Error( 'no_keys', __( 'Razorpay API keys are not configured.', 'ggm-member-dashboard' ) );
		}

		$response = wp_remote_post( 'https://api.razorpay.com/v1/orders', array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $key_id . ':' . $key_secret ),
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'amount'          => (int) round( $amount * 100 ), // paise
				'currency'        => $currency,
				'receipt'         => $receipt_id ?: 'GGM-' . time(),
				'payment_capture' => 1,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			return new WP_Error( 'razorpay_error', $data['error']['description'] ?? __( 'Razorpay order creation failed.', 'ggm-member-dashboard' ) );
		}

		return $data;
	}

	/**
	 * Verify Razorpay payment signature (HMAC-SHA256).
	 *
	 * @param string $order_id
	 * @param string $payment_id
	 * @param string $signature
	 * @return bool
	 */
	public function verify_signature( $order_id, $payment_id, $signature ) {
		$key_secret = $this->get_key_secret();
		$expected   = hash_hmac( 'sha256', $order_id . '|' . $payment_id, $key_secret );
		return hash_equals( $expected, $signature );
	}

	// ─── Outbound Payment Webhook ─────────────────────────────────────────────

	/**
	 * Fire-and-forget JSON POST to the admin-configured webhook URL whenever
	 * a payment is created (pending/"on hold"), succeeds, or fails — so an
	 * external site can mirror payment state without polling this one.
	 *
	 * @param string $event   'payment.pending' | 'payment.success' | 'payment.failed'
	 * @param object $payment Row from wp_ggm_payments.
	 */
	private function send_webhook( $event, $payment ) {
		$url = esc_url_raw( trim( (string) ggm_get_setting( 'ggm_webhook_url', '' ) ) );
		if ( '' === $url || ! $payment ) {
			return;
		}

		$user      = get_userdata( $payment->user_id );
		$item_id   = $payment->workshop_id ?: ( $payment->course_id ?? 0 );
		$item_type = $payment->workshop_id ? 'workshop' : 'course';
		$item_name = '';

		if ( $payment->workshop_id ) {
			$item_name = get_the_title( $payment->workshop_id );
		} elseif ( ! empty( $payment->course_id ) ) {
			$item_name = get_the_title( $payment->course_id );
		}

		$customer = array(
			'id'         => (int) $payment->user_id,
			'name'       => $user ? $user->display_name : '',
			'first_name' => $user ? $user->first_name : '',
			'last_name'  => $user ? $user->last_name : '',
			'email'      => $user ? $user->user_email : '',
			'phone'      => $user ? ( get_user_meta( $user->ID, 'billing_phone', true ) ?: get_user_meta( $user->ID, 'ggm_phone', true ) ) : '',
			'address_1'  => $user ? get_user_meta( $user->ID, 'billing_address_1', true ) : '',
			'address_2'  => $user ? get_user_meta( $user->ID, 'billing_address_2', true ) : '',
			'city'       => $user ? get_user_meta( $user->ID, 'billing_city', true ) : '',
			'state'      => $user ? get_user_meta( $user->ID, 'billing_state', true ) : '',
			'postcode'   => $user ? get_user_meta( $user->ID, 'billing_postcode', true ) : '',
			'country'    => $user ? get_user_meta( $user->ID, 'billing_country', true ) : '',
		);

		$payload = array(
			'event'               => $event,
			'payment_id'          => (int) $payment->id,
			'status'              => $payment->status,
			'type'                => $item_type,
			'item_id'             => (int) $item_id,
			'item_name'           => $item_name,
			'amount'              => (float) $payment->amount,
			'original_amount'     => (float) ( $payment->original_amount ?? $payment->amount ),
			'discount_amount'     => (float) ( $payment->discount_amount ?? 0 ),
			'credit_amount'       => (float) ( $payment->credit_amount ?? 0 ),
			'currency'            => $payment->currency,
			'customer'            => $customer,
			'user'                => $customer,
			'razorpay_order_id'   => $payment->razorpay_order_id,
			'razorpay_payment_id' => $payment->razorpay_payment_id ?? '',
			'timestamp'           => current_time( 'mysql' ),
		);

		wp_remote_post( $url, array(
			'timeout'  => 8,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $payload ),
		) );
	}

	// ─── Shared helpers ───────────────────────────────────────────────────────

	/**
	 * Resolve the authoritative (server-known) base price for a workshop or
	 * course. Never trust a client-supplied amount.
	 *
	 * @param string $type    'workshop' or 'course'.
	 * @param int    $item_id
	 * @return float
	 */
	private function resolve_base_amount( $type, $item_id, $contribution_option_id = '' ) {
		if ( 'workshop' === $type ) {
			if ( class_exists( 'GGM_Workshop' ) ) {
				$resolved = GGM_Workshop::resolve_purchase_price( $item_id, $contribution_option_id );
				return ! empty( $resolved['valid'] ) ? (float) $resolved['amount'] : 0.0;
			}
			return (float) get_post_meta( $item_id, 'workshop_money', true );
		}
		if ( 'course' === $type ) {
			$raw_price = get_post_meta( $item_id, 'course_price', true );
			return class_exists( 'GGM_Workshop' ) ? GGM_Workshop::parse_price( $raw_price ) : (float) $raw_price;
		}
		return 0.0;
	}

	private function checkout_currency() {
		if ( class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() ) {
			return GGM_Currency::selected_currency();
		}

		return class_exists( 'GGM_Currency' )
			? GGM_Currency::base_currency()
			: ( preg_match( '/^[A-Z]{3}$/', strtoupper( trim( (string) ggm_get_setting( 'ggm_currency', 'INR' ) ) ) ) ? strtoupper( trim( (string) ggm_get_setting( 'ggm_currency', 'INR' ) ) ) : 'INR' );
	}

	private function display_amount( $base_amount, $currency, $item_id, $include_adjustment = true ) {
		if ( class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() ) {
			return GGM_Currency::convert_amount( $base_amount, $currency, $item_id, $include_adjustment );
		}

		return round( max( 0, (float) $base_amount ), 2 );
	}

	/**
	 * Grant access after a successful purchase and fire the relevant
	 * notification hooks. Used by both the Razorpay verify path and the
	 * zero-amount (100%-off coupon) immediate-success path. A workshop
	 * purchase grants access to every one of its time slots — there's no
	 * more per-slot selection at checkout (Task 6).
	 *
	 * @param object $payment Row from wp_ggm_payments.
	 * @return string Redirect URL.
	 */
	private function grant_purchase( $payment ) {
		if ( $payment->workshop_id ) {
			GGM_Access_Manager::grant_workshop_bundle( $payment->user_id, $payment->workshop_id, $payment->id );
		}

		if ( ! empty( $payment->course_id ) ) {
			GGM_Access_Manager::grant_course( $payment->user_id, $payment->course_id, $payment->id );

			do_action( 'ggm_course_purchased', $payment->user_id, $payment->course_id );
		}

		if ( $payment->coupon_id ) {
			GGM_Coupon::record_usage( $payment->coupon_id, $payment->user_id, $payment->id, array(
				'workshop_id'     => $payment->workshop_id,
				'original_amount' => $payment->original_amount,
				'discount_amount' => $payment->discount_amount,
				'final_amount'    => $payment->amount,
			) );
		}

		do_action( 'ggm_payment_success', $payment->user_id, $payment->id );
		$this->send_webhook( 'payment.success', $payment );

		// The guest checkout handoff can verify via its signed user token even
		// when the auth cookie set during order creation did not reach the next
		// request. Re-establish the persistent browser session only after the
		// payment signature and payment ownership have been verified, so the
		// dashboard redirect always opens the purchaser's own account.
		$this->establish_customer_session( (int) $payment->user_id );

		// No dedicated "success page" is configured anywhere in this plugin, so
		// default straight to the member dashboard rather than a guessed
		// "/checkout/success/" URL that doesn't actually exist on most sites.
		$success_url = ggm_get_setting( 'ggm_checkout_success_page', '' );
		if ( ! $success_url ) {
			$dash_page_id = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );
			$success_url  = $dash_page_id ? get_permalink( $dash_page_id ) : home_url( '/dashboard/' );
		}

		return add_query_arg( 'payment_id', $payment->id, $success_url );
	}

	/**
	 * Log the verified purchaser into WordPress before returning the dashboard
	 * redirect. Calling this for an already logged-in purchaser is harmless and
	 * refreshes their persistent session cookie.
	 *
	 * @param int $user_id Verified payment owner.
	 */
	private function establish_customer_session( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			return;
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
	}

	// ─── AJAX ─────────────────────────────────────────────────────────────────

	/**
	 * AJAX: Validate + calculate a coupon for the current checkout context.
	 * Purely informational for the UI — ajax_create_order() re-validates authoritatively.
	 */
	public function ajax_apply_coupon() {
		$user_id = $this->authenticate_checkout_request();

		$code          = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) );
		$type          = in_array( $_POST['type'] ?? '', array( 'workshop', 'course' ), true ) ? $_POST['type'] : '';
		$item_id       = absint( $_POST['item_id'] ?? 0 );
		$currency      = $this->checkout_currency();
		if ( 'workshop' === $type && class_exists( 'GGM_Workshop' ) && GGM_Workshop::is_contribution( $item_id ) ) {
			wp_send_json_error( array( 'message'=>__( 'Coupons do not apply to contribution pricing.', 'ggm-member-dashboard' ) ) );
		}
		$transient_key = "ggm_applied_coupon_{$user_id}_{$type}_{$item_id}";

		if ( '' === $code || ! $type || ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ggm-member-dashboard' ) ) );
		}

		$amount = $this->resolve_base_amount( $type, $item_id );
		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'This item cannot accept a coupon.', 'ggm-member-dashboard' ) ) );
		}

		$result = GGM_Coupon::validate_and_calculate( $code, array(
			'user_id' => $user_id,
			'type'    => $type,
			'item_id' => $item_id,
			'amount'  => $amount,
		) );

		if ( ! $result['valid'] ) {
			delete_transient( $transient_key );
			$result['uid']        = $user_id;
			$result['auth_token'] = wp_hash( 'ggm_profile_' . $user_id );
			wp_send_json_error( $result );
		}

		set_transient( $transient_key, $code, HOUR_IN_SECONDS );

		$display_original = $this->display_amount( $amount, $currency, $item_id );
		$display_final    = $this->display_amount( (float) $result['final_amount'], $currency, $item_id );
		$result['base_original_amount'] = $amount;
		$result['base_final_amount']    = (float) $result['final_amount'];
		$result['base_discount_amount'] = (float) $result['discount_amount'];
		$result['original_amount']      = $display_original;
		$result['final_amount']         = $display_final;
		$result['discount_amount']      = max( 0, $display_original - $display_final );
		$result['currency']             = $currency;
		$result['uid']        = $user_id;
		$result['auth_token'] = wp_hash( 'ggm_profile_' . $user_id );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Create Razorpay order for a workshop or course.
	 */
	public function ajax_create_order() {
		$user_id = $this->authenticate_checkout_request();

		// Any unexpected PHP error inside this AJAX handler used to print
		// before wp_send_json() could run, corrupting the JSON body — the
		// browser's fetch/jQuery then can't parse the response and shows a
		// generic "Something went wrong" with no clue why. Guarantee a clean
		// JSON response no matter what, and log the real cause for us to see.
		try {
			$this->do_create_order( $user_id );
		} catch ( \Throwable $e ) {
			if ( class_exists( 'GGM_Meta_Boxes' ) ) {
				GGM_Meta_Boxes::log_error( 'ajax_create_order crashed: ' . $e->getMessage(), array(
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
					'trace' => $e->getTraceAsString(),
				) );
			}
			wp_send_json_error( array( 'message' => __( 'Something went wrong creating your order. Please try again in a moment.', 'ggm-member-dashboard' ) ) );
		}
	}

	/**
	 * Actual order-creation logic, wrapped by ajax_create_order()'s try/catch.
	 *
	 * @param int $user_id
	 */
	private function do_create_order( $user_id ) {
		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );
		$course_id   = absint( $_POST['course_id'] ?? 0 );
		$coupon_code = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) );
		$contribution_option_id = sanitize_text_field( wp_unslash( $_POST['contribution_option_id'] ?? '' ) );

		// The checkout page collects a WhatsApp number (+ country code) so it
		// can be pre-filled into the Razorpay popup — save it to the same
		// user meta keys the dashboard's own "Edit Profile" form uses, so it
		// also shows up there afterwards instead of only ever living in the
		// payment popup.
		$contact_phone = sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) );
		$contact_email = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
		$country_code  = sanitize_text_field( wp_unslash( $_POST['contact_country_code'] ?? '+91' ) );
		$clean_phone   = '' !== $contact_phone ? ggm_normalize_member_phone( $contact_phone, $country_code ) : '';
		if ( '' !== $contact_phone && '' === $clean_phone ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid phone number for the selected country.', 'ggm-member-dashboard' ) ) );
		}
		if ( is_email( $contact_email ) ) {
			update_user_meta( $user_id, 'ggm_checkout_email', $contact_email );
			update_user_meta( $user_id, 'billing_email', $contact_email );
		}
		if ( '' !== $contact_phone ) {
			if ( '' !== $clean_phone ) {
				update_user_meta( $user_id, 'billing_phone', $clean_phone );
				update_user_meta( $user_id, 'ggm_phone', $clean_phone );
				update_user_meta( $user_id, 'ggm_whatsapp_country_code', $country_code );
			}
		}

		if ( ! $workshop_id && ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ggm-member-dashboard' ) ) );
		}

		$type    = $workshop_id ? 'workshop' : 'course';
		$item_id = $workshop_id ?: $course_id;
		$currency = $this->checkout_currency();
		$base_currency = class_exists( 'GGM_Currency' )
			? GGM_Currency::base_currency()
			: strtoupper( sanitize_text_field( ggm_get_setting( 'ggm_currency', 'INR' ) ) );
		$credit_currency_allowed = strtoupper( $currency ) === strtoupper( $base_currency );

		// A genuinely free item (price left at 0/blank) is allowed through —
		// it's granted with no Razorpay step at all via the $final_amount <= 0
		// branch below, same as a 100%-off coupon. This matters now that
		// "Enroll Now" for a free course routes through this same checkout.
		$price_snapshot = array( 'valid'=>true, 'mode'=>'fixed', 'amount'=>$this->resolve_base_amount( $type, $item_id ), 'option_id'=>'', 'label'=>'' );
		if ( 'workshop' === $type && class_exists( 'GGM_Workshop' ) ) {
			$price_snapshot = GGM_Workshop::resolve_purchase_price( $item_id, $contribution_option_id );
			if ( empty( $price_snapshot['valid'] ) ) { wp_send_json_error( array( 'message'=>$price_snapshot['message'] ) ); }
		}
		$base_amount = (float) $price_snapshot['amount'];
		$is_contribution = 'contribution' === $price_snapshot['mode'];
		if ( $is_contribution ) { $coupon_code = ''; }

		// Durable workshop access usually blocks duplicate purchases. The one
		// exception is a contribution workshop where the existing access came
		// from a Free option and the member now picked a paid option.
		if (
			'workshop' === $type
			&& class_exists( 'GGM_Workshop' )
			&& ( $is_contribution || $base_amount > 0 )
			&& GGM_Workshop::user_has_access( $user_id, $item_id )
		) {
			$access_level = GGM_Workshop::get_user_access_level( $user_id, $item_id );
			if ( $is_contribution && 'free' === $access_level && $base_amount > 0 ) {
				// Free contribution access can be upgraded later by selecting
				// a paid contribution option; paid/direct access still blocks
				// duplicate purchases below.
			} else {
				$login_required = ! is_user_logged_in();
				wp_send_json_error( array(
					'message'        => $login_required
						? __( 'You already have access to this workshop. Login with OTP to continue.', 'ggm-member-dashboard' )
						: __( 'You already have access to this workshop.', 'ggm-member-dashboard' ),
					'login_required' => $login_required,
					'access_level'   => $access_level,
					'hide_pricing'   => 'free' !== $access_level,
				) );
			}
		}

		// Server-side protection: prevent existing free users from selecting Free option again.
		if (
			'workshop' === $type
			&& class_exists( 'GGM_Workshop' )
			&& $is_contribution
		) {
			$access_level = GGM_Workshop::get_user_access_level( $user_id, $item_id );
			if ( 'free' === $access_level ) {
				// Check if the selected contribution option is Free type
				$selected_option = GGM_Workshop::resolve_purchase_price( $item_id, $contribution_option_id );
				if ( ! empty( $selected_option['valid'] ) && 'free' === $selected_option['type'] ) {
					wp_send_json_error( array(
						'message'        => __( 'You have already joined this workshop for free. Please choose a paid contribution option.', 'ggm-member-dashboard' ),
						'login_required' => ! is_user_logged_in(),
						'access_level'   => $access_level,
						'hide_pricing'   => false,
					) );
				}
			}
		}

		// Resolve coupon (explicit param, or the one persisted from ajax_apply_coupon).
		if ( '' === $coupon_code && ! $is_contribution ) {
			$persisted = get_transient( "ggm_applied_coupon_{$user_id}_{$type}_{$item_id}" );
			if ( $persisted ) {
				$coupon_code = $persisted;
			}
		}

		$final_amount    = $base_amount;
		$discount_amount = 0.0;
		$coupon_id       = null;

		if ( '' !== $coupon_code ) {
			$coupon_result = GGM_Coupon::validate_and_calculate( $coupon_code, array(
				'user_id' => $user_id,
				'type'    => $type,
				'item_id' => $item_id,
				'amount'  => $base_amount,
			) );

			if ( ! $coupon_result['valid'] ) {
				wp_send_json_error( array( 'message' => $coupon_result['message'] ) );
			}

			$final_amount    = $coupon_result['final_amount'];
			$discount_amount = $coupon_result['discount_amount'];
			$coupon_id       = $coupon_result['coupon_id'];
		}

		$display_original_amount = $this->display_amount( $base_amount, $currency, $item_id );
		$display_final_amount    = $this->display_amount( $final_amount, $currency, $item_id );
		$display_discount_amount = max( 0, $display_original_amount - $display_final_amount );

		$payment_data = array(
			'user_id'          => $user_id,
			'workshop_id'      => $workshop_id ?: null,
			'course_id'        => $course_id ?: null,
			'coupon_id'        => $coupon_id,
			'original_amount'  => $display_original_amount,
			'discount_amount'  => $display_discount_amount,
			'credit_amount'    => 0,
			'currency'         => $currency,
			'pricing_mode'     => $price_snapshot['mode'],
			'contribution_option_id' => $price_snapshot['option_id'],
			'contribution_label' => $price_snapshot['label'],
			'contribution_amount' => $is_contribution ? $display_original_amount : null,
		);

		// Create the local row first, then atomically reserve credit against its
		// unique payment ID. This prevents simultaneous checkouts overspending.
		$payment_data['amount'] = $display_final_amount;
		$payment_id = GGM_Payment::create( $payment_data );
		if ( ! $payment_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create payment record.', 'ggm-member-dashboard' ) ) );
		}
		$credit_amount = $is_contribution || ! $credit_currency_allowed ? 0.0 : GGM_Credit::reserve( $user_id, $display_final_amount, $payment_id );
		$charge_amount = round( max( 0, $display_final_amount - $credit_amount ), 2 );
		GGM_Payment::update_order( $payment_id, '', $charge_amount, $credit_amount );

		// Diagnostic snapshot on every checkout attempt — the front end
		// silently redirects straight to the dashboard (no Razorpay popup at
		// all) whenever $final_amount resolves to 0, which produces no PHP
		// error. This is the only way to see *why* that happened. See
		// Settings → Error Log.
		if ( class_exists( 'GGM_Meta_Boxes' ) ) {
			GGM_Meta_Boxes::log_error( 'Checkout order requested — price/coupon snapshot', array(
				'level'           => 'debug',
				'post_id'         => $item_id,
				'post_type'       => $type,
				'razorpay_mode'   => ( get_option( 'ggm_settings', array() )['ggm_razorpay_mode'] ?? 'test' ),
				'key_id_set'      => $this->get_key_id() ? 'yes' : 'NO — missing for this mode',
				'key_secret_set'  => $this->get_key_secret() ? 'yes' : 'NO — missing for this mode',
				'base_amount'     => $base_amount,
				'currency'        => $currency,
				'base_currency'   => $base_currency,
				'display_original_amount' => $display_original_amount,
				'coupon_code'     => $coupon_code ?: '(none)',
				'discount_amount' => $discount_amount,
				'display_discount_amount' => $display_discount_amount,
				'display_final_amount' => $display_final_amount,
				'credit_amount'   => $credit_amount,
				'charge_amount'   => $charge_amount,
				'decision'        => $charge_amount <= 0 ? 'FREE — skipping Razorpay' : 'Razorpay order will be created',
			) );
		}

		// 100%-off coupon: skip Razorpay entirely and grant access immediately.
		if ( $charge_amount <= 0 ) {
			GGM_Payment::mark_success( $payment_id, '', '' );
			GGM_Credit::apply( $payment_id );
			$payment  = GGM_Payment::get( $payment_id );
			$redirect = $this->grant_purchase( $payment );

			wp_send_json_success( array(
				'free'     => true,
				'redirect' => $redirect,
			) );
		}

		$receipt  = 'GGM-U' . $user_id . '-' . time();
		$rp_order = $this->create_order( $charge_amount, $currency, $receipt );

		if ( is_wp_error( $rp_order ) ) {
			GGM_Credit::release( $payment_id );
			GGM_Payment::mark_failed( $payment_id );
			if ( class_exists( 'GGM_Meta_Boxes' ) ) {
				GGM_Meta_Boxes::log_error( 'Razorpay order creation failed: ' . $rp_order->get_error_message(), array(
					'post_id'   => $item_id,
					'post_type' => $type,
				) );
			}
			wp_send_json_error( array( 'message' => $rp_order->get_error_message() ) );
		}

		GGM_Payment::update_order( $payment_id, $rp_order['id'], $charge_amount, $credit_amount );

		$this->send_webhook( 'payment.pending', GGM_Payment::get( $payment_id ) );

		wp_send_json_success( array(
			'order_id'        => $rp_order['id'],
			'amount'          => $rp_order['amount'],
			'currency'        => $rp_order['currency'],
			'key_id'          => $this->get_key_id(),
			'payment_id'      => $payment_id,
			'original_amount' => $display_original_amount,
			'discount_amount' => $display_discount_amount,
			'credit_amount'   => $credit_amount,
			'final_amount'    => $charge_amount,
			'base_original_amount' => $base_amount,
			'base_discount_amount' => $discount_amount,
			'base_final_amount' => $final_amount,
			'uid'             => $user_id,
			'auth_token'      => wp_hash( 'ggm_profile_' . $user_id ),
		) );
	}

	/**
	 * AJAX: Verify payment, then grant membership/workshop access.
	 */
	public function ajax_verify_payment() {
		$user_id = $this->authenticate_checkout_request();

		$rp_order_id   = sanitize_text_field( wp_unslash( $_POST['razorpay_order_id'] ?? '' ) );
		$rp_payment_id = sanitize_text_field( wp_unslash( $_POST['razorpay_payment_id'] ?? '' ) );
		$rp_signature  = sanitize_text_field( wp_unslash( $_POST['razorpay_signature'] ?? '' ) );
		$payment_id    = absint( $_POST['payment_id'] ?? 0 );

		if ( ! $rp_order_id || ! $rp_payment_id || ! $rp_signature ) {
			wp_send_json_error( array( 'message' => __( 'Payment data incomplete.', 'ggm-member-dashboard' ) ) );
		}
		$payment = GGM_Payment::get( $payment_id );
		if ( ! $payment || (int) $payment->user_id !== (int) $user_id || $payment->razorpay_order_id !== $rp_order_id || 'pending' !== $payment->status ) {
			wp_send_json_error( array( 'message' => __( 'Payment record not found.', 'ggm-member-dashboard' ) ) );
		}

		if ( ! $this->verify_signature( $rp_order_id, $rp_payment_id, $rp_signature ) ) {
			GGM_Payment::mark_failed( $payment_id );
			GGM_Credit::release( $payment_id );
			$this->send_webhook( 'payment.failed', GGM_Payment::get( $payment_id ) );
			wp_send_json_error( array( 'message' => __( 'Payment signature verification failed.', 'ggm-member-dashboard' ) ) );
		}

		GGM_Payment::mark_success( $payment_id, $rp_payment_id, $rp_signature );
		GGM_Credit::apply( $payment_id );
		$payment = GGM_Payment::get( $payment_id );

		$redirect_url = $this->grant_purchase( $payment );

		wp_send_json_success( array(
			'message'  => __( 'Payment successful!', 'ggm-member-dashboard' ),
			'redirect' => $redirect_url,
		) );
	}

	/**
	 * AJAX: Register for a free workshop (no payment involved).
	 */
	public function ajax_register_workshop() {
		$user_id     = $this->authenticate_checkout_request();
		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );

		if ( ! $workshop_id || ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Workshop not found.', 'ggm-member-dashboard' ) ) );
		}

		$is_free = get_post_meta( $workshop_id, 'is_free', true );
		if ( class_exists( 'GGM_Workshop' ) && GGM_Workshop::is_contribution( $workshop_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This workshop requires a contribution.', 'ggm-member-dashboard' ) ) );
		}
		if ( '1' !== (string) $is_free ) {
			wp_send_json_error( array( 'message' => __( 'This workshop requires payment.', 'ggm-member-dashboard' ) ) );
		}

		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}ggm_workshop_access (user_id, workshop_id, granted_via, granted_at)
			 VALUES (%d, %d, 'free', %s)
			 ON DUPLICATE KEY UPDATE granted_via = 'free'",
			$user_id,
			$workshop_id,
			current_time( 'mysql' )
		) );
		$linked_course = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_linked_course( $workshop_id ) : null;
		if ( $linked_course ) {
			GGM_Access_Manager::grant_course( $user_id, $linked_course->ID, 0, 'free' );
		}

		do_action( 'ggm_workshop_registered', $user_id, $workshop_id );

		$dashboard_url = get_permalink( (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 ) ) ?: home_url( '/dashboard/' );

		wp_send_json_success( array(
			'message'  => __( 'You are registered for this workshop!', 'ggm-member-dashboard' ),
			'redirect' => $dashboard_url,
		) );
	}
}
