<?php
/** Static regression checks for the Dashboard Home performance boundary. */
$root = dirname( __DIR__ ); $failures = array();
function ggm_performance_expect( $condition, $message ) { global $failures; if ( ! $condition ) $failures[] = $message; }
$dashboard = file_get_contents( $root . '/modules/dashboard/class-ggm-dashboard.php' ); $overview = file_get_contents( $root . '/modules/dashboard/class-ggm-admin-overview-service.php' ); $members = file_get_contents( $root . '/modules/dashboard/class-ggm-member-data-service.php' ); $js = file_get_contents( $root . '/assets/js/ggm-dashboard.js' );
$start = strpos( $dashboard, 'public function ajax_get_dashboard_data()' ); $end = strpos( $dashboard, 'private function get_course_lesson_counts' ); $home = substr( $dashboard, $start, $end - $start );
ggm_performance_expect( 1 === substr_count( $dashboard, "wp_ajax_ggm_dashboard_ajax" ), 'The primary Home AJAX action must be registered once.' );
ggm_performance_expect( false !== strpos( $home, "'post_type'      => 'course'" ), 'Home course cards must query course posts only.' );
ggm_performance_expect( false === strpos( $home, 'GGM_Lesson::get_for_course' ) && false === strpos( $home, 'wp_oembed_get' ), 'Initial Home request must not hydrate lessons or run oEmbed.' );
ggm_performance_expect( false !== strpos( $dashboard, 'GROUP BY pm.meta_value' ), 'Course-card lesson counts must be batched.' );
ggm_performance_expect( false !== strpos( $dashboard, 'wp_ajax_ggm_dashboard_course_lessons' ) && false !== strpos( $dashboard, 'ggm_user_has_course_access( $course_id, $user_id )' ), 'On-demand lesson request lacks authenticated course access control.' );
ggm_performance_expect( false === strpos( $home, 'GGM_Member_Data_Service::query' ), 'Normal Home must not load the Customer member list.' );
ggm_performance_expect( false !== strpos( $overview, 'COUNT(*) FROM {$payments_table} WHERE status = %s' ) && false !== strpos( $members, 'COUNT(DISTINCT u.ID)' ), 'Overview totals must use aggregates.' );
ggm_performance_expect( 1 === substr_count( $js, "action: 'ggm_dashboard_ajax'" ), 'Client must issue one primary Home AJAX action.' );
ggm_performance_expect( false === strpos( $js, 'setInterval(loadData' ) && false === strpos( $js, 'setTimeout(loadData' ), 'Dashboard must not automatically retry Home loading.' );
if ( $failures ) { fwrite( STDERR, "FAIL\n- " . implode( "\n- ", $failures ) . "\n" ); exit( 1 ); }
echo "PASS: Dashboard performance static contract checks.\n";
