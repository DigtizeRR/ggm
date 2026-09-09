<?php
/**
 * Plugin deactivator — runs on plugin deactivation.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Deactivator
 */
class GGM_Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * Flushes rewrite rules. Tables and data are intentionally preserved;
	 * removal happens only on uninstall via uninstall.php.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();

		// Clear any scheduled cron events added by the plugin.
		wp_clear_scheduled_hook( 'ggm_expire_memberships' );
		wp_clear_scheduled_hook( 'ggm_cleanup_otp_logs' );
	}
}
