<?php
/**
 * WP REST API endpoints — namespace: ggm/v1
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_REST_API {

	const NAMESPACE = 'ggm/v1';

	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	public function register_routes() {
		// Auth
		register_rest_route( self::NAMESPACE, '/auth/send-otp', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'send_otp' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'identifier' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/auth/verify-otp', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'verify_otp' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'identifier' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'otp'        => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'request_id' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/auth/login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'password_login' ),
			'permission_callback' => '__return_true',
		) );

		// Workshops
		register_rest_route( self::NAMESPACE, '/workshops', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_workshops' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/workshops/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_workshop' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array( 'validate_callback' => function( $v ) { return is_numeric( $v ); } ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/lessons/(?P<workshop_id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_lessons' ),
			'permission_callback' => '__return_true',
		) );

		// Payment
		register_rest_route( self::NAMESPACE, '/payment/create-order', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_order' ),
			'permission_callback' => array( $this, 'require_login' ),
		) );

		register_rest_route( self::NAMESPACE, '/payment/verify', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'verify_payment' ),
			'permission_callback' => array( $this, 'require_login' ),
		) );

		// Profile
		register_rest_route( self::NAMESPACE, '/profile', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_profile' ),
			'permission_callback' => array( $this, 'require_login' ),
		) );

		register_rest_route( self::NAMESPACE, '/profile/update', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_profile' ),
			'permission_callback' => array( $this, 'require_login' ),
		) );
	}

	public function require_login() {
		return is_user_logged_in();
	}

	// ─── Auth ─────────────────────────────────────────────────────────────────

	public function send_otp( WP_REST_Request $req ) {
		$result = GGM_OTP::send( $req->get_param( 'identifier' ) );
		if ( $result['success'] ) {
			return rest_ensure_response( $result );
		}
		return new WP_Error( 'otp_error', $result['message'], array( 'status' => 400 ) );
	}

	public function verify_otp( WP_REST_Request $req ) {
		$result = GGM_OTP::verify( $req->get_param( 'identifier' ), $req->get_param( 'otp' ), $req->get_param( 'request_id' ) );
		if ( ! $result['success'] ) {
			return new WP_Error( 'otp_error', __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ), array( 'status' => 400 ) );
		}

		$user = get_user_by( 'id', (int) $result['user_id'] );
		if ( ! $user ) {
			return new WP_Error( 'otp_error', __( 'Invalid or expired OTP. Please try again.', 'ggm-member-dashboard' ), array( 'status' => 400 ) );
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		return rest_ensure_response( array(
			'success'       => true,
			'authenticated' => true,
			'user_id'       => $user->ID,
			'message'       => $result['message'],
		) );
	}

	public function password_login( WP_REST_Request $req ) {
		$email    = sanitize_email( $req->get_param( 'email' ) );
		$password = $req->get_param( 'password' );
		$user     = wp_signon( array( 'user_login' => $email, 'user_password' => $password, 'remember' => true ), false );
		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'login_error', $user->get_error_message(), array( 'status' => 401 ) );
		}
		return rest_ensure_response( array( 'success' => true, 'user_id' => $user->ID ) );
	}

	// ─── Workshops ────────────────────────────────────────────────────────────

	public function get_workshops( WP_REST_Request $req ) {
		$posts = get_posts( array(
			'post_type'      => array( 'workshop', 'ggm_workshop' ),
			'post_status'    => 'publish',
			'posts_per_page' => absint( $req->get_param( 'per_page' ) ?: 20 ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		$data = array_map( array( $this, 'format_workshop' ), $posts );
		return rest_ensure_response( $data );
	}

	public function get_workshop( WP_REST_Request $req ) {
		$post = get_post( absint( $req->get_param( 'id' ) ) );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'not_found', __( 'Workshop not found.', 'ggm-member-dashboard' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->format_workshop( $post ) );
	}

	public function get_lessons( WP_REST_Request $req ) {
		$lessons = GGM_Lesson::get_for_course( absint( $req->get_param( 'workshop_id' ) ) );
		$data    = array_map( function( $l ) {
			return array(
				'id'       => $l->ID,
				'title'    => $l->post_title,
				'order'    => (int) get_post_meta( $l->ID, 'lesson_order', true ),
				'duration' => get_post_meta( $l->ID, 'lesson_duration', true ),
				'preview'  => GGM_Lesson::is_preview( $l->ID ),
			);
		}, $lessons );
		return rest_ensure_response( $data );
	}

	// ─── Payment ─────────────────────────────────────────────────────────────

	public function create_order( WP_REST_Request $req ) {
		$workshop_id = absint( $req->get_param( 'workshop_id' ) );
		$contribution_option_id = sanitize_text_field( $req->get_param( 'contribution_option_id' ) );
		$amount      = 0;
		$price_snapshot = array( 'mode'=>'fixed','option_id'=>'','label'=>'','amount'=>0,'valid'=>true );
		$currency    = ggm_get_setting( 'ggm_currency', 'INR' );

		if ( $workshop_id ) {
			if ( class_exists( 'GGM_Workshop' ) ) {
				$price_snapshot = GGM_Workshop::resolve_purchase_price( $workshop_id, $contribution_option_id );
				if ( empty( $price_snapshot['valid'] ) ) { return new WP_Error( 'invalid_contribution', $price_snapshot['message'], array( 'status'=>400 ) ); }
				$amount = (float) $price_snapshot['amount'];
			} else { $amount = (float) get_post_meta( $workshop_id, 'workshop_money', true ); }
		}

		$is_free_contribution = 'contribution' === $price_snapshot['mode'] && ! empty( $price_snapshot['is_free'] );
		if (
			$workshop_id
			&& class_exists( 'GGM_Workshop' )
			&& ( 'contribution' === $price_snapshot['mode'] || $amount > 0 )
			&& GGM_Workshop::user_has_access( get_current_user_id(), $workshop_id )
		) {
			return new WP_Error(
				'already_purchased',
				__( 'You already have access to this workshop.', 'ggm-member-dashboard' ),
				array( 'status' => 409 )
			);
		}
		if ( $amount <= 0 && ! $is_free_contribution ) {
			return new WP_Error( 'invalid_amount', __( 'Invalid amount.', 'ggm-member-dashboard' ), array( 'status' => 400 ) );
		}

		if ( $is_free_contribution ) {
			$payment_id = GGM_Payment::create( array(
				'user_id'           => get_current_user_id(),
				'workshop_id'       => $workshop_id,
				'razorpay_order_id' => '',
				'original_amount'   => 0,
				'amount'            => 0,
				'currency'          => $currency,
				'pricing_mode'      => 'contribution',
				'contribution_option_id' => $price_snapshot['option_id'],
				'contribution_label' => $price_snapshot['label'],
				'contribution_amount' => 0,
			) );
			if ( ! $payment_id ) {
				return new WP_Error( 'payment_record_failed', __( 'Could not create enrollment record.', 'ggm-member-dashboard' ), array( 'status' => 500 ) );
			}
			GGM_Payment::mark_success( $payment_id, '', '' );
			GGM_Access_Manager::grant_workshop_bundle( get_current_user_id(), $workshop_id, $payment_id );
			return rest_ensure_response( array(
				'free'       => true,
				'payment_id' => $payment_id,
				'redirect'   => get_permalink( (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 ) ) ?: home_url( '/dashboard/' ),
			) );
		}

		$rp      = new GGM_Razorpay();
		$order   = $rp->create_order( $amount, $currency );
		if ( is_wp_error( $order ) ) {
			return new WP_Error( 'razorpay_error', $order->get_error_message(), array( 'status' => 500 ) );
		}

		$payment_id = GGM_Payment::create( array(
			'user_id'           => get_current_user_id(),
			'workshop_id'       => $workshop_id ?: null,
			'razorpay_order_id' => $order['id'],
			'amount'            => $amount,
			'currency'          => $currency,
			'pricing_mode'      => $price_snapshot['mode'],
			'contribution_option_id' => $price_snapshot['option_id'],
			'contribution_label' => $price_snapshot['label'],
			'contribution_amount' => 'contribution' === $price_snapshot['mode'] ? $amount : null,
		) );

		return rest_ensure_response( array(
			'order_id'   => $order['id'],
			'amount'     => $order['amount'],
			'currency'   => $order['currency'],
			'payment_id' => $payment_id,
		) );
	}

	public function verify_payment( WP_REST_Request $req ) {
		$rp = new GGM_Razorpay();

		$order_id   = sanitize_text_field( $req->get_param( 'razorpay_order_id' ) );
		$payment_id = sanitize_text_field( $req->get_param( 'razorpay_payment_id' ) );
		$signature  = sanitize_text_field( $req->get_param( 'razorpay_signature' ) );
		$rec_id     = absint( $req->get_param( 'payment_id' ) );
		$payment    = GGM_Payment::get( $rec_id );
		if ( ! $payment || (int) $payment->user_id !== get_current_user_id() || ! hash_equals( (string) $payment->razorpay_order_id, (string) $order_id ) || 'pending' !== $payment->status ) {
			return new WP_Error( 'invalid_payment', __( 'This payment cannot be verified.', 'ggm-member-dashboard' ), array( 'status'=>403 ) );
		}

		if ( ! $rp->verify_signature( $order_id, $payment_id, $signature ) ) {
			GGM_Payment::mark_failed( $rec_id );
			return new WP_Error( 'invalid_signature', __( 'Signature verification failed.', 'ggm-member-dashboard' ), array( 'status' => 400 ) );
		}

		GGM_Payment::mark_success( $rec_id, $payment_id, $signature );

		$payment = GGM_Payment::get( $rec_id );
		if ( $payment && class_exists( 'GGM_Workshop' ) && ! empty( $payment->workshop_id ) ) {
			GGM_Access_Manager::grant_workshop_bundle( $payment->user_id, $payment->workshop_id, $rec_id );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	// ─── Profile ──────────────────────────────────────────────────────────────

	public function get_profile() {
		$user = wp_get_current_user();
		return rest_ensure_response( array(
			'id'           => $user->ID,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'phone'        => ggm_get_member_phone( $user->ID ),
			'country_code' => get_user_meta( $user->ID, 'ggm_whatsapp_country_code', true ) ?: '+91',
		) );
	}

	public function update_profile( WP_REST_Request $req ) {
		$user_id      = get_current_user_id();
		$first_name   = sanitize_text_field( $req->get_param( 'first_name' ) ?? '' );
		$last_name    = sanitize_text_field( $req->get_param( 'last_name' ) ?? '' );
		$phone        = sanitize_text_field( $req->get_param( 'phone' ) ?? '' );
		$country_code = sanitize_text_field( $req->get_param( 'country_code' ) ?? '' );
		$clean_phone  = '' !== $phone ? ggm_normalize_member_phone( $phone, $country_code ?: '+91' ) : '';
		if ( '' !== $phone && '' === $clean_phone ) {
			return new WP_Error( 'ggm_invalid_phone', __( 'Please enter a valid phone number for the selected country.', 'ggm-member-dashboard' ), array( 'status' => 400 ) );
		}

		wp_update_user( array(
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
		) );

		if ( $phone ) {
			update_user_meta( $user_id, 'ggm_phone', $clean_phone );
			update_user_meta( $user_id, 'billing_phone', $clean_phone );
		}

		if ( $country_code ) {
			update_user_meta( $user_id, 'ggm_whatsapp_country_code', $country_code );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private function format_workshop( WP_Post $post ) {
		$has_ggm_workshop = class_exists( 'GGM_Workshop' );
		$linked_course     = $has_ggm_workshop ? GGM_Workshop::get_linked_course( $post->ID ) : null;

		return array(
			'id'                => $post->ID,
			'title'             => $post->post_title,
			'excerpt'           => get_the_excerpt( $post ),
			'thumbnail'         => get_the_post_thumbnail_url( $post, 'medium' ),
			'is_free'           => ! ( $has_ggm_workshop && GGM_Workshop::is_contribution( $post->ID ) ) && '1' === get_post_meta( $post->ID, 'is_free', true ),
			'currency'          => $has_ggm_workshop ? GGM_Workshop::get_currency( $post->ID ) : '₹',
			'regular_price'     => $has_ggm_workshop ? GGM_Workshop::get_regular_price( $post->ID ) : get_post_meta( $post->ID, 'workshop_money', true ),
			'sale_price'        => $has_ggm_workshop ? GGM_Workshop::get_sale_price( $post->ID ) : '',
			'price'             => $has_ggm_workshop ? GGM_Workshop::resolve_price( $post->ID ) : get_post_meta( $post->ID, 'workshop_money', true ),
			'start_date'        => get_post_meta( $post->ID, 'workshop_start_date', true ) ?: get_post_meta( $post->ID, 'workshop_date', true ),
			'end_date'          => get_post_meta( $post->ID, 'workshop_end_date', true ),
			'is_expired'        => $has_ggm_workshop ? GGM_Workshop::is_expired( $post->ID ) : false,
			'mode'              => get_post_meta( $post->ID, 'workshop_mode', true ) ?: 'Zoom',
			'language'          => get_post_meta( $post->ID, 'workshop_language', true ) ?: 'english',
			'linked_course'     => $linked_course ? array(
				'id'        => $linked_course->ID,
				'title'     => $linked_course->post_title,
				'permalink' => get_permalink( $linked_course->ID ),
			) : null,
			'permalink'         => get_permalink( $post->ID ),
		);
	}
}
