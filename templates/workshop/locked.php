<?php
/**
 * Locked Workshop Content view.
 *
 * Loaded when an unentitled user attempts to open a premium lesson page.
 * This partial is included by lesson.php, which already calls get_header/get_footer.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Context ──────────────────────────────────────────────────────────────────
$lesson_id    = get_the_ID();
$workshop_id  = (int) get_post_meta( $lesson_id, '_ggm_workshop_id', true );
$workshop_url = $workshop_id ? get_permalink( $workshop_id ) : home_url();
$workshop_title = $workshop_id ? get_the_title( $workshop_id ) : '';

// Login page URL.
$login_page_id = function_exists( 'ggm_get_setting' ) ? (int) ggm_get_setting( 'login_page_id' ) : 0;
$login_url     = $login_page_id ? get_permalink( $login_page_id ) : wp_login_url( get_permalink() );

// Direct-purchase checkout link for this workshop.
$checkout_page_url = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );
$selected_currency  = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
	? GGM_Currency::selected_currency()
	: ( function_exists( 'ggm_get_setting' ) ? ggm_get_setting( 'ggm_currency', 'INR' ) : 'INR' );
$enroll_url         = $workshop_id ? add_query_arg( array( 'workshop_id' => $workshop_id, 'ggm_currency' => $selected_currency ), $checkout_page_url ) : $checkout_page_url;
$price_html         = ( $workshop_id && class_exists( 'GGM_Workshop' ) ) ? GGM_Workshop::price_html( $workshop_id ) : '';
?>
<div class="ggm-locked-wrap">

	<!-- Lock icon + headings ------------------------------------------------->
	<div class="ggm-locked-hero">
		<span class="dashicons dashicons-lock ggm-locked-icon" aria-hidden="true"></span>

		<h2 class="ggm-locked-heading">
			<?php esc_html_e( 'This content is locked', 'ggm-member-dashboard' ); ?>
		</h2>

		<p class="ggm-locked-subtext">
			<?php esc_html_e( 'Purchase this workshop to unlock full access', 'ggm-member-dashboard' ); ?>
		</p>

		<?php if ( $workshop_title ) : ?>
			<p class="ggm-locked-workshop-link">
				<?php esc_html_e( 'Workshop:', 'ggm-member-dashboard' ); ?>
				<a href="<?php echo esc_url( $workshop_url ); ?>">
					<?php echo esc_html( $workshop_title ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div><!-- /.ggm-locked-hero -->

	<!-- Purchase card ---------------------------------------------------------->
	<?php if ( $workshop_id ) : ?>
		<div class="ggm-locked-plans">
			<div class="ggm-locked-plans-grid">
				<div class="ggm-plan-card">
					<div class="ggm-plan-card-body">
						<h4 class="ggm-plan-name"><?php echo esc_html( $workshop_title ); ?></h4>

						<?php if ( $price_html ) : ?>
							<div class="ggm-plan-price"><?php echo wp_kses_post( $price_html ); ?></div>
						<?php endif; ?>
					</div>

					<div class="ggm-plan-card-footer">
						<a href="<?php echo esc_url( $enroll_url ); ?>" class="ggm-btn-primary ggm-plan-enroll">
							<?php esc_html_e( 'Purchase Now', 'ggm-member-dashboard' ); ?>
						</a>
					</div>
				</div><!-- /.ggm-plan-card -->
			</div><!-- /.ggm-locked-plans-grid -->
		</div><!-- /.ggm-locked-plans -->
	<?php endif; ?>

	<!-- Login nudge (shown only to guests) ----------------------------------->
	<?php if ( ! is_user_logged_in() ) : ?>
		<p class="ggm-locked-login-prompt">
			<?php esc_html_e( 'Already purchased?', 'ggm-member-dashboard' ); ?>
			<a href="<?php echo esc_url( $login_url ); ?>">
				<?php esc_html_e( 'Login to your account', 'ggm-member-dashboard' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<!-- Back link ------------------------------------------------------------>
	<p class="ggm-locked-back-link">
		<a href="<?php echo esc_url( $workshop_url ); ?>" class="ggm-btn-secondary">
			&larr; <?php esc_html_e( 'Back to Workshop', 'ggm-member-dashboard' ); ?>
		</a>
	</p>

</div><!-- /.ggm-locked-wrap -->

<style>
/* Scoped styles — no header/footer context needed */
.ggm-locked-wrap {
	max-width: 860px;
	margin: 60px auto;
	padding: 0 20px 60px;
	text-align: center;
	font-family: inherit;
}

