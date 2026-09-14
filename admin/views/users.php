<?php
/**
 * Admin view — GGM Members list.
 *
 * Shows every WordPress user who:
 *   (a) has a row in wp_ggm_workshop_access or wp_ggm_course_access, OR
 *   (b) has the ggm_phone usermeta key (logged-in via OTP), OR
 *   (c) was manually added via the ggm_member usermeta key.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Shared with Dashboard Members: this is the one membership data source.
$ggm_members_result = GGM_Member_Data_Service::query( array(
	'search'      => isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '',
	'workshop_id' => $_GET['workshop_id'] ?? 0,
	'page'        => $_GET['paged'] ?? 1,
) );
if ( is_wp_error( $ggm_members_result ) ) {
	wp_die( esc_html( $ggm_members_result->get_error_message() ) );
}
$search      = $ggm_members_result['search'];
$workshop_id = $ggm_members_result['workshop_id'];
$paged       = $ggm_members_result['page'];
$per_page    = $ggm_members_result['per_page'];
$total       = $ggm_members_result['total'];
$pages       = $ggm_members_result['pages'];
$rows        = $ggm_members_result['rows'];
$filter_workshops = get_posts( array( 'post_type' => array( 'workshop', 'ggm_workshop' ), 'post_status' => array( 'publish', 'private', 'draft', 'pending', 'future' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
$assign_workshops = get_posts( array( 'post_type' => array( 'workshop', 'ggm_workshop' ), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
$assign_courses   = get_posts( array( 'post_type' => 'course', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
goto ggm_members_view;

global $wpdb;

// ── Search ────────────────────────────────────────────────────────────────────
$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$workshop_id = absint( $_GET['workshop_id'] ?? 0 );
$paged       = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page    = 20;
$offset      = ( $paged - 1 ) * $per_page;

if ( $workshop_id && ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
	$workshop_id = 0;
}

$workshop_access_table = $wpdb->prefix . 'ggm_workshop_access';
$course_access_table   = $wpdb->prefix . 'ggm_course_access';
$assign_workshops = get_posts( array( 'post_type' => array( 'workshop', 'ggm_workshop' ), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
$assign_courses   = get_posts( array( 'post_type' => 'course', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
$filter_workshops = get_posts( array(
	'post_type'      => array( 'workshop', 'ggm_workshop' ),
	'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );

// ── Build WHERE for search ────────────────────────────────────────────────────
$where_sql = '';
$params    = array();
if ( $search !== '' ) {
	$like       = '%' . $wpdb->esc_like( $search ) . '%';
	$where_sql  = "AND ( u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR EXISTS (
		SELECT 1 FROM {$wpdb->usermeta} search_phone
		WHERE search_phone.user_id = u.ID
		AND search_phone.meta_key IN ('ggm_phone','billing_phone')
		AND search_phone.meta_value LIKE %s
	) )";
	$params     = array( $like, $like, $like, $like );
}

// ── Union query ───────────────────────────────────────────────────────────────
// Part A: users with any workshop or course purchase.
// Part B: users with ggm_phone meta but no purchase (OTP-only).

$query_args = array();
if ( $workshop_id ) {
	$union_sql  = "
		SELECT DISTINCT u.ID
		FROM {$wpdb->users} u
		INNER JOIN {$workshop_access_table} wa ON wa.user_id = u.ID
		WHERE wa.workshop_id = %d
		{$where_sql}
	";
	$query_args = array_merge( array( $workshop_id ), $params );
} else {
	$union_sql = "
		SELECT DISTINCT u.ID
		FROM {$wpdb->users} u
		INNER JOIN {$workshop_access_table} wa ON wa.user_id = u.ID
		{$where_sql}

		UNION

		SELECT DISTINCT u.ID
		FROM {$wpdb->users} u
		INNER JOIN {$course_access_table} ca ON ca.user_id = u.ID
		{$where_sql}

		UNION

		SELECT DISTINCT u.ID
		FROM {$wpdb->users} u
		INNER JOIN {$wpdb->usermeta} meta ON meta.user_id = u.ID AND meta.meta_key IN ('ggm_phone','ggm_member')
		{$where_sql}
	";
	$query_args = array_merge( $params, $params, $params );
}

// Count total
$count_sql  = "SELECT COUNT(*) FROM ( {$union_sql} ) AS combined";
$count_args = $query_args;
$total      = (int) $wpdb->get_var(
	$count_args ? $wpdb->prepare( $count_sql, $count_args ) : $count_sql
);

$pages = max( 1, (int) ceil( $total / $per_page ) );

// Fetch paginated user IDs
$ids_sql  = "SELECT ID FROM ( {$union_sql} ) AS combined ORDER BY ID DESC LIMIT %d OFFSET %d";
$ids_args = array_merge( $query_args, array( $per_page, $offset ) );
$user_ids = $wpdb->get_col(
	$wpdb->prepare( $ids_sql, $ids_args )
);

// ── For each user ID fetch user data + latest membership + phone ──────────────
$rows = array();
if ( ! empty( $user_ids ) ) {
	$id_placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

	// User rows
	$users_data = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, display_name, user_email, user_registered FROM {$wpdb->users} WHERE ID IN ( {$id_placeholders} )",
			$user_ids
		),
		OBJECT_K
	);

	// Purchase counts per user (workshops + courses combined).
	$purchase_counts = array();
	$workshop_counts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, COUNT(*) AS cnt FROM {$workshop_access_table} WHERE user_id IN ( {$id_placeholders} ) GROUP BY user_id",
			$user_ids
		)
	);
	foreach ( $workshop_counts as $wc ) {
		$purchase_counts[ $wc->user_id ] = ( $purchase_counts[ $wc->user_id ] ?? 0 ) + (int) $wc->cnt;
	}
	$course_counts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, COUNT(*) AS cnt FROM {$course_access_table} WHERE user_id IN ( {$id_placeholders} ) GROUP BY user_id",
			$user_ids
		)
	);
	foreach ( $course_counts as $cc ) {
		$purchase_counts[ $cc->user_id ] = ( $purchase_counts[ $cc->user_id ] ?? 0 ) + (int) $cc->cnt;
	}

	// Phone metadata, resolved with one deliberate precedence rule.
	$phones_raw = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('ggm_phone','billing_phone','ggm_whatsapp_country_code') AND user_id IN ( {$id_placeholders} ) ORDER BY umeta_id ASC",
			$user_ids
		)
	);
	$phone_meta = array();
	foreach ( $phones_raw as $p ) {
		$phone_meta[ $p->user_id ][ $p->meta_key ] = $p->meta_value;
	}
	$manual_member_ids = array_map(
		'intval',
		$wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ggm_member' AND meta_value = '1' AND user_id IN ( {$id_placeholders} )",
				$user_ids
			)
		)
	);

	foreach ( $user_ids as $uid ) {
		$u = $users_data[ $uid ] ?? null;
		if ( ! $u ) {
			continue;
		}
		$purchases = $purchase_counts[ $uid ] ?? 0;
		$meta      = $phone_meta[ $uid ] ?? array();
		$country   = $meta['ggm_whatsapp_country_code'] ?? '+91';
		$phone     = ggm_normalize_member_phone( $meta['ggm_phone'] ?? '', $country )
			?: ggm_normalize_member_phone( $meta['billing_phone'] ?? '', $country );
		$raw_phone = (string) ( $meta['ggm_phone'] ?? ( $meta['billing_phone'] ?? '' ) );
		$rows[]    = (object) array(
			'user_id'         => $uid,
			'display_name'    => $u->display_name,
			'user_email'      => $u->user_email,
			'user_registered' => $u->user_registered,
			'phone'           => $phone,
			'phone_invalid'   => '' === $phone && '' !== $raw_phone,
			'purchases'       => $purchases,
			'status'          => $purchases > 0 ? 'active' : ( in_array( (int) $uid, $manual_member_ids, true ) ? 'member' : 'otp_only' ),
		);
	}
}

ggm_members_view:
// ── Page URL helper ───────────────────────────────────────────────────────────
$base_url = add_query_arg(
	array( 'page' => sanitize_key( $_GET['page'] ?? 'ggm-members' ) ),
	admin_url( 'admin.php' )
);
if ( $search !== '' ) {
	$base_url = add_query_arg( 's', $search, $base_url );
}
if ( $workshop_id ) {
	$base_url = add_query_arg( 'workshop_id', $workshop_id, $base_url );
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'GGM Members', 'ggm-member-dashboard' ); ?></h1>
	<button type="button" id="ggm-add-member-open" class="page-title-action"><?php esc_html_e( 'Add Member', 'ggm-member-dashboard' ); ?></button>
	<hr class="wp-header-end">

	<!-- Workshop filter, bulk action, and search controls. -->
	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_key( $_GET['page'] ?? 'ggm-lms-users' ) ); ?>">
		<div class="tablenav top" style="height:auto;min-height:34px;margin:12px 0 8px;">
			<div class="alignleft actions">
				<label class="screen-reader-text" for="ggm-member-workshop-filter"><?php esc_html_e( 'Filter members by workshop', 'ggm-member-dashboard' ); ?></label>
				<select id="ggm-member-workshop-filter" name="workshop_id">
					<option value="0"><?php esc_html_e( 'All Workshops', 'ggm-member-dashboard' ); ?></option>
					<?php foreach ( $filter_workshops as $filter_workshop ) : ?>
						<option value="<?php echo esc_attr( $filter_workshop->ID ); ?>" <?php selected( $workshop_id, $filter_workshop->ID ); ?>><?php echo esc_html( $filter_workshop->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filter', 'ggm-member-dashboard' ), 'button', 'filter_action', false ); ?>
				<button type="button" id="ggm-bulk-assign-open" class="button" disabled>
					<?php esc_html_e( 'Assign New Access', 'ggm-member-dashboard' ); ?> <span id="ggm-bulk-selected-count" aria-hidden="true">(0)</span>
				</button>
				<?php if ( $search !== '' || $workshop_id ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'page', sanitize_key( $_GET['page'] ?? 'ggm-lms-users' ), admin_url( 'admin.php' ) ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'ggm-member-dashboard' ); ?></a>
				<?php endif; ?>
			</div>
			<p class="search-box">
			<label class="screen-reader-text" for="ggm-member-search"><?php esc_html_e( 'Search Members', 'ggm-member-dashboard' ); ?></label>
			<input type="search"
			       id="ggm-member-search"
			       name="s"
			       value="<?php echo esc_attr( $search ); ?>"
			       placeholder="<?php esc_attr_e( 'Search by name, email or phone…', 'ggm-member-dashboard' ); ?>"
			       style="width:280px;">
			<?php submit_button( __( 'Search', 'ggm-member-dashboard' ), 'button', '', false ); ?>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ggm_export_members', 's' => $search, 'workshop_id' => $workshop_id ), admin_url( 'admin-post.php' ) ), 'ggm_export_members' ) ); ?>" class="button button-primary" style="margin-left:6px;">
				<span class="dashicons dashicons-download" style="vertical-align:text-bottom;"></span> <?php esc_html_e( 'Export CSV', 'ggm-member-dashboard' ); ?>
			</a>
			</p>
			<br class="clear">
		</div>
	</form>

	<p style="margin-bottom:6px; color:#666;">
		<?php
		printf(
			/* translators: %d: total count */
			esc_html( _n( '%d member found.', '%d members found.', $total, 'ggm-member-dashboard' ) ),
			(int) $total
		);
		?>
	</p>

	<?php if ( empty( $rows ) ) : ?>
		<p style="padding:20px 0; color:#888;"><?php esc_html_e( 'No members found.', 'ggm-member-dashboard' ); ?></p>
	<?php else : ?>

	<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">
		<thead>
			<tr>
				<td class="manage-column column-cb check-column"><input type="checkbox" class="ggm-member-check-all" aria-label="<?php esc_attr_e( 'Select all members on this page', 'ggm-member-dashboard' ); ?>"></td>
				<th style="width:40px;"><?php esc_html_e( 'Avatar', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Name', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Email', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Purchases', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Joined Date', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'ggm-member-dashboard' ); ?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td class="manage-column column-cb check-column"><input type="checkbox" class="ggm-member-check-all" aria-label="<?php esc_attr_e( 'Select all members on this page', 'ggm-member-dashboard' ); ?>"></td>
				<th><?php esc_html_e( 'Avatar', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Name', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Email', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Purchases', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Joined Date', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'ggm-member-dashboard' ); ?></th>
			</tr>
		</tfoot>
		<tbody>
			<?php foreach ( $rows as $row ) :
				// Status badge colours.
				$badge = array(
					'active'   => array( 'bg' => '#e8faf3', 'color' => '#0e9e6e' ),
					'member'   => array( 'bg' => '#eef4ff', 'color' => '#1d4ed8' ),
					'otp_only' => array( 'bg' => '#f5f5f5', 'color' => '#555555' ),
				);
				$bc = $badge[ $row->status ] ?? array( 'bg' => '#f5f5f5', 'color' => '#555' );

				$date_format = get_option( 'date_format' );
			?>
			<tr>
				<th scope="row" class="check-column"><input type="checkbox" class="ggm-member-row-check" value="<?php echo esc_attr( $row->user_id ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'ggm-member-dashboard' ), $row->display_name ) ); ?>"></th>
				<!-- Avatar -->
				<td><?php echo get_avatar( $row->user_id, 36, '', '', array( 'class' => '' ) ); ?></td>

				<!-- Name -->
				<td>
					<a href="#" class="ggm-view-profile-btn" data-user-id="<?php echo esc_attr( $row->user_id ); ?>" style="font-weight:600;">
						<?php echo esc_html( $row->display_name ); ?>
					</a>
				</td>

				<!-- Email -->
				<td><?php echo esc_html( $row->user_email ); ?></td>

				<!-- Phone -->
				<?php if ( $row->phone_invalid ) : ?>
					<td><span style="color:#b45309;font-weight:600;" title="<?php esc_attr_e( 'The saved phone value is invalid and was left unchanged for manual review.', 'ggm-member-dashboard' ); ?>"><?php esc_html_e( 'Needs review', 'ggm-member-dashboard' ); ?></span></td>
				<?php else : ?>
				<td><?php echo $row->phone !== '' ? esc_html( $row->phone ) : '<span style="color:#aaa;">—</span>'; ?></td>
				<?php endif; ?>

				<!-- Purchases -->
				<td><?php echo $row->purchases > 0 ? esc_html( $row->purchases ) : '<span style="color:#aaa;">—</span>'; ?></td>

				<!-- Status -->
				<td>
					<span style="
						background:<?php echo esc_attr( $bc['bg'] ); ?>;
						color:<?php echo esc_attr( $bc['color'] ); ?>;
						padding:3px 9px;
						border-radius:12px;
						font-size:11px;
						font-weight:600;
						white-space:nowrap;
					">
						<?php echo esc_html( $row->status === 'otp_only' ? __( 'OTP User', 'ggm-member-dashboard' ) : ucfirst( $row->status ) ); ?>
					</span>
				</td>

				<!-- Joined Date -->
				<td><?php echo esc_html( date_i18n( $date_format, strtotime( $row->user_registered ) ) ); ?></td>

				<!-- Actions -->
				<td style="white-space:nowrap;">
					<button type="button" class="button button-small ggm-view-profile-btn" data-user-id="<?php echo esc_attr( $row->user_id ); ?>">
						<?php esc_html_e( 'View Profile', 'ggm-member-dashboard' ); ?>
					</button>
					<button type="button" class="button button-small ggm-assign-content-btn" data-user-id="<?php echo esc_attr( $row->user_id ); ?>" data-user-name="<?php echo esc_attr( $row->display_name ); ?>">
						<?php esc_html_e( 'Assign Access', 'ggm-member-dashboard' ); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom" style="margin-top:12px;">
			<div class="tablenav-pages">
				<?php
				echo paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%', $base_url ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => '&laquo; ' . __( 'Previous', 'ggm-member-dashboard' ),
					'next_text' => __( 'Next', 'ggm-member-dashboard' ) . ' &raquo;',
					'type'      => 'plain',
				) );
				?>
			</div>
		</div>
	<?php endif; ?>

	<?php endif; // empty rows ?>
