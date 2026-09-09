<?php
/**
 * OTP Interakt WhatsApp Dispatcher.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_OTP_Interakt
 */
class GGM_OTP_Interakt {

	/**
	 * Send OTP via Interakt WhatsApp.
	 *
	 * @param string $phone
	 * @param string $otp
	 * @return bool
	 */
	public static function send( $phone, $otp ) {
		return ggm_send_whatsapp_otp( $phone, $otp );
	}
}
