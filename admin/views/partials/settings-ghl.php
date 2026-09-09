<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<h2><?php esc_html_e( 'GoHighLevel Purchase Tracking', 'ggm-member-dashboard' ); ?></h2>
<p><?php esc_html_e( 'Send customer and transaction data to a GoHighLevel inbound webhook when someone purchases a course, enrolls in a workshop, or completes a WooCommerce order.', 'ggm-member-dashboard' ); ?></p>
<table class="form-table">
	<tr>
		<th><label for="ggm-webhook-url"><?php esc_html_e( 'GHL Webhook URL', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="url" name="settings[ggm_webhook_url]" id="ggm-webhook-url" value="<?php echo esc_url( $settings['ggm_webhook_url'] ?? '' ); ?>" class="large-text" placeholder="https://services.leadconnectorhq.com/hooks/...">
			<p class="description"><?php esc_html_e( 'Payloads include event, purchase type, item IDs and names, amount, currency, payment/order status, customer name, email, phone, billing details, and timestamps. WooCommerce orders include every purchased product and quantity.', 'ggm-member-dashboard' ); ?></p>
		</td>
	</tr>
</table>