</div><!-- .wrap -->

<div id="ggm-add-member-modal" style="display:none;position:fixed;inset:0;z-index:100002;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
	<div style="background:#fff;border-radius:6px;width:520px;max-width:95vw;box-shadow:0 10px 40px rgba(0,0,0,.25);">
		<div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
			<h2 style="margin:0;font-size:16px;"><?php esc_html_e( 'Add Member', 'ggm-member-dashboard' ); ?></h2>
			<button type="button" class="ggm-add-member-close" aria-label="<?php esc_attr_e( 'Close', 'ggm-member-dashboard' ); ?>" style="background:none;border:0;cursor:pointer;font-size:22px;line-height:1;">&times;</button>
		</div>
		<div style="padding:20px;">
			<div style="display:flex;border-bottom:1px solid #dcdcde;margin-bottom:18px;">
				<button type="button" class="button-link ggm-member-tab is-active" data-tab="existing" style="padding:9px 12px;text-decoration:none;border-bottom:2px solid #2271b1;font-weight:600;"><?php esc_html_e( 'WordPress User', 'ggm-member-dashboard' ); ?></button>
				<button type="button" class="button-link ggm-member-tab" data-tab="new" style="padding:9px 12px;text-decoration:none;border-bottom:2px solid transparent;"><?php esc_html_e( 'Create New', 'ggm-member-dashboard' ); ?></button>
			</div>
			<div id="ggm-member-existing-panel">
				<label for="ggm-wp-user-search"><strong><?php esc_html_e( 'Search WordPress users', 'ggm-member-dashboard' ); ?></strong></label>
				<input type="search" id="ggm-wp-user-search" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Type a name, email, or username…', 'ggm-member-dashboard' ); ?>" style="width:100%;margin-top:7px;">
				<input type="hidden" id="ggm-selected-user-id">
				<div id="ggm-wp-user-results" style="margin-top:8px;max-height:210px;overflow:auto;"></div>
			</div>
			<div id="ggm-member-new-panel" style="display:none;">
				<p style="margin-top:0;"><label for="ggm-new-member-name"><strong><?php esc_html_e( 'Name', 'ggm-member-dashboard' ); ?></strong></label><br><input type="text" id="ggm-new-member-name" class="regular-text" style="width:100%;margin-top:5px;"></p>
				<p><label for="ggm-new-member-email"><strong><?php esc_html_e( 'Email', 'ggm-member-dashboard' ); ?></strong></label><br><input type="email" id="ggm-new-member-email" class="regular-text" style="width:100%;margin-top:5px;"></p>
				<p><label for="ggm-new-member-phone"><strong><?php esc_html_e( 'Phone', 'ggm-member-dashboard' ); ?></strong> <span style="color:#646970;font-weight:400;"><?php esc_html_e( '(optional)', 'ggm-member-dashboard' ); ?></span></label><br><input type="tel" id="ggm-new-member-phone" class="regular-text" inputmode="numeric" maxlength="12" style="width:100%;margin-top:5px;"></p>
			</div>
			<div id="ggm-add-member-notice" style="display:none;margin-top:12px;padding:9px 12px;border-radius:4px;"></div>
		</div>
		<div style="padding:12px 20px;border-top:1px solid #e5e7eb;text-align:right;">
			<button type="button" class="button ggm-add-member-close"><?php esc_html_e( 'Cancel', 'ggm-member-dashboard' ); ?></button>
			<button type="button" id="ggm-add-member-save" class="button button-primary"><?php esc_html_e( 'Add Member', 'ggm-member-dashboard' ); ?></button>
		</div>
	</div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     View Profile Modal
