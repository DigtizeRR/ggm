<?php
/**
 * Plugin Name: Digtize LMS System
 * Plugin URI:  https://digtize.com/
 * Description: Complete private member dashboard for Global Good Health Mission with OTP authentication and a fully standalone membership, coupon, and workshop system.
 * Version:     1.2.5
 * Author:      Rakesh Raushan
 * Author URI:  https://digtize.com/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ggm-member-dashboard
 * Domain Path: /languages
 *
 * @package GGM_Member_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Constants ───────────────────────────────────────────────────────────────
define( 'GGM_VERSION',       '1.2.5' );
define( 'GGM_DB_VERSION',    '3.3.0' );
define( 'GGM_PLUGIN_FILE',   __FILE__ );
define( 'GGM_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'GGM_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'GGM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Fixed-code demo mode is accepted only when explicitly enabled in wp-config.php
// and WordPress reports a local or development environment.
if ( ! defined( 'GGM_DEMO_MODE' ) ) {
	define( 'GGM_DEMO_MODE', false );
}

// ─── Core Includes ───────────────────────────────────────────────────────────
require_once GGM_PLUGIN_DIR . 'includes/class-ggm-loader.php';
require_once GGM_PLUGIN_DIR . 'includes/class-ggm-activator.php';
require_once GGM_PLUGIN_DIR . 'includes/class-ggm-deactivator.php';
require_once GGM_PLUGIN_DIR . 'includes/class-ggm-helpers.php';
require_once GGM_PLUGIN_DIR . 'includes/class-ggm-currency.php';
require_once GGM_PLUGIN_DIR . 'includes/class-ggm-meta-boxes.php';
require_once GGM_PLUGIN_DIR . 'includes/class-ggm-database.php';
require_once GGM_PLUGIN_DIR . 'includes/class-ggm-cpt.php';

// ─── Auth Modules ────────────────────────────────────────────────────────────
// OTP delivery is intentionally email-only; legacy SMS/WhatsApp provider
// classes are not loaded into the live runtime.
require_once GGM_PLUGIN_DIR . 'modules/auth/class-ggm-otp-email.php';
require_once GGM_PLUGIN_DIR . 'modules/auth/class-ggm-otp.php';
require_once GGM_PLUGIN_DIR . 'modules/auth/class-ggm-auth.php';

// ─── Coupon Module ───────────────────────────────────────────────────────────
require_once GGM_PLUGIN_DIR . 'modules/coupon/class-ggm-coupon.php';

// ─── Payment Modules ─────────────────────────────────────────────────────────
require_once GGM_PLUGIN_DIR . 'modules/payment/class-ggm-payment.php';
require_once GGM_PLUGIN_DIR . 'modules/payment/class-ggm-credit.php';
require_once GGM_PLUGIN_DIR . 'modules/payment/class-ggm-razorpay.php';
require_once GGM_PLUGIN_DIR . 'modules/integration/class-ggm-ghl.php';
require_once GGM_PLUGIN_DIR . 'modules/integration/class-ggm-elementor.php';
require_once GGM_PLUGIN_DIR . 'modules/integration/class-ggm-woocommerce-currency.php';

// ─── Workshop & Lesson Modules ───────────────────────────────────────────────
require_once GGM_PLUGIN_DIR . 'modules/workshop/class-ggm-workshop.php';
require_once GGM_PLUGIN_DIR . 'modules/workshop/class-ggm-workshop-slot.php';
require_once GGM_PLUGIN_DIR . 'modules/workshop/class-ggm-lesson.php';
require_once GGM_PLUGIN_DIR . 'modules/workshop/class-ggm-health-intake.php';
require_once GGM_PLUGIN_DIR . 'modules/workshop/class-ggm-form-builder.php';
require_once GGM_PLUGIN_DIR . 'modules/diseases/class-ggm-diseases.php';

// ─── Import / Export Module ──────────────────────────────────────────────────
require_once GGM_PLUGIN_DIR . 'modules/import-export/class-ggm-import-export.php';
require_once GGM_PLUGIN_DIR . 'modules/import-export/class-ggm-workshop-package.php';
require_once GGM_PLUGIN_DIR . 'modules/import-export/class-ggm-access-manager.php';

// ─── Dashboard Module ────────────────────────────────────────────────────────
require_once GGM_PLUGIN_DIR . 'modules/dashboard/class-ggm-dashboard.php';

// ─── Notification & REST API ─────────────────────────────────────────────────
require_once GGM_PLUGIN_DIR . 'modules/notification/class-ggm-notification.php';
require_once GGM_PLUGIN_DIR . 'modules/api/class-ggm-rest-api.php';

// ─── Admin ───────────────────────────────────────────────────────────────────
if ( is_admin() ) {
	require_once GGM_PLUGIN_DIR . 'admin/class-ggm-admin.php';
}

// ─── Public ──────────────────────────────────────────────────────────────────
require_once GGM_PLUGIN_DIR . 'public/class-ggm-public.php';
require_once GGM_PLUGIN_DIR . 'public/class-ggm-shortcodes.php';

// ─── Activation / Deactivation Hooks ─────────────────────────────────────────
register_activation_hook( __FILE__, array( 'GGM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GGM_Deactivator', 'deactivate' ) );

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function ggm_run() {
	// Run DB schema upgrades if the stored version is behind the current one.
	if ( get_option( 'ggm_db_version' ) !== GGM_DB_VERSION ) {
		GGM_Database::create_tables();
	}

	$loader = new GGM_Loader();

	// Meta Boxes registration.
	( new GGM_Meta_Boxes() )->init( $loader );

	// Auth / OTP.
	( new GGM_Auth() )->init( $loader );

	// Dashboard AJAX.
	( new GGM_Dashboard() )->init( $loader );

	// Course expiry, linked-course grants, and legacy enrollment import.
	GGM_Access_Manager::init();

	// CPTs.
	( new GGM_CPT() )->init( $loader );
	( new GGM_Health_Intake() )->init( $loader );
	( new GGM_Form_Builder() )->init( $loader );
	( new GGM_Diseases() )->init( $loader );

	// Razorpay.
	( new GGM_Razorpay() )->init( $loader );
	( new GGM_GHL() )->init( $loader );
	( new GGM_Elementor() )->init();
	( new GGM_WooCommerce_Currency() )->init( $loader );

	// REST API.
	( new GGM_REST_API() )->init( $loader );

	// Notifications.
	( new GGM_Notification() )->init( $loader );

	// Admin.
	if ( is_admin() ) {
		( new GGM_Admin() )->init( $loader );
	}

	// Public.
	( new GGM_Public() )->init( $loader );

	// Shortcodes (self-registers via add_shortcode).
	( new GGM_Shortcodes() )->init();

	$loader->run();
}
add_action( 'plugins_loaded', 'ggm_run' );
add_action( 'ggm_cleanup_otp_claim', array( 'GGM_OTP', 'cleanup_claim' ) );

/** Run the recoverable legacy phone cleanup once from an administrator request. */
function ggm_maybe_repair_legacy_member_phones() {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
	if ( 'ggm-lms-users' !== $page || ! current_user_can( 'manage_options' ) || get_option( 'ggm_phone_integrity_repair_v1' ) ) {
		return;
	}
	$summary = ggm_repair_legacy_member_phones();
	set_transient( 'ggm_phone_integrity_notice_' . get_current_user_id(), $summary, DAY_IN_SECONDS );
}
add_action( 'admin_init', 'ggm_maybe_repair_legacy_member_phones', 20 );

