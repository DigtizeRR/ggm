<?php
/**
 * Settings partial — Customizations, Shortcodes & Appearance.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s = get_option( 'ggm_settings', array() );
?>

<!-- Shortcodes Registry -->
<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:30px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Available Shortcodes', 'ggm-member-dashboard' ); ?></h3>
	<p style="color:#666; margin-bottom:16px;"><?php esc_html_e( 'Copy any shortcode and paste it into a page or post.', 'ggm-member-dashboard' ); ?></p>
	<?php
	$shortcodes = array(
		'[ggm_login]'                             => __( 'Login / OTP authentication form', 'ggm-member-dashboard' ),
		'[ggm_dashboard]'                         => __( 'Member dashboard (requires login)', 'ggm-member-dashboard' ),
		'[ggm_checkout workshop_id="1"]'           => __( 'Razorpay checkout for a workshop or course', 'ggm-member-dashboard' ),
		'[ggm_courses]'                           => __( 'Course card grid for pages and Elementor', 'ggm-member-dashboard' ),
		'[course_curriculum]'                     => __( 'List of lessons for the current course', 'ggm-member-dashboard' ),
		'[lesson_navigation]'                     => __( 'Prev / Next lesson links', 'ggm-member-dashboard' ),
		'[lesson_count]'                          => __( 'Total lesson count for the current course', 'ggm-member-dashboard' ),
		'[back_to_course]'                        => __( 'Link back to the parent course', 'ggm-member-dashboard' ),
		'[ggm_lesson_video]'                      => __( 'Purchase-gated video embed', 'ggm-member-dashboard' ),
		'[ggm_course_short_description]'          => __( 'Course short description', 'ggm-member-dashboard' ),
		'[ggm_workshop_linked_course]'            => __( "Link to a workshop's linked course", 'ggm-member-dashboard' ),
		'[ggm_lesson_sidebar]'                    => __( 'Sidebar listing all course lessons', 'ggm-member-dashboard' ),
	);
	foreach ( $shortcodes as $code => $desc ) :
	?>
		<div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
			<code style="background:#fff; border:1px solid #ddd; padding:6px 12px; border-radius:4px; min-width:280px; cursor:pointer; user-select:all;" onclick="this.select(); document.execCommand('copy'); ggmShowCopied(this);"><?php echo esc_html( $code ); ?></code>
			<span style="color:#555; font-size:13px;"><?php echo esc_html( $desc ); ?></span>
		</div>
	<?php endforeach; ?>
</div>

<script>
function ggmShowCopied(el) {
	el.style.background = '#e8faf3';
	setTimeout(function(){ el.style.background = '#fff'; }, 1200);
}
</script>

<!-- ─── Appearance & Colors ─────────────────────────────────── -->
<h3><?php esc_html_e( 'Appearance & Colors', 'ggm-member-dashboard' ); ?></h3>
<p style="color:#666; margin-bottom:16px;"><?php esc_html_e( 'Customize colors for the Login page and Member Dashboard. Leave blank to use the default theme colors.', 'ggm-member-dashboard' ); ?></p>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:30px;">

	<!-- Login Page Colors -->
	<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
		<h4 style="margin:0 0 16px; color:#1a1a2e; display:flex; align-items:center; gap:8px;">
			<span class="dashicons dashicons-lock" style="color:#6366f1;"></span>
			<?php esc_html_e( 'Login Page', 'ggm-member-dashboard' ); ?>
		</h4>
		<table class="form-table" style="margin:0;">
			<tr>
				<th style="padding:8px 10px 8px 0; width:160px;"><label for="ggm_login_page_bg"><?php esc_html_e( 'Page Background', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_login_page_bg]" id="ggm_login_page_bg"
						value="<?php echo esc_attr( $s['ggm_login_page_bg'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#f8fafc">
				</td>
			</tr>
			<tr>
				<th style="padding:8px 10px 8px 0;"><label for="ggm_login_card_bg"><?php esc_html_e( 'Card Background', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_login_card_bg]" id="ggm_login_card_bg"
						value="<?php echo esc_attr( $s['ggm_login_card_bg'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#ffffff">
				</td>
			</tr>
			<tr>
				<th style="padding:8px 10px 8px 0;"><label for="ggm_login_primary"><?php esc_html_e( 'Button / Accent Color', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_login_primary]" id="ggm_login_primary"
						value="<?php echo esc_attr( $s['ggm_login_primary'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#6366f1">
				</td>
			</tr>
			<tr>
				<th style="padding:8px 10px 8px 0;"><label for="ggm_login_primary_hover"><?php esc_html_e( 'Button Hover Color', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_login_primary_hover]" id="ggm_login_primary_hover"
						value="<?php echo esc_attr( $s['ggm_login_primary_hover'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#4f46e5">
				</td>
			</tr>
			<tr>
				<th style="padding:8px 10px 8px 0;"><label for="ggm_login_heading"><?php esc_html_e( 'Heading / Text Color', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_login_heading]" id="ggm_login_heading"
						value="<?php echo esc_attr( $s['ggm_login_heading'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#0f172a">
				</td>
			</tr>
		</table>
	</div>

	<!-- Dashboard Colors -->
	<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
		<h4 style="margin:0 0 16px; color:#1a1a2e; display:flex; align-items:center; gap:8px;">
			<span class="dashicons dashicons-dashboard" style="color:#0e9e6e;"></span>
			<?php esc_html_e( 'Member Dashboard', 'ggm-member-dashboard' ); ?>
		</h4>
		<table class="form-table" style="margin:0;">
			<tr>
				<th style="padding:8px 10px 8px 0; width:160px;"><label for="ggm_dash_page_bg"><?php esc_html_e( 'Page Background', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_dash_page_bg]" id="ggm_dash_page_bg"
						value="<?php echo esc_attr( $s['ggm_dash_page_bg'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#f4f7f6">
				</td>
			</tr>
			<tr>
				<th style="padding:8px 10px 8px 0;"><label for="ggm_dash_sidebar_bg"><?php esc_html_e( 'Sidebar Background', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_dash_sidebar_bg]" id="ggm_dash_sidebar_bg"
						value="<?php echo esc_attr( $s['ggm_dash_sidebar_bg'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#ffffff">
				</td>
			</tr>
			<tr>
				<th style="padding:8px 10px 8px 0;"><label for="ggm_dash_primary"><?php esc_html_e( 'Primary Accent Color', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_dash_primary]" id="ggm_dash_primary"
						value="<?php echo esc_attr( $s['ggm_dash_primary'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#0e9e6e">
					<p class="description" style="margin-top:4px;"><?php esc_html_e( 'Used for nav active state, buttons, badges, gradients.', 'ggm-member-dashboard' ); ?></p>
				</td>
			</tr>
			<tr>
				<th style="padding:8px 10px 8px 0;"><label for="ggm_dash_primary_end"><?php esc_html_e( 'Gradient End Color', 'ggm-member-dashboard' ); ?></label></th>
				<td style="padding:6px 0;">
					<input type="text" name="settings[ggm_dash_primary_end]" id="ggm_dash_primary_end"
						value="<?php echo esc_attr( $s['ggm_dash_primary_end'] ?? '' ); ?>"
						class="ggm-color-picker" data-default-color="#07c98b">
					<p class="description" style="margin-top:4px;"><?php esc_html_e( 'End color for welcome banner and button gradients.', 'ggm-member-dashboard' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

</div>

<!-- ─── Tab Labels ────────────────────────────────────────────── -->
<h3><?php esc_html_e( 'Tab Labels', 'ggm-member-dashboard' ); ?></h3>
<table class="form-table">
	<tr>
		<th><label for="ggm_tab_label_home"><?php esc_html_e( 'Home Tab', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_tab_label_home]" id="ggm_tab_label_home" value="<?php echo esc_attr( $s['ggm_tab_label_home'] ?? 'Home' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="ggm_tab_label_courses"><?php esc_html_e( 'Courses Tab', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_tab_label_courses]" id="ggm_tab_label_courses" value="<?php echo esc_attr( $s['ggm_tab_label_courses'] ?? 'My Courses' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="ggm_tab_label_workshops"><?php esc_html_e( 'Workshops Tab', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_tab_label_workshops]" id="ggm_tab_label_workshops" value="<?php echo esc_attr( $s['ggm_tab_label_workshops'] ?? 'Free Workshops' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="ggm_tab_label_profile"><?php esc_html_e( 'Profile Tab', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_tab_label_profile]" id="ggm_tab_label_profile" value="<?php echo esc_attr( $s['ggm_tab_label_profile'] ?? 'Profile' ); ?>" class="regular-text"></td>
	</tr>
</table>

<!-- ─── Currency ─────────────────────────────────────────────── -->
<h3><?php esc_html_e( 'Currency', 'ggm-member-dashboard' ); ?></h3>
<table class="form-table">
	<tr>
		<th><label for="ggm_currency_symbol"><?php esc_html_e( 'Currency Symbol', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="text" name="settings[ggm_currency_symbol]" id="ggm_currency_symbol" value="<?php echo esc_attr( $s['ggm_currency_symbol'] ?? '₹' ); ?>" class="small-text">
			<span class="description"><?php esc_html_e( 'e.g. ₹, $, £', 'ggm-member-dashboard' ); ?></span>
		</td>
	</tr>
	<tr>
		<th><label for="ggm_currency"><?php esc_html_e( 'Currency Code', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_currency]" id="ggm_currency" value="<?php echo esc_attr( $s['ggm_currency'] ?? 'INR' ); ?>" class="small-text" maxlength="3"></td>
	</tr>
</table>

<!-- ─── Course / Lesson Labels ───────────────────────────────── -->
<h3><?php esc_html_e( 'Course / Lesson Labels', 'ggm-member-dashboard' ); ?></h3>
<table class="form-table">
	<tr>
		<th><label for="ggm_label_instructor"><?php esc_html_e( 'Instructor Label', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_label_instructor]" id="ggm_label_instructor" value="<?php echo esc_attr( $s['ggm_label_instructor'] ?? 'Instructor' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="ggm_label_duration"><?php esc_html_e( 'Duration Label', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_label_duration]" id="ggm_label_duration" value="<?php echo esc_attr( $s['ggm_label_duration'] ?? 'Duration' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="ggm_label_language"><?php esc_html_e( 'Language Label', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_label_language]" id="ggm_label_language" value="<?php echo esc_attr( $s['ggm_label_language'] ?? 'Language' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="ggm_label_videos"><?php esc_html_e( 'Videos Label', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_label_videos]" id="ggm_label_videos" value="<?php echo esc_attr( $s['ggm_label_videos'] ?? 'Videos' ); ?>" class="regular-text"></td>
	</tr>
</table>

<!-- ─── Action Button Text ───────────────────────────────────── -->
<h3><?php esc_html_e( 'Action Button Text', 'ggm-member-dashboard' ); ?></h3>
<table class="form-table">
	<tr>
		<th><label for="ggm_btn_start_learning"><?php esc_html_e( 'Start Learning', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_btn_start_learning]" id="ggm_btn_start_learning" value="<?php echo esc_attr( $s['ggm_btn_start_learning'] ?? 'Start Learning' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="ggm_btn_watch_free"><?php esc_html_e( 'Watch Free Button', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_btn_watch_free]" id="ggm_btn_watch_free" value="<?php echo esc_attr( $s['ggm_btn_watch_free'] ?? 'Watch Free' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="ggm_btn_enroll_now"><?php esc_html_e( 'Enroll / Unlock Button', 'ggm-member-dashboard' ); ?></label></th>
		<td><input type="text" name="settings[ggm_btn_enroll_now]" id="ggm_btn_enroll_now" value="<?php echo esc_attr( $s['ggm_btn_enroll_now'] ?? 'Enroll Now' ); ?>" class="regular-text"></td>
	</tr>
</table>
