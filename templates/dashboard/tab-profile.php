<?php
/**
 * Dashboard Tab — Profile.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id      = get_current_user_id();
$user         = get_userdata( $user_id );
$first_name   = $user->first_name;
$last_name    = $user->last_name;
$email        = $user->user_email;
$phone        = get_user_meta( $user_id, 'ggm_phone', true );
$country_code = get_user_meta( $user_id, 'ggm_whatsapp_country_code', true ) ?: '+91';
$display_name = trim( $first_name . ' ' . $last_name ) ?: $user->display_name;
$initial      = strtoupper( substr( $display_name, 0, 1 ) ) ?: '?';

$custom_avatar    = get_user_meta( $user_id, 'ggm_avatar_url', true );
$gravatar_url     = get_avatar_url( $user_id, array( 'size' => 120, 'default' => '404' ) );
$avatar_img       = $custom_avatar ?: $gravatar_url;

$login_url    = get_permalink( (int) ggm_get_setting( 'ggm_login_page_id', 0 ) ) ?: home_url( '/workshop-login/' );
$logout_url   = wp_logout_url( $login_url );

$country_codes = class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_calling_codes() : array();
$selected_country = null;
foreach ( $country_codes as $country ) {
	if ( $country_code === $country['dial'] ) { $selected_country = $country; break; }
}
if ( ! $selected_country ) {
	$selected_country = array( 'iso'=>'IN', 'dial'=>'+91', 'name'=>'India', 'flag'=>'https://flagcdn.com/24x18/in.png' );
}
?>
<div class="ggm-profile-wrap">
	<div class="ggm-account-credit-card" style="padding:18px 20px;margin-bottom:20px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;gap:16px">
		<div><strong><?php esc_html_e( 'Account Credit', 'ggm-member-dashboard' ); ?></strong><br><small><?php esc_html_e( 'Automatically applied to workshop and course purchases.', 'ggm-member-dashboard' ); ?></small></div>
		<strong style="font-size:22px;color:#16803c;white-space:nowrap"><?php echo esc_html( ggm_get_setting( 'ggm_currency_symbol', '₹' ) . number_format_i18n( GGM_Credit::get_balance( $user_id ), 2 ) ); ?></strong>
	</div>

	<!-- Avatar + Name block -->
	<div class="ggm-profile-hero">
		<div class="ggm-profile-avatar-wrap">
			<div class="ggm-profile-avatar ggm-avatar-trigger" title="<?php esc_attr_e( 'Click to change avatar', 'ggm-member-dashboard' ); ?>">
				<span class="ggm-avatar-letter"><?php echo esc_html( $initial ); ?></span>
				<?php if ( $avatar_img ) : ?>
					<img src="<?php echo esc_url( $avatar_img ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" class="ggm-avatar-img" onerror="this.remove()">
				<?php endif; ?>
				<div class="ggm-avatar-edit-overlay">
					<span>&#9998;</span>
					<small><?php esc_html_e( 'Change', 'ggm-member-dashboard' ); ?></small>
				</div>
			</div>
		</div>
		<div class="ggm-profile-info">
			<h3 class="ggm-profile-name"><?php echo esc_html( $display_name ); ?></h3>
			<p class="ggm-profile-email"><?php echo esc_html( $email ); ?></p>
		</div>
	</div>

	<!-- Edit Profile Form -->
	<div class="ggm-profile-card">
		<h3 class="ggm-profile-section-title"><?php esc_html_e( 'Edit Profile', 'ggm-member-dashboard' ); ?></h3>
		<form id="ggm-profile-form" class="ggm-form">
			<div class="ggm-form-row">
				<div class="ggm-form-group">
					<label for="ggm_first_name"><?php esc_html_e( 'First Name', 'ggm-member-dashboard' ); ?></label>
					<input type="text" id="ggm_first_name" name="first_name" value="<?php echo esc_attr( $first_name ); ?>" class="ggm-input" required>
				</div>
				<div class="ggm-form-group">
					<label for="ggm_last_name"><?php esc_html_e( 'Last Name', 'ggm-member-dashboard' ); ?></label>
					<input type="text" id="ggm_last_name" name="last_name" value="<?php echo esc_attr( $last_name ); ?>" class="ggm-input">
				</div>
			</div>
			<div class="ggm-form-row ggm-profile-contact-row">
				<div class="ggm-form-group">
					<label><?php esc_html_e( 'Email', 'ggm-member-dashboard' ); ?></label>
					<input type="email" value="<?php echo esc_attr( $email ); ?>" class="ggm-input" disabled>
					<small><?php esc_html_e( 'Email cannot be changed here.', 'ggm-member-dashboard' ); ?></small>
				</div>
				<div class="ggm-form-group">
					<label for="ggm_phone"><?php esc_html_e( 'WhatsApp Number', 'ggm-member-dashboard' ); ?></label>
					<div class="ggm-profile-phone-control">
						<div class="ggm-profile-country-picker">
							<input type="hidden" id="ggm_country_code" name="country_code" data-country-value value="<?php echo esc_attr( $selected_country['dial'] ); ?>">
							<button type="button" class="ggm-profile-country-toggle" aria-haspopup="listbox" aria-expanded="false"><img src="<?php echo esc_url( $selected_country['flag'] ); ?>" alt=""><span><?php echo esc_html( $selected_country['dial'] ); ?></span></button>
							<div class="ggm-profile-country-menu" role="listbox">
								<input type="search" class="ggm-profile-country-search" placeholder="<?php esc_attr_e( 'Search country or code', 'ggm-member-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Search countries', 'ggm-member-dashboard' ); ?>">
								<?php foreach ( $country_codes as $country ) : $search_text = strtolower( $country['name'] . ' ' . $country['iso'] . ' ' . $country['dial'] ); ?>
									<button type="button" class="ggm-profile-country-option" role="option" data-code="<?php echo esc_attr( $country['dial'] ); ?>" data-flag="<?php echo esc_url( $country['flag'] ); ?>" data-search="<?php echo esc_attr( $search_text ); ?>" aria-selected="<?php echo $country['dial'] === $selected_country['dial'] ? 'true' : 'false'; ?>"><img src="<?php echo esc_url( $country['flag'] ); ?>" alt="" loading="lazy"><span><?php echo esc_html( $country['name'] . ' ' . $country['dial'] ); ?></span></button>
								<?php endforeach; ?>
							</div>
						</div>
						<input type="tel" id="ggm_phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" class="ggm-input" placeholder="<?php esc_attr_e( 'WhatsApp number', 'ggm-member-dashboard' ); ?>" inputmode="tel" autocomplete="tel-national">
					</div>
				</div>
			</div>
			<div id="ggm-profile-feedback" class="ggm-notice" style="display:none; margin-bottom:12px;"></div>
			<button type="submit" class="ggm-btn ggm-btn-primary"><?php esc_html_e( 'Save Changes', 'ggm-member-dashboard' ); ?></button>
		</form>
	</div>

	<!-- Change Password Form -->
	<div class="ggm-profile-card" style="margin-top:24px;">
		<h3 class="ggm-profile-section-title"><?php esc_html_e( 'Change Password', 'ggm-member-dashboard' ); ?></h3>
		<form id="ggm-change-password-form" class="ggm-form">
			<div class="ggm-form-row">
				<div class="ggm-form-group">
					<label for="ggm_new_password"><?php esc_html_e( 'New Password', 'ggm-member-dashboard' ); ?></label>
					<input type="password" id="ggm_new_password" name="new_password" class="ggm-input" autocomplete="new-password" required>
				</div>
				<div class="ggm-form-group">
					<label for="ggm_confirm_password"><?php esc_html_e( 'Confirm New Password', 'ggm-member-dashboard' ); ?></label>
					<input type="password" id="ggm_confirm_password" name="confirm_password" class="ggm-input" autocomplete="new-password" required>
				</div>
			</div>
			<div id="ggm-change-password-feedback" class="ggm-notice" style="display:none; margin-bottom:12px;"></div>
			<button type="submit" class="ggm-btn ggm-btn-primary"><?php esc_html_e( 'Change Password', 'ggm-member-dashboard' ); ?></button>
		</form>
	</div>

	<!-- Logout -->
	<div style="margin-top:24px; text-align:center;">
		<a href="<?php echo esc_url( $logout_url ); ?>" class="ggm-btn" style="background:transparent; color:#e53e3e; border:1px solid #fed7d7; padding:10px 24px;">
			<span class="dashicons dashicons-logout" aria-hidden="true" style="vertical-align:middle;margin-right:6px;"></span>
			<?php esc_html_e( 'Logout', 'ggm-member-dashboard' ); ?>
		</a>
	</div>
</div>
