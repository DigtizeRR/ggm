<?php
/**
 * OTP Wahob WhatsApp Dispatcher.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_OTP_Wahob
 */
class GGM_OTP_Wahob {

	/**
	 * Send OTP via Wahob WhatsApp.
	 *
	 * @param string $phone 10-digit number (no country code).
	 * @param string $otp
	 * @return bool
	 */
	public static function send( $phone, $otp ) {
		$mode = ggm_get_setting( 'ggm_wahob_mode', 'test' );

		if ( 'live' === $mode ) {
			$api_key    = ggm_get_setting( 'ggm_wahob_live_api_key', '' );
			$api_secret = ggm_get_setting( 'ggm_wahob_live_api_secret', '' );
		} else {
			$api_key    = ggm_get_setting( 'ggm_wahob_test_api_key', '' );
			$api_secret = ggm_get_setting( 'ggm_wahob_test_api_secret', '' );
		}

		if ( empty( $api_key ) || empty( $api_secret ) ) {
			ggm_log_otp_delivery_error( 'Wahob OTP provider not configured', array(
				'provider' => 'wahob',
				'phone'    => ggm_mask_phone_number( $phone ),
				'reason'   => 'Missing API key or secret for mode: ' . $mode,
			) );
			return false;
		}

		$phone    = preg_replace( '/^(\+91|91|0)/', '', $phone );
		$phone    = preg_replace( '/\D/', '', $phone );
		$full_num = '+91' . $phone;

		$message = sprintf(
			/* translators: %s: OTP code */
			__( 'Your OTP is: %s. Valid for 10 minutes. Do not share with anyone.', 'ggm-member-dashboard' ),
			$otp
		);

		$response = wp_remote_post( 'https://api.wahob.com/api/v1/messages/send', array(
			'timeout' => 15,
			'headers' => array(
				'X-Api-Key'    => $api_key,
				'X-Api-Secret' => $api_secret,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'phone'   => $full_num,
				'message' => $message,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			ggm_log_otp_delivery_error( 'Wahob OTP delivery request failed', array(
				'provider' => 'wahob',
				'phone'    => ggm_mask_phone_number( $phone ),
				'error'    => $response->get_error_message(),
			) );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$sent = ( 200 === (int) $code ) && isset( $body['status'] ) && 1 === (int) $body['status'];

		if ( ! $sent ) {
			ggm_log_otp_delivery_error( 'Wahob OTP delivery failed', array(
				'provider'      => 'wahob',
				'phone'         => ggm_mask_phone_number( $phone ),
				'http_status'   => $code,
				'api_status'    => sanitize_text_field( (string) ( $body['status'] ?? '' ) ),
				'api_message'   => sanitize_text_field( (string) ( $body['message'] ?? '' ) ),
			) );
		}

		return $sent;
	}
}
