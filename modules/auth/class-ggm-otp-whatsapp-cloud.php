<?php
/**
 * WhatsApp Cloud API OTP dispatcher.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_OTP_WhatsApp_Cloud {

	/**
	 * Send an approved authentication-template message through Meta.
	 *
	 * @param string $phone Recipient number.
	 * @param string $otp   One-time password.
	 * @return true|WP_Error
	 */
	public static function send( $phone, $otp ) {
		$token           = ggm_get_setting( 'ggm_whatsapp_cloud_access_token', '' );
		$phone_number_id = preg_replace( '/\D/', '', ggm_get_setting( 'ggm_whatsapp_cloud_phone_number_id', '' ) );
		$template        = sanitize_text_field( ggm_get_setting( 'ggm_whatsapp_cloud_template_name', '' ) );
		$language        = sanitize_text_field( ggm_get_setting( 'ggm_whatsapp_cloud_template_language', 'en_US' ) );
		$api_version     = sanitize_text_field( ggm_get_setting( 'ggm_whatsapp_cloud_api_version', 'v23.0' ) );

		if ( ! $token || ! $phone_number_id || ! $template ) {
			ggm_log_otp_delivery_error( 'WhatsApp Cloud OTP provider not configured', array(
				'provider'    => 'whatsapp_cloud',
				'phone'       => ggm_mask_phone_number( $phone ),
				'reason'      => 'Missing credentials: access_token, phone_number_id, or template_name',
			) );
			return new WP_Error( 'cloud_api_not_configured', __( 'WhatsApp Cloud API credentials or template settings are incomplete.', 'ggm-member-dashboard' ) );
		}
		if ( ! preg_match( '/^v\d+\.\d+$/', $api_version ) ) {
			$api_version = 'v23.0';
		}

		$digits = preg_replace( '/\D/', '', $phone );
		if ( 10 === strlen( $digits ) ) {
			$digits = '91' . $digits;
		}

		$payload = array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $digits,
			'type'              => 'template',
			'template'          => array(
				'name'       => $template,
				'language'   => array( 'code' => $language ),
				'components' => array(
					array(
						'type'       => 'body',
						'parameters' => array( array( 'type' => 'text', 'text' => (string) $otp ) ),
					),
					array(
						'type'       => 'button',
						'sub_type'   => 'url',
						'index'      => '0',
						'parameters' => array( array( 'type' => 'text', 'text' => (string) $otp ) ),
					),
				),
			),
		);

		$response = wp_remote_post(
			'https://graph.facebook.com/' . rawurlencode( $api_version ) . '/' . rawurlencode( $phone_number_id ) . '/messages',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			ggm_log_otp_delivery_error( 'WhatsApp Cloud OTP delivery request failed', array(
				'provider' => 'whatsapp_cloud',
				'phone'    => ggm_mask_phone_number( $phone ),
				'error'    => $response->get_error_message(),
			) );
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status >= 200 && $status < 300 && ! empty( $body['messages'][0]['id'] ) ) {
			return true;
		}

		$message = $body['error']['message'] ?? __( 'Unknown WhatsApp Cloud API error.', 'ggm-member-dashboard' );
		ggm_log_otp_delivery_error( 'WhatsApp Cloud OTP delivery failed', array(
			'provider'      => 'whatsapp_cloud',
			'phone'         => ggm_mask_phone_number( $phone ),
			'http_status'   => $status,
			'api_message'   => sanitize_text_field( $message ),
		) );
		return new WP_Error( 'cloud_api_error', sanitize_text_field( $message ), array( 'status' => $status ) );
	}
}
