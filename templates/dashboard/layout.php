<?php
/**
 * Dashboard Shell layout.
 *
 * Implements sidebar/bottom nav structures, header topbars,
 * and loading placeholders.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id       = get_current_user_id();
$user          = get_userdata( $user_id );
$name          = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
$initial       = strtoupper( substr( $name, 0, 1 ) ) ?: '?';
$login_page_id = (int) ggm_get_setting( 'ggm_login_page_id', 0 );
$login_url     = $login_page_id ? get_permalink( $login_page_id ) : home_url( '/workshop-login/' );
$logout_url    = wp_logout_url( $login_url );
$wallet_balance = class_exists( 'GGM_Credit' ) ? GGM_Credit::get_balance( $user_id ) : 0;
$wallet_display = ggm_get_setting( 'ggm_currency_symbol', '₹' ) . number_format_i18n( $wallet_balance, 2 );

// Logo: check ggm_settings first, then wp option, then site icon.
$logo_url = ggm_get_setting( 'ggm_dashboard_logo', '' );
if ( empty( $logo_url ) ) {
	$logo_url = get_option( 'ggm_dashboard_logo', '' );
}
if ( empty( $logo_url ) ) {
	$site_icon_id = get_option( 'site_icon' );
	$logo_url     = $site_icon_id ? wp_get_attachment_image_url( $site_icon_id, array( 40, 40 ) ) : '';
}

// Avatar: custom meta → browser-loaded Gravatar → letter initial. Avoid a
// blocking server-side HEAD request on the dashboard's critical render path.
$custom_avatar = get_user_meta( $user_id, 'ggm_avatar_url', true );
$gravatar_url  = get_avatar_url( $user_id, array( 'size' => 80, 'default' => '404' ) );
$avatar_img  = $custom_avatar ?: $gravatar_url;
$custom_links_value = ggm_get_setting( 'ggm_dashboard_custom_links', '[]' );
$custom_links       = is_array( $custom_links_value ) ? $custom_links_value : json_decode( $custom_links_value, true );
$custom_links       = is_array( $custom_links ) ? $custom_links : array();
$is_dashboard_administrator = function_exists( 'ggm_dashboard_user_is_administrator' ) && ggm_dashboard_user_is_administrator();
?>
<div id="ggm-dash" class="ggm-dash-wrap">
	<!-- Loader Spinner -->
	<div class="ggm-dash-loader" role="status" aria-live="polite" aria-hidden="true" aria-label="<?php esc_attr_e( 'Loading dashboard data', 'ggm-member-dashboard' ); ?>">
		<div class="ggm-loader-spinner"></div>
		<span class="screen-reader-text"><?php esc_html_e( 'Loading dashboard data…', 'ggm-member-dashboard' ); ?></span>
	</div>

	<!-- Left Sidebar (Desktop) -->
	<aside class="ggm-dash-sidebar">
		<div class="ggm-dash-logo">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:block;">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" class="ggm-logo-img" style="max-height: 40px; max-width: 100%; display: block;">
				<?php else : ?>
					<h3><?php echo esc_html( ggm_get_setting( 'company_name', get_bloginfo( 'name' ) ) ); ?></h3>
				<?php endif; ?>
			</a>
		</div>
		<nav class="ggm-dash-nav">
			<button class="ggm-dash-nav-item active" data-tab="home">
				<span class="dashicons dashicons-admin-home"></span>
				<span class="nav-text"><?php esc_html_e( 'Home', 'ggm-member-dashboard' ); ?></span>
			</button>
			<button class="ggm-dash-nav-item" data-tab="courses">
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<span class="nav-text"><?php esc_html_e( 'My Courses', 'ggm-member-dashboard' ); ?></span>
			</button>
			<button class="ggm-dash-nav-item" data-tab="free">
				<span class="dashicons dashicons-awards"></span>
				<span class="nav-text"><?php esc_html_e( 'Workshops', 'ggm-member-dashboard' ); ?></span>
			</button>
			<?php if ( $is_dashboard_administrator ) : ?>
			<div class="ggm-dash-nav-divider"><?php esc_html_e( 'Administration', 'ggm-member-dashboard' ); ?></div>
			<button class="ggm-dash-nav-item" data-tab="admin-workshops"><span class="dashicons dashicons-calendar-alt"></span><span class="nav-text"><?php esc_html_e( 'Manage Workshops', 'ggm-member-dashboard' ); ?></span></button>
			<button class="ggm-dash-nav-item" data-tab="admin-courses"><span class="dashicons dashicons-welcome-learn-more"></span><span class="nav-text"><?php esc_html_e( 'Manage Courses', 'ggm-member-dashboard' ); ?></span></button>
			<button class="ggm-dash-nav-item" data-tab="admin-members"><span class="dashicons dashicons-groups"></span><span class="nav-text"><?php esc_html_e( 'Members', 'ggm-member-dashboard' ); ?></span></button>
			<button class="ggm-dash-nav-item" data-tab="admin-payments"><span class="dashicons dashicons-money-alt"></span><span class="nav-text"><?php esc_html_e( 'Payments', 'ggm-member-dashboard' ); ?></span></button>
			<button class="ggm-dash-nav-item" data-tab="admin-diseases"><span class="dashicons dashicons-heart"></span><span class="nav-text"><?php esc_html_e( 'Diseases', 'ggm-member-dashboard' ); ?></span></button>
			<?php endif; ?>
			<button class="ggm-dash-nav-item" data-tab="health">
				<span class="dashicons dashicons-clipboard"></span>
				<span class="nav-text"><?php esc_html_e( 'Health Information', 'ggm-member-dashboard' ); ?></span>
			</button>
			<button class="ggm-dash-nav-item" data-tab="disease">
				<span class="dashicons dashicons-heart"></span>
				<span class="nav-text"><?php esc_html_e( 'Disease', 'ggm-member-dashboard' ); ?></span>
			</button>
			<button class="ggm-dash-nav-item" data-tab="profile">
				<span class="dashicons dashicons-admin-users"></span>
				<span class="nav-text"><?php esc_html_e( 'Profile', 'ggm-member-dashboard' ); ?></span>
			</button>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
			<button class="ggm-dash-nav-item" data-tab="woocommerce">
				<span class="dashicons dashicons-store"></span>
				<span class="nav-text"><?php esc_html_e( 'My Account', 'ggm-member-dashboard' ); ?></span>
			</button>
			<?php endif; ?>
			<?php foreach ( $custom_links as $custom_link ) :
				$link_label = sanitize_text_field( $custom_link['label'] ?? '' );
				$link_url   = esc_url( $custom_link['url'] ?? '' );
				$link_icon  = sanitize_html_class( $custom_link['icon'] ?? 'dashicons-admin-links' );
				if ( ! $link_label || ! $link_url ) {
					continue;
				}
				$new_tab = ! empty( $custom_link['new_tab'] );
			?>
			<a class="ggm-dash-nav-item ggm-custom-nav-link" href="<?php echo $link_url; ?>" <?php echo $new_tab ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
				<span class="dashicons <?php echo esc_attr( $link_icon ); ?>"></span>
				<span class="nav-text"><?php echo esc_html( $link_label ); ?></span>
			</a>
			<?php endforeach; ?>
		</nav>
		<div class="ggm-dash-sidebar-footer">
			<a href="<?php echo esc_url( $logout_url ); ?>" id="ggm-dash-logout-btn" class="ggm-dash-nav-item logout-link">
				<span class="dashicons dashicons-exit" aria-hidden="true"></span>
				<span class="nav-text"><?php esc_html_e( 'Logout', 'ggm-member-dashboard' ); ?></span>
			</a>
		</div>
	</aside>

	<!-- Main Body Wrapper -->
	<div class="ggm-dash-main-container">
		<!-- Dashboard Top Bar -->
		<header class="ggm-dash-topbar">
			<div class="ggm-topbar-greeting">
				<span class="ggm-greeting-hi"><?php printf( esc_html__( 'Hi, %s', 'ggm-member-dashboard' ), esc_html( $name ) ); ?></span>
				<span class="ggm-greeting-welcome"><?php printf( esc_html__( 'Welcome to %s', 'ggm-member-dashboard' ), esc_html( ggm_get_setting( 'company_name', get_bloginfo( 'name' ) ) ) ); ?></span>
			</div>
			<div class="ggm-topbar-actions">
				<div class="ggm-topbar-wallet" aria-label="<?php esc_attr_e( 'Wallet balance', 'ggm-member-dashboard' ); ?>">
					<span class="ggm-wallet-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" role="img" focusable="false"><path d="M4.5 5.25h12.75a2.25 2.25 0 0 1 2.25 2.25v1.25h-4a3.25 3.25 0 0 0 0 6.5h4v1.25a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25v-9A2.25 2.25 0 0 1 4.5 5.25Zm11 5h5.25a1 1 0 0 1 1 1v1.5a1 1 0 0 1-1 1H15.5a1.75 1.75 0 1 1 0-3.5Zm.25 1.25a.5.5 0 1 0 0 1 .5.5 0 0 0 0-1ZM4.5 3h10.25a.75.75 0 0 1 0 1.5H4.5A3 3 0 0 0 1.5 7.5v9a.75.75 0 0 1-1.5 0v-9A4.5 4.5 0 0 1 4.5 3Z"/></svg>
					</span>
					<span><small><?php esc_html_e( 'Wallet', 'ggm-member-dashboard' ); ?></small><strong><?php echo esc_html( $wallet_display ); ?></strong></span>
				</div>
				<button type="button" class="ggm-topbar-profile-button" data-tab="profile" aria-label="<?php esc_attr_e( 'Open profile', 'ggm-member-dashboard' ); ?>" title="<?php esc_attr_e( 'Profile', 'ggm-member-dashboard' ); ?>">
					<span class="ggm-topbar-profile-badge">
						<span class="ggm-avatar-letter"><?php echo esc_html( $initial ); ?></span>
						<?php if ( $avatar_img ) : ?>
							<img src="<?php echo esc_url( $avatar_img ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="ggm-avatar-img" onerror="this.remove()">
						<?php endif; ?>
					</span>
				</button>
			</div>
		</header>

		<!-- Pending health form reminder. The action opens the Health Information
		     tab, where the assigned form is rendered directly in the page. -->
		<div class="ggm-topbar-pending-banner" id="ggm-topbar-pending-banner" role="status" hidden>
			<span class="ggm-topbar-pending-dot" aria-hidden="true"></span>
			<span><?php esc_html_e( 'You have a health form waiting to be completed.', 'ggm-member-dashboard' ); ?></span>
			<button type="button" class="ggm-btn ggm-btn-primary ggm-topbar-pending-btn" id="ggm-topbar-pending-btn"><?php esc_html_e( 'Fill Health Form', 'ggm-member-dashboard' ); ?></button>
		</div>

		<!-- Main Scrollable Area -->
		<main class="ggm-main">
			<!-- Tab: Home -->
			<section id="tab-home" class="ggm-tab active">
				<?php include GGM_PLUGIN_DIR . 'templates/dashboard/tab-home.php'; ?>
			</section>

			<!-- Tab: Courses -->
			<section id="tab-courses" class="ggm-tab">
				<?php include GGM_PLUGIN_DIR . 'templates/dashboard/tab-courses.php'; ?>
			</section>

			<!-- Tab: Workshops -->
			<section id="tab-free" class="ggm-tab">
				<?php include GGM_PLUGIN_DIR . 'templates/dashboard/tab-workshops.php'; ?>
			</section>
			<?php if ( $is_dashboard_administrator ) : ?>
			<section id="tab-admin-workshops" class="ggm-tab"><?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/workshops.php'; ?></section>
			<section id="tab-admin-workshop-editor" class="ggm-tab"><?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/workshop-editor.php'; ?></section>
			<section id="tab-admin-courses" class="ggm-tab"><?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/courses.php'; ?></section>
			<section id="tab-admin-course-editor" class="ggm-tab"><?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/course-editor.php'; ?></section>
			<section id="tab-admin-members" class="ggm-tab"><?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/members.php'; ?></section>
			<section id="tab-admin-payments" class="ggm-tab"><?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/payments.php'; ?></section>
			<section id="tab-admin-diseases" class="ggm-tab"><?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/diseases.php'; ?></section>
			<section id="tab-admin-disease-editor" class="ggm-tab"><?php include GGM_PLUGIN_DIR . 'templates/dashboard/admin/disease-editor.php'; ?></section>
			<?php endif; ?>

			<section id="tab-health" class="ggm-tab">
				<?php include GGM_PLUGIN_DIR . 'templates/dashboard/tab-health.php'; ?>
			</section>

			<section id="tab-disease" class="ggm-tab">
				<?php include GGM_PLUGIN_DIR . 'templates/dashboard/tab-disease.php'; ?>
			</section>

			<!-- Tab: Profile -->
			<section id="tab-profile" class="ggm-tab">
				<?php include GGM_PLUGIN_DIR . 'templates/dashboard/tab-profile.php'; ?>
			</section>

			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
			<section id="tab-woocommerce" class="ggm-tab">
				<?php include GGM_PLUGIN_DIR . 'templates/dashboard/tab-woocommerce.php'; ?>
			</section>
			<?php endif; ?>
		</main>

		<!-- Sticky Bottom Nav (Mobile) -->
		<nav class="ggm-bottom-nav">
			<button class="ggm-bn-item active" data-tab="home">
				<span class="dashicons dashicons-admin-home"></span>
				<span class="bn-text"><?php esc_html_e( 'Home', 'ggm-member-dashboard' ); ?></span>
			</button>
			<button class="ggm-bn-item" data-tab="courses">
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<span class="bn-text"><?php esc_html_e( 'Courses', 'ggm-member-dashboard' ); ?></span>
			</button>
			<button class="ggm-bn-item" data-tab="free">
				<span class="dashicons dashicons-awards"></span>
				<span class="bn-text"><?php esc_html_e( 'Workshops', 'ggm-member-dashboard' ); ?></span>
			</button>
			<button class="ggm-bn-item" data-tab="health">
				<span class="dashicons dashicons-clipboard"></span>
				<span class="bn-text"><?php esc_html_e( 'Health', 'ggm-member-dashboard' ); ?></span>
			</button>
			<button class="ggm-bn-item" data-tab="disease">
				<span class="dashicons dashicons-heart"></span>
				<span class="bn-text"><?php esc_html_e( 'Disease', 'ggm-member-dashboard' ); ?></span>
			</button>
			<?php if ( $is_dashboard_administrator ) : ?>
			<button class="ggm-bn-item" data-tab="admin-workshops"><span class="dashicons dashicons-calendar-alt"></span><span class="bn-text"><?php esc_html_e( 'Manage', 'ggm-member-dashboard' ); ?></span></button>
			<button class="ggm-bn-item" data-tab="admin-members"><span class="dashicons dashicons-groups"></span><span class="bn-text"><?php esc_html_e( 'Members', 'ggm-member-dashboard' ); ?></span></button>
			<?php endif; ?>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
			<button class="ggm-bn-item" data-tab="woocommerce">
				<span class="dashicons dashicons-store"></span>
				<span class="bn-text"><?php esc_html_e( 'Account', 'ggm-member-dashboard' ); ?></span>
			</button>
			<?php endif; ?>
			<?php foreach ( $custom_links as $custom_link ) :
				$link_label = sanitize_text_field( $custom_link['label'] ?? '' );
				$link_url   = esc_url( $custom_link['url'] ?? '' );
				$link_icon  = sanitize_html_class( $custom_link['icon'] ?? 'dashicons-admin-links' );
				if ( ! $link_label || ! $link_url ) {
					continue;
				}
				$new_tab = ! empty( $custom_link['new_tab'] );
			?>
			<a class="ggm-bn-item ggm-custom-bn-link" href="<?php echo $link_url; ?>" <?php echo $new_tab ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
				<span class="dashicons <?php echo esc_attr( $link_icon ); ?>"></span>
				<span class="bn-text"><?php echo esc_html( $link_label ); ?></span>
			</a>
			<?php endforeach; ?>
		</nav>
	</div>
</div>

<!-- Hidden file input for avatar upload -->
<input type="file" id="ggm-avatar-file-input" accept="image/*" style="display:none;">
<div id="ggm-avatar-upload-feedback" style="display:none; position:fixed; bottom:20px; right:20px; background:#1a202c; color:#fff; padding:10px 18px; border-radius:10px; font-size:13px; z-index:9999;"></div>
