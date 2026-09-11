<?php
/**
 * Multi-currency helpers for display and checkout pricing.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Currency {

	const DEFAULT_API_URL = 'https://api.currencylayer.com/live?access_key={api_key}&source=USD&currencies={symbols}';
	const LEGACY_DEFAULT_API_URL = 'https://api.frankfurter.dev/v2/rates?base={base}&quotes={symbols}';
	const LEGACY_CURRENCYLAYER_BASE_API_URL = 'https://api.currencylayer.com/live?access_key={api_key}&source={base}&currencies={symbols}';
	const RATE_CACHE_PREFIX = 'ggm_currency_rates_v2_';

	public static function currencies() {
		return array(
			'INR' => array( 'symbol' => '₹',   'label' => 'Indian Rupee',      'countries' => array( 'IN' ) ),
			'USD' => array( 'symbol' => '$',   'label' => 'US Dollar',         'countries' => array( 'US', 'PR', 'GU' ) ),
			'EUR' => array( 'symbol' => '€',   'label' => 'Euro',              'countries' => array( 'AT', 'BE', 'DE', 'ES', 'FI', 'FR', 'GR', 'IE', 'IT', 'NL', 'PT' ) ),
			'GBP' => array( 'symbol' => '£',   'label' => 'British Pound',     'countries' => array( 'GB' ) ),
			'AED' => array( 'symbol' => 'AED ', 'label' => 'UAE Dirham',       'countries' => array( 'AE' ) ),
			'SAR' => array( 'symbol' => 'SAR ', 'label' => 'Saudi Riyal',      'countries' => array( 'SA' ) ),
			'AUD' => array( 'symbol' => 'A$',  'label' => 'Australian Dollar', 'countries' => array( 'AU' ) ),
			'CAD' => array( 'symbol' => 'C$',  'label' => 'Canadian Dollar',   'countries' => array( 'CA' ) ),
			'SGD' => array( 'symbol' => 'S$',  'label' => 'Singapore Dollar',  'countries' => array( 'SG' ) ),
			'NZD' => array( 'symbol' => 'NZ$', 'label' => 'New Zealand Dollar','countries' => array( 'NZ' ) ),
			'JPY' => array( 'symbol' => '¥',   'label' => 'Japanese Yen',      'countries' => array( 'JP' ) ),
		);
	}

	/**
	 * Return a currency code that is supported by this plugin.
	 *
	 * Currency values can originate in old free-text settings. Normalising at
	 * the boundary prevents symbols and stale labels from reaching a payment
	 * gateway as though they were ISO currency codes.
	 *
	 * @param mixed  $currency Currency value to validate.
	 * @param string $fallback Supported fallback code.
	 * @return string
	 */
	public static function normalize_code( $currency, $fallback = 'INR' ) {
		$known    = self::currencies();
		$currency = strtoupper( trim( sanitize_text_field( (string) $currency ) ) );
		$fallback = strtoupper( trim( sanitize_text_field( (string) $fallback ) ) );

		if ( isset( $known[ $currency ] ) ) {
			return $currency;
		}

		return isset( $known[ $fallback ] ) ? $fallback : 'INR';
	}

	public static function is_enabled() {
		return ! empty( get_option( 'ggm_settings', array() )['ggm_multicurrency_enabled'] );
	}

	public static function base_currency() {
		return self::normalize_code( ggm_get_setting( 'ggm_currency', 'INR' ) );
	}

	public static function default_currency() {
		$default = strtoupper( sanitize_text_field( ggm_get_setting( 'ggm_multicurrency_default_currency', self::base_currency() ) ) );
		return in_array( $default, self::enabled_codes(), true ) ? $default : self::base_currency();
	}

	public static function enabled_codes() {
		$base = self::base_currency();
		if ( ! self::is_enabled() ) {
			return array( $base );
		}

		$raw = (string) ggm_get_setting( 'ggm_multicurrency_enabled_codes', 'INR,USD,EUR,GBP,AED,SAR,AUD,CAD' );
		$codes = array_filter( array_map( static function ( $code ) {
			return strtoupper( sanitize_text_field( trim( $code ) ) );
		}, preg_split( '/[\s,]+/', $raw ) ) );

		$known = self::currencies();
		$codes = array_values( array_unique( array_filter( $codes, static function ( $code ) use ( $known ) {
			return isset( $known[ $code ] );
		} ) ) );

		if ( ! in_array( $base, $codes, true ) ) {
			array_unshift( $codes, $base );
		}

		return $codes ?: array( $base );
	}

	public static function selected_currency() {
		$code = '';
		foreach ( array( 'ggm_currency' => $_REQUEST['ggm_currency'] ?? '', 'cookie' => $_COOKIE['ggm_currency'] ?? '' ) as $source => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_scalar( $value ) ) {
				$code = strtoupper( sanitize_text_field( wp_unslash( (string) $value ) ) );
				if ( in_array( $code, self::enabled_codes(), true ) ) {
					return $code;
				}
			}
		}
		return self::default_currency();
	}

	public static function symbol( $currency ) {
		$currency = strtoupper( sanitize_text_field( $currency ) );
		$known    = self::currencies();
		return $known[ $currency ]['symbol'] ?? $currency . ' ';
	}

	public static function decimals( $currency ) {
		return 'JPY' === strtoupper( sanitize_text_field( $currency ) ) ? 0 : 2;
	}

	public static function parse_manual_rates() {
		$raw   = (string) ggm_get_setting( 'ggm_currency_manual_rates', '' );
		$rates = array();
		foreach ( preg_split( '/\R+/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $code, $rate ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$code = strtoupper( sanitize_text_field( $code ) );
			$rate = (float) $rate;
			if ( $code && $rate > 0 ) {
				$rates[ $code ] = $rate;
			}
		}
		return $rates;
	}

	public static function rates( array $targets = array() ) {
		$base    = self::base_currency();
		$targets = $targets ?: self::enabled_codes();
		$targets = array_values( array_diff( array_unique( array_map( 'strtoupper', $targets ) ), array( $base ) ) );
		if ( ! $targets ) {
			return array();
		}

		$manual = self::parse_manual_rates();
		$cached = get_transient( self::RATE_CACHE_PREFIX . $base );
		$rates  = is_array( $cached ) ? $cached : array();

		if ( ! $rates && self::api_url_template() ) {
			$rates = self::fetch_api_rates( $base, $targets );
			if ( $rates ) {
				set_transient( self::RATE_CACHE_PREFIX . $base, $rates, max( 1, absint( ggm_get_setting( 'ggm_exchange_rate_cache_hours', 12 ) ) ) * HOUR_IN_SECONDS );
			}
		}

		foreach ( $manual as $code => $rate ) {
			if ( ! isset( $rates[ $code ] ) ) {
				$rates[ $code ] = $rate;
			}
		}

		return array_intersect_key( $rates, array_flip( $targets ) );
	}

	public static function api_url_template() {
		$template = trim( (string) ggm_get_setting( 'ggm_exchange_rate_api_url', self::DEFAULT_API_URL ) );
		return in_array( $template, array( self::LEGACY_DEFAULT_API_URL, self::LEGACY_CURRENCYLAYER_BASE_API_URL ), true ) ? self::DEFAULT_API_URL : $template;
	}

	private static function fetch_api_rates( $base, array $targets ) {
		$template = self::api_url_template();
		if ( '' === $template ) {
			return array();
		}

		$source          = self::api_source_currency( $template, $base );
		$request_targets = self::api_request_targets( $targets, $base, $source );
		$url = str_replace(
			array( '{base}', '{source}', '{symbols}', '{currencies}', '{api_key}' ),
			array( rawurlencode( $base ), rawurlencode( $source ), rawurlencode( implode( ',', $request_targets ) ), rawurlencode( implode( ',', $request_targets ) ), rawurlencode( (string) ggm_get_setting( 'ggm_exchange_rate_api_key', '' ) ) ),
			$template
		);

		$response = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 12 ) );
		if ( is_wp_error( $response ) ) {
			self::log_rate_error( 'Exchange rate request failed: ' . $response->get_error_message() );
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			self::log_rate_error( 'Exchange rate request returned an invalid response.', array( 'http_status' => $code ) );
			return array();
		}
		if ( isset( $body['success'] ) && false === $body['success'] ) {
			$error = isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
			self::log_rate_error( 'Currencylayer exchange rate request failed.', array(
				'http_status' => $code,
				'api_error_code' => $error['code'] ?? '',
				'api_error_info' => $error['info'] ?? '',
			) );
			return array();
		}

		$raw_rates = isset( $body['rates'] ) && is_array( $body['rates'] ) ? $body['rates'] : array();
		$rates     = array();
		foreach ( $raw_rates as $currency => $rate ) {
			$currency = strtoupper( sanitize_text_field( (string) $currency ) );
			$rate     = (float) $rate;
			if ( $rate > 0 ) {
				$rates[ $currency ] = $rate;
			}
		}

		if ( ! $rates && isset( $body['quotes'] ) && is_array( $body['quotes'] ) ) {
			$rates = self::parse_currencylayer_quotes( $body['quotes'], $base, $targets, $body['source'] ?? $base );
		}

		return $rates;
	}

	private static function api_source_currency( $template, $base ) {
		$base = strtoupper( sanitize_text_field( $base ) );
		if ( preg_match( '/[?&]source=([A-Z]{3})(?:&|$)/i', (string) $template, $matches ) ) {
			return strtoupper( sanitize_text_field( $matches[1] ) );
		}
		return $base;
	}

	private static function api_request_targets( array $targets, $base, $source ) {
		$base    = strtoupper( sanitize_text_field( $base ) );
		$source  = strtoupper( sanitize_text_field( $source ) );
		$targets = array_values( array_unique( array_map( static function ( $target ) {
			return strtoupper( sanitize_text_field( (string) $target ) );
		}, $targets ) ) );

		if ( $source && $source !== $base && ! in_array( $base, $targets, true ) ) {
			array_unshift( $targets, $base );
		}
		if ( $source && ! in_array( $source, $targets, true ) ) {
			array_unshift( $targets, $source );
		}

		return array_values( array_filter( $targets ) );
	}

	private static function parse_currencylayer_quotes( array $quotes, $base, array $targets, $source ) {
		$base   = strtoupper( sanitize_text_field( $base ) );
		$source = strtoupper( sanitize_text_field( (string) $source ) );
		$clean  = array();
		foreach ( $quotes as $pair => $rate ) {
			$pair = strtoupper( sanitize_text_field( (string) $pair ) );
			$rate = (float) $rate;
			if ( $rate > 0 ) {
				$clean[ $pair ] = $rate;
			}
		}

		$rates = array();
		foreach ( $targets as $target ) {
			$target = strtoupper( sanitize_text_field( $target ) );
			if ( $target === $base ) {
				continue;
			}

			if ( $source === $base ) {
				$rate = (float) ( $clean[ $source . $target ] ?? 0 );
			} elseif ( $target === $source && ! empty( $clean[ $source . $base ] ) ) {
				$rate = 1 / (float) $clean[ $source . $base ];
			} elseif ( ! empty( $clean[ $source . $target ] ) && ! empty( $clean[ $source . $base ] ) ) {
				$rate = (float) $clean[ $source . $target ] / (float) $clean[ $source . $base ];
			} else {
				$rate = 0;
			}

			if ( $rate > 0 ) {
				$rates[ $target ] = $rate;
			}
		}

		return $rates;
	}

	private static function log_rate_error( $message, array $context = array() ) {
		if ( class_exists( 'GGM_Meta_Boxes' ) ) {
			GGM_Meta_Boxes::log_error( $message, array_merge( array( 'category' => 'currency' ), $context ) );
		}
	}

	public static function adjustment_meta_key() {
		return 'ggm_currency_adjustments';
	}

	public static function adjustments_for_item( $post_id ) {
		$raw = get_post_meta( absint( $post_id ), self::adjustment_meta_key(), true );
		$raw = is_array( $raw ) ? $raw : array();
		$clean = array();
		foreach ( $raw as $currency => $amount ) {
			$currency = strtoupper( sanitize_text_field( (string) $currency ) );
			$amount   = round( (float) $amount, 2 );
			if ( $currency && $amount > 0 ) {
				$clean[ $currency ] = $amount;
			}
		}
		return $clean;
	}

	public static function save_adjustments_for_item( $post_id, $rows ) {
		$clean = array();
		foreach ( (array) $rows as $currency => $amount ) {
			$currency = strtoupper( sanitize_text_field( (string) $currency ) );
			$amount   = round( (float) $amount, 2 );
			if ( in_array( $currency, self::enabled_codes(), true ) && $currency !== self::base_currency() && $amount > 0 ) {
				$clean[ $currency ] = $amount;
			}
		}

		if ( $clean ) {
			update_post_meta( absint( $post_id ), self::adjustment_meta_key(), $clean );
		} else {
			delete_post_meta( absint( $post_id ), self::adjustment_meta_key() );
		}
	}

	public static function convert_amount( $base_amount, $currency = '', $post_id = 0, $include_adjustment = true ) {
		$base_amount = round( max( 0, (float) $base_amount ), 2 );
		$currency    = $currency ? strtoupper( sanitize_text_field( $currency ) ) : self::selected_currency();
		$base        = self::base_currency();

		if ( ! self::is_enabled() || $currency === $base || ! in_array( $currency, self::enabled_codes(), true ) ) {
			return $base_amount;
		}

		$rates = self::rates( array( $currency ) );
		$rate  = (float) ( $rates[ $currency ] ?? 0 );
		if ( $rate <= 0 ) {
			return $base_amount;
		}

		$amount = $base_amount * $rate;
		if ( $include_adjustment && $post_id && $base_amount > 0 ) {
			$adjustments = self::adjustments_for_item( $post_id );
			$amount     += (float) ( $adjustments[ $currency ] ?? 0 );
		}

		return round( $amount, self::decimals( $currency ) );
	}

	public static function format( $amount, $currency = '', $decimals = null ) {
		$currency = $currency ? strtoupper( sanitize_text_field( $currency ) ) : self::selected_currency();
		$decimals = null === $decimals ? self::decimals( $currency ) : absint( $decimals );
		return self::symbol( $currency ) . number_format_i18n( (float) $amount, $decimals );
	}

	public static function format_converted( $base_amount, $post_id = 0, $currency = '', $decimals = null ) {
		$currency = $currency ? strtoupper( sanitize_text_field( $currency ) ) : self::selected_currency();
		return self::format( self::convert_amount( $base_amount, $currency, $post_id ), $currency, $decimals );
	}

	public static function frontend_config() {
		$codes      = self::enabled_codes();
		$currencies = self::currencies();
		$items      = array();
		$countries  = array();
		foreach ( $codes as $code ) {
			$items[ $code ] = array(
				'code'   => $code,
				'symbol' => $currencies[ $code ]['symbol'],
				'label'  => $currencies[ $code ]['label'],
			);
			foreach ( $currencies[ $code ]['countries'] as $country ) {
				$countries[ $country ] = $code;
			}
		}

		return array(
			'enabled'      => self::is_enabled(),
			'base'         => self::base_currency(),
			'current'      => self::selected_currency(),
			'default'      => self::default_currency(),
			'auto_detect'  => ! empty( ggm_get_setting( 'ggm_multicurrency_auto_detect', '1' ) ),
			'currencies'   => $items,
			'country_map'  => $countries,
			'label'        => __( 'Currency', 'ggm-member-dashboard' ),
		);
	}
}
