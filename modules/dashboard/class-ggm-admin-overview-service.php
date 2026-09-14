<?php
/** Server-rendered aggregate data for the protected Dashboard administrator overview. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GGM_Admin_Overview_Service {
	private static function count_non_trash_posts( $types ) {
		$total = 0;
		foreach ( $types as $type ) {
			$counts = wp_count_posts( $type, 'readable' );
			if ( ! $counts ) continue;
			foreach ( get_object_vars( $counts ) as $status => $count ) {
				if ( 'trash' !== $status && 'auto-draft' !== $status ) $total += (int) $count;
			}
		}
		return $total;
	}

	/** Return non-sensitive counts only; this is intentionally not an AJAX endpoint. */
	public static function get() {
		global $wpdb;
		$members = GGM_Member_Data_Service::count_customers();
		if ( is_wp_error( $members ) ) return $members;
		$payments_table = $wpdb->prefix . 'ggm_payments';
		$successful_payments = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$payments_table} WHERE status = %s", 'success' ) );
		if ( ! empty( $wpdb->last_error ) ) return new WP_Error( 'payment_count_failed', __( 'Payment data could not be loaded.', 'ggm-member-dashboard' ) );
		return array(
			'members'             => $members,
			'workshops'           => self::count_non_trash_posts( array( 'workshop', 'ggm_workshop' ) ),
			'courses'             => self::count_non_trash_posts( array( 'course' ) ),
			'successful_payments' => $successful_payments,
		);
	}
}
