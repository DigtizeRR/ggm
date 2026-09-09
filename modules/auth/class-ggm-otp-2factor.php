<?php
/**
 * OTP 2Factor.in SMS Dispatcher.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_OTP_2Factor
 */
class GGM_OTP_2Factor {

	/**
	 * Send OTP via 2Factor SMS.
	 *
	 * @param string $phone
	 * @param string $otp
	 * @return bool
	 */
	public static function send( $phone, $otp ) {
		return ggm_send_sms_otp( $phone, $otp );
	}
}
