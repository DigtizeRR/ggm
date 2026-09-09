<?php
/**
 * Coupon CRUD + validation — wraps wp_ggm_coupons / wp_ggm_coupon_usage tables.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Coupon {

	/** @var string */
	private static $table = 'ggm_coupons';

	/** @var string */
	private static $usage_table = 'ggm_coupon_usage';

	/**
	 * Get table name with prefix.
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . self::$table;
	}

	/**
	 * Get usage table name with prefix.
	 */
	private static function usage_table() {
		global $wpdb;
		return $wpdb->prefix . self::$usage_table;
	}

	/**
	 * Get all coupons.
	 *
	 * @param string $status 'active', 'inactive', or 'all'.
	 * @return array
	 */
	public static function get_all( $status = 'all' ) {
		global $wpdb;
		$table = self::table();
		if ( 'all' === $status ) {
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
		}
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC",
			$status
		) );
	}

	/**
	 * Get a single coupon by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			absint( $id )
		) );
	}

	/**
	 * Get a single coupon by code (case-insensitive).
	 *
	 * @param string $code
	 * @return object|null
	 */
	public static function get_by_code( $code ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE code = %s',
			strtoupper( sanitize_text_field( $code ) )
		) );
	}

	/**
	 * Create a new coupon.
	 *
	 * @param array $data
	 * @return int|false New ID or false.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$fields = self::prepare_fields( $data );

		if ( empty( $fields['code'] ) ) {
			return false;
		}

		$fields['used_count']  = 0;
		$fields['created_at']  = current_time( 'mysql' );

		$result = $wpdb->insert( self::table(), $fields );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing coupon.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$fields = self::prepare_fields( $data );

		if ( empty( $fields ) ) {
			return false;
		}

		// $wpdb->update() returns the number of rows *changed*, not whether
		// the query succeeded — it's 0 (falsy) whenever the submitted values
		// are identical to what's already stored (e.g. re-saving a coupon
		// without changing anything), which incorrectly read as "failed".
		// Only an actual query error returns false.
		$result = $wpdb->update( self::table(), $fields, array( 'id' => absint( $id ) ) );

		return false !== $result;
	}

	/**
	 * Delete a coupon.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Sanitize/normalize submitted coupon fields for insert/update.
	 *
	 * @param array $data
	 * @return array
	 */
	private static function prepare_fields( array $data ) {
		$fields = array();

		if ( isset( $data['code'] ) ) {
			$fields['code'] = strtoupper( sanitize_text_field( $data['code'] ) );
		}
		if ( isset( $data['name'] ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
		}
		if ( array_key_exists( 'description', $data ) ) {
			$fields['description'] = sanitize_textarea_field( $data['description'] );
		}
		if ( isset( $data['discount_type'] ) ) {
			$fields['discount_type'] = in_array( $data['discount_type'], array( 'fixed', 'percentage' ), true )
				? $data['discount_type']
				: 'fixed';
		}
		if ( isset( $data['discount_value'] ) ) {
			$fields['discount_value'] = max( 0, (float) $data['discount_value'] );
		}
		if ( array_key_exists( 'max_discount', $data ) ) {
			$fields['max_discount'] = ( '' === $data['max_discount'] || null === $data['max_discount'] ) ? null : max( 0, (float) $data['max_discount'] );
		}
		if ( array_key_exists( 'min_purchase', $data ) ) {
			$fields['min_purchase'] = ( '' === $data['min_purchase'] || null === $data['min_purchase'] ) ? null : max( 0, (float) $data['min_purchase'] );
		}
		if ( array_key_exists( 'max_purchase', $data ) ) {
			$fields['max_purchase'] = ( '' === $data['max_purchase'] || null === $data['max_purchase'] ) ? null : max( 0, (float) $data['max_purchase'] );
		}
		if ( array_key_exists( 'start_date', $data ) ) {
			$fields['start_date'] = ! empty( $data['start_date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['start_date'] ) ) : null;
		}
		if ( array_key_exists( 'expiry_date', $data ) ) {
			$fields['expiry_date'] = ! empty( $data['expiry_date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['expiry_date'] ) ) : null;
		}
		if ( array_key_exists( 'max_uses', $data ) ) {
			$fields['max_uses'] = ( '' === $data['max_uses'] || null === $data['max_uses'] ) ? null : absint( $data['max_uses'] );
		}
		if ( array_key_exists( 'max_uses_per_user', $data ) ) {
			$fields['max_uses_per_user'] = ( '' === $data['max_uses_per_user'] || null === $data['max_uses_per_user'] ) ? null : absint( $data['max_uses_per_user'] );
		}
		if ( isset( $data['applicable_workshops'] ) ) {
			$fields['applicable_workshops'] = wp_json_encode( array_map( 'absint', (array) $data['applicable_workshops'] ) );
		}
		if ( isset( $data['status'] ) ) {
			$fields['status'] = in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : 'active';
		}

		return $fields;
	}

	/**
	 * Validate a coupon code against a purchase context and calculate the discount.
	 *
	 * All checks are server-side authoritative — never trust a client-supplied discount.
	 *
	 * @param string $code
	 * @param array  $context {
	 *     @type int    $user_id
	 *     @type string $type     'membership' or 'workshop'
	 *     @type int    $item_id  Membership or workshop ID.
	 *     @type float  $amount   Original price before discount.
	 * }
	 * @return array {
	 *     @type bool   $valid
	 *     @type string $message
	 *     @type int    $coupon_id
	 *     @type float  $original_amount
	 *     @type float  $discount_amount
	 *     @type float  $final_amount
	 * }
	 */
	public static function validate_and_calculate( $code, array $context ) {
		$user_id = absint( $context['user_id'] ?? 0 );
		$type    = in_array( $context['type'] ?? '', array( 'workshop', 'course' ), true ) ? $context['type'] : '';
		$item_id = absint( $context['item_id'] ?? 0 );
		$amount  = max( 0, (float) ( $context['amount'] ?? 0 ) );

		$fail = function ( $message ) use ( $amount ) {
			return array(
				'valid'           => false,
				'message'         => $message,
				'coupon_id'       => 0,
				'original_amount' => $amount,
				'discount_amount' => 0.0,
				'final_amount'    => $amount,
			);
		};

		$coupon = self::get_by_code( $code );
		if ( ! $coupon ) {
			return $fail( __( 'Invalid coupon code.', 'ggm-member-dashboard' ) );
		}

		if ( 'active' !== $coupon->status ) {
			return $fail( __( 'This coupon is no longer active.', 'ggm-member-dashboard' ) );
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		if ( ! empty( $coupon->start_date ) && strtotime( $coupon->start_date ) > $now ) {
			return $fail( __( 'This coupon is not active yet.', 'ggm-member-dashboard' ) );
		}

		if ( ! empty( $coupon->expiry_date ) && strtotime( $coupon->expiry_date ) < $now ) {
			return $fail( __( 'This coupon has expired.', 'ggm-member-dashboard' ) );
		}

		if ( null !== $coupon->max_uses && (int) $coupon->used_count >= (int) $coupon->max_uses ) {
			return $fail( __( 'This coupon has reached its usage limit.', 'ggm-member-dashboard' ) );
		}

		if ( $user_id && null !== $coupon->max_uses_per_user ) {
			global $wpdb;
			$user_uses = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::usage_table() . ' WHERE coupon_id = %d AND user_id = %d',
				$coupon->id,
				$user_id
			) );
			if ( $user_uses >= (int) $coupon->max_uses_per_user ) {
				return $fail( __( 'You have already used this coupon the maximum number of times.', 'ggm-member-dashboard' ) );
			}
		}

		// Applicability (workshops only — coupons aren't restricted per-course).
		if ( 'workshop' === $type ) {
			$allowed = json_decode( $coupon->applicable_workshops ?? '[]', true );
			if ( ! empty( $allowed ) && ! in_array( $item_id, array_map( 'intval', $allowed ), true ) ) {
				return $fail( __( 'This coupon is not applicable to this workshop.', 'ggm-member-dashboard' ) );
			}
		}

		if ( null !== $coupon->min_purchase && $amount < (float) $coupon->min_purchase ) {
			return $fail( sprintf(
				/* translators: %s: minimum purchase amount */
				__( 'A minimum purchase of %s is required to use this coupon.', 'ggm-member-dashboard' ),
				number_format( (float) $coupon->min_purchase, 2 )
			) );
		}

		if ( null !== $coupon->max_purchase && $amount > (float) $coupon->max_purchase ) {
			return $fail( sprintf(
				/* translators: %s: maximum purchase amount */
				__( 'This coupon can only be used on purchases up to %s.', 'ggm-member-dashboard' ),
				number_format( (float) $coupon->max_purchase, 2 )
			) );
		}

		// Calculate discount.
		if ( 'percentage' === $coupon->discount_type ) {
			$discount = $amount * ( (float) $coupon->discount_value / 100 );
			if ( null !== $coupon->max_discount ) {
				$discount = min( $discount, (float) $coupon->max_discount );
			}
		} else {
			$discount = (float) $coupon->discount_value;
		}

		// Never allow the discount to exceed the amount, and never go below zero.
		$discount = max( 0, min( $discount, $amount ) );
		$final    = max( 0, $amount - $discount );

		return array(
			'valid'           => true,
			'message'         => __( 'Coupon applied.', 'ggm-member-dashboard' ),
			'coupon_id'       => (int) $coupon->id,
			'code'            => $coupon->code,
			'original_amount' => round( $amount, 2 ),
			'discount_amount' => round( $discount, 2 ),
			'final_amount'    => round( $final, 2 ),
		);
	}

	/**
	 * Record a coupon usage after a successful purchase/registration.
	 *
	 * @param int   $coupon_id
	 * @param int   $user_id
	 * @param int   $payment_id
	 * @param array $context {
	 *     @type int   $workshop_id
	 *     @type float $original_amount
	 *     @type float $discount_amount
	 *     @type float $final_amount
	 * }
	 * @return int|false
	 */
	public static function record_usage( $coupon_id, $user_id, $payment_id, array $context = array() ) {
		global $wpdb;

		$result = $wpdb->insert( self::usage_table(), array(
			'coupon_id'       => absint( $coupon_id ),
			'user_id'         => absint( $user_id ),
			'payment_id'      => $payment_id ? absint( $payment_id ) : null,
			'workshop_id'     => ! empty( $context['workshop_id'] ) ? absint( $context['workshop_id'] ) : null,
			'original_amount' => (float) ( $context['original_amount'] ?? 0 ),
			'discount_amount' => (float) ( $context['discount_amount'] ?? 0 ),
			'final_amount'    => (float) ( $context['final_amount'] ?? 0 ),
			'used_at'         => current_time( 'mysql' ),
		) );

		if ( $result ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . self::table() . ' SET used_count = used_count + 1 WHERE id = %d',
				absint( $coupon_id )
			) );
		}

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get usage history rows joined with coupon code for admin reporting.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_usage_report( $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT cu.*, c.code AS coupon_code, c.name AS coupon_name
			 FROM " . self::usage_table() . " cu
			 LEFT JOIN " . self::table() . " c ON cu.coupon_id = c.id
			 ORDER BY cu.id DESC
			 LIMIT %d",
			absint( $limit )
		) );
	}
}
