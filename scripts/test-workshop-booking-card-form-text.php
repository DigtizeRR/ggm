<?php
/**
 * Lightweight rendering contracts for the booking summary and Form Text.
 *
 * Run with: php scripts/test-workshop-booking-card-form-text.php
 */

define( 'ABSPATH', dirname( __DIR__ ) );

$GLOBALS['ggm_test_meta'] = array();
$GLOBALS['ggm_test_price'] = array( 'type' => 'sale', 'regular' => 7000, 'sale' => 1400 );
$GLOBALS['ggm_test_current_id'] = 77;
$GLOBALS['ggm_test_queried_id'] = 77;
$GLOBALS['ggm_test_shortcodes'] = array();

function shortcode_atts( $defaults, $atts, $shortcode = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return array_merge( $defaults, $atts );
}
function absint( $value ) { return abs( (int) $value ); }
function get_the_ID() { return $GLOBALS['ggm_test_current_id']; }
function get_queried_object_id() { return $GLOBALS['ggm_test_queried_id']; }
function get_post_type( $post_id ) { return 77 === (int) $post_id ? 'workshop' : 'post'; }
function add_shortcode( $tag, $callback ) { $GLOBALS['ggm_test_shortcodes'][ $tag ] = $callback; }
function get_post_meta( $post_id, $key, $single = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return $GLOBALS['ggm_test_meta'][ $key ] ?? '';
}
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function __( $value, $domain = '' ) { return $value; } // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
function number_format_i18n( $value, $decimals = 0 ) { return number_format( $value, $decimals ); }
function wp_timezone() { return new DateTimeZone( 'Asia/Kolkata' ); }
function wp_date( $format, $timestamp, $timezone = null ) {
	$date = new DateTimeImmutable( '@' . $timestamp );
	return $date->setTimezone( $timezone ?: wp_timezone() )->format( $format );
}
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_kses_post( $value ) {
	$value = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $value );
	return strip_tags( $value, '<a><audio><blockquote><br><div><em><figure><figcaption><h2><h3><h4><img><li><ol><p><strong><ul><video><span>' );
}
function wpautop( $value ) {
	$value = trim( (string) $value );
	return preg_match( '/^<(?:blockquote|div|figure|h[2-4]|ol|p|ul)\b/i', $value ) ? $value : '<p>' . nl2br( $value ) . '</p>';
}

class GGM_Currency {
	public static $enabled = false;
	public static function is_enabled() { return self::$enabled; }
	public static function format_converted( $value, $workshop_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '$' . number_format_i18n( $value / 100, 0 );
	}
}

class GGM_Workshop {
	public static function price_html( $workshop_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$price = $GLOBALS['ggm_test_price'];
		if ( 'free' === $price['type'] ) {
			return '<span class="ggm-free-badge">Free</span>';
		}
		if ( 'contribution' === $price['type'] ) {
			return '<span class="ggm-price ggm-price--contribution">Contribute (₹500 – ₹1,500)</span>';
		}
		$current = 'sale' === $price['type'] ? $price['sale'] : $price['regular'];
		return '<span class="ggm-price">₹' . number_format_i18n( $current, 0 ) . '</span>';
	}
	public static function get_regular_price( $workshop_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return (float) $GLOBALS['ggm_test_price']['regular'];
	}
	public static function get_sale_price( $workshop_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return (float) $GLOBALS['ggm_test_price']['sale'];
	}
	public static function is_contribution( $workshop_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return 'contribution' === $GLOBALS['ggm_test_price']['type'];
	}
	public static function get_currency( $workshop_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '₹';
	}
}

require dirname( __DIR__ ) . '/public/class-ggm-shortcodes.php';

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
};

$GLOBALS['ggm_test_meta'] = array(
	'workshop_start_date' => '2026-09-24',
	'workshop_end_date'   => '2026-09-30',
	'workshop_mode'       => 'Live on Zoom',
	'duration'            => '7 Days Live Online Workshop',
);

