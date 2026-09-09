<?php
/**
 * Lesson helpers — queries and access checks.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Lesson {

	/**
	 * Get all lessons belonging to a course, ordered by lesson_order.
	 *
	 * @param int $course_id
	 * @return WP_Post[]
	 */
	public static function get_for_course( $course_id ) {
		$args = array(
			'post_type'      => array( 'lesson', 'ggm_lesson' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => 'lesson_order',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => 'course',
					'value' => absint( $course_id ),
				),
			),
		);
		return get_posts( $args );
	}

	/**
	 * Count lessons for a course.
	 *
	 * @param int $course_id
	 * @return int
	 */
	public static function count_for_course( $course_id ) {
		return count( self::get_for_course( $course_id ) );
	}

	/**
	 * Get the previous and next lessons relative to the current one.
	 *
	 * Returns both WP_Post objects and integer IDs so callers can use either:
	 *   $adj['prev']    — WP_Post|null
	 *   $adj['next']    — WP_Post|null
	 *   $adj['prev_id'] — int|null
	 *   $adj['next_id'] — int|null
	 *
	 * @param int $lesson_id
	 * @return array
	 */
	public static function get_adjacent( $lesson_id ) {
		$course_id = get_post_meta( $lesson_id, 'course', true );
		if ( ! $course_id ) {
			return array(
				'prev'    => null,
				'next'    => null,
				'prev_id' => null,
				'next_id' => null,
			);
		}

		$lessons = self::get_for_course( $course_id );
		$ids     = array_map( function( $l ) { return $l->ID; }, $lessons );
		$index   = array_search( (int) $lesson_id, $ids, true );

		$prev = ( $index > 0 ) ? $lessons[ $index - 1 ] : null;
		$next = ( $index !== false && $index < count( $lessons ) - 1 ) ? $lessons[ $index + 1 ] : null;

		return array(
			'prev'    => $prev,
			'next'    => $next,
			'prev_id' => $prev ? $prev->ID : null,
			'next_id' => $next ? $next->ID : null,
		);
	}

	/**
	 * Check if a lesson is a free preview.
	 *
	 * @param int $lesson_id
	 * @return bool
	 */
	public static function is_preview( $lesson_id ) {
		return '1' === get_post_meta( $lesson_id, 'is_preview', true );
	}

	/**
	 * Check if a user can access a lesson.
	 *
	 * @param int $user_id
	 * @param int $lesson_id
	 * @return bool
	 */
	public static function user_can_access( $user_id, $lesson_id ) {
		if ( user_can( $user_id, 'administrator' ) ) {
			return true;
		}

		if ( self::is_preview( $lesson_id ) ) {
			return true;
		}

		$course_id = get_post_meta( $lesson_id, 'course', true );
		if ( ! $course_id ) {
			return false;
		}

		return ggm_user_has_course_access( $course_id, $user_id );
	}

	/**
	 * Check if the current (or given) user has access to a lesson.
	 *
	 * Access is granted when any of the following is true:
	 *   1. No user — deny immediately.
	 *   2. The user is an administrator (manage_options).
	 *   3. The lesson's 'is_preview' field equals '1' or true.
	 *   4. The user directly purchased the parent course (wp_ggm_course_access),
	 *      or the course is priced Free.
	 *
	 * @param int      $lesson_id
	 * @param int|null $user_id   Defaults to the currently logged-in user.
	 * @return bool
	 */
	public static function user_has_access( $lesson_id, $user_id = null ) {
		$user_id = $user_id ?: get_current_user_id();

		// 1. No user — deny.
		if ( ! $user_id ) {
			return false;
		}

		// 2. Admins always have access.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// 3. Free preview — open to everyone.
		$is_preview = get_post_meta( $lesson_id, 'is_preview', true );
		if ( $is_preview == '1' || $is_preview === true ) {
			return true;
		}

		// 4. Resolve parent course ID (Post Object meta can return an array).
		$course_id = get_post_meta( $lesson_id, 'course', true );
		if ( is_array( $course_id ) ) {
			$course_id = reset( $course_id );
		}

		return $course_id && ggm_user_has_course_access( $course_id, $user_id );
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
	 * Get lesson meta using the live site's exact SCF meta keys.
	 *
	 * When called with only $lesson_id (key omitted / null), returns an
	 * associative array containing all standard lesson fields:
	 *
	 *   new_field           — unserialized SCF Repeater array of {focus_area} rows (SCF key: new_field)
	 *   lesson_focus_areas  — alias of new_field (convenience)
	 *   course              — post ID of the parent course/workshop (SCF Post Object key: course)
	 *   course_id           — alias of course (convenience)
	 *   workshop_id         — alias of course (legacy back-compat)
	 *   lesson_order        — numeric order value
	 *   video_embed         — embed code or URL
	 *   lesson_duration     — human-readable duration string e.g. "45 mins"
	 *   is_preview          — '1' or '' (SCF True/False: Allow free preview)
	 *   what_to_expect      — text
	 *   best_practices      — text
	 *
	 * When called with a $key, returns get_post_meta( $lesson_id, $key, true )
	 * falling back to $default when the value is empty/false.
	 *
	 * @param int         $lesson_id
	 * @param string|null $key     Optional meta key to retrieve.
	 * @param mixed       $default Default returned when $key is given but not found.
	 * @return mixed Associative array when $key is omitted, scalar otherwise.
	 */
	public static function get_meta( $lesson_id, $key = null, $default = '' ) {
		if ( null !== $key ) {
			$val = get_post_meta( $lesson_id, $key, true );
			return $val ?: $default;
		}

		// --- Full meta array ---

		// new_field: SCF Repeater "Lesson Focus Areas" — always unserialize.
		$raw_new_field      = get_post_meta( $lesson_id, 'new_field', true );
		$new_field          = maybe_unserialize( $raw_new_field ) ?: array();
		if ( ! is_array( $new_field ) ) {
			$new_field = array();
		}

		$course          = get_post_meta( $lesson_id, 'course',          true );
		$lesson_order    = get_post_meta( $lesson_id, 'lesson_order',    true );
		$video_embed     = get_post_meta( $lesson_id, 'video_embed',     true );
		$lesson_duration = get_post_meta( $lesson_id, 'lesson_duration', true );
		$is_preview      = get_post_meta( $lesson_id, 'is_preview',      true );
		$what_to_expect  = get_post_meta( $lesson_id, 'what_to_expect',  true );
		$best_practices  = get_post_meta( $lesson_id, 'best_practices',  true );

		return array(
			'new_field'          => $new_field,
			'lesson_focus_areas' => $new_field,          // alias
			'course'             => $course,
			'course_id'          => $course,             // alias
			'workshop_id'        => $course,             // alias for old compat
			'lesson_order'       => $lesson_order,
			'video_embed'        => $video_embed,
			'lesson_duration'    => $lesson_duration,
			'is_preview'         => $is_preview,
			'what_to_expect'     => $what_to_expect,
			'best_practices'     => $best_practices,
		);
	}

	/**
	 * Get lessons belonging to a workshop by any of the common meta keys.
	 *
	 * Queries lessons where meta key 'course', 'workshop_id', or
	 * 'parent_workshop' equals $workshop_id, ordered by lesson_order ASC.
	 *
	 * @param int $workshop_id
	 * @return WP_Post[]
	 */
	public static function get_by_workshop( $workshop_id ) {
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
