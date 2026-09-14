<?php
/**
 * Admin Area Controller.
 *
 * Configures GGM LMS admin menus, enqueues admin assets,
 * handles settings saves, and hooks custom columns on CPT tables.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Admin
 */
class GGM_Admin {

	/**
	 * Register actions and filters.
	 *
	 * @param GGM_Loader $loader
	 */
	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'admin_menu', $this, 'register_admin_menus' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_assets' );
		$loader->add_action( 'admin_init', $this, 'suppress_external_notices' );

		// AJAX Settings saving.
		$loader->add_action( 'wp_ajax_ggm_save_settings',              $this, 'ajax_save_settings' );
		$loader->add_action( 'wp_ajax_ggm_clear_error_log',            $this, 'ajax_clear_error_log' );
		$loader->add_action( 'wp_ajax_ggm_send_test_otp',              $this, 'ajax_send_test_otp' );
		$loader->add_action( 'wp_ajax_ggm_send_smtp_test',             $this, 'ajax_send_smtp_test' );
		$loader->add_action( 'wp_ajax_ggm_inline_create_lesson',       $this, 'ajax_inline_create_lesson' );
		$loader->add_action( 'wp_ajax_ggm_get_course_lessons_page',    $this, 'ajax_get_course_lessons_page' );
		$loader->add_action( 'wp_ajax_ggm_reorder_lessons',            $this, 'ajax_reorder_lessons' );
		$loader->add_action( 'wp_ajax_ggm_move_lesson',                $this, 'ajax_move_lesson' );
		$loader->add_action( 'wp_ajax_ggm_get_lesson_data',            $this, 'ajax_get_lesson_data' );
		$loader->add_action( 'wp_ajax_ggm_inline_update_lesson',       $this, 'ajax_inline_update_lesson' );
		$loader->add_action( 'wp_ajax_ggm_start_member_import',        $this, 'ajax_start_member_import' );
		$loader->add_action( 'wp_ajax_ggm_process_member_import',      $this, 'ajax_process_member_import' );

		// AJAX Coupon management.
		$loader->add_action( 'wp_ajax_ggm_save_coupon',   $this, 'ajax_save_coupon' );
		$loader->add_action( 'wp_ajax_ggm_delete_coupon', $this, 'ajax_delete_coupon' );

		// Workshop / Course import & export.
		$loader->add_action( 'admin_post_ggm_export_workshops', $this, 'handle_export_workshops' );
		$loader->add_action( 'admin_post_ggm_export_workshop_package', $this, 'handle_export_workshop_package' );
		$loader->add_action( 'admin_post_ggm_import_workshop_package', $this, 'handle_import_workshop_package' );
		$loader->add_action( 'admin_post_ggm_confirm_import_workshop_package', $this, 'handle_confirm_import_workshop_package' );
		$loader->add_action( 'admin_post_ggm_export_courses',   $this, 'handle_export_courses' );
		$loader->add_action( 'admin_post_ggm_import_workshops', $this, 'handle_import_workshops' );
		$loader->add_action( 'admin_post_ggm_import_courses',   $this, 'handle_import_courses' );
		$loader->add_action( 'admin_post_ggm_import_members',   $this, 'handle_import_members' );
		$loader->add_action( 'admin_post_ggm_member_csv_sample', $this, 'handle_member_csv_sample' );

		// Workshop / Course duplication.
		$loader->add_filter( 'post_row_actions', $this, 'add_duplicate_row_action', 10, 2 );
		$loader->add_action( 'admin_post_ggm_duplicate_post', $this, 'handle_duplicate_post' );

		// AJAX Member profile popup.
		$loader->add_action( 'wp_ajax_ggm_view_member_profile', $this, 'ajax_view_member_profile' );
		$loader->add_action( 'wp_ajax_ggm_add_customer_credit', $this, 'ajax_add_customer_credit' );
		$loader->add_action( 'wp_ajax_ggm_search_wordpress_users', $this, 'ajax_search_wordpress_users' );
		$loader->add_action( 'wp_ajax_ggm_add_member', $this, 'ajax_add_member' );

		// Lesson columns.
		$loader->add_filter( 'manage_lesson_posts_columns', $this, 'lesson_columns' );
		$loader->add_action( 'manage_lesson_posts_custom_column', $this, 'lesson_column_data', 10, 2 );
		$loader->add_filter( 'manage_edit-lesson_sortable_columns', $this, 'lesson_sortable_columns' );

		// Course columns.
		$loader->add_filter( 'manage_course_posts_columns', $this, 'course_columns' );
		$loader->add_action( 'manage_course_posts_custom_column', $this, 'course_column_data', 10, 2 );

