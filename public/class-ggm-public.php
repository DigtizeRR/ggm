<?php
/**
 * Front-end controller.
 *
 * Smart asset loading: detects GGM pages by page ID (settings),
 * post content shortcode, AND Elementor widget data.
 * Falls back to loading on every page if page IDs are not configured.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Public {
	private static $dashboard_assets_localized = false;

	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_assets' );
		$loader->add_action( 'wp_head',            $this, 'inject_custom_styles', 99 );
		$loader->add_action( 'template_redirect',  $this, 'handle_redirects' );
		$loader->add_action( 'template_redirect',  $this, 'register_workshop_preview_diagnostics', 1 );
		$loader->add_filter( 'logout_redirect',    $this, 'redirect_after_logout', 10, 3 );
		$loader->add_filter( 'show_admin_bar',     $this, 'hide_admin_bar_for_members' );
		$loader->add_action( 'admin_init',         $this, 'block_wp_admin_for_members' );
		$loader->add_action( 'send_headers',       $this, 'prevent_caching_on_dynamic_pages' );
	}

	/**
	 * Record otherwise-hidden fatal errors from an authorized draft Workshop
	 * preview in the plugin Error Log. No form answers or request payloads are
	 * recorded.
	 */
	public function register_workshop_preview_diagnostics() {
		if ( ! is_preview() || ! is_singular( array( 'workshop', 'ggm_workshop' ) ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		register_shutdown_function( static function () use ( $post_id ) {
			$error = error_get_last();
			if ( ! $error || ! in_array( (int) $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
				return;
			}
			if ( class_exists( 'GGM_Meta_Boxes' ) ) {
				GGM_Meta_Boxes::log_error( 'Draft Workshop preview fatal: ' . sanitize_text_field( $error['message'] ?? '' ), array(
					'level'     => 'fatal',
					'post_id'   => $post_id,
					'post_type' => get_post_type( $post_id ),
					'file'      => sanitize_text_field( $error['file'] ?? '' ),
					'line'      => absint( $error['line'] ?? 0 ),
				) );
			}
		} );
	}

	/**
	 * Never let a page-cache plugin (WP Rocket, WP Super Cache, W3 Total
	 * Cache, LiteSpeed Cache, etc.) serve a stale copy of the checkout,
	 * login, or member dashboard page. All three are per-visitor/personalized
	 * (nonces, account details, payment history) — a cached copy is at best
	 * a stale nonce breaking every AJAX call with an opaque error, and at
	 * worst one visitor's personal details or account data leaking to every
	 * other visitor of that same cached page. DONOTCACHEPAGE is the
	 * de-facto standard constant the caching plugins above all honor to
	 * skip a request.
	 */
	public function prevent_caching_on_dynamic_pages() {
		$pages = $this->detect_page();
		$currency_product_page = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() && $this->is_woocommerce_currency_page();
		if ( ! $pages['is_checkout'] && ! $pages['is_login'] && ! $pages['is_dashboard'] && ! $pages['is_signup'] && ! $currency_product_page ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! defined( 'DONOTMINIFY' ) ) {
			define( 'DONOTMINIFY', true );
		}

		nocache_headers();
	}

	/**
	 * Members (accounts without at least edit_posts — the typical Subscriber
	 * role a phone/OTP checkout signup creates) never need the WordPress
	 * admin toolbar cluttering the front end of a member/course site.
	 *
	 * @param bool $show
	 * @return bool
	 */
	public function hide_admin_bar_for_members( $show ) {
		if ( is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * A member who ends up at /wp-admin/ — most commonly by clicking the
	 * admin toolbar's "Dashboard" link right after an OTP/checkout
	 * auto-login — is sent straight back to this plugin's own front-end
	 * member dashboard instead of the WordPress backend, which isn't built
	 * for them and reads as "the site is broken" to a non-technical customer.
	 */
	public function block_wp_admin_for_members() {
		if ( wp_doing_ajax() || current_user_can( 'edit_posts' ) ) {
			return;
		}

		$dash_page_id = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );
		$dash_url     = $dash_page_id ? get_permalink( $dash_page_id ) : home_url( '/dashboard/' );

		wp_safe_redirect( $dash_url ?: home_url( '/' ) );
		exit;
	}

	public function redirect_after_logout( $redirect_to, $requested_redirect_to, $user ) {
		$login_page_id = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
		$login_url     = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/workshop-login/' );
		return $login_url ?: $redirect_to;
	}

	/**
	 * Detect which GGM page we are on.
	 * Checks: page ID → post_content shortcode → Elementor widget data.
	 */
	private function detect_page() {
		$login_page_id    = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
		$dash_page_id     = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );
		$checkout_page_id = (int) ggm_get_setting( 'ggm_checkout_page_id', 0 );
		$signup_page_id   = (int) ggm_get_setting( 'ggm_signup_page_id', 0 );

		// By page ID — most reliable.
		$is_login     = $login_page_id    && is_page( $login_page_id );
		$is_dashboard = $dash_page_id     && is_page( $dash_page_id );
		$is_checkout  = $checkout_page_id && is_page( $checkout_page_id );
		$is_signup    = $signup_page_id   && is_page( $signup_page_id );
		// Keep the default dashboard route functional if its saved page setting
		// is missing or stale. The shortcode/content checks below still handle
		// installations that use a different slug.
		if ( ! $is_dashboard && is_page( 'dashboard' ) ) {
			$is_dashboard = true;
		}

		// Fallback: search post content + Elementor meta.
		if ( ! $is_login || ! $is_dashboard ) {
			global $post;
			if ( $post ) {
				$content          = $post->post_content ?? '';
				$elementor_data   = get_post_meta( $post->ID, '_elementor_data', true );
				$elementor_str    = is_string( $elementor_data ) ? $elementor_data : wp_json_encode( $elementor_data );

				$login_shortcodes  = array( 'ggm_login', 'ggm_login_page' );
				$dash_shortcodes   = array( 'ggm_dashboard' );
				$checkout_sc       = array( 'ggm_checkout' );
				$signup_shortcodes = array( 'ggm_signup' );

				foreach ( $login_shortcodes as $sc ) {
					if ( has_shortcode( $content, $sc ) || ( $elementor_str && strpos( $elementor_str, '[' . $sc ) !== false ) ) {
						$is_login = true;
						break;
					}
				}

				foreach ( $dash_shortcodes as $sc ) {
					if ( has_shortcode( $content, $sc ) || ( $elementor_str && strpos( $elementor_str, '[' . $sc ) !== false ) ) {
						$is_dashboard = true;
						break;
					}
				}

				foreach ( $checkout_sc as $sc ) {
					if ( has_shortcode( $content, $sc ) || ( $elementor_str && strpos( $elementor_str, '[' . $sc ) !== false ) ) {
						$is_checkout = true;
						break;
					}
				}

				foreach ( $signup_shortcodes as $sc ) {
					if ( has_shortcode( $content, $sc ) || ( $elementor_str && strpos( $elementor_str, '[' . $sc ) !== false ) ) {
						$is_signup = true;
						break;
					}
				}
			}
		}

		// Ultimate fallback: if no page IDs configured at all, load login assets everywhere.
		if ( ! $login_page_id && ! $is_login ) {
			$is_login = true;
		}

		$is_workshop = is_singular( array( 'workshop', 'ggm_workshop' ) );
		$is_lesson   = is_singular( array( 'lesson', 'ggm_lesson' ) );

		// Also load public CSS on any page/post that uses these shortcodes.
		if ( ! $is_workshop && ! $is_lesson ) {
			global $post;
			if ( $post ) {
				$content        = $post->post_content ?? '';
				$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
				$elementor_str  = is_string( $elementor_data ) ? $elementor_data : wp_json_encode( $elementor_data );
				$public_scs     = array(
					'ggm_courses', 'ggm_course_purchase', 'ggm_course_short_description', 'course_short_description', 'course_curriculum', 'lesson_navigation', 'lesson_count',
					'back_to_course', 'ggm_lesson_video', 'ggm_lesson_sidebar',
					'ggm_workshop_date', 'ggm_workshop_time', 'ggm_workshop_price', 'ggm_workshop_contribution', 'ggm_workshop_contribution_pills',
					'ggm_workshop_mode', 'ggm_workshop_duration', 'ggm_workshop_linked_course',
					'ggm_workshop_slots_select', 'ggm_workshop_enroll', 'ggm_workshop_discover',
					'ggm_workshop_why_different', 'ggm_workshop_why_workshop_is_different', 'ggm_workshop_journey', 'ggm_workshop_perfect_for', 'ggm_workshop_faq',
					'ggm_workshop_icon_list', 'ggm_workshop_countdown', 'ggm_workshop_heading', 'ggm_workshop_header_pill',
					'ggm_workshop_start_date_detail', 'ggm_workshop_preparatory_date_detail',
					'ggm_workshop_duration_detail', 'ggm_workshop_time_slot_detail',
					'ggm_workshop_language_detail', 'ggm_workshop_contribution_detail',
					'ggm_workshop_booking_card', 'ggm_workshop_booking_card_hero_header',
					'ggm_workshop_join_now', 'ggm_workshop_join_form',
					'ggm_workshop_join_form_full', 'ggm_workshop_mentor',
					'ggm_workshop_description', 'ggm_form',
				);
				foreach ( $public_scs as $sc ) {
					if ( has_shortcode( $content, $sc ) || ( $elementor_str && strpos( $elementor_str, '[' . $sc ) !== false ) ) {
						$is_workshop = true; // piggyback to trigger ggm-public.css enqueue
						break;
					}
				}
			}
		}

		return compact( 'is_login', 'is_dashboard', 'is_checkout', 'is_signup', 'is_workshop', 'is_lesson' );
	}

	public function enqueue_assets() {
		// Never load plugin assets inside the Elementor editor — it crashes Elementor's JS.
		if ( self::is_elementor_editor_request() ) {
			return;
		}

		$pages = $this->detect_page();
		$public_css_path     = GGM_PLUGIN_DIR . 'assets/css/ggm-public.css';
		$shadowless_css_path = GGM_PLUGIN_DIR . 'assets/css/ggm-shortcode-shadowless.css';
		$razorpay_js_path    = GGM_PLUGIN_DIR . 'assets/js/ggm-razorpay.js';
		$currency_js_path    = GGM_PLUGIN_DIR . 'assets/js/ggm-currency.js';
		$form_popup_css_path = GGM_PLUGIN_DIR . 'assets/css/ggm-form-popup.css';
		$form_popup_js_path  = GGM_PLUGIN_DIR . 'assets/js/ggm-form-popup.js';
		$country_picker_css_path = GGM_PLUGIN_DIR . 'assets/css/ggm-country-picker.css';
		$country_picker_js_path  = GGM_PLUGIN_DIR . 'assets/js/ggm-country-picker.js';
		$login_js_path       = GGM_PLUGIN_DIR . 'assets/js/ggm-login.js';
		$signup_js_path      = GGM_PLUGIN_DIR . 'assets/js/ggm-signup.js';
		$login_css_path      = GGM_PLUGIN_DIR . 'assets/css/ggm-login.css';
		$public_css_ver      = file_exists( $public_css_path ) ? (string) filemtime( $public_css_path ) : GGM_VERSION;
		$shadowless_css_ver  = file_exists( $shadowless_css_path ) ? (string) filemtime( $shadowless_css_path ) : GGM_VERSION;
		$razorpay_js_ver     = file_exists( $razorpay_js_path ) ? (string) filemtime( $razorpay_js_path ) : GGM_VERSION;
		$currency_js_ver     = file_exists( $currency_js_path ) ? (string) filemtime( $currency_js_path ) : GGM_VERSION;
		$form_popup_css_ver  = file_exists( $form_popup_css_path ) ? (string) filemtime( $form_popup_css_path ) : GGM_VERSION;
		$form_popup_js_ver   = file_exists( $form_popup_js_path ) ? (string) filemtime( $form_popup_js_path ) : GGM_VERSION;
		$country_picker_css_ver = file_exists( $country_picker_css_path ) ? (string) filemtime( $country_picker_css_path ) : GGM_VERSION;
		$country_picker_js_ver  = file_exists( $country_picker_js_path ) ? (string) filemtime( $country_picker_js_path ) : GGM_VERSION;
		$login_js_ver        = file_exists( $login_js_path ) ? (string) filemtime( $login_js_path ) : GGM_VERSION;
		$signup_js_ver       = file_exists( $signup_js_path ) ? (string) filemtime( $signup_js_path ) : GGM_VERSION;
		$login_css_ver       = file_exists( $login_css_path ) ? (string) filemtime( $login_css_path ) : GGM_VERSION;

		wp_enqueue_style( 'ggm-form-popup-css', GGM_PLUGIN_URL . 'assets/css/ggm-form-popup.css', array(), $form_popup_css_ver );
		wp_enqueue_script( 'ggm-form-popup-js', GGM_PLUGIN_URL . 'assets/js/ggm-form-popup.js', array(), $form_popup_js_ver, true );
		wp_enqueue_style( 'ggm-country-picker-css', GGM_PLUGIN_URL . 'assets/css/ggm-country-picker.css', array(), $country_picker_css_ver );
		wp_enqueue_script( 'ggm-country-picker-js', GGM_PLUGIN_URL . 'assets/js/ggm-country-picker.js', array(), $country_picker_js_ver, true );
		wp_localize_script( 'ggm-form-popup-js', 'ggmFormPopup', array(
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'label'       => __( 'GGM form', 'ggm-member-dashboard' ),
			'close_label' => __( 'Close form popup', 'ggm-member-dashboard' ),
			'loading'     => __( 'Loading form...', 'ggm-member-dashboard' ),
			'error'       => __( 'Could not load this form.', 'ggm-member-dashboard' ),
		) );

		$is_woocommerce_page = $this->is_woocommerce_currency_page();
		$is_plugin_page = $pages['is_login'] || $pages['is_dashboard'] || $pages['is_checkout']
		                  || $pages['is_signup'] || $pages['is_workshop'] || $pages['is_lesson'] || $is_woocommerce_page;

		if ( ! $is_plugin_page ) {
			// Shortcodes can be rendered outside the queried post (for example
			// by a Theme Builder template, widget, FSE template, or direct
			// do_shortcode() call). Keep the small namespace-scoped guard
			// available on every front-end request so those paths cannot inherit
			// a theme or page-builder shadow.
			wp_enqueue_style(
				'ggm-shortcode-shadowless-css',
				GGM_PLUGIN_URL . 'assets/css/ggm-shortcode-shadowless.css',
				array(),
				$shadowless_css_ver
			);
			return;
		}

		// Google Fonts — only on GGM pages.
		wp_enqueue_style(
			'ggm-google-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@600&display=swap',
			array(),
			null
		);

		// ── Login page ─────────────────────────────────────────────────────────
		if ( $pages['is_login'] ) {
			wp_enqueue_style( 'ggm-login-css', GGM_PLUGIN_URL . 'assets/css/ggm-login.css', array(), $login_css_ver );

			wp_enqueue_script(
				'ggm-login-js',
				GGM_PLUGIN_URL . 'assets/js/ggm-login.js',
				array( 'jquery' ),
				$login_js_ver,
				true
			);

			wp_localize_script( 'ggm-login-js', 'ggmData', $this->login_js_data() );
		}

		// ── Signup page — reuses the login page's CSS for consistent styling ────
		if ( $pages['is_signup'] ) {
			wp_enqueue_style( 'ggm-login-css', GGM_PLUGIN_URL . 'assets/css/ggm-login.css', array(), $login_css_ver );

			wp_enqueue_script(
				'ggm-signup-js',
				GGM_PLUGIN_URL . 'assets/js/ggm-signup.js',
				array( 'jquery' ),
				$signup_js_ver,
				true
			);

			wp_localize_script( 'ggm-signup-js', 'ggmSignupData', $this->signup_js_data() );
		}

		// ── Dashboard page ──────────────────────────────────────────────────────
		if ( $pages['is_dashboard'] ) {
			$this->enqueue_dashboard_assets();
		}

		// ── Workshop / Lesson / Checkout pages ──────────────────────────────────
		if ( $pages['is_workshop'] || $pages['is_lesson'] || $pages['is_checkout'] ) {
			wp_enqueue_style( 'ggm-public-css', GGM_PLUGIN_URL . 'assets/css/ggm-public.css', array(), $public_css_ver );
		}

		if ( class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() && ( $pages['is_workshop'] || $pages['is_checkout'] || $pages['is_dashboard'] || $is_woocommerce_page ) ) {
			wp_enqueue_script( 'ggm-currency-js', GGM_PLUGIN_URL . 'assets/js/ggm-currency.js', array(), $currency_js_ver, true );
			wp_localize_script( 'ggm-currency-js', 'ggmCurrency', GGM_Currency::frontend_config() );
		}

		// Final cascade guard: every GGM shortcode stays shadow-free even when
		// a theme, Elementor, or a cached legacy feature stylesheet adds one.
		wp_enqueue_style(
			'ggm-shortcode-shadowless-css',
			GGM_PLUGIN_URL . 'assets/css/ggm-shortcode-shadowless.css',
			array(),
			$shadowless_css_ver
		);

		// ── Workshop page — Razorpay (for a logged-in visitor's "Join Now") ─────
		// Workshop pages are typically page-cached (unlike login/dashboard/
		// checkout, which are exempted above), so nothing visitor-specific is
		// localized here — [ggm_workshop_join_now]'s own inline script fetches
		// a live nonce + contact details via ggm_get_checkout_nonce right when
		// clicked, exactly like the checkout page's guest flow already does.
		if ( $pages['is_workshop'] ) {
			wp_enqueue_script( 'razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', array(), null, true );
		}

		// ── Checkout page — Razorpay ────────────────────────────────────────────
		if ( $pages['is_checkout'] ) {
			wp_enqueue_script( 'razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', array(), null, true );
			wp_enqueue_script(
				'ggm-razorpay-js',
				GGM_PLUGIN_URL . 'assets/js/ggm-razorpay.js',
				array( 'jquery', 'razorpay-checkout' ),
				$razorpay_js_ver,
				true
			);

			// This script data is printed straight into the page's HTML, which
			// a page-cache plugin can serve identically to every visitor —
			// deliberately not including this visitor's own name/email/phone
			// or a nonce tied to their session here; ggm-razorpay.js fetches
			// both live via the never-cached ggm_get_checkout_nonce AJAX call
			// instead, right after the page loads.
			wp_localize_script( 'ggm-razorpay-js', 'ggmCheckout', array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'site_name'   => get_bloginfo( 'name' ),
				'currency'    => class_exists( 'GGM_Currency' ) ? GGM_Currency::selected_currency() : ggm_get_setting( 'ggm_currency', 'INR' ),
				'theme_color' => ggm_get_setting( 'ggm_razorpay_theme_color', '#7c3aed' ),
				'pay_btn'     => __( 'Pay Now', 'ggm-member-dashboard' ),
				'enroll_free' => __( 'Enroll for Free', 'ggm-member-dashboard' ),
				'creating'    => __( 'Creating order…', 'ggm-member-dashboard' ),
				'error'       => __( 'Something went wrong. Please try again.', 'ggm-member-dashboard' ),
				'applying'       => __( 'Applying…', 'ggm-member-dashboard' ),
				'apply_coupon'   => __( 'Apply Coupon', 'ggm-member-dashboard' ),
				'coupon_applied' => __( 'Coupon applied.', 'ggm-member-dashboard' ),
				'select_slot'    => __( 'Please choose a workshop time slot.', 'ggm-member-dashboard' ),
			) );
		}
	}

	/**
	 * Enqueue the dashboard runtime independently of page detection.
	 *
	 * The shortcode renderer also calls this method so Theme Builder, block
	 * template, widget, and direct do_shortcode() placements cannot omit the
	 * dashboard assets or localized AJAX configuration.
	 */
	public function enqueue_dashboard_assets() {
		// This method is also called directly by the shortcode renderer, so the
		// editor guard must live here as well as in the normal enqueue path.
		if ( self::is_elementor_editor_request() ) {
			return false;
		}

		$dashboard_css_path = GGM_PLUGIN_DIR . 'assets/css/ggm-dashboard.css';
		$dashboard_js_path  = GGM_PLUGIN_DIR . 'assets/js/ggm-dashboard.js';
		$dashboard_css_ver  = file_exists( $dashboard_css_path ) ? (string) filemtime( $dashboard_css_path ) : GGM_VERSION;
		$dashboard_js_ver   = file_exists( $dashboard_js_path ) ? (string) filemtime( $dashboard_js_path ) : GGM_VERSION;

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'ggm-dashboard-css', GGM_PLUGIN_URL . 'assets/css/ggm-dashboard.css', array( 'dashicons' ), $dashboard_css_ver );
		wp_enqueue_script( 'ggm-dashboard-js', GGM_PLUGIN_URL . 'assets/js/ggm-dashboard.js', array( 'jquery' ), $dashboard_js_ver, true );
		if ( function_exists( 'ggm_dashboard_user_can_use_admin_controls' ) && ggm_dashboard_user_can_use_admin_controls() ) {
			wp_enqueue_media();
			wp_enqueue_editor();
			$management_js_path = GGM_PLUGIN_DIR . 'assets/js/ggm-dashboard-management.js';
			wp_enqueue_script( 'ggm-dashboard-management-js', GGM_PLUGIN_URL . 'assets/js/ggm-dashboard-management.js', array( 'jquery', 'ggm-dashboard-js' ), file_exists( $management_js_path ) ? filemtime( $management_js_path ) : GGM_VERSION, true );
			wp_localize_script( 'ggm-dashboard-management-js', 'ggmDashboardManagement', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ggm_dashboard_management' ) ) );
			$payments_js = GGM_PLUGIN_DIR . 'assets/js/ggm-dashboard-payments.js';
			wp_enqueue_script( 'ggm-dashboard-payments-js', GGM_PLUGIN_URL . 'assets/js/ggm-dashboard-payments.js', array( 'jquery', 'ggm-dashboard-management-js' ), file_exists( $payments_js ) ? filemtime( $payments_js ) : GGM_VERSION, true );
			$workshop_editor_js = GGM_PLUGIN_DIR . 'assets/js/ggm-dashboard-workshop-editor.js';
			wp_enqueue_script( 'ggm-dashboard-workshop-editor-js', GGM_PLUGIN_URL . 'assets/js/ggm-dashboard-workshop-editor.js', array( 'jquery', 'ggm-dashboard-management-js', 'media-editor' ), file_exists( $workshop_editor_js ) ? filemtime( $workshop_editor_js ) : GGM_VERSION, true );
			$course_editor_js = GGM_PLUGIN_DIR . 'assets/js/ggm-dashboard-course-editor.js';
			wp_enqueue_script( 'ggm-dashboard-course-editor-js', GGM_PLUGIN_URL . 'assets/js/ggm-dashboard-course-editor.js', array( 'jquery', 'ggm-dashboard-management-js', 'media-editor' ), file_exists( $course_editor_js ) ? filemtime( $course_editor_js ) : GGM_VERSION, true );
			$diseases_js = GGM_PLUGIN_DIR . 'assets/js/ggm-dashboard-diseases.js';
			wp_enqueue_script( 'ggm-dashboard-diseases-js', GGM_PLUGIN_URL . 'assets/js/ggm-dashboard-diseases.js', array( 'jquery', 'ggm-dashboard-management-js', 'editor' ), file_exists( $diseases_js ) ? filemtime( $diseases_js ) : GGM_VERSION, true );
		}

		if ( ! self::$dashboard_assets_localized ) {
			wp_localize_script( 'ggm-dashboard-js', 'ggm_public', $this->shared_js_data() );
			self::$dashboard_assets_localized = true;
		}

		// Razorpay is intentionally loaded on the first paid purchase click by
		// ggm-dashboard.js. An unavailable payment CDN must never block the
		// dashboard shell, its AJAX request, or DOM readiness.
		return true;
	}

	/**
	 * Whether WordPress is currently rendering inside the Elementor editor.
	 *
	 * @return bool
	 */
	public static function is_elementor_editor_request() {
		$editor_active = (
			class_exists( '\Elementor\Plugin' )
			&& isset( \Elementor\Plugin::$instance->editor )
			&& \Elementor\Plugin::$instance->editor->is_edit_mode()
		);
		$action = isset( $_GET['action'] )
			? sanitize_key( wp_unslash( $_GET['action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: '';

		return $editor_active || 'elementor' === $action;
	}

	private function is_woocommerce_currency_page() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}

		return is_woocommerce()
			|| ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() );
	}

	private function login_js_data() {
		$login_page_id  = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
		$login_url      = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/workshop-login/' );
		$signup_page_id = (int) ggm_get_setting( 'ggm_signup_page_id', 0 );
		$signup_url     = $signup_page_id ? get_permalink( $signup_page_id ) : home_url( '/' );

		return array(
			'ajaxurl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'ggm_nonce' ),
			'send_otp'           => __( 'Send OTP', 'ggm-member-dashboard' ),
			'verify_otp'         => __( 'Verify OTP', 'ggm-member-dashboard' ),
			'sending'            => __( 'Sending…', 'ggm-member-dashboard' ),
			'verifying'          => __( 'Verifying…', 'ggm-member-dashboard' ),
			'otp_resent'         => __( 'OTP resent successfully.', 'ggm-member-dashboard' ),
			'error'              => __( 'Something went wrong. Please try again.', 'ggm-member-dashboard' ),
			'logout_url'         => wp_logout_url( $login_url ),
			'signup_url'         => $signup_url,
			'create_account'     => __( 'Create Account', 'ggm-member-dashboard' ),
		);
	}

	private function signup_js_data() {
		return array(
			'ajaxurl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'ggm_nonce' ),
			'create_account'     => __( 'Create Account', 'ggm-member-dashboard' ),
			'creating'           => __( 'Creating account…', 'ggm-member-dashboard' ),
			'fill_required'      => __( 'Please fill in all required fields.', 'ggm-member-dashboard' ),
			'password_mismatch'  => __( 'Passwords do not match.', 'ggm-member-dashboard' ),
			'error'              => __( 'Something went wrong. Please try again.', 'ggm-member-dashboard' ),
		);
	}

	private function shared_js_data() {
		$login_page_id    = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
		$login_url        = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/workshop-login/' );
		$checkout_page_id = (int) ggm_get_setting( 'ggm_checkout_page_id', 0 );
		$checkout_url     = $checkout_page_id ? get_permalink( $checkout_page_id ) : home_url( '/membership-checkout/' );
		$country_codes    = class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_calling_codes() : array();
		foreach ( $country_codes as &$country ) {
			$country['flag_svg'] = GGM_Form_Builder::country_flag_svg( $country['iso'] ?? '' );
		}
		unset( $country );

		return array(
			'ajaxurl'              => admin_url( 'admin-ajax.php' ),
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'nonce'                => wp_create_nonce( 'ggm_nonce' ),
			'logout_url'           => wp_logout_url( $login_url ),
			'checkout_url'         => $checkout_url ?: home_url( '/membership-checkout/' ),
			'currency_symbol'      => ggm_get_setting( 'ggm_currency_symbol', '₹' ),
			'currency'             => class_exists( 'GGM_Currency' ) ? GGM_Currency::selected_currency() : ggm_get_setting( 'ggm_currency', 'INR' ),
			'label_start_learning' => ggm_get_setting( 'ggm_btn_start_learning', __( 'Start Learning', 'ggm-member-dashboard' ) ),
			'label_videos'         => ggm_get_setting( 'ggm_label_videos', __( 'Videos', 'ggm-member-dashboard' ) ),
			'label_unlock'         => ggm_get_setting( 'ggm_btn_enroll_now', __( 'Enroll Now', 'ggm-member-dashboard' ) ),
			'label_watch_free'     => ggm_get_setting( 'ggm_btn_watch_free', __( 'Watch Free', 'ggm-member-dashboard' ) ),
			'label_purchase_now'      => __( 'Purchase Now', 'ggm-member-dashboard' ),
			'label_view_details'      => __( 'View workshop details', 'ggm-member-dashboard' ),
			'label_workshop_completed' => __( 'Workshop Completed', 'ggm-member-dashboard' ),
			'label_starts_in'         => __( 'Starts in', 'ggm-member-dashboard' ),
			'label_start_soon'        => __( 'Start Soon', 'ggm-member-dashboard' ),
			'error'                => __( 'Something went wrong. Please try again.', 'ggm-member-dashboard' ),
			// Quick Buy — Purchase Now opens Razorpay directly from the
			// dashboard for a logged-in member instead of visiting Checkout.
			'site_name'            => get_bloginfo( 'name' ),
			'theme_color'          => ggm_get_setting( 'ggm_razorpay_theme_color', '#0e9e6e' ),
			'label_processing'     => __( 'Processing…', 'ggm-member-dashboard' ),
			'label_verifying'      => __( 'Verifying…', 'ggm-member-dashboard' ),
			'label_payment_failed' => __( 'Payment failed. Please try again.', 'ggm-member-dashboard' ),
			'dashboard_load_error' => __( 'Failed to load dashboard data. Please try again.', 'ggm-member-dashboard' ),
			'dashboard_timeout_error' => __( 'The dashboard is taking too long to respond. Please try again.', 'ggm-member-dashboard' ),
			'dashboard_config_error' => __( 'Dashboard configuration is unavailable. Please refresh the page.', 'ggm-member-dashboard' ),
			'dashboard_retry'       => __( 'Try again', 'ggm-member-dashboard' ),
			'country_codes'          => $country_codes,
		);
	}

	/**
	 * Output CSS custom properties for color customization on login/dashboard pages.
	 */
	public function inject_custom_styles() {
		$pages = $this->detect_page();

		// Signup reuses the login page's CSS/markup, so it gets the same
		// branding color variables.
		$is_login     = $pages['is_login'] || $pages['is_signup'];
		$is_dashboard = $pages['is_dashboard'];

		if ( ! $is_login && ! $is_dashboard ) {
			return;
		}

		$login_vars = array();
		$dash_vars  = array();

		if ( $is_login ) {
			$map = array(
				'--ggm-login-page-bg'       => ggm_get_setting( 'ggm_login_page_bg', '' ),
				'--ggm-login-card-bg'       => ggm_get_setting( 'ggm_login_card_bg', '' ),
				'--ggm-login-primary'       => ggm_get_setting( 'ggm_login_primary', '' ),
				'--ggm-login-primary-hover' => ggm_get_setting( 'ggm_login_primary_hover', '' ),
				'--ggm-login-heading'       => ggm_get_setting( 'ggm_login_heading', '' ),
			);
			foreach ( $map as $prop => $val ) {
				$clean = sanitize_hex_color( $val );
				if ( $clean ) {
					$login_vars[] = $prop . ':' . $clean;
				}
			}
		}

		if ( $is_dashboard ) {
			$map = array(
				'--ggm-dash-page-bg'     => ggm_get_setting( 'ggm_dash_page_bg', '' ),
				'--ggm-dash-sidebar-bg'  => ggm_get_setting( 'ggm_dash_sidebar_bg', '' ),
				'--ggm-dash-primary'     => ggm_get_setting( 'ggm_dash_primary', '' ),
				'--ggm-dash-primary-end' => ggm_get_setting( 'ggm_dash_primary_end', '' ),
			);
			foreach ( $map as $prop => $val ) {
				$clean = sanitize_hex_color( $val );
				if ( $clean ) {
					$dash_vars[] = $prop . ':' . $clean;
				}
			}
		}

		if ( empty( $login_vars ) && empty( $dash_vars ) ) {
			return;
		}

		echo '<style id="ggm-custom-colors">';
		if ( ! empty( $login_vars ) ) {
			echo '.ggm-login-container{' . implode( ';', $login_vars ) . '}';
		}
		if ( ! empty( $dash_vars ) ) {
			echo '#ggm-dash{' . implode( ';', $dash_vars ) . '}';
		}
		echo '</style>' . "\n";
	}

	/**
	 * Redirect protection — runs before any output on template_redirect.
	 * Detects pages by ID (settings) AND by Elementor widget data (handles swapped/missing settings).
	 */
	public function handle_redirects() {
		if ( ! is_page() ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$login_page_id = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
		$dash_page_id  = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );

		// Detect current page role by checking Elementor data + post content.
		$elementor_raw = get_post_meta( $post->ID, '_elementor_data', true );
		$elementor_str = is_string( $elementor_raw ) ? $elementor_raw : '';
		$content       = $post->post_content ?? '';

		$current_is_login = ( $login_page_id && is_page( $login_page_id ) )
			|| has_shortcode( $content, 'ggm_login' )
			|| has_shortcode( $content, 'ggm_login_page' )
			|| ( $elementor_str && (
				strpos( $elementor_str, '[ggm_login]' ) !== false
				|| strpos( $elementor_str, '[ggm_login_page]' ) !== false
			) );

		$current_is_dash = ( $dash_page_id && is_page( $dash_page_id ) )
			|| has_shortcode( $content, 'ggm_dashboard' )
			|| ( $elementor_str && strpos( $elementor_str, '[ggm_dashboard]' ) !== false );

		// Resolve login URL.
		$login_url = $login_page_id
			? ( get_permalink( $login_page_id ) ?: home_url( '/workshop-login/' ) )
			: home_url( '/workshop-login/' );

		// Resolve dashboard URL.
		$dash_url = $dash_page_id
			? ( get_permalink( $dash_page_id ) ?: home_url( '/dashboard/' ) )
			: ggm_get_setting( 'ggm_redirect_after_login', home_url( '/dashboard/' ) );

		// Not logged in on dashboard → send to login.
		if ( $current_is_dash && ! is_user_logged_in() ) {
			wp_redirect( $login_url );
			exit;
		}

		// Already logged in on login page → send to dashboard.
		if ( $current_is_login && is_user_logged_in() ) {
			wp_redirect( $dash_url );
			exit;
		}
	}
}
