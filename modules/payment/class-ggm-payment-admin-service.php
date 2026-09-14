<?php
/** Canonical administrator payment listing and refund operations shared by both UIs. */
if ( ! defined( 'ABSPATH' ) ) exit;
class GGM_Payment_Admin_Service {
	public static function statuses() { return array( 'pending', 'success', 'failed', 'refunded' ); }
	public static function query( $args = array() ) {
		global $wpdb;
		$page = max( 1, absint( $args['page'] ?? 1 ) ); $per_page = 20; $status = sanitize_key( $args['status'] ?? '' ); $search = sanitize_text_field( $args['search'] ?? '' );
		if ( $status && ! in_array( $status, self::statuses(), true ) ) $status = '';
		$purchases = "SELECT 'purchase' source,p.id,p.user_id,p.workshop_id,p.course_id,p.original_amount,p.discount_amount,p.credit_amount,p.razorpay_order_id,p.razorpay_payment_id,p.razorpay_signature,p.amount,p.currency,p.status,p.created_at,NULL form_id,NULL form_title,NULL submission_id,NULL form_label,NULL form_description FROM {$wpdb->prefix}ggm_payments p";
		$forms = "SELECT 'form' source,fp.id,fp.user_id,NULL workshop_id,NULL course_id,NULL original_amount,0 discount_amount,0 credit_amount,fp.razorpay_order_id,fp.razorpay_payment_id,fp.razorpay_signature,fp.amount,fp.currency,fp.status,fp.created_at,fp.form_id,f.title form_title,fp.submission_id,fp.label form_label,fp.description form_description FROM {$wpdb->prefix}ggm_form_payments fp LEFT JOIN {$wpdb->prefix}ggm_forms f ON f.id=fp.form_id";
		$union = "({$purchases} UNION ALL {$forms}) payments";
		$where = array( '1=1' ); $params = array();
		if ( $status ) { $where[] = 'payments.status=%s'; $params[] = $status; }
		if ( '' !== $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = "(CAST(payments.id AS CHAR) LIKE %s OR payments.razorpay_order_id LIKE %s OR payments.razorpay_payment_id LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s OR EXISTS(SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id=payments.user_id AND um.meta_key IN ('ggm_phone','billing_phone') AND um.meta_value LIKE %s))"; $params = array_merge( $params, array( $like,$like,$like,$like,$like,$like ) ); }
		$where_sql = ' WHERE ' . implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$union} LEFT JOIN {$wpdb->users} u ON u.ID=payments.user_id{$where_sql}";
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		$sql = "SELECT payments.*,u.display_name,u.user_email,COALESCE(phone.meta_value,billing.meta_value,'') phone FROM {$union} LEFT JOIN {$wpdb->users} u ON u.ID=payments.user_id LEFT JOIN {$wpdb->usermeta} phone ON phone.user_id=payments.user_id AND phone.meta_key='ggm_phone' LEFT JOIN {$wpdb->usermeta} billing ON billing.user_id=payments.user_id AND billing.meta_key='billing_phone'{$where_sql} ORDER BY payments.created_at DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ) );
		foreach ( (array) $rows as $row ) { $row->item_label = 'form' === $row->source ? ( $row->form_title ?: '#' . $row->form_id ) : ( $row->workshop_id ? get_the_title( $row->workshop_id ) : ( $row->course_id ? get_the_title( $row->course_id ) : __( 'Direct purchase', 'ggm-member-dashboard' ) ) ); $row->refund_nonce = ( 'purchase' === $row->source && 'success' === $row->status ) ? wp_create_nonce( 'ggm_refund_' . $row->id ) : ''; }
		return array( 'items' => $rows, 'total' => $total, 'page' => $page, 'pages' => max( 1, (int) ceil( $total / $per_page ) ), 'per_page' => $per_page, 'status' => $status, 'search' => $search );
	}
	public static function refund( $payment_id ) {
		$payment = GGM_Payment::get( $payment_id );
		if ( ! $payment || 'success' !== $payment->status ) return new WP_Error( 'not_refundable', __( 'This purchase payment cannot be refunded.', 'ggm-member-dashboard' ) );
		global $wpdb; $updated = $wpdb->update( $wpdb->prefix . 'ggm_payments', array( 'status' => 'refunded' ), array( 'id' => $payment->id ), array( '%s' ), array( '%d' ) );
		if ( false === $updated ) return new WP_Error( 'refund_failed', __( 'Payment status could not be updated.', 'ggm-member-dashboard' ) );
		GGM_Credit::restore_for_refund( $payment->id ); return true;
	}
}
