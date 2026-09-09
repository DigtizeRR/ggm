<?php
/**
 * Checkout success template.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$payment_id      = absint( $_GET['payment_id'] ?? 0 );
$payment         = $payment_id && class_exists( 'GGM_Payment' ) ? GGM_Payment::get( $payment_id ) : null;
$dashboard       = get_permalink( (int) ggm_get_setting( 'ggm_dashboard_page_id', 0 ) ) ?: home_url( '/dashboard/' );
$payment_currency = $payment && ! empty( $payment->currency ) ? strtoupper( sanitize_text_field( $payment->currency ) ) : ggm_get_setting( 'ggm_currency', 'INR' );
$currency_symbol = class_exists( 'GGM_Currency' )
	? GGM_Currency::symbol( $payment_currency )
	: ggm_get_setting( 'ggm_currency_symbol', '₹' );

// Resolve the purchased item's title (workshop or course) for display.
$item_title = '';
if ( $payment && ! empty( $payment->workshop_id ) ) {
	$item_title = get_the_title( (int) $payment->workshop_id );
} elseif ( $payment && ! empty( $payment->course_id ) ) {
	$item_title = get_the_title( (int) $payment->course_id );
}

// Razorpay payment ID passed directly as query param by the verify-payment handler.
$razorpay_payment_id = sanitize_text_field( $_GET['razorpay_payment_id'] ?? '' );
if ( $payment && empty( $razorpay_payment_id ) ) {
	$razorpay_payment_id = $payment->razorpay_payment_id ?? '';
}
?>
<div class="ggm-success-wrap">
	<div class="ggm-success-card">
		<div class="ggm-success-icon">&#10003;</div>
		<h2><?php esc_html_e( 'Payment Successful!', 'ggm-member-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'Your purchase is complete. You now have full access.', 'ggm-member-dashboard' ); ?></p>

		<?php if ( $payment ) : ?>
			<div class="ggm-success-details">
				<?php if ( $item_title ) : ?>
				<div class="ggm-checkout-row">
					<span><?php esc_html_e( 'Item', 'ggm-member-dashboard' ); ?></span>
					<span><?php echo esc_html( $item_title ); ?></span>
				</div>
				<?php endif; ?>
				<div class="ggm-checkout-row">
					<span><?php esc_html_e( 'Amount Paid', 'ggm-member-dashboard' ); ?></span>
					<span><?php echo esc_html( $currency_symbol . number_format( (float) $payment->amount, 2 ) ); ?></span>
				</div>
				<?php if ( $razorpay_payment_id ) : ?>
				<div class="ggm-checkout-row">
					<span><?php esc_html_e( 'Payment ID', 'ggm-member-dashboard' ); ?></span>
					<code><?php echo esc_html( $razorpay_payment_id ); ?></code>
				</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<a href="<?php echo esc_url( $dashboard ); ?>" class="ggm-btn ggm-btn-primary" style="margin-top:20px; display:inline-block;">
			<?php esc_html_e( 'Go to Dashboard', 'ggm-member-dashboard' ); ?>
		</a>
	</div>
</div>
