<?php
/**
 * OTP Provider — Twilio (SMS).
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_OTP_Twilio {

	/**
	 * Send SMS OTP via Twilio.
	 *
	 * @param string $phone Full phone number with country code (e.g. +919876543210).
	 * @param string $otp
	 * @return bool
	 */
	public static function send_sms( $phone, $otp ) {
		$settings   = get_option( 'ggm_settings', array() );
		$account_sid = $settings['ggm_twilio_account_sid'] ?? '';
		$auth_token  = $settings['ggm_twilio_auth_token'] ?? '';
		$from        = $settings['ggm_twilio_from'] ?? '';

		if ( empty( $account_sid ) || empty( $auth_token ) || empty( $from ) ) {
			ggm_log_otp_delivery_error( 'Twilio OTP provider not configured', array(
				'provider'  => 'twilio',
				'phone'     => ggm_mask_phone_number( $phone ),
				'reason'    => 'Missing credentials: account_sid, auth_token, or from number',
			) );
			return false;
		}

		$message  = sprintf( __( 'Your GGM OTP is: %s. Valid for 10 minutes.', 'ggm-member-dashboard' ), $otp );
		$endpoint = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

		$response = wp_remote_post( $endpoint, array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
			),
			'body' => array(
				'From' => $from,
				'To'   => $phone,
				'Body' => $message,
			),
		) );

		if ( is_wp_error( $response ) ) {
			ggm_log_otp_delivery_error( 'Twilio OTP delivery request failed', array(
				'provider' => 'twilio',
				'phone'    => ggm_mask_phone_number( $phone ),
				'error'    => $response->get_error_message(),
			) );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$sent = ( $code >= 200 && $code < 300 );

		if ( ! $sent ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			ggm_log_otp_delivery_error( 'Twilio OTP delivery failed', array(
				'provider'      => 'twilio',
				'phone'         => ggm_mask_phone_number( $phone ),
				'http_status'   => $code,
				'api_message'   => sanitize_text_field( (string) ( $body['message'] ?? '' ) ),
				'api_code'      => sanitize_text_field( (string) ( $body['code'] ?? '' ) ),
			) );
		}

		return $sent;
	}
}