		// Workshop columns.
		$loader->add_filter( 'manage_workshop_posts_columns', $this, 'workshop_columns' );
		$loader->add_action( 'manage_workshop_posts_custom_column', $this, 'workshop_column_data', 10, 2 );
	}

	/**
	 * Register Admin Menus.
	 */
	public function register_admin_menus() {
		// Top Level.
		add_menu_page(
			__( 'DZ LMS Dashboard', 'ggm-member-dashboard' ),
			__( 'DZ LMS', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms',
			array( $this, 'render_dashboard_page' ),
			'dashicons-welcome-learn-more',
			6
		);

		// Submenus.
		add_submenu_page(
			'ggm-lms',
			__( 'Dashboard', 'ggm-member-dashboard' ),
			__( 'Dashboard', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Help Center', 'ggm-member-dashboard' ),
			__( 'Help Center', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms-help',
			array( $this, 'render_help_center_page' )
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Workshops', 'ggm-member-dashboard' ),
			__( 'Workshops', 'ggm-member-dashboard' ),
			'manage_options',
			'edit.php?post_type=workshop'
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Courses', 'ggm-member-dashboard' ),
			__( 'Courses', 'ggm-member-dashboard' ),
			'manage_options',
			'edit.php?post_type=course'
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Mentors', 'ggm-member-dashboard' ),
			__( 'Mentors', 'ggm-member-dashboard' ),
			'manage_options',
			'edit.php?post_type=ggm_mentor'
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Coupons', 'ggm-member-dashboard' ),
			__( 'Coupons', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms-coupons',
			array( $this, 'render_coupons_page' )
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Members', 'ggm-member-dashboard' ),
			__( 'Members', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms-users',
			array( $this, 'render_users_page' )
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Customer Credits', 'ggm-member-dashboard' ),
			__( 'Credits', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms-credits',
			array( $this, 'render_credits_page' )
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Payments', 'ggm-member-dashboard' ),
			__( 'Payments', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms-payments',
			array( $this, 'render_payments_page' )
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Diseases List', 'ggm-member-dashboard' ),
			__( 'Diseases List', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms-diseases',
			array( $this, 'render_diseases_page' )
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Import / Export', 'ggm-member-dashboard' ),
			__( 'Import / Export', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms-import-export',
			array( $this, 'render_import_export_page' )
		);

		add_submenu_page(
			'ggm-lms',
			__( 'Settings', 'ggm-member-dashboard' ),
			__( 'Settings', 'ggm-member-dashboard' ),
			'manage_options',
			'ggm-lms-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render Home Dashboard view.
	 */
	public function render_dashboard_page() {
		include GGM_PLUGIN_DIR . 'admin/views/page-dashboard.php';
	}

	/**
	 * Render Settings view.
	 */
	public function render_settings_page() {
		include GGM_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	public function render_coupons_page() {
		include GGM_PLUGIN_DIR . 'admin/views/coupons.php';
	}

	public function render_users_page() {
		include GGM_PLUGIN_DIR . 'admin/views/users.php';
	}

	public function render_payments_page() {
		include GGM_PLUGIN_DIR . 'admin/views/payments.php';
	}

	public function render_credits_page() {
		include GGM_PLUGIN_DIR . 'admin/views/credits.php';
	}

	public function render_diseases_page() {
		GGM_Diseases::render_admin_page();
	}

	public function ajax_add_customer_credit() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'ggm_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 );
		}
		$user_id = absint( $_POST['user_id'] ?? 0 );
		$amount  = round( (float) ( $_POST['amount'] ?? 0 ), 2 );
		$note    = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) );
		if ( $amount <= 0 || $amount > 99999999 || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid positive amount and customer.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! GGM_Credit::add( $user_id, $amount, get_current_user_id(), $note ) ) {
			wp_send_json_error( array( 'message' => __( 'Credit could not be added.', 'ggm-member-dashboard' ) ) );
		}
		wp_send_json_success( array(
			'message' => __( 'Credit added successfully.', 'ggm-member-dashboard' ),
			'balance' => number_format_i18n( GGM_Credit::get_balance( $user_id ), 2 ),
		) );
	}

	public function render_import_export_page() {
		include GGM_PLUGIN_DIR . 'admin/views/import-export.php';
	}

	/**
	 * Render Help Center view.
	 */
	public function render_help_center_page() {
		include GGM_PLUGIN_DIR . 'admin/views/page-help-center.php';
	}

	/**
	 * Stream a Workshops export as a downloadable JSON file.
	 */
	public function handle_export_workshops() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ggm_export_workshops' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) );
		}

		$data     = GGM_Import_Export::export_workshops();
		$filename = 'ggm-workshops-export-' . gmdate( 'Y-m-d-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/** Download one Workshop and its local Media Library files as a ZIP package. */
	public function handle_export_workshop_package() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ggm_export_workshop_package' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) );
		}
		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );
		$post = get_post( $workshop_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'workshop', 'ggm_workshop' ), true ) ) {
			wp_die( esc_html__( 'Please select a valid Workshop.', 'ggm-member-dashboard' ) );
		}
		$tmp = wp_tempnam( 'ggm-workshop-package.zip' );
		$result = $tmp ? GGM_Workshop_Package::export( $workshop_id, $tmp ) : new WP_Error( 'ggm_package_tmp', __( 'A temporary package file could not be created.', 'ggm-member-dashboard' ) );
		if ( is_wp_error( $result ) ) { if ( $tmp ) { wp_delete_file( $tmp ); } wp_die( esc_html( $result->get_error_message() ) ); }
		nocache_headers(); header( 'Content-Type: application/zip' ); header( 'Content-Disposition: attachment; filename="ggm-workshop-' . sanitize_file_name( $post->post_name ?: $post->ID ) . '.zip"' ); header( 'Content-Length: ' . filesize( $tmp ) ); readfile( $tmp ); wp_delete_file( $tmp ); exit;
	}

	/** Import one portable Workshop ZIP, always creating a new Workshop. */
	public function handle_import_workshop_package() {
		check_admin_referer( 'ggm_import_workshop_package' );
		$redirect = admin_url( 'admin.php?page=ggm-lms-import-export' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) ); }
		$file = $_FILES['workshop_package'] ?? array();
		if ( UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || 'zip' !== strtolower( pathinfo( sanitize_file_name( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) ) {
			$this->set_import_flash( array( 'type' => 'error', 'message' => __( 'Please upload a valid Workshop ZIP package.', 'ggm-member-dashboard' ) ) ); wp_safe_redirect( $redirect ); exit;
		}
		$title = GGM_Workshop_Package::title( $file['tmp_name'] );
		if ( is_wp_error( $title ) ) { $this->set_import_flash( array( 'type' => 'error', 'message' => $title->get_error_message() ) ); wp_safe_redirect( $redirect ); exit; }
		$existing = get_page_by_title( $title, OBJECT, array( 'workshop', 'ggm_workshop' ) );
		if ( $existing ) {
			$uploads = wp_upload_dir(); $dir = trailingslashit( $uploads['basedir'] ) . 'ggm-workshop-package-imports'; wp_mkdir_p( $dir );
			$file_path = trailingslashit( $dir ) . wp_generate_uuid4() . '.zip';
			if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) { $this->set_import_flash( array( 'type'=>'error','message'=>__( 'The package could not be staged for confirmation.', 'ggm-member-dashboard' ) ) ); wp_safe_redirect( $redirect ); exit; }
			set_transient( 'ggm_workshop_package_pending_' . get_current_user_id(), array( 'path'=>$file_path, 'title'=>$title, 'existing_id'=>(int) $existing->ID ), 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( $redirect ); exit;
		}
		$result = GGM_Workshop_Package::import( $file['tmp_name'] );
		if ( is_wp_error( $result ) ) { $this->set_import_flash( array( 'type' => 'error', 'message' => $result->get_error_message() ) ); }
		else { $this->set_import_flash( array( 'type' => 'success', 'kind' => __( 'workshop package', 'ggm-member-dashboard' ), 'summary' => array( 'created' => 1, 'updated' => 0, 'skipped' => 0, 'messages' => array( sprintf( __( 'Created Workshop ID %d.', 'ggm-member-dashboard' ), $result ) ) ) ) ); }
		wp_safe_redirect( $redirect ); exit;
	}

	public function handle_confirm_import_workshop_package() {
		check_admin_referer( 'ggm_confirm_import_workshop_package' ); $redirect = admin_url( 'admin.php?page=ggm-lms-import-export' );
		$pending = get_transient( 'ggm_workshop_package_pending_' . get_current_user_id() ); delete_transient( 'ggm_workshop_package_pending_' . get_current_user_id() );
		if ( ! current_user_can( 'manage_options' ) || ! is_array( $pending ) || empty( $pending['path'] ) || ! is_file( $pending['path'] ) ) { $this->set_import_flash( array( 'type'=>'error','message'=>__( 'The pending import has expired. Please upload the package again.', 'ggm-member-dashboard' ) ) ); wp_safe_redirect( $redirect ); exit; }
		if ( 'update' !== sanitize_key( $_POST['decision'] ?? '' ) ) { wp_delete_file( $pending['path'] ); $this->set_import_flash( array( 'type'=>'success','kind'=>__( 'workshop package','ggm-member-dashboard' ),'summary'=>array('created'=>0,'updated'=>0,'skipped'=>1,'messages'=>array(__( 'Import cancelled.','ggm-member-dashboard' )) ) ) ); wp_safe_redirect( $redirect ); exit; }
		// Update the existing post in place: preserve its ID, permalink, and template links.
		$result = GGM_Workshop_Package::import( $pending['path'], (int) $pending['existing_id'] ); wp_delete_file( $pending['path'] );
		$this->set_import_flash( is_wp_error($result) ? array('type'=>'error','message'=>$result->get_error_message()) : array('type'=>'success','kind'=>__('workshop package','ggm-member-dashboard'),'summary'=>array('created'=>0,'updated'=>1,'skipped'=>0,'messages'=>array(sprintf(__( 'Updated Workshop ID %d.', 'ggm-member-dashboard' ),$result)))) ); wp_safe_redirect( $redirect ); exit;
	}

	/**
	 * Stream a Courses export as a downloadable JSON file.
	 */
	public function handle_export_courses() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ggm_export_courses' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) );
		}

		$data     = GGM_Import_Export::export_courses();
		$filename = 'ggm-courses-export-' . gmdate( 'Y-m-d-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Handle an uploaded Workshops JSON import, then redirect back with a
	 * flash-message summary (stored in a short-lived per-user transient).
	 */
	public function handle_import_workshops() {
		check_admin_referer( 'ggm_import_workshops' );

		$redirect = admin_url( 'admin.php?page=ggm-lms-import-export' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) );
		}

		$data = $this->read_uploaded_export_json( 'ggm_workshops_export' );
		if ( is_wp_error( $data ) ) {
			$this->set_import_flash( array( 'type' => 'error', 'message' => $data->get_error_message() ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		$summary = GGM_Import_Export::import_workshops( $data );
		$this->set_import_flash( array( 'type' => 'success', 'kind' => __( 'workshops', 'ggm-member-dashboard' ), 'summary' => $summary ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle an uploaded Courses JSON import, then redirect back with a
	 * flash-message summary.
	 */
	public function handle_import_courses() {
		check_admin_referer( 'ggm_import_courses' );

		$redirect = admin_url( 'admin.php?page=ggm-lms-import-export' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) );
		}

		$data = $this->read_uploaded_export_json( 'ggm_courses_export' );
		if ( is_wp_error( $data ) ) {
			$this->set_import_flash( array( 'type' => 'error', 'message' => $data->get_error_message() ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		$summary = GGM_Import_Export::import_courses( $data );
		$this->set_import_flash( array( 'type' => 'success', 'kind' => __( 'courses', 'ggm-member-dashboard' ), 'summary' => $summary ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Import a large member CSV into WordPress users and GGM member metadata.
	 */
	public function handle_import_members() {
		check_admin_referer( 'ggm_import_members' );
		$redirect = admin_url( 'admin.php?page=ggm-lms-import-export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) );
		}

		$file = $_FILES['members_file'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- upload is validated below and parsed as CSV.
		$name = sanitize_file_name( $file['name'] ?? '' );
		if ( UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $file['tmp_name'] ?? '' ) || 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			$this->set_import_flash( array( 'type' => 'error', 'message' => __( 'Please choose a valid CSV file.', 'ggm-member-dashboard' ) ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- hosts may disable this function.
		}
		$summary = GGM_Import_Export::import_members_csv( $file['tmp_name'] );
		if ( is_wp_error( $summary ) ) {
			$this->set_import_flash( array( 'type' => 'error', 'message' => $summary->get_error_message() ) );
		} else {
			$this->set_import_flash( array( 'type' => 'success', 'kind' => __( 'members', 'ggm-member-dashboard' ), 'summary' => $summary ) );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/** Start a resumable member CSV import and return its tracking ID. */
	public function ajax_start_member_import() {
		check_ajax_referer( 'ggm_member_import_batches', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 ); }
		if ( ! wp_roles()->is_role( 'customer' ) ) { wp_send_json_error( array( 'message' => __( 'The WooCommerce Customer role is unavailable. Activate WooCommerce and try again.', 'ggm-member-dashboard' ) ) ); }
		$file = $_FILES['members_file'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name = sanitize_file_name( $file['name'] ?? '' );
		if ( UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $file['tmp_name'] ?? '' ) || 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid CSV file.', 'ggm-member-dashboard' ) ) );
		}
		$handle = fopen( $file['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$headers = $handle ? fgetcsv( $handle ) : false;
		if ( ! is_array( $headers ) ) { if ( $handle ) { fclose( $handle ); } wp_send_json_error( array( 'message' => __( 'The CSV file is empty.', 'ggm-member-dashboard' ) ) ); }
		$normalized = array_map( static function ( $header ) { return sanitize_key( preg_replace( '/^\xEF\xBB\xBF/', '', trim( (string) $header ) ) ); }, $headers );
		if ( ! in_array( 'email', $normalized, true ) && ! in_array( 'mobile_no', $normalized, true ) ) {
			fclose( $handle ); wp_send_json_error( array( 'message' => __( 'The CSV needs at least an email or mobile_no column.', 'ggm-member-dashboard' ) ) );
		}
		$total = 0;
		while ( false !== ( $row = fgetcsv( $handle ) ) ) { if ( count( $row ) > 1 || '' !== trim( (string) ( $row[0] ?? '' ) ) ) { $total++; } }
		fclose( $handle );
		if ( ! $total ) { wp_send_json_error( array( 'message' => __( 'The CSV contains no member rows.', 'ggm-member-dashboard' ) ) ); }

		$uploads = wp_upload_dir();
		$dir = trailingslashit( $uploads['basedir'] ) . 'ggm-member-imports';
		if ( ! wp_mkdir_p( $dir ) ) { wp_send_json_error( array( 'message' => __( 'The import storage directory could not be created.', 'ggm-member-dashboard' ) ) ); }
		if ( ! file_exists( $dir . '/.htaccess' ) ) { file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$id = wp_generate_uuid4();
		$path = trailingslashit( $dir ) . $id . '.csv';
		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) { wp_send_json_error( array( 'message' => __( 'The uploaded CSV could not be stored.', 'ggm-member-dashboard' ) ) ); }
		$state = array( 'id'=>$id, 'file'=>$name, 'path'=>$path, 'headers'=>$headers, 'position'=>0, 'total'=>$total, 'processed'=>0, 'created'=>0, 'updated'=>0, 'skipped'=>0, 'messages'=>array(), 'status'=>'pending', 'started_at'=>current_time( 'mysql' ), 'finished_at'=>'' );
		update_option( 'ggm_member_import_' . $id, $state, false );
		$index = (array) get_option( 'ggm_member_import_log_index', array() ); array_unshift( $index, $id ); update_option( 'ggm_member_import_log_index', array_slice( array_unique( $index ), 0, 20 ), false );
		wp_send_json_success( self::member_import_public_state( $state ) );
	}

	/** Process the next CSV chunk and persist progress/log output. */
	public function ajax_process_member_import() {
		check_ajax_referer( 'ggm_member_import_batches', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 ); }
		$id = sanitize_text_field( wp_unslash( $_POST['import_id'] ?? '' ) );
		$state = get_option( 'ggm_member_import_' . $id );
		if ( ! is_array( $state ) || empty( $state['path'] ) ) { wp_send_json_error( array( 'message' => __( 'Import session not found or expired.', 'ggm-member-dashboard' ) ) ); }
		if ( 'complete' === $state['status'] ) { wp_send_json_success( self::member_import_public_state( $state ) ); }
		if ( ! is_file( $state['path'] ) ) { $state['status']='failed'; $state['messages'][]=__( 'Stored CSV file is missing.', 'ggm-member-dashboard' ); update_option( 'ggm_member_import_'.$id, $state, false ); wp_send_json_error( array( 'message'=>end( $state['messages'] ) ) ); }

		$source = fopen( $state['path'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $source ) { wp_send_json_error( array( 'message' => __( 'The stored CSV could not be opened.', 'ggm-member-dashboard' ) ) ); }
		if ( empty( $state['position'] ) ) { fgetcsv( $source ); } else { fseek( $source, (int) $state['position'] ); }
		$tmp = wp_tempnam( 'ggm-member-batch.csv' );
		if ( ! $tmp ) { fclose( $source ); wp_send_json_error( array( 'message' => __( 'A temporary batch file could not be created.', 'ggm-member-dashboard' ) ) ); }
		$out = fopen( $tmp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, $state['headers'] );
		$count = 0;
		while ( $count < 100 && false !== ( $row = fgetcsv( $source ) ) ) { if ( count( $row ) > 1 || '' !== trim( (string) ( $row[0] ?? '' ) ) ) { fputcsv( $out, $row ); $count++; } }
		$state['position'] = ftell( $source );
		$at_end = feof( $source );
		fclose( $source ); fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$state['status'] = 'processing';
		if ( $count ) {
			$result = GGM_Import_Export::import_members_csv( $tmp, (int) $state['processed'] );
			if ( is_wp_error( $result ) ) { $state['status']='failed'; $state['messages'][]=$result->get_error_message(); }
			else { foreach ( array( 'processed','created','updated','skipped' ) as $key ) { $state[$key] += (int) ( $result[$key] ?? 0 ); } $state['messages'] = array_slice( array_merge( $state['messages'], (array) ( $result['messages'] ?? array() ) ), -500 ); }
		}
		wp_delete_file( $tmp );
		if ( 'failed' !== $state['status'] && ( $at_end || $state['processed'] >= $state['total'] ) ) { $state['status']='complete'; $state['finished_at']=current_time( 'mysql' ); wp_delete_file( $state['path'] ); $state['path']=''; }
		update_option( 'ggm_member_import_' . $id, $state, false );
		if ( 'failed' === $state['status'] ) { wp_send_json_error( array( 'message'=>end( $state['messages'] ), 'state'=>self::member_import_public_state( $state ) ) ); }
		wp_send_json_success( self::member_import_public_state( $state ) );
	}

	private static function member_import_public_state( array $state ) {
		$processed=(int)($state['processed']??0); $total=max( 1, (int)($state['total']??0) );
		return array( 'id'=>$state['id']??'', 'file'=>$state['file']??'', 'status'=>$state['status']??'', 'total'=>(int)($state['total']??0), 'processed'=>$processed, 'created'=>(int)($state['created']??0), 'updated'=>(int)($state['updated']??0), 'skipped'=>(int)($state['skipped']??0), 'percent'=>min( 100, (int) floor( $processed * 100 / $total ) ), 'messages'=>(array)($state['messages']??array()), 'started_at'=>$state['started_at']??'', 'finished_at'=>$state['finished_at']??'' );
	}

	public static function get_member_import_logs() {
		$logs=array(); foreach ( (array)get_option( 'ggm_member_import_log_index', array() ) as $id ) { $state=get_option( 'ggm_member_import_'.$id ); if ( is_array( $state ) ) { $logs[]=self::member_import_public_state( $state ); } } return $logs;
	}

	/** Download a UTF-8 CSV template with the exact supported headers. */
	public function handle_member_csv_sample() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ggm_member_csv_sample' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ggm-members-import-template.csv"' );
		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, GGM_Import_Export::MEMBER_CSV_HEADERS );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Add a "Duplicate" row action to Workshops and Courses in wp-admin's
	 * post list, next to Edit/Trash/View.
	 *
	 * @param string[] $actions
	 * @param WP_Post  $post
	 * @return string[]
	 */
	public function add_duplicate_row_action( $actions, $post ) {
		if ( ! in_array( $post->post_type, array( 'workshop', 'course' ), true ) ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'ggm_duplicate_post',
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'ggm_duplicate_post_' . $post->ID
		);

		$actions['ggm_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'ggm-member-dashboard' ) . '</a>';

		return $actions;
	}

	/**
	 * Handle a "Duplicate" click for a Workshop or Course: clones the post,
	 * all of its post meta (repeaters, pricing, dates, linked course, etc.),
	 * its taxonomy terms, and — for workshops — its time slots, then opens
	 * the new draft copy for editing.
	 */
	public function handle_duplicate_post() {
		$post_id = absint( $_GET['post'] ?? 0 );

		if ( ! $post_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'ggm_duplicate_post_' . $post_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'ggm-member-dashboard' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'workshop', 'course' ), true ) ) {
			wp_die( esc_html__( 'Item not found.', 'ggm-member-dashboard' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ggm-member-dashboard' ) );
		}

		$new_id = wp_insert_post( array(
			'post_type'    => $post->post_type,
			/* translators: %s: original title */
			'post_title'   => sprintf( __( '%s (Copy)', 'ggm-member-dashboard' ), $post->post_title ),
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
		), true );

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			wp_die( esc_html__( 'Failed to duplicate this item.', 'ggm-member-dashboard' ) );
		}

		// Copy every meta field (pricing, dates, repeaters, linked course,
		// featured image, etc.) except WordPress's own edit-lock housekeeping.
		$skip_meta = array( '_edit_lock', '_edit_last' );
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( in_array( $key, $skip_meta, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, $value );
			}
		}

		// Copy any taxonomy terms assigned to the original.
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_id, $terms, $taxonomy );
			}
		}

		// Workshops also carry their time slots in a separate table — clone those too.
		if ( 'workshop' === $post->post_type && class_exists( 'GGM_Workshop_Slot' ) ) {
			foreach ( GGM_Workshop_Slot::get_for_workshop( $post_id, 'all' ) as $slot ) {
				GGM_Workshop_Slot::create( array(
					'workshop_id'  => $new_id,
					'slot_type'    => $slot->slot_type,
					'start_time'   => $slot->start_time,
					'end_time'     => $slot->end_time,
					'meeting_link' => $slot->meeting_link,
					'sort_order'   => $slot->sort_order,
					'status'       => $slot->status,
				) );
			}
		}

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
		exit;
	}

	/**
	 * Read & validate the uploaded 'import_file' as an export JSON of the
	 * expected $expected_type ('ggm_workshops_export' or 'ggm_courses_export').
	 *
	 * @param string $expected_type
	 * @return array|WP_Error Decoded payload, or a WP_Error with a user-facing message.
	 */
	private function read_uploaded_export_json( $expected_type ) {
		if ( empty( $_FILES['import_file']['tmp_name'] ) || UPLOAD_ERR_OK !== ( $_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new WP_Error( 'ggm_import_no_file', __( 'Please choose a valid JSON file to upload.', 'ggm-member-dashboard' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading an admin-uploaded local tmp file, not a remote URL.
		$raw  = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) || $expected_type !== ( $data['type'] ?? '' ) ) {
			return new WP_Error( 'ggm_import_bad_file', __( 'That file is not a valid export of the expected type.', 'ggm-member-dashboard' ) );
		}

		return $data;
	}

	/**
	 * Store a one-time import result for display on the next page load.
	 *
	 * @param array $flash
	 */
	private function set_import_flash( array $flash ) {
		set_transient( 'ggm_import_result_' . get_current_user_id(), $flash, MINUTE_IN_SECONDS );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'ggm-lms' ) === false ) {
			return;
		}

		wp_enqueue_media();
		if ( false !== strpos( $hook, 'ggm-lms-settings' ) ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}

		wp_enqueue_style( 'wp-color-picker' );

		// Version off the file's mtime (not the static GGM_VERSION constant)
		// so every edit auto-busts the browser cache — GGM_VERSION has stayed
		// at 1.0.0 across many admin.js/css changes, which left browsers
		// serving stale cached copies (e.g. settings tab clicks silently
		// doing nothing because an old ggm-admin.js was still in play).
		$admin_css_path = GGM_PLUGIN_DIR . 'assets/css/ggm-admin.css';
		$admin_js_path  = GGM_PLUGIN_DIR . 'assets/js/ggm-admin.js';
		$admin_css_ver  = file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : GGM_VERSION;
		$admin_js_ver   = file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : GGM_VERSION;

		wp_enqueue_style(
			'ggm-admin-style',
			GGM_PLUGIN_URL . 'assets/css/ggm-admin.css',
			array(),
			$admin_css_ver
		);

		// Settings tab switching is printed inline in page-settings.php
		// (not enqueued) — see the comment there for why.

		wp_enqueue_script(
			'ggm-admin-script',
			GGM_PLUGIN_URL . 'assets/js/ggm-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			$admin_js_ver,
			true
		);

		wp_localize_script( 'ggm-admin-script', 'ggmAdmin', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ggm_admin_nonce' ),
		) );
	}

	/**
	 * Determine whether the current admin screen belongs to this plugin —
	 * either a "ggm-lms" menu page or the Workshop/Course/Lesson post-edit screens.
	 *
	 * @param WP_Screen $screen
	 * @return bool
	 */
	private function is_ggm_screen( $screen ) {
		if ( strpos( (string) $screen->id, 'ggm-lms' ) !== false ) {
			return true;
		}

		$ggm_post_types = array( 'workshop', 'course', 'lesson', 'ggm_workshop', 'ggm_lesson', 'ggm_product' );

		return ! empty( $screen->post_type ) && in_array( $screen->post_type, $ggm_post_types, true );
	}

	/**
	 * Remove admin notices from other plugins on every page that belongs to this plugin
	 * (the "DZ LMS" menu pages and the Workshop/Course/Lesson post-edit screens).
	 * Fires on admin_init so it runs before notices are output.
	 */
	public function suppress_external_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! $this->is_ggm_screen( $screen ) ) {
			return;
		}

		// Remove all third-party admin notices.
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );

		// Re-add only WordPress core notices (update/maintenance nags) so we don't miss critical WP alerts.
		add_action( 'admin_notices', 'update_nag', 3 );
		add_action( 'admin_notices', 'maintenance_nag', 10 );

		// Re-add this plugin's own notices, which the wipe above also removed.
		if ( class_exists( 'GGM_Meta_Boxes' ) ) {
			add_action( 'admin_notices', array( new GGM_Meta_Boxes(), 'show_slot_admin_notice' ) );
		}
	}

	/**
	 * AJAX: Save Settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$form_data        = array();
		$current_settings = get_option( 'ggm_settings', array() );
		$clear_currency_rates = false;
		$currency_setting_keys = array(
			'ggm_currency',
			'ggm_multicurrency_enabled',
			'ggm_multicurrency_enabled_codes',
			'ggm_multicurrency_default_currency',
			'ggm_exchange_rate_api_url',
			'ggm_exchange_rate_api_key',
			'ggm_exchange_rate_cache_hours',
			'ggm_currency_manual_rates',
		);
		if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
			foreach ( wp_unslash( $_POST['settings'] ) as $key => $val ) {
				$key = sanitize_key( $key );
				if ( 'ggm_workshop_admin_configuration' === $key ) {
					$form_data[ $key ] = class_exists( 'GGM_Workshop_Admin_Config' )
						? GGM_Workshop_Admin_Config::sanitise_submitted( $val )
						: array();
					continue;
				}
				if ( in_array( $key, $currency_setting_keys, true ) ) {
					$clear_currency_rates = true;
				}
				if ( 'ggm_dashboard_custom_links' === $key ) {
					$decoded = json_decode( (string) $val, true );
					$links   = array();
					if ( is_array( $decoded ) ) {
						foreach ( array_slice( $decoded, 0, 20 ) as $link ) {
							$label = sanitize_text_field( $link['label'] ?? '' );
							$url   = esc_url_raw( $link['url'] ?? '' );
							if ( '' === $label || '' === $url ) {
								continue;
							}
							$allowed_icons = array( 'dashicons-admin-links', 'dashicons-whatsapp', 'dashicons-video-alt3', 'dashicons-calendar-alt', 'dashicons-download', 'dashicons-book', 'dashicons-email' );
							$icon = sanitize_html_class( $link['icon'] ?? 'dashicons-admin-links' );
							$links[] = array(
								'label'   => $label,
								'url'     => $url,
								'icon'    => in_array( $icon, $allowed_icons, true ) ? $icon : 'dashicons-admin-links',
								'new_tab' => empty( $link['new_tab'] ) ? 0 : 1,
							);
						}
					}
					$form_data[ $key ] = wp_json_encode( $links );
				} elseif ( in_array( $key, array(
					'ggm_workshop_discover_heading',
					'ggm_workshop_why_different_heading',
					'ggm_workshop_perfect_for_heading',
					'ggm_workshop_faq_heading',
				), true ) ) {
					// These are text headings. Handle them before the generic
					// "_heading" color-key branch below.
					$form_data[ $key ] = sanitize_text_field( $val );
				} elseif ( 'ggm_exchange_rate_api_url' === $key ) {
					$form_data[ $key ] = sanitize_text_field( $val );
				} elseif ( 'ggm_exchange_rate_cache_hours' === $key ) {
					$form_data[ $key ] = min( 168, max( 1, absint( $val ) ) );
				} elseif ( 'ggm_multicurrency_enabled_codes' === $key ) {
					$form_data[ $key ] = implode( ',', array_values( array_unique( array_filter( array_map( static function( $code ) {
						return strtoupper( sanitize_text_field( trim( $code ) ) );
					}, preg_split( '/[\s,]+/', (string) $val ) ) ) ) ) );
				} elseif ( 'ggm_multicurrency_default_currency' === $key ) {
					$form_data[ $key ] = strtoupper( sanitize_text_field( $val ) );
				} elseif ( 'ggm_currency_manual_rates' === $key ) {
					$form_data[ $key ] = sanitize_textarea_field( $val );
				} elseif ( 'ggm_workshop_slot_types' === $key ) {
					$form_data[ $key ] = sanitize_textarea_field( $val );
				} elseif ( strpos( $key, 'api_key' ) !== false || strpos( $key, 'secret' ) !== false || strpos( $key, 'smtp_password' ) !== false ) {
					$form_data[ $key ] = sanitize_text_field( $val );
				} elseif ( strpos( $key, 'body' ) !== false ) {
					$form_data[ $key ] = wp_kses_post( $val ); // Allow HTML from rich text editor
				} elseif ( 'ggm_workshop_validation_method' === $key ) {
					$allowed = array( 'phone', 'email', 'both' );
					$val = sanitize_text_field( $val );
					$form_data[ $key ] = in_array( $val, $allowed, true ) ? $val : 'phone';
				} elseif ( strpos( $key, '_bg' ) !== false || strpos( $key, '_primary' ) !== false || strpos( $key, '_heading' ) !== false || strpos( $key, '_hover' ) !== false || strpos( $key, '_sidebar' ) !== false || strpos( $key, '_end' ) !== false ) {
					$form_data[ $key ] = sanitize_hex_color( $val ) ?? '';
				} else {
					$form_data[ $key ] = sanitize_text_field( $val );
				}
			}
		}

		// Unchecked checkboxes are absent from an HTML submission. Preserve the
		// explicit off state for Elementor's workshop video/image fallback.
		$form_data['ggm_elementor_workshop_featured_media_fallback_enabled'] = isset( $_POST['settings']['ggm_elementor_workshop_featured_media_fallback_enabled'] ) ? '1' : '';

		update_option( 'ggm_settings', $form_data );
		if ( $clear_currency_rates && class_exists( 'GGM_Currency' ) ) {
			foreach ( array_keys( GGM_Currency::currencies() ) as $currency_code ) {
				delete_transient( 'ggm_currency_rates_' . $currency_code );
				delete_transient( GGM_Currency::RATE_CACHE_PREFIX . $currency_code );
			}
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'ggm-member-dashboard' ) ) );
	}

	/**
	 * AJAX: Clear the workshop/course/lesson save error log.
	 */
	public function ajax_clear_error_log() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		if ( class_exists( 'GGM_Meta_Boxes' ) ) {
			GGM_Meta_Boxes::clear_error_log();
		}

		wp_send_json_success( array( 'message' => __( 'Error log cleared.', 'ggm-member-dashboard' ) ) );
	}

	/**
	 * AJAX: Send Test OTP.
	 */
	public function ajax_send_test_otp() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		if ( ! ggm_custom_smtp_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Enable and save Custom SMTP before sending an OTP test.', 'ggm-member-dashboard' ) ) );
		}

		$target_email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( empty( $target_email ) ) {
			$target_email = get_option( 'admin_email' );
		}
		if ( ! is_email( $target_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'ggm-member-dashboard' ) ) );
		}

		$otp  = str_pad( (string) wp_rand( 100000, 999999 ), 6, '0', STR_PAD_LEFT );
		$sent = ggm_send_email_otp( $target_email, $otp );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => sprintf( __( 'Test OTP email sent to: %s', 'ggm-member-dashboard' ), $target_email ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to dispatch test OTP. Check your email/SMTP configuration.', 'ggm-member-dashboard' ) ) );
		}
	}


	/**
	 * AJAX: Send a plain test email using current SMTP settings.
	 */
	public function ajax_send_smtp_test() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'ggm-member-dashboard' ) ) );
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: %s: site name */
			__( '[%s] DZ LMS SMTP Test', 'ggm-member-dashboard' ),
			$site_name
		);
		$body_html = '<p>' . esc_html__( 'This is a test email to verify your SMTP configuration.', 'ggm-member-dashboard' ) . '</p>'
			. '<p>' . sprintf(
				/* translators: %s: date/time sent */
				esc_html__( 'Sent at: %s', 'ggm-member-dashboard' ),
				esc_html( current_time( 'mysql' ) )
			) . '</p>'
			. '<p>' . esc_html__( 'If you received this, your SMTP settings are working correctly.', 'ggm-member-dashboard' ) . '</p>';

		$html    = ggm_wrap_email_html( $site_name, $body_html );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// Enable SMTP debug capture for test email (same as workshop OTP)
		// so the Error Log shows the full SMTP transaction for comparison.
		$GLOBALS['ggm_smtp_debug_active'] = true;

		$sent = ggm_send_plugin_mail( $email, $subject, $html, $headers );

		// Disable SMTP debug capture
		$GLOBALS['ggm_smtp_debug_active'] = false;

		if ( $sent ) {
			wp_send_json_success( array( 'message' => sprintf( __( 'Test email sent successfully to: %s', 'ggm-member-dashboard' ), $email ) ) );
		} else {
			global $phpmailer;
			$error = isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ? $phpmailer->ErrorInfo : __( 'Unknown error.', 'ggm-member-dashboard' );
			wp_send_json_error( array( 'message' => sprintf( __( 'Failed to send test email: %s', 'ggm-member-dashboard' ), $error ) ) );
		}
	}

	/**
	 * AJAX: Inline-create a lesson and return refreshed table HTML.
	 */
	public function ajax_inline_create_lesson() {
		$course_id = absint( $_POST['course_id'] ?? 0 );
		if ( ! $course_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ggm_inline_lesson_' . $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$title  = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'Lesson title is required.', 'ggm-member-dashboard' ) ) );
		}

		$status      = in_array( $_POST['status'] ?? '', array( 'publish', 'draft' ), true ) ? $_POST['status'] : 'publish';
		$order       = absint( $_POST['lesson_order'] ?? 0 );
		$duration    = sanitize_text_field( wp_unslash( $_POST['duration'] ?? '' ) );
		$is_preview  = ! empty( $_POST['is_preview'] ) ? '1' : '';
		$video_embed = wp_kses_post( wp_unslash( $_POST['video_embed'] ?? '' ) );

		$lesson_id = wp_insert_post( array(
			'post_type'   => 'lesson',
			'post_title'  => $title,
			'post_status' => $status,
			'post_author' => get_current_user_id(),
		), true );

		if ( is_wp_error( $lesson_id ) ) {
			wp_send_json_error( array( 'message' => $lesson_id->get_error_message() ) );
		}

		update_post_meta( $lesson_id, 'course',           $course_id );
		update_post_meta( $lesson_id, 'lesson_order',     $order );
		update_post_meta( $lesson_id, 'lesson_duration',  $duration );
		update_post_meta( $lesson_id, 'is_preview',       $is_preview );
		update_post_meta( $lesson_id, 'video_embed',      $video_embed );

		$total       = (int) ( new WP_Query( array(
			'post_type'      => array( 'lesson', 'ggm_lesson' ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => 'course', 'value' => $course_id ) ),
		) ) )->found_posts;
		$table_html  = GGM_Meta_Boxes::render_lessons_table_html( $course_id, 1, 10 );
		$count_label = sprintf(
			_n( '%d lesson in this course', '%d lessons in this course', $total, 'ggm-member-dashboard' ),
			$total
		);

		wp_send_json_success( array(
			'message'     => sprintf( __( 'Lesson "%s" created.', 'ggm-member-dashboard' ), $title ),
			'lesson_id'   => $lesson_id,
			'table_html'  => $table_html,
			'count_label' => $count_label,
			'next_order'  => $total + 1,
		) );
	}

	/**
	 * AJAX: Return paginated lessons table HTML for a course.
	 */
	public function ajax_get_course_lessons_page() {
		$course_id = absint( $_POST['course_id'] ?? 0 );
		if ( ! $course_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ggm_inline_lesson_' . $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$paged    = max( 1, absint( $_POST['paged'] ?? 1 ) );
		$per_page = max( 1, absint( $_POST['per_page'] ?? 10 ) );

		wp_send_json_success( array(
			'table_html' => GGM_Meta_Boxes::render_lessons_table_html( $course_id, $paged, $per_page ),
		) );
	}

	/**
	 * AJAX: Save drag-and-drop reorder for visible page lessons.
	 *
	 * Receives the ordered IDs for the visible page and renumbers
	 * lesson_order starting from the correct page offset.
	 */
	public function ajax_reorder_lessons() {
		$course_id = absint( $_POST['course_id'] ?? 0 );
		if ( ! $course_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ggm_inline_lesson_' . $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$ordered_ids = array_map( 'absint', (array) ( $_POST['ordered_ids'] ?? array() ) );
		$paged       = max( 1, absint( $_POST['paged'] ?? 1 ) );
		$per_page    = max( 1, absint( $_POST['per_page'] ?? 10 ) );

		if ( empty( $ordered_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No lesson IDs provided.', 'ggm-member-dashboard' ) ) );
		}

		// Assign lesson_order starting from the correct global offset.
		$offset = ( $paged - 1 ) * $per_page;
		foreach ( $ordered_ids as $i => $lid ) {
			if ( $lid ) {
				update_post_meta( $lid, 'lesson_order', $offset + $i + 1 );
			}
		}

		wp_send_json_success( array(
			'table_html' => GGM_Meta_Boxes::render_lessons_table_html( $course_id, $paged, $per_page ),
		) );
	}

	/**
	 * AJAX: Move a lesson one step up or down in the global sequence.
	 *
	 * Swaps lesson_order values with the adjacent lesson and returns
	 * the refreshed table at the appropriate page.
	 */
	public function ajax_move_lesson() {
		$course_id = absint( $_POST['course_id'] ?? 0 );
		if ( ! $course_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ggm_inline_lesson_' . $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$lesson_id = absint( $_POST['lesson_id'] ?? 0 );
		$direction = 'up' === ( $_POST['direction'] ?? '' ) ? 'up' : 'down';
		$per_page  = max( 1, absint( $_POST['per_page'] ?? 10 ) );

		// Fetch all lessons for the course in current order.
		$all = get_posts( array(
			'post_type'      => array( 'lesson', 'ggm_lesson' ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'meta_key'       => 'lesson_order',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array( array( 'key' => 'course', 'value' => $course_id ) ),
		) );

		$ids    = array_map( function( $l ) { return $l->ID; }, $all );
		$pos    = array_search( $lesson_id, $ids, true );

		if ( false === $pos ) {
			wp_send_json_error( array( 'message' => __( 'Lesson not found in this course.', 'ggm-member-dashboard' ) ) );
		}

		$swap = 'up' === $direction ? $pos - 1 : $pos + 1;
		if ( $swap < 0 || $swap >= count( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Already at boundary.', 'ggm-member-dashboard' ) ) );
		}

		// Swap the lesson_order values of the two lessons.
		$order_a = (int) get_post_meta( $ids[ $pos ],  'lesson_order', true );
		$order_b = (int) get_post_meta( $ids[ $swap ], 'lesson_order', true );

		// If orders are identical (e.g. both 0), use position index.
		if ( $order_a === $order_b ) {
			$order_a = $pos  + 1;
			$order_b = $swap + 1;
		}

		update_post_meta( $ids[ $pos ],  'lesson_order', $order_b );
		update_post_meta( $ids[ $swap ], 'lesson_order', $order_a );

		// Determine which page the moved lesson now lives on.
		$new_global_pos = $swap; // 0-based
		$new_paged      = (int) ceil( ( $new_global_pos + 1 ) / $per_page );

		wp_send_json_success( array(
			'table_html' => GGM_Meta_Boxes::render_lessons_table_html( $course_id, $new_paged, $per_page ),
			'paged'      => $new_paged,
		) );
	}

	/**
	 * AJAX: Return all fields for a lesson so the inline edit form can be pre-filled.
	 */
	public function ajax_get_lesson_data() {
		$course_id = absint( $_POST['course_id'] ?? 0 );
		if ( ! $course_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ggm_inline_lesson_' . $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$lesson_id = absint( $_POST['lesson_id'] ?? 0 );
		$lesson    = get_post( $lesson_id );
		if ( ! $lesson || ! in_array( $lesson->post_type, array( 'lesson', 'ggm_lesson' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Lesson not found.', 'ggm-member-dashboard' ) ) );
		}

		$course = get_post( $course_id );

		wp_send_json_success( array(
			'title'        => $lesson->post_title,
			'slug'         => $lesson->post_name,
			'status'       => $lesson->post_status,
			'lesson_order' => (int) get_post_meta( $lesson_id, 'lesson_order', true ),
			'duration'     => get_post_meta( $lesson_id, 'lesson_duration', true ),
			'is_preview'   => get_post_meta( $lesson_id, 'is_preview', true ),
			'video_embed'  => get_post_meta( $lesson_id, 'video_embed', true ),
			'course_slug'  => $course ? $course->post_name : '',
			'home_url'     => trailingslashit( home_url( '/course' ) ) . ( $course ? $course->post_name . '/' : '' ),
		) );
	}

	/**
	 * AJAX: Update a lesson from the inline edit form and return refreshed table HTML.
	 */
	public function ajax_inline_update_lesson() {
		$course_id = absint( $_POST['course_id'] ?? 0 );
		if ( ! $course_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'ggm_inline_lesson_' . $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$lesson_id   = absint( $_POST['lesson_id'] ?? 0 );
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$raw_slug    = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		$status      = in_array( $_POST['status'] ?? '', array( 'publish', 'draft' ), true ) ? $_POST['status'] : 'publish';
		$order       = absint( $_POST['lesson_order'] ?? 0 );
		$duration    = sanitize_text_field( wp_unslash( $_POST['duration'] ?? '' ) );
		$is_preview  = ! empty( $_POST['is_preview'] ) ? '1' : '';
		$video_embed = wp_kses_post( wp_unslash( $_POST['video_embed'] ?? '' ) );

		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'Lesson title is required.', 'ggm-member-dashboard' ) ) );
		}

		$slug   = $raw_slug ?: sanitize_title( $title );
		$result = wp_update_post( array(
			'ID'          => $lesson_id,
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_status' => $status,
		), true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		update_post_meta( $lesson_id, 'lesson_order',    $order );
		update_post_meta( $lesson_id, 'lesson_duration', $duration );
		update_post_meta( $lesson_id, 'is_preview',      $is_preview );
		update_post_meta( $lesson_id, 'video_embed',     $video_embed );

		$paged    = max( 1, absint( $_POST['paged'] ?? 1 ) );
		$per_page = max( 1, absint( $_POST['per_page'] ?? 10 ) );

		wp_send_json_success( array(
			'message'    => sprintf( __( '"%s" updated successfully.', 'ggm-member-dashboard' ), $title ),
			'table_html' => GGM_Meta_Boxes::render_lessons_table_html( $course_id, $paged, $per_page ),
		) );
	}

	// ─── Lesson Columns ──────────────────────────────────────────────────────

	public function lesson_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['course']       = __( 'Course', 'ggm-member-dashboard' );
				$new['duration']     = __( 'Duration', 'ggm-member-dashboard' );
				$new['lesson_order'] = __( 'Order', 'ggm-member-dashboard' );
				$new['is_preview']   = __( 'Free Preview', 'ggm-member-dashboard' );
			}
		}
		return $new;
	}

	public function lesson_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'course':
				$course_id = get_post_meta( $post_id, 'course', true );
				if ( $course_id ) {
					echo '<a href="' . esc_url( get_edit_post_link( $course_id ) ) . '">' . esc_html( get_the_title( $course_id ) ) . '</a>';
				} else {
					echo '—';
				}
				break;
			case 'duration':
				echo esc_html( get_post_meta( $post_id, 'lesson_duration', true ) ?: '—' );
				break;
			case 'lesson_order':
				echo esc_html( get_post_meta( $post_id, 'lesson_order', true ) ?: '0' );
				break;
			case 'is_preview':
				$preview = get_post_meta( $post_id, 'is_preview', true );
				if ( '1' === $preview ) {
					echo '<span class="badge badge-green" style="background:#e8faf3; color:#0e9e6e; padding:3px 8px; border-radius:10px; font-weight:600;">' . esc_html__( 'Yes', 'ggm-member-dashboard' ) . '</span>';
				} else {
					echo '<span class="badge badge-gray" style="background:#f1f5f9; color:#64748b; padding:3px 8px; border-radius:10px;">' . esc_html__( 'No', 'ggm-member-dashboard' ) . '</span>';
				}
				break;
		}
	}

	public function lesson_sortable_columns( $columns ) {
		$columns['lesson_order'] = 'lesson_order';
		return $columns;
	}

	// ─── Course Columns ──────────────────────────────────────────────────────

	public function course_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['price']         = __( 'Price', 'ggm-member-dashboard' );
				$new['lessons_count'] = __( 'Lessons Count', 'ggm-member-dashboard' );
			}
		}
		return $new;
	}

	public function course_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'price':
				echo esc_html( get_post_meta( $post_id, 'course_price', true ) ?: 'Free' );
				break;
			case 'lessons_count':
				$lessons = get_posts( array(
					'post_type'      => 'lesson',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'   => 'course',
							'value' => $post_id,
						),
					),
				) );
				echo esc_html( count( $lessons ) );
				break;
		}
	}

	// ─── Workshop Columns ────────────────────────────────────────────────────

	public function workshop_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['price']            = __( 'Price', 'ggm-member-dashboard' );
				$new['workshop_date']    = __( 'Date', 'ggm-member-dashboard' );
				$new['workshop_time']    = __( 'Time', 'ggm-member-dashboard' );
				$new['workshop_mode']    = __( 'Mode', 'ggm-member-dashboard' );
				$new['is_free']          = __( 'Is Free', 'ggm-member-dashboard' );
				$new['registrations']    = __( 'Registrations', 'ggm-member-dashboard' );
			}
		}
		return $new;
	}

	public function workshop_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'price':
				echo class_exists( 'GGM_Workshop' ) ? GGM_Workshop::price_html( $post_id ) : esc_html( get_post_meta( $post_id, 'workshop_money', true ) ?: 'Free' );
				break;
			case 'workshop_date':
				$w_date = get_post_meta( $post_id, 'workshop_start_date', true ) ?: get_post_meta( $post_id, 'workshop_date', true );
				echo esc_html( $w_date ? date_i18n( get_option( 'date_format' ), strtotime( $w_date ) ) : '—' );
				break;
			case 'workshop_time':
				$slots = class_exists( 'GGM_Workshop_Slot' ) ? GGM_Workshop_Slot::get_for_workshop( $post_id, 'active' ) : array();
				if ( empty( $slots ) ) {
					echo '—';
				} elseif ( 1 === count( $slots ) ) {
					echo esc_html( GGM_Workshop_Slot::format_range( $slots[0] ) );
				} else {
					echo esc_html( sprintf(
						/* translators: %d: number of time slots */
						_n( '%d time slot', '%d time slots', count( $slots ), 'ggm-member-dashboard' ),
						count( $slots )
					) );
				}
				break;
			case 'workshop_mode':
				echo esc_html( get_post_meta( $post_id, 'workshop_mode', true ) ?: 'Zoom' );
				break;
			case 'is_free':
				$free = get_post_meta( $post_id, 'is_free', true );
				if ( '1' === $free ) {
					echo '<span class="badge badge-green" style="background:#e8faf3; color:#0e9e6e; padding:3px 8px; border-radius:10px; font-weight:600;">' . esc_html__( 'Yes', 'ggm-member-dashboard' ) . '</span>';
				} else {
					echo '<span class="badge badge-gray" style="background:#f1f5f9; color:#64748b; padding:3px 8px; border-radius:10px;">' . esc_html__( 'No', 'ggm-member-dashboard' ) . '</span>';
				}
				break;
			case 'registrations':
				global $wpdb;
				$count = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ggm_workshop_access WHERE workshop_id = %d",
					$post_id
				) );
				echo esc_html( $count );
				break;
		}
	}


	// ─── Coupon AJAX Handlers ───────────────────────────────────────────────

	/**
	 * AJAX: Create or update a coupon.
	 */
	public function ajax_save_coupon() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
		if ( '' === trim( $code ) ) {
			wp_send_json_error( array( 'message' => __( 'Coupon code is required.', 'ggm-member-dashboard' ) ) );
		}

		$id = absint( $_POST['coupon_id'] ?? 0 );

		// Ensure the code is unique (case-insensitive) against other coupons.
		$existing = GGM_Coupon::get_by_code( $code );
		if ( $existing && (int) $existing->id !== $id ) {
			wp_send_json_error( array( 'message' => __( 'A coupon with this code already exists.', 'ggm-member-dashboard' ) ) );
		}

		$data = array(
			'code'                   => $code,
			'name'                   => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'description'            => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'discount_type'          => sanitize_key( wp_unslash( $_POST['discount_type'] ?? 'fixed' ) ),
			'discount_value'         => (float) ( $_POST['discount_value'] ?? 0 ),
			'max_discount'           => ( '' === ( $_POST['max_discount'] ?? '' ) ) ? null : (float) $_POST['max_discount'],
			'min_purchase'           => ( '' === ( $_POST['min_purchase'] ?? '' ) ) ? null : (float) $_POST['min_purchase'],
			'max_purchase'           => ( '' === ( $_POST['max_purchase'] ?? '' ) ) ? null : (float) $_POST['max_purchase'],
			'start_date'             => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
			'expiry_date'            => sanitize_text_field( wp_unslash( $_POST['expiry_date'] ?? '' ) ),
			'max_uses'               => ( '' === ( $_POST['max_uses'] ?? '' ) ) ? null : absint( $_POST['max_uses'] ),
			'max_uses_per_user'      => ( '' === ( $_POST['max_uses_per_user'] ?? '' ) ) ? null : absint( $_POST['max_uses_per_user'] ),
			'applicable_workshops'   => isset( $_POST['applicable_workshops'] ) ? array_map( 'absint', (array) $_POST['applicable_workshops'] ) : array(),
			'status'                 => in_array( $_POST['status'] ?? '', array( 'active', 'inactive' ), true ) ? $_POST['status'] : 'active',
		);

		if ( $id ) {
			$ok = GGM_Coupon::update( $id, $data );
		} else {
			$ok = GGM_Coupon::create( $data );
		}

		if ( $ok ) {
			wp_send_json_success( array( 'message' => __( 'Coupon saved.', 'ggm-member-dashboard' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save coupon.', 'ggm-member-dashboard' ) ) );
		}
	}

	/**
	 * AJAX: Delete a coupon.
	 */
	public function ajax_delete_coupon() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$id = absint( $_POST['coupon_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid coupon ID.', 'ggm-member-dashboard' ) ) );
		}

		$ok = GGM_Coupon::delete( $id );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => __( 'Coupon deleted.', 'ggm-member-dashboard' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete coupon.', 'ggm-member-dashboard' ) ) );
		}
	}

	/**
	 * AJAX: Render the "View Profile" popup for one member — personal
	 * details, membership history, workshop registrations, and payments —
	 * as a server-rendered HTML fragment the Members page drops into a modal.
	 */
	public function ajax_view_member_profile() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}

		$user_id = absint( $_POST['user_id'] ?? 0 );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'ggm-member-dashboard' ) ) );
		}

		$phone      = ggm_get_member_phone( $user_id );
		$avatar_url = get_user_meta( $user_id, 'ggm_avatar_url', true ) ?: get_avatar_url( $user_id, array( 'size' => 64 ) );
		$member_fields = array(
			__( 'Address', 'ggm-member-dashboard' )         => get_user_meta( $user_id, 'ggm_full_address', true ),
			__( 'Pincode', 'ggm-member-dashboard' )         => get_user_meta( $user_id, 'ggm_pincode', true ),
			__( 'Gender', 'ggm-member-dashboard' )          => get_user_meta( $user_id, 'ggm_gender', true ),
			__( 'Age', 'ggm-member-dashboard' )             => get_user_meta( $user_id, 'ggm_age', true ),
			__( 'Weight', 'ggm-member-dashboard' )          => get_user_meta( $user_id, 'ggm_weight', true ),
			__( 'Blood Pressure', 'ggm-member-dashboard' )  => get_user_meta( $user_id, 'ggm_bp', true ),
			__( 'Glucose Level', 'ggm-member-dashboard' )   => get_user_meta( $user_id, 'ggm_glucose_level', true ),
			__( 'Diseases', 'ggm-member-dashboard' )        => get_user_meta( $user_id, 'ggm_diseases', true ),
			__( 'Payment Details', 'ggm-member-dashboard' ) => get_user_meta( $user_id, 'ggm_payment_details', true ),
			__( 'Declaration', 'ggm-member-dashboard' )     => get_user_meta( $user_id, 'ggm_declaration', true ),
		);

		$registrations = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_registrations_for_user( $user_id ) : array();
		$payments      = class_exists( 'GGM_Payment' ) ? GGM_Payment::get_for_user( $user_id ) : array();

		$status_bg     = array( 'active' => '#dcfce7', 'success' => '#dcfce7', 'expired' => '#fef3c7', 'pending' => '#fef3c7', 'cancelled' => '#fee2e2', 'failed' => '#fee2e2' );
		$status_color  = array( 'active' => '#16a34a', 'success' => '#16a34a', 'expired' => '#b45309', 'pending' => '#b45309', 'cancelled' => '#dc2626', 'failed' => '#dc2626' );
		$badge         = function ( $status ) use ( $status_bg, $status_color ) {
			$s = strtolower( (string) $status ) ?: 'active';
			return sprintf(
				'<span style="background:%s;color:%s;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600;text-transform:capitalize;">%s</span>',
				esc_attr( $status_bg[ $s ] ?? '#f3f4f6' ),
				esc_attr( $status_color[ $s ] ?? '#374151' ),
				esc_html( $s )
			);
		};

		ob_start();
		?>
		<div class="ggm-profile-header" style="display:flex;gap:16px;align-items:center;margin-bottom:20px;">
			<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
			<div>
				<h2 style="margin:0 0 4px;font-size:18px;"><?php echo esc_html( $user->display_name ); ?></h2>
				<div style="color:#666;font-size:13px;"><?php echo esc_html( $user->user_email ); ?><?php echo $phone ? ' &middot; ' . esc_html( $phone ) : ''; ?></div>
				<div style="color:#888;font-size:12px;margin-top:2px;">
					<?php
					printf(
						/* translators: %s: date joined */
						esc_html__( 'Joined %s', 'ggm-member-dashboard' ),
						esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) )
					);
					?>
					&middot;
					<a href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Edit in WordPress', 'ggm-member-dashboard' ); ?></a>
				</div>
			</div>
		</div>
		<h3 style="font-size:14px;margin:20px 0 8px;"><?php esc_html_e( 'Member Details', 'ggm-member-dashboard' ); ?></h3>
		<table class="widefat striped" style="margin-bottom:10px;">
			<tbody>
			<?php foreach ( $member_fields as $label => $value ) : ?>
				<tr><th style="width:150px;"><?php echo esc_html( $label ); ?></th><td style="white-space:pre-wrap;"><?php echo '' !== (string) $value ? esc_html( $value ) : '—'; ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h3 style="font-size:14px;margin:20px 0 8px;"><?php esc_html_e( 'Workshop Registrations', 'ggm-member-dashboard' ); ?></h3>
		<?php if ( empty( $registrations ) ) : ?>
			<p style="color:#888;font-size:13px;"><?php esc_html_e( 'No workshops registered.', 'ggm-member-dashboard' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="margin-bottom:10px;">
				<thead><tr>
					<th><?php esc_html_e( 'Workshop', 'ggm-member-dashboard' ); ?></th>
					<th><?php esc_html_e( 'Time Slot', 'ggm-member-dashboard' ); ?></th>
					<th><?php esc_html_e( 'Via', 'ggm-member-dashboard' ); ?></th>
					<th><?php esc_html_e( 'Registered', 'ggm-member-dashboard' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $registrations as $r ) : ?>
					<tr>
						<td>
							<?php if ( $r->workshop_id && $r->workshop_title ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $r->workshop_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r->workshop_title ); ?></a>
							<?php else : ?>
								<?php echo esc_html__( 'Workshop #', 'ggm-member-dashboard' ) . esc_html( $r->workshop_id ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $r->slot_label ?: '—' ); ?></td>
						<td><?php echo esc_html( ucfirst( $r->granted_via ?: '—' ) ); ?></td>
						<td><?php echo esc_html( $r->granted_at ? date_i18n( get_option( 'date_format' ), strtotime( $r->granted_at ) ) : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h3 style="font-size:14px;margin:20px 0 8px;"><?php esc_html_e( 'Payments Made', 'ggm-member-dashboard' ); ?></h3>
		<?php if ( empty( $payments ) ) : ?>
			<p style="color:#888;font-size:13px;"><?php esc_html_e( 'No payments recorded.', 'ggm-member-dashboard' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Item', 'ggm-member-dashboard' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'ggm-member-dashboard' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th>
					<th><?php esc_html_e( 'Razorpay Payment ID', 'ggm-member-dashboard' ); ?></th>
					<th><?php esc_html_e( 'Date', 'ggm-member-dashboard' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $payments as $p ) : ?>
					<?php $item_label = $p->workshop_id ? get_the_title( $p->workshop_id ) : ( $p->plan_name ?: '—' ); ?>
					<tr>
						<td><?php echo esc_html( $item_label ?: '—' ); ?></td>
						<td><?php echo esc_html( ggm_get_setting( 'ggm_currency_symbol', '₹' ) . number_format( (float) $p->amount, 2 ) ); ?></td>
						<td><?php echo $badge( $p->status ); ?></td>
						<td><code style="font-size:11px;"><?php echo esc_html( $p->razorpay_payment_id ?: '—' ); ?></code></td>
						<td><?php echo esc_html( $p->created_at ? date_i18n( get_option( 'date_format' ), strtotime( $p->created_at ) ) : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX: Find WordPress users that can be added to the GGM members list.
	 */
	public function ajax_search_wordpress_users() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 );
		}

		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'users' => array() ) );
		}

		$users   = get_users( array(
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'number'         => 15,
			'orderby'        => 'display_name',
			'order'          => 'ASC',
		) );
		$results = array();
		global $wpdb;
		$workshop_access = $wpdb->prefix . 'ggm_workshop_access';
		$course_access   = $wpdb->prefix . 'ggm_course_access';

		foreach ( $users as $user ) {
			$is_member = get_user_meta( $user->ID, 'ggm_member', true )
				|| metadata_exists( 'user', $user->ID, 'ggm_phone' )
				|| $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$workshop_access} WHERE user_id = %d LIMIT 1", $user->ID ) )
				|| $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$course_access} WHERE user_id = %d LIMIT 1", $user->ID ) );
			$results[] = array(
				'id'       => $user->ID,
				'name'     => $user->display_name ?: $user->user_login,
				'email'    => $user->user_email,
				'is_member' => (bool) $is_member,
			);
		}

		wp_send_json_success( array( 'users' => $results ) );
	}

	/**
	 * AJAX: Add an existing WordPress user as a member or create a new one.
	 */
	public function ajax_add_member() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 );
		}

		$mode = sanitize_key( $_POST['mode'] ?? '' );
		if ( 'existing' === $mode ) {
			$user_id = absint( $_POST['user_id'] ?? 0 );
			if ( ! get_userdata( $user_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select a valid WordPress user.', 'ggm-member-dashboard' ) ) );
			}
			update_user_meta( $user_id, 'ggm_member', 1 );
			wp_send_json_success( array( 'message' => __( 'WordPress user added as a member.', 'ggm-member-dashboard' ) ) );
		}

		if ( 'new' !== $mode ) {
			wp_send_json_error( array( 'message' => __( 'Invalid member type.', 'ggm-member-dashboard' ) ) );
		}

		$name      = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone_raw = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$phone     = '' !== $phone_raw ? ggm_normalize_member_phone( $phone_raw, '+91' ) : '';

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter the member name.', 'ggm-member-dashboard' ) ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'ggm-member-dashboard' ) ) );
		}
		if ( '' !== $phone_raw && '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid 10-digit Indian mobile number.', 'ggm-member-dashboard' ) ) );
		}
		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'That email already belongs to a WordPress user. Use the WordPress User tab instead.', 'ggm-member-dashboard' ) ) );
		}

		$base_login = sanitize_user( strstr( $email, '@', true ), true );
		$base_login = $base_login ?: 'member';
		$user_login = $base_login;
		$suffix     = 1;
		while ( username_exists( $user_login ) ) {
			$user_login = $base_login . $suffix++;
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $user_login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'display_name' => $name,
			'nickname'     => $name,
			'role'         => 'subscriber',
		) );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		update_user_meta( $user_id, 'ggm_member', 1 );
		if ( '' !== $phone ) {
			update_user_meta( $user_id, 'ggm_phone', $phone );
			update_user_meta( $user_id, 'billing_phone', $phone );
		}

		wp_send_json_success( array( 'message' => __( 'New member created successfully.', 'ggm-member-dashboard' ) ) );
	}

}
