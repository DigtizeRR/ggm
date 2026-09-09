<?php
/**
 * Admin view — Payments log.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

if ( ! function_exists( 'ggm_lms_payment_user_phone' ) ) {
	function ggm_lms_payment_user_phone( $user_id ) {
		static $cache = array();
		$user_id = absint( $user_id );
		if ( isset( $cache[ $user_id ] ) ) {
			return $cache[ $user_id ];
		}
		if ( ! $user_id ) {
			$cache[ $user_id ] = '';
			return '';
		}

		$phone = get_user_meta( $user_id, 'ggm_phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true );
		$code  = get_user_meta( $user_id, 'ggm_whatsapp_country_code', true );
		if ( $phone && $code && 0 !== strpos( preg_replace( '/\D/', '', (string) $phone ), preg_replace( '/\D/', '', (string) $code ) ) ) {
			$phone = trim( $code . ' ' . $phone );
		}
		$cache[ $user_id ] = $phone;
		return $phone;
	}
}

if ( ! function_exists( 'ggm_lms_payment_format_answer' ) ) {
	function ggm_lms_payment_format_answer( $field, $value ) {
		if ( class_exists( 'GGM_Form_Builder' ) ) {
			return GGM_Form_Builder::format_answer( $field, $value );
		}
		return is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
	}
}

if ( ! function_exists( 'ggm_lms_payment_submission_details' ) ) {
	function ggm_lms_payment_submission_details( $submission_id ) {
		static $cache = array();
		global $wpdb;

		$submission_id = absint( $submission_id );
		if ( isset( $cache[ $submission_id ] ) ) {
			return $cache[ $submission_id ];
		}

		$out = array(
			'phone'   => '',
			'answers' => '',
			'files'   => '',
		);

		if ( ! $submission_id ) {
			$cache[ $submission_id ] = $out;
			return $out;
		}

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT answers_json, schema_snapshot_json FROM {$wpdb->prefix}ggm_form_submissions WHERE id=%d",
			$submission_id
		) );
		if ( ! $row ) {
			$cache[ $submission_id ] = $out;
			return $out;
		}

		$answers = json_decode( (string) $row->answers_json, true );
		$schema  = json_decode( (string) $row->schema_snapshot_json, true );
		$answers = is_array( $answers ) ? $answers : array();
		$schema  = is_array( $schema ) ? $schema : array();

		$fields = array();
		foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) {
			foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
				if ( ! empty( $field['key'] ) ) {
					$fields[ $field['key'] ] = $field;
				}
			}
		}

		ob_start();
		foreach ( $answers as $key => $value ) {
			$field = $fields[ $key ] ?? array(
				'key'   => $key,
				'type'  => '',
				'label' => $key,
			);
			$label     = $field['label'] ?? $key;
			$formatted = ggm_lms_payment_format_answer( $field, $value );
			$is_phone  = 'phone' === ( $field['validation_type'] ?? '' )
				|| ( 'number' === ( $field['validation_type'] ?? '' ) && ! empty( $field['phone_country_enabled'] ) )
				|| false !== stripos( (string) $label, 'phone' )
				|| false !== stripos( (string) $label, 'whatsapp' )
				|| false !== stripos( (string) $label, 'mobile' );

			if ( $is_phone && ! $out['phone'] ) {
				$out['phone'] = is_array( $formatted ) ? wp_json_encode( $formatted ) : (string) wp_strip_all_tags( $formatted );
			}

			echo '<div class="ggm-payment-answer"><strong>' . esc_html( $label ) . ':</strong> ';
			if ( 'checkboxes' === ( $field['type'] ?? '' ) ) {
				echo wp_kses_post( $formatted );
			} elseif ( 'chip_selector' === ( $field['type'] ?? '' ) ) {
				foreach ( (array) $value as $chip ) {
					echo '<span class="ggm-answer-chip">' . esc_html( $chip ) . '</span> ';
				}
			} else {
				echo esc_html( is_array( $formatted ) ? wp_json_encode( $formatted ) : $formatted );
			}
			echo '</div>';
		}
		$out['answers'] = ob_get_clean();

		$files = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ggm_form_files WHERE submission_id=%d",
			$submission_id
		) );
		if ( $files ) {
			$file_labels = wp_list_pluck( $fields, 'label', 'key' );
			ob_start();
			foreach ( $files as $file ) {
				$preview_url  = wp_nonce_url( admin_url( 'admin-post.php?action=ggm_form_file&mode=preview&file_id=' . absint( $file->id ) ), 'ggm_form_file_' . absint( $file->id ) . '_preview' );
				$download_url = wp_nonce_url( admin_url( 'admin-post.php?action=ggm_form_file&mode=download&file_id=' . absint( $file->id ) ), 'ggm_form_file_' . absint( $file->id ) . '_download' );
				echo '<div class="ggm-payment-file"><strong>' . esc_html( $file_labels[ $file->field_key ] ?? __( 'File', 'ggm-member-dashboard' ) ) . ':</strong> ' . esc_html( $file->original_name ) . '<br>';
				echo '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url( $preview_url ) . '">' . esc_html__( 'Preview', 'ggm-member-dashboard' ) . '</a> ';
				echo '<a class="button button-small" href="' . esc_url( $download_url ) . '">' . esc_html__( 'Download', 'ggm-member-dashboard' ) . '</a></div>';
			}
			$out['files'] = ob_get_clean();
		}

		$cache[ $submission_id ] = $out;
		return $out;
	}
}

if ( ! function_exists( 'ggm_lms_payment_load_rows' ) ) {
	function ggm_lms_payment_load_rows() {
		global $wpdb;

		$purchase_rows = $wpdb->get_results(
			"SELECT 'purchase' AS source, p.*, u.display_name, u.user_email, NULL AS form_id, NULL AS form_title, NULL AS submission_id, NULL AS form_label, NULL AS form_description
			 FROM {$wpdb->prefix}ggm_payments p
			 LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
			 ORDER BY p.created_at DESC"
		);

		$form_rows = $wpdb->get_results(
			"SELECT 'form' AS source,
					fp.id, fp.user_id, NULL AS workshop_id, NULL AS workshop_slot_id, NULL AS course_id, NULL AS coupon_id,
					NULL AS original_amount, 0 AS discount_amount, 0 AS credit_amount, NULL AS pricing_mode,
					NULL AS contribution_option_id, NULL AS contribution_label, NULL AS contribution_amount,
					fp.razorpay_order_id, fp.razorpay_payment_id, fp.razorpay_signature,
					fp.amount, fp.currency, fp.status, fp.created_at,
					u.display_name, u.user_email, fp.form_id, f.title AS form_title, fp.submission_id,
					fp.label AS form_label, fp.description AS form_description
			 FROM {$wpdb->prefix}ggm_form_payments fp
			 LEFT JOIN {$wpdb->users} u ON fp.user_id = u.ID
			 LEFT JOIN {$wpdb->prefix}ggm_forms f ON f.id = fp.form_id
			 ORDER BY fp.created_at DESC"
		);

		$rows = array_merge( (array) $purchase_rows, (array) $form_rows );
		foreach ( $rows as $row ) {
			$row->phone = ggm_lms_payment_user_phone( $row->user_id );
			if ( 'form' === $row->source ) {
				$details = ggm_lms_payment_submission_details( $row->submission_id );
				if ( ! $row->phone && ! empty( $details['phone'] ) ) {
					$row->phone = $details['phone'];
				}
			}
		}
		usort( $rows, static function( $a, $b ) {
			return strtotime( (string) $b->created_at ) <=> strtotime( (string) $a->created_at );
		} );
		return $rows;
	}
}

if ( ! function_exists( 'ggm_lms_payment_matches_search' ) ) {
	function ggm_lms_payment_matches_search( $payment, $search ) {
		if ( '' === $search ) {
			return true;
		}
		$haystack = array(
			$payment->id,
			$payment->display_name ?? '',
			$payment->user_email ?? '',
			$payment->phone ?? '',
			$payment->razorpay_order_id ?? '',
			$payment->razorpay_payment_id ?? '',
			$payment->source ?? '',
			$payment->form_title ?? '',
			$payment->form_label ?? '',
			! empty( $payment->workshop_id ) ? get_the_title( $payment->workshop_id ) : '',
			! empty( $payment->course_id ) ? get_the_title( $payment->course_id ) : '',
		);
		return false !== stripos( implode( ' ', array_map( 'strval', $haystack ) ), $search );
	}
}

/* -----------------------------------------------------------------------
 * Handle: Mark as Refunded (nonce-protected POST action)
 * --------------------------------------------------------------------- */
