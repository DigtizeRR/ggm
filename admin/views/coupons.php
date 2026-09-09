<?php
/**
 * Admin view — Coupon Management.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$coupons         = GGM_Coupon::get_all( 'all' );
$usage_rows      = GGM_Coupon::get_usage_report( 100 );
$currency_symbol = ggm_get_setting( 'ggm_currency_symbol', '₹' );

$workshops = get_posts( array(
	'post_type'      => 'workshop',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );
?>
<div class="wrap ggm-admin-wrap">
	<h1 class="ggm-admin-title">
		<?php esc_html_e( 'Coupons', 'ggm-member-dashboard' ); ?>
		<button class="button button-primary" id="ggm-add-coupon-btn" style="margin-left:10px;">
			<?php esc_html_e( '+ Add Coupon', 'ggm-member-dashboard' ); ?>
		</button>
	</h1>

	<!-- Add / Edit Form -->
	<div id="ggm-coupon-form-wrap" class="ggm-dashboard-card" style="display:none; margin-bottom:20px;">
		<div class="card-body">
			<h3 id="ggm-coupon-form-title"><?php esc_html_e( 'New Coupon', 'ggm-member-dashboard' ); ?></h3>
			<form id="ggm-coupon-form">
				<input type="hidden" name="coupon_id" id="c-id" value="">
				<table class="form-table">
					<tr>
						<th><label for="c_code"><?php esc_html_e( 'Coupon Code', 'ggm-member-dashboard' ); ?></label></th>
						<td><input type="text" name="code" id="c_code" class="regular-text" style="text-transform:uppercase;" required></td>
					</tr>
					<tr>
						<th><label for="c_name"><?php esc_html_e( 'Coupon Name', 'ggm-member-dashboard' ); ?></label></th>
						<td><input type="text" name="name" id="c_name" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="c_description"><?php esc_html_e( 'Description', 'ggm-member-dashboard' ); ?></label></th>
						<td><textarea name="description" id="c_description" class="large-text" rows="2"></textarea></td>
					</tr>
					<tr>
						<th><label for="c_discount_type"><?php esc_html_e( 'Discount Type', 'ggm-member-dashboard' ); ?></label></th>
						<td>
							<select name="discount_type" id="c_discount_type">
								<option value="fixed"><?php esc_html_e( 'Fixed Amount', 'ggm-member-dashboard' ); ?></option>
								<option value="percentage"><?php esc_html_e( 'Percentage', 'ggm-member-dashboard' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="c_discount_value"><?php esc_html_e( 'Discount Value', 'ggm-member-dashboard' ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" name="discount_value" id="c_discount_value" class="regular-text" required value="0">
							<span id="c_discount_value_suffix" style="margin-left:6px; color:#64748b;"><?php echo esc_html( $currency_symbol ); ?></span>
						</td>
					</tr>
					<tr id="c_max_discount_row">
						<th><label for="c_max_discount"><?php esc_html_e( 'Maximum Discount', 'ggm-member-dashboard' ); ?></label></th>
						<td>
							<input type="number" step="0.01" min="0" name="max_discount" id="c_max_discount" class="regular-text" placeholder="<?php esc_attr_e( 'No cap', 'ggm-member-dashboard' ); ?>">
							<p class="description"><?php esc_html_e( 'Only applies to percentage discounts. Leave empty for no cap.', 'ggm-member-dashboard' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="c_min_purchase"><?php esc_html_e( 'Minimum Purchase', 'ggm-member-dashboard' ); ?></label></th>
						<td><input type="number" step="0.01" min="0" name="min_purchase" id="c_min_purchase" class="regular-text" placeholder="<?php esc_attr_e( 'No minimum', 'ggm-member-dashboard' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="c_max_purchase"><?php esc_html_e( 'Maximum Purchase', 'ggm-member-dashboard' ); ?></label></th>
						<td><input type="number" step="0.01" min="0" name="max_purchase" id="c_max_purchase" class="regular-text" placeholder="<?php esc_attr_e( 'No maximum', 'ggm-member-dashboard' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="c_start_date"><?php esc_html_e( 'Start Date', 'ggm-member-dashboard' ); ?></label></th>
						<td><input type="date" name="start_date" id="c_start_date" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="c_expiry_date"><?php esc_html_e( 'Expiry Date', 'ggm-member-dashboard' ); ?></label></th>
						<td><input type="date" name="expiry_date" id="c_expiry_date" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="c_max_uses"><?php esc_html_e( 'Maximum Uses', 'ggm-member-dashboard' ); ?></label></th>
						<td><input type="number" min="0" name="max_uses" id="c_max_uses" class="regular-text" placeholder="<?php esc_attr_e( 'Unlimited', 'ggm-member-dashboard' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="c_max_uses_per_user"><?php esc_html_e( 'Maximum Uses Per User', 'ggm-member-dashboard' ); ?></label></th>
						<td><input type="number" min="0" name="max_uses_per_user" id="c_max_uses_per_user" class="regular-text" placeholder="<?php esc_attr_e( 'Unlimited', 'ggm-member-dashboard' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="c_workshops"><?php esc_html_e( 'Applicable Workshops', 'ggm-member-dashboard' ); ?></label></th>
						<td>
							<select name="applicable_workshops[]" id="c_workshops" multiple style="min-width:320px; min-height:100px;">
								<?php foreach ( $workshops as $ws ) : ?>
									<option value="<?php echo esc_attr( $ws->ID ); ?>"><?php echo esc_html( $ws->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Hold Ctrl / Cmd to select multiple. Leave empty to allow all workshops.', 'ggm-member-dashboard' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="c_status"><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></label></th>
						<td>
							<select name="status" id="c_status">
								<option value="active"><?php esc_html_e( 'Active', 'ggm-member-dashboard' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'ggm-member-dashboard' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Coupon', 'ggm-member-dashboard' ); ?></button>
					<button type="button" class="button" id="ggm-cancel-coupon"><?php esc_html_e( 'Cancel', 'ggm-member-dashboard' ); ?></button>
				</p>
				<div id="ggm-coupon-feedback" style="display:none; margin-top:8px; padding:8px 12px; border-radius:4px;"></div>
			</form>
		</div>
	</div>

	<!-- Coupons Table -->
	<div class="ggm-dashboard-card" style="margin-bottom:20px;">
		<div class="card-body">
			<?php if ( empty( $coupons ) ) : ?>
				<p class="ggm-no-data" style="text-align:center; padding:30px; color:#888;">
					<?php esc_html_e( 'No coupons found. Click "+ Add Coupon" to create one.', 'ggm-member-dashboard' ); ?>
				</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Code', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Name', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Discount', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Uses', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Expiry', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Actions', 'ggm-member-dashboard' ); ?></th>
						</tr>
					</thead>
					<tbody id="ggm-coupons-tbody">
						<?php foreach ( $coupons as $c ) :
							$ws_ids  = json_decode( $c->applicable_workshops ?? '[]', true ) ?: array();
							$discount_label = 'percentage' === $c->discount_type
								? number_format( (float) $c->discount_value, 0 ) . '%'
								: $currency_symbol . number_format( (float) $c->discount_value, 2 );
						?>
							<tr id="ggm-coupon-row-<?php echo esc_attr( $c->id ); ?>">
								<td><code><?php echo esc_html( $c->code ); ?></code></td>
								<td><?php echo esc_html( $c->name ); ?></td>
								<td><?php echo esc_html( $discount_label ); ?></td>
								<td>
									<?php
									echo esc_html( $c->used_count );
									echo ' / ';
									echo esc_html( null !== $c->max_uses ? $c->max_uses : __( '∞', 'ggm-member-dashboard' ) );
									?>
								</td>
								<td><?php echo esc_html( $c->expiry_date ? date_i18n( get_option( 'date_format' ), strtotime( $c->expiry_date ) ) : '—' ); ?></td>
								<td>
									<?php if ( 'active' === $c->status ) : ?>
										<span style="background:#e8faf3; color:#0e9e6e; padding:3px 8px; border-radius:10px; font-weight:600; font-size:11px;"><?php esc_html_e( 'Active', 'ggm-member-dashboard' ); ?></span>
									<?php else : ?>
										<span style="background:#fef3f2; color:#e53e3e; padding:3px 8px; border-radius:10px; font-size:11px;"><?php esc_html_e( 'Inactive', 'ggm-member-dashboard' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<button class="button button-small ggm-edit-coupon"
										data-id="<?php echo esc_attr( $c->id ); ?>"
										data-code="<?php echo esc_attr( $c->code ); ?>"
										data-name="<?php echo esc_attr( $c->name ); ?>"
										data-description="<?php echo esc_attr( $c->description ); ?>"
										data-discount-type="<?php echo esc_attr( $c->discount_type ); ?>"
										data-discount-value="<?php echo esc_attr( $c->discount_value ); ?>"
										data-max-discount="<?php echo esc_attr( $c->max_discount ?? '' ); ?>"
										data-min-purchase="<?php echo esc_attr( $c->min_purchase ?? '' ); ?>"
										data-max-purchase="<?php echo esc_attr( $c->max_purchase ?? '' ); ?>"
										data-start-date="<?php echo esc_attr( $c->start_date ? gmdate( 'Y-m-d', strtotime( $c->start_date ) ) : '' ); ?>"
										data-expiry-date="<?php echo esc_attr( $c->expiry_date ? gmdate( 'Y-m-d', strtotime( $c->expiry_date ) ) : '' ); ?>"
										data-max-uses="<?php echo esc_attr( $c->max_uses ?? '' ); ?>"
										data-max-uses-per-user="<?php echo esc_attr( $c->max_uses_per_user ?? '' ); ?>"
										data-status="<?php echo esc_attr( $c->status ); ?>"
										data-workshops="<?php echo esc_attr( wp_json_encode( array_map( 'intval', $ws_ids ) ) ); ?>">
										<?php esc_html_e( 'Edit', 'ggm-member-dashboard' ); ?>
									</button>
									<button class="button button-small ggm-delete-coupon"
										data-id="<?php echo esc_attr( $c->id ); ?>"
										data-code="<?php echo esc_attr( $c->code ); ?>"
										style="color:#b32d2e; border-color:#b32d2e;">
										<?php esc_html_e( 'Delete', 'ggm-member-dashboard' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<!-- Usage Reports -->
	<div class="ggm-dashboard-card">
		<div class="card-body">
			<h2><?php esc_html_e( 'Coupon Usage Reports', 'ggm-member-dashboard' ); ?></h2>
			<?php if ( empty( $usage_rows ) ) : ?>
				<p class="ggm-no-data" style="text-align:center; padding:30px; color:#888;">
					<?php esc_html_e( 'No coupon usage recorded yet.', 'ggm-member-dashboard' ); ?>
				</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Coupon', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'User', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Original', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Discount', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Final', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Date', 'ggm-member-dashboard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $usage_rows as $row ) :
							$user = get_userdata( $row->user_id );
						?>
							<tr>
								<td><code><?php echo esc_html( $row->coupon_code ); ?></code></td>
								<td><?php echo esc_html( $user ? $user->display_name : '#' . $row->user_id ); ?></td>
								<td><?php echo esc_html( $currency_symbol . number_format( (float) $row->original_amount, 2 ) ); ?></td>
								<td><?php echo esc_html( $currency_symbol . number_format( (float) $row->discount_amount, 2 ) ); ?></td>
								<td><?php echo esc_html( $currency_symbol . number_format( (float) $row->final_amount, 2 ) ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->used_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
jQuery(function($){

	function togglePercentUI() {
		var isPercent = $('#c_discount_type').val() === 'percentage';
		$('#c_discount_value_suffix').text( isPercent ? '%' : '<?php echo esc_js( $currency_symbol ); ?>' );
		$('#c_max_discount_row').toggle( isPercent );
	}
	$('#c_discount_type').on('change', togglePercentUI);

	$('#ggm-add-coupon-btn').on('click', function(){
		$('#ggm-coupon-form-wrap').show();
		$('#ggm-coupon-form-title').text( '<?php echo esc_js( __( 'New Coupon', 'ggm-member-dashboard' ) ); ?>' );
		$('#ggm-coupon-form')[0].reset();
		$('#c-id').val('');
		$('#c_workshops option').prop('selected', false);
		togglePercentUI();
		$('#ggm-coupon-feedback').hide();
		$('html,body').animate({ scrollTop: 0 }, 300);
	});

	$('#ggm-cancel-coupon').on('click', function(){
		$('#ggm-coupon-form-wrap').hide();
		$('#ggm-coupon-feedback').hide();
	});

	$(document).on('click', '.ggm-edit-coupon', function(){
		var d = $(this).data();

		$('#ggm-coupon-form-wrap').show();
		$('#ggm-coupon-form-title').text( '<?php echo esc_js( __( 'Edit Coupon', 'ggm-member-dashboard' ) ); ?>' );
		$('#c-id').val( d.id );
		$('#c_code').val( d.code );
		$('#c_name').val( d.name );
		$('#c_description').val( d.description );
		$('#c_discount_type').val( d.discountType );
		$('#c_discount_value').val( d.discountValue );
		$('#c_max_discount').val( d.maxDiscount || '' );
		$('#c_min_purchase').val( d.minPurchase || '' );
		$('#c_max_purchase').val( d.maxPurchase || '' );
		$('#c_start_date').val( d.startDate || '' );
		$('#c_expiry_date').val( d.expiryDate || '' );
		$('#c_max_uses').val( d.maxUses || '' );
		$('#c_max_uses_per_user').val( d.maxUsesPerUser || '' );
		$('#c_status').val( d.status );
		togglePercentUI();

		var wsIds = [];
		try { wsIds = JSON.parse( d.workshops || '[]' ); } catch(e){}
		$('#c_workshops option').each(function(){
			$(this).prop( 'selected', wsIds.indexOf( parseInt( $(this).val(), 10 ) ) !== -1 );
		});

		$('#ggm-coupon-feedback').hide();
		$('html,body').animate({ scrollTop: 0 }, 300);
	});

	$('#ggm-coupon-form').on('submit', function(e){
		e.preventDefault();

		var $btn = $(this).find('button[type=submit]');
		var fb   = $('#ggm-coupon-feedback');
		fb.hide();
		$btn.prop('disabled', true).text( '<?php echo esc_js( __( 'Saving…', 'ggm-member-dashboard' ) ); ?>' );

		$.post( ggmAdmin.ajaxurl, {
			action:                  'ggm_save_coupon',
			nonce:                   ggmAdmin.nonce,
			coupon_id:               $('#c-id').val(),
			code:                    $('#c_code').val(),
			name:                    $('#c_name').val(),
			description:             $('#c_description').val(),
			discount_type:           $('#c_discount_type').val(),
			discount_value:          $('#c_discount_value').val(),
			max_discount:            $('#c_max_discount').val(),
			min_purchase:            $('#c_min_purchase').val(),
			max_purchase:            $('#c_max_purchase').val(),
			start_date:              $('#c_start_date').val(),
			expiry_date:             $('#c_expiry_date').val(),
			max_uses:                $('#c_max_uses').val(),
			max_uses_per_user:       $('#c_max_uses_per_user').val(),
			applicable_workshops:    $('#c_workshops').val() || [],
			status:                  $('#c_status').val(),
		} ).done(function(res){
			if ( res.success ) {
				location.reload();
			} else {
				fb.text( res.data.message )
					.css({ 'color': '#b32d2e', 'background': '#fef3f2', 'border': '1px solid #f5c6cb' })
					.show();
				$btn.prop('disabled', false).text( '<?php echo esc_js( __( 'Save Coupon', 'ggm-member-dashboard' ) ); ?>' );
			}
		}).fail(function(){
			fb.text( '<?php echo esc_js( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?>' )
				.css({ 'color': '#b32d2e', 'background': '#fef3f2', 'border': '1px solid #f5c6cb' })
				.show();
			$btn.prop('disabled', false).text( '<?php echo esc_js( __( 'Save Coupon', 'ggm-member-dashboard' ) ); ?>' );
		});
	});

	$(document).on('click', '.ggm-delete-coupon', function(){
		var id   = $(this).data('id');
		var code = $(this).data('code');

		if ( ! window.confirm(
			'<?php echo esc_js( __( 'Are you sure you want to delete the coupon', 'ggm-member-dashboard' ) ); ?>' +
			' "' + code + '"? ' +
			'<?php echo esc_js( __( 'This cannot be undone.', 'ggm-member-dashboard' ) ); ?>'
		) ) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post( ggmAdmin.ajaxurl, {
			action:    'ggm_delete_coupon',
			nonce:     ggmAdmin.nonce,
			coupon_id: id,
		} ).done(function(res){
			if ( res.success ) {
				$('#ggm-coupon-row-' + id).fadeOut(300, function(){ $(this).remove(); });
			} else {
				window.alert( res.data.message );
				$btn.prop('disabled', false);
			}
		}).fail(function(){
			window.alert( '<?php echo esc_js( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?>' );
			$btn.prop('disabled', false);
		});
	});

});
</script>
