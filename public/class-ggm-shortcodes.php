<?php
/**
 * Public Shortcodes Controller.
 *
 * Registers and renders [ggm_login_page] and [ggm_dashboard].
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Shortcodes
 */
class GGM_Shortcodes {

	/**
	 * Register shortcodes.
	 */
	public function init() {
		add_shortcode( 'ggm_login_page',       array( $this, 'render_login_page_sc' ) );
		add_shortcode( 'ggm_login',            array( $this, 'render_login_page_sc' ) );
		add_shortcode( 'ggm_dashboard',        array( $this, 'render_dashboard_sc' ) );
		add_shortcode( 'ggm_checkout',         array( $this, 'render_checkout_sc' ) );

		// Course / Lesson shortcodes.
		add_shortcode( 'ggm_courses',          array( $this, 'sc_courses' ) );
		add_shortcode( 'ggm_course_purchase',  array( $this, 'sc_course_purchase' ) );
		add_shortcode( 'ggm_course_short_description', array( $this, 'sc_course_short_description' ) );
		add_shortcode( 'course_short_description', array( $this, 'sc_course_short_description' ) );
		add_shortcode( 'course_curriculum',    array( $this, 'sc_course_curriculum' ) );
		add_shortcode( 'lesson_navigation',    array( $this, 'sc_lesson_navigation' ) );
		add_shortcode( 'lesson_count',         array( $this, 'sc_lesson_count' ) );
		add_shortcode( 'back_to_course',       array( $this, 'sc_back_to_course' ) );
		add_shortcode( 'ggm_lesson_video',     array( $this, 'sc_lesson_video' ) );
		add_shortcode( 'ggm_lesson_sidebar',   array( $this, 'sc_lesson_sidebar' ) );

		// Workshop detail shortcodes — for building a single-workshop page in
		// Elementor (or any page builder) without relying on ACF.
		add_shortcode( 'ggm_workshop_date',        array( $this, 'sc_workshop_date' ) );
		add_shortcode( 'ggm_workshop_time',        array( $this, 'sc_workshop_time' ) );
		add_shortcode( 'ggm_workshop_price',       array( $this, 'sc_workshop_price' ) );
		add_shortcode( 'ggm_workshop_contribution', array( $this, 'sc_workshop_contribution' ) );
		add_shortcode( 'ggm_workshop_contribution_pills', array( $this, 'sc_workshop_contribution_pills' ) );
		add_shortcode( 'ggm_workshop_mode',        array( $this, 'sc_workshop_mode' ) );
		add_shortcode( 'ggm_workshop_duration',    array( $this, 'sc_workshop_duration' ) );
		add_shortcode( 'ggm_workshop_linked_course', array( $this, 'sc_workshop_linked_course' ) );
		add_shortcode( 'ggm_workshop_slots_select',array( $this, 'sc_workshop_slots_select' ) );
		add_shortcode( 'ggm_workshop_enroll',      array( $this, 'sc_workshop_enroll' ) );
		add_shortcode( 'ggm_workshop_discover',    array( $this, 'sc_workshop_discover' ) );
		add_shortcode( 'ggm_workshop_why_different', array( $this, 'sc_workshop_why_different' ) );
		add_shortcode( 'ggm_workshop_why_workshop_is_different', array( $this, 'sc_workshop_why_workshop_is_different' ) );
		add_shortcode( 'ggm_workshop_journey', array( $this, 'sc_workshop_journey' ) );
		add_shortcode( 'ggm_workshop_perfect_for', array( $this, 'sc_workshop_perfect_for' ) );
		add_shortcode( 'ggm_workshop_faq',         array( $this, 'sc_workshop_faq' ) );
		add_shortcode( 'ggm_workshop_icon_list',   array( $this, 'sc_workshop_icon_list' ) );
		add_shortcode( 'ggm_workshop_countdown',   array( $this, 'sc_workshop_countdown' ) );
		add_shortcode( 'ggm_workshop_heading',     array( $this, 'sc_workshop_heading' ) );
		add_shortcode( 'ggm_workshop_header_pill', array( $this, 'sc_workshop_header_pill' ) );
		add_shortcode( 'ggm_workshop_join_now',    array( $this, 'sc_workshop_join_now' ) );
		add_shortcode( 'ggm_workshop_join_form',   array( $this, 'sc_workshop_join_form' ) );
		add_shortcode( 'ggm_workshop_join_form_full', array( $this, 'sc_workshop_join_form_full' ) );
		add_shortcode( 'ggm_workshop_mentor',      array( $this, 'sc_workshop_mentor' ) );
		add_shortcode( 'ggm_workshop_description', array( $this, 'sc_workshop_description' ) );
		add_shortcode( 'ggm_workshop_start_date_detail', array( $this, 'sc_workshop_start_date_detail' ) );
		add_shortcode( 'ggm_workshop_preparatory_date_detail', array( $this, 'sc_workshop_preparatory_date_detail' ) );
		add_shortcode( 'ggm_workshop_duration_detail', array( $this, 'sc_workshop_duration_detail' ) );
		add_shortcode( 'ggm_workshop_time_slot_detail', array( $this, 'sc_workshop_time_slot_detail' ) );
		add_shortcode( 'ggm_workshop_language_detail', array( $this, 'sc_workshop_language_detail' ) );
		add_shortcode( 'ggm_workshop_contribution_detail', array( $this, 'sc_workshop_contribution_detail' ) );
		add_shortcode( 'ggm_workshop_booking_card', array( $this, 'sc_workshop_booking_card' ) );
		add_shortcode( 'ggm_workshop_booking_summary', array( $this, 'sc_workshop_booking_summary' ) );
		add_shortcode( 'ggm_workshop_booking_card_hero_header', array( $this, 'sc_workshop_booking_card_hero_header' ) );
		add_shortcode( 'ggm_workshop_form_text', array( $this, 'sc_workshop_form_text' ) );
	}

