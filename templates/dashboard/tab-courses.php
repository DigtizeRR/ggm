<?php
/**
 * Dashboard Tab — My Courses.
 * JS renders into #ggm-courses-list via renderCourses().
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div id="ggm-courses-list">
	<div class="ggm-home-section">
		<h3 class="ggm-section-title"><?php esc_html_e( 'My Enrolled Courses', 'ggm-member-dashboard' ); ?></h3>
		<div class="ggm-cards-grid">
			<div class="ggm-card ggm-empty">
				<p style="color:#94a3b8; text-align:center; padding:20px 0;"><?php esc_html_e( 'Loading courses…', 'ggm-member-dashboard' ); ?></p>
			</div>
		</div>
	</div>
</div>
