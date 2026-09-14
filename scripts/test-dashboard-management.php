<?php
/** Static regression checks for Administrator Dashboard Management. */
$root = dirname( __DIR__ );
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } echo "PASS: {$message}\n"; };
$controller = file_get_contents( $root . '/modules/dashboard/class-ggm-dashboard-management.php' );
$members    = file_get_contents( $root . '/modules/dashboard/class-ggm-member-data-service.php' );
$layout     = file_get_contents( $root . '/templates/dashboard/layout.php' );
$bootstrap  = file_get_contents( $root . '/ggm-member-dashboard.php' );
$assert( false !== strpos( $controller, "current_user_can( 'manage_options' )" ) && false !== strpos( $controller, "'administrator'" ), 'Authorization requires capability and Administrator role.' );
$assert( false === strpos( $controller, 'wp_ajax_nopriv_' ), 'No public management AJAX endpoint exists.' );
foreach ( array( 'workshops', 'workshop_load', 'workshop_save', 'courses', 'course_load', 'course_save', 'members' ) as $endpoint ) $assert( false !== strpos( $controller, "ggm_dashboard_management_' . \$action" ) || false !== strpos( $controller, 'ajax_' . $endpoint ), "Management endpoint {$endpoint} is registered." );
$assert( false !== strpos( $controller, "current_user_can('edit_post'" ), 'Post updates use edit_post capability checks.' );
$assert( false !== strpos( $members, '$wpdb->prepare' ), 'Member query uses prepared SQL.' );
$assert( false !== strpos( $layout, 'is_dashboard_administrator' ), 'Navigation is rendered behind server-side authorization.' );
$assert( false !== strpos( $bootstrap, 'class-ggm-workshop-data-service.php' ) && false !== strpos( $bootstrap, 'class-ggm-course-data-service.php' ), 'Shared data services are bootstrapped.' );
$assert( false === strpos( $bootstrap, 'ggm_workshop_page' ), 'Removed composite Workshop shortcode was not reintroduced.' );
echo "Dashboard management static checks complete.\n";
