<?php
/**
 * Payment record CRUD — wraps wp_ggm_payments table.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Payment {

	private static $table = 'ggm_payments';

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . self::$table;
	}

	/**
	 * Create a new payment record (pending).
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function create( array $data ) {
		global $wpdb;
		$result = $wpdb->insert( self::table(), array(
			'user_id'             => absint( $data['user_id'] ?? get_current_user_id() ),
			'workshop_id'         => absint( $data['workshop_id'] ?? 0 ) ?: null,
			'course_id'           => absint( $data['course_id'] ?? 0 ) ?: null,
			'coupon_id'           => absint( $data['coupon_id'] ?? 0 ) ?: null,
			'original_amount'     => isset( $data['original_amount'] ) ? (float) $data['original_amount'] : null,
			'discount_amount'     => (float) ( $data['discount_amount'] ?? 0 ),
			'credit_amount'       => (float) ( $data['credit_amount'] ?? 0 ),
			'pricing_mode'        => sanitize_key( $data['pricing_mode'] ?? 'fixed' ),
			'contribution_option_id' => sanitize_text_field( $data['contribution_option_id'] ?? '' ),
			'contribution_label'  => sanitize_text_field( $data['contribution_label'] ?? '' ),
			'contribution_amount' => isset( $data['contribution_amount'] ) ? (float) $data['contribution_amount'] : null,
			'razorpay_order_id'   => sanitize_text_field( $data['razorpay_order_id'] ?? '' ),
			'amount'              => (float) ( $data['amount'] ?? 0 ),
			'currency'            => sanitize_text_field( $data['currency'] ?? 'INR' ),
			'status'              => 'pending',
			'created_at'          => current_time( 'mysql' ),
		), array( '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%s', '%s' ) );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Mark payment as successful.
	 *
	 * @param int    $id
	 * @param string $razorpay_payment_id
	 * @param string $razorpay_signature
	 * @return bool
	 */
	public static function mark_success( $id, $razorpay_payment_id, $razorpay_signature ) {
		global $wpdb;
		return (bool) $wpdb->update( self::table(), array(
			'status'               => 'success',
			'razorpay_payment_id'  => sanitize_text_field( $razorpay_payment_id ),
			'razorpay_signature'   => sanitize_text_field( $razorpay_signature ),
		), array( 'id' => absint( $id ) ), array( '%s', '%s', '%s' ), array( '%d' ) );
	}

	public static function update_order( $id, $order_id, $amount, $credit_amount ) {
		global $wpdb;
		return false !== $wpdb->update( self::table(), array(
			'razorpay_order_id' => sanitize_text_field( $order_id ),
			'amount' => (float) $amount,
			'credit_amount' => (float) $credit_amount,
		), array( 'id' => absint( $id ) ), array( '%s','%f','%f' ), array( '%d' ) );
	}

	/**
	 * Mark payment as failed.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function mark_failed( $id ) {
		global $wpdb;
		return (bool) $wpdb->update( self::table(), array( 'status' => 'failed' ), array( 'id' => absint( $id ) ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Get a payment by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Get a payment by Razorpay order ID.
	 *
	 * @param string $order_id
	 * @return object|null
	 */
	public static function get_by_order_id( $order_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . self::table() . " WHERE razorpay_order_id = %s LIMIT 1",
			sanitize_text_field( $order_id )
		) );
	}

	/**
	 * Get recent payments.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent( $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, u.display_name, u.user_email
			 FROM " . self::table() . " p
			 LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
			 ORDER BY p.created_at DESC
			 LIMIT %d",
			absint( $limit )
		) );
	}

	/**
	 * Get payments for a specific user.
	 *
	 * @param int $user_id
	 * @return array
	 */
	public static function get_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.* FROM " . self::table() . " p
			 WHERE p.user_id = %d ORDER BY p.created_at DESC",
			absint( $user_id )
		) );
	}
}
