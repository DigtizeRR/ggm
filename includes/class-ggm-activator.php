<?php
/**
 * Plugin activator — runs on plugin activation.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Activator
 */
class GGM_Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		// Create database tables.
		GGM_Database::create_tables();

		// Create plugin pages if they don't exist.
		self::create_pages();

		// Set default options.
		self::set_defaults();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create required frontend pages with shortcodes.
	 *
	 * Page IDs are written into the `ggm_settings` array option — the only
	 * place `ggm_get_setting()` ever reads from — not as bare top-level
	 * options, so every consumer (checkout links, login/dashboard
	 * redirects, etc.) actually picks them up.
	 *
	 * @return void
	 */
	private static function create_pages() {
		$settings = get_option( 'ggm_settings', array() );
		$changed  = false;

		$pages = array(
			array(
				'option' => 'ggm_dashboard_page_id',
				'title'  => 'Dashboard',
				'slug'   => 'dashboard',
				'content'=> '[ggm_dashboard]',
			),
			array(
				'option' => 'ggm_login_page_id',
				'title'  => 'Workshop Login',
				'slug'   => 'workshop-login',
				'content'=> '[ggm_login]',
			),
			array(
				'option' => 'ggm_checkout_page_id',
				'title'  => 'Membership Checkout',
				'slug'   => 'membership-checkout',
				'content'=> '[ggm_checkout]',
			),
		);

		foreach ( $pages as $page ) {
			$existing = (int) ( $settings[ $page['option'] ] ?? 0 );
			if ( $existing && get_post( $existing ) ) {
				continue;
			}

			// A pre-4.x install may have left a bare legacy option behind —
			// reuse that page instead of creating a duplicate.
			$legacy = (int) get_option( $page['option'] );
			if ( $legacy && get_post( $legacy ) ) {
				$settings[ $page['option'] ] = $legacy;
				$changed                      = true;
				continue;
			}

			$page_id = wp_insert_post( array(
				'post_title'   => $page['title'],
				'post_name'    => $page['slug'],
				'post_content' => $page['content'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => 1,
			) );

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$settings[ $page['option'] ] = $page_id;
				$changed                      = true;
			}
		}

		if ( $changed ) {
			update_option( 'ggm_settings', $settings );
		}
	}

	/**
	 * Set default plugin options.
	 *
	 * Written into `ggm_settings` (merged, never overwriting a value the
	 * admin already configured) since that's the only option
	 * `ggm_get_setting()` reads.
	 *
	 * @return void
	 */
	private static function set_defaults() {
		$settings = get_option( 'ggm_settings', array() );
		$changed  = false;

		$defaults = array(
			'ggm_razorpay_mode'         => 'test',
			'ggm_otp_provider'          => 'email',
			'ggm_otp_expiry'            => 10, // minutes.
			'ggm_otp_length'            => 6,
			'ggm_otp_rate_limit'        => 5,  // max attempts.
			'ggm_whatsapp_cloud_template_language' => 'en_US',
			'ggm_whatsapp_cloud_api_version'        => 'v23.0',
			'ggm_currency'              => 'INR',
			'ggm_currency_symbol'       => '₹',
			'ggm_multicurrency_enabled' => '',
			'ggm_multicurrency_auto_detect' => '1',
			'ggm_multicurrency_enabled_codes' => 'INR,USD,EUR,GBP,AED,SAR,AUD,CAD',
			'ggm_multicurrency_default_currency' => 'INR',
			'ggm_exchange_rate_api_url' => 'https://api.currencylayer.com/live?access_key={api_key}&source=USD&currencies={symbols}',
			'ggm_exchange_rate_api_key' => '',
			'ggm_exchange_rate_cache_hours' => 12,
			'ggm_currency_manual_rates' => '',
			'ggm_whatsapp_notifications'=> 0,
			'ggm_email_notifications'   => 1,
			'ggm_auth_methods'          => array( 'email_otp', 'password' ),
		);

		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $value;
				$changed          = true;
			}
		}

		if ( $changed ) {
			update_option( 'ggm_settings', $settings );
		}
	}
}
