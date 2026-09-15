<?php
/**
 * Source-level checks for privacy-safe OTP mail observability.
 * Run: php scripts/test-otp-mail-observability.php
 */

$root    = dirname( __DIR__ );
$helpers = file_get_contents( $root . '/includes/class-ggm-helpers.php' );
$admin   = file_get_contents( $root . '/admin/class-ggm-admin.php' );
$view    = file_get_contents( $root . '/admin/views/partials/settings-error-log.php' );

$checks = array(
	'OTP enables the redacted SMTP diagnostic capture' => false !== strpos( $helpers, '$GLOBALS[\'ggm_smtp_debug_active\'] = true;' ),
	'OTP log includes safe local SMTP stages'          => false !== strpos( $helpers, "'local_smtp_data'" ),
	'Raw transcript sample is removed before logging'  => false !== strpos( $helpers, 'unset( $summary[\'transcript_sample\'] );' ),
	'Outcome endpoint requires administrator capability'=> false !== strpos( $admin, "function ajax_record_mail_delivery_outcome()" ) && false !== strpos( $admin, "current_user_can( 'manage_options' )" ),
	'Outcome endpoint validates delivery status'        => false !== strpos( $admin, "array( 'delivered', 'deferred', 'bounced', 'rejected' )" ),
	'Outcome endpoint records source provenance'        => false !== strpos( $admin, "administrator-recorded cPanel/provider result" ),
	'Error Log provides outcome attachment controls'    => false !== strpos( $view, 'ggm-record-mail-outcome-btn' ),
);

$failed = 0;
foreach ( $checks as $label => $passed ) {
	echo ( $passed ? 'PASS' : 'FAIL' ) . ': ' . $label . PHP_EOL;
	$failed += $passed ? 0 : 1;
}

exit( $failed ? 1 : 0 );