════════════════════════════════════════════════════════════════════════ -->
<div id="ggm-profile-modal" style="display:none; position:fixed; inset:0; z-index:100000; background:rgba(0,0,0,.5); align-items:center; justify-content:center;">
	<div style="background:#fff; border-radius:6px; width:640px; max-width:95vw; max-height:85vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,.25);">
		<div style="padding:16px 20px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">
			<h2 style="margin:0; font-size:16px;"><?php esc_html_e( 'Member Profile', 'ggm-member-dashboard' ); ?></h2>
			<button type="button" id="ggm-profile-modal-close" style="background:none; border:none; cursor:pointer; font-size:20px; line-height:1; color:#6b7280;">&times;</button>
		</div>
		<div style="padding:20px;" id="ggm-profile-modal-body">
			<p style="color:#888;"><?php esc_html_e( 'Loading…', 'ggm-member-dashboard' ); ?></p>
		</div>
	</div>
</div><!-- #ggm-profile-modal -->

<div id="ggm-assign-modal" style="display:none; position:fixed; inset:0; z-index:100001; background:rgba(0,0,0,.5); align-items:center; justify-content:center;">
	<div style="background:#fff;border-radius:6px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.25);">
		<div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
			<h2 style="margin:0;font-size:16px;"><?php esc_html_e( 'Manage Member Access', 'ggm-member-dashboard' ); ?></h2>
			<button type="button" class="ggm-assign-modal-close" style="background:none;border:none;cursor:pointer;font-size:20px;">&times;</button>
		</div>
		<div style="padding:20px;">
			<p style="margin-top:0;"><?php esc_html_e( 'Access for:', 'ggm-member-dashboard' ); ?> <strong id="ggm-assign-member-name"></strong></p>
			<input type="hidden" id="ggm-assign-user-id" value="">
			<div id="ggm-current-access-section" style="margin:0 0 20px;padding-bottom:18px;border-bottom:1px solid #e5e7eb;">
				<strong><?php esc_html_e( 'Current Access', 'ggm-member-dashboard' ); ?></strong>
				<div id="ggm-current-access-list" style="margin-top:9px;"><p style="color:#64748b;margin:0;"><?php esc_html_e( 'Loading…', 'ggm-member-dashboard' ); ?></p></div>
			</div>
			<label for="ggm-assign-item"><strong><?php esc_html_e( 'Assign New Access', 'ggm-member-dashboard' ); ?></strong></label>
			<select id="ggm-assign-item" style="width:100%;margin-top:7px;">
				<option value=""><?php esc_html_e( 'Select an item…', 'ggm-member-dashboard' ); ?></option>
				<optgroup label="<?php esc_attr_e( 'Workshops', 'ggm-member-dashboard' ); ?>">
					<?php foreach ( $assign_workshops as $item ) : ?><option value="workshop:<?php echo esc_attr( $item->ID ); ?>"><?php echo esc_html( $item->post_title ); ?></option><?php endforeach; ?>
				</optgroup>
				<optgroup label="<?php esc_attr_e( 'Courses', 'ggm-member-dashboard' ); ?>">
					<?php foreach ( $assign_courses as $item ) : ?><option value="course:<?php echo esc_attr( $item->ID ); ?>"><?php echo esc_html( $item->post_title ); ?></option><?php endforeach; ?>
				</optgroup>
			</select>
			<div id="ggm-assign-notice" style="display:none;margin-top:12px;padding:9px 12px;border-radius:4px;"></div>
		</div>
		<div style="padding:12px 20px;border-top:1px solid #e5e7eb;text-align:right;">
			<button type="button" class="button ggm-assign-modal-close"><?php esc_html_e( 'Cancel', 'ggm-member-dashboard' ); ?></button>
			<button type="button" id="ggm-assign-save" class="button button-primary"><?php esc_html_e( 'Assign Access', 'ggm-member-dashboard' ); ?></button>
		</div>
	</div>
