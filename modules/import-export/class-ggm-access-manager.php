<?php
/** Course access expiry and legacy workshop enrollment imports. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GGM_Access_Manager {
	const DAYS_META = 'ggm_course_access_days';

	public static function init() {
		add_action( 'add_meta_boxes_course', array( __CLASS__, 'add_course_expiry_box' ) );
		add_action( 'save_post_course', array( __CLASS__, 'save_course_expiry' ), 10, 2 );
		add_action( 'admin_post_ggm_import_legacy_enrollments', array( __CLASS__, 'import_legacy_enrollments' ) );
		add_action( 'admin_post_ggm_legacy_enrollment_sample', array( __CLASS__, 'download_sample' ) );
		// Run before the older Members-page handler so manual grants also receive
		// expiry and workshop-linked-course access.
		add_action( 'wp_ajax_ggm_assign_member_content', array( __CLASS__, 'ajax_assign_content' ), 5 );
		add_action( 'wp_ajax_ggm_bulk_assign_member_content', array( __CLASS__, 'ajax_bulk_assign_content' ) );
		add_action( 'wp_ajax_ggm_get_member_access', array( __CLASS__, 'ajax_get_member_access' ) );
		add_action( 'wp_ajax_ggm_remove_member_access', array( __CLASS__, 'ajax_remove_member_access' ) );
	}

	public static function get_access_days( $course_id ) {
		$value = get_post_meta( $course_id, self::DAYS_META, true );
		return '' === $value ? 30 : min( 36500, absint( $value ) );
	}

	public static function calculate_expiry( $course_id, $granted_at = '' ) {
		$days = self::get_access_days( $course_id );
		if ( 0 === $days ) { return null; }
		try {
			$date = new DateTimeImmutable( $granted_at ?: 'now', wp_timezone() );
			return $date->modify( '+' . $days . ' days' )->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( $days * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		}
	}

	/** Grant or renew course access using the course's current duration. */
	public static function grant_course( $user_id, $course_id, $payment_id = 0, $via = 'direct', $granted_at = '' ) {
		global $wpdb;
		$user_id    = absint( $user_id );
		$course_id  = absint( $course_id );
		$granted_at = $granted_at ?: current_time( 'mysql' );
		$expires_at = self::calculate_expiry( $course_id, $granted_at );
		if ( ! $user_id || 'course' !== get_post_type( $course_id ) ) { return false; }
		$table = $wpdb->prefix . 'ggm_course_access';
		$row = array(
			'user_id' => $user_id, 'course_id' => $course_id, 'payment_id' => absint( $payment_id ) ?: null,
			'granted_via' => in_array( $via, array( 'direct', 'free' ), true ) ? $via : 'direct',
			'granted_at' => $granted_at, 'expires_at' => $expires_at,
		);
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id=%d AND course_id=%d", $user_id, $course_id ) );
		if ( $existing && ! $payment_id ) { unset( $row['payment_id'] ); }
		return $existing
			? false !== $wpdb->update( $table, $row, array( 'id' => absint( $existing ) ) )
			: (bool) $wpdb->insert( $table, $row );
	}

	/** Grant workshop access and automatically grant its selected course. */
	public static function grant_workshop_bundle( $user_id, $workshop_id, $payment_id = 0, $granted_at = '' ) {
		global $wpdb;
		$granted_at = $granted_at ?: current_time( 'mysql' );
		$table = $wpdb->prefix . 'ggm_workshop_access';
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id=%d AND workshop_id=%d", absint( $user_id ), absint( $workshop_id ) ) );
		$row = array( 'user_id' => absint( $user_id ), 'workshop_id' => absint( $workshop_id ), 'payment_id' => absint( $payment_id ) ?: null, 'granted_via' => 'direct', 'granted_at' => $granted_at );
		if ( $existing && ! $payment_id ) { unset( $row['payment_id'] ); }
		$ok = $existing ? false !== $wpdb->update( $table, $row, array( 'id' => absint( $existing ) ) ) : (bool) $wpdb->insert( $table, $row );
		$course = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_linked_course( $workshop_id ) : null;
		if ( $course ) { self::grant_course( $user_id, $course->ID, $payment_id, 'direct', $granted_at ); }
		return $ok;
	}

	public static function add_course_expiry_box() {
		add_meta_box( 'ggm-course-access-expiry', __( 'Course Access Expiry', 'ggm-member-dashboard' ), array( __CLASS__, 'render_course_expiry_box' ), 'course', 'side', 'high' );
	}

	public static function render_course_expiry_box( $post ) {
		wp_nonce_field( 'ggm_course_expiry_' . $post->ID, 'ggm_course_expiry_nonce' );
		$days = self::get_access_days( $post->ID );
		?>
		<p><label for="ggm-course-access-days"><strong><?php esc_html_e( 'Access duration (days)', 'ggm-member-dashboard' ); ?></strong></label></p>
		<input type="number" id="ggm-course-access-days" name="ggm_course_access_days" value="<?php echo esc_attr( $days ); ?>" min="0" max="36500" step="1" style="width:100%;">
		<p class="description"><?php esc_html_e( 'Access expires this many days after each enrollment. Use 0 for lifetime access. This affects new enrollments and renewals; existing access keeps its original expiry.', 'ggm-member-dashboard' ); ?></p>
		<?php
	}

	public static function save_course_expiry( $post_id, $post ) {
		if ( ! isset( $_POST['ggm_course_expiry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ggm_course_expiry_nonce'] ) ), 'ggm_course_expiry_' . $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		update_post_meta( $post_id, self::DAYS_META, min( 36500, absint( $_POST['ggm_course_access_days'] ?? 30 ) ) );
	}

	public static function ajax_assign_content() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) ); }
		$user_id = absint( $_POST['user_id'] ?? 0 );
		$item_id = absint( $_POST['item_id'] ?? 0 );
		$type = sanitize_key( $_POST['item_type'] ?? '' );
		$post = get_post( $item_id );
		if ( ! get_userdata( $user_id ) || ! $post || ! in_array( $type, array( 'course', 'workshop' ), true ) || ( 'course' === $type && 'course' !== $post->post_type ) || ( 'workshop' === $type && ! in_array( $post->post_type, array( 'workshop', 'ggm_workshop' ), true ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a valid member and item.', 'ggm-member-dashboard' ) ) );
		}
		$ok = 'course' === $type ? self::grant_course( $user_id, $item_id ) : self::grant_workshop_bundle( $user_id, $item_id );
		wp_send_json_success( array( 'message' => $ok ? sprintf( __( '%s assigned successfully.', 'ggm-member-dashboard' ), $post->post_title ) : __( 'Access is already assigned or could not be updated.', 'ggm-member-dashboard' ) ) );
	}

	/** Assign one workshop or course to up to one visible Members-page batch. */
	public static function ajax_bulk_assign_content() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 );
		}

		$raw_user_ids = isset( $_POST['user_ids'] ) && is_array( $_POST['user_ids'] ) ? wp_unslash( $_POST['user_ids'] ) : array();
		$user_ids     = array_values( array_unique( array_filter( array_map( 'absint', $raw_user_ids ) ) ) );
		$item_id      = absint( $_POST['item_id'] ?? 0 );
		$type         = sanitize_key( $_POST['item_type'] ?? '' );
		$post         = get_post( $item_id );

		if ( empty( $user_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one member.', 'ggm-member-dashboard' ) ) );
		}
		if ( count( $user_ids ) > 20 ) {
			wp_send_json_error( array( 'message' => __( 'Bulk assignment is limited to the 20 members visible on the current page.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! $post || ! in_array( $type, array( 'course', 'workshop' ), true ) || ( 'course' === $type && 'course' !== $post->post_type ) || ( 'workshop' === $type && ! in_array( $post->post_type, array( 'workshop', 'ggm_workshop' ), true ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a valid workshop or course.', 'ggm-member-dashboard' ) ) );
		}

		global $wpdb;
		$assigned = 0;
		$renewed  = 0;
		$failed   = 0;
		foreach ( $user_ids as $user_id ) {
			if ( ! get_userdata( $user_id ) ) {
				$failed++;
				continue;
			}

			if ( 'course' === $type ) {
				$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ggm_course_access WHERE user_id=%d AND course_id=%d", $user_id, $item_id ) );
				$ok       = self::grant_course( $user_id, $item_id );
			} else {
				$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ggm_workshop_access WHERE user_id=%d AND workshop_id=%d", $user_id, $item_id ) );
				$ok       = self::grant_workshop_bundle( $user_id, $item_id );
			}

			if ( ! $ok ) {
				$failed++;
			} elseif ( $existing ) {
				$renewed++;
			} else {
				$assigned++;
			}
		}

		$message = sprintf(
			/* translators: 1: item title, 2: new assignments, 3: renewed assignments, 4: failed assignments. */
			__( '%1$s processed: %2$d newly assigned, %3$d renewed, %4$d failed.', 'ggm-member-dashboard' ),
			$post->post_title,
			$assigned,
			$renewed,
			$failed
		);
		wp_send_json_success( array(
			'message'  => $message,
			'assigned' => $assigned,
			'renewed'  => $renewed,
			'failed'   => $failed,
		) );
	}

	/** Return all explicitly assigned workshop/course access for one member. */
	public static function ajax_get_member_access() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 ); }
		$user_id = absint( $_POST['user_id'] ?? 0 );
		if ( ! get_userdata( $user_id ) ) { wp_send_json_error( array( 'message' => __( 'Member not found.', 'ggm-member-dashboard' ) ) ); }

		global $wpdb;
		$items = array();
		$workshops = $wpdb->get_results( $wpdb->prepare( "SELECT workshop_id item_id, granted_at FROM {$wpdb->prefix}ggm_workshop_access WHERE user_id=%d ORDER BY granted_at DESC", $user_id ) );
		foreach ( $workshops as $row ) {
			$items[] = array( 'type'=>'workshop', 'item_id'=>(int)$row->item_id, 'title'=>get_the_title( $row->item_id ) ?: sprintf( __( 'Workshop #%d', 'ggm-member-dashboard' ), $row->item_id ), 'granted_at'=>$row->granted_at, 'expires_at'=>'', 'expired'=>false );
		}
		$courses = $wpdb->get_results( $wpdb->prepare( "SELECT course_id item_id, granted_at, expires_at FROM {$wpdb->prefix}ggm_course_access WHERE user_id=%d ORDER BY granted_at DESC", $user_id ) );
		$now = current_time( 'mysql' );
		foreach ( $courses as $row ) {
			$items[] = array( 'type'=>'course', 'item_id'=>(int)$row->item_id, 'title'=>get_the_title( $row->item_id ) ?: sprintf( __( 'Course #%d', 'ggm-member-dashboard' ), $row->item_id ), 'granted_at'=>$row->granted_at, 'expires_at'=>$row->expires_at ?: '', 'expired'=>! empty( $row->expires_at ) && $row->expires_at <= $now );
		}
		wp_send_json_success( array( 'items'=>$items ) );
	}

	/** Revoke one explicit workshop or course access row; payment history remains. */
	public static function ajax_remove_member_access() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 ); }
		global $wpdb;
		$user_id = absint( $_POST['user_id'] ?? 0 );
		$item_id = absint( $_POST['item_id'] ?? 0 );
		$type = sanitize_key( $_POST['item_type'] ?? '' );
		if ( ! get_userdata( $user_id ) || ! $item_id || ! in_array( $type, array( 'workshop','course' ), true ) ) { wp_send_json_error( array( 'message' => __( 'Invalid access record.', 'ggm-member-dashboard' ) ) ); }
		$table = $wpdb->prefix . ( 'course' === $type ? 'ggm_course_access' : 'ggm_workshop_access' );
		$column = 'course' === $type ? 'course_id' : 'workshop_id';
		$deleted = $wpdb->delete( $table, array( 'user_id'=>$user_id, $column=>$item_id ), array( '%d','%d' ) );
		if ( ! $deleted ) { wp_send_json_error( array( 'message' => __( 'Access was not found or could not be removed.', 'ggm-member-dashboard' ) ) ); }
		do_action( 'ggm_member_access_removed', $user_id, $type, $item_id, get_current_user_id() );
		wp_send_json_success( array( 'message' => __( 'Access removed successfully.', 'ggm-member-dashboard' ) ) );
	}

	private static function normalize_phone( $value ) {
		return ggm_normalize_member_phone( $value, '+91' );
	}

	private static function normalized_headers( array $headers ) {
		$aliases = array(
			'name'=>'name', 'fullname'=>'name', 'full_name'=>'name', 'customername'=>'name',
			'email'=>'email', 'emailaddress'=>'email', 'email_address'=>'email',
			'phone'=>'phone', 'mobile'=>'phone', 'mobileno'=>'phone', 'mobile_no'=>'phone', 'phonenumber'=>'phone', 'phone_number'=>'phone', 'whatsapp'=>'phone',
		);
		return array_map( static function ( $header ) use ( $aliases ) {
			$key = sanitize_key( preg_replace( '/^\xEF\xBB\xBF/', '', strtolower( trim( (string) $header ) ) ) );
			return $aliases[ $key ] ?? $key;
		}, $headers );
	}

	private static function unique_login( $base ) {
		$base = sanitize_user( $base, true ) ?: 'ggm_member';
		$login = $base; $i = 1;
		while ( username_exists( $login ) ) { $login = $base . '_' . $i++; }
		return $login;
	}

	public static function import_legacy_enrollments() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) ); }
		check_admin_referer( 'ggm_import_legacy_enrollments' );
		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );
		$file = $_FILES['legacy_members_file'] ?? array();
		$linked_course = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_linked_course( $workshop_id ) : null;
		if ( ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) || ! $linked_course || empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 ) || (int) ( $file['size'] ?? 0 ) > 10 * MB_IN_BYTES ) {
			self::finish_import( array( 'error' => __( 'Select a workshop with a linked course and a valid CSV file (maximum 10 MB).', 'ggm-member-dashboard' ) ) );
		}
		$handle = fopen( $file['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$headers = $handle ? fgetcsv( $handle ) : false;
		$headers = $headers ? self::normalized_headers( $headers ) : array();
		if ( ! $handle || ( ! in_array( 'email', $headers, true ) && ! in_array( 'phone', $headers, true ) ) ) {
			if ( $handle ) { fclose( $handle ); }
			self::finish_import( array( 'error' => __( 'CSV must contain an email or phone/mobile column.', 'ggm-member-dashboard' ) ) );
		}

		global $wpdb;
		$email_map = array(); $phone_map = array();
		foreach ( $wpdb->get_results( "SELECT ID,user_email FROM {$wpdb->users} WHERE user_email<>''" ) as $u ) { $email_map[ strtolower( $u->user_email ) ] = (int) $u->ID; }
		foreach ( $wpdb->get_results( "SELECT user_id,meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('ggm_phone','billing_phone')" ) as $m ) {
			$phone = self::normalize_phone( $m->meta_value );
			if ( $phone ) { $phone_map[ $phone ] = isset( $phone_map[ $phone ] ) && $phone_map[ $phone ] !== (int) $m->user_id ? -1 : (int) $m->user_id; }
		}
		$summary = array( 'processed'=>0, 'created'=>0, 'matched'=>0, 'enrolled'=>0, 'skipped'=>0, 'messages'=>array() );
		$row_no = 1;
		while ( false !== ( $values = fgetcsv( $handle ) ) ) {
			$row_no++; if ( 1 === count( $values ) && '' === trim( (string) $values[0] ) ) { continue; }
			$summary['processed']++;
			$values = array_pad( $values, count( $headers ), '' );
			$row = array_combine( $headers, array_slice( $values, 0, count( $headers ) ) );
			$name = sanitize_text_field( $row['name'] ?? '' );
			$email = is_email( trim( (string) ( $row['email'] ?? '' ) ) ) ? sanitize_email( $row['email'] ) : '';
			$phone = self::normalize_phone( $row['phone'] ?? '' );
			$email_id = $email ? ( $email_map[ strtolower( $email ) ] ?? 0 ) : 0;
			$phone_id = $phone ? ( $phone_map[ $phone ] ?? 0 ) : 0;
			if ( ( $email_id && $phone_id && $email_id !== $phone_id ) || -1 === $phone_id || ( ! $email && ! $phone ) ) { self::skip( $summary, $row_no, 'identity is missing or conflicts with an existing account' ); continue; }
			$user_id = $email_id ?: $phone_id;
			if ( $user_id && user_can( $user_id, 'manage_options' ) ) { self::skip( $summary, $row_no, 'matched account is an administrator' ); continue; }
			if ( ! $user_id ) {
				$display = $name ?: ( $email ? strstr( $email, '@', true ) : $phone );
				$login = self::unique_login( $email ? strstr( $email, '@', true ) : 'ggm_' . $phone );
				$account_email = $email ?: $login . '@guest.invalid';
				$parts = preg_split( '/\s+/', trim( $display ), 2 );
				$user_id = wp_insert_user( array( 'user_login'=>$login, 'user_pass'=>wp_generate_password( 24, true, true ), 'user_email'=>$account_email, 'display_name'=>$display, 'first_name'=>$parts[0] ?? '', 'last_name'=>$parts[1] ?? '', 'role'=>get_role( 'customer' ) ? 'customer' : 'subscriber' ) );
				if ( is_wp_error( $user_id ) ) { self::skip( $summary, $row_no, $user_id->get_error_message() ); continue; }
				$summary['created']++; if ( $email ) { $email_map[ strtolower( $email ) ] = (int) $user_id; } if ( $phone ) { $phone_map[ $phone ] = (int) $user_id; }
			} else { $summary['matched']++; }
			if ( $phone ) { update_user_meta( $user_id, 'ggm_phone', $phone ); update_user_meta( $user_id, 'billing_phone', $phone ); }

			$access_table = $wpdb->prefix . 'ggm_workshop_access';
			$already = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$access_table} WHERE user_id=%d AND workshop_id=%d", $user_id, $workshop_id ) );
			$payment_id = 0;
			if ( ! $already ) {
				$payment_id = GGM_Payment::create( array( 'user_id'=>$user_id, 'workshop_id'=>$workshop_id, 'original_amount'=>class_exists( 'GGM_Workshop' ) ? GGM_Workshop::resolve_price( $workshop_id ) : 0, 'amount'=>0, 'currency'=>ggm_get_setting( 'ggm_currency', 'INR' ), 'razorpay_order_id'=>'legacy-' . $workshop_id . '-' . $user_id ) );
				if ( $payment_id ) { GGM_Payment::mark_success( $payment_id, 'legacy-import', '' ); }
			}
			if ( self::grant_workshop_bundle( $user_id, $workshop_id, $payment_id ) ) { $summary['enrolled']++; } else { self::skip( $summary, $row_no, 'access could not be assigned' ); }
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		self::finish_import( $summary );
	}

	private static function skip( &$summary, $row, $message ) {
		$summary['skipped']++;
		if ( count( $summary['messages'] ) < 100 ) { $summary['messages'][] = sprintf( 'Row %d: %s.', $row, $message ); }
	}

	private static function finish_import( $result ) {
		set_transient( 'ggm_legacy_access_import_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=ggm-lms-import-export#ggm-legacy-enrollment-import' ) ); exit;
	}

	public static function download_sample() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) ); }
		check_admin_referer( 'ggm_legacy_enrollment_sample' );
		nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="legacy-workshop-purchasers.csv"' );
		$out = fopen( 'php://output', 'w' ); fwrite( $out, "\xEF\xBB\xBF" ); fputcsv( $out, array( 'full_name','email','mobile_no' ), ',', '"', '\\' ); fputcsv( $out, array( 'Example Member','member@example.com','9876543210' ), ',', '"', '\\' ); fclose( $out ); exit;
	}
}
