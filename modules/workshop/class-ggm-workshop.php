<?php
/**
 * Workshop access helpers and queries.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Workshop {

	/**
	 * Check if a user has access to a workshop.
	 *
	 * Priority: admin → free resolved price → direct/registered access →
	 * required GGM membership → membership's allowed_workshops list.
	 *
	 * @param int $user_id
	 * @param int $workshop_id
	 * @return bool
	 */
	public static function user_has_access( $user_id, $workshop_id ) {
		$workshop_id = absint( $workshop_id );
		if ( ! $workshop_id || ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// A fixed-price workshop can be globally free. Contribution workshops
		// always require an explicit, recorded option selection — even when
		// their configured default contribution is Free.
		if ( ! self::is_contribution( $workshop_id ) && self::resolve_price( $workshop_id ) <= 0 ) {
			return true;
		}

		// Direct purchase/registration recorded in wp_ggm_workshop_access —
		// this also covers every user who was previously gated by a
		// membership plan, since that access was migrated into this same
		// table (granted_via = 'membership') when the Membership system was
		// removed. See GGM_Database::migrate_membership_access_to_v2().
		global $wpdb;
		$registered = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ggm_workshop_access WHERE user_id = %d AND workshop_id = %d",
			$user_id,
			$workshop_id
		) );

		return $registered > 0;
	}

	/**
	 * Get the explicit workshop access row for a user, if one exists.
	 *
	 * @param int $user_id
	 * @param int $workshop_id
	 * @return object|null
	 */
	public static function get_access_record( $user_id, $workshop_id ) {
		$user_id     = absint( $user_id );
		$workshop_id = absint( $workshop_id );
		if ( ! $user_id || ! $workshop_id ) {
			return null;
		}

		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT a.*, p.status AS payment_status, p.amount AS payment_amount, p.original_amount, p.credit_amount, p.pricing_mode, p.contribution_amount
			 FROM {$wpdb->prefix}ggm_workshop_access a
			 LEFT JOIN {$wpdb->prefix}ggm_payments p ON p.id = a.payment_id
			 WHERE a.user_id = %d AND a.workshop_id = %d
			 LIMIT 1",
			$user_id,
			$workshop_id
		) );
	}

	/**
	 * Classify a user's durable access to a workshop.
	 *
	 * Returns:
	 * - none: no explicit paid/free row and the workshop is not globally free.
	 * - free: access came from a free option or a zero-value payment.
	 * - paid: access came from a positive payment, manual direct grant, membership, or admin.
	 * - global_free: fixed-price workshop is globally free, with no user row.
	 *
	 * @param int $user_id
	 * @param int $workshop_id
	 * @return string
	 */
	public static function get_user_access_level( $user_id, $workshop_id ) {
		$user_id     = absint( $user_id );
		$workshop_id = absint( $workshop_id );
		if ( ! $user_id || ! $workshop_id ) {
			return 'none';
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return 'paid';
		}

		$row = self::get_access_record( $user_id, $workshop_id );
		if ( ! $row ) {
			return ( ! self::is_contribution( $workshop_id ) && self::resolve_price( $workshop_id ) <= 0 ) ? 'global_free' : 'none';
		}

		if ( in_array( (string) $row->granted_via, array( 'membership', 'direct' ), true ) && empty( $row->payment_id ) ) {
			return 'paid';
		}

		if ( ! empty( $row->payment_id ) && 'success' === (string) $row->payment_status ) {
			$paid_total = (float) $row->payment_amount + (float) $row->credit_amount;
			if ( (float) $row->original_amount > 0 || (float) $row->contribution_amount > 0 || $paid_total > 0 ) {
				return 'paid';
			}
		}

		return 'free';
	}

	/**
	 * Whether a workshop's End Date has passed. A workshop with no end date
	 * set never expires.
	 *
	 * @param int $workshop_id
	 * @return bool
	 */
	public static function is_expired( $workshop_id ) {
		$end_date = get_post_meta( $workshop_id, 'workshop_end_date', true );
		if ( '' === $end_date ) {
			return false;
		}
		$end_ts = strtotime( $end_date . ' 23:59:59' );
		return $end_ts && $end_ts < current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	}

	/**
	 * Whether a workshop should disappear from the member dashboard.
	 *
	 * A workshop stays visible (showing "Workshop Completed" on its Start
	 * Learning button — see GGM_Workshop_Slot::compute_status()) for a grace
	 * period after its End Date has passed, so buyers can still see it
	 * finished rather than have it vanish the instant it ends. Only once
	 * that grace period elapses does it disappear entirely.
	 *
	 * @param int $workshop_id
	 * @param int $grace_days Days after End Date before the workshop is hidden.
	 * @return bool
	 */
	public static function should_hide_from_dashboard( $workshop_id, $grace_days = 30 ) {
		$end_date = get_post_meta( $workshop_id, 'workshop_end_date', true );
		if ( '' === $end_date ) {
			return false;
		}
		$end_ts = strtotime( $end_date . ' 23:59:59' );
		if ( ! $end_ts ) {
			return false;
		}
		$cutoff_ts = $end_ts + ( $grace_days * DAY_IN_SECONDS );
		return $cutoff_ts < current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	}

	/**
	 * The course linked to a workshop (Task 7 — "Linked Course"), if any.
	 *
	 * @param int $workshop_id
	 * @return WP_Post|null
	 */
	public static function get_linked_course( $workshop_id ) {
		$course_id = (int) get_post_meta( $workshop_id, 'linked_course_id', true );
		if ( ! $course_id ) {
			return null;
		}
		$course = get_post( $course_id );
		return ( $course && 'course' === $course->post_type ) ? $course : null;
	}

	/**
	 * List every workshop a user has registered/purchased access to, most
	 * recent first — for admin-facing profile views. Unlike
	 * user_has_access() (which only answers yes/no for one workshop), this
	 * reads every row `wp_ggm_workshop_access` has for the user and joins
	 * in the workshop title + time-slot label.
	 *
	 * @param int $user_id
	 * @return object[] Rows: workshop_id, workshop_title, slot_id, slot_label, granted_via, payment_id, granted_at.
	 */
	public static function get_registrations_for_user( $user_id ) {
		global $wpdb;

		$access_table = $wpdb->prefix . 'ggm_workshop_access';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $access_table ) ) !== $access_table ) {
			return array();
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.workshop_id, a.slot_id, a.granted_via, a.payment_id, a.granted_at, p.post_title AS workshop_title
			 FROM `{$access_table}` a
			 LEFT JOIN {$wpdb->posts} p ON p.ID = a.workshop_id
			 WHERE a.user_id = %d
			 ORDER BY a.granted_at DESC",
			absint( $user_id )
		) );

		foreach ( $rows as $row ) {
			$row->slot_label = '';
			if ( $row->slot_id && class_exists( 'GGM_Workshop_Slot' ) ) {
				$slot = GGM_Workshop_Slot::get( $row->slot_id );
				if ( $slot ) {
					$row->slot_label = GGM_Workshop_Slot::format_range( $slot );
				}
			}
		}

		return $rows;
	}

	/**
	 * Get workshops accessible to a user.
	 *
	 * @param int $user_id
	 * @return WP_Post[]
	 */
	public static function get_accessible( $user_id ) {
		$args = array(
			'post_type'      => array( 'workshop', 'ggm_workshop' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$all  = get_posts( $args );
		return array_filter( $all, function( $p ) use ( $user_id ) {
			return self::user_has_access( $user_id, $p->ID );
		} );
	}

	/**
	 * Get free workshops.
	 *
	 * @return WP_Post[]
	 */
	public static function get_free() {
		return get_posts( array(
			'post_type'      => array( 'workshop', 'ggm_workshop' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'   => 'is_free',
					'value' => '1',
				),
			),
		) );
	}

	/**
	 * Read a single field value with ACF/post-meta fallback.
	 *
	 * @param int    $post_id
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	private static function _read_field( $post_id, $key, $default = '' ) {
		$val = get_post_meta( $post_id, $key, true );
		return ( $val !== '' && $val !== false ) ? $val : $default;
	}

	/**
	 * Get workshop meta.
	 *
	 * When called with only $post_id, returns an associative array of all
	 * standard meta keys mapped to their live-site meta key names.
	 * Repeater fields are unserialized via maybe_unserialize() and default to [].
	 * Scalar fields default to ''.
	 *
	 * When called with $key, returns get_post_meta( $post_id, $key, true )
	 * with $default as fallback.
	 *
	 * @param int         $post_id
	 * @param string|null $key     Optional. Meta key to retrieve.
	 * @param mixed       $default Default value when $key is provided and not found.
	 * @return mixed Associative array when $key is omitted, scalar otherwise.
	 */
	public static function get_meta( $post_id, $key = null, $default = '' ) {
		if ( null !== $key ) {
			$val = get_post_meta( $post_id, $key, true );
			return $val ?: $default;
		}

		return array(
			'Counter_Start_Date'                          => get_post_meta( $post_id, 'Counter_Start_Date', true ),
			'workshop_preparatory_date'                   => get_post_meta( $post_id, 'workshop_preparatory_date', true ),
			'workshop_date'                               => get_post_meta( $post_id, 'workshop_date', true ),
			'workshop_money'                              => get_post_meta( $post_id, 'workshop_money', true ),
			'workshop_mode'                               => get_post_meta( $post_id, 'workshop_mode', true ),
			'duration'                                    => get_post_meta( $post_id, 'duration', true ),
			'You_Will_Discover'                           => maybe_unserialize( get_post_meta( $post_id, 'You_Will_Discover', true ) ) ?: array(),
			'why_this_webinar_different_heading'          => get_post_meta( $post_id, 'why_this_webinar_different_heading', true ),
			'why_this_webinar_different_content'          => get_post_meta( $post_id, 'why_this_webinar_different_content', true ),
			'why_this_webinar_different_points_and_image' => maybe_unserialize( get_post_meta( $post_id, 'why_this_webinar_different_points_and_image', true ) ) ?: array(),
			'perfect_for_you_if_you_want_to'              => maybe_unserialize( get_post_meta( $post_id, 'perfect_for_you_if_you_want_to', true ) ) ?: array(),
			'faq'                                         => maybe_unserialize( get_post_meta( $post_id, 'faq', true ) ) ?: array(),
			'is_free'                                     => get_post_meta( $post_id, 'is_free', true ),
			'workshop_short_desc'                         => get_post_meta( $post_id, 'workshop_short_desc', true ),
			'ggm_workshop_featured_video_url'              => get_post_meta( $post_id, 'ggm_workshop_featured_video_url', true ),
		);
	}

	/**
	 * Return HTML star icons for a numeric rating (0–5).
	 *
	 * @param float|int $rating
	 * @return string HTML string safe for direct echo.
	 */
	public static function rating_html( $rating ) {
		$rating  = (float) $rating;
		$full    = min( 5, max( 0, (int) floor( $rating ) ) );
		$half    = ( $rating - $full >= 0.5 ) ? 1 : 0;
		$empty   = 5 - $full - $half;

		$html  = '<span class="ggm-stars">';
		$html .= str_repeat( '<span class="ggm-star ggm-star--full">&#9733;</span>', $full );
		if ( $half ) {
			$html .= '<span class="ggm-star ggm-star--half">&#9733;</span>';
		}
		$html .= str_repeat( '<span class="ggm-star ggm-star--empty">&#9734;</span>', $empty );
		$html .= '</span>';

		return $html;
	}

	/**
	 * Parse a raw `workshop_money` value into a real float, regardless of
	 * whether the admin typed a currency symbol/commas along with the
	 * number (e.g. "₹1800", "1,800", "1800" all become 1800.0). A plain
	 * `(float)` cast on a string starting with a non-numeric character
	 * (like "₹") silently evaluates to 0 in PHP — every price reader in
	 * this plugin must go through this instead of casting directly.
	 *
	 * @param string|int|float $price
	 * @return float
	 */
	public static function parse_price( $price ) {
		$numeric = preg_replace( '/[^0-9.]/', '', (string) $price );
		return '' === $numeric ? 0.0 : (float) $numeric;
	}

	/**
	 * Get the currently configured display currency symbol.
	 *
	 * @param int $workshop_id Retained for backward-compatible method calls.
	 * @return string
	 */
	public static function get_currency( $workshop_id ) {
		if ( class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() ) {
			return GGM_Currency::symbol( GGM_Currency::selected_currency() );
		}

		return function_exists( 'ggm_get_setting' ) ? ggm_get_setting( 'ggm_currency_symbol', '₹' ) : '₹';
	}

	/**
	 * Regular Price for a workshop, parsed to a float. Falls back to the
	 * pre-v2 single "Workshop Price" field for workshops saved before the
	 * Regular/Sale Price upgrade.
	 *
	 * @param int $workshop_id
	 * @return float
	 */
	public static function get_regular_price( $workshop_id ) {
		$value = get_post_meta( $workshop_id, 'workshop_regular_price', true );
		if ( '' === $value ) {
			$value = get_post_meta( $workshop_id, 'workshop_money', true );
		}
		return self::parse_price( $value );
	}

	/**
	 * Sale Price for a workshop, parsed to a float (0.0 if not set).
	 *
	 * @param int $workshop_id
	 * @return float
	 */
	public static function get_sale_price( $workshop_id ) {
		return self::parse_price( get_post_meta( $workshop_id, 'workshop_sale_price', true ) );
	}

	/**
	 * Resolve the price that should actually be charged for a workshop —
	 * the Sale Price when one is validly set (below the Regular Price),
	 * otherwise the Regular Price. No more membership involvement in
	 * pricing — every workshop is purchased directly.
	 *
	 * @param int $workshop_id
	 * @return float
	 */
	public static function resolve_price( $workshop_id ) {
		if ( self::is_contribution( $workshop_id ) ) {
			$option = self::get_default_contribution( $workshop_id );
			return $option ? (float) $option['amount'] : 0.0;
		}
		$regular = self::get_regular_price( $workshop_id );
		$sale    = self::get_sale_price( $workshop_id );

		return ( $sale > 0 && $sale < $regular ) ? $sale : $regular;
	}

	public static function is_contribution( $workshop_id ) {
		return '1' === get_post_meta( $workshop_id, 'ggm_contribution_enabled', true );
	}

	public static function get_contribution_options( $workshop_id ) {
		$options = get_post_meta( $workshop_id, 'ggm_contribution_options', true );
		if ( ! is_array( $options ) ) {
			return array();
		}

		$normalized = array();
		$free_seen  = false;
		foreach ( $options as $option ) {
			if ( ! is_array( $option ) || empty( $option['id'] ) ) {
				continue;
			}

			$raw_id     = $option['id'] ?? '';
			$raw_type   = $option['type'] ?? 'paid';
			$raw_amount = $option['amount'] ?? 0;
			$raw_label  = $option['label'] ?? '';
			if ( ! is_scalar( $raw_id ) || ! is_scalar( $raw_type ) || ! is_scalar( $raw_amount ) || ! is_scalar( $raw_label ) ) {
				continue;
			}

			$id     = sanitize_key( (string) $raw_id );
			$type   = 'free' === (string) $raw_type ? 'free' : 'paid';
			$amount = round( (float) $raw_amount, 2 );
			if ( '' === $id ) {
				continue;
			}
			if ( 'free' === $type ) {
				if ( $free_seen ) {
					continue;
				}
				$free_seen = true;
				$amount    = 0.0;
			} elseif ( $amount <= 0 ) {
				continue;
			}

			$normalized[] = array(
				'id'         => $id,
				'type'       => $type,
				'amount'     => $amount,
				'label'      => sanitize_text_field( (string) $raw_label ),
				'is_default' => ! empty( $option['is_default'] ),
			);
		}

		$default_seen = false;
		foreach ( $normalized as &$option ) {
			if ( $option['is_default'] && ! $default_seen ) {
				$default_seen = true;
			} else {
				$option['is_default'] = false;
			}
		}
		unset( $option );
		if ( $normalized && ! $default_seen ) {
			$normalized[0]['is_default'] = true;
		}

		return $normalized;
	}

	public static function get_default_contribution( $workshop_id ) {
		$options = self::get_contribution_options( $workshop_id );
		foreach ( $options as $option ) { if ( ! empty( $option['is_default'] ) ) { return $option; } }
		return $options[0] ?? null;
	}

	public static function resolve_purchase_price( $workshop_id, $option_id = '' ) {
		if ( ! self::is_contribution( $workshop_id ) ) {
			return array( 'valid'=>true, 'mode'=>'fixed', 'amount'=>self::resolve_price( $workshop_id ), 'option_id'=>'', 'label'=>'' );
		}
		foreach ( self::get_contribution_options( $workshop_id ) as $option ) {
			if ( hash_equals( (string) $option['id'], (string) $option_id ) ) {
				$is_free = 'free' === $option['type'];
				return array(
					'valid'     => true,
					'mode'      => 'contribution',
					'amount'    => $is_free ? 0.0 : (float) $option['amount'],
					'option_id' => $option['id'],
					'label'     => $option['label'] ?: ( $is_free ? __( 'Free', 'ggm-member-dashboard' ) : ( class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() ? GGM_Currency::format_converted( (float) $option['amount'], $workshop_id ) : self::get_currency( $workshop_id ) . number_format( (float) $option['amount'], 2 ) ) ),
					'is_free'   => $is_free,
				);
			}
		}
		return array( 'valid'=>false, 'mode'=>'contribution', 'amount'=>0, 'option_id'=>'', 'label'=>'', 'message'=>__( 'Select a valid contribution amount.', 'ggm-member-dashboard' ) );
	}

	/**
	 * Return a single HTML price string with a currency symbol/code.
	 *
	 * Returns a free badge when price is 0 or empty.
	 *
	 * @param string|int|float $price
	 * @param string|null      $currency_symbol Defaults to the site-wide currency symbol setting.
	 * @return string HTML string safe for direct echo.
	 */
	public static function format_price( $price, $currency_symbol = null ) {
		$numeric = self::parse_price( $price );

		if ( $numeric <= 0.0 ) {
			return '<span class="ggm-free-badge">Free</span>';
		}

		if ( class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() ) {
			return '<span class="ggm-price">' . esc_html( GGM_Currency::format_converted( $numeric ) ) . '</span>';
		}

		$symbol    = $currency_symbol ?? ( function_exists( 'ggm_get_setting' ) ? ggm_get_setting( 'ggm_currency_symbol', '&#8377;' ) : '&#8377;' );
		$formatted = number_format( (float) $numeric, 0, '.', ',' );

		return '<span class="ggm-price">' . esc_html( $symbol ) . $formatted . '</span>';
	}

	/**
	 * Full Regular/Sale price display for a workshop (Task 4) — the Regular
	 * Price struck through next to the Sale Price when a valid sale price is
	 * set (WooCommerce-style), otherwise just the Regular Price. Returns a
	 * "Free" badge when the resolved price is 0.
	 *
	 * @param int $workshop_id
	 * @return string HTML string safe for direct echo.
	 */
	public static function price_html( $workshop_id ) {
		$currency = self::get_currency( $workshop_id );
		$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() ? GGM_Currency::selected_currency() : '';
		if ( self::is_contribution( $workshop_id ) ) {
			$options = self::get_contribution_options( $workshop_id );
			if ( ! $options ) {
				return '';
			}

			$amounts = array_map(
				static function ( $option ) {
					return 'free' === $option['type'] ? 0.0 : (float) $option['amount'];
				},
				$options
			);
			$lowest  = min( $amounts );
			$highest = max( $amounts );

			if ( $lowest === $highest ) {
				$label = $lowest <= 0
					? __( 'Free', 'ggm-member-dashboard' )
					: ( $selected_currency ? GGM_Currency::format_converted( $lowest, $workshop_id, $selected_currency ) : $currency . number_format( $lowest, 0, '.', ',' ) );
			} else {
				$low_label  = $lowest <= 0
					? __( 'Free', 'ggm-member-dashboard' )
					: ( $selected_currency ? GGM_Currency::format_converted( $lowest, $workshop_id, $selected_currency ) : $currency . number_format( $lowest, 0, '.', ',' ) );
				$high_label = $selected_currency ? GGM_Currency::format_converted( $highest, $workshop_id, $selected_currency ) : $currency . number_format( $highest, 0, '.', ',' );
				$label      = sprintf(
					/* translators: 1: lowest contribution amount, 2: highest contribution amount. */
					__( '%1$s – %2$s', 'ggm-member-dashboard' ),
					$low_label,
					$high_label
				);
			}

			return '<span class="ggm-price ggm-price--contribution">' . esc_html__( 'Contribute ', 'ggm-member-dashboard' ) . '(' . esc_html( $label ) . ')</span>';
		}
		$regular  = self::get_regular_price( $workshop_id );
		$sale     = self::get_sale_price( $workshop_id );

		if ( $regular <= 0 && $sale <= 0 ) {
			return '<span class="ggm-free-badge">' . esc_html__( 'Free', 'ggm-member-dashboard' ) . '</span>';
		}

		if ( $sale > 0 && $sale < $regular ) {
			$display_regular = $selected_currency ? GGM_Currency::format_converted( $regular, $workshop_id, $selected_currency ) : $currency . number_format( $regular, 0, '.', ',' );
			$display_sale    = $selected_currency ? GGM_Currency::format_converted( $sale, $workshop_id, $selected_currency ) : $currency . number_format( $sale, 0, '.', ',' );
			return '<span class="ggm-price ggm-price--sale">'
				. '<del class="ggm-price__regular">' . esc_html( $display_regular ) . '</del> '
				. '<ins class="ggm-price__sale">' . esc_html( $display_sale ) . '</ins>'
				. '</span>';
		}

		$display_regular = $selected_currency ? GGM_Currency::format_converted( $regular, $workshop_id, $selected_currency ) : $currency . number_format( $regular, 0, '.', ',' );
		return '<span class="ggm-price">' . esc_html( $display_regular ) . '</span>';
	}

	/**
	 * Get lessons belonging to a workshop.
	 *
	 * Alias for get_by_parent(). Queries lessons where meta key 'course',
	 * 'workshop_id', or 'parent_workshop' equals $workshop_id, ordered by
	 * lesson_order ASC.
	 *
	 * @param int $workshop_id
	 * @return WP_Post[]
	 */
	public static function get_by_workshop( $workshop_id ) {
		return self::get_by_parent( $workshop_id );
	}

	/**
	 * Get lessons belonging to a parent workshop by any of the common meta keys.
	 *
	 * @param int $workshop_id
	 * @return WP_Post[]
	 */
	public static function get_by_parent( $workshop_id ) {
		$workshop_id = absint( $workshop_id );

		return get_posts( array(
			'post_type'      => array( 'lesson', 'ggm_lesson' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => 'lesson_order',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => 'course',
					'value' => $workshop_id,
				),
				array(
					'key'   => 'workshop_id',
					'value' => $workshop_id,
				),
				array(
					'key'   => 'parent_workshop',
					'value' => $workshop_id,
				),
			),
		) );
	}
}
