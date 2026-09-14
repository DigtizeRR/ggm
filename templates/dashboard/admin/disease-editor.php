<?php if ( ! defined( 'ABSPATH' ) || ! function_exists( 'ggm_dashboard_user_is_administrator' ) || ! ggm_dashboard_user_is_administrator() ) return; ?>
<div class="ggm-management-editor ggm-disease-editor" data-ggm-disease-editor>
	<div class="ggm-management-head">
		<button type="button" class="ggm-btn" data-ggm-disease-back><?php esc_html_e( 'Back to Diseases', 'ggm-member-dashboard' ); ?></button>
		<h2 data-ggm-disease-editor-title><?php esc_html_e( 'Add Disease', 'ggm-member-dashboard' ); ?></h2>
	</div>
	<div class="ggm-management-notice" data-ggm-disease-editor-notice role="status" aria-live="polite"></div>
	<form data-ggm-disease-form novalidate>
		<input type="hidden" name="id" value="">
		<label for="ggm-dashboard-disease-title"><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?>
			<input id="ggm-dashboard-disease-title" type="text" name="title" maxlength="255" required>
		</label>
		<label for="ggm-dashboard-disease-description"><?php esc_html_e( 'Description', 'ggm-member-dashboard' ); ?></label>
		<textarea id="ggm-dashboard-disease-description" class="wp-editor-area" name="description" rows="18"></textarea>
		<div class="ggm-management-actions">
			<button type="button" class="ggm-btn" data-ggm-disease-cancel><?php esc_html_e( 'Cancel', 'ggm-member-dashboard' ); ?></button>
			<button type="submit" class="ggm-btn ggm-btn-primary" data-ggm-disease-submit><?php esc_html_e( 'Create Disease', 'ggm-member-dashboard' ); ?></button>
		</div>
	</form>
</div>
