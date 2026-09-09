<?php
/**
 * OTP Email Dispatcher.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_OTP_Email
 */
class GGM_OTP_Email {

	/**
	 * Send OTP via email.
	 *
	 * @param string $email
	 * @param string $otp
	 * @return bool
	 */
	public static function send( $email, $otp ) {
		return ggm_send_email_otp( $email, $otp );
	}
}
