<?php
/**
 * OTP Provider — MSG91 (SMS & WhatsApp).
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_OTP_MSG91 {

	/**
	 * Send SMS OTP via MSG91.
	 *
	 * @param string $phone 10-digit number without country code.
	 * @param string $otp
	 * @return bool
	 */
	public static function send_sms( $phone, $otp ) {
		$settings  = get_option( 'ggm_settings', array() );
		$auth_key  = $settings['ggm_msg91_auth_key'] ?? '';
		$template  = $settings['ggm_msg91_template_id'] ?? '';

		if ( empty( $auth_key ) ) {
			ggm_log_otp_delivery_error( 'MSG91 OTP provider not configured', array(
				'provider' => 'msg91',
				'phone'    => ggm_mask_phone_number( $phone ),
				'reason'   => 'Missing auth key (ggm_msg91_auth_key)',
			) );
			return false;
		}

		$response = wp_remote_post( 'https://api.msg91.com/api/v5/otp', array(
			'timeout' => 10,
			'headers' => array(
				'authkey'      => $auth_key,
				'Content-Type' => 'application/json',
				'accept'       => 'application/json',
			),
			'body' => wp_json_encode( array(
				'template_id' => $template,
				'mobile'      => '91' . $phone,
				'otp'         => $otp,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			ggm_log_otp_delivery_error( 'MSG91 OTP delivery request failed', array(
				'provider' => 'msg91',
				'phone'    => ggm_mask_phone_number( $phone ),
				'error'    => $response->get_error_message(),
			) );
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$sent   = isset( $body['type'] ) && 'success' === $body['type'];

		if ( ! $sent ) {
			ggm_log_otp_delivery_error( 'MSG91 OTP delivery failed', array(
				'provider'      => 'msg91',
				'phone'         => ggm_mask_phone_number( $phone ),
				'http_status'   => $status,
				'api_type'      => sanitize_text_field( (string) ( $body['type'] ?? '' ) ),
				'api_message'   => sanitize_text_field( (string) ( $body['message'] ?? '' ) ),
			) );
		}

		return $sent;
	}

	/**
	 * Resend OTP via MSG91.
	 *
	 * @param string $phone
	 * @return bool
	 */
	public static function resend( $phone ) {
		$settings = get_option( 'ggm_settings', array() );
		$auth_key = $settings['ggm_msg91_auth_key'] ?? '';

		if ( empty( $auth_key ) ) {
			ggm_log_otp_delivery_error( 'MSG91 OTP resend: provider not configured', array(
				'provider' => 'msg91',
				'phone'    => ggm_mask_phone_number( $phone ),
				'reason'   => 'Missing auth key (ggm_msg91_auth_key)',
			) );
			return false;
		}

		$url      = add_query_arg( array(
			'authkey' => $auth_key,
			'mobile'  => '91' . $phone,
			'retrytype' => 'text',
		), 'https://api.msg91.com/api/v5/otp/retry' );

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		$sent = ! is_wp_error( $response );

		if ( ! $sent ) {
			ggm_log_otp_delivery_error( 'MSG91 OTP resend request failed', array(
				'provider' => 'msg91',
				'phone'    => ggm_mask_phone_number( $phone ),
				'error'    => is_wp_error( $response ) ? $response->get_error_message() : 'Unknown error',
			) );
		}

		return $sent;
	}
}