</div>

<script>
(function ($) {
	'use strict';

	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'ggm_admin_nonce' ) ); ?>;
	var addMode = 'existing';
	var userSearchTimer;
	var assignMode = 'single';
	var assignUserIds = [];

	$( '#ggm-add-member-open' ).on( 'click', function () {
		$( '#ggm-add-member-notice' ).hide();
		$( '#ggm-add-member-modal' ).css( 'display', 'flex' );
		$( '#ggm-wp-user-search' ).trigger( 'focus' );
	} );
	$( document ).on( 'click', '.ggm-add-member-close', function () { $( '#ggm-add-member-modal' ).hide(); } );
	$( '#ggm-add-member-modal' ).on( 'click', function ( e ) { if ( $( e.target ).is( this ) ) { $( this ).hide(); } } );

	$( '.ggm-member-tab' ).on( 'click', function () {
		addMode = $( this ).data( 'tab' );
		$( '.ggm-member-tab' ).removeClass( 'is-active' ).css( { borderBottomColor:'transparent', fontWeight:400 } );
		$( this ).addClass( 'is-active' ).css( { borderBottomColor:'#2271b1', fontWeight:600 } );
		$( '#ggm-member-existing-panel' ).toggle( addMode === 'existing' );
		$( '#ggm-member-new-panel' ).toggle( addMode === 'new' );
		$( '#ggm-add-member-notice' ).hide();
	} );

	$( '#ggm-wp-user-search' ).on( 'input', function () {
		var query = $.trim( $( this ).val() );
		clearTimeout( userSearchTimer );
		$( '#ggm-selected-user-id' ).val( '' );
		if ( query.length < 2 ) { $( '#ggm-wp-user-results' ).empty(); return; }
		$( '#ggm-wp-user-results' ).html( '<p style="color:#646970;"><?php echo esc_js( __( 'Searching…', 'ggm-member-dashboard' ) ); ?></p>' );
		userSearchTimer = setTimeout( function () {
			$.post( ajaxUrl, { action:'ggm_search_wordpress_users', nonce:nonce, search:query } ).done( function ( res ) {
				var $results = $( '#ggm-wp-user-results' ).empty();
				if ( ! res.success || ! res.data.users.length ) { $results.html( '<p style="color:#646970;"><?php echo esc_js( __( 'No WordPress users found.', 'ggm-member-dashboard' ) ); ?></p>' ); return; }
				res.data.users.forEach( function ( user ) {
					var $button = $( '<button type="button">' ).css( { display:'block', width:'100%', padding:'9px 10px', textAlign:'left', background:'#fff', border:'1px solid #dcdcde', marginTop:'-1px', cursor:user.is_member ? 'default' : 'pointer' } );
					$button.append( $( '<strong>' ).text( user.name ), $( '<span>' ).css( { display:'block', color:'#646970', fontSize:'12px' } ).text( user.email + ( user.is_member ? ' · <?php echo esc_js( __( 'Already a member', 'ggm-member-dashboard' ) ); ?>' : '' ) ) );
					if ( ! user.is_member ) { $button.on( 'click', function () { $( '#ggm-wp-user-results button' ).css( { background:'#fff', borderColor:'#dcdcde' } ); $( this ).css( { background:'#f0f6fc', borderColor:'#2271b1' } ); $( '#ggm-selected-user-id' ).val( user.id ); } ); }
					$results.append( $button );
				} );
			} );
		}, 300 );
	} );

	$( '#ggm-add-member-save' ).on( 'click', function () {
		var $button = $( this ), $notice = $( '#ggm-add-member-notice' );
		var data = { action:'ggm_add_member', nonce:nonce, mode:addMode };
		if ( addMode === 'existing' ) { data.user_id = $( '#ggm-selected-user-id' ).val(); }
		else { data.name = $( '#ggm-new-member-name' ).val(); data.email = $( '#ggm-new-member-email' ).val(); data.phone = $( '#ggm-new-member-phone' ).val(); }
		$button.prop( 'disabled', true ).text( <?php echo wp_json_encode( __( 'Adding…', 'ggm-member-dashboard' ) ); ?> );
		$.post( ajaxUrl, data ).done( function ( res ) {
			var ok = !!res.success;
			$notice.css( { display:'block', background:ok ? '#dcfce7' : '#fee2e2', color:ok ? '#166534' : '#991b1b' } ).text( res.data && res.data.message ? res.data.message : <?php echo wp_json_encode( __( 'Request failed.', 'ggm-member-dashboard' ) ); ?> );
			if ( ok ) { setTimeout( function () { window.location.reload(); }, 700 ); }
		} ).fail( function () { $notice.css( { display:'block', background:'#fee2e2', color:'#991b1b' } ).text( <?php echo wp_json_encode( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?> ); } ).always( function () { $button.prop( 'disabled', false ).text( <?php echo wp_json_encode( __( 'Add Member', 'ggm-member-dashboard' ) ); ?> ); } );
	} );

	function openProfile( userId ) {
		$( '#ggm-profile-modal' ).css( 'display', 'flex' );
		$( '#ggm-profile-modal-body' ).html( '<p style="color:#888;"><?php echo esc_js( __( 'Loading…', 'ggm-member-dashboard' ) ); ?></p>' );

		$.post( ajaxUrl, {
			action  : 'ggm_view_member_profile',
			nonce   : nonce,
			user_id : userId
		} ).done( function ( res ) {
			if ( res.success ) {
				$( '#ggm-profile-modal-body' ).html( res.data.html );
			} else {
				$( '#ggm-profile-modal-body' ).html( '<p style="color:#dc2626;">' + ( res.data && res.data.message ? res.data.message : <?php echo wp_json_encode( __( 'Failed to load profile.', 'ggm-member-dashboard' ) ); ?> ) + '</p>' );
			}
		} ).fail( function () {
			$( '#ggm-profile-modal-body' ).html( '<p style="color:#dc2626;"><?php echo esc_js( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?></p>' );
		} );
	}

	$( document ).on( 'click', '.ggm-view-profile-btn', function ( e ) {
		e.preventDefault();
		openProfile( $( this ).data( 'user-id' ) );
	} );

	$( '#ggm-profile-modal-close' ).on( 'click', function () {
		$( '#ggm-profile-modal' ).hide();
	} );

	$( '#ggm-profile-modal' ).on( 'click', function ( e ) {
		if ( $( e.target ).is( '#ggm-profile-modal' ) ) {
			$( this ).hide();
		}
	} );

	function selectedMemberIds() {
		return $( '.ggm-member-row-check:checked' ).map( function () { return parseInt( this.value, 10 ); } ).get().filter( function ( id ) { return id > 0; } );
	}

	function updateBulkSelection() {
		var total = $( '.ggm-member-row-check' ).length;
		var selected = selectedMemberIds().length;
		$( '.ggm-member-check-all' ).prop( 'checked', total > 0 && selected === total ).prop( 'indeterminate', selected > 0 && selected < total );
		$( '#ggm-bulk-assign-open' ).prop( 'disabled', selected === 0 );
		$( '#ggm-bulk-selected-count' ).text( '(' + selected + ')' );
	}

	$( document ).on( 'change', '.ggm-member-check-all', function () {
		$( '.ggm-member-row-check' ).prop( 'checked', this.checked );
		updateBulkSelection();
	} );
	$( document ).on( 'change', '.ggm-member-row-check', updateBulkSelection );

	$( '#ggm-bulk-assign-open' ).on( 'click', function () {
		assignUserIds = selectedMemberIds();
		if ( ! assignUserIds.length ) { return; }
		assignMode = 'bulk';
		$( '#ggm-assign-user-id' ).val( '' );
		$( '#ggm-assign-member-name' ).text( assignUserIds.length + ' ' + <?php echo wp_json_encode( __( 'selected members', 'ggm-member-dashboard' ) ); ?> );
		$( '#ggm-current-access-section' ).hide();
		$( '#ggm-assign-item' ).val( '' );
		$( '#ggm-assign-notice' ).hide();
		$( '#ggm-assign-save' ).text( <?php echo wp_json_encode( __( 'Assign New Access', 'ggm-member-dashboard' ) ); ?> );
		$( '#ggm-assign-modal' ).css( 'display', 'flex' );
	} );

	$( document ).on( 'click', '.ggm-assign-content-btn', function () {
		assignMode = 'single';
		assignUserIds = [ parseInt( $( this ).data( 'user-id' ), 10 ) ];
		$( '#ggm-assign-user-id' ).val( $( this ).data( 'user-id' ) );
		$( '#ggm-assign-member-name' ).text( $( this ).data( 'user-name' ) );
		$( '#ggm-current-access-section' ).show();
		$( '#ggm-assign-item' ).val( '' );
		$( '#ggm-assign-notice' ).hide();
		$( '#ggm-assign-save' ).text( <?php echo wp_json_encode( __( 'Assign Access', 'ggm-member-dashboard' ) ); ?> );
		$( '#ggm-assign-modal' ).css( 'display', 'flex' );
		loadMemberAccess();
	} );
	$( document ).on( 'click', '.ggm-assign-modal-close', function () { $( '#ggm-assign-modal' ).hide(); } );
	$( '#ggm-assign-modal' ).on( 'click', function ( e ) { if ( $( e.target ).is( this ) ) { $( this ).hide(); } } );

	function loadMemberAccess() {
		var $list = $( '#ggm-current-access-list' ).empty().append( $( '<p>' ).css( { color:'#64748b', margin:0 } ).text( <?php echo wp_json_encode( __( 'Loading…', 'ggm-member-dashboard' ) ); ?> ) );
		$.post( ajaxUrl, { action:'ggm_get_member_access', nonce:nonce, user_id:$( '#ggm-assign-user-id' ).val() } ).done( function ( res ) {
			$list.empty();
			if ( ! res.success ) { $list.append( $( '<p>' ).css( { color:'#b91c1c', margin:0 } ).text( res.data && res.data.message ? res.data.message : <?php echo wp_json_encode( __( 'Could not load access.', 'ggm-member-dashboard' ) ); ?> ) ); return; }
			var items = res.data.items || [];
			if ( ! items.length ) { $list.append( $( '<p>' ).css( { color:'#64748b', margin:0 } ).text( <?php echo wp_json_encode( __( 'No workshop or course access assigned.', 'ggm-member-dashboard' ) ); ?> ) ); return; }
			items.forEach( function ( item ) {
				var $row = $( '<div>' ).css( { display:'flex', alignItems:'center', gap:'10px', padding:'9px 10px', marginBottom:'6px', background:'#f8fafc', border:'1px solid #e2e8f0', borderRadius:'6px' } );
				var typeLabel = item.type === 'course' ? <?php echo wp_json_encode( __( 'Course', 'ggm-member-dashboard' ) ); ?> : <?php echo wp_json_encode( __( 'Workshop', 'ggm-member-dashboard' ) ); ?>;
				var meta = typeLabel;
				if ( item.expires_at ) { meta += ' · ' + ( item.expired ? <?php echo wp_json_encode( __( 'Expired', 'ggm-member-dashboard' ) ); ?> : <?php echo wp_json_encode( __( 'Expires', 'ggm-member-dashboard' ) ); ?> ) + ': ' + item.expires_at; }
				$row.append( $( '<div>' ).css( { flex:1, minWidth:0 } ).append( $( '<strong>' ).css( { display:'block' } ).text( item.title ), $( '<small>' ).css( { color:item.expired ? '#b91c1c' : '#64748b' } ).text( meta ) ) );
				$row.append( $( '<button type="button" class="button button-small ggm-remove-access">' ).css( { color:'#b91c1c', borderColor:'#fca5a5' } ).attr( { 'data-type':item.type, 'data-item-id':item.item_id, 'data-title':item.title } ).text( <?php echo wp_json_encode( __( 'Remove Access', 'ggm-member-dashboard' ) ); ?> ) );
				$list.append( $row );
			} );
		} ).fail( function () { $list.empty().append( $( '<p>' ).css( { color:'#b91c1c', margin:0 } ).text( <?php echo wp_json_encode( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?> ) ); } );
	}

	$( document ).on( 'click', '.ggm-remove-access', function () {
		var $button = $( this ), title = $button.data( 'title' );
		if ( ! window.confirm( <?php echo wp_json_encode( __( 'Remove access to', 'ggm-member-dashboard' ) ); ?> + ' “' + title + '”? ' + <?php echo wp_json_encode( __( 'The payment record will be kept.', 'ggm-member-dashboard' ) ); ?> ) ) { return; }
		$button.prop( 'disabled', true ).text( <?php echo wp_json_encode( __( 'Removing…', 'ggm-member-dashboard' ) ); ?> );
		$.post( ajaxUrl, { action:'ggm_remove_member_access', nonce:nonce, user_id:$( '#ggm-assign-user-id' ).val(), item_type:$button.data( 'type' ), item_id:$button.data( 'item-id' ) } ).done( function ( res ) {
			if ( res.success ) { loadMemberAccess(); } else { window.alert( res.data && res.data.message ? res.data.message : <?php echo wp_json_encode( __( 'Could not remove access.', 'ggm-member-dashboard' ) ); ?> ); $button.prop( 'disabled', false ).text( <?php echo wp_json_encode( __( 'Remove Access', 'ggm-member-dashboard' ) ); ?> ); }
		} ).fail( function () { window.alert( <?php echo wp_json_encode( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?> ); $button.prop( 'disabled', false ).text( <?php echo wp_json_encode( __( 'Remove Access', 'ggm-member-dashboard' ) ); ?> ); } );
	} );
	$( '#ggm-assign-save' ).on( 'click', function () {
		var parts = String( $( '#ggm-assign-item' ).val() || '' ).split( ':' );
		var $button = $( this );
		var $notice = $( '#ggm-assign-notice' );
		if ( parts.length !== 2 ) { $notice.css( { display:'block', background:'#fee2e2', color:'#991b1b' } ).text( <?php echo wp_json_encode( __( 'Please select a workshop or course.', 'ggm-member-dashboard' ) ); ?> ); return; }
		if ( assignMode === 'bulk' && ! assignUserIds.length ) { $notice.css( { display:'block', background:'#fee2e2', color:'#991b1b' } ).text( <?php echo wp_json_encode( __( 'Select at least one member.', 'ggm-member-dashboard' ) ); ?> ); return; }
		var request = { action:'ggm_assign_member_content', nonce:nonce, user_id:$( '#ggm-assign-user-id' ).val(), item_type:parts[0], item_id:parts[1] };
		if ( assignMode === 'bulk' ) { request.action = 'ggm_bulk_assign_member_content'; request.user_ids = assignUserIds; delete request.user_id; }
		$button.prop( 'disabled', true ).text( <?php echo wp_json_encode( __( 'Assigning…', 'ggm-member-dashboard' ) ); ?> );
		$.post( ajaxUrl, request ).done( function ( res ) {
			var ok = !!res.success;
			$notice.css( { display:'block', background:ok ? '#dcfce7' : '#fee2e2', color:ok ? '#166534' : '#991b1b' } ).text( res.data && res.data.message ? res.data.message : <?php echo wp_json_encode( __( 'Request failed.', 'ggm-member-dashboard' ) ); ?> );
			if ( ok ) { setTimeout( function () { window.location.reload(); }, 900 ); }
		} ).fail( function () { $notice.css( { display:'block', background:'#fee2e2', color:'#991b1b' } ).text( <?php echo wp_json_encode( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?> ); } ).always( function () { $button.prop( 'disabled', false ).text( assignMode === 'bulk' ? <?php echo wp_json_encode( __( 'Assign New Access', 'ggm-member-dashboard' ) ); ?> : <?php echo wp_json_encode( __( 'Assign Access', 'ggm-member-dashboard' ) ); ?> ); } );
	} );
})( jQuery );
</script>
