<?php
/**
 * Dashboard AJAX Handlers.
 *
 * Pulls profile information, enrolled/available courses,
 * free/paid workshops, and manages profile updates.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Dashboard
 */
class GGM_Dashboard {

	/**
	 * Register actions via loader.
	 *
	 * @param GGM_Loader $loader
	 */
	public function init( GGM_Loader $loader ) {
		// Primary action name used by ggm-dashboard.js
		$loader->add_action( 'wp_ajax_ggm_dashboard_ajax',     $this, 'ajax_get_dashboard_data' );
		// Legacy alias so both action names work
		$loader->add_action( 'wp_ajax_ggm_get_dashboard_data', $this, 'ajax_get_dashboard_data' );
		$loader->add_action( 'wp_ajax_ggm_update_profile',     $this, 'ajax_update_profile' );
		$loader->add_action( 'wp_ajax_ggm_upload_avatar',      $this, 'ajax_upload_avatar' );
		$loader->add_action( 'wp_ajax_ggm_remove_avatar',      $this, 'ajax_remove_avatar' );
		$loader->add_action( 'wp_ajax_ggm_change_password',    $this, 'ajax_change_password' );
		$loader->add_action( 'wp_ajax_ggm_complete_lesson',    $this, 'ajax_complete_lesson' );
	}

