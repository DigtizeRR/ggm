<?php
/** Authoritative Customer/member population shared by WP Admin and Dashboard. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GGM_Member_Data_Service {
	private static function error( $code, $message ) {
		global $wpdb;
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && ! empty( $wpdb->last_error ) ) error_log( 'GGM Member Data Service: ' . $wpdb->last_error );
		return new WP_Error( $code, $message );
	}

	/** The single canonical Customer-role join used by member counts and listings. */
	private static function customer_from_clause() {
		global $wpdb;
		return array( "FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} capabilities ON capabilities.user_id = u.ID AND capabilities.meta_key = %s AND capabilities.meta_value LIKE %s", array( $wpdb->prefix . 'capabilities', '%"customer"%' ) );
	}

	/** Count the same WordPress Customer population used by the member listings. */
	public static function count_customers() {
		global $wpdb;
		if ( ! get_role( 'customer' ) ) return self::error( 'customer_role_missing', __( 'The WordPress Customer role is unavailable.', 'ggm-member-dashboard' ) );
		list( $from, $params ) = self::customer_from_clause();
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT u.ID) {$from}", $params ) );
		return $wpdb->last_error ? self::error( 'member_query_failed', __( 'Member data could not be loaded.', 'ggm-member-dashboard' ) ) : $count;
	}

	public static function query( $args = array() ) {
		global $wpdb;
		$search = sanitize_text_field( $args['search'] ?? '' ); $workshop_id = absint( $args['workshop_id'] ?? 0 ); $page = max( 1, absint( $args['page'] ?? 1 ) ); $per_page=20;
		if ( ! get_role( 'customer' ) ) return self::error( 'customer_role_missing', __( 'The WordPress Customer role is unavailable.', 'ggm-member-dashboard' ) );
		if ( $workshop_id && ! in_array( get_post_type( $workshop_id ), array( 'workshop', 'ggm_workshop' ), true ) ) return self::error( 'invalid_workshop', __( 'Invalid Workshop filter.', 'ggm-member-dashboard' ) );
		$access = $wpdb->prefix . 'ggm_workshop_access'; list( $from, $role_params ) = self::customer_from_clause();
		if ( $workshop_id ) { $from .= " INNER JOIN {$access} access_row ON access_row.user_id = u.ID AND access_row.workshop_id=%d"; $role_params[] = $workshop_id; }
		$search_sql = ''; $search_params = array();
		if ( '' !== $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $search_sql = " AND (u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR EXISTS(SELECT 1 FROM {$wpdb->usermeta} sm WHERE sm.user_id = u.ID AND sm.meta_key IN ('first_name','last_name','ggm_phone','billing_phone') AND sm.meta_value LIKE %s))"; $search_params = array( $like, $like, $like, $like ); }
		$params = array_merge( $role_params, $search_params ); $where = " WHERE 1=1{$search_sql}";
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT u.ID) {$from}{$where}", $params ) ); if ( $wpdb->last_error ) return self::error( 'member_query_failed', __( 'Member data could not be loaded.', 'ggm-member-dashboard' ) );
		$id_params = array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ); $ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT u.ID {$from}{$where} ORDER BY u.ID DESC LIMIT %d OFFSET %d", $id_params ) ); if ( $wpdb->last_error ) return self::error( 'member_query_failed', __( 'Member data could not be loaded.', 'ggm-member-dashboard' ) );
		$rows = array(); $assignment_count = 0;
		if ( $ids ) {
			$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) ); $users = $wpdb->get_results( $wpdb->prepare( "SELECT ID,display_name,user_email,user_registered FROM {$wpdb->users} WHERE ID IN ({$ph})", $ids ), OBJECT_K ); $meta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id,meta_key,meta_value FROM {$wpdb->usermeta} WHERE user_id IN ({$ph}) AND meta_key IN ('ggm_phone','billing_phone','ggm_whatsapp_country_code') ORDER BY umeta_id ASC", $ids ) );
			$meta = array(); foreach ( $meta_rows as $m ) $meta[ $m->user_id ][ $m->meta_key ] = $m->meta_value;
			$assignment_rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.user_id,a.workshop_id,a.granted_via,a.granted_at,p.post_title FROM {$access} a LEFT JOIN {$wpdb->posts} p ON p.ID=a.workshop_id WHERE a.user_id IN ({$ph}) ORDER BY a.granted_at DESC", $ids ) ); if ( $wpdb->last_error ) return self::error( 'member_query_failed', __( 'Member data could not be loaded.', 'ggm-member-dashboard' ) );
			$assignments = array(); foreach ( $assignment_rows as $a ) { $assignments[ $a->user_id ][] = array( 'id' => (int) $a->workshop_id, 'title' => $a->post_title ?: sprintf( __( 'Workshop #%d', 'ggm-member-dashboard' ), $a->workshop_id ), 'granted_via' => $a->granted_via, 'granted_at' => $a->granted_at ); $assignment_count++; }
			foreach ( $ids as $id ) { if ( empty( $users[ $id ] ) ) continue; $m = $meta[ $id ] ?? array(); $country = $m['ggm_whatsapp_country_code'] ?? '+91'; $raw_phone = (string) ( $m['ggm_phone'] ?? ( $m['billing_phone'] ?? '' ) ); $phone = ggm_normalize_member_phone( $m['ggm_phone'] ?? '', $country ) ?: ggm_normalize_member_phone( $m['billing_phone'] ?? '', $country ); $workshops = $assignments[ $id ] ?? array(); $rows[] = (object) array( 'user_id' => (int) $id, 'display_name' => $users[ $id ]->display_name, 'user_email' => $users[ $id ]->user_email, 'user_registered' => $users[ $id ]->user_registered, 'phone' => $phone, 'phone_invalid' => '' === $phone && '' !== $raw_phone, 'workshops' => $workshops, 'workshop_count' => count( $workshops ), 'purchases' => count( $workshops ), 'status' => $workshops ? 'assigned' : 'unassigned' ); }
		}
		return array( 'rows' => $rows, 'ids' => array_map( 'absint', $ids ), 'total' => $total, 'page' => $page, 'pages' => max( 1, (int) ceil( $total / $per_page ) ), 'per_page' => $per_page, 'workshop_id' => $workshop_id, 'search' => $search, 'source' => 'GGM_Member_Data_Service', 'diagnostic' => array( 'customer_count' => $total, 'assignment_count' => $assignment_count, 'matched_customer_count' => count( $rows ), 'workshop_id' => $workshop_id ) );
	}
}
