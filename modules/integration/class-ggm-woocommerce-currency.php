<?php
/**
 * WooCommerce price display integration for GGM multi-currency.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_WooCommerce_Currency {

	public function init( GGM_Loader $loader ) {
		$price_filters = array(
			'woocommerce_product_get_price',
			'woocommerce_product_get_regular_price',
			'woocommerce_product_get_sale_price',
			'woocommerce_product_variation_get_price',
			'woocommerce_product_variation_get_regular_price',
			'woocommerce_product_variation_get_sale_price',
		);

		foreach ( $price_filters as $filter ) {
			$loader->add_filter( $filter, $this, 'convert_product_price', 999, 2 );
		}

		$loader->add_filter( 'woocommerce_currency', $this, 'selected_currency', 999, 1 );
		$loader->add_filter( 'woocommerce_currency_symbol', $this, 'selected_currency_symbol', 999, 2 );
		$loader->add_filter( 'woocommerce_get_variation_prices_hash', $this, 'variation_prices_hash', 999, 3 );
	}

	public function convert_product_price( $price, $product ) {
		if ( '' === $price || null === $price || ! $this->should_convert() ) {
			return $price;
		}

		$product_id = is_object( $product ) && method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		return (string) GGM_Currency::convert_amount( (float) $price, GGM_Currency::selected_currency(), $product_id, false );
	}

	public function selected_currency( $currency ) {
		return $this->should_convert() ? GGM_Currency::selected_currency() : $currency;
	}

	public function selected_currency_symbol( $symbol, $currency ) {
		if ( ! $this->should_convert() ) {
			return $symbol;
		}

		return GGM_Currency::symbol( GGM_Currency::selected_currency() );
	}

	public function variation_prices_hash( $hash, $product, $display ) {
		if ( $this->should_convert() ) {
			$hash['ggm_currency'] = GGM_Currency::selected_currency();
		}

		return $hash;
	}

	private function should_convert() {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'GGM_Currency' ) || ! GGM_Currency::is_enabled() ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		$currency = GGM_Currency::selected_currency();
		return $currency && $currency !== GGM_Currency::base_currency() && in_array( $currency, GGM_Currency::enabled_codes(), true );
	}
}
