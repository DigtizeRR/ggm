<?php
/** GoHighLevel purchase, enrollment, and WooCommerce order tracking. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GGM_GHL {
	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'ggm_workshop_registered', $this, 'send_free_workshop', 10, 2 );
		$loader->add_action( 'woocommerce_payment_complete', $this, 'send_woocommerce_order', 10, 1 );
		$loader->add_action( 'woocommerce_order_status_processing', $this, 'send_woocommerce_order', 10, 1 );
		$loader->add_action( 'woocommerce_order_status_completed', $this, 'send_woocommerce_order', 10, 1 );
	}

	private function webhook_url() {
		$url = esc_url_raw( trim( (string) ggm_get_setting( 'ggm_webhook_url', '' ) ) );
		return $url && wp_http_validate_url( $url ) ? $url : '';
	}

	private function post( array $payload ) {
		$url = $this->webhook_url();
		if ( ! $url ) { return; }
		wp_remote_post( $url, array(
			'timeout'  => 8,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $payload ),
		) );
	}

	private function customer( $user_id ) {
		$user = get_userdata( $user_id );
		return array(
			'id'         => (int) $user_id,
			'name'       => $user ? $user->display_name : '',
			'first_name' => $user ? $user->first_name : '',
			'last_name'  => $user ? $user->last_name : '',
			'email'      => $user ? $user->user_email : '',
			'phone'      => get_user_meta( $user_id, 'billing_phone', true ) ?: get_user_meta( $user_id, 'ggm_phone', true ),
		);
	}

	public function send_free_workshop( $user_id, $workshop_id ) {
		$this->post( array(
			'event'       => 'workshop.enrolled',
			'type'        => 'workshop',
			'item_id'     => (int) $workshop_id,
			'item_name'   => get_the_title( $workshop_id ),
			'amount'      => 0,
			'currency'    => ggm_get_setting( 'ggm_currency', 'INR' ),
			'status'      => 'enrolled',
			'customer'    => $this->customer( $user_id ),
			'timestamp'   => current_time( 'mysql' ),
		) );
	}

	public function send_woocommerce_order( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) { return; }
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_ggm_ghl_purchase_sent', true ) ) { return; }
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'product_id'   => (int) $item->get_product_id(),
				'variation_id' => (int) $item->get_variation_id(),
				'sku'          => $product ? $product->get_sku() : '',
				'name'         => $item->get_name(),
				'quantity'     => (int) $item->get_quantity(),
				'subtotal'     => (float) $item->get_subtotal(),
				'total'        => (float) $item->get_total(),
			);
		}
		$payload = array(
			'event'          => 'woocommerce.order.paid',
			'type'           => 'woocommerce_order',
			'order_id'       => (int) $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'status'         => $order->get_status(),
			'amount'         => (float) $order->get_total(),
			'subtotal'       => (float) $order->get_subtotal(),
			'discount_total' => (float) $order->get_discount_total(),
			'tax_total'      => (float) $order->get_total_tax(),
			'shipping_total' => (float) $order->get_shipping_total(),
			'currency'       => $order->get_currency(),
			'payment_method' => $order->get_payment_method(),
			'items'          => $items,
			'customer'       => array(
				'id'         => (int) $order->get_customer_id(),
				'name'       => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
			),
			'timestamp'      => current_time( 'mysql' ),
		);
		$this->post( $payload );
		$order->update_meta_data( '_ggm_ghl_purchase_sent', current_time( 'mysql' ) );
		$order->save();
	}
}