$shortcodes = new GGM_Shortcodes();
$shortcodes->init();
$assert( isset( $GLOBALS['ggm_test_shortcodes']['ggm_workshop_booking_summary'] ), 'Legacy standalone booking-summary shortcode is not registered.' );
$assert( isset( $GLOBALS['ggm_test_shortcodes']['ggm_workshop_booking_card_summary'] ), 'Booking-card summary shortcode is not registered.' );
$sale_html  = $shortcodes->sc_workshop_booking_card( array( 'id' => 77 ) );
$assert( 1 === substr_count( $sale_html, 'class="ggm-workshop-booking-summary"' ), 'Booking card must use the shared compact summary surface.' );
$assert( false !== strpos( $sale_html, 'Sept 24–30, 2026' ), 'Same-month compact date range failed.' );
$assert( 1 === substr_count( $sale_html, 'Live on Zoom' ), 'Workshop mode must render once.' );
$assert( false === strpos( $sale_html, '7 Days' ), 'Duration must not duplicate a configured Workshop Mode.' );
$assert( false !== strpos( $sale_html, 'ggm-workshop-booking-summary__money' ), 'Inline money icon is missing.' );
$assert( strpos( $sale_html, 'ggm-workshop-booking-summary__divider' ) < strpos( $sale_html, 'ggm-workshop-booking-summary__money' ), 'Booking-card divider must come before the money icon.' );
$assert( false !== strpos( $sale_html, '₹1,400' ) && false !== strpos( $sale_html, '₹7,000' ), 'Sale and regular price output failed.' );
$assert( false !== strpos( $sale_html, 'You Save ₹5,600' ), 'Savings pill value failed.' );
$assert( false === strpos( $sale_html, 'ggm-workshop-booking-card__price-label' ), 'Legacy price label must not render.' );
$assert( false === strpos( $sale_html, 'ggm-workshop-booking-card__summary' ), 'Booking card must not retain its separate legacy summary markup.' );
$assert( strpos( $sale_html, 'ggm-workshop-booking-card__button' ) > strpos( $sale_html, 'ggm-workshop-booking-summary' ), 'CTA must remain after the shared summary.' );

$compact_summary_html = $shortcodes->sc_workshop_booking_summary( array( 'id' => 77 ) );
$assert( false !== strpos( $compact_summary_html, 'ggm-workshop-booking-summary__schedule' ), 'Standalone booking summary schedule is missing.' );
$assert( false !== strpos( $compact_summary_html, 'ggm-workshop-booking-summary__money' ), 'Standalone booking summary money SVG is missing.' );
$assert( false !== strpos( $compact_summary_html, 'ggm-workshop-booking-summary__divider' ), 'Standalone booking summary divider is missing.' );
$assert( strpos( $compact_summary_html, 'ggm-workshop-booking-summary__divider' ) < strpos( $compact_summary_html, 'ggm-workshop-booking-summary__money' ), 'Standalone divider must come before the money icon.' );
$assert( false !== strpos( $compact_summary_html, '₹1,400' ) && false !== strpos( $compact_summary_html, '₹7,000' ), 'Standalone booking summary prices failed.' );
$assert( false !== strpos( $compact_summary_html, 'You Save ₹5,600' ), 'Standalone booking summary saving failed.' );
$assert( false === strpos( $compact_summary_html, 'ggm-workshop-booking-card__button' ), 'Standalone booking summary must not include the existing CTA.' );
$booking_card_summary_html = $shortcodes->sc_workshop_booking_card_summary( array( 'id' => 77 ) );
$assert( $compact_summary_html === $booking_card_summary_html, 'The booking-card summary shortcode must use the exact shared renderer.' );
$GLOBALS['ggm_test_current_id'] = 999;
$GLOBALS['ggm_test_queried_id'] = 77;
$invalid_placeholder_html = $shortcodes->sc_workshop_booking_card_summary( array( 'id' => 123 ) );
$assert( false !== strpos( $invalid_placeholder_html, 'ggm-workshop-booking-summary' ), 'An invalid placeholder ID must fall back to the current queried Workshop.' );
$GLOBALS['ggm_test_current_id'] = 77;
$GLOBALS['ggm_test_queried_id'] = 77;
$booking_summary_classes = array();
$standalone_summary_classes = array();
preg_match_all( '/ggm-workshop-booking-summary(?:__(?:[a-z-]+)|--(?:[a-z-]+))?/', $sale_html, $booking_summary_classes );
preg_match_all( '/ggm-workshop-booking-summary(?:__(?:[a-z-]+)|--(?:[a-z-]+))?/', $compact_summary_html, $standalone_summary_classes );
$assert( array_values( array_unique( $booking_summary_classes[0] ) ) === array_values( array_unique( $standalone_summary_classes[0] ) ), 'Both shortcodes must use the exact same summary CSS classes.' );

GGM_Currency::$enabled = true;
$converted_html = $shortcodes->sc_workshop_booking_card( array( 'id' => 77 ) );
$assert( false !== strpos( $converted_html, '$14' ) && false !== strpos( $converted_html, '$70' ) && false !== strpos( $converted_html, 'You Save $56' ), 'Converted sale, regular, and savings currencies must stay consistent.' );
GGM_Currency::$enabled = false;

$GLOBALS['ggm_test_meta']['workshop_end_date'] = '2026-09-24';
$single_date_html = $shortcodes->sc_workshop_booking_card( array( 'id' => 77 ) );
$assert( 1 === substr_count( $single_date_html, 'Sept 24, 2026' ), 'Identical start/end dates must render once.' );
$GLOBALS['ggm_test_meta']['workshop_end_date'] = '2026-10-02';
$cross_month_html = $shortcodes->sc_workshop_booking_card( array( 'id' => 77 ) );
$assert( false !== strpos( $cross_month_html, 'Sept 24 – Octo 2, 2026' ), 'Cross-month compact date range failed.' );
$GLOBALS['ggm_test_meta']['workshop_mode'] = '';
$fallback_detail_html = $shortcodes->sc_workshop_booking_card( array( 'id' => 77 ) );
$assert( false !== strpos( $fallback_detail_html, '>7 Days<' ), 'Duration-derived detail fallback failed.' );
$GLOBALS['ggm_test_meta']['workshop_end_date'] = '2026-09-30';
$GLOBALS['ggm_test_meta']['workshop_mode'] = 'Live on Zoom';