if (
	isset( $_POST['ggm_action'], $_POST['payment_id'], $_POST['_wpnonce'] ) &&
	'mark_refunded' === $_POST['ggm_action'] &&
	wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ggm_refund_' . absint( $_POST['payment_id'] ) )
) {
	$pid = absint( $_POST['payment_id'] );
	$wpdb->update(
		$wpdb->prefix . 'ggm_payments',
		array( 'status' => 'refunded' ),
		array( 'id'     => $pid ),
		array( '%s' ),
		array( '%d' )
	);
	GGM_Credit::restore_for_refund( $pid );
	wp_safe_redirect( add_query_arg( array( 'refunded' => 1 ), remove_query_arg( array( 'ggm_action', 'payment_id', '_wpnonce' ) ) ) );
	exit;
}

/* -----------------------------------------------------------------------
 * Query helpers
 * --------------------------------------------------------------------- */
$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged          = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page       = 25;
$currency_symbol = ggm_get_setting( 'ggm_currency_symbol', '₹' );
$base_url       = admin_url( 'admin.php?page=ggm-lms-payments' );

$status_labels = array(
	''         => __( 'All', 'ggm-member-dashboard' ),
	'pending'  => __( 'Pending', 'ggm-member-dashboard' ),
	'success'  => __( 'Success', 'ggm-member-dashboard' ),
	'failed'   => __( 'Failed', 'ggm-member-dashboard' ),
	'refunded' => __( 'Refunded', 'ggm-member-dashboard' ),
);

