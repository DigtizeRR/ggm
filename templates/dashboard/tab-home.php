<?php
/**
 * Dashboard Tab — Home.
 * JS renders into #ggm-home-course via renderHome().
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<?php if ( function_exists( 'ggm_dashboard_user_can_use_admin_controls' ) && ggm_dashboard_user_can_use_admin_controls() ) include GGM_PLUGIN_DIR . 'templates/dashboard/admin/overview.php'; ?>
<div id="ggm-home-course">
	<div class="ggm-welcome-banner-skeleton">
		<p style="color:#94a3b8; text-align:center; padding:20px 0;"><?php esc_html_e( 'Loading your dashboard…', 'ggm-member-dashboard' ); ?></p>
	</div>
</div>
