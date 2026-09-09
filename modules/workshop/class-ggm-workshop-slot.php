<?php
/**
 * Workshop time slot CRUD — wraps wp_ggm_workshop_slots table.
 *
 * Each slot is a daily-recurring session: its own type (Live / Repeat /
 * Morning Repeat), its own start & end time-of-day, its own Zoom/Meet link,
 * and its own active/inactive (enable/disable) toggle. A slot repeats every
 * day from the parent workshop's Start Date through its End Date — there is
 * no per-slot date, only a time.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Workshop_Slot {

	/** @var string */
	private static $table = 'ggm_workshop_slots';

	/** Legacy default keys retained for third-party compatibility. */
	const TYPES = array( 'live', 'repeat', 'morning_repeat' );

	/**
	 * Configured slot type keys and labels. Keys remain stable while labels can
	 * be edited in settings, so existing workshop slots keep working.
	 */
	public static function types() {
		$defaults = array(
			'live'           => __( 'Live', 'ggm-member-dashboard' ),
			'repeat'         => __( 'Repeat', 'ggm-member-dashboard' ),
			'morning_repeat' => __( 'Morning Repeat', 'ggm-member-dashboard' ),
		);
		$raw = (string) ggm_get_setting( 'ggm_workshop_slot_types', '' );
		if ( '' === trim( $raw ) ) { return $defaults; }
		$types = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			$key = substr( sanitize_key( $parts[0] ?? '' ), 0, 20 );
			$label = sanitize_text_field( $parts[1] ?? '' );
			if ( $key && $label ) { $types[ $key ] = $label; }
		}
		return $types ?: $defaults;
	}

	/**
	 * Get table name with prefix.
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . self::$table;
	}

	/**
	 * Get all slots for a workshop, ordered by start time.
	 *
	 * @param int    $workshop_id
	 * @param string $status 'active', 'inactive', or 'all'.
	 * @return array
	 */
	public static function get_for_workshop( $workshop_id, $status = 'active' ) {
		global $wpdb;
		$table = self::table();

		if ( 'all' === $status ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE workshop_id = %d ORDER BY sort_order ASC, start_time ASC, id ASC",
				absint( $workshop_id )
			) );
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE workshop_id = %d AND status = %s ORDER BY sort_order ASC, start_time ASC, id ASC",
			absint( $workshop_id ),
			$status
		) );
	}

	/**
	 * Get a single slot.
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
	 * Create a new slot.
	 *
	 * @param array $data Keys: workshop_id, slot_type, start_time, end_time, meeting_link, sort_order.
	 * @return int|false
	 */
	public static function create( array $data ) {
		global $wpdb;

		$start = self::normalize_time( $data['start_time'] ?? '' );
		$end   = self::normalize_time( $data['end_time'] ?? '' );

		if ( ! self::validate_range( $start, $end ) ) {
			return false;
		}

		$result = $wpdb->insert( self::table(), array(
			'workshop_id'  => absint( $data['workshop_id'] ?? 0 ),
			'slot_type'    => self::normalize_type( $data['slot_type'] ?? 'live' ),
			'start_time'   => $start,
			'end_time'     => $end,
			'meeting_link' => esc_url_raw( $data['meeting_link'] ?? '' ),
			'sort_order'   => absint( $data['sort_order'] ?? 0 ),
			'status'       => in_array( $data['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
			'created_at'   => current_time( 'mysql' ),
		) );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing slot.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$fields = array();

		$start = isset( $data['start_time'] ) ? self::normalize_time( $data['start_time'] ) : null;
		$end   = isset( $data['end_time'] ) ? self::normalize_time( $data['end_time'] ) : null;

		if ( null !== $start && null !== $end && ! self::validate_range( $start, $end ) ) {
			return false;
		}
		if ( null !== $start ) {
			$fields['start_time'] = $start;
		}
		if ( null !== $end ) {
			$fields['end_time'] = $end;
		}
		if ( isset( $data['slot_type'] ) ) {
			$fields['slot_type'] = self::normalize_type( $data['slot_type'] );
		}
		if ( isset( $data['meeting_link'] ) ) {
			$fields['meeting_link'] = esc_url_raw( $data['meeting_link'] );
		}
		if ( isset( $data['sort_order'] ) ) {
			$fields['sort_order'] = absint( $data['sort_order'] );
		}
		if ( isset( $data['status'] ) ) {
			$fields['status'] = in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : 'active';
		}

		if ( empty( $fields ) ) {
			return false;
		}

		// $wpdb->update() returns the number of rows *changed* — 0 (falsy)
		// when the submitted values already match what's stored, which is
		// not a failure. Only an actual query error returns false.
		$result = $wpdb->update( self::table(), $fields, array( 'id' => absint( $id ) ) );

		return false !== $result;
	}

	/**
	 * Delete a slot.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Delete all slots for a workshop that are not in the given list of IDs.
	 *
	 * @param int   $workshop_id
	 * @param int[] $keep_ids
	 * @return void
	 */
	public static function delete_missing( $workshop_id, array $keep_ids ) {
		global $wpdb;
		$table = self::table();

		if ( empty( $keep_ids ) ) {
			$wpdb->delete( $table, array( 'workshop_id' => absint( $workshop_id ) ), array( '%d' ) );
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $keep_ids ), '%d' ) );
		$query        = "DELETE FROM {$table} WHERE workshop_id = %d AND id NOT IN ({$placeholders})";
		$params       = array_merge( array( absint( $workshop_id ) ), array_map( 'absint', $keep_ids ) );

		$wpdb->query( $wpdb->prepare( $query, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Normalize a slot type to one of the supported values, defaulting to 'live'.
	 *
	 * @param string $type
	 * @return string
	 */
	public static function normalize_type( $type ) {
		$type = substr( sanitize_key( (string) $type ), 0, 20 );
		return $type ?: array_key_first( self::types() );
	}

	/**
	 * Human-readable label for a slot type.
	 *
	 * @param string $type
	 * @return string
	 */
	public static function type_label( $type ) {
		$type = sanitize_key( (string) $type );
		$labels = self::types();
		return $labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
	}

	/**
	 * Normalize a submitted time value (time input format "H:i", or already
	 * "H:i:s") into MySQL TIME format "H:i:s".
	 *
	 * Deliberately does NOT round-trip through strtotime()/gmdate() — this
	 * value is always the site's local wall-clock time-of-day (see
	 * compute_status(), which attaches wp_timezone() when combining it with
	 * a calendar date). Converting through a timestamp here would silently
	 * shift the clock value whenever PHP's ini timezone differs from the
	 * site's configured timezone.
	 *
	 * @param string $value
	 * @return string Empty string if not a valid time.
	 */
	private static function normalize_time( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(\d{2}):(\d{2})(:(\d{2}))?$/', $value, $m ) ) {
			return '';
		}
		return $m[1] . ':' . $m[2] . ':' . ( $m[4] ?? '00' );
	}

	/**
	 * Validate that end is strictly after start (same reference date; slots
	 * are same-day windows, not overnight-spanning).
	 *
	 * @param string $start
	 * @param string $end
	 * @return bool
	 */
	public static function validate_range( $start, $end ) {
		$start = trim( (string) $start );
		$end   = trim( (string) $end );

		if ( '' === $start || '' === $end ) {
			return false;
		}

		return strtotime( '1970-01-01 ' . $end ) > strtotime( '1970-01-01 ' . $start );
	}

	/**
	 * Combine a calendar date with this slot's time-of-day, in the site's
	 * timezone, without ever converting through a differently-zoned
	 * timestamp.
	 *
	 * @param DateTime $date  Midnight of the target day, already in $tz.
	 * @param string   $time  "H:i:s".
	 * @param DateTimeZone $tz
	 * @return DateTime
	 */
	private static function combine_date_time( DateTime $date, $time, DateTimeZone $tz ) {
		$parts = array_map( 'intval', explode( ':', $time . ':0:0' ) );
		$dt    = clone $date;
		$dt->setTimezone( $tz );
		$dt->setTime( $parts[0], $parts[1], $parts[2] );
		return $dt;
	}

	/**
	 * Format a slot's recurring time-of-day range for display, e.g.
	 * "3:00 pm – 4:00 pm" — timezone-aware via wp_date(). There is no date
	 * component: the same window repeats every day the workshop runs.
	 *
	 * @param object $slot
	 * @return string
	 */
	public static function format_range( $slot ) {
		if ( ! $slot || empty( $slot->start_time ) ) {
			return '';
		}

		$site_tz = wp_timezone();
		$today   = new DateTime( 'now', $site_tz );
		$today->setTime( 0, 0, 0 );

		$start_dt = self::combine_date_time( $today, $slot->start_time, $site_tz );
		$end_dt   = ! empty( $slot->end_time ) ? self::combine_date_time( $today, $slot->end_time, $site_tz ) : false;

		$time_fmt = get_option( 'time_format' );

		$start_label = wp_date( $time_fmt, $start_dt->getTimestamp(), $site_tz );
		$end_label   = $end_dt ? wp_date( $time_fmt, $end_dt->getTimestamp(), $site_tz ) : '';

		return trim( $start_label . ( $end_label ? ' – ' . $end_label : '' ) );
	}

	/**
	 * Format just this slot's start time-of-day, e.g. "3:00 pm" — used where
	 * a compact per-slot listing (type + start time) is shown rather than a
	 * full start–end range.
	 *
	 * @param object $slot
	 * @return string
	 */
	public static function format_start_time( $slot ) {
		if ( ! $slot || empty( $slot->start_time ) ) {
			return '';
		}

		$site_tz = wp_timezone();
		$today   = new DateTime( 'now', $site_tz );
		$today->setTime( 0, 0, 0 );

		$start_dt = self::combine_date_time( $today, $slot->start_time, $site_tz );

		return wp_date( get_option( 'time_format' ), $start_dt->getTimestamp(), $site_tz );
	}

	/**
	 * Compute this slot's live-session status relative to right now, in the
	 * site's configured timezone (never the visitor's browser clock).
	 *
	 * The slot recurs daily between $range_start and $range_end (inclusive):
	 * this walks forward from today (or $range_start, whichever is later)
	 * looking for the first day's occurrence that hasn't ended yet, and
	 * reports status against that occurrence. If every occurrence through
	 * $range_end has already ended, the slot is 'ended'.
	 *
	 * States:
	 *   'future'    — more than 1 hour before that occurrence's start. Button
	 *                 disabled, shows a plain "Starts in HH:MM:SS" countdown.
	 *   'countdown' — within 1 hour of start but more than 10 minutes before
	 *                 it. Button still disabled, ticking countdown continues.
	 *   'unlocked'  — from 10 minutes before start through the end time.
	 *                 Button is active and links to the meeting URL.
	 *   'ended'     — every occurrence through $range_end has ended.
	 *
	 * @param object      $slot
	 * @param string      $range_start Workshop Start Date, "Y-m-d".
	 * @param string      $range_end   Workshop End Date, "Y-m-d".
	 * @param int|null    $now_ts      Unix timestamp to treat as "now" (site
	 *                                 timezone); defaults to the real current time.
	 * @return array{state:string,seconds_to_unlock:int,seconds_to_start:int,seconds_to_end:int,start_ts:int,end_ts:int}
	 */
	public static function compute_status( $slot, $range_start, $range_end, $now_ts = null ) {
		$site_tz = wp_timezone();
		$now     = null !== $now_ts ? $now_ts : ( new DateTime( 'now', $site_tz ) )->getTimestamp();
		$ended   = array(
			'state'             => 'ended',
			'seconds_to_unlock' => 0,
			'seconds_to_start'  => 0,
			'seconds_to_end'    => 0,
			'start_ts'          => 0,
			'end_ts'            => 0,
		);

		$start_boundary = DateTime::createFromFormat( '!Y-m-d', (string) $range_start, $site_tz );
		$end_boundary   = DateTime::createFromFormat( '!Y-m-d', (string) $range_end, $site_tz );
		if ( ! $start_boundary || ! $end_boundary ) {
			return $ended;
		}
		$end_boundary->setTime( 23, 59, 59 );

		$cursor = new DateTime( 'now', $site_tz );
		$cursor->setTime( 0, 0, 0 );
		if ( $cursor < $start_boundary ) {
			$cursor = clone $start_boundary;
		}

		// Bounded walk — a workshop's active window is never remotely this long.
		for ( $i = 0; $i < 366; $i++ ) {
			if ( $cursor > $end_boundary ) {
				break;
			}

			$start_dt = self::combine_date_time( $cursor, $slot->start_time, $site_tz );
			$end_dt   = self::combine_date_time( $cursor, $slot->end_time, $site_tz );
			if ( $end_dt <= $start_dt ) {
				$end_dt->modify( '+1 day' ); // Overnight-spanning slot.
			}

			if ( $now < $end_dt->getTimestamp() ) {
				$start_ts  = $start_dt->getTimestamp();
				$end_ts    = $end_dt->getTimestamp();
				$unlock_ts = $start_ts - ( 10 * MINUTE_IN_SECONDS );

				if ( $now >= $unlock_ts ) {
					$state = 'unlocked';
				} elseif ( $now >= $start_ts - HOUR_IN_SECONDS ) {
					$state = 'countdown';
				} else {
					$state = 'future';
				}

				return array(
					'state'              => $state,
					'seconds_to_unlock'  => max( 0, $unlock_ts - $now ),
					'seconds_to_start'   => max( 0, $start_ts - $now ),
					'seconds_to_end'     => max( 0, $end_ts - $now ),
					'start_ts'           => $start_ts,
					'end_ts'             => $end_ts,
				);
			}

			$cursor->modify( '+1 day' );
		}

		return $ended;
	}

	/**
	 * Pick the single most relevant slot for a workshop's "Start Learning"
	 * button right now — the slot whose next occurrence (today or a coming
	 * day, within the workshop's Start/End Date) starts soonest — along with
	 * its already-computed status.
	 *
	 * Once every slot's daily occurrences through the workshop's End Date
	 * have ended, the button doesn't switch to "Workshop Completed" right
	 * away: buyers get a $grace_days-long window (recordings posted to the
	 * same Zoom/Meet link) where the button stays active. Only after that
	 * window elapses does this return null (button shows "Workshop
	 * Completed") — a purchased workshop is never otherwise removed.
	 *
	 * @param int $workshop_id
	 * @param int $grace_days Days after End Date the recording stays reachable.
	 * @return array{slot:object,status:array}|null
	 */
	public static function get_current_or_next( $workshop_id, $grace_days = 30 ) {
		$slots = self::get_for_workshop( $workshop_id, 'active' );
		if ( empty( $slots ) ) {
			return null;
		}

		$range_start = get_post_meta( $workshop_id, 'workshop_start_date', true ) ?: get_post_meta( $workshop_id, 'workshop_date', true );
		$range_end   = get_post_meta( $workshop_id, 'workshop_end_date', true ) ?: $range_start;
		if ( ! $range_start ) {
			return null;
		}

		$now  = ( new DateTime( 'now', wp_timezone() ) )->getTimestamp();
		$best = null;

		foreach ( $slots as $slot ) {
			$status = self::compute_status( $slot, $range_start, $range_end, $now );
			if ( 'ended' === $status['state'] ) {
				continue;
			}
			if ( ! $best || $status['start_ts'] < $best['status']['start_ts'] ) {
				$best = array( 'slot' => $slot, 'status' => $status );
			}
		}

		if ( $best ) {
			return $best;
		}

		// Every occurrence has ended — offer the recording for $grace_days
		// past the workshop's End Date before finally calling it Completed.
		$site_tz = wp_timezone();
		$end_dt  = DateTime::createFromFormat( '!Y-m-d', (string) $range_end, $site_tz );
		if ( ! $end_dt ) {
			return null;
		}
		$end_dt->setTime( 23, 59, 59 );
		$replay_until_ts = $end_dt->getTimestamp() + ( $grace_days * DAY_IN_SECONDS );

		if ( $now > $replay_until_ts ) {
			return null; // Grace period elapsed — Workshop Completed.
		}

		// Whichever slot ran latest on the final day is the best guess for
		// where the recording ends up (same Zoom/Meet link).
		$last_slot = null;
		foreach ( $slots as $slot ) {
			if ( ! $last_slot || strcmp( $slot->start_time, $last_slot->start_time ) > 0 ) {
				$last_slot = $slot;
			}
		}

		return array(
			'slot'   => $last_slot,
			'status' => array(
				'state'             => 'unlocked',
				'seconds_to_unlock' => 0,
				'seconds_to_start'  => 0,
				'seconds_to_end'    => max( 0, $replay_until_ts - $now ),
				'start_ts'          => 0,
				'end_ts'            => $replay_until_ts,
			),
		);
	}
}
