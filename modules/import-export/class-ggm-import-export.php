<?php
/**
 * Workshop / Course export & import — JSON round-trip of posts, their
 * content-block repeaters, workshop time slots, and course lessons.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Import_Export {

	const EXPORT_VERSION = 1;
	const MEMBER_CSV_HEADERS = array( 'full_name', 'mobile_no', 'email', 'full_address', 'pincode', 'gender', 'age', 'weight', 'bp', 'glucose_level', 'diseases', 'payment_details', 'declaration' );

	// ─── Export ──────────────────────────────────────────────────────────────

	/**
	 * Build the full Workshops export payload.
	 *
	 * @return array
	 */
	public static function export_workshops() {
		$posts = get_posts( array(
			'post_type'      => 'workshop',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		$items = array();
		foreach ( $posts as $post ) {
			$items[] = self::export_one_workshop( $post );
		}

		return array(
			'type'        => 'ggm_workshops_export',
			'version'     => self::EXPORT_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'site_url'    => home_url(),
			'items'       => $items,
		);
	}

	/**
	 * Build the full Courses export payload (each course carries its lessons).
	 *
	 * @return array
	 */
	public static function export_courses() {
		$posts = get_posts( array(
			'post_type'      => 'course',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		$items = array();
		foreach ( $posts as $post ) {
			$items[] = self::export_one_course( $post );
		}

		return array(
			'type'        => 'ggm_courses_export',
			'version'     => self::EXPORT_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'site_url'    => home_url(),
			'items'       => $items,
		);
	}

	/**
	 * @param WP_Post $post
	 * @return array
	 */
	private static function export_one_workshop( $post ) {
		$mentor_ids = get_post_meta( $post->ID, 'ggm_mentor_ids', true );
		$mentor_ids = is_array( $mentor_ids ) ? array_map( 'absint', $mentor_ids ) : array();
		if ( empty( $mentor_ids ) ) {
			// Fall back to the pre-multi-mentor single "ggm_mentor_id" field.
			$legacy_mentor_id = (int) get_post_meta( $post->ID, 'ggm_mentor_id', true );
			if ( $legacy_mentor_id ) {
				$mentor_ids = array( $legacy_mentor_id );
			}
		}
		$mentor_names  = array_values( array_filter( array_map( 'get_the_title', $mentor_ids ) ) );
		$linked_course = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_linked_course( $post->ID ) : null;
		$linked_course_title = $linked_course ? $linked_course->post_title : '';

		// These repeater keys are namespaced under 'ggm_' to avoid colliding
		// with any ACF/SCF field group of the same unprefixed name (this bit
		// this site before) — fall back to the legacy key for older content
		// that hasn't been re-saved since the rename.
		$discover_raw = self::meta_with_fallback( $post->ID, 'ggm_you_will_discover', 'you_will_discover' );
		$diff_raw     = self::meta_with_fallback( $post->ID, 'ggm_why_different_points', 'why_different_points' );
		$perf_raw     = self::meta_with_fallback( $post->ID, 'ggm_perfect_for_you', 'perfect_for_you' );
		$faq_raw      = self::meta_with_fallback( $post->ID, 'ggm_faq', 'faq' );

		$time_slots = array();
		if ( class_exists( 'GGM_Workshop_Slot' ) ) {
			foreach ( GGM_Workshop_Slot::get_for_workshop( $post->ID, 'all' ) as $slot ) {
				$time_slots[] = array(
					'slot_type'    => $slot->slot_type,
					'start'        => $slot->start_time,
					'end'          => $slot->end_time,
					'meeting_link' => $slot->meeting_link,
					'status'       => $slot->status,
				);
			}
		}

		return array(
			'title'                => $post->post_title,
			'slug'                 => $post->post_name,
			'status'               => $post->post_status,
			'content'              => $post->post_content,
			'excerpt'              => $post->post_excerpt,
			'mentor_name'          => $mentor_names[0] ?? '', // kept for older tooling; mentor_names below is authoritative
			'mentor_names'         => $mentor_names,
			'linked_course_title'  => $linked_course_title,
			'meta'                 => array(
				'Counter_Start_Date'     => get_post_meta( $post->ID, 'Counter_Start_Date', true ),
				'workshop_preparatory_date' => get_post_meta( $post->ID, 'workshop_preparatory_date', true ),
				'workshop_start_date'    => get_post_meta( $post->ID, 'workshop_start_date', true ) ?: get_post_meta( $post->ID, 'workshop_date', true ),
				'workshop_end_date'      => get_post_meta( $post->ID, 'workshop_end_date', true ),
				'workshop_regular_price' => get_post_meta( $post->ID, 'workshop_regular_price', true ) ?: get_post_meta( $post->ID, 'workshop_money', true ),
				'workshop_sale_price'    => get_post_meta( $post->ID, 'workshop_sale_price', true ),
				'workshop_mode'          => get_post_meta( $post->ID, 'workshop_mode', true ),
				'ggm_workshop_whatsapp_group_url' => get_post_meta( $post->ID, 'ggm_workshop_whatsapp_group_url', true ),
				'duration'               => get_post_meta( $post->ID, 'duration', true ),
				'is_free'                => get_post_meta( $post->ID, 'is_free', true ),
				'workshop_short_desc'    => get_post_meta( $post->ID, 'workshop_short_desc', true ),
				'ggm_workshop_discover_heading_override'      => get_post_meta( $post->ID, 'ggm_workshop_discover_heading_override', true ),
				'ggm_workshop_why_different_heading_override' => get_post_meta( $post->ID, 'ggm_workshop_why_different_heading_override', true ),
				'ggm_workshop_perfect_for_heading_override'   => get_post_meta( $post->ID, 'ggm_workshop_perfect_for_heading_override', true ),
				'ggm_workshop_faq_heading_override'           => get_post_meta( $post->ID, 'ggm_workshop_faq_heading_override', true ),
			),
			'you_will_discover'    => json_decode( $discover_raw, true ) ?: array(),
			'why_different_points' => json_decode( $diff_raw, true ) ?: array(),
			'perfect_for_you'      => json_decode( $perf_raw, true ) ?: array(),
			'faq'                  => json_decode( $faq_raw, true ) ?: array(),
			'time_slots'           => $time_slots,
		);
	}

	/**
	 * @param WP_Post $post
	 * @return array
	 */
	private static function export_one_course( $post ) {
		$lesson_items = array();
		if ( class_exists( 'GGM_Lesson' ) ) {
			foreach ( GGM_Lesson::get_for_course( $post->ID ) as $lesson ) {
				$lesson_items[] = array(
					'title'                => $lesson->post_title,
					'status'               => $lesson->post_status,
					'lesson_order'         => (int) get_post_meta( $lesson->ID, 'lesson_order', true ),
					'video_embed'          => get_post_meta( $lesson->ID, 'video_embed', true ),
					'lesson_duration'      => get_post_meta( $lesson->ID, 'lesson_duration', true ),
					'is_preview'           => get_post_meta( $lesson->ID, 'is_preview', true ),
					'what_to_expect'       => get_post_meta( $lesson->ID, 'what_to_expect', true ),
					'best_practices'       => get_post_meta( $lesson->ID, 'best_practices', true ),
					'lesson_resources_pdf' => get_post_meta( $lesson->ID, 'lesson_resources_pdf', true ),
					'lesson_summary'       => get_post_meta( $lesson->ID, 'lesson_summary', true ),
					'lesson_focus_areas'   => json_decode( get_post_meta( $lesson->ID, 'lesson_focus_areas', true ), true ) ?: array(),
				);
			}
		}

		return array(
			'title'                 => $post->post_title,
			'slug'                  => $post->post_name,
			'status'                => $post->post_status,
			'content'               => $post->post_content,
			'excerpt'               => $post->post_excerpt,
			'meta'                  => array(
				'course_price'              => get_post_meta( $post->ID, 'course_price', true ),
				'course_short_description'  => get_post_meta( $post->ID, 'course_short_description', true ),
				'course_duration'           => get_post_meta( $post->ID, 'course_duration', true ),
				'ggm_course_access_days'    => get_post_meta( $post->ID, 'ggm_course_access_days', true ),
				'course_lesson_count_label' => get_post_meta( $post->ID, 'course_lesson_count_label', true ),
				'course_instructor'         => get_post_meta( $post->ID, 'course_instructor', true ),
				'course_language'           => get_post_meta( $post->ID, 'course_language', true ),
				'course_thumbnail'          => get_post_meta( $post->ID, 'course_thumbnail', true ),
			),
			'lessons'               => $lesson_items,
		);
	}

	// ─── Import ──────────────────────────────────────────────────────────────

	/**
	 * Stream a members CSV into WordPress users. Existing accounts are matched
	 * by email or normalized 10-digit phone and updated; conflicting matches
	 * are skipped so two accounts are never silently merged.
	 *
	 * @param string $path Uploaded CSV temporary path.
	 * @return array|WP_Error
	 */
	public static function import_members_csv( $path, $row_offset = 0 ) {
		if ( ! wp_roles()->is_role( 'customer' ) ) {
			return new WP_Error(
				'ggm_customer_role_missing',
				__( 'The WooCommerce Customer role is unavailable. Activate WooCommerce and try the import again.', 'ggm-member-dashboard' )
			);
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'ggm_member_csv_open', __( 'The CSV file could not be opened.', 'ggm-member-dashboard' ) );
		}

		$headers = fgetcsv( $handle );
		if ( ! is_array( $headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'ggm_member_csv_empty', __( 'The CSV file is empty.', 'ggm-member-dashboard' ) );
		}
		$headers = array_map( static function ( $header ) {
			return sanitize_key( preg_replace( '/^\xEF\xBB\xBF/', '', trim( (string) $header ) ) );
		}, $headers );
		// Every supported profile column is optional. We only need at least one
		// identity column so rows can be matched or created without inventing an
		// account that the member can never use to log in.
		if ( ! in_array( 'email', $headers, true ) && ! in_array( 'mobile_no', $headers, true ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'ggm_member_csv_identity', __( 'The CSV needs at least an email or mobile_no column so members can be safely identified.', 'ggm-member-dashboard' ) );
		}

		global $wpdb;
		$email_map = array();
		foreach ( $wpdb->get_results( "SELECT ID, user_email FROM {$wpdb->users} WHERE user_email <> ''" ) as $item ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$email_map[ strtolower( $item->user_email ) ] = (int) $item->ID;
		}
		$phone_map = array();
		$phone_rows = $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('ggm_phone','billing_phone') AND meta_value <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $phone_rows as $item ) {
			$phone = self::normalize_member_phone( $item->meta_value );
			if ( $phone ) {
				if ( isset( $phone_map[ $phone ] ) && $phone_map[ $phone ] !== (int) $item->user_id ) {
					$phone_map[ $phone ] = -1;
				} else {
					$phone_map[ $phone ] = (int) $item->user_id;
				}
			}
		}

		$summary = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'processed' => 0, 'messages' => array() );
		$row_number = 1 + absint( $row_offset );
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		while ( false !== ( $values = fgetcsv( $handle ) ) ) {
			$row_number++;
			if ( 1 === count( $values ) && '' === trim( (string) $values[0] ) ) {
				continue;
			}
			$summary['processed']++;
			$values = array_pad( $values, count( $headers ), '' );
			$row    = array_combine( $headers, array_slice( $values, 0, count( $headers ) ) );
			$name   = sanitize_text_field( $row['full_name'] ?? '' );
			$raw_email = trim( (string) ( $row['email'] ?? '' ) );
			$email  = is_email( $raw_email ) ? sanitize_email( $raw_email ) : '';
			$phone  = self::normalize_member_phone( $row['mobile_no'] ?? '' );

			if ( ! $email && ! $phone ) {
				self::member_import_skip( $summary, $row_number, __( 'no valid email or 10-digit mobile number was supplied.', 'ggm-member-dashboard' ) );
				continue;
			}

			$email_id = $email ? ( $email_map[ strtolower( $email ) ] ?? 0 ) : 0;
			$phone_id = $phone ? ( $phone_map[ $phone ] ?? 0 ) : 0;
			if ( -1 === $phone_id ) {
				self::member_import_skip( $summary, $row_number, __( 'mobile number is already attached to multiple WordPress users; resolve that conflict first.', 'ggm-member-dashboard' ) );
				continue;
			}
			if ( $email_id && $phone_id && $email_id !== $phone_id ) {
				self::member_import_skip( $summary, $row_number, __( 'email and mobile number belong to different existing users.', 'ggm-member-dashboard' ) );
				continue;
			}
			$user_id = $email_id ?: $phone_id;
			if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
				self::member_import_skip( $summary, $row_number, __( 'the matched account is an administrator and cannot be changed by member import.', 'ggm-member-dashboard' ) );
				continue;
			}
			if ( ! $name && ! $user_id ) {
				$name = $email ? strstr( $email, '@', true ) : $phone;
			}
			$first   = '';
			$last    = '';
			$userarr = array();
			if ( $name ) {
				$parts = preg_split( '/\s+/', trim( $name ), 2 );
				$first = $parts[0] ?? $name;
				$last  = $parts[1] ?? '';
				$userarr = array( 'display_name' => $name, 'first_name' => $first, 'last_name' => $last );
			}
			if ( $email ) {
				$userarr['user_email'] = $email;
			}

			if ( $user_id ) {
				$userarr['ID'] = $user_id;
				$result = wp_update_user( $userarr );
			} else {
				$userarr['user_login'] = self::unique_member_login( $phone ?: strstr( $email, '@', true ) );
				$userarr['user_pass']  = wp_generate_password( 24, true, true );
				$userarr['role']       = 'customer';
				$result = wp_insert_user( $userarr );
			}

			if ( is_wp_error( $result ) ) {
				self::member_import_skip( $summary, $row_number, $result->get_error_message() );
				continue;
			}
			$user_id = (int) $result;
			$imported_user = get_userdata( $user_id );
			if ( $imported_user ) {
				$imported_user->set_role( 'customer' );
			}
			self::save_member_meta( $user_id, $row, $phone, $first, $last );
			if ( $email ) {
				$email_map[ strtolower( $email ) ] = $user_id;
			}
			if ( $phone ) {
				$phone_map[ $phone ] = $user_id;
			}
			isset( $userarr['ID'] ) ? $summary['updated']++ : $summary['created']++;
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $summary;
	}

	private static function normalize_member_phone( $value ) {
		$digits = preg_replace( '/\D+/', '', (string) $value );
		$digits = preg_replace( '/^(?:91|0)(?=\d{10}$)/', '', $digits );
		return 10 === strlen( $digits ) ? $digits : '';
	}

	private static function unique_member_login( $phone ) {
		$identity = sanitize_user( (string) $phone, true );
		$base = 'ggm_' . ( $identity ?: strtolower( wp_generate_password( 10, false, false ) ) );
		$login = $base;
		$i = 1;
		while ( username_exists( $login ) ) {
			$login = $base . '_' . $i++;
		}
		return $login;
	}

	private static function member_import_skip( array &$summary, $row, $reason ) {
		$summary['skipped']++;
		if ( count( $summary['messages'] ) < 100 ) {
			$summary['messages'][] = sprintf( __( 'Row %1$d: %2$s', 'ggm-member-dashboard' ), $row, $reason );
		}
	}

	private static function save_member_meta( $user_id, array $row, $phone, $first, $last ) {
		$plain_fields = array( 'pincode', 'gender', 'age', 'weight', 'bp', 'glucose_level', 'declaration' );
		if ( $phone ) { update_user_meta( $user_id, 'ggm_phone', $phone ); update_user_meta( $user_id, 'billing_phone', $phone ); }
		if ( ! empty( $row['full_name'] ) ) {
			if ( $first ) { update_user_meta( $user_id, 'billing_first_name', $first ); }
			if ( $last ) { update_user_meta( $user_id, 'billing_last_name', $last ); }
			update_user_meta( $user_id, 'ggm_full_name', sanitize_text_field( $row['full_name'] ) );
		}
		if ( ! empty( $row['full_address'] ) ) { $address = sanitize_textarea_field( $row['full_address'] ); update_user_meta( $user_id, 'ggm_full_address', $address ); update_user_meta( $user_id, 'billing_address_1', $address ); }
		if ( ! empty( $row['pincode'] ) ) { update_user_meta( $user_id, 'billing_postcode', sanitize_text_field( $row['pincode'] ) ); }
		foreach ( $plain_fields as $field ) {
			if ( isset( $row[ $field ] ) && '' !== trim( (string) $row[ $field ] ) ) { update_user_meta( $user_id, 'ggm_' . $field, sanitize_text_field( $row[ $field ] ) ); }
		}
		if ( ! empty( $row['diseases'] ) ) { update_user_meta( $user_id, 'ggm_diseases', sanitize_textarea_field( $row['diseases'] ) ); }
		if ( ! empty( $row['payment_details'] ) ) { update_user_meta( $user_id, 'ggm_payment_details', sanitize_textarea_field( $row['payment_details'] ) ); }
		update_user_meta( $user_id, 'ggm_imported_member', 1 );
		update_user_meta( $user_id, 'ggm_imported_at', current_time( 'mysql' ) );
	}

	/**
	 * Import a previously exported Workshops payload.
	 *
	 * A workshop is matched to an existing one by exact title; if found it's
	 * updated in place, otherwise a new workshop is created. Membership and
	 * mentor assignments are re-resolved by name against this site's data.
	 *
	 * @param array $payload Decoded export JSON (must contain an 'items' array).
	 * @return array{created:int,updated:int,skipped:int,messages:string[]}
	 */
	public static function import_workshops( array $payload ) {
		$items   = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();
		$summary = array(
			'created'  => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'messages' => array(),
		);

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = sanitize_text_field( $item['title'] ?? '' );
			if ( '' === $title ) {
				$summary['skipped']++;
				$summary['messages'][] = __( 'Skipped a row with no title.', 'ggm-member-dashboard' );
				continue;
			}

			$existing = self::find_post_by_title( $title, 'workshop' );
			$postarr  = array(
				'post_type'    => 'workshop',
				'post_title'   => $title,
				'post_name'    => sanitize_title( $item['slug'] ?? $title ),
				'post_content' => wp_kses_post( $item['content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( $item['excerpt'] ?? '' ),
				'post_status'  => in_array( $item['status'] ?? '', array( 'publish', 'draft', 'pending', 'private' ), true ) ? $item['status'] : 'draft',
			);

			$is_new = ! $existing;
			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				$post_id       = wp_update_post( $postarr, true );
			} else {
				$post_id = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$summary['skipped']++;
				$summary['messages'][] = sprintf(
					/* translators: 1: workshop title, 2: error message */
					__( 'Failed to save "%1$s": %2$s', 'ggm-member-dashboard' ),
					$title,
					is_wp_error( $post_id ) ? $post_id->get_error_message() : __( 'unknown error', 'ggm-member-dashboard' )
				);
				continue;
			}

			$meta        = is_array( $item['meta'] ?? null ) ? $item['meta'] : array();
			$text_fields = array( 'Counter_Start_Date', 'workshop_preparatory_date', 'workshop_start_date', 'workshop_end_date', 'workshop_mode', 'duration', 'is_free' );
			foreach ( $text_fields as $field ) {
				if ( isset( $meta[ $field ] ) ) {
					update_post_meta( $post_id, $field, sanitize_text_field( $meta[ $field ] ) );
				}
			}
			$block_heading_fields = array(
				'ggm_workshop_discover_heading_override',
				'ggm_workshop_why_different_heading_override',
				'ggm_workshop_perfect_for_heading_override',
				'ggm_workshop_faq_heading_override',
			);
			foreach ( $block_heading_fields as $field ) {
				// Missing keys in older exports leave any existing override intact.
				if ( ! array_key_exists( $field, $meta ) ) {
					continue;
				}
				$value = is_scalar( $meta[ $field ] ) ? trim( sanitize_text_field( (string) $meta[ $field ] ) ) : '';
				if ( '' === $value ) {
					delete_post_meta( $post_id, $field );
				} else {
					update_post_meta( $post_id, $field, $value );
				}
			}
			if ( isset( $meta['ggm_workshop_whatsapp_group_url'] ) ) {
				update_post_meta( $post_id, 'ggm_workshop_whatsapp_group_url', esc_url_raw( $meta['ggm_workshop_whatsapp_group_url'] ) );
			}
			// Allows the same basic inline formatting tags as the meta box save
			// (see GGM_Meta_Boxes::SHORT_DESC_ALLOWED_TAGS) instead of stripping
			// them via sanitize_text_field().
			if ( isset( $meta['workshop_short_desc'] ) && class_exists( 'GGM_Meta_Boxes' ) ) {
				update_post_meta( $post_id, 'workshop_short_desc', wp_kses( $meta['workshop_short_desc'], GGM_Meta_Boxes::SHORT_DESC_ALLOWED_TAGS ) );
			}
			if ( isset( $meta['workshop_regular_price'] ) ) {
				update_post_meta( $post_id, 'workshop_regular_price', (float) $meta['workshop_regular_price'] );
			}
			if ( isset( $meta['workshop_sale_price'] ) && '' !== trim( (string) $meta['workshop_sale_price'] ) ) {
				$regular = (float) ( $meta['workshop_regular_price'] ?? 0 );
				$sale    = (float) $meta['workshop_sale_price'];
				update_post_meta( $post_id, 'workshop_sale_price', $sale > 0 && $sale < $regular ? $sale : '' );
			}

			update_post_meta( $post_id, 'linked_course_id', self::resolve_course_id( $item['linked_course_title'] ?? '' ) );

			// mentor_names (array) is the current export format; mentor_name
			// (single string) is kept for reading exports produced before
			// multi-mentor support was added.
			$mentor_names = is_array( $item['mentor_names'] ?? null ) ? $item['mentor_names'] : array( $item['mentor_name'] ?? '' );
			$mentor_ids   = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'resolve_mentor_id' ), $mentor_names ) ) ) );
			update_post_meta( $post_id, 'ggm_mentor_ids', $mentor_ids );
			update_post_meta( $post_id, 'ggm_mentor_id', $mentor_ids[0] ?? 0 );

			update_post_meta( $post_id, 'ggm_you_will_discover', wp_json_encode( self::sanitize_repeater_rows( $item['you_will_discover'] ?? array(), array( 'description' ), array( 'image' ) ) ) );
			update_post_meta( $post_id, 'ggm_why_different_points', wp_json_encode( self::sanitize_repeater_rows( $item['why_different_points'] ?? array(), array(), array( 'icon' ) ) ) );
			update_post_meta( $post_id, 'ggm_perfect_for_you', wp_json_encode( self::sanitize_repeater_rows( $item['perfect_for_you'] ?? array(), array( 'description' ), array( 'image' ) ) ) );
			update_post_meta( $post_id, 'ggm_faq', wp_json_encode( self::sanitize_repeater_rows( $item['faq'] ?? array(), array( 'answer' ), array() ) ) );

			self::replace_time_slots( $post_id, is_array( $item['time_slots'] ?? null ) ? $item['time_slots'] : array() );

			$is_new ? $summary['created']++ : $summary['updated']++;
		}

		return $summary;
	}

	/**
	 * Import a previously exported Courses payload (with embedded lessons).
	 *
	 * A course is matched by exact title; its lessons are matched to existing
	 * lessons of that course by exact title, else created.
	 *
	 * @param array $payload Decoded export JSON (must contain an 'items' array).
	 * @return array{created:int,updated:int,skipped:int,messages:string[]}
	 */
	public static function import_courses( array $payload ) {
		$items   = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();
		$summary = array(
			'created'  => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'messages' => array(),
		);

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = sanitize_text_field( $item['title'] ?? '' );
			if ( '' === $title ) {
				$summary['skipped']++;
				$summary['messages'][] = __( 'Skipped a row with no title.', 'ggm-member-dashboard' );
				continue;
			}

			$existing = self::find_post_by_title( $title, 'course' );
			$postarr  = array(
				'post_type'    => 'course',
				'post_title'   => $title,
				'post_name'    => sanitize_title( $item['slug'] ?? $title ),
				'post_content' => wp_kses_post( $item['content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( $item['excerpt'] ?? '' ),
				'post_status'  => in_array( $item['status'] ?? '', array( 'publish', 'draft', 'pending', 'private' ), true ) ? $item['status'] : 'draft',
			);

			$is_new = ! $existing;
			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				$course_id     = wp_update_post( $postarr, true );
			} else {
				$course_id = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $course_id ) || ! $course_id ) {
				$summary['skipped']++;
				$summary['messages'][] = sprintf(
					/* translators: 1: course title, 2: error message */
					__( 'Failed to save "%1$s": %2$s', 'ggm-member-dashboard' ),
					$title,
					is_wp_error( $course_id ) ? $course_id->get_error_message() : __( 'unknown error', 'ggm-member-dashboard' )
				);
				continue;
			}

			$meta        = is_array( $item['meta'] ?? null ) ? $item['meta'] : array();
			$text_fields = array( 'course_price', 'course_short_description', 'course_duration', 'ggm_course_access_days', 'course_lesson_count_label', 'course_instructor', 'course_language' );
			foreach ( $text_fields as $field ) {
				if ( isset( $meta[ $field ] ) ) {
					update_post_meta( $course_id, $field, sanitize_text_field( $meta[ $field ] ) );
				}
			}
			if ( isset( $meta['course_thumbnail'] ) ) {
				update_post_meta( $course_id, 'course_thumbnail', esc_url_raw( $meta['course_thumbnail'] ) );
			}

			self::import_lessons_for_course( $course_id, is_array( $item['lessons'] ?? null ) ? $item['lessons'] : array() );

			$is_new ? $summary['created']++ : $summary['updated']++;
		}

		return $summary;
	}

	/**
	 * Create/update the lessons embedded in a course import row, matching
	 * existing lessons of that course by exact title.
	 *
	 * @param int   $course_id
	 * @param array $lesson_items
	 */
	private static function import_lessons_for_course( $course_id, array $lesson_items ) {
		if ( ! class_exists( 'GGM_Lesson' ) ) {
			return;
		}

		$existing_by_title = array();
		foreach ( GGM_Lesson::get_for_course( $course_id ) as $lesson ) {
			$existing_by_title[ $lesson->post_title ] = $lesson->ID;
		}

		foreach ( $lesson_items as $lesson_item ) {
			if ( ! is_array( $lesson_item ) ) {
				continue;
			}

			$lesson_title = sanitize_text_field( $lesson_item['title'] ?? '' );
			if ( '' === $lesson_title ) {
				continue;
			}

			$lesson_postarr = array(
				'post_type'   => 'lesson',
				'post_title'  => $lesson_title,
				'post_status' => in_array( $lesson_item['status'] ?? '', array( 'publish', 'draft' ), true ) ? $lesson_item['status'] : 'publish',
			);

			if ( isset( $existing_by_title[ $lesson_title ] ) ) {
				$lesson_postarr['ID'] = $existing_by_title[ $lesson_title ];
				$lesson_id            = wp_update_post( $lesson_postarr, true );
			} else {
				$lesson_id = wp_insert_post( $lesson_postarr, true );
			}

			if ( is_wp_error( $lesson_id ) || ! $lesson_id ) {
				continue;
			}

			update_post_meta( $lesson_id, 'course', $course_id );
			update_post_meta( $lesson_id, 'lesson_order', absint( $lesson_item['lesson_order'] ?? 0 ) );
			update_post_meta( $lesson_id, 'video_embed', wp_kses_post( $lesson_item['video_embed'] ?? '' ) );
			update_post_meta( $lesson_id, 'lesson_duration', sanitize_text_field( $lesson_item['lesson_duration'] ?? '' ) );
			update_post_meta( $lesson_id, 'is_preview', ! empty( $lesson_item['is_preview'] ) ? '1' : '' );
			update_post_meta( $lesson_id, 'what_to_expect', sanitize_textarea_field( $lesson_item['what_to_expect'] ?? '' ) );
			update_post_meta( $lesson_id, 'best_practices', sanitize_textarea_field( $lesson_item['best_practices'] ?? '' ) );
			update_post_meta( $lesson_id, 'lesson_resources_pdf', esc_url_raw( $lesson_item['lesson_resources_pdf'] ?? '' ) );
			update_post_meta( $lesson_id, 'lesson_summary', sanitize_textarea_field( $lesson_item['lesson_summary'] ?? '' ) );

			$focus_areas = array();
			foreach ( (array) ( $lesson_item['lesson_focus_areas'] ?? array() ) as $focus_row ) {
				if ( ! empty( $focus_row['focus_area'] ) ) {
					$focus_areas[] = array( 'focus_area' => sanitize_text_field( $focus_row['focus_area'] ) );
				}
			}
			update_post_meta( $lesson_id, 'lesson_focus_areas', wp_json_encode( $focus_areas ) );
		}
	}

	/**
	 * Replace all of a workshop's time slots with the imported set.
	 *
	 * @param int   $post_id
	 * @param array $slots
	 */
	private static function replace_time_slots( $post_id, array $slots ) {
		if ( ! class_exists( 'GGM_Workshop_Slot' ) ) {
			return;
		}

		GGM_Workshop_Slot::delete_missing( $post_id, array() ); // Empty keep-list deletes every existing slot.

		$order = 0;
		foreach ( $slots as $slot ) {
			if ( empty( $slot['start'] ) || empty( $slot['end'] ) ) {
				continue;
			}
			GGM_Workshop_Slot::create( array(
				'workshop_id'  => $post_id,
				'slot_type'    => sanitize_key( $slot['slot_type'] ?? 'live' ),
				'start_time'   => sanitize_text_field( $slot['start'] ),
				'end_time'     => sanitize_text_field( $slot['end'] ),
				'meeting_link' => esc_url_raw( $slot['meeting_link'] ?? '' ),
				'status'       => 'inactive' === ( $slot['status'] ?? 'active' ) ? 'inactive' : 'active',
				'sort_order'   => $order++,
			) );
		}
	}

	/**
	 * Resolve a course name (from an export file) to this site's course post
	 * ID. Returns 0 if blank or not found here.
	 *
	 * @param string $name
	 * @return int
	 */
	private static function resolve_course_id( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}
		$found = self::find_post_by_title( $name, 'course' );
		return $found ? (int) $found->ID : 0;
	}

	/**
	 * Resolve a mentor name (from an export file) to this site's ggm_mentor
	 * post ID. Returns 0 if blank or not found here.
	 *
	 * @param string $name
	 * @return int
	 */
	private static function resolve_mentor_id( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}
		$found = self::find_post_by_title( $name, 'ggm_mentor' );
		return $found ? (int) $found->ID : 0;
	}

	/**
	 * Find an existing post of a given type by exact title match.
	 *
	 * @param string $title
	 * @param string $post_type
	 * @return WP_Post|null
	 */
	private static function find_post_by_title( $title, $post_type ) {
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'title'          => $title,
		) );
		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Read a post meta value, falling back to a legacy key if the current
	 * (namespaced) key has never been saved for this post.
	 *
	 * @param int    $post_id
	 * @param string $key
	 * @param string $legacy_key
	 * @return string
	 */
	private static function meta_with_fallback( $post_id, $key, $legacy_key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( '' === $value ) {
			$value = get_post_meta( $post_id, $legacy_key, true );
		}
		return $value;
	}

	/**
	 * Sanitize repeater rows coming from an uploaded import file — each row's
	 * keys are whitelisted via sanitize_key() and values run through the same
	 * per-field sanitizer the meta box save uses (HTML vs. URL vs. text).
	 *
	 * $textarea_keys allow the same basic inline formatting tags as the meta
	 * box save (see GGM_Meta_Boxes::SHORT_DESC_ALLOWED_TAGS) instead of
	 * stripping them via sanitize_textarea_field().
	 *
	 * @param mixed $rows
	 * @param array $textarea_keys
	 * @param array $url_keys
	 * @return array
	 */
	private static function sanitize_repeater_rows( $rows, array $textarea_keys = array(), array $url_keys = array() ) {
		$allowed_tags = class_exists( 'GGM_Meta_Boxes' ) ? GGM_Meta_Boxes::SHORT_DESC_ALLOWED_TAGS : array();
		$out          = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = array();
			foreach ( $row as $k => $v ) {
				$key = sanitize_key( $k );
				if ( in_array( $key, $url_keys, true ) ) {
					$clean[ $key ] = esc_url_raw( (string) $v );
				} elseif ( in_array( $key, $textarea_keys, true ) ) {
					$clean[ $key ] = wp_kses( (string) $v, $allowed_tags );
				} else {
					$clean[ $key ] = sanitize_text_field( (string) $v );
				}
			}
			$out[] = $clean;
		}
		return $out;
	}
}