	/**
	 * AJAX: Get Dashboard Data.
	 */
	public function ajax_get_dashboard_data() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'ggm-member-dashboard' ) ) );
		}

		$user = get_userdata( $user_id );
		$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );

		// ─── 1. Profile ──────────────────────────────────────────────────────
		$phone = get_user_meta( $user_id, 'billing_phone', true )
			  ?: get_user_meta( $user_id, 'ggm_phone', true );
		$whatsapp_country_code = get_user_meta( $user_id, 'ggm_whatsapp_country_code', true ) ?: '+91';

		$login_page_id = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
		$login_url     = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/workshop-login/' );

		// The templates load Gravatar in the browser with an initial fallback;
		// keep the AJAX payload limited to an explicitly uploaded avatar.
		$avatar = get_user_meta( $user_id, 'ggm_avatar_url', true );

		$profile = array(
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'name'         => trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'phone'        => $phone,
			'whatsapp_country_code' => $whatsapp_country_code,
			'joined'       => date_i18n( 'd M Y', strtotime( $user->user_registered ) ),
			'initial'      => strtoupper( substr( $user->first_name ?: $user->display_name, 0, 1 ) ),
			'avatar'       => $avatar,
			'logout_url'   => wp_logout_url( $login_url ),
		);

		// ─── 2. Courses — search `course` + `ggm_workshop` (old & new data) ──
		$courses_query = get_posts( array(
			'post_type'      => array( 'course', 'ggm_workshop' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$enrolled_courses   = array();
		$unenrolled_courses = array();

		foreach ( $courses_query as $c ) {
			// Count lessons where meta 'course' = post ID.
			$lesson_args = array(
				'post_type'      => array( 'lesson', 'ggm_lesson' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array( 'key' => 'course', 'value' => $c->ID ),
				),
			);
			$course_lessons = class_exists( 'GGM_Lesson' )
				? GGM_Lesson::get_for_course( $c->ID )
				: get_posts( $lesson_args );
			$video_count    = count( $course_lessons );

			// Course access is direct only: an explicit free price, or a
			// direct course purchase (no more membership gating).
			$course_price_raw     = get_post_meta( $c->ID, 'course_price', true );
			$course_price         = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::parse_price( $course_price_raw ) : (float) $course_price_raw;
			$course_price_is_set  = '' !== trim( (string) $course_price_raw );
			$course_is_free       = $course_price_is_set && $course_price <= 0;
			$co_page          = (int) ggm_get_setting( 'ggm_checkout_page_id', 0 );
			$checkout_base    = $co_page ? get_permalink( $co_page ) : home_url( '/membership-checkout/' );
			$checkout_url     = $course_price > 0 ? add_query_arg( array( 'course_id' => $c->ID, 'ggm_currency' => $selected_currency ), $checkout_base ) : '';
			$course_price_display = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
				? GGM_Currency::format_converted( $course_price, $c->ID, $selected_currency )
				: ggm_get_setting( 'ggm_currency_symbol', '₹' ) . number_format_i18n( $course_price, 0 );

			$c_data = array(
				'id'          => $c->ID,
				'title'       => $c->post_title,
				'thumbnail'   => get_the_post_thumbnail_url( $c->ID, 'medium' ) ?: get_post_meta( $c->ID, 'course_thumbnail', true ),
				'short_desc'  => get_post_meta( $c->ID, 'course_short_desc', true ) ?: wp_trim_words( get_the_excerpt( $c->ID ), 15 ),
				'permalink'   => get_permalink( $c->ID ),
				'video_count' => $video_count,
				'price'       => $course_price,
				'price_display' => $course_price_display,
				'currency'    => $selected_currency,
				'is_free'     => $course_is_free,
				'instructor'  => get_post_meta( $c->ID, 'course_instructor', true ) ?: '',
				'language'    => get_post_meta( $c->ID, 'course_language', true )   ?: 'English',
				'checkout_url'=> $checkout_url,
			);

			if ( ggm_user_has_course_access( $c->ID, $user_id ) ) {
				$completed_ids = array_map( 'absint', (array) get_user_meta( $user_id, 'ggm_completed_lessons', true ) );
				$lessons       = $course_lessons;
				$c_data['lessons'] = array();
				foreach ( $lessons as $index => $lesson ) {
					$video = get_post_meta( $lesson->ID, 'video_embed', true );
					$video_html = '';
					if ( $video ) {
						$video_html = wp_oembed_get( $video );
						if ( ! $video_html && false !== strpos( $video, '<' ) ) {
							$video_html = $video;
						}
					}
					$video_allowed = wp_kses_allowed_html( 'post' );
					$video_allowed['iframe'] = array(
						'src' => true, 'title' => true, 'width' => true, 'height' => true,
						'allow' => true, 'allowfullscreen' => true, 'frameborder' => true,
						'loading' => true, 'referrerpolicy' => true,
					);
					$c_data['lessons'][] = array(
						'id'        => $lesson->ID,
						'number'    => $index + 1,
						'title'     => $lesson->post_title ?: sprintf( __( 'Lesson %d', 'ggm-member-dashboard' ), $index + 1 ),
						'duration'  => get_post_meta( $lesson->ID, 'lesson_duration', true ),
						'content'   => apply_filters( 'the_content', $lesson->post_content ),
						'video'     => $video_html ? wp_kses( $video_html, $video_allowed ) : '',
						'completed' => in_array( (int) $lesson->ID, $completed_ids, true ),
					);
				}
				$c_data['completed_count'] = count( array_filter( $c_data['lessons'], function( $lesson ) { return $lesson['completed']; } ) );
				$c_data['progress']        = $video_count ? (int) round( ( $c_data['completed_count'] / $video_count ) * 100 ) : 0;
				$enrolled_courses[] = $c_data;
			} else {
				$unenrolled_courses[] = $c_data;
			}
		}

		// ─── 3. Workshops — search both `workshop` (old) and `ggm_workshop` (new) ──
		$workshops_raw = get_posts( array(
			'post_type'      => array( 'workshop', 'ggm_workshop' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$free_workshops     = array();
		$paid_workshops     = array();
		$checkout_page_url  = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );

		foreach ( $workshops_raw as $w ) {
			$has_access = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::user_has_access( $user_id, $w->ID ) : false;

			// Task 11 — a workshop past its End Date shows as "Workshop
			// Completed" (see GGM_Workshop_Slot::compute_status()) once its
			// 30-day recording window has also elapsed. This only ever hides
			// a workshop the visitor hasn't purchased (nothing to browse
			// anymore) — a workshop they've bought stays on their dashboard
			// forever and is never removed.
			if ( ! $has_access && class_exists( 'GGM_Workshop' ) && GGM_Workshop::should_hide_from_dashboard( $w->ID ) ) {
				continue;
			}

			$active_slots = class_exists( 'GGM_Workshop_Slot' ) ? GGM_Workshop_Slot::get_for_workshop( $w->ID, 'active' ) : array();

			$slot_time = '';
			if ( 1 === count( $active_slots ) ) {
				$slot_time = GGM_Workshop_Slot::format_range( $active_slots[0] );
			} elseif ( count( $active_slots ) > 1 ) {
				$slot_time = __( 'Multiple time slots available', 'ggm-member-dashboard' );
			}

			// Per-slot breakdown for the dashboard card — only whatever slots
			// are actually configured & enabled show up here; an empty array
			// renders nothing, a single slot renders exactly one row.
			$slots_list = array();
			if ( class_exists( 'GGM_Workshop_Slot' ) ) {
				foreach ( $active_slots as $slot ) {
					$slots_list[] = array(
						'type' => GGM_Workshop_Slot::type_label( $slot->slot_type ),
						'time' => GGM_Workshop_Slot::format_start_time( $slot ),
					);
				}
			}

			// Task 9 — server-computed status of whichever slot is current or
			// coming up next, for the dashboard's "Start Learning" button.
			// Never computed client-side from the browser's clock.
			$slot_info = null;
			if ( class_exists( 'GGM_Workshop_Slot' ) ) {
				$current = GGM_Workshop_Slot::get_current_or_next( $w->ID );
				if ( $current ) {
					$current_slot = $current['slot'];
					$status       = $current['status'];
					$slot_info    = array(
						'type'              => GGM_Workshop_Slot::type_label( $current_slot->slot_type ),
						'state'             => $status['state'], // future | countdown | unlocked | ended
						'seconds_to_unlock' => $status['seconds_to_unlock'],
						'seconds_to_start'  => $status['seconds_to_start'],
						'seconds_to_end'    => $status['seconds_to_end'],
						'meeting_link'      => $current_slot->meeting_link,
						'label'             => GGM_Workshop_Slot::format_range( $current_slot ),
					);
				}
			}

			$workshop_price   = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::resolve_price( $w->ID ) : 0.0;
			$is_contribution = class_exists( 'GGM_Workshop' ) && GGM_Workshop::is_contribution( $w->ID );
			$workshop_price_display = class_exists( 'GGM_Workshop' )
				? GGM_Workshop::price_html( $w->ID )
				: ggm_get_setting( 'ggm_currency_symbol', '₹' ) . number_format_i18n( $workshop_price, 0 );
			$start_date     = get_post_meta( $w->ID, 'workshop_start_date', true ) ?: get_post_meta( $w->ID, 'workshop_date', true );
			$linked_course  = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_linked_course( $w->ID ) : null;

			$w_data = array(
				'id'             => $w->ID,
				'title'          => $w->post_title,
				'thumbnail'      => get_the_post_thumbnail_url( $w->ID, 'medium' ),
				'short_desc'     => wp_kses( get_post_meta( $w->ID, 'workshop_short_desc', true ), GGM_Meta_Boxes::SHORT_DESC_ALLOWED_TAGS ),
				'permalink'      => get_permalink( $w->ID ), // Task 8 — eye icon target.
				'date'           => $start_date,
				'end_date'       => get_post_meta( $w->ID, 'workshop_end_date', true ),
				'time'           => $slot_time,
				'slots_list'     => $slots_list,
				'duration'       => get_post_meta( $w->ID, 'duration', true ),
				'mode'           => get_post_meta( $w->ID, 'workshop_mode', true ),
				'language'       => 'hindi' === get_post_meta( $w->ID, 'workshop_language', true ) ? __( 'Hindi', 'ggm-member-dashboard' ) : __( 'English', 'ggm-member-dashboard' ),
				'price'          => $workshop_price,
				'price_display'  => $workshop_price_display,
				'currency'       => $selected_currency,
				'is_contribution'=> $is_contribution,
				'is_free'        => ! $is_contribution && $workshop_price <= 0,
				'has_access'     => $has_access,
				'whatsapp_group_url' => $has_access ? esc_url_raw( get_post_meta( $w->ID, 'ggm_workshop_whatsapp_group_url', true ) ) : '',
				'checkout_url'   => add_query_arg( array( 'workshop_id' => $w->ID, 'ggm_currency' => $selected_currency ), $checkout_page_url ),
				'has_slots'      => ! empty( $active_slots ),
				'slot_info'      => $slot_info,
				'linked_course'  => $linked_course ? array( 'title' => $linked_course->post_title, 'permalink' => get_permalink( $linked_course ) ) : null,
			);

			if ( $w_data['is_free'] ) {
				$free_workshops[] = $w_data;
			} else {
				$paid_workshops[] = $w_data;
			}
		}

		wp_send_json_success( array(
			'profile'            => $profile,
			'enrolled_courses'   => $enrolled_courses,
			'unenrolled_courses' => $unenrolled_courses,
			'free_workshops'     => $free_workshops,
			'paid_workshops'     => $paid_workshops,
		) );
	}

	/**
	 * AJAX: persist a member's lesson completion state.
	 */
	public function ajax_complete_lesson() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );
		$user_id   = get_current_user_id();
		$lesson_id = absint( $_POST['lesson_id'] ?? 0 );
		$complete  = ! empty( $_POST['complete'] );

		if ( ! $user_id || ! $lesson_id || ! class_exists( 'GGM_Lesson' ) || ! GGM_Lesson::user_can_access( $user_id, $lesson_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot update this lesson.', 'ggm-member-dashboard' ) ), 403 );
		}

		$completed = array_map( 'absint', (array) get_user_meta( $user_id, 'ggm_completed_lessons', true ) );
		$completed = array_values( array_unique( $completed ) );
		if ( $complete && ! in_array( $lesson_id, $completed, true ) ) {
			$completed[] = $lesson_id;
		} elseif ( ! $complete ) {
			$completed = array_values( array_diff( $completed, array( $lesson_id ) ) );
		}
		update_user_meta( $user_id, 'ggm_completed_lessons', $completed );
		wp_send_json_success( array( 'completed' => $complete ) );
	}

	/**
	 * AJAX: Update Profile.
	 */
	public function ajax_update_profile() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'ggm-member-dashboard' ) ) );
		}

		$user_id      = get_current_user_id();
		$first_name   = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name    = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$phone        = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$country_code = sanitize_text_field( wp_unslash( $_POST['country_code'] ?? '' ) );

		if ( empty( $first_name ) ) {
			wp_send_json_error( array( 'message' => __( 'First name is required.', 'ggm-member-dashboard' ) ) );
		}

		wp_update_user( array(
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
		) );

		update_user_meta( $user_id, 'billing_first_name', $first_name );
		update_user_meta( $user_id, 'billing_last_name', $last_name );

		if ( ! empty( $phone ) ) {
			$clean_phone = preg_replace( '/\D/', '', $phone );
			update_user_meta( $user_id, 'billing_phone', $clean_phone );
			update_user_meta( $user_id, 'ggm_phone', $clean_phone );
		}
		if ( ! empty( $country_code ) ) {
			update_user_meta( $user_id, 'ggm_whatsapp_country_code', $country_code );
		}

		wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'ggm-member-dashboard' ) ) );
	}

	/**
	 * AJAX: Change Password.
	 * No current-password check — the dashboard session itself is the
	 * authentication (same trust level as every other profile field here).
	 */
	public function ajax_change_password() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'ggm-member-dashboard' ) ) );
		}

		$user_id          = get_current_user_id();
		$new_password     = wp_unslash( $_POST['new_password'] ?? '' );
		$confirm_password = wp_unslash( $_POST['confirm_password'] ?? '' );

		if ( empty( $new_password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a new password.', 'ggm-member-dashboard' ) ) );
		}

		if ( strlen( $new_password ) < 6 ) {
			wp_send_json_error( array( 'message' => __( 'New password must be at least 6 characters.', 'ggm-member-dashboard' ) ) );
		}
		if ( $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => __( 'New passwords do not match.', 'ggm-member-dashboard' ) ) );
		}

		wp_set_password( $new_password, $user_id );

		// wp_set_password() destroys every session for this user, including
		// the current one — re-issue a fresh auth cookie so the visitor
		// isn't logged out immediately after changing their own password.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		wp_send_json_success( array( 'message' => __( 'Password changed successfully.', 'ggm-member-dashboard' ) ) );
	}

	/**
	 * AJAX: Upload avatar image.
	 */
	public function ajax_upload_avatar() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'ggm-member-dashboard' ) ) );
		}

		if ( empty( $_FILES['avatar'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'ggm-member-dashboard' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Validate file type.
		$allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$type    = $_FILES['avatar']['type'];
		if ( ! in_array( $type, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Only JPG, PNG, GIF, and WebP images are allowed.', 'ggm-member-dashboard' ) ) );
		}

		// Max 2MB.
		if ( $_FILES['avatar']['size'] > 2 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( 'Image must be smaller than 2 MB.', 'ggm-member-dashboard' ) ) );
		}

		add_filter( 'upload_dir', array( $this, 'set_avatar_upload_dir' ) );
		$attachment_id = media_handle_upload( 'avatar', 0 );
		remove_filter( 'upload_dir', array( $this, 'set_avatar_upload_dir' ) );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: wp_get_attachment_url( $attachment_id );

		// Delete old custom avatar attachment if it was uploaded by this user.
		$old_id = (int) get_user_meta( get_current_user_id(), 'ggm_avatar_attachment_id', true );
		if ( $old_id ) {
			wp_delete_attachment( $old_id, true );
		}

		update_user_meta( get_current_user_id(), 'ggm_avatar_url', $url );
		update_user_meta( get_current_user_id(), 'ggm_avatar_attachment_id', $attachment_id );

		wp_send_json_success( array(
			'url'     => $url,
			'message' => __( 'Avatar updated successfully.', 'ggm-member-dashboard' ),
		) );
	}

	/**
	 * Filter upload dir to keep avatars organised.
	 */
	public function set_avatar_upload_dir( $dirs ) {
		$user_id          = get_current_user_id();
		$dirs['subdir']   = '/ggm-avatars/' . $user_id;
		$dirs['path']     = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']      = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	}

	/**
	 * AJAX: Remove custom avatar (revert to Gravatar / initial).
	 */
	public function ajax_remove_avatar() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'ggm-member-dashboard' ) ) );
		}

		$user_id = get_current_user_id();
		$old_id  = (int) get_user_meta( $user_id, 'ggm_avatar_attachment_id', true );
		if ( $old_id ) {
			wp_delete_attachment( $old_id, true );
		}

		delete_user_meta( $user_id, 'ggm_avatar_url' );
		delete_user_meta( $user_id, 'ggm_avatar_attachment_id' );

		wp_send_json_success( array( 'message' => __( 'Avatar removed.', 'ggm-member-dashboard' ) ) );
	}
}
