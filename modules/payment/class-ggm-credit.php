<?php
/** Customer credit ledger and checkout reservations. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GGM_Credit {
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ggm_credit_transactions';
	}

	/** Spendable balance, including active checkout reservations. */
	public static function get_balance( $user_id ) {
		global $wpdb;
		self::release_expired( $user_id );
		return max( 0.0, (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount),0) FROM " . self::table() . " WHERE user_id=%d AND status IN ('available','applied','reserved')",
			absint( $user_id )
		) ) );
	}

	public static function add( $user_id, $amount, $admin_user_id, $note = '' ) {
		global $wpdb;
		$amount = round( (float) $amount, 2 );
		if ( $amount <= 0 || ! get_userdata( $user_id ) ) { return false; }
		return (bool) $wpdb->insert( self::table(), array(
			'user_id' => absint( $user_id ), 'amount' => $amount, 'type' => 'admin_credit',
			'status' => 'available', 'admin_user_id' => absint( $admin_user_id ),
			'note' => sanitize_text_field( $note ), 'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		), array( '%d','%f','%s','%s','%d','%s','%s','%s' ) );
	}

	/** Reserve up to $maximum and return the reserved positive amount. */
	public static function reserve( $user_id, $maximum, $payment_id ) {
		global $wpdb;
		$maximum = round( max( 0, (float) $maximum ), 2 );
		if ( $maximum <= 0 ) { return 0.0; }
		$wpdb->query( 'START TRANSACTION' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, amount, status FROM " . self::table() . " WHERE user_id=%d AND status IN ('available','applied','reserved') FOR UPDATE",
			absint( $user_id )
		) );
		$balance = 0.0;
		foreach ( $rows as $row ) { $balance += (float) $row->amount; }
		$use = round( min( max( 0, $balance ), $maximum ), 2 );
		$ok = true;
		if ( $use > 0 ) {
			$ok = (bool) $wpdb->insert( self::table(), array(
				'user_id' => absint( $user_id ), 'amount' => -$use, 'type' => 'purchase',
				'status' => 'reserved', 'payment_id' => absint( $payment_id ),
				'note' => 'Checkout credit reservation', 'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			), array( '%d','%f','%s','%s','%d','%s','%s','%s' ) );
		}
		$wpdb->query( $ok ? 'COMMIT' : 'ROLLBACK' );
		return $ok ? $use : 0.0;
	}

	public static function apply( $payment_id ) { return self::set_payment_status( $payment_id, 'applied' ); }
	public static function release( $payment_id ) { return self::set_payment_status( $payment_id, 'released' ); }
	public static function restore_for_refund( $payment_id ) {
		global $wpdb;
		return false !== $wpdb->update( self::table(), array( 'status' => 'released', 'updated_at' => current_time( 'mysql' ), 'note' => 'Credit restored after refund' ), array( 'payment_id' => absint( $payment_id ), 'status' => 'applied' ), array( '%s','%s','%s' ), array( '%d','%s' ) );
	}
	private static function set_payment_status( $payment_id, $status ) {
		global $wpdb;
		return false !== $wpdb->update( self::table(), array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ), array( 'payment_id' => absint( $payment_id ), 'status' => 'reserved' ), array( '%s','%s' ), array( '%d','%s' ) );
	}

	public static function release_expired( $user_id = 0 ) {
		global $wpdb;
		$sql = "UPDATE " . self::table() . " SET status='released', updated_at=%s WHERE status='reserved' AND created_at < %s";
		$args = array( current_time( 'mysql' ), date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - HOUR_IN_SECONDS ) );
		if ( $user_id ) { $sql .= ' AND user_id=%d'; $args[] = absint( $user_id ); }
		$wpdb->query( $wpdb->prepare( $sql, $args ) );
	}

	public static function get_recent( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT t.*,u.display_name,u.user_email,a.display_name admin_name FROM " . self::table() . " t LEFT JOIN {$wpdb->users} u ON u.ID=t.user_id LEFT JOIN {$wpdb->users} a ON a.ID=t.admin_user_id ORDER BY t.id DESC LIMIT %d", absint( $limit ) ) );
	}
}
