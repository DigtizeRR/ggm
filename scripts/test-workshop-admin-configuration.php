<?php
/** Focused static/data-preservation checks for Workshop admin configuration. */
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = null ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $value ) ); }
function ggm_get_setting( $key, $default = array() ) { return $default; }
require dirname( __DIR__ ) . '/includes/class-ggm-workshop-admin-config.php';

$failures = array();
$assert = static function( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };
$config = GGM_Workshop_Admin_Config::sanitise_submitted( array(
	'sections' => array( array( 'id' => 'faq', 'enabled' => '1' ), array( 'id' => 'invalid', 'enabled' => '1' ), array( 'id' => 'faq', 'enabled' => '0' ) ),
	'fields' => array( 'workshop_details' => array( array( 'id' => 'duration', 'enabled' => '0' ), array( 'id' => 'unknown', 'enabled' => '1' ) ) ),
) );
$assert( 'faq' === $config['sections'][0]['id'] && true === $config['sections'][0]['enabled'], 'Configured section order/visibility was not preserved.' );
$assert( count( $config['sections'] ) === count( GGM_Workshop_Admin_Config::sections() ), 'Unknown or duplicate section IDs were not safely normalised.' );
$assert( 'duration' === $config['fields']['workshop_details'][0]['id'] && false === $config['fields']['workshop_details'][0]['enabled'], 'Configured field order/visibility was not preserved.' );
$assert( count( $config['fields']['workshop_details'] ) === count( GGM_Workshop_Admin_Config::fields()['workshop_details'] ), 'Missing Workshop Details fields were not restored from defaults.' );

$root = dirname( __DIR__ );
$shortcodes = file_get_contents( $root . '/public/class-ggm-shortcodes.php' );
$css = file_get_contents( $root . '/assets/css/ggm-public.css' );
$metaboxes = file_get_contents( $root . '/includes/class-ggm-meta-boxes.php' );
$assert( false === strpos( $shortcodes, "add_shortcode( 'ggm_workshop_page'" ) && false === strpos( $shortcodes, 'function sc_workshop_page' ), 'Removed composite frontend shortcode is still registered or rendered.' );
$assert( false === strpos( $css, '.ggm-workshop-page' ), 'Composite frontend CSS remains.' );
$assert( false === strpos( $metaboxes, 'update_post_meta( $post_id, $rep, wp_json_encode( array() ) );' ) || false !== strpos( $metaboxes, 'print_workshop_admin_configuration_js' ), 'Repeater preservation implementation is missing from the Workshop editor path.' );
$assert( false !== strpos( $metaboxes, "toggle(!!item.enabled)" ), 'Hidden editor controls are not CSS-hidden/submitted for data preservation.' );

if ( $failures ) { fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL ); exit( 1 ); }
echo "Workshop admin configuration checks passed.\n";