/* Hero */
.ggm-locked-icon {
	font-size: 72px !important;
	width: 72px !important;
	height: 72px !important;
	color: #f43f5e;
	display: block;
	margin: 0 auto 20px;
}
.ggm-locked-heading {
	font-size: 28px;
	font-weight: 700;
	color: #0f172a;
	margin: 0 0 10px;
}
.ggm-locked-subtext {
	font-size: 16px;
	color: #64748b;
	margin: 0 0 14px;
}
.ggm-locked-workshop-link {
	font-size: 14px;
	color: #64748b;
	margin: 0 0 40px;
}
.ggm-locked-workshop-link a {
	color: #6366f1;
	text-decoration: none;
	font-weight: 600;
}
.ggm-locked-workshop-link a:hover {
	text-decoration: underline;
}

/* Plans heading */
.ggm-locked-plans-heading {
	font-size: 18px;
	font-weight: 600;
	color: #1e293b;
	margin: 0 0 24px;
}

/* Grid */
.ggm-locked-plans-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 20px;
	justify-content: center;
	margin-bottom: 36px;
}

/* Plan card */
.ggm-plan-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	width: 220px;
	display: flex;
	flex-direction: column;
	overflow: hidden;
}
.ggm-plan-card-body {
	padding: 24px 20px 16px;
	flex: 1;
}
.ggm-plan-name {
	font-size: 16px;
	font-weight: 700;
	color: #0f172a;
	margin: 0 0 12px;
}
.ggm-plan-price {
	font-size: 32px;
	font-weight: 800;
	color: #6366f1;
	line-height: 1;
	margin-bottom: 8px;
}
.ggm-plan-currency {
	font-size: 18px;
	vertical-align: super;
}
.ggm-plan-duration {
	font-size: 13px;
	color: #94a3b8;
	margin: 0;
}
.ggm-plan-card-footer {
	padding: 16px 20px;
	border-top: 1px solid #f1f5f9;
}

/* Buttons */
.ggm-btn-primary,
.ggm-plan-enroll {
	display: block;
	width: 100%;
	padding: 10px 18px;
	background: #6366f1;
	color: #ffffff !important;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 600;
	text-align: center;
	text-decoration: none;
	box-sizing: border-box;
	transition: background 0.15s;
}
.ggm-btn-primary:hover,
.ggm-plan-enroll:hover {
	background: #4f46e5;
}
.ggm-btn-secondary {
	display: inline-block;
	padding: 9px 20px;
	border: 1px solid #cbd5e1;
	border-radius: 8px;
	font-size: 14px;
	color: #475569 !important;
	text-decoration: none;
	transition: border-color 0.15s, color 0.15s;
}
.ggm-btn-secondary:hover {
	border-color: #94a3b8;
	color: #0f172a !important;
}

/* Login / back links */
.ggm-locked-login-prompt {
	font-size: 14px;
	color: #64748b;
	margin: 0 0 20px;
}
.ggm-locked-login-prompt a {
	color: #6366f1;
	font-weight: 600;
	text-decoration: none;
}
.ggm-locked-login-prompt a:hover {
	text-decoration: underline;
}
.ggm-locked-back-link {
	margin: 0;
}

@media ( max-width: 600px ) {
	.ggm-locked-plans-grid {
		flex-direction: column;
		align-items: center;
	}
	.ggm-plan-card {
		width: 100%;
		max-width: 320px;
	}
}
</style>