/** Show the one-time result of the recoverable phone cleanup. */
function ggm_legacy_member_phone_repair_notice() {
	$key     = 'ggm_phone_integrity_notice_' . get_current_user_id();
	$summary = get_transient( $key );
	if ( ! is_array( $summary ) ) {
		return;
	}
	delete_transient( $key );
	?>
	<div class="notice notice-info is-dismissible"><p>
		<?php
		echo esc_html( sprintf(
			/* translators: 1: repaired corrupt values, 2: canonicalized values, 3: ambiguous values left unchanged. */
			__( 'Member phone audit complete: %1$d malformed value(s) repaired, %2$d value(s) canonicalized, and %3$d ambiguous value(s) left unchanged for manual review. Original changed values were backed up.', 'ggm-member-dashboard' ),
			(int) ( $summary['repaired'] ?? 0 ),
			(int) ( $summary['canonicalized'] ?? 0 ),
			(int) ( $summary['ambiguous'] ?? 0 )
		) );
		?>
	</p></div>
	<?php
}
add_action( 'admin_notices', 'ggm_legacy_member_phone_repair_notice' );

/**
 * Download the complete Members list as CSV. The optional search value uses
 * the same name/username/email/phone filter as DZ LMS → Members.
 */
function ggm_export_members_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ggm_export_members' );

	global $wpdb;
	$search      = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$workshop_id = absint( $_GET['workshop_id'] ?? 0 );
	if ( $workshop_id && ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
		$workshop_id = 0;
	}
	$workshop_access = $wpdb->prefix . 'ggm_workshop_access';
	$course_access   = $wpdb->prefix . 'ggm_course_access';
	$payments_table  = $wpdb->prefix . 'ggm_payments';
	$params          = array();
	$search_sql      = '';
	$scope_sql       = "(
		EXISTS (SELECT 1 FROM {$workshop_access} wa2 WHERE wa2.user_id=u.ID)
		OR EXISTS (SELECT 1 FROM {$course_access} ca2 WHERE ca2.user_id=u.ID)
		OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} gm WHERE gm.user_id=u.ID AND gm.meta_key='ggm_phone')
		OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} mm WHERE mm.user_id=u.ID AND mm.meta_key='ggm_member')
	)";
	if ( $workshop_id ) {
		$scope_sql = "EXISTS (SELECT 1 FROM {$workshop_access} wa2 WHERE wa2.user_id=u.ID AND wa2.workshop_id=%d)";
		$params[]  = $workshop_id;
	}

	if ( '' !== $search ) {
		$like       = '%' . $wpdb->esc_like( $search ) . '%';
		$search_sql = " AND (u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s OR EXISTS (
			SELECT 1 FROM {$wpdb->usermeta} sm
			WHERE sm.user_id = u.ID AND sm.meta_key IN ('ggm_phone','billing_phone') AND sm.meta_value LIKE %s
		))";
		$params = array_merge( $params, array( $like, $like, $like, $like ) );
	}

	$sql = "SELECT u.ID, u.display_name, u.user_login, u.user_email, u.user_registered,
		COALESCE((SELECT pm.meta_value FROM {$wpdb->usermeta} pm WHERE pm.user_id=u.ID AND pm.meta_key='ggm_phone' ORDER BY pm.umeta_id DESC LIMIT 1), '') AS ggm_phone,
		COALESCE((SELECT bm.meta_value FROM {$wpdb->usermeta} bm WHERE bm.user_id=u.ID AND bm.meta_key='billing_phone' ORDER BY bm.umeta_id DESC LIMIT 1), '') AS billing_phone,
		COALESCE((SELECT cm.meta_value FROM {$wpdb->usermeta} cm WHERE cm.user_id=u.ID AND cm.meta_key='ggm_whatsapp_country_code' ORDER BY cm.umeta_id DESC LIMIT 1), '+91') AS country_code,
		(SELECT COUNT(*) FROM {$payments_table} p WHERE p.user_id=u.ID AND p.status='success') AS purchase_count
	FROM {$wpdb->users} u
	WHERE {$scope_sql} {$search_sql}
	ORDER BY u.ID DESC";

	$members = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="ggm-members-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
	header( 'X-Content-Type-Options: nosniff' );

	$output = fopen( 'php://output', 'w' );
	if ( false === $output ) {
		wp_die( esc_html__( 'Could not create the CSV export.', 'ggm-member-dashboard' ) );
	}
	// UTF-8 BOM keeps names and phone values readable when opened in Excel.
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'Member ID', 'Name', 'Username', 'Email', 'Phone', 'Purchase Count', 'Purchase Status', 'Joined Date' ), ',', '"', '\\' );

	$csv_safe = static function ( $value ) {
		$value = (string) $value;
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	};
	foreach ( $members as $member ) {
		$purchases = (int) $member->purchase_count;
		$phone     = ggm_normalize_member_phone( $member->ggm_phone, $member->country_code )
			?: ggm_normalize_member_phone( $member->billing_phone, $member->country_code );
		fputcsv( $output, array(
			(int) $member->ID,
			$csv_safe( $member->display_name ),
			$csv_safe( $member->user_login ),
			$csv_safe( $member->user_email ),
			$csv_safe( $phone ),
			$purchases,
			$purchases > 0 ? 'Purchased' : 'Not Purchased',
			$member->user_registered,
		), ',', '"', '\\' );
	}
	fclose( $output );
	exit;
}
add_action( 'admin_post_ggm_export_members', 'ggm_export_members_csv' );

