<?php
/**
 * Database schema — creates and upgrades plugin tables.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Database {

	/**
	 * Run dbDelta to create/upgrade all plugin tables.
	 *
	 * Runs one-time data migrations FIRST (while the old column shapes still
	 * exist), then lets dbDelta bring the schema to its current shape.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		self::migrate_workshop_slots_to_v2();
		self::migrate_workshop_slots_to_v3();
		self::migrate_membership_access_to_v2();

		// wp_ggm_payments
		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_payments (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			workshop_id bigint(20) UNSIGNED DEFAULT NULL,
			workshop_slot_id bigint(20) UNSIGNED DEFAULT NULL,
			course_id bigint(20) UNSIGNED DEFAULT NULL,
			coupon_id bigint(20) UNSIGNED DEFAULT NULL,
			original_amount decimal(10,2) DEFAULT NULL,
			discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			credit_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			pricing_mode varchar(20) DEFAULT NULL,
			contribution_option_id varchar(64) DEFAULT NULL,
			contribution_label varchar(255) DEFAULT NULL,
			contribution_amount decimal(10,2) DEFAULT NULL,
			razorpay_order_id varchar(255) DEFAULT NULL,
			razorpay_payment_id varchar(255) DEFAULT NULL,
			razorpay_signature varchar(500) DEFAULT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(10) NOT NULL DEFAULT 'INR',
			status enum('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_forms (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			slug varchar(191) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			current_version int(11) UNSIGNED NOT NULL DEFAULT 1,
			settings_json longtext DEFAULT NULL,
			created_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_form_versions (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id bigint(20) UNSIGNED NOT NULL,
			version int(11) UNSIGNED NOT NULL,
			schema_json longtext NOT NULL,
			created_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY form_version (form_id,version),
			KEY form_id (form_id)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_form_assignments (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id bigint(20) UNSIGNED NOT NULL,
			location_type varchar(30) NOT NULL,
			object_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			PRIMARY KEY (id),
			UNIQUE KEY form_location (form_id,location_type,object_id),
			KEY location_object (location_type,object_id)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_form_submissions (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id bigint(20) UNSIGNED NOT NULL,
			form_version int(11) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			context_type varchar(20) NOT NULL DEFAULT 'dashboard',
			context_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			answers_json longtext NOT NULL,
			schema_snapshot_json longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'submitted',
			submitted_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			additional_completed_at datetime DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			reviewed_by bigint(20) UNSIGNED DEFAULT NULL,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY user_id (user_id),
			KEY context (context_type,context_id),
			KEY review_state (reviewed_at),
			KEY submitted_at (submitted_at)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_form_files (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) UNSIGNED NOT NULL,
			field_key varchar(64) NOT NULL,
			original_name varchar(255) NOT NULL,
			stored_name varchar(255) NOT NULL,
			relative_path varchar(500) NOT NULL,
			mime_type varchar(100) NOT NULL,
			file_size bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY submission_id (submission_id)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_form_payments (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id bigint(20) UNSIGNED NOT NULL,
			submission_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			payment_key varchar(64) NOT NULL DEFAULT 'primary',
			label varchar(255) DEFAULT NULL,
			description varchar(500) DEFAULT NULL,
			razorpay_order_id varchar(255) DEFAULT NULL,
			razorpay_payment_id varchar(255) DEFAULT NULL,
			razorpay_signature varchar(500) DEFAULT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(10) NOT NULL DEFAULT 'INR',
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY submission_id (submission_id),
			KEY user_id (user_id),
			KEY razorpay_order_id (razorpay_order_id),
			KEY status (status)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_diseases (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY title (title(191)),
			KEY status (status)
		) $charset_collate;" );

		// Immutable customer credit ledger. Positive rows are admin grants;
		// negative rows reserve/apply credit to a checkout.
		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_credit_transactions (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			type enum('admin_credit','purchase') NOT NULL DEFAULT 'admin_credit',
			status enum('available','reserved','applied','released') NOT NULL DEFAULT 'available',
			payment_id bigint(20) UNSIGNED DEFAULT NULL,
			admin_user_id bigint(20) UNSIGNED DEFAULT NULL,
			note varchar(500) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY payment_credit (payment_id),
			KEY user_status (user_id, status)
		) $charset_collate;" );

		// wp_ggm_coupons
		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_coupons (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code varchar(50) NOT NULL DEFAULT '',
			name varchar(255) NOT NULL DEFAULT '',
			description text DEFAULT NULL,
			discount_type enum('fixed','percentage') NOT NULL DEFAULT 'fixed',
			discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
			max_discount decimal(10,2) DEFAULT NULL,
			min_purchase decimal(10,2) DEFAULT NULL,
			max_purchase decimal(10,2) DEFAULT NULL,
			start_date datetime DEFAULT NULL,
			expiry_date datetime DEFAULT NULL,
			max_uses int(11) DEFAULT NULL,
			max_uses_per_user int(11) DEFAULT NULL,
			applicable_workshops longtext DEFAULT NULL,
			status enum('active','inactive') NOT NULL DEFAULT 'active',
			used_count int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY code (code)
		) $charset_collate;" );

		// wp_ggm_coupon_usage
		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_coupon_usage (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			coupon_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			payment_id bigint(20) UNSIGNED DEFAULT NULL,
			workshop_id bigint(20) UNSIGNED DEFAULT NULL,
			original_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			final_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			used_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY coupon_id (coupon_id),
			KEY user_id (user_id)
		) $charset_collate;" );

		// wp_ggm_workshop_slots — each slot is a daily-recurring session: its
		// own start/end time-of-day (no date — it repeats every day between
		// the parent workshop's Start Date and End Date), its own Zoom/Meet
		// link, a Live/Repeat/Morning Repeat type label, and its own
		// active/inactive (enable/disable) toggle.
		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_workshop_slots (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			workshop_id bigint(20) UNSIGNED NOT NULL,
			slot_type varchar(20) NOT NULL DEFAULT 'live',
			start_time time NOT NULL DEFAULT '00:00:00',
			end_time time NOT NULL DEFAULT '00:00:00',
			meeting_link varchar(500) DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			status enum('active','inactive') NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY workshop_id (workshop_id)
		) $charset_collate;" );

		// wp_ggm_otp_logs
		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_otp_logs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			identifier varchar(255) NOT NULL DEFAULT '',
			otp varchar(10) NOT NULL DEFAULT '',
			type enum('sms','whatsapp','email') NOT NULL DEFAULT 'email',
			provider varchar(50) NOT NULL DEFAULT '',
			attempts int(11) NOT NULL DEFAULT 0,
			verified tinyint(1) NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY identifier (identifier)
		) $charset_collate;" );

		// wp_ggm_workshop_access
		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_workshop_access (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			workshop_id bigint(20) UNSIGNED NOT NULL,
			slot_id bigint(20) UNSIGNED DEFAULT NULL,
			payment_id bigint(20) UNSIGNED DEFAULT NULL,
			granted_via enum('membership','direct','free') NOT NULL DEFAULT 'direct',
			granted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_workshop (user_id, workshop_id)
		) $charset_collate;" );

		// wp_ggm_course_access — mirrors wp_ggm_workshop_access, for direct
		// one-time course purchases.
		dbDelta( "CREATE TABLE {$wpdb->prefix}ggm_course_access (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL,
			payment_id bigint(20) UNSIGNED DEFAULT NULL,
			granted_via enum('direct','free') NOT NULL DEFAULT 'direct',
			granted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY expires_at (expires_at)
		) $charset_collate;" );

		update_option( 'ggm_db_version', GGM_DB_VERSION );
	}

	/**
	 * One-time upgrade of wp_ggm_workshop_slots from the v1 shape
	 * (start_time/end_time as bare "HH:MM" strings, shared workshop-level
	 * date) to v2 (each slot carries its own full start/end datetime, a
	 * type, and a meeting link). No-ops once already migrated.
	 */
	private static function migrate_workshop_slots_to_v2() {
		global $wpdb;
		$table = $wpdb->prefix . 'ggm_workshop_slots';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return; // Fresh install — dbDelta below creates the v2 shape directly.
		}

		$start_time_type = $wpdb->get_var( $wpdb->prepare(
			"SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'start_time'",
			DB_NAME,
			$table
		) );

		if ( 'datetime' === $start_time_type ) {
			return; // Already migrated.
		}

		if ( ! $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'slot_type'",
			DB_NAME,
			$table
		) ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN slot_type varchar(20) NOT NULL DEFAULT 'live'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN start_time_v2 datetime NULL, ADD COLUMN end_time_v2 datetime NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Backfill each slot's new full datetime from its old "HH:MM" value
		// combined with its parent workshop's single Workshop Date field.
		$rows = $wpdb->get_results( "SELECT id, workshop_id, start_time, end_time FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as $row ) {
			$workshop_date = get_post_meta( $row->workshop_id, 'workshop_date', true ) ?: current_time( 'Y-m-d' );
			$start_ts      = strtotime( $workshop_date . ' ' . $row->start_time );
			$end_ts        = strtotime( $workshop_date . ' ' . $row->end_time );

			$wpdb->update(
				$table,
				array(
					'start_time_v2' => $start_ts ? date( 'Y-m-d H:i:s', $start_ts ) : current_time( 'mysql' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					'end_time_v2'   => $end_ts ? date( 'Y-m-d H:i:s', $end_ts ) : current_time( 'mysql' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				),
				array( 'id' => $row->id )
			);
		}

		// Drop the old time-only columns and the never-used seat_limit /
		// instructor_name / meeting_type columns, then promote the v2
		// datetime columns to the real start_time/end_time names.
		$wpdb->query( "ALTER TABLE {$table}
			DROP COLUMN start_time,
			DROP COLUMN end_time,
			DROP COLUMN seat_limit,
			DROP COLUMN instructor_name,
			DROP COLUMN meeting_type" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query( "ALTER TABLE {$table}
			CHANGE start_time_v2 start_time datetime NOT NULL,
			CHANGE end_time_v2 end_time datetime NOT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * One-time upgrade of wp_ggm_workshop_slots from the v2 shape (each slot
	 * carries a full one-off start/end datetime) to v3 (each slot carries
	 * only a start/end time-of-day, recurring every day between the parent
	 * workshop's Start Date and End Date). Only the time-of-day portion of
	 * the old datetime is kept — the date it was originally entered for is
	 * discarded, since the slot now applies to every day in range. No-ops
	 * once already migrated.
	 */
	private static function migrate_workshop_slots_to_v3() {
		global $wpdb;
		$table = $wpdb->prefix . 'ggm_workshop_slots';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return; // Fresh install — dbDelta above creates the v3 shape directly.
		}

		$start_time_type = $wpdb->get_var( $wpdb->prepare(
			"SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'start_time'",
			DB_NAME,
			$table
		) );

		if ( 'datetime' !== $start_time_type ) {
			return; // Already migrated (or a fresh v3 table).
		}

		$wpdb->query( "ALTER TABLE {$table}
			ADD COLUMN start_time_v3 time NULL,
			ADD COLUMN end_time_v3 time NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query( "UPDATE {$table} SET start_time_v3 = TIME(start_time), end_time_v3 = TIME(end_time)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query( "ALTER TABLE {$table}
			DROP COLUMN start_time,
			DROP COLUMN end_time" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query( "ALTER TABLE {$table}
			CHANGE start_time_v3 start_time time NOT NULL DEFAULT '00:00:00',
			CHANGE end_time_v3 end_time time NOT NULL DEFAULT '00:00:00'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * One-time upgrade: before the Membership system is removed, grant every
	 * user with an active membership permanent direct access (in
	 * wp_ggm_workshop_access / wp_ggm_course_access) to whatever that
	 * membership currently unlocks for them — a workshop/course gated by
	 * "Required Membership", or one included in the plan's allowed-workshops
	 * list. No-ops once already run (guarded by an option flag) and is safe
	 * to run even if the membership tables no longer exist.
	 */
	private static function migrate_membership_access_to_v2() {
		if ( get_option( 'ggm_membership_access_migrated' ) ) {
			return;
		}

		global $wpdb;
		$memberships_table      = $wpdb->prefix . 'ggm_memberships';
		$user_memberships_table = $wpdb->prefix . 'ggm_user_memberships';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $user_memberships_table ) ) !== $user_memberships_table
			|| $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $memberships_table ) ) !== $memberships_table
		) {
			update_option( 'ggm_membership_access_migrated', 1 );
			return; // Nothing to migrate — membership tables were never created.
		}

		$active_memberships = $wpdb->get_results(
			"SELECT user_id, membership_id FROM {$user_memberships_table} WHERE status = 'active' AND (end_date IS NULL OR end_date > NOW())" // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( empty( $active_memberships ) ) {
			update_option( 'ggm_membership_access_migrated', 1 );
			return;
		}

		$plans_by_id = array();
		foreach ( $wpdb->get_results( "SELECT id, allowed_workshops FROM {$memberships_table}" ) as $plan ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$plans_by_id[ (int) $plan->id ] = json_decode( $plan->allowed_workshops ?? '[]', true ) ?: array();
		}

		// Workshops directly gated via "Required Membership" (ggm_membership_id meta).
		$gated_workshops = get_posts( array(
			'post_type'      => array( 'workshop', 'ggm_workshop' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => 'ggm_membership_id', 'value' => 0, 'compare' => '>' ) ),
		) );
		$workshops_by_plan = array();
		foreach ( $gated_workshops as $wid ) {
			$plan_id                          = (int) get_post_meta( $wid, 'ggm_membership_id', true );
			$workshops_by_plan[ $plan_id ][] = $wid;
		}

		// Courses directly gated via course_membership_level.
		$gated_courses = get_posts( array(
			'post_type'      => 'course',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => 'course_membership_level', 'value' => 0, 'compare' => '>' ) ),
		) );
		$courses_by_plan = array();
		foreach ( $gated_courses as $cid ) {
			$plan_id                       = (int) get_post_meta( $cid, 'course_membership_level', true );
			$courses_by_plan[ $plan_id ][] = $cid;
		}

		foreach ( $active_memberships as $row ) {
			$user_id = (int) $row->user_id;
			$plan_id = (int) $row->membership_id;

			$workshop_ids = array_unique( array_merge(
				$workshops_by_plan[ $plan_id ] ?? array(),
				$plans_by_id[ $plan_id ] ?? array()
			) );

			foreach ( $workshop_ids as $workshop_id ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}ggm_workshop_access (user_id, workshop_id, granted_via, granted_at)
					 VALUES (%d, %d, 'membership', %s)
					 ON DUPLICATE KEY UPDATE granted_via = granted_via",
					$user_id,
					$workshop_id,
					current_time( 'mysql' )
				) );
			}

			foreach ( $courses_by_plan[ $plan_id ] ?? array() as $course_id ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}ggm_course_access (user_id, course_id, granted_via, granted_at)
					 VALUES (%d, %d, 'direct', %s)
					 ON DUPLICATE KEY UPDATE granted_via = granted_via",
					$user_id,
					$course_id,
					current_time( 'mysql' )
				) );
			}
		}

		update_option( 'ggm_membership_access_migrated', 1 );
	}
}
