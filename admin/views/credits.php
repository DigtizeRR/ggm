<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$users = array();
if ( '' !== $search ) {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $search ) . '%';
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT u.ID FROM {$wpdb->users} u LEFT JOIN {$wpdb->usermeta} m ON m.user_id=u.ID AND m.meta_key IN ('ggm_phone','billing_phone') WHERE u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s OR m.meta_value LIKE %s ORDER BY u.display_name LIMIT 50",
		$like, $like, $like, $like
	) );
	foreach ( $ids as $id ) { $users[] = get_userdata( $id ); }
}
$currency = ggm_get_setting( 'ggm_currency_symbol', '₹' );
$recent = GGM_Credit::get_recent( 50 );
?>
<div class="wrap ggm-credits-page">
	<h1><?php esc_html_e( 'Customer Credits', 'ggm-member-dashboard' ); ?></h1>
	<p><?php esc_html_e( 'Search for a customer, then add credit to their account. Credits are automatically used on their next workshop or course purchase.', 'ggm-member-dashboard' ); ?></p>
	<form method="get"><input type="hidden" name="page" value="ggm-lms-credits">
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, username, email or phone', 'ggm-member-dashboard' ); ?>" style="width:360px">
		<?php submit_button( __( 'Search Customers', 'ggm-member-dashboard' ), 'secondary', '', false ); ?>
	</form>
	<?php if ( $users ) : ?>
	<table class="wp-list-table widefat striped" style="margin-top:20px"><thead><tr><th><?php esc_html_e( 'Customer', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Username', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Email / Phone', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Balance', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Add Credit', 'ggm-member-dashboard' ); ?></th></tr></thead><tbody>
	<?php foreach ( $users as $user ) : $phone = get_user_meta( $user->ID, 'ggm_phone', true ) ?: get_user_meta( $user->ID, 'billing_phone', true ); ?>
	<tr><td><strong><?php echo esc_html( $user->display_name ); ?></strong></td><td><?php echo esc_html( $user->user_login ); ?></td><td><?php echo esc_html( $user->user_email ); ?><br><?php echo esc_html( $phone ); ?></td><td><strong><?php echo esc_html( $currency ); ?><span class="ggm-credit-balance"><?php echo esc_html( number_format_i18n( GGM_Credit::get_balance( $user->ID ), 2 ) ); ?></span></strong></td><td><form class="ggm-add-credit-form" data-user-id="<?php echo esc_attr( $user->ID ); ?>"><input type="number" min="0.01" max="99999999" step="0.01" name="amount" placeholder="Amount" required style="width:110px"> <input type="text" name="note" maxlength="500" placeholder="Reason / note"> <button class="button button-primary"><?php esc_html_e( 'Add Credit', 'ggm-member-dashboard' ); ?></button> <span class="spinner"></span><span class="ggm-credit-feedback"></span></form></td></tr>
	<?php endforeach; ?></tbody></table>
	<?php elseif ( '' !== $search ) : ?><p><?php esc_html_e( 'No customers found.', 'ggm-member-dashboard' ); ?></p><?php endif; ?>
	<h2 style="margin-top:32px"><?php esc_html_e( 'Recent Credit Activity', 'ggm-member-dashboard' ); ?></h2>
	<table class="wp-list-table widefat striped"><thead><tr><th>Date</th><th>Customer</th><th>Amount</th><th>Status</th><th>Added by</th><th>Note</th></tr></thead><tbody>
	<?php foreach ( $recent as $tx ) : ?><tr><td><?php echo esc_html( $tx->created_at ); ?></td><td><?php echo esc_html( $tx->display_name ?: '#' . $tx->user_id ); ?><br><small><?php echo esc_html( $tx->user_email ); ?></small></td><td style="color:<?php echo $tx->amount >= 0 ? '#16803c' : '#b32d2e'; ?>"><?php echo esc_html( ( $tx->amount >= 0 ? '+' : '-' ) . $currency . number_format_i18n( abs( $tx->amount ), 2 ) ); ?></td><td><?php echo esc_html( ucfirst( $tx->status ) ); ?></td><td><?php echo esc_html( $tx->admin_name ?: '—' ); ?></td><td><?php echo esc_html( $tx->note ); ?></td></tr><?php endforeach; ?>
	<?php if ( ! $recent ) : ?><tr><td colspan="6">No credit activity yet.</td></tr><?php endif; ?></tbody></table>
</div>
