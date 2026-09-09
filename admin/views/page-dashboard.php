<?php
/**
 * GGM LMS Admin Dashboard view.
 *
 * Displays general statistics, recent payments, recent enrollments,
 * and quick-action links for the GGM Member Dashboard plugin.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ggm-member-dashboard' ) );
}

global $wpdb;

// ─── Table Names ─────────────────────────────────────────────────────────────
$tbl_workshop_access = $wpdb->prefix . 'ggm_workshop_access';
$tbl_course_access   = $wpdb->prefix . 'ggm_course_access';
$tbl_payments        = $wpdb->prefix . 'ggm_payments';

// ─── Stats ───────────────────────────────────────────────────────────────────
// Total registered members (distinct users who ever purchased a workshop or course).
$total_members = (int) $wpdb->get_var(
	"SELECT COUNT(DISTINCT user_id) FROM (
		SELECT user_id FROM `{$tbl_workshop_access}`
		UNION
		SELECT user_id FROM `{$tbl_course_access}`
	) AS combined"
);

// Total completed purchases (workshops + courses).
$total_purchases = (int) $wpdb->get_var(
	"SELECT
		( SELECT COUNT(*) FROM `{$tbl_workshop_access}` ) +
		( SELECT COUNT(*) FROM `{$tbl_course_access}` )"
);

// Total confirmed revenue.
$total_revenue = (float) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COALESCE( SUM(amount), 0 ) FROM `{$tbl_payments}` WHERE status = %s",
		'completed'
	)
);

// Published workshops count.
$workshops_count = (int) wp_count_posts( 'workshop' )->publish;

// Settings / currency.
$settings        = get_option( 'ggm_settings', array() );
$currency_symbol = ! empty( $settings['ggm_currency_symbol'] ) ? $settings['ggm_currency_symbol'] : '₹';

// ─── Recent Payments (last 10) ────────────────────────────────────────────────
$recent_payments = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT p.id, p.amount, p.status, p.created_at, p.workshop_id, p.course_id,
		        u.display_name, u.user_email
		 FROM `{$tbl_payments}` p
		 LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
		 ORDER BY p.created_at DESC
		 LIMIT %d",
		10
	)
);

// ─── Recent Purchases (last 10) ───────────────────────────────────────────────
$recent_enrollments = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT wa.id, wa.workshop_id AS item_id, 'workshop' AS item_type, wa.granted_at,
		        u.display_name, u.user_email
		 FROM `{$tbl_workshop_access}` wa
		 LEFT JOIN {$wpdb->users} u ON wa.user_id = u.ID

		 UNION ALL

		 SELECT ca.id, ca.course_id AS item_id, 'course' AS item_type, ca.granted_at,
		        u.display_name, u.user_email
		 FROM `{$tbl_course_access}` ca
		 LEFT JOIN {$wpdb->users} u ON ca.user_id = u.ID

		 ORDER BY granted_at DESC
		 LIMIT %d",
		10
	)
);

// ─── Helper: status badge ─────────────────────────────────────────────────────
function ggm_status_badge( $status ) {
	$map = array(
		'active'    => array( '#e6f4ea', '#1e7e34', __( 'Active', 'ggm-member-dashboard' ) ),
		'completed' => array( '#e6f4ea', '#1e7e34', __( 'Completed', 'ggm-member-dashboard' ) ),
		'pending'   => array( '#fff8e1', '#b08500', __( 'Pending', 'ggm-member-dashboard' ) ),
		'expired'   => array( '#fce8e8', '#c0392b', __( 'Expired', 'ggm-member-dashboard' ) ),
		'cancelled' => array( '#fce8e8', '#c0392b', __( 'Cancelled', 'ggm-member-dashboard' ) ),
		'failed'    => array( '#fce8e8', '#c0392b', __( 'Failed', 'ggm-member-dashboard' ) ),
		'refunded'  => array( '#f3f3f3', '#666666', __( 'Refunded', 'ggm-member-dashboard' ) ),
	);
	$key    = strtolower( $status );
	$config = isset( $map[ $key ] ) ? $map[ $key ] : array( '#f3f3f3', '#555', esc_html( ucfirst( $status ) ) );
	printf(
		'<span style="background:%s;color:%s;padding:3px 9px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;">%s</span>',
		esc_attr( $config[0] ),
		esc_attr( $config[1] ),
		esc_html( $config[2] )
	);
}
?>

<div class="wrap">

	<h1><?php esc_html_e( 'GGM LMS — Dashboard', 'ggm-member-dashboard' ); ?></h1>
	<p style="color:#666;margin-top:-5px;"><?php esc_html_e( 'Overview of purchases, payments, and workshop activity.', 'ggm-member-dashboard' ); ?></p>

	<!-- ── Stats Cards ──────────────────────────────────────────────────── -->
	<div style="display:flex;flex-wrap:wrap;gap:16px;margin:20px 0;">

		<!-- Total Members -->
		<div style="flex:1 1 200px;background:#fff;border:1px solid #e0e0e0;border-top:4px solid #2271b1;border-radius:4px;padding:20px 24px;box-shadow:0 1px 3px rgba(0,0,0,.06);">
			<div style="display:flex;align-items:center;gap:12px;">
				<span class="dashicons dashicons-admin-users" style="font-size:32px;color:#2271b1;width:32px;height:32px;"></span>
				<div>
					<div style="font-size:28px;font-weight:700;color:#1d2327;line-height:1;"><?php echo esc_html( number_format( $total_members ) ); ?></div>
					<div style="font-size:13px;color:#666;margin-top:4px;"><?php esc_html_e( 'Total Members', 'ggm-member-dashboard' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Total Purchases -->
		<div style="flex:1 1 200px;background:#fff;border:1px solid #e0e0e0;border-top:4px solid #00a32a;border-radius:4px;padding:20px 24px;box-shadow:0 1px 3px rgba(0,0,0,.06);">
			<div style="display:flex;align-items:center;gap:12px;">
				<span class="dashicons dashicons-groups" style="font-size:32px;color:#00a32a;width:32px;height:32px;"></span>
				<div>
					<div style="font-size:28px;font-weight:700;color:#1d2327;line-height:1;"><?php echo esc_html( number_format( $total_purchases ) ); ?></div>
					<div style="font-size:13px;color:#666;margin-top:4px;"><?php esc_html_e( 'Total Purchases', 'ggm-member-dashboard' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Total Revenue -->
		<div style="flex:1 1 200px;background:#fff;border:1px solid #e0e0e0;border-top:4px solid #9b59b6;border-radius:4px;padding:20px 24px;box-shadow:0 1px 3px rgba(0,0,0,.06);">
			<div style="display:flex;align-items:center;gap:12px;">
				<span class="dashicons dashicons-chart-bar" style="font-size:32px;color:#9b59b6;width:32px;height:32px;"></span>
				<div>
					<div style="font-size:28px;font-weight:700;color:#1d2327;line-height:1;"><?php echo esc_html( $currency_symbol . number_format( $total_revenue, 2 ) ); ?></div>
					<div style="font-size:13px;color:#666;margin-top:4px;"><?php esc_html_e( 'Total Revenue', 'ggm-member-dashboard' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Workshops Count -->
		<div style="flex:1 1 200px;background:#fff;border:1px solid #e0e0e0;border-top:4px solid #e67e22;border-radius:4px;padding:20px 24px;box-shadow:0 1px 3px rgba(0,0,0,.06);">
			<div style="display:flex;align-items:center;gap:12px;">
				<span class="dashicons dashicons-welcome-learn-more" style="font-size:32px;color:#e67e22;width:32px;height:32px;"></span>
				<div>
					<div style="font-size:28px;font-weight:700;color:#1d2327;line-height:1;"><?php echo esc_html( number_format( $workshops_count ) ); ?></div>
					<div style="font-size:13px;color:#666;margin-top:4px;"><?php esc_html_e( 'Workshops', 'ggm-member-dashboard' ); ?></div>
				</div>
			</div>
		</div>

	</div><!-- /stats cards -->

	<!-- ── Two-column layout ────────────────────────────────────────────── -->
	<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">

		<!-- LEFT: Tables (2/3 width) -->
		<div style="flex:2 1 500px;min-width:0;">

			<!-- Recent Payments -->
			<div class="postbox" style="margin-bottom:20px;">
				<div class="postbox-header">
					<h2 class="hndle" style="padding:12px 16px;font-size:14px;font-weight:600;">
						<?php esc_html_e( 'Recent Payments', 'ggm-member-dashboard' ); ?>
						<span style="font-weight:400;font-size:12px;color:#888;margin-left:8px;"><?php esc_html_e( '(last 10)', 'ggm-member-dashboard' ); ?></span>
					</h2>
				</div>
				<div class="inside" style="padding:0;">
					<?php if ( empty( $recent_payments ) ) : ?>
						<p style="text-align:center;padding:24px;color:#888;"><?php esc_html_e( 'No payment records found.', 'ggm-member-dashboard' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped" style="border:none;box-shadow:none;">
							<thead>
								<tr>
									<th scope="col" style="width:22%;"><?php esc_html_e( 'Member', 'ggm-member-dashboard' ); ?></th>
									<th scope="col" style="width:22%;"><?php esc_html_e( 'Item', 'ggm-member-dashboard' ); ?></th>
									<th scope="col" style="width:12%;"><?php esc_html_e( 'Amount', 'ggm-member-dashboard' ); ?></th>
									<th scope="col" style="width:12%;"><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th>
									<th scope="col" style="width:20%;"><?php esc_html_e( 'Date', 'ggm-member-dashboard' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_payments as $payment ) :
									$item_title = '';
									if ( ! empty( $payment->workshop_id ) ) {
										$item_title = get_the_title( (int) $payment->workshop_id );
									} elseif ( ! empty( $payment->course_id ) ) {
										$item_title = get_the_title( (int) $payment->course_id );
									}
								?>
									<tr>
										<td>
											<strong><?php echo esc_html( $payment->display_name ?: __( 'Unknown', 'ggm-member-dashboard' ) ); ?></strong>
											<?php if ( ! empty( $payment->user_email ) ) : ?>
												<br><span style="font-size:11px;color:#777;"><?php echo esc_html( $payment->user_email ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $item_title ?: '—' ); ?></td>
										<td><?php echo esc_html( $currency_symbol . number_format( (float) $payment->amount, 2 ) ); ?></td>
										<td><?php ggm_status_badge( $payment->status ); ?></td>
										<td>
											<?php
											if ( ! empty( $payment->created_at ) && '0000-00-00 00:00:00' !== $payment->created_at ) {
												echo esc_html(
													date_i18n(
														get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
														strtotime( $payment->created_at )
													)
												);
											} else {
												echo '—';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div><!-- /recent payments postbox -->

			<!-- Recent Purchases -->
			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle" style="padding:12px 16px;font-size:14px;font-weight:600;">
						<?php esc_html_e( 'Recent Purchases', 'ggm-member-dashboard' ); ?>
						<span style="font-weight:400;font-size:12px;color:#888;margin-left:8px;"><?php esc_html_e( '(last 10)', 'ggm-member-dashboard' ); ?></span>
					</h2>
				</div>
				<div class="inside" style="padding:0;">
					<?php if ( empty( $recent_enrollments ) ) : ?>
						<p style="text-align:center;padding:24px;color:#888;"><?php esc_html_e( 'No purchase records found.', 'ggm-member-dashboard' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped" style="border:none;box-shadow:none;">
							<thead>
								<tr>
									<th scope="col" style="width:25%;"><?php esc_html_e( 'Member', 'ggm-member-dashboard' ); ?></th>
									<th scope="col" style="width:35%;"><?php esc_html_e( 'Item', 'ggm-member-dashboard' ); ?></th>
									<th scope="col" style="width:15%;"><?php esc_html_e( 'Type', 'ggm-member-dashboard' ); ?></th>
									<th scope="col" style="width:25%;"><?php esc_html_e( 'Purchased', 'ggm-member-dashboard' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_enrollments as $enrollment ) :
									$item_title = get_the_title( (int) $enrollment->item_id );
								?>
									<tr>
										<td>
											<strong><?php echo esc_html( $enrollment->display_name ?: __( 'Unknown', 'ggm-member-dashboard' ) ); ?></strong>
											<?php if ( ! empty( $enrollment->user_email ) ) : ?>
												<br><span style="font-size:11px;color:#777;"><?php echo esc_html( $enrollment->user_email ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $item_title ?: '—' ); ?></td>
										<td><?php echo esc_html( 'course' === $enrollment->item_type ? __( 'Course', 'ggm-member-dashboard' ) : __( 'Workshop', 'ggm-member-dashboard' ) ); ?></td>
										<td>
											<?php
											if ( ! empty( $enrollment->granted_at ) && '0000-00-00 00:00:00' !== $enrollment->granted_at ) {
												echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $enrollment->granted_at ) ) );
											} else {
												echo '—';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div><!-- /recent purchases postbox -->

		</div><!-- /left column -->

		<!-- RIGHT: Quick Links (1/3 width) -->
		<div style="flex:1 1 220px;">
			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle" style="padding:12px 16px;font-size:14px;font-weight:600;">
						<?php esc_html_e( 'Quick Links', 'ggm-member-dashboard' ); ?>
					</h2>
				</div>
				<div class="inside" style="padding:12px 16px 16px;">
					<ul style="margin:0;padding:0;list-style:none;">

						<li style="margin-bottom:10px;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ggm-settings' ) ); ?>"
							   style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#2271b1;font-weight:500;">
								<span class="dashicons dashicons-admin-settings" style="color:#2271b1;"></span>
								<?php esc_html_e( 'Plugin Settings', 'ggm-member-dashboard' ); ?>
							</a>
						</li>

						<li style="margin-bottom:10px;">
							<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"
							   style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#2271b1;font-weight:500;">
								<span class="dashicons dashicons-admin-users" style="color:#2271b1;"></span>
								<?php esc_html_e( 'View All Users', 'ggm-member-dashboard' ); ?>
							</a>
						</li>

						<li style="margin-bottom:10px;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ggm-lms-payments' ) ); ?>"
							   style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#2271b1;font-weight:500;">
								<span class="dashicons dashicons-money-alt" style="color:#2271b1;"></span>
								<?php esc_html_e( 'All Payments', 'ggm-member-dashboard' ); ?>
							</a>
						</li>

						<li>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=workshop' ) ); ?>"
							   style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#2271b1;font-weight:500;">
								<span class="dashicons dashicons-welcome-learn-more" style="color:#2271b1;"></span>
								<?php esc_html_e( 'Manage Workshops', 'ggm-member-dashboard' ); ?>
							</a>
						</li>

					</ul>
				</div>
			</div><!-- /quick links postbox -->
		</div><!-- /right column -->

	</div><!-- /two-column layout -->

</div><!-- /wrap -->
