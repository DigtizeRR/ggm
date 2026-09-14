<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="ggm-management-editor ggm-workshop-editor" data-editor="workshop">
	<div class="ggm-management-head"><button type="button" class="ggm-btn" data-ggm-back="workshops"><?php esc_html_e( 'Back to Workshops', 'ggm-member-dashboard' ); ?></button><h2 data-ggm-editor-title><?php esc_html_e( 'Create Workshop', 'ggm-member-dashboard' ); ?></h2></div>
	<div class="ggm-management-notice" role="status" aria-live="polite"></div>
	<form data-ggm-editor-form novalidate><input type="hidden" name="id">
		<?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/partials/workshop-editor-schema.php'; ?>
		<div class="ggm-management-actions"><button type="button" class="ggm-btn" data-ggm-cancel="workshops"><?php esc_html_e( 'Cancel', 'ggm-member-dashboard' ); ?></button><button class="ggm-btn" name="status" value="draft"><?php esc_html_e( 'Save Draft', 'ggm-member-dashboard' ); ?></button><button class="ggm-btn ggm-btn-primary" name="status" value="publish"><?php esc_html_e( 'Publish', 'ggm-member-dashboard' ); ?></button></div>
	</form>
</div>