/**
 * Fallback Custom Post Type registrations.
 * Runs on init only if the post types are not already registered by a theme or another plugin
 * (e.g. when SCF/ACF is deactivated).
 */
function ggm_register_cpts_fallback() {
	if ( ! post_type_exists( 'workshop' ) ) {
		register_post_type( 'workshop', array(
			'labels'             => array(
				'name'               => _x( 'Workshops', 'post type general name', 'ggm-member-dashboard' ),
				'singular_name'      => _x( 'Workshop', 'post type singular name', 'ggm-member-dashboard' ),
				'menu_name'          => _x( 'Workshops', 'admin menu', 'ggm-member-dashboard' ),
				'name_admin_bar'     => _x( 'Workshop', 'add new on admin bar', 'ggm-member-dashboard' ),
				'add_new'            => _x( 'Add New', 'workshop', 'ggm-member-dashboard' ),
				'add_new_item'       => __( 'Add New Workshop', 'ggm-member-dashboard' ),
				'new_item'           => __( 'New Workshop', 'ggm-member-dashboard' ),
				'edit_item'          => __( 'Edit Workshop', 'ggm-member-dashboard' ),
				'view_item'          => __( 'View Workshop', 'ggm-member-dashboard' ),
				'all_items'          => __( 'All Workshops', 'ggm-member-dashboard' ),
				'search_items'       => __( 'Search Workshops', 'ggm-member-dashboard' ),
				'parent_item_colon'  => __( 'Parent Workshops:', 'ggm-member-dashboard' ),
				'not_found'          => __( 'No workshops found.', 'ggm-member-dashboard' ),
				'not_found_in_trash' => __( 'No workshops found in Trash.', 'ggm-member-dashboard' )
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'workshop' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' )
		) );
	}

	if ( ! post_type_exists( 'course' ) ) {
		register_post_type( 'course', array(
			'labels'             => array(
				'name'               => _x( 'Courses', 'post type general name', 'ggm-member-dashboard' ),
				'singular_name'      => _x( 'Course', 'post type singular name', 'ggm-member-dashboard' ),
				'menu_name'          => _x( 'Courses', 'admin menu', 'ggm-member-dashboard' ),
				'name_admin_bar'     => _x( 'Course', 'add new on admin bar', 'ggm-member-dashboard' ),
				'add_new'            => _x( 'Add New', 'course', 'ggm-member-dashboard' ),
				'add_new_item'       => __( 'Add New Course', 'ggm-member-dashboard' ),
				'new_item'           => __( 'New Course', 'ggm-member-dashboard' ),
				'edit_item'          => __( 'Edit Course', 'ggm-member-dashboard' ),
				'view_item'          => __( 'View Course', 'ggm-member-dashboard' ),
				'all_items'          => __( 'All Courses', 'ggm-member-dashboard' ),
				'search_items'       => __( 'Search Courses', 'ggm-member-dashboard' ),
				'parent_item_colon'  => __( 'Parent Courses:', 'ggm-member-dashboard' ),
				'not_found'          => __( 'No courses found.', 'ggm-member-dashboard' ),
				'not_found_in_trash' => __( 'No courses found in Trash.', 'ggm-member-dashboard' )
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'course' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' )
		) );
	}

	if ( ! post_type_exists( 'lesson' ) ) {
		register_post_type( 'lesson', array(
			'labels'             => array(
				'name'               => _x( 'Lessons', 'post type general name', 'ggm-member-dashboard' ),
				'singular_name'      => _x( 'Lesson', 'post type singular name', 'ggm-member-dashboard' ),
				'menu_name'          => _x( 'Lessons', 'admin menu', 'ggm-member-dashboard' ),
				'name_admin_bar'     => _x( 'Lesson', 'add new on admin bar', 'ggm-member-dashboard' ),
				'add_new'            => _x( 'Add New', 'lesson', 'ggm-member-dashboard' ),
				'add_new_item'       => __( 'Add New Lesson', 'ggm-member-dashboard' ),
				'new_item'           => __( 'New Lesson', 'ggm-member-dashboard' ),
				'edit_item'          => __( 'Edit Lesson', 'ggm-member-dashboard' ),
				'view_item'          => __( 'View Lesson', 'ggm-member-dashboard' ),
				'all_items'          => __( 'All Lessons', 'ggm-member-dashboard' ),
				'search_items'       => __( 'Search Lessons', 'ggm-member-dashboard' ),
				'parent_item_colon'  => __( 'Parent Lessons:', 'ggm-member-dashboard' ),
				'not_found'          => __( 'No lessons found.', 'ggm-member-dashboard' ),
				'not_found_in_trash' => __( 'No lessons found in Trash.', 'ggm-member-dashboard' )
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'course/%course_name%', 'with_front' => false ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' )
		) );
	}

	if ( ! post_type_exists( 'ggm_mentor' ) ) {
		register_post_type( 'ggm_mentor', array(
			'labels'             => array(
				'name'               => _x( 'Mentors', 'post type general name', 'ggm-member-dashboard' ),
				'singular_name'      => _x( 'Mentor', 'post type singular name', 'ggm-member-dashboard' ),
				'menu_name'          => _x( 'Mentors', 'admin menu', 'ggm-member-dashboard' ),
				'name_admin_bar'     => _x( 'Mentor', 'add new on admin bar', 'ggm-member-dashboard' ),
				'add_new'            => _x( 'Add New', 'mentor', 'ggm-member-dashboard' ),
				'add_new_item'       => __( 'Add New Mentor', 'ggm-member-dashboard' ),
				'new_item'           => __( 'New Mentor', 'ggm-member-dashboard' ),
				'edit_item'          => __( 'Edit Mentor', 'ggm-member-dashboard' ),
				'view_item'          => __( 'View Mentor', 'ggm-member-dashboard' ),
				'all_items'          => __( 'All Mentors', 'ggm-member-dashboard' ),
				'search_items'       => __( 'Search Mentors', 'ggm-member-dashboard' ),
				'parent_item_colon'  => __( 'Parent Mentors:', 'ggm-member-dashboard' ),
				'not_found'          => __( 'No mentors found.', 'ggm-member-dashboard' ),
				'not_found_in_trash' => __( 'No mentors found in Trash.', 'ggm-member-dashboard' )
			),
			'public'             => true,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'custom-fields' )
		) );
	}
}
add_action( 'init', 'ggm_register_cpts_fallback', 5 );

/**
 * One-time repair for sites activated before the settings-storage fix above:
 * older versions of GGM_Activator wrote page IDs (checkout/login/dashboard)
 * and gateway defaults as bare top-level options instead of into the
 * `ggm_settings` array that ggm_get_setting() actually reads, so they were
 * silently ignored. Also carries forward a Razorpay Key ID/Secret entered
 * under the old single-pair field into the test-mode fields the payment
 * code expects. Runs once, guarded by a flag, so it never overwrites
 * settings an admin has since configured through the UI.
 */
function ggm_migrate_legacy_settings() {
	if ( get_option( 'ggm_settings_migrated_v1' ) ) {
		return;
	}

	$settings = get_option( 'ggm_settings', array() );
	$changed  = false;

	$legacy_page_options = array( 'ggm_checkout_page_id', 'ggm_login_page_id', 'ggm_dashboard_page_id' );
	foreach ( $legacy_page_options as $key ) {
		if ( empty( $settings[ $key ] ) ) {
			$legacy_id = (int) get_option( $key );
			if ( $legacy_id && get_post( $legacy_id ) ) {
				$settings[ $key ] = $legacy_id;
				$changed          = true;
			}
		}
	}

	// Old single-pair Razorpay fields -> the test-mode fields the code reads.
	if ( ! empty( $settings['ggm_razorpay_key_id'] ) && empty( $settings['ggm_razorpay_test_key_id'] ) ) {
		$settings['ggm_razorpay_test_key_id'] = $settings['ggm_razorpay_key_id'];
		$changed                              = true;
	}
	if ( ! empty( $settings['ggm_razorpay_key_secret'] ) && empty( $settings['ggm_razorpay_test_key_secret'] ) ) {
		$settings['ggm_razorpay_test_key_secret'] = $settings['ggm_razorpay_key_secret'];
		$changed                                  = true;
	}

	if ( $changed ) {
		update_option( 'ggm_settings', $settings );
	}

	update_option( 'ggm_settings_migrated_v1', 1 );
}
add_action( 'admin_init', 'ggm_migrate_legacy_settings' );

/**
 * Register the %course_name% rewrite tag used in lesson CPT URLs.
 * Must run after CPTs are registered (priority 15).
 */
add_action( 'init', function () {
	add_rewrite_tag( '%course_name%', '([^/]+)', 'course_name=' );

	// Explicit rewrite rule so lesson URLs resolve correctly even when
	// the auto-generated CPT rule ordering is unpredictable.
	// Pattern: /course/{course-slug}/{lesson-slug}/
	add_rewrite_rule(
		'course/([^/]+)/([^/]+)/?$',
		'index.php?post_type=lesson&name=$matches[2]',
		'top'
	);
}, 15 );

/**
 * Build the correct nested permalink for lesson CPT:
 *   /course/{course-slug}/{lesson-slug}/
 */
add_filter( 'post_type_link', function ( $url, $post, $leavename ) {
	if ( 'lesson' !== $post->post_type || $leavename ) {
		return $url;
	}

	$course_id = get_post_meta( $post->ID, 'course', true );
	if ( $course_id ) {
		$course = get_post( absint( $course_id ) );
		if ( $course && 'publish' === $course->post_status ) {
			return home_url( '/course/' . $course->post_name . '/' . $post->post_name . '/' );
		}
	}

	// Fallback: no parent course assigned — use plain slug.
	return home_url( '/course/' . $post->post_name . '/' );
}, 10, 3 );

/**
 * One-time rewrite flush when the lesson URL structure changes.
 * Fires on first admin page load after this version is deployed.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'ggm_rewrite_version' ) !== '1.2.0' ) {
		flush_rewrite_rules( false );
		update_option( 'ggm_rewrite_version', '1.2.0' );
	}
} );