$status_colors = array(
	'success'  => 'background:#e8faf3; color:#0e9e6e;',
	'pending'  => 'background:#fff3cd; color:#856404;',
	'failed'   => 'background:#fef3f2; color:#e53e3e;',
	'refunded' => 'background:#e2e8f0; color:#475569;',
);

$all_payments = ggm_lms_payment_load_rows();
$filtered_payments = array_values( array_filter( $all_payments, static function( $payment ) use ( $current_status, $search ) {
	if ( $current_status && $current_status !== $payment->status ) {
		return false;
	}
	return ggm_lms_payment_matches_search( $payment, $search );
} ) );

$total_items  = count( $filtered_payments );
$total_pages  = max( 1, (int) ceil( $total_items / $per_page ) );
$paged        = min( $paged, $total_pages );
$offset       = ( $paged - 1 ) * $per_page;
$payments     = array_slice( $filtered_payments, $offset, $per_page );
$total_amount = array_reduce( $filtered_payments, static function( $carry, $payment ) {
	return 'success' === $payment->status ? $carry + (float) $payment->amount : $carry;
}, 0.0 );

/* -----------------------------------------------------------------------
 * Handle: CSV Export
 * --------------------------------------------------------------------- */
if ( isset( $_GET['ggm_export'] ) && 'csv' === $_GET['ggm_export'] && check_admin_referer( 'ggm_export_payments' ) ) {
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="ggm-payments-' . date( 'Y-m-d' ) . '.csv"' );
	header( 'Pragma: no-cache' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Source', 'ID', 'Email', 'Phone', 'User Name', 'Item/Form', 'Amount', 'Currency', 'Razorpay Order ID', 'Razorpay Payment ID', 'Status', 'Date' ) );
	foreach ( $filtered_payments as $r ) {
		$item = 'form' === $r->source
			? ( $r->form_title ?: '#' . $r->form_id )
			: ( $r->workshop_id ? get_the_title( $r->workshop_id ) : ( $r->course_id ? get_the_title( $r->course_id ) : '' ) );
		fputcsv( $out, array(
			$r->source,
			$r->id,
			$r->user_email,
			$r->phone,
			$r->display_name,
			$item,
			$r->amount,
			$r->currency,
			$r->razorpay_order_id,
			$r->razorpay_payment_id,
			$r->status,
			$r->created_at,
		) );
	}
	fclose( $out );
	exit;
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Payments Log', 'ggm-member-dashboard' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['refunded'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Payment marked as refunded.', 'ggm-member-dashboard' ); ?></p></div>
	<?php endif; ?>

	<ul class="subsubsub" style="margin-bottom:8px;">
		<?php foreach ( $status_labels as $slug => $label ) :
			$url       = '' === $slug ? $base_url : add_query_arg( 'status', $slug, $base_url );
			$is_active = $current_status === $slug;
			?>
			<li>
				<a href="<?php echo esc_url( $url ); ?>" <?php echo $is_active ? 'class="current" style="font-weight:700;"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
				</a>
				<?php echo $slug !== 'refunded' ? ' | ' : ''; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
		<form method="get" action="<?php echo esc_url( $base_url ); ?>" style="display:flex; gap:6px; align-items:center;">
			<input type="hidden" name="page" value="ggm-lms-payments">
			<?php if ( $current_status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
			<?php endif; ?>
			<input
				type="search"
				name="s"
				value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'Search by name, email, phone or Razorpay ID...', 'ggm-member-dashboard' ); ?>"
				class="regular-text"
				style="width:340px;"
			>
			<?php submit_button( __( 'Search', 'ggm-member-dashboard' ), 'secondary', '', false ); ?>
			<?php if ( $search ) : ?>
				<a href="<?php echo esc_url( $current_status ? add_query_arg( 'status', $current_status, $base_url ) : $base_url ); ?>" class="button">
					<?php esc_html_e( 'Clear', 'ggm-member-dashboard' ); ?>
				</a>
			<?php endif; ?>
		</form>

		<form method="get" action="<?php echo esc_url( $base_url ); ?>">
			<input type="hidden" name="page" value="ggm-lms-payments">
			<input type="hidden" name="ggm_export" value="csv">
			<?php if ( $current_status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>"><?php endif; ?>
			<?php if ( $search ) : ?><input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>"><?php endif; ?>
			<?php wp_nonce_field( 'ggm_export_payments' ); ?>
			<?php submit_button( __( 'Export CSV', 'ggm-member-dashboard' ), 'secondary', '', false, array( 'style' => 'white-space:nowrap;' ) ); ?>
		</form>
	</div>

	<table class="wp-list-table widefat fixed striped" id="ggm-payments-table">
		<thead>
			<tr>
				<th style="width:60px;"><?php esc_html_e( 'ID', 'ggm-member-dashboard' ); ?></th>
				<th style="width:86px;"><?php esc_html_e( 'Source', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'User', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'ggm-member-dashboard' ); ?></th>
				<th style="width:110px;"><?php esc_html_e( 'Amount', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Order ID', 'ggm-member-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Payment ID', 'ggm-member-dashboard' ); ?></th>
				<th style="width:90px;"><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th>
				<th style="width:140px;"><?php esc_html_e( 'Date', 'ggm-member-dashboard' ); ?></th>
				<th style="width:140px;"><?php esc_html_e( 'Actions', 'ggm-member-dashboard' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $payments ) ) : ?>
				<tr><td colspan="10" style="text-align:center; padding:30px; color:#888;"><?php esc_html_e( 'No payments found.', 'ggm-member-dashboard' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $payments as $p ) :
					$sc           = $status_colors[ $p->status ] ?? '';
					$nonce_refund = wp_create_nonce( 'ggm_refund_' . $p->id );
					$item_label   = 'form' === $p->source
						? ( $p->form_title ?: '#' . $p->form_id )
						: ( $p->workshop_id ? get_the_title( $p->workshop_id ) : ( $p->course_id ? get_the_title( $p->course_id ) : __( 'Direct purchase', 'ggm-member-dashboard' ) ) );
					?>
					<tr id="ggm-row-<?php echo esc_attr( $p->source . '-' . $p->id ); ?>">
						<td><?php echo esc_html( $p->id ); ?></td>
						<td><span class="ggm-payment-source ggm-payment-source-<?php echo esc_attr( $p->source ); ?>"><?php echo esc_html( 'form' === $p->source ? __( 'Form', 'ggm-member-dashboard' ) : __( 'Purchase', 'ggm-member-dashboard' ) ); ?></span></td>
						<td>
							<strong><?php echo esc_html( $p->display_name ?: ( $p->user_id ? 'User #' . $p->user_id : __( 'Guest', 'ggm-member-dashboard' ) ) ); ?></strong><br>
							<span style="font-size:11px; color:#666;"><?php echo esc_html( $p->user_email ?? '' ); ?></span>
						</td>
						<td><?php echo esc_html( $p->phone ?: '—' ); ?></td>
						<td><?php echo esc_html( $currency_symbol . number_format( (float) $p->amount, 2 ) ); ?></td>
						<td><code style="font-size:11px;"><?php echo esc_html( $p->razorpay_order_id ?: '—' ); ?></code></td>
						<td><code style="font-size:11px;"><?php echo esc_html( $p->razorpay_payment_id ?: '—' ); ?></code></td>
						<td>
							<span style="<?php echo esc_attr( $sc ); ?> padding:3px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap;">
								<?php echo esc_html( ucfirst( $p->status ) ); ?>
							</span>
						</td>
						<td style="font-size:12px; white-space:nowrap;"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $p->created_at ) ) ); ?></td>
						<td style="white-space:nowrap;">
							<button type="button" class="button button-small ggm-toggle-details" data-target="ggm-details-<?php echo esc_attr( $p->source . '-' . $p->id ); ?>" style="margin-right:4px;"><?php esc_html_e( 'Details', 'ggm-member-dashboard' ); ?></button>
							<?php if ( 'purchase' === $p->source && 'success' === $p->status ) : ?>
								<form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Mark this payment as refunded?', 'ggm-member-dashboard' ); ?>');">
									<input type="hidden" name="ggm_action" value="mark_refunded">
									<input type="hidden" name="payment_id" value="<?php echo esc_attr( $p->id ); ?>">
									<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce_refund ); ?>">
									<button type="submit" class="button button-small" style="color:#c0392b; border-color:#c0392b;"><?php esc_html_e( 'Refund', 'ggm-member-dashboard' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
					<tr id="ggm-details-<?php echo esc_attr( $p->source . '-' . $p->id ); ?>" class="ggm-details-row" style="display:none; background:#f9f9f9;">
						<td colspan="10" style="padding:16px 20px;">
							<div class="ggm-payment-detail-grid">
								<div>
									<h3><?php esc_html_e( 'Payment Details', 'ggm-member-dashboard' ); ?></h3>
									<table class="ggm-payment-detail-table">
										<tr><th><?php esc_html_e( 'Source', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( 'form' === $p->source ? __( 'Form payment', 'ggm-member-dashboard' ) : __( 'Workshop/Course purchase', 'ggm-member-dashboard' ) ); ?></td></tr>
										<tr><th><?php esc_html_e( 'Item/Form', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $item_label ); ?></td></tr>
										<tr><th><?php esc_html_e( 'User ID', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $p->user_id ?: '—' ); ?></td></tr>
										<tr><th><?php esc_html_e( 'Email', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $p->user_email ?? '—' ); ?></td></tr>
										<tr><th><?php esc_html_e( 'Phone', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $p->phone ?: '—' ); ?></td></tr>
										<tr><th><?php esc_html_e( 'Amount', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $p->currency . ' ' . number_format( (float) $p->amount, 2 ) ); ?></td></tr>
										<?php if ( 'purchase' === $p->source && isset( $p->original_amount ) && null !== $p->original_amount ) : ?><tr><th><?php esc_html_e( 'Original Amount', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $currency_symbol . number_format( (float) $p->original_amount, 2 ) ); ?></td></tr><?php endif; ?>
										<?php if ( 'purchase' === $p->source && ! empty( $p->discount_amount ) ) : ?><tr><th><?php esc_html_e( 'Discount', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $currency_symbol . number_format( (float) $p->discount_amount, 2 ) ); ?></td></tr><?php endif; ?>
										<?php if ( 'purchase' === $p->source && ! empty( $p->credit_amount ) ) : ?><tr><th><?php esc_html_e( 'Credit Used', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $currency_symbol . number_format( (float) $p->credit_amount, 2 ) ); ?></td></tr><?php endif; ?>
										<tr><th><?php esc_html_e( 'Razorpay Order ID', 'ggm-member-dashboard' ); ?></th><td><code><?php echo esc_html( $p->razorpay_order_id ?: '—' ); ?></code></td></tr>
										<tr><th><?php esc_html_e( 'Razorpay Payment ID', 'ggm-member-dashboard' ); ?></th><td><code><?php echo esc_html( $p->razorpay_payment_id ?: '—' ); ?></code></td></tr>
										<tr><th><?php esc_html_e( 'Razorpay Signature', 'ggm-member-dashboard' ); ?></th><td class="ggm-code-cell"><code><?php echo esc_html( $p->razorpay_signature ?: '—' ); ?></code></td></tr>
										<tr><th><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( ucfirst( $p->status ) ); ?></td></tr>
										<tr><th><?php esc_html_e( 'Created At', 'ggm-member-dashboard' ); ?></th><td><?php echo esc_html( $p->created_at ); ?></td></tr>
									</table>
								</div>
								<?php if ( 'form' === $p->source ) : $submission_details = ggm_lms_payment_submission_details( $p->submission_id ); ?>
									<div>
										<h3><?php esc_html_e( 'Submitted Form Fields', 'ggm-member-dashboard' ); ?></h3>
										<div class="ggm-payment-answers"><?php echo $submission_details['answers'] ? wp_kses_post( $submission_details['answers'] ) : esc_html__( 'No submitted fields found.', 'ggm-member-dashboard' ); ?></div>
										<h3><?php esc_html_e( 'Uploaded Files', 'ggm-member-dashboard' ); ?></h3>
										<div class="ggm-payment-files"><?php echo $submission_details['files'] ? wp_kses_post( $submission_details['files'] ) : '—'; ?></div>
									</div>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr style="background:#f0f6fc;">
				<td colspan="4" style="font-weight:700; padding:10px 8px;">
					<?php esc_html_e( 'Total Successful', 'ggm-member-dashboard' ); ?>
					<?php if ( $current_status || $search ) : ?><span style="font-weight:400; font-size:11px; color:#555;">(<?php esc_html_e( 'filtered', 'ggm-member-dashboard' ); ?>)</span><?php endif; ?>
				</td>
				<td colspan="6" style="font-weight:700; padding:10px 8px;"><?php echo esc_html( $currency_symbol . number_format( $total_amount, 2 ) ); ?></td>
			</tr>
		</tfoot>
	</table>

	<?php if ( $total_pages > 1 ) :
		$page_links = paginate_links( array(
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'total'     => $total_pages,
			'current'   => $paged,
		) );
		?>
		<div class="tablenav bottom" style="margin-top:10px;">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						esc_html( _n( '%d item', '%d items', $total_items, 'ggm-member-dashboard' ) ),
						number_format_i18n( $total_items )
					);
					?>
				</span>
				<?php echo wp_kses_post( $page_links ); ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<style>
.ggm-payment-source{display:inline-flex;padding:3px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:700}.ggm-payment-source-form{background:#ecfdf5;color:#047857}.ggm-payment-detail-grid{display:grid;grid-template-columns:minmax(280px,420px) minmax(0,1fr);gap:24px}.ggm-payment-detail-grid h3{margin:0 0 10px;font-size:14px}.ggm-payment-detail-table{width:100%;border-collapse:collapse;font-size:13px}.ggm-payment-detail-table th{width:160px;text-align:left;padding:5px 12px 5px 0;color:#555}.ggm-payment-detail-table td{padding:5px 0}.ggm-code-cell{word-break:break-all;font-size:11px}.ggm-payment-answer{margin:0 0 8px}.ggm-payment-file{margin:0 0 10px}.ggm-answer-chip{display:inline-flex;margin:2px 4px 2px 0;padding:3px 8px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:12px;font-weight:600}@media(max-width:960px){.ggm-payment-detail-grid{grid-template-columns:1fr}}
</style>

<script>
(function () {
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.ggm-toggle-details').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var row = document.getElementById(btn.getAttribute('data-target'));
				if (!row) return;
				var visible = row.style.display !== 'none';
				row.style.display = visible ? 'none' : 'table-row';
				btn.textContent = visible ? <?php echo wp_json_encode( __( 'Details', 'ggm-member-dashboard' ) ); ?> : <?php echo wp_json_encode( __( 'Hide', 'ggm-member-dashboard' ) ); ?>;
			});
		});
	});
}());
</script>
