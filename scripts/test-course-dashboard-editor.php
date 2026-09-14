<?php
$root = dirname( __DIR__ );
$files = array(
	$root . '/templates/dashboard/admin/course-editor.php',
	$root . '/modules/course/class-ggm-course-data-service.php',
	$root . '/assets/js/ggm-dashboard-course-editor.js',
);
foreach ( $files as $file ) if ( ! file_exists( $file ) ) { fwrite( STDERR, "Missing $file\n" ); exit( 1 ); }
$template = file_get_contents( $files[0] ); $service = file_get_contents( $files[1] ); $script = file_get_contents( $files[2] );
$checks = array(
	'No raw lesson JSON textarea' => false === strpos( $template, 'name="lessons"' ),
	'No manual attachment ID field' => false === strpos( $template, 'attachment ID' ),
	'Media selector exists' => false !== strpos( $template, 'data-ggm-course-image-select' ) && false !== strpos( $script, 'wp.media' ),
	'Native lesson relationship preserved' => false !== strpos( $service, "'course'" ) && false !== strpos( $service, "'lesson_order'" ),
	'Safe detach only' => false !== strpos( $service, "delete_post_meta( \$lesson_id, 'course' )" ) && false === strpos( $service, 'wp_delete_post' ),
	'Lesson details preserved' => false !== strpos( $service, "'lesson_focus_areas'" ) && false !== strpos( $service, "'lesson_resources_pdf'" ),
);
foreach ( $checks as $label => $pass ) { echo ( $pass ? 'PASS' : 'FAIL' ) . ": $label\n"; if ( ! $pass ) exit( 1 ); }
