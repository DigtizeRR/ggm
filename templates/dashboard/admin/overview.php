<?php
/** Protected Dashboard Home overview for administrators. */
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'ggm_dashboard_user_can_use_admin_controls' ) || ! ggm_dashboard_user_can_use_admin_controls() ) { return; }
$overview = GGM_Admin_Overview_Service::get();
if ( is_wp_error( $overview ) ) : ?>
	<div class="ggm-admin-overview-error" role="status"><?php esc_html_e( 'Administrator overview is temporarily unavailable.', 'ggm-member-dashboard' ); ?></div>
<?php return; endif; ?>
<section class="ggm-admin-overview" aria-labelledby="ggm-admin-overview-title">
	<div class="ggm-admin-overview-heading"><div><p class="ggm-admin-overview-kicker"><?php esc_html_e( 'Administration', 'ggm-member-dashboard' ); ?></p><h2 id="ggm-admin-overview-title"><?php esc_html_e( 'Overview', 'ggm-member-dashboard' ); ?></h2></div></div>
	<div class="ggm-admin-overview-grid">
		<article class="ggm-admin-overview-card"><span class="dashicons dashicons-groups" aria-hidden="true"></span><p><?php esc_html_e( 'Members', 'ggm-member-dashboard' ); ?></p><strong><?php echo esc_html( number_format_i18n( $overview['members'] ) ); ?></strong><button type="button" class="ggm-btn ggm-btn-outline ggm-dash-nav-item ggm-admin-overview-link" data-tab="admin-members"><?php esc_html_e( 'View Members', 'ggm-member-dashboard' ); ?></button></article>
		<article class="ggm-admin-overview-card"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><p><?php esc_html_e( 'Workshops', 'ggm-member-dashboard' ); ?></p><strong><?php echo esc_html( number_format_i18n( $overview['workshops'] ) ); ?></strong><button type="button" class="ggm-btn ggm-btn-outline ggm-dash-nav-item ggm-admin-overview-link" data-tab="admin-workshops"><?php esc_html_e( 'Manage Workshops', 'ggm-member-dashboard' ); ?></button></article>
		<article class="ggm-admin-overview-card"><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span><p><?php esc_html_e( 'Courses', 'ggm-member-dashboard' ); ?></p><strong><?php echo esc_html( number_format_i18n( $overview['courses'] ) ); ?></strong><button type="button" class="ggm-btn ggm-btn-outline ggm-dash-nav-item ggm-admin-overview-link" data-tab="admin-courses"><?php esc_html_e( 'Manage Courses', 'ggm-member-dashboard' ); ?></button></article>
		<article class="ggm-admin-overview-card"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><p><?php esc_html_e( 'Successful Payments', 'ggm-member-dashboard' ); ?></p><strong><?php echo esc_html( number_format_i18n( $overview['successful_payments'] ) ); ?></strong><button type="button" class="ggm-btn ggm-btn-outline ggm-admin-overview-link" data-tab="admin-payments"><?php esc_html_e( 'View Payments', 'ggm-member-dashboard' ); ?></button></article>
	</div>
</section>
