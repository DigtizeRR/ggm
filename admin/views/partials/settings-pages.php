<?php
/**
 * Settings Partial — Page Routing.
 *
 * Displays dropdown list of all published pages for routing targets.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pages         = get_pages( array( 'post_status' => 'publish' ) );
$login_page_id = (int) ( $settings['ggm_login_page_id'] ?? 0 );
$dash_page_id  = (int) ( $settings['ggm_dashboard_page_id'] ?? 0 );

// Detect if pages look swapped (both IDs set but seem reversed).
$login_title = $login_page_id ? get_the_title( $login_page_id ) : '';
$dash_title  = $dash_page_id  ? get_the_title( $dash_page_id )  : '';
$looks_swapped = $login_page_id && $dash_page_id
	&& (
		stripos( $login_title, 'dashboard' ) !== false
		|| stripos( $dash_title, 'login' ) !== false
	);
$custom_links_json = $settings['ggm_dashboard_custom_links'] ?? '[]';
$custom_links      = is_array( $custom_links_json ) ? $custom_links_json : json_decode( $custom_links_json, true );
$custom_links      = is_array( $custom_links ) ? $custom_links : array();
?>
<div class="ggm-settings-section-header">
	<h3><?php esc_html_e( 'Page Routing Settings', 'ggm-member-dashboard' ); ?></h3>
	<p><?php esc_html_e( 'Select the primary WordPress pages dedicated to login actions and member dashboards.', 'ggm-member-dashboard' ); ?></p>
</div>

<?php if ( $looks_swapped ) : ?>
<div style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px 16px; margin-bottom:16px; border-radius:4px;">
	<strong>⚠ Pages may be swapped.</strong>
	Your Login Page is set to "<strong><?php echo esc_html( $login_title ); ?></strong>"
	and Dashboard Page is "<strong><?php echo esc_html( $dash_title ); ?></strong>".
	Please verify these are correct and swap them if needed.
</div>
<?php endif; ?>

<table class="form-table ggm-settings-table">
	<tr>
		<th><label for="ggm-dashboard-logo"><?php esc_html_e( 'Dashboard Logo', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<div class="ggm-image-upload-wrap" style="display: flex; align-items: center; gap: 8px;">
				<input type="text" name="settings[ggm_dashboard_logo]" id="ggm-dashboard-logo" value="<?php echo esc_url( $settings['ggm_dashboard_logo'] ?? '' ); ?>" class="regular-text ggm-media-url">
				<button type="button" class="button ggm-media-upload-btn" data-target="ggm-dashboard-logo"><?php esc_html_e( 'Upload Logo', 'ggm-member-dashboard' ); ?></button>
				<button type="button" class="button ggm-media-clear-btn" data-target="ggm-dashboard-logo"><?php esc_html_e( 'Clear', 'ggm-member-dashboard' ); ?></button>
			</div>
			<?php 
			$logo_url = $settings['ggm_dashboard_logo'] ?? '';
			?>
			<div style="margin-top: 10px;">
				<img id="ggm-dashboard-logo-preview" src="<?php echo esc_url( $logo_url ); ?>" style="max-height: 50px; display: <?php echo $logo_url ? 'block' : 'none'; ?>;" alt="Logo Preview">
			</div>
			<p class="description"><?php esc_html_e( 'Upload or select an image for your member dashboard logo.', 'ggm-member-dashboard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-login-page-id"><?php esc_html_e( 'Login Page', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<select name="settings[ggm_login_page_id]" id="ggm-login-page-id" class="regular-text">
				<option value=""><?php esc_html_e( '— Select Page —', 'ggm-member-dashboard' ); ?></option>
				<?php foreach ( $pages as $p ) : ?>
					<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $settings['ggm_login_page_id'] ?? '', $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php esc_html_e( 'Select the page dedicated to user login. Paste this shortcode on the selected page:', 'ggm-member-dashboard' ); ?> 
				<code>[ggm_login]</code>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-dashboard-page-id"><?php esc_html_e( 'Dashboard Page', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<select name="settings[ggm_dashboard_page_id]" id="ggm-dashboard-page-id" class="regular-text">
				<option value=""><?php esc_html_e( '— Select Page —', 'ggm-member-dashboard' ); ?></option>
				<?php foreach ( $pages as $p ) : ?>
					<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $settings['ggm_dashboard_page_id'] ?? '', $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php esc_html_e( 'Select the page dedicated to the member dashboard. Paste this shortcode on the selected page:', 'ggm-member-dashboard' ); ?> 
				<code>[ggm_dashboard]</code>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-signup-page-id"><?php esc_html_e( 'Signup Page', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<select name="settings[ggm_signup_page_id]" id="ggm-signup-page-id" class="regular-text">
				<option value=""><?php esc_html_e( '— Select Page —', 'ggm-member-dashboard' ); ?></option>
				<?php foreach ( $pages as $p ) : ?>
					<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $settings['ggm_signup_page_id'] ?? '', $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php esc_html_e( 'Select the page where new visitors can create an account. The "Account not found" message on the login page links here.', 'ggm-member-dashboard' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-checkout-page-id"><?php esc_html_e( 'Checkout Page', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<select name="settings[ggm_checkout_page_id]" id="ggm-checkout-page-id" class="regular-text">
				<option value=""><?php esc_html_e( '— Select Page —', 'ggm-member-dashboard' ); ?></option>
				<?php foreach ( $pages as $p ) : ?>
					<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $settings['ggm_checkout_page_id'] ?? '', $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php esc_html_e( 'Select the page dedicated to workshop/membership checkout. Paste this shortcode on the selected page:', 'ggm-member-dashboard' ); ?>
				<code>[ggm_checkout]</code>
				<?php esc_html_e( 'Used by "Enroll Now" / "Join Now" buttons and the membership checkout flow.', 'ggm-member-dashboard' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-redirect-after-login"><?php esc_html_e( 'Redirect After Login', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="text" name="settings[ggm_redirect_after_login]" id="ggm-redirect-after-login" value="<?php echo esc_attr( $settings['ggm_redirect_after_login'] ?? '/dashboard/' ); ?>" class="regular-text" placeholder="/dashboard/">
			<p class="description"><?php esc_html_e( 'URL path where users are sent immediately after successful login verification.', 'ggm-member-dashboard' ); ?></p>
		</td>
	</tr>
</table>

<div class="ggm-custom-links-settings" style="margin-top:28px; padding-top:24px; border-top:1px solid #e2e8f0;">
	<h3 style="margin:0 0 6px;"><?php esc_html_e( 'Dashboard Custom Links', 'ggm-member-dashboard' ); ?></h3>
	<p class="description" style="margin-bottom:16px;"><?php esc_html_e( 'Add links to the member dashboard navigation. Drag with the arrow buttons to control their order.', 'ggm-member-dashboard' ); ?></p>
	<input type="hidden" name="settings[ggm_dashboard_custom_links]" id="ggm-dashboard-custom-links-json" value="<?php echo esc_attr( wp_json_encode( $custom_links ) ); ?>">
	<div id="ggm-dashboard-custom-links-list"></div>
	<button type="button" class="button button-secondary" id="ggm-add-dashboard-link" style="margin-top:10px;">+ <?php esc_html_e( 'Add Custom Link', 'ggm-member-dashboard' ); ?></button>
</div>

<script>
(function () {
	'use strict';
	var hidden = document.getElementById('ggm-dashboard-custom-links-json');
	var list = document.getElementById('ggm-dashboard-custom-links-list');
	var add = document.getElementById('ggm-add-dashboard-link');
	if (!hidden || !list || !add) return;
	var links = [];
	try { links = JSON.parse(hidden.value || '[]'); } catch (e) { links = []; }
	if (!Array.isArray(links)) links = [];

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>"]/g, function (char) {
			return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char];
		});
	}
	function sync() { hidden.value = JSON.stringify(links); }
	function readRows() {
		links = Array.prototype.map.call(list.querySelectorAll('.ggm-custom-link-row'), function (row) {
			return {
				label: row.querySelector('[data-field="label"]').value.trim(),
				url: row.querySelector('[data-field="url"]').value.trim(),
				icon: row.querySelector('[data-field="icon"]').value,
				new_tab: row.querySelector('[data-field="new_tab"]').checked ? 1 : 0
			};
		});
		sync();
	}
	function render() {
		list.innerHTML = links.map(function (link, index) {
			return '<div class="ggm-custom-link-row" style="display:grid;grid-template-columns:minmax(140px,1fr) minmax(220px,2fr) minmax(150px,1fr) auto;gap:10px;align-items:center;padding:12px;margin-bottom:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">' +
			'<input type="text" data-field="label" class="regular-text" value="' + escapeHtml(link.label) + '" placeholder="Link label">' +
			'<input type="text" data-field="url" class="regular-text" value="' + escapeHtml(link.url) + '" placeholder="https://example.com/page or /resources/">' +
			'<select data-field="icon"><option value="dashicons-admin-links"' + (link.icon === 'dashicons-admin-links' ? ' selected' : '') + '>Link</option><option value="dashicons-whatsapp"' + (link.icon === 'dashicons-whatsapp' ? ' selected' : '') + '>WhatsApp</option><option value="dashicons-video-alt3"' + (link.icon === 'dashicons-video-alt3' ? ' selected' : '') + '>Video</option><option value="dashicons-calendar-alt"' + (link.icon === 'dashicons-calendar-alt' ? ' selected' : '') + '>Calendar</option><option value="dashicons-download"' + (link.icon === 'dashicons-download' ? ' selected' : '') + '>Download</option><option value="dashicons-book"' + (link.icon === 'dashicons-book' ? ' selected' : '') + '>Resource</option><option value="dashicons-email"' + (link.icon === 'dashicons-email' ? ' selected' : '') + '>Email</option></select>' +
			'<div style="display:flex;align-items:center;gap:5px;white-space:nowrap;"><label title="Open in new tab"><input type="checkbox" data-field="new_tab"' + (link.new_tab ? ' checked' : '') + '> New tab</label><button type="button" class="button" data-action="up" data-index="' + index + '" title="Move up">↑</button><button type="button" class="button" data-action="down" data-index="' + index + '" title="Move down">↓</button><button type="button" class="button-link-delete" data-action="remove" data-index="' + index + '">Remove</button></div></div>';
		}).join('');
	}
	add.addEventListener('click', function () { readRows(); links.push({label:'',url:'',icon:'dashicons-admin-links',new_tab:0}); render(); });
	list.addEventListener('input', readRows);
	list.addEventListener('change', readRows);
	list.addEventListener('click', function (event) {
		var button = event.target.closest('[data-action]');
		if (!button) return;
		readRows();
		var index = parseInt(button.getAttribute('data-index'), 10);
		var action = button.getAttribute('data-action');
		if (action === 'remove') links.splice(index, 1);
		if (action === 'up' && index > 0) { var up = links[index - 1]; links[index - 1] = links[index]; links[index] = up; }
		if (action === 'down' && index < links.length - 1) { var down = links[index + 1]; links[index + 1] = links[index]; links[index] = down; }
		sync(); render();
	});
	document.getElementById('ggm-settings-form').addEventListener('submit', readRows);
	render();
})();
</script>
