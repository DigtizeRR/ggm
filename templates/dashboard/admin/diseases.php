<?php if ( ! defined( 'ABSPATH' ) || ! function_exists( 'ggm_dashboard_user_can_use_admin_controls' ) || ! ggm_dashboard_user_can_use_admin_controls() ) return; ?>
<div class="ggm-management-panel ggm-diseases-panel" data-ggm-disease-manager>
	<div class="ggm-management-head">
		<h2><?php esc_html_e( 'Diseases', 'ggm-member-dashboard' ); ?></h2>
		<button type="button" class="ggm-btn ggm-btn-primary" data-ggm-disease-create><?php esc_html_e( '+ Add Disease', 'ggm-member-dashboard' ); ?></button>
	</div>
	<div class="ggm-management-filters">
		<label class="screen-reader-text" for="ggm-disease-dashboard-search"><?php esc_html_e( 'Search diseases', 'ggm-member-dashboard' ); ?></label>
		<input id="ggm-disease-dashboard-search" type="search" placeholder="<?php esc_attr_e( 'Search diseases', 'ggm-member-dashboard' ); ?>" data-ggm-disease-search>
		<button type="button" class="ggm-btn" data-ggm-disease-search-button><?php esc_html_e( 'Search', 'ggm-member-dashboard' ); ?></button>
	</div>
	<div class="ggm-management-notice" data-ggm-disease-list-notice role="status" aria-live="polite"></div>
	<div class="ggm-management-results" data-ggm-disease-results aria-live="polite"><p><?php esc_html_e( 'Loading…', 'ggm-member-dashboard' ); ?></p></div>
</div>
