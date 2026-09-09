<?php
/**
 * WooCommerce customer account summary inside the member dashboard.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_orders' ) ) :
?>
	<div class="ggm-wc-empty">
		<span class="dashicons dashicons-store"></span>
		<h2><?php esc_html_e( 'Store account unavailable', 'ggm-member-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'WooCommerce is not currently active.', 'ggm-member-dashboard' ); ?></p>
	</div>
<?php
	return;
endif;

$customer_id = get_current_user_id();
$customer    = new WC_Customer( $customer_id );
$orders      = wc_get_orders( array(
	'customer_id' => $customer_id,
	'limit'       => 10,
	'orderby'     => 'date',
	'order'       => 'DESC',
) );
$dashboard_page_id = (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 );
$dashboard_url     = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/dashboard/' );
$account_tab_url   = remove_query_arg( array( 'ggm_wc_order', 'ggm_wc_address' ), $dashboard_url ) . '#tab-woocommerce';
$selected_order_id = absint( $_GET['ggm_wc_order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view, ownership checked below.
$selected_order    = $selected_order_id ? wc_get_order( $selected_order_id ) : false;
if ( $selected_order && (int) $selected_order->get_customer_id() !== $customer_id ) {
	$selected_order = false;
}
$edit_address = sanitize_key( wp_unslash( $_GET['ggm_wc_address'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$edit_address = in_array( $edit_address, array( 'billing', 'shipping' ), true ) ? $edit_address : '';
$address_notice = '';

if ( $edit_address && 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['ggm_wc_save_address'] ) ) {
	if ( ! isset( $_POST['ggm_wc_address_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ggm_wc_address_nonce'] ) ), 'ggm_wc_save_address_' . $edit_address ) ) {
		$address_notice = __( 'Your session expired. Please try again.', 'ggm-member-dashboard' );
	} else {
		$fields = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode' );
		if ( 'billing' === $edit_address ) {
			$fields[] = 'phone';
			$fields[] = 'email';
		}
		foreach ( $fields as $field ) {
			$value  = sanitize_text_field( wp_unslash( $_POST[ $edit_address . '_' . $field ] ?? '' ) );
			$setter = 'set_' . $edit_address . '_' . $field;
			if ( is_callable( array( $customer, $setter ) ) ) {
				$customer->{$setter}( $value );
			}
		}
		try {
			$customer->save();
			$address_notice = __( 'Address saved successfully.', 'ggm-member-dashboard' );
		} catch ( Exception $e ) {
			$address_notice = __( 'The address could not be saved. Please check the fields and try again.', 'ggm-member-dashboard' );
		}
	}
}

$get_address = static function ( $customer, $type ) {
	$parts = array_filter( array(
		$customer->{"get_{$type}_first_name"}() . ' ' . $customer->{"get_{$type}_last_name"}(),
		$customer->{"get_{$type}_company"}(),
		$customer->{"get_{$type}_address_1"}(),
		$customer->{"get_{$type}_address_2"}(),
		trim( $customer->{"get_{$type}_city"}() . ' ' . $customer->{"get_{$type}_postcode"}() ),
		$customer->{"get_{$type}_state"}(),
		$customer->{"get_{$type}_country"}(),
	) );
	return $parts;
};

$billing_address  = $get_address( $customer, 'billing' );
$shipping_address = $get_address( $customer, 'shipping' );
?>

<div class="ggm-wc-account">
	<div class="ggm-wc-heading">
		<div><span class="dashicons dashicons-store"></span></div>
		<span><small><?php esc_html_e( 'WooCommerce', 'ggm-member-dashboard' ); ?></small><h3><?php esc_html_e( 'My Account', 'ggm-member-dashboard' ); ?></h3></span>
	</div>

	<div class="ggm-wc-stats">
		<div><span class="dashicons dashicons-cart"></span><strong><?php echo esc_html( count( $orders ) ); ?></strong><small><?php esc_html_e( 'Recent orders', 'ggm-member-dashboard' ); ?></small></div>
		<div><span class="dashicons dashicons-location"></span><strong><?php echo esc_html( ( $billing_address ? 1 : 0 ) + ( $shipping_address ? 1 : 0 ) ); ?></strong><small><?php esc_html_e( 'Saved addresses', 'ggm-member-dashboard' ); ?></small></div>
	</div>

	<?php if ( $selected_order ) : ?>
	<section class="ggm-wc-section ggm-wc-detail-view">
		<a class="ggm-wc-back" href="<?php echo esc_url( $account_tab_url ); ?>">← <?php esc_html_e( 'Back to My Account', 'ggm-member-dashboard' ); ?></a>
		<div class="ggm-wc-section-head"><div><h2><?php printf( esc_html__( 'Order #%s', 'ggm-member-dashboard' ), esc_html( $selected_order->get_order_number() ) ); ?></h2><p><?php echo esc_html( wc_format_datetime( $selected_order->get_date_created() ) ); ?> · <?php echo esc_html( wc_get_order_status_name( $selected_order->get_status() ) ); ?></p></div></div>
		<div class="ggm-wc-detail-items">
			<?php foreach ( $selected_order->get_items() as $item ) : ?><div><span><strong><?php echo esc_html( $item->get_name() ); ?></strong><small><?php printf( esc_html__( 'Quantity: %d', 'ggm-member-dashboard' ), esc_html( $item->get_quantity() ) ); ?></small></span><b><?php echo wp_kses_post( $selected_order->get_formatted_line_subtotal( $item ) ); ?></b></div><?php endforeach; ?>
		</div>
		<div class="ggm-wc-order-totals"><?php foreach ( $selected_order->get_order_item_totals() as $total ) : ?><div><span><?php echo wp_kses_post( $total['label'] ); ?></span><strong><?php echo wp_kses_post( $total['value'] ); ?></strong></div><?php endforeach; ?></div>
		<?php if ( $selected_order->get_customer_note() ) : ?><div class="ggm-wc-order-note"><strong><?php esc_html_e( 'Order note', 'ggm-member-dashboard' ); ?></strong><p><?php echo wp_kses_post( nl2br( esc_html( $selected_order->get_customer_note() ) ) ); ?></p></div><?php endif; ?>
	</section>
	<?php endif; ?>

	<section class="ggm-wc-section">
		<div class="ggm-wc-section-head"><div><h2><?php esc_html_e( 'Orders', 'ggm-member-dashboard' ); ?></h2><p><?php esc_html_e( 'Your latest WooCommerce purchases.', 'ggm-member-dashboard' ); ?></p></div></div>
		<?php if ( $orders ) : ?>
			<div class="ggm-wc-orders">
			<?php foreach ( $orders as $order ) : ?>
				<article class="ggm-wc-order-card">
					<div class="ggm-wc-order-top">
						<div><small><?php esc_html_e( 'Order', 'ggm-member-dashboard' ); ?></small><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></div>
						<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></time>
						<span class="ggm-wc-status ggm-wc-status-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
					</div>
					<div class="ggm-wc-order-items">
						<?php foreach ( $order->get_items() as $item ) : ?><span><?php echo esc_html( $item->get_name() ); ?> <small>× <?php echo esc_html( $item->get_quantity() ); ?></small></span><?php endforeach; ?>
					</div>
					<div class="ggm-wc-order-bottom"><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong><a href="<?php echo esc_url( add_query_arg( 'ggm_wc_order', $order->get_id(), $dashboard_url ) . '#tab-woocommerce' ); ?>"><?php esc_html_e( 'View details', 'ggm-member-dashboard' ); ?> →</a></div>
				</article>
			<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="ggm-wc-empty-inline"><span class="dashicons dashicons-cart"></span><p><?php esc_html_e( 'You have no WooCommerce orders yet.', 'ggm-member-dashboard' ); ?></p></div>
		<?php endif; ?>
	</section>

	<section class="ggm-wc-section">
		<div class="ggm-wc-section-head"><div><h2><?php esc_html_e( 'Addresses', 'ggm-member-dashboard' ); ?></h2><p><?php esc_html_e( 'Addresses used during WooCommerce checkout.', 'ggm-member-dashboard' ); ?></p></div></div>
		<div class="ggm-wc-address-grid">
			<article><span class="dashicons dashicons-admin-home"></span><h3><?php esc_html_e( 'Billing address', 'ggm-member-dashboard' ); ?></h3><?php if ( $billing_address ) : ?><address><?php echo wp_kses_post( implode( '<br>', array_map( 'esc_html', $billing_address ) ) ); ?></address><?php else : ?><p><?php esc_html_e( 'No billing address saved.', 'ggm-member-dashboard' ); ?></p><?php endif; ?><a class="ggm-wc-address-edit" href="<?php echo esc_url( add_query_arg( 'ggm_wc_address', 'billing', $dashboard_url ) . '#tab-woocommerce' ); ?>"><?php esc_html_e( 'Edit billing address', 'ggm-member-dashboard' ); ?></a></article>
			<article><span class="dashicons dashicons-location-alt"></span><h3><?php esc_html_e( 'Shipping address', 'ggm-member-dashboard' ); ?></h3><?php if ( $shipping_address ) : ?><address><?php echo wp_kses_post( implode( '<br>', array_map( 'esc_html', $shipping_address ) ) ); ?></address><?php else : ?><p><?php esc_html_e( 'No shipping address saved.', 'ggm-member-dashboard' ); ?></p><?php endif; ?><a class="ggm-wc-address-edit" href="<?php echo esc_url( add_query_arg( 'ggm_wc_address', 'shipping', $dashboard_url ) . '#tab-woocommerce' ); ?>"><?php esc_html_e( 'Edit shipping address', 'ggm-member-dashboard' ); ?></a></article>
		</div>
	</section>

	<?php if ( $edit_address ) :
		$address_fields = array(
			'first_name' => __( 'First name', 'ggm-member-dashboard' ), 'last_name' => __( 'Last name', 'ggm-member-dashboard' ),
			'company' => __( 'Company', 'ggm-member-dashboard' ), 'address_1' => __( 'Address line 1', 'ggm-member-dashboard' ),
			'address_2' => __( 'Address line 2', 'ggm-member-dashboard' ), 'city' => __( 'City', 'ggm-member-dashboard' ),
			'state' => __( 'State', 'ggm-member-dashboard' ), 'postcode' => __( 'Postcode', 'ggm-member-dashboard' ),
			'country' => __( 'Country code', 'ggm-member-dashboard' ),
		);
		if ( 'billing' === $edit_address ) { $address_fields['phone'] = __( 'Phone', 'ggm-member-dashboard' ); $address_fields['email'] = __( 'Email', 'ggm-member-dashboard' ); }
	?>
	<section class="ggm-wc-section ggm-wc-address-form-wrap">
		<a class="ggm-wc-back" href="<?php echo esc_url( $account_tab_url ); ?>">← <?php esc_html_e( 'Back to My Account', 'ggm-member-dashboard' ); ?></a>
		<div class="ggm-wc-section-head"><div><h2><?php printf( esc_html__( 'Edit %s address', 'ggm-member-dashboard' ), esc_html( $edit_address ) ); ?></h2><p><?php esc_html_e( 'Update the address used during WooCommerce checkout.', 'ggm-member-dashboard' ); ?></p></div></div>
		<?php if ( $address_notice ) : ?><div class="ggm-wc-notice"><?php echo esc_html( $address_notice ); ?></div><?php endif; ?>
		<form method="post" class="ggm-wc-address-form">
			<?php foreach ( $address_fields as $field => $label ) : $getter = 'get_' . $edit_address . '_' . $field; ?>
			<label><span><?php echo esc_html( $label ); ?></span><input type="<?php echo 'email' === $field ? 'email' : ( 'phone' === $field ? 'tel' : 'text' ); ?>" name="<?php echo esc_attr( $edit_address . '_' . $field ); ?>" value="<?php echo esc_attr( is_callable( array( $customer, $getter ) ) ? $customer->{$getter}() : '' ); ?>" <?php echo in_array( $field, array( 'first_name', 'last_name', 'address_1', 'city', 'postcode', 'country' ), true ) ? 'required' : ''; ?>></label>
			<?php endforeach; ?>
			<?php wp_nonce_field( 'ggm_wc_save_address_' . $edit_address, 'ggm_wc_address_nonce' ); ?>
			<button type="submit" name="ggm_wc_save_address" value="1" class="ggm-btn ggm-btn-primary"><?php esc_html_e( 'Save address', 'ggm-member-dashboard' ); ?></button>
		</form>
	</section>
	<?php endif; ?>
</div>