foreach ( array(
	array( 'type' => 'regular', 'regular' => 1400, 'sale' => 0 ),
	array( 'type' => 'free', 'regular' => 0, 'sale' => 0 ),
	array( 'type' => 'contribution', 'regular' => 0, 'sale' => 0 ),
) as $price_state ) {
	$GLOBALS['ggm_test_price'] = $price_state;
	$html = $shortcodes->sc_workshop_booking_card( array( 'id' => 77 ) );
	$assert( false !== strpos( $html, 'ggm-workshop-booking-summary' ), ucfirst( $price_state['type'] ) . ' price state lost the shared summary.' );
	$assert( false === strpos( $html, 'ggm-workshop-booking-card__regular-price' ), ucfirst( $price_state['type'] ) . ' price state rendered false sale metadata.' );
}

$GLOBALS['ggm_test_meta'] = array(
	'ggm_workshop_form_text_heading' => 'Ready to Decode Your Glow?',
	'ggm_workshop_form_text_content' => '<p>Join the <strong>7-Day Workshop</strong>.</p><script>alert(1)</script>',
);
$form_text_html = $shortcodes->sc_workshop_form_text( array( 'id' => 77 ) );
$assert( false !== strpos( $form_text_html, '<h2 class="ggm-ws-section-heading">Ready to Decode Your Glow?</h2>' ), 'Form Text must use the shared main heading.' );
$assert( false !== strpos( $form_text_html, '<strong>7-Day Workshop</strong>' ), 'Allowed rich-text formatting was lost.' );
$assert( false === strpos( $form_text_html, '<script' ), 'Unsafe Form Text markup was not removed.' );

$GLOBALS['ggm_test_meta'] = array( 'ggm_workshop_form_text_content' => '<figure><img src="example.jpg" alt=""></figure>' );
$assert( false !== strpos( $shortcodes->sc_workshop_form_text( array( 'id' => 77 ) ), '<img' ), 'Image-only Form Text content must render.' );
$GLOBALS['ggm_test_meta'] = array();
$assert( '' === $shortcodes->sc_workshop_form_text( array( 'id' => 77 ) ), 'Empty Form Text must render nothing.' );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/ggm-public.css' );
$shortcode_source = file_get_contents( dirname( __DIR__ ) . '/public/class-ggm-shortcodes.php' );
$assert( false !== strpos( $css, '.ggm-workshop-booking-summary {' ), 'Standalone compact booking-summary CSS is missing.' );
$assert( false !== strpos( $css, 'grid-template-columns: minmax(0, 1fr) 1px minmax(0, 1fr);' ), 'Summary divider must own the exact centre grid column.' );
$assert( false === strpos( $css, '.ggm-workshop-booking-card__summary' ), 'Legacy duplicate booking-card summary CSS must be removed.' );
$assert( false === strpos( $css, '@container ggm-booking-card' ), 'A legacy breakpoint must not split the shared summary.' );
$assert( false !== strpos( $css, '.ggm-ws-form-text__content img' ), 'Responsive Form Text media CSS is missing.' );
$assert( 7 === substr_count( $shortcode_source, '<h2 class="ggm-ws-section-heading">' ), 'Every workshop section main heading must use the one shared CSS class.' );
$assert( 1 === preg_match( '/\.ggm-ws-section-heading\s*\{[^}]*text-align:\s*center\s*!important;/s', $css ), 'Shared workshop section headings must be centred.' );
$assert( 1 === preg_match( '/\.ggm-ws-why-workshop-different\s+\.ggm-ws-section-heading\s*\{[^}]*text-align:\s*left\s*!important;/s', $css ), 'The Why Workshop Is Different heading must retain its scoped left alignment.' );

$meta_box_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-ggm-meta-boxes.php' );
$assert( false !== strpos( $meta_box_source, "wp_editor( \$form_text_content, 'ggm-workshop-form-text-content-editor'" ), 'Full Form Text editor is missing from Workshop Details.' );
$assert( false !== strpos( $meta_box_source, "'media_buttons' => true" ), 'Form Text editor must retain the Media Library button.' );
$assert( false !== strpos( $meta_box_source, "wp_kses_post( (string) \$form_text_content_raw )" ), 'Form Text rich-content sanitization contract is missing.' );

echo 'Workshop booking summary and Form Text contract tests passed.' . PHP_EOL;
