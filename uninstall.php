<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all custom database tables, options, and plugin data.
 *
 * @package GGM_Member_Dashboard
 */

// Exit if accessed directly or not an uninstall request.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ─── Drop custom tables ───────────────────────────────────────────────────────
$tables = array(
	$wpdb->prefix . 'ggm_memberships',
	$wpdb->prefix . 'ggm_user_memberships',
	$wpdb->prefix . 'ggm_payments',
	$wpdb->prefix . 'ggm_credit_transactions',
	$wpdb->prefix . 'ggm_otp_logs',
	$wpdb->prefix . 'ggm_workshop_access',
	$wpdb->prefix . 'ggm_coupons',
	$wpdb->prefix . 'ggm_coupon_usage',
	$wpdb->prefix . 'ggm_workshop_slots',
	$wpdb->prefix . 'ggm_course_access',
	$wpdb->prefix . 'ggm_forms',
	$wpdb->prefix . 'ggm_form_versions',
	$wpdb->prefix . 'ggm_form_assignments',
	$wpdb->prefix . 'ggm_form_submissions',
	$wpdb->prefix . 'ggm_form_files',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// ─── Delete plugin options ────────────────────────────────────────────────────
$options = array(
	'ggm_db_version',
	'ggm_razorpay_key_id',
	'ggm_razorpay_key_secret',
	'ggm_razorpay_mode',
	'ggm_otp_provider',
	'ggm_msg91_api_key',
	'ggm_msg91_template_id',
	'ggm_msg91_sender_id',
	'ggm_interakt_api_key',
	'ggm_twilio_sid',
	'ggm_twilio_token',
	'ggm_twilio_from',
	'ggm_auth_methods',
	'ggm_otp_expiry',
	'ggm_otp_length',
	'ggm_otp_rate_limit',
	'ggm_dashboard_page_id',
	'ggm_login_page_id',
	'ggm_checkout_page_id',
	'ggm_general_settings',
	'ggm_email_from_name',
	'ggm_email_from_address',
	'ggm_whatsapp_notifications',
	'ggm_email_notifications',
	'ggm_currency',
	'ggm_currency_symbol',
	'ggm_settings',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// ─── Delete user meta ─────────────────────────────────────────────────────────
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ggm_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// ─── Delete post meta for custom CPTs ────────────────────────────────────────
$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN ('ggm_workshop','ggm_lesson','ggm_product')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// ─── Delete custom posts ───────────────────────────────────────────────────────
$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ('ggm_workshop','ggm_lesson','ggm_product')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