	/**
	 * Resolve the member dashboard URL for buttons rendered outside the normal
	 * login flow.
	 *
	 * @return string
	 */
	private function resolve_dashboard_url() {
		$redirect = trim( (string) ggm_get_setting( 'ggm_redirect_after_login', '' ) );
		if ( '' !== $redirect && '#profile-completion' !== $redirect ) {
			return 0 === strpos( $redirect, 'http' )
				? wp_validate_redirect( $redirect, home_url( '/dashboard/' ) )
				: home_url( '/' . ltrim( $redirect, '/' ) );
		}

		$dash_page_id = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );
		$dash_page    = $dash_page_id ? get_post( $dash_page_id ) : null;
		if ( $dash_page && 'publish' === $dash_page->post_status && has_shortcode( $dash_page->post_content, 'ggm_dashboard' ) ) {
			$permalink = get_permalink( $dash_page_id );
			if ( $permalink ) {
				return $permalink;
			}
		}

		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			's'              => '[ggm_dashboard]',
			'fields'         => 'ids',
		) );
		if ( ! empty( $pages[0] ) ) {
			$permalink = get_permalink( (int) $pages[0] );
			if ( $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/dashboard/' );
	}

	/**
	 * Shortcode: [ggm_login_page]
	 *
	 * Renders identifier, OTP verification, and first-time profile step views.
	 *
	 * @return string
	 */
	public function render_login_page_sc() {
		if ( is_user_logged_in() ) {
			// Honor a pending redirect_to (e.g. an already-logged-in visitor
			// bounced back to this page, or hitting it again via back button)
			// instead of always sending them to the generic dashboard.
			$redirect_to = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), '' ) : '';

			if ( $redirect_to ) {
				$redirect_url = $redirect_to;
			} else {
				$dash_page_id = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );
				$redirect_url = $dash_page_id
					? get_permalink( $dash_page_id )
					: ggm_get_setting( 'ggm_redirect_after_login', home_url( '/dashboard/' ) );
			}
			$redirect_url = esc_url( $redirect_url );
			// NEVER call wp_redirect()+exit inside a shortcode — it kills Elementor mid-render.
			// Use JS redirect + meta fallback instead.
			return '<script>window.location.replace("' . $redirect_url . '");</script>'
			       . '<noscript><meta http-equiv="refresh" content="0;url=' . $redirect_url . '"></noscript>'
			       . '<div class="ggm-login-redirect">'
			       . '<p>Redirecting to dashboard…</p>'
			       . '<a href="' . $redirect_url . '" class="ggm-login-redirect__link">Click here if not redirected</a>'
			       . '</div>';
		}

		ob_start();
		echo '<div id="ggm-login-wrap" class="ggm-login-container">';
		include GGM_PLUGIN_DIR . 'templates/auth/login.php';
		include GGM_PLUGIN_DIR . 'templates/auth/otp-verify.php';
		include GGM_PLUGIN_DIR . 'templates/auth/complete-profile.php';
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [ggm_dashboard]
	 *
	 * Renders the dashboard shell and sub-tabs.
	 *
	 * @return string
	 */
	public function render_dashboard_sc() {
		if ( GGM_Public::is_elementor_editor_request() ) {
			return '<div class="ggm-dashboard-editor-placeholder">' . esc_html__( 'The member dashboard is available on the published page.', 'ggm-member-dashboard' ) . '</div>';
		}

		if ( ! is_user_logged_in() ) {
			return '<p class="ggm-error-alert">' . esc_html__( 'Please log in to access the member dashboard.', 'ggm-member-dashboard' ) . '</p>';
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) { define( 'DONOTCACHEOBJECT', true ); }
		if ( ! defined( 'DONOTMINIFY' ) ) { define( 'DONOTMINIFY', true ); }
		nocache_headers();

		$dashboard_public = new GGM_Public();
		$dashboard_public->enqueue_dashboard_assets();
		$late_styles = '';
		if ( did_action( 'wp_head' ) && ! wp_style_is( 'ggm-dashboard-css', 'done' ) ) {
			ob_start();
			wp_print_styles( array( 'dashicons', 'ggm-dashboard-css' ) );
			$late_styles = ob_get_clean();
		}

		ob_start();
		echo $late_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated by WordPress' style printer.
		include GGM_PLUGIN_DIR . 'templates/dashboard/layout.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: [ggm_checkout workshop_id="1"] or [ggm_checkout course_id="1"]
	 *
	 * Membership checkout was removed along with the Membership system —
	 * every workshop/course is purchased directly.
	 */
	public function render_checkout_sc( $atts ) {
		$atts = shortcode_atts( array( 'workshop_id' => 0, 'course_id' => 0 ), $atts, 'ggm_checkout' );

		$workshop_id = absint( $atts['workshop_id'] ) ?: absint( $_GET['workshop_id'] ?? 0 );
		$course_id   = absint( $atts['course_id'] ) ?: absint( $_GET['course_id'] ?? 0 );

		$currency_symbol = ggm_get_setting( 'ggm_currency_symbol', '₹' );
		$workshop         = null;
		$course           = null;

		if ( $workshop_id && in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
			$workshop = get_post( $workshop_id );
		} elseif ( $course_id && 'course' === get_post_type( $course_id ) ) {
			$course = get_post( $course_id );
		}

		if ( ! $workshop && ! $course ) {
			return '<p class="ggm-error-alert">' . esc_html__( 'No workshop or course selected.', 'ggm-member-dashboard' ) . '</p>';
		}

		ob_start();
		include GGM_PLUGIN_DIR . 'templates/checkout/checkout.php';
		return ob_get_clean();
	}

	// ─── Course / Lesson Shortcodes ────────────────────────────────────────────

	/**
	 * [ggm_courses] — course card grid for pages/builders.
	 */
	public function sc_courses( $atts ) {
		$atts = shortcode_atts( array(
			'status'  => 'all',
			'limit'   => -1,
			'columns' => 3,
			'orderby' => 'menu_order',
			'order'   => 'ASC',
		), $atts, 'ggm_courses' );

		$status  = strtolower( sanitize_key( $atts['status'] ) );
		$status  = in_array( $status, array( 'all', 'enrolled', 'available' ), true ) ? $status : 'all';
		$limit   = (int) $atts['limit'];
		$columns = min( 4, max( 1, absint( $atts['columns'] ) ) );
		$order   = 'DESC' === strtoupper( $atts['order'] ) ? 'DESC' : 'ASC';
		$orderby = sanitize_key( $atts['orderby'] );

		$courses = get_posts( array(
			'post_type'      => 'course',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => $orderby ?: 'menu_order',
			'order'          => $order,
		) );

		if ( empty( $courses ) ) {
			return '<p class="ggm-no-data">' . esc_html__( 'No courses found.', 'ggm-member-dashboard' ) . '</p>';
		}

		$user_id         = get_current_user_id();
		$checkout_page   = (int) ggm_get_setting( 'ggm_checkout_page_id', 0 );
		$checkout_url    = $checkout_page ? get_permalink( $checkout_page ) : '';
		$checkout_url    = $checkout_url ?: home_url( '/membership-checkout/' );
		$currency_symbol = ggm_get_setting( 'ggm_currency_symbol', '₹' );
		$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$start_text      = ggm_get_setting( 'ggm_btn_start_learning', __( 'Start Learning', 'ggm-member-dashboard' ) );
		$enroll_text     = ggm_get_setting( 'ggm_btn_enroll_now', __( 'Enroll Now', 'ggm-member-dashboard' ) );

		ob_start();
		echo '<div class="ggm-courses-grid ggm-courses-grid--cols-' . esc_attr( $columns ) . '">';

		foreach ( $courses as $course ) {
			$has_access = $user_id ? ggm_user_has_course_access( $course->ID, $user_id ) : false;

			if ( 'enrolled' === $status && ! $has_access ) {
				continue;
			}

			if ( 'available' === $status && $has_access ) {
				continue;
			}

			$thumbnail    = get_the_post_thumbnail_url( $course->ID, 'medium_large' );
			$short_desc   = get_post_meta( $course->ID, 'course_short_description', true );
			$short_desc   = $short_desc ?: get_post_meta( $course->ID, 'course_short_desc', true );
			$short_desc   = $short_desc ?: wp_trim_words( get_the_excerpt( $course->ID ), 18 );
			$instructor   = implode( ', ', ggm_get_course_mentor_names( $course->ID ) );
			$raw_price    = get_post_meta( $course->ID, 'course_price', true );
			$price        = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::parse_price( $raw_price ) : (float) $raw_price;
			$price_display = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
				? GGM_Currency::format_converted( $price, $course->ID, $selected_currency )
				: $currency_symbol . number_format_i18n( $price, 0 );
			$lesson_count = class_exists( 'GGM_Lesson' ) ? GGM_Lesson::count_for_course( $course->ID ) : 0;
			$button_url   = $has_access || $price <= 0
				? get_permalink( $course->ID )
				: add_query_arg( array( 'course_id' => $course->ID, 'ggm_currency' => $selected_currency ), $checkout_url );
			$button_text  = $has_access || $price <= 0 ? $start_text : $enroll_text;

			echo '<article class="ggm-course-card' . ( $has_access ? ' is-enrolled' : ' is-available' ) . '">';
			if ( $thumbnail ) {
				echo '<a class="ggm-course-card__thumb" href="' . esc_url( $button_url ) . '"><img class="ggm-course-card__thumb-image" src="' . esc_url( $thumbnail ) . '" alt="" loading="lazy">';
				if ( ! $has_access && $price > 0 ) {
					echo '<span class="ggm-course-card__badge">' . esc_html__( 'Premium', 'ggm-member-dashboard' ) . '</span>';
				}
				echo '</a>';
			}
			echo '<div class="ggm-course-card__body">';
			echo '<h3 class="ggm-course-card__title"><a href="' . esc_url( $button_url ) . '">' . esc_html( get_the_title( $course ) ) . '</a></h3>';
			if ( $short_desc ) {
				echo '<p class="ggm-course-card__desc">' . esc_html( $short_desc ) . '</p>';
			}
			echo '<div class="ggm-course-card__meta">';
			echo '<span>' . esc_html( sprintf( _n( '%d Lesson', '%d Lessons', $lesson_count, 'ggm-member-dashboard' ), $lesson_count ) ) . '</span>';
			if ( $instructor ) {
				echo '<span>' . esc_html( $instructor ) . '</span>';
			}
			echo '</div>';
			if ( $price && ! $has_access ) {
				echo '<div class="ggm-course-card__price">' . esc_html( $price_display ) . '</div>';
			}
			echo '<a class="ggm-btn ggm-btn-primary ggm-btn-full" href="' . esc_url( $button_url ) . '">' . esc_html( $button_text ) . '</a>';
			echo '</div>';
			echo '</article>';
		}

		echo '</div>';
		$output = ob_get_clean();

		if ( false === strpos( $output, 'ggm-course-card' ) ) {
			return '<p class="ggm-no-data">' . esc_html__( 'No matching courses found.', 'ggm-member-dashboard' ) . '</p>';
		}

		return $output;
	}

	/**
	 * [ggm_course_short_description] — saved short description for a course.
	 */
	public function sc_course_short_description( $atts ) {
		$atts      = shortcode_atts( array( 'course_id' => 0 ), $atts, 'ggm_course_short_description' );
		$course_id = absint( $atts['course_id'] ) ?: get_the_ID();
		$description = get_post_meta( $course_id, 'course_short_description', true );
		$description = $description ?: get_post_meta( $course_id, 'course_short_desc', true );

		if ( empty( $description ) ) {
			$description = get_the_excerpt( $course_id );
		}

		return $description
			? '<div class="ggm-course-short-description">' . wp_kses_post( wpautop( $description ) ) . '</div>'
			: '';
	}

	/**
	 * [ggm_course_purchase] — state-aware course price and purchase button.
	 */
	public function sc_course_purchase( $atts ) {
		static $styles_printed = false;
		$styles = '';
		if ( ! $styles_printed ) {
			$styles_printed = true;
			$styles = '';
		}
		$atts = shortcode_atts( array(
			'id'            => 0,
			'button_text'   => __( 'Buy the Course', 'ggm-member-dashboard' ),
			'enrolled_text' => __( 'Enrolled', 'ggm-member-dashboard' ),
			'class'         => '',
		), $atts, 'ggm_course_purchase' );

		$course_id = absint( $atts['id'] ) ?: get_the_ID();
		if ( ! $course_id || 'course' !== get_post_type( $course_id ) ) {
			return '';
		}

		$user_id     = get_current_user_id();
		$has_access  = $user_id && ggm_user_has_course_access( $course_id, $user_id );
		$extra_class = sanitize_html_class( $atts['class'] );

		if ( $has_access ) {
			return $styles . '<div class="ggm-course-purchase is-enrolled' . ( $extra_class ? ' ' . esc_attr( $extra_class ) : '' ) . '">'
				. '<span class="ggm-course-purchase__enrolled">' . esc_html( $atts['enrolled_text'] ) . '</span>'
				. '</div>';
		}

		$raw_price    = get_post_meta( $course_id, 'course_price', true );
		$price        = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::parse_price( $raw_price ) : (float) $raw_price;
		$currency     = ggm_get_setting( 'ggm_currency_symbol', '₹' );
		$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$checkout_id  = (int) ggm_get_setting( 'ggm_checkout_page_id', 0 );
		$checkout_url = $checkout_id ? get_permalink( $checkout_id ) : '';
		$checkout_url = $checkout_url ?: home_url( '/membership-checkout/' );
		$purchase_url = add_query_arg( array( 'course_id' => $course_id, 'ggm_currency' => $selected_currency ), $checkout_url );
		$price_display = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::format_converted( $price, $course_id, $selected_currency )
			: $currency . number_format_i18n( $price, 0 );
		$price_html   = '' !== trim( (string) $raw_price )
			? '<span class="ggm-course-purchase__price">' . esc_html( $price_display ) . '</span>'
			: '';

		return $styles . '<div class="ggm-course-purchase' . ( $extra_class ? ' ' . esc_attr( $extra_class ) : '' ) . '">'
			. $price_html
			. '<a class="ggm-course-purchase__button" href="' . esc_url( $purchase_url ) . '">' . esc_html( $atts['button_text'] ) . '</a>'
			. '</div>';
	}

	/**
	 * [course_curriculum] — card grid of lessons for a course.
	 */
	public function sc_course_curriculum( $atts ) {
		static $styles_printed = false;

		$atts      = shortcode_atts( array( 'course_id' => 0 ), $atts, 'course_curriculum' );
		$course_id = absint( $atts['course_id'] ) ?: get_the_ID();

		$lessons = class_exists( 'GGM_Lesson' ) ? GGM_Lesson::get_for_course( $course_id ) : array();
		if ( empty( $lessons ) ) {
			return '<p class="ggm-no-data">' . esc_html__( 'No lessons found for this course.', 'ggm-member-dashboard' ) . '</p>';
		}

		$play_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="16" height="16"><polygon points="6 4 20 12 6 20 6 4" fill="currentColor" stroke="none"/></svg>';

		ob_start();

		if ( ! $styles_printed ) {
			$styles_printed = true;
			echo '';
		}

		echo '<div class="ggm-curriculum-grid">';
		foreach ( $lessons as $i => $lesson ) {
			$url = get_permalink( $lesson->ID );
			echo '<a href="' . esc_url( $url ) . '" class="ggm-curriculum-card" aria-label="' . esc_attr( sprintf( __( 'Watch %s', 'ggm-member-dashboard' ), $lesson->post_title ) ) . '">';
			echo '<span class="ggm-card-num">' . esc_html( $i + 1 ) . '</span>';
			echo '<div class="ggm-card-body">';
			echo '<div class="ggm-card-title">' . esc_html( $lesson->post_title ) . '</div>';
			echo '<div class="ggm-card-sub">' . esc_html__( 'Session Lesson', 'ggm-member-dashboard' ) . '</div>';
			echo '</div>';
			echo '<span class="ggm-card-play" aria-hidden="true">' . $play_svg . '</span>';
			echo '</a>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * [lesson_navigation] — prev / next lesson links.
	 */
	public function sc_lesson_navigation( $atts ) {
		$lesson_id = get_the_ID();
		if ( ! $lesson_id ) {
			return '';
		}

		$adj  = class_exists( 'GGM_Lesson' ) ? GGM_Lesson::get_adjacent( $lesson_id ) : array( 'prev' => null, 'next' => null );
		$prev = $adj['prev'];
		$next = $adj['next'];

		if ( ! $prev && ! $next ) {
			return '';
		}

		ob_start();
		echo '<nav class="ggm-lesson-nav">';
		if ( $prev ) {
			echo '<a href="' . esc_url( get_permalink( $prev->ID ) ) . '">&larr; ' . esc_html( $prev->post_title ) . '</a>';
		} else {
			echo '<span></span>';
		}
		if ( $next ) {
			echo '<a href="' . esc_url( get_permalink( $next->ID ) ) . '">' . esc_html( $next->post_title ) . ' &rarr;</a>';
		}
		echo '</nav>';
		return ob_get_clean();
	}

	/**
	 * [lesson_count] — total lesson count for a course.
	 */
	public function sc_lesson_count( $atts ) {
		$atts      = shortcode_atts( array( 'course_id' => 0 ), $atts, 'lesson_count' );
		$course_id = absint( $atts['course_id'] ) ?: get_the_ID();
		$count     = class_exists( 'GGM_Lesson' ) ? GGM_Lesson::count_for_course( $course_id ) : 0;
		return '<span class="ggm-lesson-count">' . esc_html(
			sprintf( _n( '%d Lesson', '%d Lessons', $count, 'ggm-member-dashboard' ), $count )
		) . '</span>';
	}

	/**
	 * [back_to_course] — link back to parent course.
	 */
	public function sc_back_to_course( $atts ) {
		$lesson_id = get_the_ID();
		$course_id = get_post_meta( $lesson_id, 'course', true );
		if ( ! $course_id ) {
			return '';
		}
		return '<a href="' . esc_url( get_permalink( $course_id ) ) . '" class="ggm-back-to-course">&larr; ' . esc_html( get_the_title( $course_id ) ) . '</a>';
	}

	/**
	 * [ggm_lesson_video] — membership-gated video embed.
	 */
	public function sc_lesson_video( $atts ) {
		$atts = shortcode_atts( array(
			'lesson_id'   => 0,
			'level_id'    => 0,
		), $atts, 'ggm_lesson_video' );

		$lesson_id = absint( $atts['lesson_id'] ) ?: get_the_ID();
		$user_id   = get_current_user_id();

		$can_access = class_exists( 'GGM_Lesson' )
			? GGM_Lesson::user_can_access( $user_id, $lesson_id )
			: ( is_user_logged_in() && user_can( $user_id, 'administrator' ) );

		if ( ! $can_access ) {
			$checkout_url = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );
			$btn_text     = ggm_get_setting( 'ggm_btn_enroll_now', __( 'Unlock Access', 'ggm-member-dashboard' ) );
			return '<div class="ggm-video-locked">
				<div class="lock-icon">&#128274;</div>
				<h3>' . esc_html__( 'Premium Content', 'ggm-member-dashboard' ) . '</h3>
				<p>' . esc_html__( 'Get a membership to unlock this video.', 'ggm-member-dashboard' ) . '</p>
				<a href="' . esc_url( $checkout_url ) . '" class="ggm-btn ggm-btn-primary">' . esc_html( $btn_text ) . '</a>
			</div>';
		}

		$video_embed = class_exists( 'GGM_Lesson' )
			? GGM_Lesson::get_meta( $lesson_id, 'video_embed', '' )
			: get_post_meta( $lesson_id, 'video_embed', true );

		if ( empty( $video_embed ) ) {
			return '<p class="ggm-no-data">' . esc_html__( 'No video available for this lesson.', 'ggm-member-dashboard' ) . '</p>';
		}

		$player = $this->render_lesson_video( $video_embed, $lesson_id );

		if ( empty( $player ) ) {
			return '<p class="ggm-no-data">' . esc_html__( 'This video could not be loaded.', 'ggm-member-dashboard' ) . '</p>';
		}

		return '<div class="ggm-video-container">' . $player . '</div>';
	}

	/**
	 * Build stable player markup for a lesson video.
	 *
	 * WordPress may add lazy loading to an oEmbed iframe. For a primary lesson
	 * player that means the iframe can remain unpainted until the first pointer
	 * interaction, exposing the iframe/provider background inside the rounded
	 * wrapper. Marking the player eager makes its poster and controls available
	 * before hover and avoids that first-paint race.
	 *
	 * @param string $video_embed Stored video URL or embed markup.
	 * @param int    $lesson_id   Lesson post ID.
	 * @return string
	 */
	private function render_lesson_video( $video_embed, $lesson_id ) {
		$video_embed = trim( (string) $video_embed );

		if ( preg_match( '/<\s*(?:iframe|video)\b/i', $video_embed ) ) {
			$player = $video_embed;
		} elseif ( preg_match( '/\.(?:mp4|m4v|webm|ogv)(?:[?#].*)?$/i', $video_embed ) ) {
			$poster = get_the_post_thumbnail_url( $lesson_id, 'large' );
			$player = wp_video_shortcode( array(
				'src'      => esc_url_raw( $video_embed ),
				'poster'   => $poster ? esc_url_raw( $poster ) : '',
				'preload'  => 'metadata',
				'width'    => 1280,
				'height'   => 720,
			) );
		} else {
			$player = wp_oembed_get( esc_url_raw( $video_embed ), array(
				'width'  => 1280,
				'height' => 720,
			) );
		}

		if ( empty( $player ) ) {
			return '';
		}

		$title = sprintf(
			/* translators: %s: lesson title. */
			__( '%s video', 'ggm-member-dashboard' ),
			get_the_title( $lesson_id )
		);

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $player );
			if ( $processor->next_tag( 'iframe' ) ) {
				$processor->set_attribute( 'loading', 'eager' );
				$processor->set_attribute( 'title', $title );
				$processor->set_attribute( 'allowfullscreen', '' );
				$player = $processor->get_updated_html();
			}
		} elseif ( false !== stripos( $player, '<iframe' ) ) {
			// Backward compatibility for WordPress versions before 6.2.
			$player = preg_replace( '/\sloading=(?:"[^"]*"|\'[^\']*\')/i', '', $player, 1 );
			$player = preg_replace( '/<iframe\b/i', '<iframe loading="eager" title="' . esc_attr( $title ) . '"', $player, 1 );
		}

		return $player;
	}

	/**
	 * [ggm_lesson_sidebar] — sidebar listing all course lessons.
	 */
	public function sc_lesson_sidebar( $atts ) {
		$atts = shortcode_atts( array(
			'course_id'  => 0,
			'lesson_id'  => 0,
			'show_back'  => 'yes',
			'class'      => '',
		), $atts, 'ggm_lesson_sidebar' );

		$current_post_id = get_the_ID();
		$lesson_id       = absint( $atts['lesson_id'] );
		if ( ! $lesson_id && $current_post_id && in_array( get_post_type( $current_post_id ), array( 'lesson', 'ggm_lesson' ), true ) ) {
			$lesson_id = $current_post_id;
		}
		$course_id = absint( $atts['course_id'] );

		if ( ! $course_id && $lesson_id ) {
			$course_id = (int) get_post_meta( $lesson_id, 'course', true );
		}
		if ( ! $course_id && $current_post_id && in_array( get_post_type( $current_post_id ), array( 'course', 'workshop', 'ggm_workshop' ), true ) ) {
			$course_id = $current_post_id;
		}

		if ( ! $course_id ) {
			return '';
		}

		$lessons = class_exists( 'GGM_Lesson' ) ? GGM_Lesson::get_for_course( $course_id ) : array();
		if ( empty( $lessons ) ) {
			return '';
		}

		$course_title = get_the_title( $course_id );

		ob_start();
		echo '<aside class="ggm-lesson-tabs-panel ' . esc_attr( $atts['class'] ) . '">';
		if ( 'no' !== strtolower( (string) $atts['show_back'] ) ) {
			echo '<a class="ggm-lesson-back-card" href="' . esc_url( get_permalink( $course_id ) ) . '">&larr; ';
			echo esc_html( sprintf( __( 'Back To %s', 'ggm-member-dashboard' ), $course_title ) );
			echo '</a>';
		}
		echo '<nav class="ggm-lesson-tab-list" aria-label="' . esc_attr__( 'Course lessons', 'ggm-member-dashboard' ) . '">';
		foreach ( $lessons as $i => $l ) {
			$current = (int) $l->ID === (int) $lesson_id ? ' is-active' : '';
			echo '<a class="ggm-lesson-tab' . esc_attr( $current ) . '" href="' . esc_url( get_permalink( $l->ID ) ) . '">';
			echo '<span>' . esc_html( $l->post_title ?: sprintf( __( 'Lesson %d', 'ggm-member-dashboard' ), $i + 1 ) ) . '</span>';
			echo '</a>';
		}
		echo '</nav>';
		echo '</aside>';
		return ob_get_clean();
	}

	// ─── Workshop Detail Shortcodes (Elementor-friendly, no ACF required) ─────

	/**
	 * Resolve the workshop ID from a shortcode's "id" attribute, falling back
	 * to the current post — so these work unattended inside an Elementor
	 * Single/Loop template for the workshop CPT.
	 *
	 * @param array $atts
	 * @return int
	 */
	private function resolve_workshop_id( array $atts ) {
		return absint( $atts['id'] ?? 0 ) ?: (int) get_the_ID();
	}

	/**
	 * Whether an Additional Block shortcode should print its own section heading.
	 * This allows Elementor layouts with a separate edited Heading widget to
	 * suppress the shortcode heading and avoid duplicate headings.
	 *
	 * @param mixed $value Shortcode attribute value.
	 * @return bool
	 */
	private function show_workshop_block_heading( $value ) {
		return ! in_array( strtolower( trim( (string) $value ) ), array( '0', 'false', 'no', 'off' ), true );
	}

	/**
	 * Apply the saved per-workshop grid capacity to one Additional Block.
	 * A zero value is the deliberate "Default" sentinel and leaves legacy
	 * layouts and their complete repeater lists unchanged.
	 *
	 * @param array  $rows             Filtered repeater rows.
	 * @param int    $workshop_id      Workshop ID.
	 * @param string $section          discover|why_different|perfect_for.
	 * @param int    $default_columns  Current desktop column count.
	 * @return array{rows:array,class:string,style:string}
	 */
	private function workshop_block_grid_layout( array $rows, $workshop_id, $section, $default_columns ) {
		$layouts = get_post_meta( $workshop_id, 'ggm_workshop_additional_block_layouts', true );
		$layout  = is_array( $layouts ) && isset( $layouts[ $section ] ) && is_array( $layouts[ $section ] ) ? $layouts[ $section ] : array();
		$columns = min( 8, absint( $layout['columns'] ?? 0 ) );
		$row_cap = min( 10, absint( $layout['rows'] ?? 0 ) );

		if ( $row_cap ) {
			$rows = array_slice( $rows, 0, $row_cap * ( $columns ?: $default_columns ) );
		}

		return array(
			'rows'  => $rows,
			'class' => $columns ? ' ggm-ws-layout--custom' : '',
			'style' => $columns ? ' style="--ggm-ws-columns:' . esc_attr( $columns ) . ';"' : '',
		);
	}

	private function workshop_detail_svg( $field ) {
		$icons = array(
			'start_date'   => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><rect x="6" y="10" width="36" height="32" rx="4"/><path d="M15 5v10M33 5v10M6 20h36M14 28h5M24 28h5M34 28h1M14 35h5M24 35h5"/></svg>',
			'duration'     => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path d="M13 5h22M13 43h22M16 5c0 9 3 12 8 15-5 3-8 7-8 15v8M32 5c0 9-3 12-8 15 5 3 8 7 8 15v8M18 35h12"/></svg>',
			'time_slot'    => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="19"/><path d="M24 13v12l8 5"/></svg>',
			'language'     => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="19"/><path d="M5 24h38M24 5c6 5 9 11 9 19s-3 14-9 19c-6-5-9-11-9-19s3-14 9-19zM8 15h32M8 33h32"/></svg>',
			'contribution' => '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path d="M24 41S7 31 7 18c0-6 4-10 10-10 4 0 6 2 7 5 1-3 4-5 7-5 6 0 10 4 10 10 0 13-17 23-17 23z"/><path d="M24 17v15M29 20c-1-2-3-3-5-3-3 0-5 2-5 4 0 6 10 2 10 7 0 3-2 4-5 4-2 0-5-1-6-3"/></svg>',
		);
		return $icons[ $field ] ?? '';
	}

	private function render_workshop_detail( $field, $label, $value, $class = '', $value_is_html = false ) {
		if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) { return ''; }
		$classes = array_filter( array( 'ggm-workshop-detail', 'ggm-workshop-detail--' . sanitize_html_class( $field ), sanitize_html_class( $class ) ) );
		return '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><span class="ggm-workshop-detail__icon">' . $this->workshop_detail_svg( $field ) . '</span><span class="ggm-workshop-detail__label">' . esc_html( $label ) . '</span><span class="ggm-workshop-detail__value">' . ( $value_is_html ? wp_kses_post( $value ) : nl2br( esc_html( $value ) ) ) . '</span></div>';
	}

	public function sc_workshop_start_date_detail( $atts ) {
		$atts = shortcode_atts( array( 'id'=>0, 'label'=>__( 'Start Date', 'ggm-member-dashboard' ), 'class'=>'', 'format'=>'' ), $atts, 'ggm_workshop_start_date_detail' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$date = get_post_meta( $workshop_id, 'workshop_start_date', true ) ?: get_post_meta( $workshop_id, 'workshop_date', true );
		$value = $date && strtotime( $date ) ? date_i18n( $atts['format'] ?: get_option( 'date_format' ), strtotime( $date ) ) : '';
		return $this->render_workshop_detail( 'start_date', $atts['label'], $value, $atts['class'] );
	}

	public function sc_workshop_preparatory_date_detail( $atts ) {
		$atts = shortcode_atts( array( 'id'=>0, 'label'=>__( 'Preparatory Date', 'ggm-member-dashboard' ), 'class'=>'', 'format'=>'' ), $atts, 'ggm_workshop_preparatory_date_detail' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$date = get_post_meta( $workshop_id, 'workshop_preparatory_date', true );
		if ( ! $date ) { return ''; }
		$value = wp_date( $atts['format'] ?: get_option( 'date_format' ), strtotime( $date ) );
		return $this->render_workshop_detail( 'start_date', $atts['label'], $value, $atts['class'] );
	}

	public function sc_workshop_duration_detail( $atts ) {
		$atts = shortcode_atts( array( 'id'=>0, 'label'=>__( 'Duration', 'ggm-member-dashboard' ), 'class'=>'' ), $atts, 'ggm_workshop_duration_detail' );
		return $this->render_workshop_detail( 'duration', $atts['label'], get_post_meta( $this->resolve_workshop_id( $atts ), 'duration', true ), $atts['class'] );
	}

	public function sc_workshop_time_slot_detail( $atts ) {
		$atts = shortcode_atts( array( 'id'=>0, 'label'=>__( 'Time Slot', 'ggm-member-dashboard' ), 'class'=>'' ), $atts, 'ggm_workshop_time_slot_detail' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$slots = class_exists( 'GGM_Workshop_Slot' ) ? GGM_Workshop_Slot::get_for_workshop( $workshop_id, 'active' ) : array();
		$values = array();
		foreach ( $slots as $slot ) {
			$range = $this->compact_workshop_slot_range( $slot );
			if ( $range ) { $values[] = '<span class="ggm-workshop-detail__slot">' . esc_html( $range ) . '</span>'; }
		}
		$value = $values ? '<span class="ggm-workshop-detail__slots">' . implode( '', $values ) . '</span>' : '';
		return $this->render_workshop_detail( 'time_slot', $atts['label'], $value, $atts['class'], true );
	}

	private function compact_workshop_slot_range( $slot ) {
		if ( ! $slot || empty( $slot->start_time ) ) { return ''; }
		$timezone = wp_timezone();
		$start = DateTime::createFromFormat( '!H:i:s', (string) $slot->start_time, $timezone );
		$end = ! empty( $slot->end_time ) ? DateTime::createFromFormat( '!H:i:s', (string) $slot->end_time, $timezone ) : false;
		if ( ! $start ) { return ''; }
		$format = static function( $date, $include_period = true ) {
			$value = wp_date( $include_period ? 'g:i a' : 'g:i', $date->getTimestamp(), $date->getTimezone() );
			return preg_replace( '/:00(?=\s|$)/', '', $value );
		};
		if ( ! $end ) { return $format( $start ); }
		$same_period = $start->format( 'a' ) === $end->format( 'a' );
		return $format( $start, ! $same_period ) . '–' . $format( $end );
	}

	public function sc_workshop_language_detail( $atts ) {
		$atts = shortcode_atts( array( 'id'=>0, 'label'=>__( 'Language', 'ggm-member-dashboard' ), 'class'=>'' ), $atts, 'ggm_workshop_language_detail' );
		$language = sanitize_text_field( get_post_meta( $this->resolve_workshop_id( $atts ), 'workshop_language', true ) ) ?: 'english';
		$value = ucwords( str_replace( array( '-', '_' ), ' ', $language ) );
		return $this->render_workshop_detail( 'language', $atts['label'], $value, $atts['class'] );
	}

	public function sc_workshop_contribution_detail( $atts ) {
		$atts = shortcode_atts( array( 'id'=>0, 'label'=>__( 'Contribution', 'ggm-member-dashboard' ), 'class'=>'' ), $atts, 'ggm_workshop_contribution_detail' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$value = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::price_html( $workshop_id ) : esc_html( get_post_meta( $workshop_id, 'workshop_money', true ) );
		return $this->render_workshop_detail( 'contribution', $atts['label'], $value, $atts['class'], true );
	}

	/**
	 * [ggm_workshop_date id="" format="" before="" after=""]
	 */
	public function sc_workshop_date( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'format' => '', 'before' => '', 'after' => '' ), $atts, 'ggm_workshop_date' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$date        = get_post_meta( $workshop_id, 'workshop_date', true );
		if ( ! $date ) {
			return '';
		}
		$format = $atts['format'] ?: get_option( 'date_format' );
		return '<span class="ggm-workshop-date">'
			. esc_html( $atts['before'] . date_i18n( $format, strtotime( $date ) ) . $atts['after'] )
			. '</span>';
	}

	/**
	 * [ggm_workshop_time id="" before="" after=""]
	 *
	 * Shows the single active slot's time range, or a fallback label when the
	 * workshop has multiple slots (use [ggm_workshop_slots_select] to let the
	 * visitor actually choose one).
	 */
	public function sc_workshop_time( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'before' => '', 'after' => '' ), $atts, 'ggm_workshop_time' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if ( ! class_exists( 'GGM_Workshop_Slot' ) ) {
			return '';
		}

		$slots = GGM_Workshop_Slot::get_for_workshop( $workshop_id, 'active' );
		if ( empty( $slots ) ) {
			return '';
		}

		$label = 1 === count( $slots )
			? GGM_Workshop_Slot::format_range( $slots[0] )
			: __( 'Multiple time slots available', 'ggm-member-dashboard' );

		return '<span class="ggm-workshop-time">'
			. esc_html( $atts['before'] . $label . $atts['after'] )
			. '</span>';
	}

	/**
	 * [ggm_workshop_price id=""]
	 * Outputs formatted price with currency symbol, or a "Free" badge.
	 */
	public function sc_workshop_price( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0 ), $atts, 'ggm_workshop_price' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if ( ! class_exists( 'GGM_Workshop' ) ) {
			return '<span class="ggm-workshop-price">'
				. esc_html( get_post_meta( $workshop_id, 'workshop_money', true ) )
				. '</span>';
		}

		// Regular Price struck through next to the Sale Price when one is
		// set (Task 4), in the workshop's own selected currency (Task 3).
		return GGM_Workshop::price_html( $workshop_id );
	}

	/**
	 * [ggm_workshop_contribution id="" button_text="Contribute" class=""]
	 *
	 * Displays the server-configured contribution choices and sends the
	 * selected stable option ID to checkout. Amounts in this markup are
	 * informational only; checkout and order creation resolve them again.
	 */
	public function sc_workshop_contribution( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'          => 0,
				'button_text' => __( 'Contribute', 'ggm-member-dashboard' ),
				'class'       => '',
			),
			$atts,
			'ggm_workshop_contribution'
		);
		$workshop_id = $this->resolve_workshop_id( $atts );
		$post_type   = get_post_type( $workshop_id );
		if ( ! $workshop_id || ! in_array( $post_type, array( 'workshop', 'ggm_workshop' ), true ) || ! class_exists( 'GGM_Workshop' ) || ! GGM_Workshop::is_contribution( $workshop_id ) ) {
			return '';
		}

		$options = GGM_Workshop::get_contribution_options( $workshop_id );
		if ( ! $options ) {
			return '';
		}

		if ( is_user_logged_in() && GGM_Workshop::user_has_access( get_current_user_id(), $workshop_id ) ) {
			$access_level = GGM_Workshop::get_user_access_level( get_current_user_id(), $workshop_id );
			if ( 'free' !== $access_level ) {
				return '<p class="ggm-contribution-access">' . esc_html__( 'You already have access to this workshop.', 'ggm-member-dashboard' ) . '</p>';
			}
		}

		// Use the same white pill CTA treatment as [ggm_workshop_join_now].
		$this->print_join_now_styles();

		$default           = GGM_Workshop::get_default_contribution( $workshop_id );
		$currency          = GGM_Workshop::get_currency( $workshop_id );
		$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$checkout_page_id  = (int) ggm_get_setting( 'ggm_checkout_page_id', 0 );
		$checkout_page_url = $checkout_page_id ? get_permalink( $checkout_page_id ) : '';
		$checkout_page_url = $checkout_page_url ?: home_url( '/membership-checkout/' );
		$base_checkout_url = add_query_arg( array( 'workshop_id' => $workshop_id, 'ggm_currency' => $selected_currency ), $checkout_page_url );
		$uid               = wp_unique_id( 'ggm-contribution-' );
		$extra_classes     = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) );
		$classes           = trim( 'ggm-workshop-contribution ' . implode( ' ', $extra_classes ) );
		$button_text       = sanitize_text_field( $atts['button_text'] ) ?: __( 'Contribute', 'ggm-member-dashboard' );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( $classes ); ?>">
			<fieldset class="ggm-contribution-options">
				<legend><?php esc_html_e( 'Choose your contribution', 'ggm-member-dashboard' ); ?></legend>
				<?php foreach ( $options as $option ) :
					$is_free = 'free' === $option['type'];
					$display_amount = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
						? GGM_Currency::format_converted( (float) $option['amount'], $workshop_id, $selected_currency )
						: $currency . number_format( (float) $option['amount'], 2 );
					$label   = $option['label'] ?: ( $is_free ? __( 'Free', 'ggm-member-dashboard' ) : $display_amount );
					$url     = add_query_arg( 'contribution_option_id', $option['id'], $base_checkout_url );
					?>
					<label class="ggm-contribution-option<?php echo $is_free ? ' is-free' : ''; ?>">
						<input type="radio" name="<?php echo esc_attr( $uid ); ?>-option" value="<?php echo esc_attr( $option['id'] ); ?>" data-checkout-url="<?php echo esc_url( $url ); ?>" <?php checked( $default && $default['id'] === $option['id'] ); ?>>
						<span class="ggm-contribution-option__label"><?php echo esc_html( $label ); ?></span>
						<?php if ( ! $is_free && $option['label'] ) : ?>
							<span class="ggm-contribution-option__amount"><?php echo esc_html( $display_amount ); ?></span>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<a class="ggm-contribution-continue ggm-ws-join-now" href="<?php echo esc_url( $base_checkout_url ); ?>"><?php echo esc_html( $button_text ); ?></a>
		</div>
		<script>
		(function(){
			var wrap=document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if(!wrap)return;
			var button=wrap.querySelector('.ggm-contribution-continue');
			function update(){
				var selected=wrap.querySelector('input[type="radio"]:checked');
				if(selected)button.href=selected.getAttribute('data-checkout-url');
			}
			wrap.addEventListener('change',update);
			update();
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * [ggm_workshop_contribution_pills id="" class=""]
	 *
	 * Compact contribution-price pills. Each option links directly to the
	 * existing checkout with its stable server-side option ID.
	 */
	public function sc_workshop_contribution_pills( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			'ggm_workshop_contribution_pills'
		);
		$workshop_id = $this->resolve_workshop_id( $atts );
		if (
			! $workshop_id
			|| ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true )
			|| ! class_exists( 'GGM_Workshop' )
			|| ! GGM_Workshop::is_contribution( $workshop_id )
		) {
			return '';
		}

		$options = GGM_Workshop::get_contribution_options( $workshop_id );
		if ( ! $options ) {
			return '';
		}

		if ( is_user_logged_in() && GGM_Workshop::user_has_access( get_current_user_id(), $workshop_id ) ) {
			$access_level = GGM_Workshop::get_user_access_level( get_current_user_id(), $workshop_id );
			if ( 'free' !== $access_level ) {
				return '';
			}
		}

		$default           = GGM_Workshop::get_default_contribution( $workshop_id );
		$currency          = GGM_Workshop::get_currency( $workshop_id );
		$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$checkout_page_url = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );
		$checkout_page_url = add_query_arg( array( 'workshop_id' => $workshop_id, 'ggm_currency' => $selected_currency ), $checkout_page_url );
		$extra_classes     = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) );
		$classes           = trim( 'ggm-contribution-pills ' . implode( ' ', $extra_classes ) );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" role="list" aria-label="<?php esc_attr_e( 'Contribution options', 'ggm-member-dashboard' ); ?>">
			<?php foreach ( $options as $option ) :
				$is_free    = 'free' === $option['type'];
				$is_default = $default && $default['id'] === $option['id'];
				$display_amount = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
					? GGM_Currency::format_converted( (float) $option['amount'], $workshop_id, $selected_currency )
					: $currency . number_format( (float) $option['amount'], 0, '.', ',' );
				$label      = $option['label'] ?: ( $is_free ? __( 'Free', 'ggm-member-dashboard' ) : $display_amount );
				$url        = add_query_arg( 'contribution_option_id', $option['id'], $checkout_page_url );
				?>
				<a role="listitem" class="ggm-contribution-pill<?php echo $is_default ? ' is-selected' : ''; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $is_default ? ' aria-current="true"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [ggm_workshop_mode id="" before="" after=""]
	 */
	public function sc_workshop_mode( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'before' => '', 'after' => '' ), $atts, 'ggm_workshop_mode' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$mode        = get_post_meta( $workshop_id, 'workshop_mode', true );
		return $mode
			? '<span class="ggm-workshop-mode">' . esc_html( $atts['before'] . $mode . $atts['after'] ) . '</span>'
			: '';
	}

	/**
	 * [ggm_workshop_description id="" class=""]
	 *
	 * Renders the workshop's "Short Description" field (Workshop Details
	 * meta box) — the same source used for the dashboard card blurb. Basic
	 * inline formatting like <strong>bold</strong> or <em>italic</em>,
	 * typed directly into that field, is preserved and rendered (not shown
	 * as literal tags) — anything outside that small safe set is stripped.
	 */
	public function sc_workshop_description( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'class' => '' ), $atts, 'ggm_workshop_description' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$description = get_post_meta( $workshop_id, 'workshop_short_desc', true );

		if ( '' === trim( wp_strip_all_tags( $description ) ) ) {
			return '';
		}

		$class = 'ggm-workshop-description' . ( $atts['class'] ? ' ' . sanitize_html_class( $atts['class'] ) : '' );
		$allowed_tags = class_exists( 'GGM_Meta_Boxes' ) ? GGM_Meta_Boxes::SHORT_DESC_ALLOWED_TAGS : array( 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'br' => array() );

		return '<p class="' . esc_attr( $class ) . '">' . wp_kses( $description, $allowed_tags ) . '</p>';
	}

	/**
	 * [ggm_workshop_duration id=""]
	 *
	 * Displays the Workshop Details Duration field exactly as saved. No
	 * prefix, suffix, label, or icon is added by the shortcode.
	 */
	public function sc_workshop_duration( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0 ), $atts, 'ggm_workshop_duration' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$duration    = get_post_meta( $workshop_id, 'duration', true );

		if ( ! $duration ) {
			return '';
		}

		$style = "font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:bold;line-height:1.1em;color:#000000;";

		return '<span class="ggm-workshop-duration">'
			. esc_html( $duration )
			. '</span>';
	}

	/**
	 * [ggm_workshop_slots_select id="" html_id=""]
	 * Renders a <select> of the workshop's active time slots. Pair it with
	 * [ggm_workshop_enroll], which reads the same html_id to submit the
	 * chosen slot — only needed when a workshop has more than one slot.
	 */
	public function sc_workshop_slots_select( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'html_id' => 'ggm-slot-select' ), $atts, 'ggm_workshop_slots_select' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if ( ! class_exists( 'GGM_Workshop_Slot' ) ) {
			return '';
		}

		$slots = GGM_Workshop_Slot::get_for_workshop( $workshop_id, 'active' );
		if ( count( $slots ) < 2 ) {
			// Nothing to choose — no dropdown needed.
			return '';
		}

		ob_start();
		?>
		<select id="<?php echo esc_attr( $atts['html_id'] ); ?>" class="ggm-workshop-slot-select">
			<option value=""><?php esc_html_e( 'Choose Your Workshop Time', 'ggm-member-dashboard' ); ?></option>
			<?php foreach ( $slots as $slot ) : ?>
				<option value="<?php echo esc_attr( $slot->id ); ?>"><?php echo esc_html( GGM_Workshop_Slot::format_range( $slot ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		return ob_get_clean();
	}

	/**
	 * [ggm_workshop_enroll id="" class="" html_id=""]
	 *
	 * A complete, state-aware call-to-action: "Start Watching" when the user
	 * already has access, "Register Now" (+ AJAX free registration, with a
	 * slot picker if needed) for free workshops, or "Enroll Now" linking to
	 * this plugin's own checkout for paid workshops. Drop this in an
	 * Elementor Shortcode widget instead of a static Button widget, since the
	 * label/link genuinely depend on server-side access state.
	 */
	public function sc_workshop_enroll( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'class' => '', 'button_text' => '' ), $atts, 'ggm_workshop_enroll' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if ( ! $workshop_id || ! class_exists( 'GGM_Workshop' ) ) {
			return '';
		}

		$user_id    = get_current_user_id();
		$is_contribution = GGM_Workshop::is_contribution( $workshop_id );
		$is_free         = ! $is_contribution && '1' === (string) get_post_meta( $workshop_id, 'is_free', true );
		$has_access = GGM_Workshop::user_has_access( $user_id, $workshop_id );
		$lessons    = class_exists( 'GGM_Lesson' ) ? GGM_Lesson::get_by_workshop( $workshop_id ) : array();
		$first_lesson_url = ! empty( $lessons ) ? get_permalink( $lessons[0]->ID ) : '';

		$active_slots = class_exists( 'GGM_Workshop_Slot' ) ? GGM_Workshop_Slot::get_for_workshop( $workshop_id, 'active' ) : array();
		$needs_slot   = count( $active_slots ) > 1;

		$uid            = wp_unique_id( 'ggm-ws-enroll-' );
		$custom_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) );
		$button_classes = $custom_classes ?: array( 'ggm-btn-enroll' );
		array_unshift( $button_classes, 'ggm-workshop-enroll-action' );
		$class = esc_attr( implode( ' ', array_unique( $button_classes ) ) );
		$button_text = sanitize_text_field( $atts['button_text'] );

		ob_start();

		if ( $has_access ) {
			if ( $first_lesson_url ) {
				echo '<a href="' . esc_url( $first_lesson_url ) . '" class="' . $class . '">' . esc_html__( 'Start Watching', 'ggm-member-dashboard' ) . '</a>';
			}
			return ob_get_clean();
		}

		if ( $is_free ) {
			$nonce = wp_create_nonce( 'ggm_nonce' );
			?>
			<div id="<?php echo esc_attr( $uid ); ?>" class="ggm-workshop-enroll-wrap">
				<?php if ( $needs_slot ) : ?>
					<select class="ggm-ws-slot">
						<option value=""><?php esc_html_e( 'Choose Your Workshop Time', 'ggm-member-dashboard' ); ?></option>
						<?php foreach ( $active_slots as $slot ) : ?>
							<option value="<?php echo esc_attr( $slot->id ); ?>"><?php echo esc_html( GGM_Workshop_Slot::format_range( $slot ) ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<button type="button" class="ggm-ws-register <?php echo $class; ?>"><?php echo esc_html( $button_text ?: __( 'Register Now', 'ggm-member-dashboard' ) ); ?></button>
				<p class="ggm-ws-register-feedback"></p>
			</div>
			<script>
			(function () {
				var wrap = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
				if (!wrap) return;
				var btn      = wrap.querySelector('.ggm-ws-register');
				var slot     = wrap.querySelector('.ggm-ws-slot');
				var feedback = wrap.querySelector('.ggm-ws-register-feedback');

				btn.addEventListener('click', function () {
					var slotId = slot ? slot.value : '';
					if (slot && !slotId) {
						feedback.style.color = '#b91c1c';
						feedback.textContent = <?php echo wp_json_encode( __( 'Please choose a workshop time slot.', 'ggm-member-dashboard' ) ); ?>;
						return;
					}
					btn.disabled = true;
					btn.textContent = <?php echo wp_json_encode( __( 'Registering…', 'ggm-member-dashboard' ) ); ?>;

					var data = new URLSearchParams();
					data.append('action', 'ggm_register_workshop');
					data.append('nonce', <?php echo wp_json_encode( $nonce ); ?>);
					data.append('workshop_id', <?php echo (int) $workshop_id; ?>);
					data.append('slot_id', slotId);

					fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: data.toString()
					}).then(function (r) { return r.json(); }).then(function (res) {
						if (res.success) {
							window.location.reload();
						} else {
							btn.disabled = false;
							btn.textContent = <?php echo wp_json_encode( $button_text ?: __( 'Register Now', 'ggm-member-dashboard' ) ); ?>;
							feedback.style.color = '#b91c1c';
							feedback.textContent = res.data.message || <?php echo wp_json_encode( __( 'Something went wrong.', 'ggm-member-dashboard' ) ); ?>;
						}
					}).catch(function () {
						btn.disabled = false;
						btn.textContent = <?php echo wp_json_encode( $button_text ?: __( 'Register Now', 'ggm-member-dashboard' ) ); ?>;
						feedback.style.color = '#b91c1c';
						feedback.textContent = <?php echo wp_json_encode( __( 'Something went wrong.', 'ggm-member-dashboard' ) ); ?>;
					});
				});
			})();
			</script>
			<?php
			return ob_get_clean();
		}

		// Paid workshop, not yet enrolled — link to this plugin's own checkout.
		$checkout_page_url = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );
		$selected_currency  = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$enroll_url         = add_query_arg( array( 'workshop_id' => $workshop_id, 'ggm_currency' => $selected_currency ), $checkout_page_url );
		echo '<a href="' . esc_url( $enroll_url ) . '" class="' . $class . '">' . esc_html( $button_text ?: __( 'Enroll Now', 'ggm-member-dashboard' ) ) . '</a>';

		return ob_get_clean();
	}

	/**
	 * Format the booking card's workshop date range without repeating shared
	 * month/year components. An explicit shortcode format retains the legacy
	 * endpoint-by-endpoint formatting behavior.
	 *
	 * @param string $start_value       Start date in Y-m-d format.
	 * @param string $end_value         End date in Y-m-d format.
	 * @param string $explicit_format   Optional WordPress date format.
	 * @return string
	 */
	private function format_booking_card_date_range( $start_value, $end_value, $explicit_format = '' ) {
		$timezone = wp_timezone();
		$parse_date = static function( $value ) use ( $timezone ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return null;
			}

			$date   = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $timezone );
			$errors = DateTimeImmutable::getLastErrors();
			if ( ! $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d' ) !== $value ) {
				return null;
			}

			return $date;
		};

		$start = $parse_date( $start_value );
		$end   = $parse_date( $end_value );
		if ( ! $start && ! $end ) {
			return '';
		}

		$separator = ' – ';
		if ( '' !== $explicit_format ) {
			$start_label = $start ? wp_date( $explicit_format, $start->getTimestamp(), $timezone ) : '';
			$end_label   = $end ? wp_date( $explicit_format, $end->getTimestamp(), $timezone ) : '';
			if ( ! $start || ! $end || $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' ) ) {
				return $start_label ?: $end_label;
			}

			return $start_label . $separator . $end_label;
		}

		$short_month = static function( DateTimeImmutable $date ) use ( $timezone ) {
			$month = wp_date( 'F', $date->getTimestamp(), $timezone );
			return function_exists( 'mb_substr' ) ? mb_substr( $month, 0, 4 ) : substr( $month, 0, 4 );
		};
		$single_date = static function( DateTimeImmutable $date ) use ( $short_month, $timezone ) {
			return sprintf(
				'%1$s %2$s, %3$s',
				$short_month( $date ),
				wp_date( 'j', $date->getTimestamp(), $timezone ),
				wp_date( 'Y', $date->getTimestamp(), $timezone )
			);
		};

		if ( ! $start || ! $end || $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' ) ) {
			return $single_date( $start ?: $end );
		}

		if ( $start->format( 'Y-m' ) === $end->format( 'Y-m' ) ) {
			return sprintf(
				'%1$s %2$s–%3$s, %4$s',
				$short_month( $start ),
				wp_date( 'j', $start->getTimestamp(), $timezone ),
				wp_date( 'j', $end->getTimestamp(), $timezone ),
				wp_date( 'Y', $start->getTimestamp(), $timezone )
			);
		}

		if ( $start->format( 'Y' ) === $end->format( 'Y' ) ) {
			return sprintf(
				'%1$s %2$s%3$s%4$s %5$s, %6$s',
				$short_month( $start ),
				wp_date( 'j', $start->getTimestamp(), $timezone ),
				$separator,
				$short_month( $end ),
				wp_date( 'j', $end->getTimestamp(), $timezone ),
				wp_date( 'Y', $start->getTimestamp(), $timezone )
			);
		}

		return $single_date( $start ) . $separator . $single_date( $end );
	}

	/**
	 * [ggm_workshop_booking_card id="" class="" date_format=""]
	 *
	 * A single, workshop-configured booking card for Elementor or standard
	 * content. It deliberately delegates pricing and the CTA to the existing
	 * authoritative implementations, so access, checkout, currency, sale, and
	 * contribution behavior cannot drift from the rest of the plugin.
	 */
	public function sc_workshop_booking_card( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'class' => '', 'date_format' => '' ), $atts, 'ggm_workshop_booking_card' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		if ( ! $workshop_id || ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
			return '';
		}

		// The default uses a compact, non-repeating range. An explicit shortcode
		// date_format retains the legacy full-endpoint formatting behavior.
		$date_format = sanitize_text_field( $atts['date_format'] );
		$start_date  = get_post_meta( $workshop_id, 'workshop_start_date', true ) ?: get_post_meta( $workshop_id, 'workshop_date', true );
		$end_date    = get_post_meta( $workshop_id, 'workshop_end_date', true );
		$date_label  = $this->format_booking_card_date_range( $start_date, $end_date, $date_format );

		$mode        = trim( (string) get_post_meta( $workshop_id, 'workshop_mode', true ) );
		$duration    = trim( (string) get_post_meta( $workshop_id, 'duration', true ) );
		// Duration often includes mode wording (for example, "7 Days Live
		// Online Workshop"). Keep only its day portion here to avoid repeating
		// the separately stored Workshop Mode on the second schedule line.
		$duration_label = $duration;
		if ( $duration && preg_match( '/\b\d+\s*(?:-| )?\s*days?\b/i', $duration, $duration_match ) ) {
			$duration_label = trim( $duration_match[0] );
		}
		$price_html  = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::price_html( $workshop_id ) : '';
		$price_note  = '';
		$current_price_display = $price_html;
		$regular_price_display = '';
		if ( class_exists( 'GGM_Workshop' ) ) {
			$regular_price = GGM_Workshop::get_regular_price( $workshop_id );
			$sale_price    = GGM_Workshop::get_sale_price( $workshop_id );
			if ( $sale_price > 0 && $sale_price < $regular_price ) {
				$savings     = $regular_price - $sale_price;
				$format_amount = static function( $amount ) use ( $workshop_id ) {
					return class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
						? GGM_Currency::format_converted( $amount, $workshop_id )
						: GGM_Workshop::get_currency( $workshop_id ) . number_format_i18n( $amount, 0 );
				};
				$current_price_display = esc_html( $format_amount( $sale_price ) );
				$regular_price_display = esc_html( $format_amount( $regular_price ) );
				$savings_label = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
					? GGM_Currency::format_converted( $savings, $workshop_id )
					: GGM_Workshop::get_currency( $workshop_id ) . number_format_i18n( $savings, 0 );
				$price_note = sprintf( __( 'You Save %s', 'ggm-member-dashboard' ), $savings_label );
			}
		}
		$hero_items  = get_post_meta( $workshop_id, 'ggm_workshop_booking_card_hero_items', true );
		$hero_items  = is_array( $hero_items ) ? array_slice( $hero_items, 0, 4 ) : array();
		$hero_items  = array_values( array_filter( $hero_items, static function( $item ) {
			return is_array( $item ) && ( ! empty( $item['icon'] ) || '' !== trim( (string) ( $item['text'] ?? '' ) ) );
		} ) );
		$cta_heading = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_booking_card_cta_heading', true ) );
		$cta_text    = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_booking_card_cta_text', true ) );
		$testimonial_text = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_booking_card_testimonial_text', true ) );
		$rating_text      = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_booking_card_rating_text', true ) );
		$form_anchor      = 'ggm-workshop-join-form-full-' . $workshop_id;
		$extra_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) );
		$classes = trim( 'ggm-workshop-booking-card ' . implode( ' ', $extra_classes ) );

		ob_start();
		?>
		<div class="ggm-workshop-booking-card-wrap">
			<?php if ( $hero_items ) : ?>
				<div class="ggm-workshop-booking-card__hero" style="display:block!important;width:100%!important;">
					<div class="ggm-workshop-booking-card__hero-items" role="list" style="display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;grid-auto-flow:row!important;gap:10px!important;width:100%!important;">
					<?php foreach ( $hero_items as $hero_item ) : ?>
						<div class="ggm-workshop-booking-card__hero-item" role="listitem" style="box-sizing:border-box!important;min-width:0!important;border:1px solid currentColor!important;border-radius:10px!important;padding:8px 4px!important;overflow:hidden!important;">
							<?php if ( ! empty( $hero_item['icon'] ) ) : ?><img src="<?php echo esc_url( $hero_item['icon'] ); ?>" alt="" loading="lazy"><?php endif; ?>
							<?php if ( '' !== trim( (string) ( $hero_item['text'] ?? '' ) ) ) : ?><span><?php echo esc_html( $hero_item['text'] ); ?></span><?php endif; ?>
						</div>
					<?php endforeach; ?></div>
				</div>
			<?php endif; ?>
			<section class="<?php echo esc_attr( $classes ); ?>">
			<?php
			$date_detail = $mode ?: $duration_label;
			$has_schedule = (bool) ( $date_label || $date_detail );
			$has_price    = (bool) $price_html;
			$summary_classes = array( 'ggm-workshop-booking-summary' );
			if ( ! $has_schedule ) {
				$summary_classes[] = 'ggm-workshop-booking-summary--price-only';
			} elseif ( ! $has_price ) {
				$summary_classes[] = 'ggm-workshop-booking-summary--schedule-only';
			}
			?>
			<?php if ( $has_schedule || $has_price ) : ?>
				<div class="ggm-workshop-booking-summary-wrap">
					<div class="<?php echo esc_attr( implode( ' ', $summary_classes ) ); ?>">
						<?php if ( $has_schedule ) : ?>
							<div class="ggm-workshop-booking-summary__schedule">
								<span class="ggm-workshop-booking-summary__calendar" aria-hidden="true"><?php echo $this->icon_svg( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Fixed, trusted inline SVG. ?></span>
								<div class="ggm-workshop-booking-summary__schedule-content"><?php if ( $date_label ) : ?><strong><?php echo esc_html( $date_label ); ?></strong><?php endif; ?><?php if ( $date_detail ) : ?><span><?php echo esc_html( $date_detail ); ?></span><?php endif; ?></div>
							</div>
						<?php endif; ?>
						<?php if ( $has_schedule && $has_price ) : ?><span class="ggm-workshop-booking-summary__divider" aria-hidden="true"></span><?php endif; ?>
						<?php if ( $has_price ) : ?>
							<div class="ggm-workshop-booking-summary__price-section">
								<?php if ( $has_schedule ) : ?><span class="ggm-workshop-booking-summary__money" aria-hidden="true"><?php echo $this->icon_svg( 'money' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Fixed, trusted inline SVG. ?></span><?php endif; ?>
								<div class="ggm-workshop-booking-summary__price">
									<div class="ggm-workshop-booking-summary__price-line"><span class="ggm-workshop-booking-summary__current-price"><?php echo wp_kses_post( $current_price_display ); ?></span><?php if ( $regular_price_display ) : ?><del><?php echo esc_html( $regular_price_display ); ?></del><?php endif; ?></div>
									<?php if ( $price_note ) : ?><span class="ggm-workshop-booking-summary__saving"><?php echo esc_html( $price_note ); ?></span><?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			<a class="ggm-workshop-booking-card__button" href="#<?php echo esc_attr( $form_anchor ); ?>"><span class="ggm-workshop-booking-card__button-heading"><?php echo esc_html( $cta_heading ?: __( 'Reserve My Spot Now', 'ggm-member-dashboard' ) ); ?></span><span class="ggm-workshop-booking-card__button-text"><?php echo esc_html( $cta_text ?: __( 'Submit Now', 'ggm-member-dashboard' ) ); ?></span></a>
			<?php if ( $testimonial_text || $rating_text ) : ?>
				<div class="ggm-workshop-booking-card__testimonial">
					<?php if ( $testimonial_text ) : ?><strong><?php echo esc_html( $testimonial_text ); ?></strong><?php endif; ?>
					<?php if ( $rating_text ) : ?><span><span class="ggm-workshop-booking-card__stars" aria-hidden="true">★★★★★</span><?php echo esc_html( $rating_text ); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [ggm_workshop_booking_summary id="" class="" date_format=""]
	 *
	 * A compact, separately placeable schedule-and-price summary. This is kept
	 * independent from [ggm_workshop_booking_card] so placing it elsewhere does
	 * not alter the existing booking card, CTA, hero, or testimonial.
	 */
	public function sc_workshop_booking_summary( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'class' => '', 'date_format' => '' ), $atts, 'ggm_workshop_booking_summary' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		if ( ! $workshop_id || ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
			return '';
		}

		$date_format = sanitize_text_field( $atts['date_format'] );
		$start_date  = get_post_meta( $workshop_id, 'workshop_start_date', true ) ?: get_post_meta( $workshop_id, 'workshop_date', true );
		$end_date    = get_post_meta( $workshop_id, 'workshop_end_date', true );
		$date_label  = $this->format_booking_card_date_range( $start_date, $end_date, $date_format );
		$mode        = trim( (string) get_post_meta( $workshop_id, 'workshop_mode', true ) );
		$duration    = trim( (string) get_post_meta( $workshop_id, 'duration', true ) );
		$duration_label = $duration;
		if ( $duration && preg_match( '/\b\d+\s*(?:-| )?\s*days?\b/i', $duration, $duration_match ) ) {
			$duration_label = trim( $duration_match[0] );
		}
		$date_detail = $mode ?: $duration_label;

		$price_html           = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::price_html( $workshop_id ) : '';
		$current_price_display = $price_html;
		$regular_price_display = '';
		$price_note            = '';
		if ( class_exists( 'GGM_Workshop' ) ) {
			$regular_price = GGM_Workshop::get_regular_price( $workshop_id );
			$sale_price    = GGM_Workshop::get_sale_price( $workshop_id );
			if ( $sale_price > 0 && $sale_price < $regular_price ) {
				$format_amount = static function( $amount ) use ( $workshop_id ) {
					return class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
						? GGM_Currency::format_converted( $amount, $workshop_id )
						: GGM_Workshop::get_currency( $workshop_id ) . number_format_i18n( $amount, 0 );
				};
				$current_price_display = esc_html( $format_amount( $sale_price ) );
				$regular_price_display = esc_html( $format_amount( $regular_price ) );
				$savings_display       = $format_amount( $regular_price - $sale_price );
				$price_note            = sprintf( __( 'You Save %s', 'ggm-member-dashboard' ), $savings_display );
			}
		}

		$has_schedule = (bool) ( $date_label || $date_detail );
		$has_price    = (bool) $price_html;
		if ( ! $has_schedule && ! $has_price ) {
			return '';
		}

		$extra_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) );
		$classes       = array( 'ggm-workshop-booking-summary' );
		if ( ! $has_schedule ) {
			$classes[] = 'ggm-workshop-booking-summary--price-only';
		} elseif ( ! $has_price ) {
			$classes[] = 'ggm-workshop-booking-summary--schedule-only';
		}
		$classes = array_merge( $classes, $extra_classes );

		ob_start();
		?>
		<div class="ggm-workshop-booking-summary-wrap">
			<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
				<?php if ( $has_schedule ) : ?>
					<div class="ggm-workshop-booking-summary__schedule">
						<span class="ggm-workshop-booking-summary__calendar" aria-hidden="true"><?php echo $this->icon_svg( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Fixed, trusted inline SVG. ?></span>
						<div class="ggm-workshop-booking-summary__schedule-content"><?php if ( $date_label ) : ?><strong><?php echo esc_html( $date_label ); ?></strong><?php endif; ?><?php if ( $date_detail ) : ?><span><?php echo esc_html( $date_detail ); ?></span><?php endif; ?></div>
					</div>
				<?php endif; ?>
				<?php if ( $has_schedule && $has_price ) : ?><span class="ggm-workshop-booking-summary__divider" aria-hidden="true"></span><?php endif; ?>
				<?php if ( $has_price ) : ?>
					<div class="ggm-workshop-booking-summary__price-section">
						<?php if ( $has_schedule ) : ?><span class="ggm-workshop-booking-summary__money" aria-hidden="true"><?php echo $this->icon_svg( 'money' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Fixed, trusted inline SVG. ?></span><?php endif; ?>
						<div class="ggm-workshop-booking-summary__price">
							<div class="ggm-workshop-booking-summary__price-line"><span class="ggm-workshop-booking-summary__current-price"><?php echo wp_kses_post( $current_price_display ); ?></span><?php if ( $regular_price_display ) : ?><del><?php echo esc_html( $regular_price_display ); ?></del><?php endif; ?></div>
							<?php if ( $price_note ) : ?><span class="ggm-workshop-booking-summary__saving"><?php echo esc_html( $price_note ); ?></span><?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [ggm_workshop_booking_card_hero_header id=""] — separately placeable
	 * rich header configured in the Booking Card Hero settings.
	 */
	public function sc_workshop_booking_card_hero_header( $atts ) {
		$atts        = shortcode_atts( array( 'id' => '', 'class' => '' ), $atts, 'ggm_workshop_booking_card_hero_header' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$header      = get_post_meta( $workshop_id, 'ggm_workshop_booking_card_hero_header', true );
		$allowed     = class_exists( 'GGM_Meta_Boxes' ) ? GGM_Meta_Boxes::HERO_HEADER_ALLOWED_TAGS : array( 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'br' => array(), 'span' => array( 'style' => array() ), 'p' => array( 'style' => array() ) );
		$header      = is_scalar( $header ) ? wp_kses( (string) $header, $allowed ) : '';
		if ( '' === trim( wp_strip_all_tags( $header ) ) ) {
			return '';
		}

		$class = trim( 'ggm-workshop-booking-card__hero-header ' . sanitize_html_class( $atts['class'] ) );
		return '<div class="' . esc_attr( $class ) . '">' . $header . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Saved and rendered through the restricted Hero Header allowlist.
	}

	/**
	 * [ggm_workshop_form_text id=""] — workshop-configured heading and rich text.
	 */
	public function sc_workshop_form_text( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0 ), $atts, 'ggm_workshop_form_text' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		if ( ! $workshop_id || ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
			return '';
		}

		$heading_raw = get_post_meta( $workshop_id, 'ggm_workshop_form_text_heading', true );
		$content_raw = get_post_meta( $workshop_id, 'ggm_workshop_form_text_content', true );
		$heading = is_scalar( $heading_raw ) ? trim( (string) $heading_raw ) : '';
		$content = is_scalar( $content_raw ) ? wp_kses_post( (string) $content_raw ) : '';
		$has_rich_content = '' !== trim( wp_strip_all_tags( $content ) ) || (bool) preg_match( '/<(?:audio|figure|gallery|img|video)\b/i', $content );
		if ( '' === $heading && ! $has_rich_content ) {
			return '';
		}

		ob_start();
		?>
		<section class="ggm-ws-form-text">
			<?php if ( '' !== $heading ) : ?><h2 class="ggm-ws-section-heading"><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
			<?php if ( $has_rich_content ) : ?><div class="ggm-ws-form-text__content"><?php echo wp_kses_post( wpautop( $content ) ); ?></div><?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * [ggm_workshop_discover id=""] — "You Will Discover" heading and grid.
	 *
	 * Renders each row as a centered block: a large circular photo with a
	 * soft gradient ring, a bold navy heading line, and a plain caption line
	 * below it.
	 */
	public function sc_workshop_discover( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'show_heading' => 'yes' ), $atts, 'ggm_workshop_discover' );
		$workshop_id  = $this->resolve_workshop_id( $atts );
		$discover_raw = get_post_meta( $workshop_id, 'ggm_you_will_discover', true );
		if ( '' === $discover_raw ) {
			$discover_raw = get_post_meta( $workshop_id, 'you_will_discover', true ); // Legacy key, pre-rename.
		}
		$rows = json_decode( $discover_raw, true );
		if ( is_array( $rows ) ) {
			$rows = array_values( array_filter( $rows, function ( $row ) {
				return is_array( $row ) && ( ! empty( $row['heading'] ) || ! empty( $row['description'] ) || ! empty( $row['image'] ) );
			} ) );
		}

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return '';
		}
		$layout = $this->workshop_block_grid_layout( $rows, $workshop_id, 'discover', 1 );
		$rows   = $layout['rows'];
		$bottom_text = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_discover_bottom_text', true ) );

		$this->print_discover_styles();

		ob_start();
		echo '<section class="ggm-ws-block ggm-ws-block--discover">';
		if ( $this->show_workshop_block_heading( $atts['show_heading'] ) ) {
			echo '<h2 class="ggm-ws-section-heading">' . esc_html( ggm_get_workshop_block_heading( 'discover', $workshop_id ) ) . '</h2>';
		}
		echo '<div class="ggm-ws-discover' . esc_attr( $layout['class'] ) . '"' . $layout['style'] . '>';
		foreach ( $rows as $row ) {
			$heading     = $row['heading'] ?? '';
			$description = $row['description'] ?? '';
			$image       = $row['image'] ?? '';
			if ( ! $heading && ! $description && ! $image ) {
				continue;
			}
			echo '<div class="ggm-ws-discover__item">';
			if ( $image ) {
				echo '<span class="ggm-ws-discover__icon"><img src="' . esc_url( $image ) . '" alt="" loading="lazy"></span>';
			}
			if ( $heading || $description ) {
				echo '<div>';
				if ( $heading ) {
					echo '<p class="ggm-ws-discover__heading">' . esc_html( $heading ) . '</p>';
				}
				if ( $description ) {
					$allowed_tags = class_exists( 'GGM_Meta_Boxes' ) ? GGM_Meta_Boxes::SHORT_DESC_ALLOWED_TAGS : array();
					echo '<p class="ggm-ws-discover__desc">' . wp_kses( $description, $allowed_tags ) . '</p>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
		if ( '' !== $bottom_text ) {
			echo '<p class="ggm-ws-block__bottom-text">' . nl2br( esc_html( $bottom_text ) ) . '</p>';
		}
		echo '</section>';
		return ob_get_clean();
	}

	/**
	 * Print the "You Will Discover" block CSS once per page load.
	 */
	private function print_discover_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_why_different id=""] — "Why This Webinar Is Different" heading and grid.
	 *
	 * Renders each row as a tinted, rounded card with the icon in a solid
	 * circular badge above a bold centered title (matching the "stacked icon
	 * box" design used in the original workshop hero layout).
	 */
	public function sc_workshop_why_different( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'show_heading' => 'yes' ), $atts, 'ggm_workshop_why_different' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$diff_raw    = get_post_meta( $workshop_id, 'ggm_why_different_points', true );
		if ( '' === $diff_raw ) {
			$diff_raw = get_post_meta( $workshop_id, 'why_different_points', true ); // Legacy key, pre-rename.
		}
		$rows = json_decode( $diff_raw, true );
		if ( is_array( $rows ) ) {
			$rows = array_values( array_filter( $rows, function ( $row ) {
				return is_array( $row ) && ( ! empty( $row['title'] ) || ! empty( $row['icon'] ) );
			} ) );
		}

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return '';
		}
		$layout = $this->workshop_block_grid_layout( $rows, $workshop_id, 'why_different', 3 );
		$rows   = $layout['rows'];
		$bottom_text = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_why_different_bottom_text', true ) );

		$this->print_why_different_styles();

		ob_start();
		echo '<section class="ggm-ws-block ggm-ws-block--why-different">';
		if ( $this->show_workshop_block_heading( $atts['show_heading'] ) ) {
			echo '<h2 class="ggm-ws-section-heading">' . esc_html( ggm_get_workshop_block_heading( 'why_different', $workshop_id ) ) . '</h2>';
		}
		echo '<div class="ggm-ws-why-different' . esc_attr( $layout['class'] ) . '"' . $layout['style'] . '>';
		foreach ( $rows as $row ) {
			$title = $row['title'] ?? '';
			$icon  = $row['icon'] ?? '';
			if ( ! $title && ! $icon ) {
				continue;
			}
			echo '<div class="ggm-ws-why-different__item">';
			if ( $icon ) {
				echo '<span class="ggm-ws-why-different__icon"><img src="' . esc_url( $icon ) . '" alt="" loading="lazy"></span>';
			}
			if ( $title ) {
				echo '<p class="ggm-ws-why-different__title">' . esc_html( $title ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';
		if ( '' !== $bottom_text ) {
			echo '<p class="ggm-ws-block__bottom-text">' . nl2br( esc_html( $bottom_text ) ) . '</p>';
		}
		echo '</section>';
		return ob_get_clean();
	}

	/**
	 * [ggm_workshop_why_workshop_is_different id=""] — a two-column
	 * "Why {Workshop Name} is Different" section with configurable copy,
	 * tick points, image, and optional Workshop-specific colour overrides.
	 */
	public function sc_workshop_why_workshop_is_different( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0 ), $atts, 'ggm_workshop_why_workshop_is_different' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$intro       = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_why_workshop_different_intro', true ) );
		$points_raw  = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_why_workshop_different_points', true ) );
		$image       = esc_url( get_post_meta( $workshop_id, 'ggm_workshop_why_workshop_different_image', true ) );
		$points_data = json_decode( $points_raw, true );
		$points      = is_array( $points_data ) ? array_values( array_filter( array_map( static function( $point ) { return is_array( $point ) ? trim( (string) ( $point['text'] ?? '' ) ) : ''; }, $points_data ) ) ) : array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $points_raw ) ) ) );

		if ( '' === $intro && empty( $points ) && '' === $image ) {
			return '';
		}

		$workshop_name = trim( wp_strip_all_tags( get_the_title( $workshop_id ) ) );
		$default_heading = sprintf( __( 'Why %s is Different', 'ggm-member-dashboard' ), $workshop_name ?: __( 'This Workshop', 'ggm-member-dashboard' ) );
		$heading         = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_why_workshop_different_heading', true ) ) ?: $default_heading;

		ob_start();
		?>
		<section class="ggm-ws-why-workshop-different">
			<div class="ggm-ws-why-workshop-different__content">
				<h2 class="ggm-ws-section-heading"><?php echo esc_html( $heading ); ?></h2>
				<?php if ( '' !== $intro ) : ?><p class="ggm-ws-why-workshop-different__intro"><?php echo nl2br( esc_html( $intro ) ); ?></p><?php endif; ?>
				<?php if ( ! empty( $points ) ) : ?>
					<ul class="ggm-ws-why-workshop-different__points">
						<?php foreach ( $points as $point ) : ?>
							<li><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.5 2.5L16 9"></path></svg><span><?php echo esc_html( $point ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $image ) : ?><div class="ggm-ws-why-workshop-different__media"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $heading ); ?>" loading="lazy"></div><?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * [ggm_workshop_journey id=""] — linked Workshop day cards and up to
	 * four supporting footer boxes. Day cards are paged in groups of seven.
	 */
	public function sc_workshop_journey( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0 ), $atts, 'ggm_workshop_journey' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$heading     = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_journey_heading', true ) );
		$days_raw    = get_post_meta( $workshop_id, 'ggm_workshop_journey_days', true );
		$days        = json_decode( $days_raw, true );
		$days        = is_array( $days ) ? array_values( array_filter( $days, static function( $day ) {
			return is_array( $day ) && ( '' !== trim( (string) ( $day['day'] ?? '' ) ) || '' !== trim( (string) ( $day['description'] ?? '' ) ) || ! empty( $day['image'] ) );
		} ) ) : array();
		$footer_items = get_post_meta( $workshop_id, 'ggm_workshop_journey_footer_items', true );
		$footer_items = is_array( $footer_items ) ? array_slice( $footer_items, 0, 4 ) : array();
		$footer_items = array_values( array_filter( $footer_items, static function( $item ) {
			return is_array( $item ) && ( '' !== trim( (string) ( $item['text'] ?? '' ) ) || ! empty( $item['icon'] ) );
		} ) );

		if ( '' === $heading && empty( $days ) && empty( $footer_items ) ) {
			return '';
		}

		$pages     = array_chunk( $days, 7 );
		$page_count = count( $pages );
		$slider_id = wp_unique_id( 'ggm-ws-journey-' );

		ob_start();
		?>
		<section class="ggm-ws-journey" id="<?php echo esc_attr( $slider_id ); ?>">
			<?php if ( '' !== $heading ) : ?><h2 class="ggm-ws-section-heading"><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
			<?php if ( $pages ) : ?>
				<div class="ggm-ws-journey__slider">
					<div class="ggm-ws-journey__viewport"><div class="ggm-ws-journey__track">
						<?php foreach ( $pages as $page_index => $page ) : ?>
							<div class="ggm-ws-journey__page"<?php echo 0 === $page_index ? '' : ' aria-hidden="true"'; ?>>
								<?php foreach ( $page as $day ) : $day_label = trim( (string) ( $day['day'] ?? '' ) ); $description = trim( (string) ( $day['description'] ?? '' ) ); $image = esc_url( $day['image'] ?? '' ); ?>
									<article class="ggm-ws-journey__day"><span class="ggm-ws-journey__marker"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy"><?php else : ?><span aria-hidden="true"><?php echo esc_html( $day_label ?: '•' ); ?></span><?php endif; ?></span><div class="ggm-ws-journey__card"><?php if ( '' !== $day_label ) : ?><strong><?php echo esc_html( $day_label ); ?></strong><?php endif; ?><?php if ( '' !== $description ) : ?><span><?php echo esc_html( $description ); ?></span><?php endif; ?></div></article>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</div></div>
					<?php if ( $page_count > 1 ) : ?><div class="ggm-ws-journey__nav"><button type="button" class="ggm-ws-journey__arrow ggm-ws-journey__arrow--previous" aria-label="<?php esc_attr_e( 'Show previous days', 'ggm-member-dashboard' ); ?>" disabled>&larr;</button><button type="button" class="ggm-ws-journey__arrow ggm-ws-journey__arrow--next" aria-label="<?php esc_attr_e( 'Show more days', 'ggm-member-dashboard' ); ?>">&rarr;</button></div><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $footer_items ) : ?><div class="ggm-ws-journey__footer" role="list"><?php foreach ( $footer_items as $footer_item ) : ?><div class="ggm-ws-journey__footer-item" role="listitem"><?php if ( ! empty( $footer_item['icon'] ) ) : ?><img src="<?php echo esc_url( $footer_item['icon'] ); ?>" alt="" loading="lazy"><?php endif; ?><?php if ( '' !== trim( (string) ( $footer_item['text'] ?? '' ) ) ) : ?><span><?php echo esc_html( $footer_item['text'] ); ?></span><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
		</section>
		<?php if ( $page_count > 1 ) : ?>
		<script>
		(function(){var root=document.getElementById(<?php echo wp_json_encode( $slider_id ); ?>);if(!root){return;}var track=root.querySelector('.ggm-ws-journey__track'),pages=root.querySelectorAll('.ggm-ws-journey__page'),previous=root.querySelector('.ggm-ws-journey__arrow--previous'),next=root.querySelector('.ggm-ws-journey__arrow--next'),index=0;function render(){track.style.transform='translateX(-'+(index*100)+'%)';previous.disabled=index===0;next.disabled=index===pages.length-1;pages.forEach(function(page,pageIndex){page.setAttribute('aria-hidden',pageIndex===index?'false':'true');});}previous.addEventListener('click',function(){if(index>0){index--;render();}});next.addEventListener('click',function(){if(index<pages.length-1){index++;render();}});render();})();
		</script>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Print the "Why This Webinar Is Different" card CSS once per page load.
	 */
	private function print_why_different_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_perfect_for id=""] — "Perfect For You If You Want To" heading and list.
	 *
	 * Renders each row as a horizontal row: a framed image on the left, a
	 * bold heading and a description paragraph stacked on the right.
	 */
	public function sc_workshop_perfect_for( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'show_heading' => 'yes' ), $atts, 'ggm_workshop_perfect_for' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$perf_raw    = get_post_meta( $workshop_id, 'ggm_perfect_for_you', true );
		if ( '' === $perf_raw ) {
			$perf_raw = get_post_meta( $workshop_id, 'perfect_for_you', true ); // Legacy key, pre-rename.
		}
		$rows = json_decode( $perf_raw, true );
		if ( is_array( $rows ) ) {
			$rows = array_values( array_filter( $rows, function ( $row ) {
				return is_array( $row ) && ( ! empty( $row['title'] ) || ! empty( $row['description'] ) || ! empty( $row['image'] ) );
			} ) );
		}

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return '';
		}
		$layout = $this->workshop_block_grid_layout( $rows, $workshop_id, 'perfect_for', 3 );
		$rows   = $layout['rows'];
		$bottom_text = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_perfect_for_bottom_text', true ) );

		$this->print_perfect_for_styles();

		ob_start();
		echo '<section class="ggm-ws-block ggm-ws-block--perfect-for">';
		if ( $this->show_workshop_block_heading( $atts['show_heading'] ) ) {
			echo '<h2 class="ggm-ws-section-heading">' . esc_html( ggm_get_workshop_block_heading( 'perfect_for', $workshop_id ) ) . '</h2>';
		}
		echo '<div class="ggm-ws-perfect-for' . esc_attr( $layout['class'] ) . '"' . $layout['style'] . '>';
		foreach ( $rows as $row ) {
			$title       = $row['title'] ?? '';
			$description = $row['description'] ?? '';
			$image       = $row['image'] ?? '';
			if ( ! $title && ! $description && ! $image ) {
				continue;
			}
			echo '<div class="ggm-ws-perfect-for__item">';
			if ( $image ) {
				echo '<span class="ggm-ws-perfect-for__image"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( $title ) . '" loading="lazy"></span>';
			}
			echo '<div class="ggm-ws-perfect-for__text">';
			if ( $title ) {
				echo '<h3 class="ggm-ws-perfect-for__title">' . esc_html( $title ) . '</h3>';
			}
			if ( $description ) {
				echo '<p class="ggm-ws-perfect-for__desc">' . esc_html( $description ) . '</p>';
			}
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
		if ( '' !== $bottom_text ) {
			echo '<p class="ggm-ws-block__bottom-text">' . nl2br( esc_html( $bottom_text ) ) . '</p>';
		}
		echo '</section>';
		return ob_get_clean();
	}

	/**
	 * Print the "Perfect For You" row CSS once per page load.
	 */
	private function print_perfect_for_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_faq id=""] — FAQ heading and accordion (question + answer).
	 */
	public function sc_workshop_faq( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'show_heading' => 'yes' ), $atts, 'ggm_workshop_faq' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$faq_raw     = get_post_meta( $workshop_id, 'ggm_faq', true );
		if ( '' === $faq_raw ) {
			$faq_raw = get_post_meta( $workshop_id, 'faq', true ); // Legacy key, pre-rename.
		}
		$rows = json_decode( $faq_raw, true );
		if ( is_array( $rows ) ) {
			$rows = array_values( array_filter( $rows, function ( $row ) {
				return is_array( $row ) && ! empty( $row['question'] );
			} ) );
		}

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return '';
		}

		$this->print_faq_styles();

		ob_start();
		echo '<section class="ggm-ws-block ggm-ws-block--faq">';
		if ( $this->show_workshop_block_heading( $atts['show_heading'] ) ) {
			echo '<h2 class="ggm-ws-section-heading">' . esc_html( ggm_get_workshop_block_heading( 'faq', $workshop_id ) ) . '</h2>';
		}
		echo '<div class="ggm-ws-faq">';
		foreach ( $rows as $row ) {
			$question = $row['question'] ?? '';
			$answer   = $row['answer'] ?? '';
			if ( ! $question ) {
				continue;
			}
			echo '<details class="ggm-ws-faq__item">';
			echo '<summary class="ggm-ws-faq__question">';
			echo '<span class="ggm-ws-faq__question-text">' . esc_html( $question ) . '</span>';
			echo '<span class="ggm-ws-faq__toggle" aria-hidden="true"></span>';
			echo '</summary>';
			echo '<div class="ggm-ws-faq__answer">' . wp_kses_post( wpautop( $answer ) ) . '</div>';
			echo '</details>';
		}
		echo '</div>';
		echo '</section>';
		return ob_get_clean();
	}

	/**
	 * Print the FAQ accordion CSS once per page load — teal-tinted cards with
	 * a circular plus/minus toggle, matching the "Why This Webinar Is
	 * Different" card styling used elsewhere on the workshop page.
	 */
	private function print_faq_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_icon_list id="" fields="date,preparatory,time,price,mode,language" date_format="M j, Y"]
	 *
	 * Renders the workshop's optional Preparatory Date, time slot(s), price,
	 * mode, and language as
	 * a vertical icon list (calendar / clock / currency / camera / speech
	 * bubble), styled as a solid teal card with white icons and text. Every
	 * value is fetched live from the workshop's own post meta / slots table
	 * on each render — nothing here is cached or hardcoded.
	 */
	public function sc_workshop_icon_list( $atts ) {
		$atts        = shortcode_atts( array(
			'id'          => 0,
			'fields'      => 'date,time,price,mode',
			'date_format' => 'M j, Y',
		), $atts, 'ggm_workshop_icon_list' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$fields      = array_filter( array_map( 'trim', explode( ',', $atts['fields'] ) ) );

		$items = array();

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'date':
					$range_start = get_post_meta( $workshop_id, 'workshop_start_date', true ) ?: get_post_meta( $workshop_id, 'workshop_date', true );
					$range_end = get_post_meta( $workshop_id, 'workshop_end_date', true );
					if ( $range_start ) {
						$items[] = array(
							'icon' => $this->icon_svg( 'calendar' ),
							'text' => date_i18n( $atts['date_format'], strtotime( $range_start ) ) . ( $range_end ? ' – ' . date_i18n( $atts['date_format'], strtotime( $range_end ) ) : '' ),
						);
					}
					break;

				case 'preparatory':
					$preparatory_date = get_post_meta( $workshop_id, 'workshop_preparatory_date', true );
					if ( $preparatory_date ) {
						$items[] = array(
							'icon' => $this->icon_svg( 'calendar' ),
							'text' => sprintf( __( 'Preparatory: %s', 'ggm-member-dashboard' ), date_i18n( $atts['date_format'], strtotime( $preparatory_date ) ) ),
						);
					}
					break;

				case 'time':
					// One row per configured & enabled slot — never collapsed
					// into a generic "multiple slots" message: 0 slots shows
					// nothing, 1 slot shows one row, N slots show N rows.
					$slots = class_exists( 'GGM_Workshop_Slot' ) ? GGM_Workshop_Slot::get_for_workshop( $workshop_id, 'active' ) : array();
					if ( count( $slots ) > 1 ) {
						$items[] = array(
							'icon' => $this->icon_svg( 'clock' ),
							'text' => __( 'Multiple time slots available', 'ggm-member-dashboard' ),
						);
					} elseif ( 1 === count( $slots ) ) {
						$items[] = array(
							'icon' => $this->icon_svg( 'clock' ),
							'text' => GGM_Workshop_Slot::type_label( $slots[0]->slot_type ) . ': ' . GGM_Workshop_Slot::format_start_time( $slots[0] ),
						);
					}
					break;

				case 'price':
					if ( class_exists( 'GGM_Workshop' ) ) {
						$price_html = GGM_Workshop::price_html( $workshop_id );
						if ( GGM_Workshop::is_contribution( $workshop_id ) && preg_match( '/^Contribute\s*\((.*)\)$/u', wp_strip_all_tags( $price_html ), $price_match ) ) {
							$price_html = '<span class="ggm-price ggm-price--contribution">' . esc_html__( 'Contribution: ', 'ggm-member-dashboard' ) . esc_html( $price_match[1] ) . '</span>';
						}
						// price_html() already returns escaped, trusted HTML (regular
						// price struck through next to the sale price, when one is
						// set) — don't esc_html() it again below.
						$items[] = array(
							'icon' => $this->icon_svg( 'rupee' ),
							'text' => $price_html,
							'html' => true,
						);
					} else {
						$price = get_post_meta( $workshop_id, 'workshop_money', true );
						if ( $price ) {
							$items[] = array(
								'icon' => $this->icon_svg( 'rupee' ),
								'text' => $price,
							);
						}
					}
					break;

				case 'mode':
					$mode = get_post_meta( $workshop_id, 'workshop_mode', true );
					if ( $mode ) {
						$items[] = array(
							'icon' => $this->icon_svg( 'video' ),
							'text' => $mode,
						);
					}
					break;

				case 'language':
					$language = get_post_meta( $workshop_id, 'workshop_language', true );
					$items[]  = array(
						'icon' => $this->icon_svg( 'language' ),
						'text' => 'hindi' === $language ? __( 'Hindi', 'ggm-member-dashboard' ) : __( 'English', 'ggm-member-dashboard' ),
					);
					break;
			}
		}

		if ( empty( $items ) ) {
			return '';
		}

		$this->print_icon_list_styles();

		ob_start();
		echo '<div class="ggm-ws-icon-list">';
		foreach ( $items as $item ) {
			echo '<div class="ggm-ws-icon-list__row">';
			echo '<span class="ggm-ws-icon-list__icon">' . $item['icon'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed, trusted SVG/glyph markup.
			echo '<span class="ggm-ws-icon-list__text">' . ( ! empty( $item['html'] ) ? $item['text'] : esc_html( $item['text'] ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- 'html' items come only from GGM_Workshop::format_price(), which already escapes.
			echo '</div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Fixed inline SVG/glyph icons for the icon list (stroke = currentColor).
	 *
	 * @param string $name calendar|clock|video|rupee|language|money
	 * @return string
	 */
	private function icon_svg( $name ) {
		if ( 'rupee' === $name ) {
			return '<span class="ggm-ws-icon-list__glyph">&#8377;</span>';
		}

		$icons = array(
			'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
			'money'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><rect x="2.5" y="5.5" width="19" height="13" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6.5 12h.01M17.5 12h.01"/></svg>',
			'clock'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/></svg>',
			'video'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 10l6-3v10l-6-3"/></svg>',
			'language' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.7 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.7-3.8-9s1.3-6.5 3.8-9z"/></svg>',
		);

		return $icons[ $name ] ?? '';
	}

	/**
	 * Print the icon list CSS once per page load.
	 */
	private function print_icon_list_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_countdown id="" label="Starts in :" expired_text="Workshop Started"]
	 *
	 * Live countdown (days/hours/minutes) to the workshop's "Counter Start
	 * Date" — set per-workshop in the Workshop Details meta box — styled as
	 * a solid teal card matching the icon-list design. Updates client-side
	 * every second; once the target date has passed it swaps to
	 * expired_text and hides the counter.
	 */
	public function sc_workshop_countdown( $atts ) {
		$atts = shortcode_atts( array(
			'id'           => 0,
			'label'        => __( 'Starts in :', 'ggm-member-dashboard' ),
			'expired_text' => __( 'Workshop Started', 'ggm-member-dashboard' ),
		), $atts, 'ggm_workshop_countdown' );

		$workshop_id = $this->resolve_workshop_id( $atts );
		$start_date  = get_post_meta( $workshop_id, 'Counter_Start_Date', true );
		$start       = $start_date ? DateTime::createFromFormat( 'Y-m-d\TH:i', $start_date, wp_timezone() ) : false;
		if ( ! $start && $start_date ) { $start = DateTime::createFromFormat( 'Y-m-d H:i:s', $start_date, wp_timezone() ); }
		$timestamp   = $start ? $start->getTimestamp() : false;

		if ( ! $timestamp ) {
			return '';
		}

		$this->print_countdown_styles();
		$primary = sanitize_hex_color( ggm_get_setting( 'ggm_dash_primary', '#0e9e6e' ) ) ?: '#0e9e6e';
		$primary_end = sanitize_hex_color( ggm_get_setting( 'ggm_dash_primary_end', '#07c98b' ) ) ?: '#07c98b';

		$uid = wp_unique_id( 'ggm-ws-countdown-' );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $uid ); ?>" class="ggm-ws-countdown" data-target="<?php echo esc_attr( $timestamp * 1000 ); ?>">
			<div class="ggm-ws-countdown__row">
				<span class="ggm-ws-countdown__item"><strong class="ggm-ws-countdown__num ggm-cd-days">00</strong><span class="ggm-ws-countdown__unit"><?php esc_html_e( 'days', 'ggm-member-dashboard' ); ?></span></span>
				<span class="ggm-ws-countdown__item"><strong class="ggm-ws-countdown__num ggm-cd-hours">00</strong><span class="ggm-ws-countdown__unit"><?php esc_html_e( 'hours', 'ggm-member-dashboard' ); ?></span></span>
				<span class="ggm-ws-countdown__item"><strong class="ggm-ws-countdown__num ggm-cd-minutes">00</strong><span class="ggm-ws-countdown__unit"><?php esc_html_e( 'mins', 'ggm-member-dashboard' ); ?></span></span>
				<span class="ggm-ws-countdown__item"><strong class="ggm-ws-countdown__num ggm-cd-seconds">00</strong><span class="ggm-ws-countdown__unit"><?php esc_html_e( 'secs', 'ggm-member-dashboard' ); ?></span></span>
			</div>
		</div>
		<script>
		(function () {
			var wrap = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if (!wrap) return;
			var target  = parseInt(wrap.getAttribute('data-target'), 10);
			var daysEl  = wrap.querySelector('.ggm-cd-days');
			var hoursEl = wrap.querySelector('.ggm-cd-hours');
			var minsEl  = wrap.querySelector('.ggm-cd-minutes');
			var secsEl  = wrap.querySelector('.ggm-cd-seconds');
			var timer   = null;
			function pad(value) { return String(value).padStart(2, '0'); }

			function tick() {
				var diff = Math.max(0, target - Date.now());
				var totalSeconds = Math.floor(diff / 1000);
				var days    = Math.floor(totalSeconds / 86400);
				var hours   = Math.floor((totalSeconds % 86400) / 3600);
				var minutes = Math.floor((totalSeconds % 3600) / 60);
				var seconds = totalSeconds % 60;
				daysEl.textContent  = pad(days);
				hoursEl.textContent = pad(hours);
				minsEl.textContent  = pad(minutes);
				secsEl.textContent  = pad(seconds);
				if (diff <= 0 && timer) { clearInterval(timer); timer = null; }
			}

			tick();
			timer = setInterval(tick, 1000);
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Print the countdown card CSS once per page load.
	 */
	private function print_countdown_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_heading id="" before="GGM " after=" Days Course"]
	 *
	 * Renders "GGM {N} Days Course" where {N} is the number of days pulled
	 * from the workshop's Duration field (e.g. "14 Days" -> "14"). Fixed
	 * typography: Space Grotesk, 25px, semi-bold (600), 1.1em line height,
	 * pure white.
	 */
	public function sc_workshop_heading( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'before' => 'GGM ', 'after' => ' Days Course' ), $atts, 'ggm_workshop_heading' );
		$workshop_id = $this->resolve_workshop_id( $atts );
		$duration    = get_post_meta( $workshop_id, 'duration', true );

		if ( ! $duration ) {
			return '';
		}

		$days = preg_replace( '/[^0-9]/', '', $duration );
		$days = '' !== $days ? $days : $duration;

		$style = "font-family:'Space Grotesk',sans-serif;font-size:25px;font-weight:600;line-height:1.1em;color:#000000;";

		return '<span class="ggm-workshop-heading">'
			. esc_html( $atts['before'] . $days . $atts['after'] )
			. '</span>';
	}

	/**
	 * [ggm_workshop_header_pill id="" text="" class=""]
	 *
	 * Renders the Workshop's optional Header Pill Text as a compact, primary
	 * colour label. The text attribute provides a one-off template override;
	 * otherwise the Workshop setting is used, with the Workshop title as a
	 * fallback.
	 */
	public function sc_workshop_header_pill( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'text' => '', 'class' => '' ), $atts, 'ggm_workshop_header_pill' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if ( ! $workshop_id ) {
			return '';
		}

		$text = sanitize_text_field( $atts['text'] );
		if ( '' === $text ) {
			$text = trim( (string) get_post_meta( $workshop_id, 'ggm_workshop_header_pill_text', true ) );
		}
		if ( '' === $text ) {
			$text = get_the_title( $workshop_id );
		}
		if ( '' === $text ) {
			return '';
		}

		$extra_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) );
		$class         = trim( 'ggm-workshop-header-pill ' . implode( ' ', $extra_classes ) );

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * [ggm_workshop_join_now id="" text="Join Now" class=""]
	 *
	 * A "Join Now" pill CTA. For a guest it links straight to this
	 * workshop's checkout page, which first walks them through phone-OTP
	 * login + complete-profile and brings them straight back. For an
	 * already logged-in visitor it skips the checkout page entirely: it
	 * opens the real Razorpay payment popup right on this page, and on
	 * success redirects straight to the dashboard, which then shows this
	 * workshop as purchased.
	 */
	public function sc_workshop_join_now( $atts ) {
		$atts        = shortcode_atts( array(
			'id'    => 0,
			'text'  => __( 'Join Now', 'ggm-member-dashboard' ),
			'class' => '',
		), $atts, 'ggm_workshop_join_now' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if ( ! $workshop_id ) {
			return '';
		}

		// A contribution workshop must first collect an explicit configured
		// option. When both shortcodes are present in an Elementor template,
		// only the contribution selector/CTA should render.
		if ( class_exists( 'GGM_Workshop' ) && GGM_Workshop::is_contribution( $workshop_id ) ) {
			return '';
		}

		$this->print_join_now_styles();

		$class = 'ggm-ws-join-now' . ( $atts['class'] ? ' ' . sanitize_html_class( $atts['class'] ) : '' );

		if ( ! is_user_logged_in() ) {
			$checkout_page_url = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );
			$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
				? GGM_Currency::selected_currency()
				: ggm_get_setting( 'ggm_currency', 'INR' );
			$url               = add_query_arg( array( 'workshop_id' => $workshop_id, 'ggm_currency' => $selected_currency ), $checkout_page_url );

			return '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $atts['text'] ) . '</a>';
		}

		return $this->render_quick_buy_button( $workshop_id, 0, $atts['text'], $class );
	}

	/**
	 * Render a "Join Now"/"Purchase Now"-style button for an already
	 * logged-in visitor that opens the real Razorpay popup directly on this
	 * page (no checkout-page redirect), then sends them to the dashboard on
	 * success. Shared by every workshop-page shortcode that needs this.
	 *
	 * A live nonce + this visitor's own contact details are fetched via
	 * ggm_get_checkout_nonce right when clicked rather than baked into the
	 * page's HTML, since workshop pages (unlike the dashboard) are commonly
	 * full-page cached.
	 *
	 * @param int    $workshop_id
	 * @param int    $course_id
	 * @param string $label
	 * @param string $class
	 * @return string
	 */
	private function render_quick_buy_button( $workshop_id, $course_id, $label, $class ) {
		$uid           = wp_unique_id( 'ggm-ws-quickbuy-' );
		$dashboard_url = get_permalink( (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 ) ) ?: home_url( '/dashboard/' );
		$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );

		ob_start();
		?>
		<button type="button" id="<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		<script>
		(function () {
			var btn = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if (!btn) return;
			var originalText = btn.textContent;
			var ajaxUrl       = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var workshopId    = <?php echo (int) $workshop_id; ?>;
			var courseId      = <?php echo (int) $course_id; ?>;
			var dashboardUrl  = <?php echo wp_json_encode( $dashboard_url ); ?>;
			var currencyCode  = window.ggmSelectedCurrency || <?php echo wp_json_encode( $selected_currency ); ?>;
			var genericError  = <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'ggm-member-dashboard' ) ); ?>;

			function post(action, data) {
				var params = new URLSearchParams(data || {});
				params.append('action', action);
				return fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: params.toString()
				}).then(function (r) { return r.json(); });
			}

			function fail(message) {
				window.alert(message || genericError);
				btn.disabled = false;
				btn.textContent = originalText;
			}

			function openRazorpay(order, contact) {
				if (typeof Razorpay === 'undefined') {
					fail(<?php echo wp_json_encode( __( 'Payment gateway failed to load. Please refresh the page and try again.', 'ggm-member-dashboard' ) ); ?>);
					return;
				}
				var rzp = new Razorpay({
					key: order.key_id,
					amount: order.amount,
					currency: order.currency,
					order_id: order.order_id,
					description: <?php echo wp_json_encode( __( 'Workshop/Course Purchase', 'ggm-member-dashboard' ) ); ?>,
					prefill: {
						name: contact.name || '',
						email: contact.email || '',
						contact: contact.phone || ''
					},
					modal: {
						ondismiss: function () {
							btn.disabled = false;
							btn.textContent = originalText;
						}
					},
					handler: function (response) {
						btn.textContent = <?php echo wp_json_encode( __( 'Verifying…', 'ggm-member-dashboard' ) ); ?>;
						post('ggm_verify_payment', {
							nonce: order.nonce,
							razorpay_order_id: response.razorpay_order_id,
							razorpay_payment_id: response.razorpay_payment_id,
							razorpay_signature: response.razorpay_signature,
							payment_id: order.payment_id
						}).then(function (res) {
							if (res.success) {
								window.location.href = res.data.redirect;
							} else {
								fail(res.data && res.data.message);
							}
						}).catch(function () { fail(); });
					}
				});
				rzp.on('payment.failed', function () {
					fail(<?php echo wp_json_encode( __( 'Payment failed. Please try again.', 'ggm-member-dashboard' ) ); ?>);
				});
				rzp.open();
			}

			btn.addEventListener('click', function () {
				btn.disabled = true;
				btn.textContent = <?php echo wp_json_encode( __( 'Processing…', 'ggm-member-dashboard' ) ); ?>;

				post('ggm_get_checkout_nonce', { ggm_currency: currencyCode }).then(function (nonceRes) {
					if (!nonceRes.success) {
						fail();
						return;
					}
					var nonce   = nonceRes.data.nonce;
					var contact = nonceRes.data.contact || {};

					post('ggm_create_order', {
						nonce: nonce,
						workshop_id: workshopId,
						course_id: courseId,
						ggm_currency: currencyCode
					}).then(function (res) {
						if (!res.success) {
							fail(res.data && res.data.message);
							return;
						}
						if (res.data.free) {
							window.location.href = res.data.redirect || dashboardUrl;
							return;
						}
						res.data.nonce = nonce;
						openRazorpay(res.data, contact);
					}).catch(function () { fail(); });
				}).catch(function () { fail(); });
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Print the "Join Now" pill button CSS once per page load.
	 */
	private function print_join_now_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_join_form id="" button_text="Join Now"] — inline Name +
	 * Number + "Join Now" registration bar. On submit it redirects the
	 * visitor straight to this workshop's checkout page with the typed
	 * name/number appended as query args, so the checkout form's Name and
	 * Phone fields arrive already filled in (see checkout.php, which reads
	 * ggm_name / ggm_phone).
	 */
	public function sc_workshop_join_form( $atts ) {
		$atts        = shortcode_atts( array(
			'id'          => 0,
			'button_text' => __( 'Join Now', 'ggm-member-dashboard' ),
		), $atts, 'ggm_workshop_join_form' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if ( ! $workshop_id ) {
			return '';
		}

		$this->print_join_form_styles();

		$checkout_page_url = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );
		$selected_currency  = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$checkout_url       = add_query_arg( array( 'workshop_id' => $workshop_id, 'ggm_currency' => $selected_currency ), $checkout_page_url );
		$uid                = wp_unique_id( 'ggm-ws-joinform-' );

		ob_start();
		?>
		<form id="<?php echo esc_attr( $uid ); ?>" class="ggm-ws-join-form">
			<input type="text" class="ggm-ws-join-form__name" placeholder="<?php esc_attr_e( 'Enter Your Name', 'ggm-member-dashboard' ); ?>" required>
			<div class="ggm-global-phone-control ggm-ws-join-form__phone-control">
				<?php echo class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_picker_html( '', $uid . '-country-code', '+91' ) : '<input type="hidden" value="+91">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="tel" class="ggm-ws-join-form__phone" placeholder="<?php esc_attr_e( 'Number', 'ggm-member-dashboard' ); ?>" inputmode="tel" autocomplete="tel-national" maxlength="15" required>
			</div>
			<button type="submit" class="ggm-ws-join-form__btn"><?php echo esc_html( $atts['button_text'] ); ?></button>
			<p class="ggm-ws-join-form__error ggm-is-hidden"></p>
		</form>
		<script>
		(function () {
			var form = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if (!form) return;
			var nameInput  = form.querySelector('.ggm-ws-join-form__name');
			var phoneInput = form.querySelector('.ggm-ws-join-form__phone');
			var countryInput = form.querySelector('.ggm-shared-country-picker input[type="hidden"]');
			var error      = form.querySelector('.ggm-ws-join-form__error');
			var baseUrl    = <?php echo wp_json_encode( $checkout_url ); ?>;

			form.addEventListener('submit', function (e) {
				e.preventDefault();

				var name  = nameInput.value.trim();
				var phone = phoneInput.value.trim().replace(/\D/g, '');

				if (!name) {
					error.style.display = 'block';
					error.textContent = <?php echo wp_json_encode( __( 'Please enter your name.', 'ggm-member-dashboard' ) ); ?>;
					return;
				}
				var dialDigits = String(countryInput ? countryInput.value : '+91').replace(/\D/g, '') || '91';
				var validPhone = dialDigits === '91'
					? (/^[6-9]\d{9}$/.test(phone) || /^0[6-9]\d{9}$/.test(phone) || /^91[6-9]\d{9}$/.test(phone))
					: phone.length >= 4 && (dialDigits.length + phone.length) >= 7 && (dialDigits.length + phone.length) <= 15;
				if (!validPhone) {
					error.style.display = 'block';
					error.textContent = <?php echo wp_json_encode( __( 'Please enter a valid phone number.', 'ggm-member-dashboard' ) ); ?>;
					return;
				}
				error.style.display = 'none';

				var url = baseUrl
					+ (baseUrl.indexOf('?') > -1 ? '&' : '?')
					+ 'ggm_name=' + encodeURIComponent(name)
					+ '&ggm_phone=' + encodeURIComponent(phone)
					+ '&ggm_country_code=' + encodeURIComponent(countryInput ? countryInput.value : '+91');
				window.location.href = url;
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Print the inline "Join" form CSS once per page load.
	 */
	private function print_join_form_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_join_form_full id="" button_text="Pay Now"] — Name + Phone
	 * row followed by a full-width Email row. Contribution-priced workshops also
	 * show their server-configured amount choices and dynamic workshop copy.
	 * Pay Now creates the order and opens the real Razorpay popup right on
	 * this page — no navigation to the checkout page — using the typed
	 * contact fields and selected stable contribution option ID. A free
	 * workshop (or a free contribution option) skips Razorpay entirely and
	 * goes straight to the dashboard, same as after a successful payment.
	 * Order creation resolves the real amount again on the server; no amount
	 * supplied by the browser is trusted.
	 */
	public function sc_workshop_join_form_full( $atts ) {
		$atts        = shortcode_atts( array(
			'id'          => 0,
			'button_text' => __( 'Pay Now', 'ggm-member-dashboard' ),
			'anchor_id'   => '',
		), $atts, 'ggm_workshop_join_form_full' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if (
			! $workshop_id
			|| ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true )
		) {
			return '';
		}

		$this->print_join_form_full_styles();

		$uid                = wp_unique_id( 'ggm-ws-joinformfull-' );
		$anchor_id          = sanitize_html_class( $atts['anchor_id'] ) ?: 'ggm-workshop-join-form-full-' . $workshop_id;
		$workshop_title     = get_the_title( $workshop_id ) ?: __( 'this workshop', 'ggm-member-dashboard' );
		$is_contribution    = class_exists( 'GGM_Workshop' ) && GGM_Workshop::is_contribution( $workshop_id );
		$options            = $is_contribution ? GGM_Workshop::get_contribution_options( $workshop_id ) : array();
		$default_option     = $is_contribution ? GGM_Workshop::get_default_contribution( $workshop_id ) : null;
		$currency           = class_exists( 'GGM_Workshop' )
			? GGM_Workshop::get_currency( $workshop_id )
			: ggm_get_setting( 'ggm_currency_symbol', '₹' );
		$selected_currency  = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
			? GGM_Currency::selected_currency()
			: ggm_get_setting( 'ggm_currency', 'INR' );
		$is_fixed_free      = ! $is_contribution && class_exists( 'GGM_Workshop' ) && GGM_Workshop::resolve_price( $workshop_id ) <= 0;
		$button_text        = sanitize_text_field( (string) $atts['button_text'] );

		// Preserve custom labels, but transparently upgrade the exact legacy
		// shortcode value the site already uses.
		$is_legacy_label = '' === trim( $button_text ) || 0 === strcasecmp( trim( $button_text ), 'Submit Now' );
		if ( $is_fixed_free && ( $is_legacy_label || 0 === strcasecmp( trim( $button_text ), 'Pay Now' ) ) ) {
			$button_text = __( 'Join Now', 'ggm-member-dashboard' );
		} elseif ( $is_legacy_label ) {
			$button_text = __( 'Pay Now', 'ggm-member-dashboard' );
		}
		$default_is_free = $is_contribution && $default_option && 'free' === $default_option['type'];
		$rendered_button_text = $default_is_free
			? __( 'Join Now', 'ggm-member-dashboard' )
			: $button_text;
		$current_access_level = ( is_user_logged_in() && class_exists( 'GGM_Workshop' ) )
			? GGM_Workshop::get_user_access_level( get_current_user_id(), $workshop_id )
			: 'none';
		$has_paid_access = in_array( $current_access_level, array( 'paid' ), true );
		$is_existing_free_user = 'free' === $current_access_level;
		$is_logged_in_member = is_user_logged_in();
		$dashboard_url = $this->resolve_dashboard_url();
		$sticky_price = __( 'Free', 'ggm-member-dashboard' );
		$sticky_regular_price = '';

		// Resolve the mobile companion's initial display from the same
		// authoritative workshop/currency data used by the form. The browser may
		// mirror contribution changes later, while order creation remains the
		// final server-side authority for the amount charged.
		if ( $is_contribution && $default_option ) {
			if ( 'free' !== $default_option['type'] ) {
				$default_amount = (float) $default_option['amount'];
				$sticky_price = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
					? GGM_Currency::format_converted( $default_amount, $workshop_id, $selected_currency )
					: $currency . number_format_i18n( $default_amount, 0 );
			}
		} elseif ( class_exists( 'GGM_Workshop' ) ) {
			$resolved_price = GGM_Workshop::resolve_price( $workshop_id );
			$regular_price  = GGM_Workshop::get_regular_price( $workshop_id );
			$sale_price     = GGM_Workshop::get_sale_price( $workshop_id );

			if ( $resolved_price > 0 ) {
				$sticky_price = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
					? GGM_Currency::format_converted( $resolved_price, $workshop_id, $selected_currency )
					: $currency . number_format_i18n( $resolved_price, 0 );
			}
			if ( $sale_price > 0 && $sale_price < $regular_price ) {
				$sticky_regular_price = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
					? GGM_Currency::format_converted( $regular_price, $workshop_id, $selected_currency )
					: $currency . number_format_i18n( $regular_price, 0 );
			}
		}
		$resume_url = add_query_arg( array(
			'ggm_enroll_workshop' => $workshop_id,
			'ggm_resume_payment'  => 1,
			'ggm_currency'        => $selected_currency,
		), $dashboard_url ) . '#tab-free';
		if ( $is_logged_in_member && 'none' === $current_access_level && ! $is_contribution ) {
			$rendered_button_text = __( 'Enroll', 'ggm-member-dashboard' );
		}

		if ( $is_contribution && ! $options ) {
			return '<p id="' . esc_attr( $anchor_id ) . '" class="ggm-ws-join-form-full ggm-ws-join-form-full--unavailable" role="alert">'
				. esc_html__( 'Contribution options are currently unavailable. Please try again later.', 'ggm-member-dashboard' )
				. '</p>';
		}

		if ( $has_paid_access ) {
			$dashboard_url = $this->resolve_dashboard_url();
			$access_id = $uid . '-access';
			return '<div id="' . esc_attr( $anchor_id ) . '" class="ggm-ws-join-form-full ggm-ws-join-form-full--access">'
				. '<p>' . esc_html__( 'You already have access to this workshop.', 'ggm-member-dashboard' ) . '</p>'
				. '<a class="ggm-ws-join-form-full__btn" href="' . esc_url( $dashboard_url ) . '">' . esc_html__( 'Go to Dashboard', 'ggm-member-dashboard' ) . '</a>'
				. '</div>'
				. $this->render_mobile_join_bar( $anchor_id, $sticky_price, $sticky_regular_price, $dashboard_url, true );
		}

ob_start();
		?>
		<div id="<?php echo esc_attr( $anchor_id ); ?>">
		<form id="<?php echo esc_attr( $uid ); ?>" class="ggm-ws-join-form-full" method="get">
			<input type="hidden" name="workshop_id" value="<?php echo esc_attr( $workshop_id ); ?>">
			<input type="hidden" name="ggm_currency" value="<?php echo esc_attr( $selected_currency ); ?>">
			<div class="ggm-ws-join-form-full__row">
				<div class="ggm-ws-join-form-full__field">
					<label class="ggm-ws-join-form-full__sr-only" for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Full name', 'ggm-member-dashboard' ); ?></label>
					<div class="ggm-ws-join-form-full__input-wrap">
						<span class="ggm-ws-join-form-full__input-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 21a8 8 0 0 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path></svg></span>
						<input id="<?php echo esc_attr( $uid ); ?>-name" name="ggm_name" type="text" class="ggm-ws-join-form-full__name" placeholder="<?php esc_attr_e( 'Enter your full name', 'ggm-member-dashboard' ); ?>" autocomplete="name" required aria-describedby="<?php echo esc_attr( $uid ); ?>-name-error">
					</div>
					<div class="ggm-field-error" id="<?php echo esc_attr( $uid ); ?>-name-error" aria-live="polite"></div>
				</div>
				<div class="ggm-ws-join-form-full__field">
					<label class="ggm-ws-join-form-full__sr-only" for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'Phone number', 'ggm-member-dashboard' ); ?></label>
					<div class="ggm-global-phone-control ggm-ws-join-form-full__phone-control">
						<?php echo class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_picker_html( 'ggm_country_code', $uid . '-country-code', '+91' ) : '<input type="hidden" name="ggm_country_code" value="+91">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input id="<?php echo esc_attr( $uid ); ?>-phone" name="ggm_phone" type="tel" class="ggm-ws-join-form-full__phone" placeholder="<?php esc_attr_e( 'Enter your phone number', 'ggm-member-dashboard' ); ?>" inputmode="tel" autocomplete="tel-national" maxlength="15" required aria-describedby="<?php echo esc_attr( $uid ); ?>-phone-error">
					</div>
					<div class="ggm-field-error" id="<?php echo esc_attr( $uid ); ?>-phone-error" aria-live="polite"></div>
				</div>
			</div>
			<div class="ggm-ws-join-form-full__field">
				<label class="ggm-ws-join-form-full__sr-only" for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email address', 'ggm-member-dashboard' ); ?></label>
				<div class="ggm-ws-join-form-full__input-wrap">
					<span class="ggm-ws-join-form-full__input-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="14" rx="1"></rect><path d="m3 7 9 6 9-6"></path></svg></span>
					<input id="<?php echo esc_attr( $uid ); ?>-email" name="ggm_email" type="email" class="ggm-ws-join-form-full__email" placeholder="<?php esc_attr_e( 'Enter your email address', 'ggm-member-dashboard' ); ?>" autocomplete="email" required aria-describedby="<?php echo esc_attr( $uid ); ?>-email-error">
				</div>
				<div class="ggm-field-error" id="<?php echo esc_attr( $uid ); ?>-email-error" aria-live="polite"></div>
			</div>

			<!-- Login / OTP box — rendered here so it appears after Email and before contribution text/prices -->
			<div class="ggm-ws-join-form-full__login ggm-is-hidden" aria-live="polite">
				<p class="ggm-ws-join-form-full__login-message">
					<strong class="ggm-ws-join-form-full__login-heading"><?php esc_html_e( 'Account already registered', 'ggm-member-dashboard' ); ?></strong>
					<span class="ggm-ws-join-form-full__login-desc"><?php esc_html_e( 'Log in with OTP to continue, or choose a contribution amount below.', 'ggm-member-dashboard' ); ?></span>
				</p>
				<button type="button" class="ggm-ws-join-form-full__login-btn"><?php esc_html_e( 'Login with OTP', 'ggm-member-dashboard' ); ?></button>
				<div class="ggm-ws-join-form-full__otp ggm-is-hidden">
					<input type="text" class="ggm-ws-join-form-full__otp-input" inputmode="numeric" maxlength="6" pattern="[0-9]*" placeholder="<?php esc_attr_e( '6-digit OTP', 'ggm-member-dashboard' ); ?>">
					<button type="button" class="ggm-ws-join-form-full__otp-verify"><?php esc_html_e( 'Verify OTP', 'ggm-member-dashboard' ); ?></button>
					<div class="ggm-ws-join-form-full__otp-error ggm-is-hidden" role="alert" aria-live="polite"></div>
				</div>
			</div>

			<?php if ( $is_contribution ) : ?>
				<fieldset class="ggm-ws-join-form-full__contribution">
					<legend class="ggm-ws-join-form-full__contribution-copy">
						<strong><?php esc_html_e( 'Choose Your Contribution', 'ggm-member-dashboard' ); ?></strong>
						<span class="ggm-ws-join-form-full__contribution-desc"><?php esc_html_e( 'Select an amount you\'d like to contribute. Your support helps us continue the GGM Diabetes Free Movement.', 'ggm-member-dashboard' ); ?></span>
					</legend>
					<div class="ggm-ws-join-form-full__amounts">
						<?php foreach ( $options as $option ) :
							$is_free    = 'free' === $option['type'];
							// Server-side: hide Free option for existing free users
							if ( $is_free && $is_existing_free_user ) {
								continue;
							}
							$amount     = (float) $option['amount'];
							$decimals   = $amount === floor( $amount ) ? 0 : 2;
							$display_amount = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
								? GGM_Currency::format_converted( $amount, $workshop_id, $selected_currency )
								: $currency . number_format( $amount, $decimals, '.', ',' );
							$price_text = $is_free
								? ( $option['label'] ?: __( 'Free', 'ggm-member-dashboard' ) )
								: $display_amount;
							$is_default = $default_option && hash_equals( (string) $default_option['id'], (string) $option['id'] );
							?>
							<label class="ggm-ws-join-form-full__amount<?php echo $is_free ? ' is-free' : ''; ?>">
								<input
									type="radio"
									class="ggm-ws-join-form-full__amount-input"
									name="contribution_option_id"
									value="<?php echo esc_attr( $option['id'] ); ?>"
									data-free="<?php echo $is_free ? '1' : '0'; ?>"
									data-amount="<?php echo esc_attr( $display_amount ); ?>"
									data-raw-amount="<?php echo esc_attr( $amount ); ?>"
									data-type="<?php echo esc_attr( $option['type'] ); ?>"
									<?php checked( $is_default ); ?>
									required>
								<span class="ggm-ws-join-form-full__amount-label"><?php echo esc_html( $price_text ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endif; ?>

			<button
				type="submit"
				class="ggm-ws-join-form-full__btn"
				data-new-user-free-label="<?php esc_attr_e( 'Register for Free', 'ggm-member-dashboard' ); ?>"
				data-new-user-paid-label-prefix="<?php esc_attr_e( 'Contribute ', 'ggm-member-dashboard' ); ?>"
				data-new-user-paid-label-suffix="<?php esc_attr_e( ' & Register', 'ggm-member-dashboard' ); ?>"
				data-existing-user-paid-label-prefix="<?php esc_attr_e( 'Contribute ', 'ggm-member-dashboard' ); ?>"
				data-existing-user-paid-label-suffix=""
				data-new-user-label="<?php esc_attr_e( 'Pay Now', 'ggm-member-dashboard' ); ?>"
				data-existing-user-label="<?php esc_attr_e( 'Enroll', 'ggm-member-dashboard' ); ?>"
				data-currency-symbol="<?php echo esc_attr( $currency ); ?>">
				<?php echo esc_html( $rendered_button_text ); ?></button>
			<p class="ggm-ws-join-form-full__general-error ggm-is-hidden" role="alert" aria-live="polite"></p>
		</form>
		</div>
		<?php echo $this->render_mobile_join_bar( $uid, $sticky_price, $sticky_regular_price ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes all dynamic output. ?>
		<script>
		(function () {
			var form = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if (!form) return;
			var nameInput  = form.querySelector('.ggm-ws-join-form-full__name');
			var emailInput = form.querySelector('.ggm-ws-join-form-full__email');
			var phoneInput = form.querySelector('.ggm-ws-join-form-full__phone');
			var countryCodeInput = form.querySelector('input[name="ggm_country_code"]');
			var payButton  = form.querySelector('.ggm-ws-join-form-full__btn');
			var nameError  = form.querySelector('#' + nameInput.id + '-error');
			var emailError = form.querySelector('#' + emailInput.id + '-error');
			var phoneError = form.querySelector('#' + phoneInput.id + '-error');
			var generalError = form.querySelector('.ggm-ws-join-form-full__general-error');
			var loginBox   = form.querySelector('.ggm-ws-join-form-full__login');
			var loginMsg   = form.querySelector('.ggm-ws-join-form-full__login-message');
			var loginBtn   = form.querySelector('.ggm-ws-join-form-full__login-btn');
			var otpBox     = form.querySelector('.ggm-ws-join-form-full__otp');
			var otpInput   = form.querySelector('.ggm-ws-join-form-full__otp-input');
			var otpVerify  = form.querySelector('.ggm-ws-join-form-full__otp-verify');
			var otpError   = form.querySelector('.ggm-ws-join-form-full__otp-error');
			var contributionBox = form.querySelector('.ggm-ws-join-form-full__contribution');
			var loginIdentifier = '';
			var loginRequestId  = '';
			var loginNonce      = '';
			var loginRedirect   = '';
			var otpDeliveryComplete = false;
			var accessLookupTimer = null;
			var priceHiddenByAccess = false;
			var isExistingAccount = false;
			var currentAccessLevel = 'none';
			var isContribution = <?php echo $is_contribution ? 'true' : 'false'; ?>;
			var isLoggedInMember = <?php echo $is_logged_in_member ? 'true' : 'false'; ?>;

			// Request tracking for stale response prevention
			var accessLookupRequestId = 0;

			var ajaxUrl      = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var workshopId   = <?php echo (int) $workshop_id; ?>;
			var dashboardUrl = <?php echo wp_json_encode( $dashboard_url ); ?>;
			var enrollmentResumeUrl = <?php echo wp_json_encode( $resume_url ); ?>;
			var genericError = <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'ggm-member-dashboard' ) ); ?>;

			// Centralized button label update function
			function updateContributionButton() {
				if (!payButton) return;
				if (!isContribution) {
					payButton.textContent = (isExistingAccount || isLoggedInMember)
						? (payButton.getAttribute('data-existing-user-label') || 'Enroll')
						: (payButton.getAttribute('data-new-user-label') || 'Pay Now');
					return;
				}
				var selected = form.querySelector('input[name="contribution_option_id"]:checked');
				if (!selected) {
					// No selection - restore idle label
					payButton.textContent = payButton.getAttribute('data-idle-label') || '';
					return;
				}

				var isFree = selected.getAttribute('data-free') === '1';
				var amount = selected.getAttribute('data-amount') || '';
				var isNewUser = !isExistingAccount;

				if (isFree) {
					// Free option - only available for new users
					if (isNewUser) {
						payButton.textContent = payButton.getAttribute('data-new-user-free-label') || 'Register for Free';
					}
				} else {
					// Paid contribution option
					var prefix = isNewUser
						? (payButton.getAttribute('data-new-user-paid-label-prefix') || 'Contribute ')
						: (payButton.getAttribute('data-existing-user-paid-label-prefix') || 'Contribute ');
					var suffix = isNewUser
						? (payButton.getAttribute('data-new-user-paid-label-suffix') || ' & Register')
						: (payButton.getAttribute('data-existing-user-paid-label-suffix') || '');
					payButton.textContent = prefix + amount + suffix;
				}
			}

			// Initialize idle label from button's initial text
			if (payButton) {
				payButton.setAttribute('data-idle-label', payButton.textContent.trim());
			}

			<?php if ( $is_contribution ) : ?>
			// Update button when contribution selection changes
			form.addEventListener('change', function (event) {
				if (event.target.matches('input[name="contribution_option_id"]')) {
					updateContributionButton();
				}
			});
			// Set initial button label
			updateContributionButton();
			<?php endif; ?>

			function post(action, data) {
				var params = new URLSearchParams(data || {});
				params.append('action', action);
				return fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: params.toString()
				}).then(function (r) { return r.json(); });
			}

			// Field-specific error handling
			function showFieldError(input, errorEl, message) {
				if (!input || !errorEl) return;
				errorEl.textContent = message || genericError;
				input.setAttribute('aria-invalid', 'true');
			}

			function clearFieldError(input, errorEl) {
				if (!input || !errorEl) return;
				errorEl.textContent = '';
				input.setAttribute('aria-invalid', 'false');
			}

			function clearAllFieldErrors() {
				clearFieldError(nameInput, nameError);
				clearFieldError(emailInput, emailError);
				clearFieldError(phoneInput, phoneError);
			}

			// General error (for non-field-specific errors like payment failures)
			function showGeneralError(message) {
				if (!generalError) return;
				generalError.textContent = message || genericError;
				generalError.style.display = 'block';
			}

			function hideGeneralError() {
				if (!generalError) return;
				generalError.textContent = '';
				generalError.style.display = 'none';
			}

			// OTP error (for OTP verification errors)
			function showOtpError(message) {
				if (!otpError) return;
				otpError.textContent = message || genericError;
				otpError.style.display = 'block';
			}

			function hideOtpError() {
				if (!otpError) return;
				otpError.textContent = '';
				otpError.style.display = 'none';
			}

			// Live validation - clear error when field becomes valid
			function setupLiveValidation() {
				// Name field
				if (nameInput && nameError) {
					nameInput.addEventListener('input', function () {
						if (nameInput.value.trim()) {
							clearFieldError(nameInput, nameError);
						}
					});
					nameInput.addEventListener('blur', function () {
						if (nameInput.value.trim()) {
							clearFieldError(nameInput, nameError);
						}
					});
				}

				// Email field
				if (emailInput && emailError) {
					emailInput.addEventListener('input', function () {
						var email = emailInput.value.trim();
						if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
							clearFieldError(emailInput, emailError);
						}
					});
					emailInput.addEventListener('blur', function () {
						var email = emailInput.value.trim();
						if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
							clearFieldError(emailInput, emailError);
						}
					});
				}

				// Phone field
				if (phoneInput && phoneError) {
					phoneInput.addEventListener('input', function () {
						var phone = phoneInput.value.trim().replace(/\D/g, '');
						if (isValidPhone(phone)) {
							clearFieldError(phoneInput, phoneError);
						}
					});
					phoneInput.addEventListener('blur', function () {
						var phone = phoneInput.value.trim().replace(/\D/g, '');
						if (isValidPhone(phone)) {
							clearFieldError(phoneInput, phoneError);
						}
					});
				}
			}

			setupLiveValidation();

			function hideLoginPrompt() {
				if (!loginBox) return;
				loginBox.style.display = 'none';
				if (loginBtn) loginBtn.disabled = false;
				if (otpVerify) otpVerify.disabled = false;
				isExistingAccount = false;
				currentAccessLevel = 'none';
				// Re-show Free option if it was hidden
				if (contributionBox) {
					var freeOption = form.querySelector('input[name="contribution_option_id"][data-free="1"]');
					if (freeOption) {
						freeOption.closest('.ggm-ws-join-form-full__amount').style.display = '';
					}
				}
				updateContributionButton();
			}

			function restorePricingControls() {
				if (!priceHiddenByAccess) return;
				priceHiddenByAccess = false;
				if (contributionBox) {
					contributionBox.style.display = '';
					form.querySelectorAll('input[name="contribution_option_id"]').forEach(function (input) {
						input.required = true;
					});
				}
				if (payButton) {
					payButton.style.display = '';
					payButton.disabled = false;
				}
				updateContributionButton();
			}

			function hidePricingForOtpLogin() {
				if (contributionBox) {
					contributionBox.style.display = 'none';
					form.querySelectorAll('input[name="contribution_option_id"]').forEach(function (input) {
						input.required = false;
					});
				}
				if (payButton) {
					payButton.style.display = 'none';
				}
				priceHiddenByAccess = true;
			}

			function setFreeOptionVisibility(available) {
				if (!contributionBox) return;
				var freeOption = form.querySelector('input[name="contribution_option_id"][data-free="1"]');
				if (freeOption) {
					var label = freeOption.closest('.ggm-ws-join-form-full__amount');
					if (label) {
						label.style.display = available ? '' : 'none';
					}
					// If free option was selected and is now hidden, clear selection
					if (!available && freeOption.checked) {
						freeOption.checked = false;
						updateContributionButton();
					}
				}
			}

			function showLoginPrompt(data, contact, nonce) {
				if (otpDeliveryComplete) return;
				data = data || {};
				loginIdentifier = data.identifier || contact.email || '';
				loginNonce = nonce || loginNonce;
				loginRequestId = '';
				loginRedirect = enrollmentResumeUrl;
				isExistingAccount = true;
				currentAccessLevel = data.access_level || 'none';

				if (data.hide_pricing) {
					hidePricingForOtpLogin();
				} else if (payButton) {
					payButton.style.display = '';
					payButton.disabled = false;
				}

				// Hide Free option whenever an existing account is detected (login_required)
				if (data.login_required) {
					setFreeOptionVisibility(false);
				}

				clearAllFieldErrors();
				loginBox.style.display = 'block';

				// Preserve semantic HTML structure: set heading and description separately
				var loginHeading = form.querySelector('.ggm-ws-join-form-full__login-heading');
				var loginDesc = form.querySelector('.ggm-ws-join-form-full__login-desc');
				if (loginHeading) {
					loginHeading.textContent = data.hide_pricing
						? <?php echo wp_json_encode( __( 'Workshop already enrolled', 'ggm-member-dashboard' ) ); ?>
						: <?php echo wp_json_encode( __( 'Existing member account found', 'ggm-member-dashboard' ) ); ?>;
				}
				if (loginDesc) {
					loginDesc.textContent = data.hide_pricing
						? <?php echo wp_json_encode( __( 'Log in with OTP to access this workshop. No payment is required.', 'ggm-member-dashboard' ) ); ?>
						: <?php echo wp_json_encode( __( 'Log in with OTP. The payment window will open automatically on your dashboard.', 'ggm-member-dashboard' ) ); ?>;
				}

				if (otpBox) otpBox.style.display = 'none';
				if (otpInput) otpInput.value = '';
				if (loginBtn) {
					loginBtn.disabled = false;
					loginBtn.textContent = <?php echo wp_json_encode( __( 'Login with OTP', 'ggm-member-dashboard' ) ); ?>;
					loginBtn.style.display = '';
				}
				updateContributionButton();
			}

			// Phone input: allow only digits
			phoneInput.addEventListener('input', function () {
				this.value = this.value.replace(/\D/g, '');
			});

			// Email validation helper
			function isValidEmail(email) {
				return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
			}

			// Phone validation helper
			function isValidPhone(phone) {
				var dialDigits = String(countryCodeInput ? countryCodeInput.value : '+91').replace(/\D/g, '') || '91';
				if (dialDigits === '91') {
					return /^[6-9]\d{9}$/.test(phone) || /^0[6-9]\d{9}$/.test(phone) || /^91[6-9]\d{9}$/.test(phone);
				}
				return phone.length >= 4 && (dialDigits.length + phone.length) >= 7 && (dialDigits.length + phone.length) <= 15;
			}

			// Validate both phone and email for OTP eligibility (universal requirement)
			function validateOtpContactFields() {
				var phone = phoneInput.value.trim().replace(/\D/g, '');
				var email = emailInput.value.trim();
				var hasError = false;

				clearFieldError(phoneInput, phoneError);
				clearFieldError(emailInput, emailError);

				if (!phone) {
					showFieldError(phoneInput, phoneError, <?php echo wp_json_encode( __( 'Please enter your phone number.', 'ggm-member-dashboard' ) ); ?>);
					hasError = true;
				} else if (!isValidPhone(phone)) {
					showFieldError(phoneInput, phoneError, <?php echo wp_json_encode( __( 'Please enter a valid phone number.', 'ggm-member-dashboard' ) ); ?>);
					hasError = true;
				}

				if (!email) {
					showFieldError(emailInput, emailError, <?php echo wp_json_encode( __( 'Please enter your email address.', 'ggm-member-dashboard' ) ); ?>);
					hasError = true;
				} else if (!isValidEmail(email)) {
					showFieldError(emailInput, emailError, <?php echo wp_json_encode( __( 'Please enter a valid email address.', 'ggm-member-dashboard' ) ); ?>);
					hasError = true;
				}

				if (hasError) {
					// Focus first invalid field
					if (!phone || !isValidPhone(phone)) {
						phoneInput.focus();
					} else if (!email || !isValidEmail(email)) {
						emailInput.focus();
					}
					return false;
				}
				return true;
			}

			// Existing-account lookup for this shortcode is intentionally based on
			// the digits typed in the phone field only. The country picker is contact
			// metadata and must never participate in account matching.
			function canPerformLookup() {
				var phone = phoneInput.value.trim().replace(/\D/g, '');
				return isValidPhone(phone);
			}

			// Get current lookup values
			function getLookupValues() {
				return {
					phone: phoneInput.value.trim().replace(/\D/g, ''),
					email: emailInput.value.trim().toLowerCase()
				};
			}

			// Main access check function supporting all validation methods
			function checkWorkshopContactAccess() {
				if (otpDeliveryComplete) return;
				// Don't run if we don't have enough valid input for the configured method
				if (!canPerformLookup()) {
					hideLoginPrompt();
					restorePricingControls();
					return;
				}

				var lookupValues = getLookupValues();
				var currentRequestId = ++accessLookupRequestId;

				// Fetch a fresh nonce first
				post('ggm_get_checkout_nonce', { ggm_currency: window.ggmSelectedCurrency || <?php echo wp_json_encode( $selected_currency ); ?> }).then(function (nonceRes) {
					// Ignore stale requests
					if (currentRequestId !== accessLookupRequestId) return;
					if (!nonceRes || !nonceRes.success) return;

					var nonce = nonceRes.data.nonce;

					// Send only the national-number field for account lookup. The selected
					// country code is deliberately excluded from this request.
					post('ggm_check_workshop_contact_access', {
						nonce: nonce,
						workshop_id: workshopId,
						phone: lookupValues.phone,
						phone_only: '1'
					}).then(function (res) {
						// Ignore stale responses
						if (currentRequestId !== accessLookupRequestId) return;
						if (otpDeliveryComplete) return;
						if (!res || !res.success || !res.data) return;

						// This lookup is phone-only. Do not discard a valid phone match
						// merely because mobile autofill updated the email concurrently.
						var currentValues = getLookupValues();
						if (currentValues.phone !== lookupValues.phone) {
							return;
						}

						if (res.data.login_required) {
							showLoginPrompt(res.data, { phone: lookupValues.phone, email: lookupValues.email }, nonce);
						} else {
							hideLoginPrompt();
							restorePricingControls();
						}
					}).catch(function () {});
				}).catch(function () {});
			}

			// Debounced scheduler
			function scheduleAccessLookup() {
				clearTimeout(accessLookupTimer);
				accessLookupTimer = setTimeout(checkWorkshopContactAccess, 350);
			}

			function resetButton() {
				payButton.disabled = false;
				updateContributionButton();
			}

			// ... rest of the existing code (openRazorpay, submitPayment, form submit, OTP handlers)

			// Pay Now opens the real Razorpay popup right here — no navigation
			// to the checkout page. A free workshop (or a free contribution
			// option) resolves with no Razorpay step at all: ggm_create_order
			// itself detects a final amount of 0 and returns `free: true`, so
			// the same code path already lands directly on the dashboard.
			function openRazorpay(order, nonce, contact) {
				if (typeof Razorpay === 'undefined') {
					showGeneralError(<?php echo wp_json_encode( __( 'Payment gateway failed to load. Please refresh the page and try again.', 'ggm-member-dashboard' ) ); ?>);
					resetButton();
					return;
				}
				var rzp = new Razorpay({
					key:         order.key_id,
					amount:      order.amount,
					currency:    order.currency,
					order_id:    order.order_id,
					name:        <?php echo wp_json_encode( get_bloginfo( 'name' ) ); ?>,
					description: <?php echo wp_json_encode( __( 'Workshop Purchase', 'ggm-member-dashboard' ) ); ?>,
					prefill: {
						name:    contact.name,
						email:   contact.email,
						contact: contact.phone
					},
					modal: { ondismiss: resetButton },
					handler: function (response) {
						payButton.textContent = <?php echo wp_json_encode( __( 'Verifying…', 'ggm-member-dashboard' ) ); ?>;
						post('ggm_verify_payment', {
							nonce:               nonce,
							razorpay_order_id:   response.razorpay_order_id,
							razorpay_payment_id: response.razorpay_payment_id,
							razorpay_signature:  response.razorpay_signature,
							payment_id:          order.payment_id,
							uid:                 order.uid || 0,
							auth_token:          order.auth_token || ''
						}).then(function (res) {
							if (res.success) {
								window.location.href = res.data.redirect || dashboardUrl;
							} else {
								showGeneralError(res.data && res.data.message);
								resetButton();
							}
						}).catch(function () { showGeneralError(); resetButton(); });
					}
				});
				rzp.on('payment.failed', function () {
					showGeneralError(<?php echo wp_json_encode( __( 'Payment failed. Please try again.', 'ggm-member-dashboard' ) ); ?>);
					resetButton();
				});
				rzp.open();
			}

			function submitPayment(contact) {
				payButton.disabled = true;
				payButton.textContent = <?php echo wp_json_encode( __( 'Processing…', 'ggm-member-dashboard' ) ); ?>;
				hideGeneralError();

				// A fresh nonce, fetched live rather than baked into this
				// (commonly page-cached) markup — see ajax_get_checkout_nonce().
				post('ggm_get_checkout_nonce', { ggm_currency: window.ggmSelectedCurrency || <?php echo wp_json_encode( $selected_currency ); ?> }).then(function (nonceRes) {
					if (!nonceRes || !nonceRes.success) { showGeneralError(); resetButton(); return; }
					var nonce = nonceRes.data.nonce;

					post('ggm_create_order', {
						nonce:                  nonce,
						workshop_id:            workshopId,
						contact_name:           contact.name,
						contact_phone:          contact.phone,
						contact_email:          contact.email,
						contact_country_code:   contact.countryCode || '+91',
						contribution_option_id: contact.contributionOptionId || '',
						ggm_currency:           window.ggmSelectedCurrency || <?php echo wp_json_encode( $selected_currency ); ?>
					}).then(function (res) {
						if (!res.success) {
							if (res.data && res.data.login_required) {
								showLoginPrompt(res.data, contact, nonce);
								resetButton();
								return;
							}
							showGeneralError(res.data && res.data.message);
							resetButton();
							return;
						}
						if (res.data.free) {
							window.location.href = res.data.redirect || dashboardUrl;
							return;
						}
						openRazorpay(res.data, nonce, contact);
					}).catch(function () { showGeneralError(); resetButton(); });
				}).catch(function () { showGeneralError(); resetButton(); });
			}

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var name  = nameInput.value.trim();
				var email = emailInput.value.trim();
				var phone = phoneInput.value.trim().replace(/\D/g, '');

				clearAllFieldErrors();

				var hasError = false;

				if (!name) {
					showFieldError(nameInput, nameError, <?php echo wp_json_encode( __( 'Please enter your name.', 'ggm-member-dashboard' ) ); ?>);
					hasError = true;
				}
				if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
					showFieldError(emailInput, emailError, <?php echo wp_json_encode( __( 'Please enter a valid email address.', 'ggm-member-dashboard' ) ); ?>);
					hasError = true;
				}
				if (!isValidPhone(phone)) {
					showFieldError(phoneInput, phoneError, <?php echo wp_json_encode( __( 'Please enter a valid phone number.', 'ggm-member-dashboard' ) ); ?>);
					hasError = true;
				}

				if (hasError) {
					// Focus first invalid field
					if (!name) {
						nameInput.focus();
					} else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
						emailInput.focus();
					} else if (!isValidPhone(phone)) {
						phoneInput.focus();
					}
					return;
				}

				if (!priceHiddenByAccess) {
					hideLoginPrompt();
				}
				var contributionOptionId = '';
				<?php if ( $is_contribution ) : ?>
				var selectedOption = form.querySelector('input[name="contribution_option_id"]:checked');
				if (!priceHiddenByAccess && !selectedOption) {
					// For contribution selection, we don't have a field-specific error element
					// Use a generic approach or show near the contribution options
					showFieldError(phoneInput, phoneError, <?php echo wp_json_encode( __( 'Please select a contribution amount.', 'ggm-member-dashboard' ) ); ?>);
					var firstOption = form.querySelector('input[name="contribution_option_id"]');
					if (firstOption) firstOption.focus();
					return;
				}
				contributionOptionId = selectedOption ? selectedOption.value : '';
				<?php endif; ?>
				nameInput.value = name;
				emailInput.value = email;
				phoneInput.value = phone;

				if (priceHiddenByAccess) {
					if (loginBtn) {
						loginBtn.focus();
					}
					return;
				}

				submitPayment({ name: name, email: email, phone: phone, countryCode: countryCodeInput ? countryCodeInput.value : '+91', contributionOptionId: contributionOptionId });
			});

			function resetOtpDeliveryState() {
				if (!otpDeliveryComplete) return;
				otpDeliveryComplete = false;
				loginRequestId = '';
				if (otpBox) otpBox.style.display = 'none';
				if (otpInput) otpInput.value = '';
				if (loginBtn) {
					loginBtn.style.display = '';
					loginBtn.disabled = false;
					loginBtn.textContent = <?php echo wp_json_encode( __( 'Login with OTP', 'ggm-member-dashboard' ) ); ?>;
				}
			}

			function handleContactInput() {
				resetOtpDeliveryState();
				scheduleAccessLookup();
			}

			phoneInput.addEventListener('input', handleContactInput);
			phoneInput.addEventListener('change', checkWorkshopContactAccess);
			phoneInput.addEventListener('paste', function () {
				window.setTimeout(checkWorkshopContactAccess, 0);
			});
			phoneInput.addEventListener('blur', checkWorkshopContactAccess);
			emailInput.addEventListener('input', handleContactInput);
			emailInput.addEventListener('blur', checkWorkshopContactAccess);

			// Mobile browsers and password/contact managers can restore or autofill
			// a tel value without emitting a normal input event.
			window.setTimeout(checkWorkshopContactAccess, 0);
			window.addEventListener('pageshow', checkWorkshopContactAccess);

			if (loginBtn) {
				loginBtn.addEventListener('click', function () {
					// Validate both phone and email before sending OTP (universal requirement)
					if (!validateOtpContactFields()) {
						return;
					}

					loginBtn.disabled = true;
					loginBtn.textContent = <?php echo wp_json_encode( __( 'Sending OTP…', 'ggm-member-dashboard' ) ); ?>;
					hidePricingForOtpLogin();

					var currentPhone = phoneInput.value.trim().replace(/\D/g, '');
					var currentEmail = emailInput.value.trim().toLowerCase();

					post('ggm_send_otp', {
						nonce: loginNonce,
						identifier: loginIdentifier,
						phone: currentPhone,
						email: currentEmail,
						country_code: countryCodeInput ? countryCodeInput.value : '+91',
						source: 'workshop_join_form_full'
					}).then(function (res) {
						if (!res.success) {
							loginBtn.disabled = false;
							loginBtn.textContent = <?php echo wp_json_encode( __( 'Login with OTP', 'ggm-member-dashboard' ) ); ?>;
							var sendError = res.data && res.data.message
								? res.data.message
								: <?php echo wp_json_encode( __( 'Unable to send OTP. Please verify your registered account details and try again.', 'ggm-member-dashboard' ) ); ?>;
							if (res.data && res.data.field === 'phone') {
								showFieldError(phoneInput, phoneError, sendError);
							} else if (res.data && res.data.field === 'email') {
								showFieldError(emailInput, emailError, sendError);
							} else {
								showGeneralError(sendError);
							}
							return;
						}
						otpDeliveryComplete = true;
						clearTimeout(accessLookupTimer);
						accessLookupRequestId++;
						loginBtn.style.display = 'none';
						loginRequestId = res.data && res.data.request_id ? res.data.request_id : '';
						// Verify with the exact account identifier bound by the server.
						if (res.data && res.data.identifier) {
							loginIdentifier = res.data.identifier;
						}
						var loginHeading = form.querySelector('.ggm-ws-join-form-full__login-heading');
						var loginDesc = form.querySelector('.ggm-ws-join-form-full__login-desc');
						if (loginHeading) {
							loginHeading.textContent = <?php echo wp_json_encode( __( 'Account already registered', 'ggm-member-dashboard' ) ); ?>;
						}
						if (loginDesc) {
							var maskedDestination = res.data && res.data.masked ? res.data.masked : '';
							loginDesc.textContent = maskedDestination
								? <?php echo wp_json_encode( __( 'OTP sent to ', 'ggm-member-dashboard' ) ); ?> + maskedDestination + <?php echo wp_json_encode( __( '. Enter the 6-digit code to login.', 'ggm-member-dashboard' ) ); ?>
								: <?php echo wp_json_encode( __( 'OTP sent by email. Enter the 6-digit code to login.', 'ggm-member-dashboard' ) ); ?>;
						}
						if (otpBox) otpBox.style.display = 'flex';
						if (otpInput) {
							otpInput.value = '';
							otpInput.focus();
						}
					}).catch(function () {
						loginBtn.disabled = false;
						loginBtn.textContent = <?php echo wp_json_encode( __( 'Login with OTP', 'ggm-member-dashboard' ) ); ?>;
						showGeneralError( <?php echo wp_json_encode( __( 'Unable to send OTP. Please check your connection and try again.', 'ggm-member-dashboard' ) ); ?> );
					});
				});
			}

			function verifyLoginOtp() {
				if (otpVerify && otpVerify.disabled) {
					return;
				}
				var code = otpInput ? otpInput.value.trim() : '';
				if (!/^\d{6}$/.test(code) || !loginRequestId) {
					showOtpError(<?php echo wp_json_encode( __( 'Please enter the 6-digit OTP.', 'ggm-member-dashboard' ) ); ?>);
					return;
				}
				hideOtpError();
				otpVerify.disabled = true;
				otpVerify.textContent = <?php echo wp_json_encode( __( 'Verifying…', 'ggm-member-dashboard' ) ); ?>;
				var selectedContribution = form.querySelector('input[name="contribution_option_id"]:checked');
				var redirectUrl = new URL(enrollmentResumeUrl, window.location.href);
				if (selectedContribution && selectedContribution.value) {
					redirectUrl.searchParams.set('ggm_contribution_option', selectedContribution.value);
				}
				loginRedirect = redirectUrl.toString();
				post('ggm_verify_otp', {
					nonce: loginNonce,
					identifier: loginIdentifier,
					otp: code,
					request_id: loginRequestId,
					redirect_to: loginRedirect
				}).then(function (res) {
					if (res && res.success && res.data && res.data.authenticated) {
						window.location.assign(res.data.redirect || loginRedirect);
						return;
					}
					otpVerify.disabled = false;
					otpVerify.textContent = <?php echo wp_json_encode( __( 'Verify OTP', 'ggm-member-dashboard' ) ); ?>;
					showOtpError(res.data && res.data.message);
				}).catch(function () {
					otpVerify.disabled = false;
					otpVerify.textContent = <?php echo wp_json_encode( __( 'Verify OTP', 'ggm-member-dashboard' ) ); ?>;
					showOtpError();
				});
			}

			if (otpVerify) {
				otpVerify.addEventListener('click', verifyLoginOtp);
			}
			if (otpInput) {
				otpInput.addEventListener('keyup', function () {
					otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
					if (otpInput.value.length === 6) verifyLoginOtp();
				});
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the phone-only sticky price/action companion for a full join form.
	 *
	 * @param string $target_id           Matching form/access panel ID.
	 * @param string $price               Current formatted price.
	 * @param string $regular_price       Optional struck-through regular price.
	 * @param string $action_url          Dashboard URL for existing access.
	 * @param bool   $has_existing_access Whether the action opens the dashboard.
	 * @return string
	 */
	private function render_mobile_join_bar( $target_id, $price, $regular_price = '', $action_url = '', $has_existing_access = false ) {
		$bar_id = wp_unique_id( 'ggm-mobile-workshop-join-' );
		ob_start();
		?>
		<div
			id="<?php echo esc_attr( $bar_id ); ?>"
			class="ggm-mobile-workshop-join"
			data-ggm-mobile-join-target="<?php echo esc_attr( $target_id ); ?>"
			aria-label="<?php esc_attr_e( 'Workshop enrollment', 'ggm-member-dashboard' ); ?>">
			<div class="ggm-mobile-workshop-join__price" aria-live="polite">
				<strong><?php echo esc_html( $price ); ?></strong>
				<del<?php echo '' === $regular_price ? ' hidden' : ''; ?>><?php echo esc_html( $regular_price ); ?></del>
			</div>
			<?php if ( $has_existing_access ) : ?>
				<a class="ggm-mobile-workshop-join__cta" href="<?php echo esc_url( $action_url ); ?>"><?php esc_html_e( 'Go to Dashboard', 'ggm-member-dashboard' ); ?></a>
			<?php else : ?>
				<button type="button" class="ggm-mobile-workshop-join__cta" aria-controls="<?php echo esc_attr( $target_id ); ?>"><?php esc_html_e( 'Join Now', 'ggm-member-dashboard' ); ?></button>
			<?php endif; ?>
		</div>
		<script>
		(function () {
			var bar = document.getElementById(<?php echo wp_json_encode( $bar_id ); ?>);
			var target = document.getElementById(<?php echo wp_json_encode( $target_id ); ?>);
			if (!bar || !target) return;

			/* Elementor and some themes apply transform/filter to section wrappers.
			   Those properties turn a fixed child into a section-positioned child.
			   Portal the bar to body so its containing block is always the viewport. */
			if (bar.parentNode !== document.body) document.body.appendChild(bar);

			/* A malformed/page-builder layout with multiple full forms must never
			   stack several fixed bars over one another. */
			if (window.ggmMobileWorkshopJoinClaimed) {
				bar.classList.add('is-suppressed');
				return;
			}
			window.ggmMobileWorkshopJoinClaimed = true;

			var mobileQuery = window.matchMedia('(max-width: 600px)');
			var targetVisible = false;
			var keyboardOpen = false;
			var price = bar.querySelector('.ggm-mobile-workshop-join__price strong');
			var regular = bar.querySelector('.ggm-mobile-workshop-join__price del');
			var scrollButton = bar.querySelector('button.ggm-mobile-workshop-join__cta');

			function syncState() {
				var active = mobileQuery.matches && !targetVisible && !keyboardOpen;
				bar.classList.toggle('is-active', active);
				document.body.classList.toggle('ggm-mobile-workshop-join-active', active);
				if (active) {
					document.documentElement.style.setProperty('--ggm-mobile-workshop-join-space', (bar.offsetHeight + 54) + 'px');
				} else {
					document.documentElement.style.removeProperty('--ggm-mobile-workshop-join-space');
				}
			}

			function measureTarget() {
				var rect = target.getBoundingClientRect();
				var viewport = window.innerHeight || document.documentElement.clientHeight;
				targetVisible = rect.bottom > Math.min(120, viewport * 0.2) && rect.top < viewport * 0.82;
				syncState();
			}

			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (entries) {
					targetVisible = !!(entries[0] && entries[0].isIntersecting);
					syncState();
				}, { threshold: 0.12 }).observe(target);
			} else {
				window.addEventListener('scroll', measureTarget, { passive: true });
			}

			if (scrollButton) {
				scrollButton.addEventListener('click', function () {
					var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
					var targetTop = Math.max(0, target.getBoundingClientRect().top + window.pageYOffset - 250);
					window.scrollTo({ top: targetTop, behavior: reduceMotion ? 'auto' : 'smooth' });
					if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
					window.setTimeout(function () {
						try { target.focus({ preventScroll: true }); } catch (error) { /* Keep the requested 250px scroll offset. */ }
					}, reduceMotion ? 0 : 450);
				});
			}

			target.addEventListener('change', function (event) {
				if (!price || !event.target.matches('input[name="contribution_option_id"]')) return;
				price.textContent = event.target.getAttribute('data-free') === '1'
					? <?php echo wp_json_encode( __( 'Free', 'ggm-member-dashboard' ) ); ?>
					: (event.target.getAttribute('data-amount') || price.textContent);
				if (regular) regular.hidden = true;
			});

			function detectKeyboard() {
				var fieldFocused = target.contains(document.activeElement) && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
				var visibleHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
				keyboardOpen = fieldFocused && (window.innerHeight - visibleHeight > 120);
				syncState();
			}

			target.addEventListener('focusin', detectKeyboard);
			target.addEventListener('focusout', function () {
				window.setTimeout(detectKeyboard, 80);
			});
			window.addEventListener('resize', measureTarget, { passive: true });
			if (window.visualViewport) window.visualViewport.addEventListener('resize', detectKeyboard, { passive: true });
			if (mobileQuery.addEventListener) mobileQuery.addEventListener('change', measureTarget);
			else mobileQuery.addListener(measureTarget);
			measureTarget();
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Print the stacked "Join" form CSS once per page load.
	 */
	private function print_join_form_full_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		
		<?php
	}

	/**
	 * [ggm_workshop_mentor id=""] — every mentor assigned to this workshop.
	 * Cards are always kept inside the same two-column grid so one mentor (or
	 * an odd final mentor) retains a single-card width instead of stretching.
	 */
	public function sc_workshop_mentor( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0 ), $atts, 'ggm_workshop_mentor' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		$mentor_ids = get_post_meta( $workshop_id, 'ggm_mentor_ids', true );
		$mentor_ids = is_array( $mentor_ids ) ? array_map( 'absint', $mentor_ids ) : array();
		if ( empty( $mentor_ids ) ) {
			// Fall back to the pre-multi-mentor single "ggm_mentor_id" field.
			$legacy_mentor_id = (int) get_post_meta( $workshop_id, 'ggm_mentor_id', true );
			if ( $legacy_mentor_id ) {
				$mentor_ids = array( $legacy_mentor_id );
			}
		}

		$cards = array();
		foreach ( $mentor_ids as $mentor_id ) {
			if ( 'ggm_mentor' !== get_post_type( $mentor_id ) ) {
				continue;
			}

			$name  = get_the_title( $mentor_id );
			$image = get_post_meta( $mentor_id, 'ggm_mentor_image', true );
			$bio   = get_post_meta( $mentor_id, 'ggm_mentor_bio', true );

			if ( ! $name && ! $bio ) {
				continue;
			}

			ob_start();
			echo '<article class="ggm-ws-mentor' . ( $image ? '' : ' ggm-ws-mentor--without-image' ) . '">';
			if ( $image ) {
				echo '<span class="ggm-ws-mentor__avatar"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async"></span>';
			}
			echo '<div class="ggm-ws-mentor__content">';
			if ( $name ) {
				echo '<h3 class="ggm-ws-mentor__name">' . esc_html( $name ) . '</h3>';
			}
			if ( $bio ) {
				echo '<div class="ggm-ws-mentor__bio">' . wp_kses_post( wpautop( $bio ) ) . '</div>';
			}
			echo '</div></article>';
			$cards[] = ob_get_clean();
		}

		if ( empty( $cards ) ) {
			return '';
		}

		return '<section class="ggm-ws-mentor-section"><div class="ggm-ws-mentors" data-mentor-count="' . esc_attr( count( $cards ) ) . '">' . implode( '', $cards ) . '</div></section>';
	}

	/**
	 * [ggm_workshop_linked_course id="" before="" after=""]
	 *
	 * Outputs the title of this workshop's Linked Course (Task 7), linked to
	 * the course's own page. Renders nothing if no course is linked.
	 */
	public function sc_workshop_linked_course( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0, 'before' => '', 'after' => '' ), $atts, 'ggm_workshop_linked_course' );
		$workshop_id = $this->resolve_workshop_id( $atts );

		if ( ! class_exists( 'GGM_Workshop' ) ) {
			return '';
		}

		$course = GGM_Workshop::get_linked_course( $workshop_id );
		if ( ! $course ) {
			return '';
		}

		return $atts['before']
			. '<a class="ggm-ws-linked-course" href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( $course->post_title ) . '</a>'
			. $atts['after'];
	}
}
