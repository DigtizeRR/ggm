<?php
/** Static contract checks for the protected Dashboard administrator overview. */
$root = dirname( __DIR__ );
$failures = array();
function ggm_overview_expect( $condition, $message ) { global $failures; if ( ! $condition ) $failures[] = $message; }
$service = file_get_contents( $root . '/modules/dashboard/class-ggm-admin-overview-service.php' );
$members = file_get_contents( $root . '/modules/dashboard/class-ggm-member-data-service.php' );
$template = file_get_contents( $root . '/templates/dashboard/admin/overview.php' );
$home = file_get_contents( $root . '/templates/dashboard/tab-home.php' );
$css = file_get_contents( $root . '/assets/css/ggm-dashboard.css' );
$js = file_get_contents( $root . '/assets/js/ggm-dashboard.js' );
ggm_overview_expect( false !== strpos( $members, 'function count_customers()' ), 'Missing shared Customer count method.' );
ggm_overview_expect( false !== strpos( $members, 'customer_from_clause' ), 'Customer count is not factored through the canonical member source.' );
ggm_overview_expect( false !== strpos( $service, "status = %s" ) && false !== strpos( $service, "'success'" ), 'Successful-payment count is not status=success only.' );
ggm_overview_expect( false !== strpos( $service, "array( 'workshop', 'ggm_workshop' )" ), 'Workshop total does not cover both post types.' );
ggm_overview_expect( false !== strpos( $service, "array( 'course' )" ), 'Course total is missing.' );
ggm_overview_expect( false === strpos( $service, 'wp_ajax_' ), 'Overview must not register AJAX endpoints.' );
ggm_overview_expect( false !== strpos( $template, 'ggm_dashboard_user_can_use_admin_controls' ), 'Overview template lacks the server-side dashboard-controls guard.' );
ggm_overview_expect( false !== strpos( $home, "templates/dashboard/admin/overview.php" ) && false !== strpos( $home, 'ggm-home-course' ), 'Overview is not rendered before replaceable Home content.' );
ggm_overview_expect( false !== strpos( $css, 'overflow-y: auto;' ) && false !== strpos( $css, '.ggm-dash-nav' ), 'Desktop sidebar navigation is not scrollable.' );
ggm_overview_expect( false !== strpos( $js, '.ggm-admin-overview-link[data-tab]' ), 'Overview management quick links do not use tab switching.' );
if ( $failures ) { fwrite( STDERR, "FAIL\n- " . implode( "\n- ", $failures ) . "\n" ); exit( 1 ); }
echo "PASS: Dashboard administrator overview static contract checks.\n";
