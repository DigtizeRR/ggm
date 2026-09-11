<?php
/** Post-purchase workshop health intake and disease-message automation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GGM_Health_Intake {
	const META_KEY = 'ggm_health_intakes';
	const SKIPS_META_KEY = 'ggm_health_intake_skips';
	const RULES_OPTION = 'ggm_health_intake_rules';

	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'init', $this, 'redirect_legacy_admin_url' );
		$loader->add_action( 'template_redirect', $this, 'handle_customer_form_link', 1 );
		$loader->add_action( 'template_redirect', $this, 'handle_skip_request', 2 );
		$loader->add_action( 'wp_ajax_ggm_save_health_intake', $this, 'ajax_save' );
		$loader->add_action( 'wp_ajax_ggm_skip_health_intake', $this, 'ajax_skip' );
		$loader->add_action( 'wp_ajax_ggm_reopen_health_intake', $this, 'ajax_reopen' );
		// The DZ LMS parent menu is registered at the default priority (10).
		// Register this sensitive-data submenu afterwards so WordPress can attach
		// a valid page hook instead of rejecting the direct admin.php URL.
		$loader->add_action( 'admin_menu', $this, 'register_admin_page', 20 );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_media' );
		$loader->add_action( 'admin_post_ggm_save_health_rules', $this, 'save_rules' );
		$loader->add_action( 'admin_post_ggm_health_report', $this, 'download_report' );
		$loader->add_action( 'admin_post_ggm_delete_legacy_health_submission', $this, 'delete_legacy_submission' );
	}

	/**
	 * Permanent customer-facing link that survives dashboard page changes.
	 * Logged-out visitors authenticate first and are returned to the health tab.
	 */
	public function handle_customer_form_link() {
		$path = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
		if ( '/health-information-form' !== $path ) { return; }

		$dashboard_page_id = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );
		$dashboard_url = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/dashboard/' );
		// Keep both forms: the query parameter survives authentication and the
		// hash is understood by older/cached dashboard JavaScript immediately.
		$health_url = add_query_arg( 'ggm_tab', 'health', $dashboard_url ) . '#tab-health';
		if ( is_user_logged_in() ) {
			wp_safe_redirect( $health_url );
			exit;
		}

		$login_page_id = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
		$login_url = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/workshop-login/' );
		// Return through this stable route after authentication. It can then add
		// the dashboard fragment in a real redirect; fragments cannot safely be
		// nested inside the login page's redirect_to query parameter.
		$return_url = add_query_arg( 'continue', '1', home_url( '/health-information-form/' ) );
		wp_safe_redirect( add_query_arg( 'redirect_to', $return_url, $login_url ) );
		exit;
	}

	/**
	 * Redirect the previously shared pretty-looking wp-admin URL to the real
	 * WordPress admin page route instead of letting the theme render a 404.
	 */
	public function redirect_legacy_admin_url() {
		$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		if ( ! in_array( untrailingslashit( (string) $path ), array( '/wp-admin/ggm-health-intakes', '/ggm-health-intakes' ), true ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view health intakes.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ggm-health-intakes' ) );
		exit;
	}

	public static function pending_workshop( $user_id ) {
		$rows = self::workshops_for_user( $user_id );
		$submissions = get_user_meta( $user_id, self::META_KEY, true );
		$submissions = is_array( $submissions ) ? $submissions : array();
		$skips = get_user_meta( $user_id, self::SKIPS_META_KEY, true );
		$skips = is_array( $skips ) ? array_map( 'absint', $skips ) : array();
		foreach ( $rows as $row ) {
			if ( empty( $submissions[ (int) $row->workshop_id ] ) && ! in_array( (int) $row->workshop_id, $skips, true ) && get_post( $row->workshop_id ) ) {
				return (int) $row->workshop_id;
			}
		}
		return 0;
	}

	public static function workshops_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT workshop_id, granted_at FROM {$wpdb->prefix}ggm_workshop_access WHERE user_id=%d ORDER BY granted_at DESC",
			$user_id
		) );
	}

	public static function render_pending_form( $user_id ) {
		$workshop_id = self::pending_workshop( $user_id );
		if ( ! $workshop_id ) { return; }
		$user = get_userdata( $user_id );
		$phone = get_user_meta( $user_id, 'ggm_phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true );
		$checkout_email = sanitize_email( get_user_meta( $user_id, 'ggm_checkout_email', true ) );
		$billing_email  = sanitize_email( get_user_meta( $user_id, 'billing_email', true ) );
		$form_email     = is_email( $checkout_email ) ? $checkout_email : ( is_email( $billing_email ) ? $billing_email : $user->user_email );
		include GGM_PLUGIN_DIR . 'templates/dashboard/health-intake.php';
	}

	/**
	 * Process the dashboard's native Skip form without relying on admin-ajax.php.
	 */
	public function handle_skip_request() {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['ggm_health_action'] ?? '' ) );
		if ( 'skip' !== $action ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'ggm_skip_health_intake', 'ggm_health_skip_nonce' );

		$user_id     = get_current_user_id();
		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );
		if ( ! $workshop_id || ! class_exists( 'GGM_Workshop' ) || ! GGM_Workshop::user_has_access( $user_id, $workshop_id ) ) {
			wp_die( esc_html__( 'Workshop access could not be verified.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
		}

		$skips = get_user_meta( $user_id, self::SKIPS_META_KEY, true );
		$skips = is_array( $skips ) ? array_map( 'absint', $skips ) : array();
		if ( ! in_array( $workshop_id, $skips, true ) ) {
			$skips[] = $workshop_id;
			update_user_meta( $user_id, self::SKIPS_META_KEY, array_values( $skips ) );
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$dashboard_page_id = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );
			$redirect = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/dashboard/' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function ajax_skip() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Please log in again.', 'ggm-member-dashboard' ) ), 401 ); }
		$user_id = get_current_user_id();
		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );
		if ( ! $workshop_id || ! class_exists( 'GGM_Workshop' ) || ! GGM_Workshop::user_has_access( $user_id, $workshop_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Workshop access could not be verified.', 'ggm-member-dashboard' ) ), 403 );
		}
		$skips = get_user_meta( $user_id, self::SKIPS_META_KEY, true );
		$skips = is_array( $skips ) ? array_map( 'absint', $skips ) : array();
		if ( ! in_array( $workshop_id, $skips, true ) ) {
			$skips[] = $workshop_id;
			update_user_meta( $user_id, self::SKIPS_META_KEY, array_values( $skips ) );
		}
		wp_send_json_success();
	}

	public function ajax_reopen() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Please log in again.', 'ggm-member-dashboard' ) ), 401 ); }
		$user_id = get_current_user_id();
		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );
		if ( ! $workshop_id || ! class_exists( 'GGM_Workshop' ) || ! GGM_Workshop::user_has_access( $user_id, $workshop_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Workshop access could not be verified.', 'ggm-member-dashboard' ) ), 403 );
		}
		$skips = get_user_meta( $user_id, self::SKIPS_META_KEY, true );
		$skips = is_array( $skips ) ? array_map( 'absint', $skips ) : array();
		update_user_meta( $user_id, self::SKIPS_META_KEY, array_values( array_diff( $skips, array( $workshop_id ) ) ) );
		wp_send_json_success();
	}

	public function ajax_save() {
		check_ajax_referer( 'ggm_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Please log in again.', 'ggm-member-dashboard' ) ), 401 ); }
		$user_id = get_current_user_id();
		$user = get_userdata( $user_id );
		$workshop_id = absint( $_POST['workshop_id'] ?? 0 );
		if ( ! $workshop_id || ! class_exists( 'GGM_Workshop' ) || ! GGM_Workshop::user_has_access( $user_id, $workshop_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Workshop access could not be verified.', 'ggm-member-dashboard' ) ), 403 );
		}
		$required = array( 'full_name', 'phone', 'gender', 'dob_age', 'email', 'address', 'city', 'state', 'diseases', 'declaration' );
		foreach ( $required as $field ) {
			if ( '' === trim( (string) ( $_POST[ $field ] ?? '' ) ) ) { wp_send_json_error( array( 'message' => __( 'Please complete every required field.', 'ggm-member-dashboard' ) ) ); }
		}
		$email = sanitize_email( wp_unslash( $_POST['email'] ) );
		$country_code = class_exists( 'GGM_Form_Builder' )
			? GGM_Form_Builder::sanitize_country_dial_value( $_POST['country_code'] ?? '+91' )
			: '+91';
		$phone = ggm_normalize_member_phone( wp_unslash( $_POST['phone'] ), $country_code );
		if ( ! is_email( $email ) || '' === $phone ) { wp_send_json_error( array( 'message' => __( 'Enter a valid email and mobile number.', 'ggm-member-dashboard' ) ) ); }

		$report_path = '';
		if ( ! empty( $_FILES['medical_report']['name'] ) ) {
			if ( (int) $_FILES['medical_report']['size'] > 10 * MB_IN_BYTES ) { wp_send_json_error( array( 'message' => __( 'Medical report must be 10 MB or smaller.', 'ggm-member-dashboard' ) ) ); }
			$checked = wp_check_filetype_and_ext( $_FILES['medical_report']['tmp_name'], sanitize_file_name( $_FILES['medical_report']['name'] ), array( 'pdf'=>'application/pdf','jpg|jpeg'=>'image/jpeg','png'=>'image/png' ) );
			if ( empty( $checked['ext'] ) ) { wp_send_json_error( array( 'message' => __( 'Only PDF, JPG and PNG medical reports are allowed.', 'ggm-member-dashboard' ) ) ); }
			$dir = WP_CONTENT_DIR . '/ggm-private-health';
			if ( ! wp_mkdir_p( $dir ) ) { wp_send_json_error( array( 'message' => __( 'Secure report storage is unavailable.', 'ggm-member-dashboard' ) ) ); }
			if ( ! file_exists( $dir . '/.htaccess' ) ) { file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$filename = $user_id . '-' . $workshop_id . '-' . wp_generate_uuid4() . '.' . $checked['ext'];
			if ( ! move_uploaded_file( $_FILES['medical_report']['tmp_name'], $dir . '/' . $filename ) ) { wp_send_json_error( array( 'message' => __( 'The medical report could not be stored securely.', 'ggm-member-dashboard' ) ) ); }
			$report_path = $filename;
		}

		$fields = array( 'full_name','gender','dob_age','address','city','state','weight','bp','sugar_level','diseases','mentioned_diseases','note' );
		$data = array( 'workshop_id' => $workshop_id, 'workshop' => get_the_title( $workshop_id ), 'email' => $email, 'phone' => $phone, 'country_code' => $country_code, 'report_path' => $report_path, 'submitted_at' => current_time( 'mysql' ) );
		foreach ( $fields as $field ) { $data[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ?? '' ) ); }
		$data['declaration'] = '1';
		$all = get_user_meta( $user_id, self::META_KEY, true ); $all = is_array( $all ) ? $all : array(); $all[ $workshop_id ] = $data;
		update_user_meta( $user_id, self::META_KEY, $all );
		update_user_meta( $user_id, 'ggm_whatsapp_country_code', $country_code );
		update_user_meta( $user_id, 'ggm_phone', $phone ); update_user_meta( $user_id, 'billing_phone', $phone );
		update_user_meta( $user_id, 'ggm_checkout_email', $email ); update_user_meta( $user_id, 'billing_email', $email );
		// Replace a temporary checkout address when the submitted address is not
		// already attached to another WordPress account. WordPress must retain
		// its normal unique-email protection when the address already exists.
		if ( false !== strpos( $user->user_email, '@guest.invalid' ) ) {
			$email_owner = email_exists( $email );
			if ( ! $email_owner || (int) $email_owner === $user_id ) {
				wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
			}
		}
		update_user_meta( $user_id, 'ggm_diseases', $data['diseases'] ); update_user_meta( $user_id, 'ggm_bp', $data['bp'] ); update_user_meta( $user_id, 'ggm_glucose_level', $data['sugar_level'] ); update_user_meta( $user_id, 'ggm_weight', $data['weight'] );
		$this->run_rules( $user_id, $data );
		do_action( 'ggm_health_intake_submitted', $user_id, $workshop_id, $data );
		wp_send_json_success( array( 'message' => __( 'Your health form has been submitted successfully.', 'ggm-member-dashboard' ) ) );
	}

	private function run_rules( $user_id, array $data ) {
		$rules = get_option( self::RULES_OPTION, array() );
		$haystack = strtolower( $data['diseases'] . ' ' . $data['mentioned_diseases'] );
		$user = get_userdata( $user_id );
		foreach ( (array) $rules as $rule ) {
			$keywords = array_filter( array_map( 'trim', explode( ',', strtolower( $rule['keywords'] ?? '' ) ) ) );
			if ( ! $keywords || ! array_filter( $keywords, static function ( $word ) use ( $haystack ) { return false !== strpos( $haystack, $word ); } ) ) { continue; }
			$replace = array( '{name}' => $data['full_name'], '{workshop}' => $data['workshop'], '{diseases}' => $data['diseases'] );
			if ( ! empty( $rule['email_subject'] ) && ! empty( $rule['email_body'] ) ) {
				$attachments = array();
				$email_file = get_attached_file( absint( $rule['email_attachment_id'] ?? 0 ) );
				if ( $email_file && is_file( $email_file ) ) { $attachments[] = $email_file; }
				ggm_send_plugin_mail( $data['email'], strtr( $rule['email_subject'], $replace ), wpautop( strtr( $rule['email_body'], $replace ) ), array( 'Content-Type: text/html; charset=UTF-8' ), $attachments );
			}
			if ( ! empty( $rule['whatsapp'] ) ) {
				$message = strtr( $rule['whatsapp'], $replace );
				$whatsapp_file = wp_get_attachment_url( absint( $rule['whatsapp_attachment_id'] ?? 0 ) );
				if ( $whatsapp_file ) { $message .= "\n\n" . __( 'Download file:', 'ggm-member-dashboard' ) . ' ' . $whatsapp_file; }
				$this->send_wahob( $data['phone'], $message );
			}
		}
	}

	private function send_wahob( $phone, $message ) {
		$mode = ggm_get_setting( 'ggm_wahob_mode', 'test' ); $prefix = 'live' === $mode ? 'ggm_wahob_live_' : 'ggm_wahob_test_';
		$key = ggm_get_setting( $prefix . 'api_key', '' ); $secret = ggm_get_setting( $prefix . 'api_secret', '' ); if ( ! $key || ! $secret ) { return false; }
		$res = wp_remote_post( 'https://api.wahob.com/api/v1/messages/send', array( 'timeout' => 15, 'headers' => array( 'X-Api-Key'=>$key, 'X-Api-Secret'=>$secret, 'Content-Type'=>'application/json' ), 'body' => wp_json_encode( array( 'phone'=>'+91'.$phone, 'message'=>$message ) ) ) );
		return ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res );
	}

	public function register_admin_page() { add_submenu_page( 'ggm-lms', __( 'Health Intakes', 'ggm-member-dashboard' ), __( 'Health Intakes', 'ggm-member-dashboard' ), 'manage_options', 'ggm-health-intakes', array( $this, 'admin_page' ) ); }
	public function enqueue_admin_media() { if ( 'ggm-health-intakes' === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ) { wp_enqueue_media(); } }
	public function admin_page() { include GGM_PLUGIN_DIR . 'admin/views/health-forms.php'; }
	public function save_rules() {
		check_admin_referer( 'ggm_save_health_rules' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.' ); }
		$rules = array(); foreach ( (array) ( $_POST['rules'] ?? array() ) as $rule ) { $keywords = sanitize_text_field( wp_unslash( $rule['keywords'] ?? '' ) ); if ( ! $keywords ) { continue; } $rules[] = array( 'keywords'=>$keywords, 'email_subject'=>sanitize_text_field( wp_unslash( $rule['email_subject'] ?? '' ) ), 'email_body'=>sanitize_textarea_field( wp_unslash( $rule['email_body'] ?? '' ) ), 'email_attachment_id'=>$this->sanitize_attachment_id( $rule['email_attachment_id'] ?? 0 ), 'whatsapp'=>sanitize_textarea_field( wp_unslash( $rule['whatsapp'] ?? '' ) ), 'whatsapp_attachment_id'=>$this->sanitize_attachment_id( $rule['whatsapp_attachment_id'] ?? 0 ) ); }
		update_option( self::RULES_OPTION, $rules, false );
		wp_safe_redirect( admin_url( 'admin.php?page=ggm-health-intakes&saved=1' ) ); exit;
	}
	private function sanitize_attachment_id( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		return $attachment_id && 'attachment' === get_post_type( $attachment_id ) ? $attachment_id : 0;
	}
	public function download_report() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.' ); }
		$user_id=absint($_GET['user_id']??0); $workshop_id=absint($_GET['workshop_id']??0);
		$mode='preview'===sanitize_key($_GET['mode']??'')?'preview':'download';
		$nonce=sanitize_text_field(wp_unslash($_GET['_wpnonce']??''));
		$valid_nonce=wp_verify_nonce($nonce,'ggm_health_report_'.$user_id.'_'.$workshop_id.'_'.$mode);
		if(!$valid_nonce&&'download'===$mode){$valid_nonce=wp_verify_nonce($nonce,'ggm_health_report_'.$user_id.'_'.$workshop_id);}
		if(!$valid_nonce){wp_die(esc_html__('The report link has expired.','ggm-member-dashboard'),'',array('response'=>403));}
		$all=get_user_meta($user_id,self::META_KEY,true); $filename=basename((string)($all[$workshop_id]['report_path']??'')); $path=WP_CONTENT_DIR.'/ggm-private-health/'.$filename;
		if(!$filename||!is_file($path)){wp_die('Report not found.');}
		$type=wp_check_filetype($filename);
		$mime=$type['type']?:'application/octet-stream';
		if('preview'===$mode&&!in_array($mime,array('application/pdf','image/jpeg','image/png'),true)){wp_die(esc_html__('This report cannot be previewed safely. Please download it instead.','ggm-member-dashboard'),'',array('response'=>415));}
		nocache_headers();
		header('X-Content-Type-Options: nosniff');
		header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox");
		header('Content-Type: '.$mime);
		header('Content-Disposition: '.('preview'===$mode?'inline':'attachment').'; filename="medical-report-'.absint($user_id).'.'.sanitize_key($type['ext']).'"');
		header('Content-Length: '.filesize($path));
		readfile($path); exit; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	}

	public function delete_legacy_submission() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response'=>403 ) ); }
		$user_id = absint( $_GET['user_id'] ?? 0 );
		$key     = sanitize_text_field( wp_unslash( $_GET['legacy_key'] ?? '' ) );
		check_admin_referer( 'ggm_delete_legacy_health_submission_' . $user_id . '_' . $key );
		$redirect_args = array( 'page'=>'ggm-health-intakes', 'tab'=>'submissions' );
		$filter = sanitize_text_field( wp_unslash( $_GET['filter_form_id'] ?? '' ) );
		if ( 'legacy' === $filter ) { $redirect_args['form_id'] = 'legacy'; }
		elseif ( absint( $filter ) ) { $redirect_args['form_id'] = absint( $filter ); }
		$paged = max( 1, absint( $_GET['submissions_page'] ?? 1 ) );
		if ( $paged > 1 ) { $redirect_args['submissions_page'] = $paged; }
		$items = get_user_meta( $user_id, self::META_KEY, true );
		$items = is_array( $items ) ? $items : array();
		if ( ! $user_id || '' === $key || ! array_key_exists( $key, $items ) ) {
			$redirect_args['submission_deleted'] = 'missing';
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) ); exit;
		}
		$filename = basename( (string) ( $items[ $key ]['report_path'] ?? '' ) );
		if ( $filename ) {
			$path = WP_CONTENT_DIR . '/ggm-private-health/' . $filename;
			if ( is_file( $path ) ) { wp_delete_file( $path ); }
		}
		unset( $items[ $key ] );
		update_user_meta( $user_id, self::META_KEY, $items );
		$redirect_args['submission_deleted'] = '1';
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) ); exit;
	}
}
