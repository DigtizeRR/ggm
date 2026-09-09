<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) ); }
$tab = sanitize_key( $_GET['tab'] ?? 'automation' );
$tabs = array(
	'automation' => __( 'Health Intakes & Disease Automation', 'ggm-member-dashboard' ),
	'submissions' => __( 'Submitted Forms', 'ggm-member-dashboard' ),
	'myforms' => __( 'My Forms', 'ggm-member-dashboard' ),
	'builder' => __( 'Create New Forms', 'ggm-member-dashboard' ),
);
if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'automation'; }
?>
<div class="wrap ggm-health-admin">
	<header class="ggm-admin-page-header">
		<div><h1><?php esc_html_e( 'Health Intake Forms', 'ggm-member-dashboard' ); ?></h1><p><?php esc_html_e( 'Create and manage health intake forms for your programs and members.', 'ggm-member-dashboard' ); ?></p></div>
		<a class="ggm-help-button" href="<?php echo esc_url( admin_url( 'admin.php?page=ggm-help-center' ) ); ?>"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help', 'ggm-member-dashboard' ); ?></a>
	</header>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $key => $label ) : ?><a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page'=>'ggm-health-intakes','tab'=>$key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
	</nav>
	<?php if ( 'automation' === $tab ) : ?>
		<?php $rules = get_option( GGM_Health_Intake::RULES_OPTION, array() ); if ( ! $rules ) { $rules = array( array() ); } ?>
		<h2><?php esc_html_e( 'Disease Message Rules', 'ggm-member-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'Legacy health submissions, medical reports, downloads, and disease-rule data remain available for historical records. Members now complete published form-builder forms assigned to the dashboard.', 'ggm-member-dashboard' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ggm_save_health_rules"><?php wp_nonce_field( 'ggm_save_health_rules' ); ?>
			<div id="ggm-rule-list">
			<?php foreach ( $rules as $i => $rule ) : ?>
				<div class="postbox ggm-health-rule" style="padding:16px">
					<p><label><strong><?php esc_html_e( 'Keywords', 'ggm-member-dashboard' ); ?></strong><br><input class="widefat" name="rules[<?php echo esc_attr( $i ); ?>][keywords]" value="<?php echo esc_attr( $rule['keywords'] ?? '' ); ?>" placeholder="diabetes, sugar, glucose"></label></p>
					<p><label><strong><?php esc_html_e( 'Email subject', 'ggm-member-dashboard' ); ?></strong><br><input class="widefat" name="rules[<?php echo esc_attr( $i ); ?>][email_subject]" value="<?php echo esc_attr( $rule['email_subject'] ?? '' ); ?>"></label></p>
					<p><label><strong><?php esc_html_e( 'Email body', 'ggm-member-dashboard' ); ?></strong><br><textarea class="widefat" rows="5" name="rules[<?php echo esc_attr( $i ); ?>][email_body]"><?php echo esc_textarea( $rule['email_body'] ?? '' ); ?></textarea></label></p>
					<p class="ggm-rule-attachment"><input type="hidden" name="rules[<?php echo esc_attr( $i ); ?>][email_attachment_id]" value="<?php echo esc_attr( absint( $rule['email_attachment_id'] ?? 0 ) ); ?>"><button type="button" class="button ggm-choose-rule-file"><?php esc_html_e( 'Choose email attachment', 'ggm-member-dashboard' ); ?></button> <span><?php echo esc_html( absint($rule['email_attachment_id']??0) ? basename((string)get_attached_file(absint($rule['email_attachment_id']))) : __('No file selected','ggm-member-dashboard') ); ?></span></p>
					<p><label><strong><?php esc_html_e( 'WhatsApp message', 'ggm-member-dashboard' ); ?></strong><br><textarea class="widefat" rows="4" name="rules[<?php echo esc_attr( $i ); ?>][whatsapp]"><?php echo esc_textarea( $rule['whatsapp'] ?? '' ); ?></textarea></label></p>
					<p class="ggm-rule-attachment"><input type="hidden" name="rules[<?php echo esc_attr( $i ); ?>][whatsapp_attachment_id]" value="<?php echo esc_attr( absint( $rule['whatsapp_attachment_id'] ?? 0 ) ); ?>"><button type="button" class="button ggm-choose-rule-file"><?php esc_html_e( 'Choose WhatsApp attachment', 'ggm-member-dashboard' ); ?></button> <span><?php echo esc_html( absint($rule['whatsapp_attachment_id']??0) ? basename((string)get_attached_file(absint($rule['whatsapp_attachment_id']))) : __('No file selected','ggm-member-dashboard') ); ?></span></p>
				</div>
			<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="ggm-add-rule"><?php esc_html_e( 'Add Rule', 'ggm-member-dashboard' ); ?></button>
			<?php submit_button( __( 'Save Rules', 'ggm-member-dashboard' ), 'primary', 'submit', false ); ?>
		</form>
		<script>(function(){var l=document.getElementById('ggm-rule-list'),b=document.getElementById('ggm-add-rule');b.onclick=function(){var n=l.querySelector('.ggm-health-rule').cloneNode(true),i=l.children.length;n.querySelectorAll('input,textarea').forEach(function(f){f.name=f.name.replace(/rules\[\d+\]/,'rules['+i+']');f.value='';});n.querySelectorAll('.ggm-rule-attachment span').forEach(function(s){s.textContent='No file selected'});l.appendChild(n);};l.addEventListener('click',function(e){if(!e.target.classList.contains('ggm-choose-rule-file'))return;var row=e.target.closest('.ggm-rule-attachment'),frame=wp.media({title:'Choose attachment',button:{text:'Use this file'},multiple:false});frame.on('select',function(){var f=frame.state().get('selection').first().toJSON();row.querySelector('input').value=f.id;row.querySelector('span').textContent=f.filename||f.title});frame.open()})})();</script>
	<?php elseif ( 'submissions' === $tab ) : ?>
		<?php
		global $wpdb;
		$selected_form_key = sanitize_text_field( wp_unslash( $_GET['form_id'] ?? '0' ) );
		if ( 'legacy' !== $selected_form_key && ! absint( $selected_form_key ) ) { $selected_form_key = '0'; }
		$selected_form_id  = 'legacy' === $selected_form_key ? 0 : absint( $selected_form_key );
		$submission_search = sanitize_text_field( wp_unslash( $_GET['submission_search'] ?? '' ) );
		$submissions_page  = max( 1, absint( $_GET['submissions_page'] ?? 1 ) );
		$submissions_limit = 20;
		$submission_forms  = GGM_Form_Builder::get_forms();
		$submission_rows   = array();
		$show_legacy       = 'legacy' === $selected_form_key || '0' === $selected_form_key;
		$show_builder      = 'legacy' !== $selected_form_key;

		if ( $show_legacy ) {
			foreach ( get_users( array( 'meta_key'=>GGM_Health_Intake::META_KEY ) ) as $user ) {
				$items = get_user_meta( $user->ID, GGM_Health_Intake::META_KEY, true );
				foreach ( (array) $items as $legacy_key => $item ) {
					$workshop_id = absint( $item['workshop_id'] ?? ( is_numeric( $legacy_key ) ? $legacy_key : 0 ) );
					$workshop_title = (string) ( $item['workshop'] ?? '' );
					if ( ! $workshop_title && $workshop_id ) { $workshop_title = get_the_title( $workshop_id ); }
					$base_args = array( 'action'=>'ggm_health_report', 'user_id'=>$user->ID, 'workshop_id'=>$workshop_id );
					$files_html = '—';
					if ( ! empty( $item['report_path'] ) && $workshop_id ) {
						$preview_url = wp_nonce_url( add_query_arg( $base_args + array( 'mode'=>'preview' ), admin_url( 'admin-post.php' ) ), 'ggm_health_report_'.$user->ID.'_'.$workshop_id.'_preview' );
						$download_url = wp_nonce_url( add_query_arg( $base_args + array( 'mode'=>'download' ), admin_url( 'admin-post.php' ) ), 'ggm_health_report_'.$user->ID.'_'.$workshop_id.'_download' );
						$files_html = '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url( $preview_url ) . '">' . esc_html__( 'Preview', 'ggm-member-dashboard' ) . '</a> <a class="button button-small" href="' . esc_url( $download_url ) . '">' . esc_html__( 'Download', 'ggm-member-dashboard' ) . '</a>';
					}
					$delete_url = wp_nonce_url(
						add_query_arg(
							array_filter(
								array(
									'action'           => 'ggm_delete_legacy_health_submission',
									'user_id'          => (int) $user->ID,
									'legacy_key'       => (string) $legacy_key,
									'filter_form_id'   => '0' !== $selected_form_key ? $selected_form_key : null,
									'submissions_page' => $submissions_page,
								),
								static function( $value ) { return null !== $value && '' !== $value; }
							),
							admin_url( 'admin-post.php' )
						),
						'ggm_delete_legacy_health_submission_' . (int) $user->ID . '_' . (string) $legacy_key
					);
					$contact = trim( ( ( $item['email'] ?? '' ) ?: $user->user_email ) . "\n" . (string) ( $item['phone'] ?? '' ) );
					$diseases = trim( (string) ( $item['diseases'] ?? '' ) . "\n" . (string) ( $item['mentioned_diseases'] ?? '' ) );
					$health = sprintf( 'Weight: %s | BP: %s | Sugar: %s', ( $item['weight'] ?? '' ) ?: '—', ( $item['bp'] ?? '' ) ?: '—', ( $item['sugar_level'] ?? '' ) ?: '—' );
					$submission_rows[] = array(
						'user_id'   => (int) $user->ID,
						'form'      => esc_html__( 'Legacy Health Intake', 'ggm-member-dashboard' ),
						'member'    => '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $item['full_name'] ?? $user->display_name ) . '</a>',
						'placement' => esc_html( $workshop_title ?: '—' ),
						'answers'   => '<div class="ggm-submission-answer"><strong>' . esc_html__( 'Contact', 'ggm-member-dashboard' ) . ':</strong><br>' . nl2br( esc_html( $contact ?: '—' ) ) . '</div><div class="ggm-submission-answer"><strong>' . esc_html__( 'Diseases', 'ggm-member-dashboard' ) . ':</strong><br>' . nl2br( esc_html( $diseases ?: '—' ) ) . '</div><div class="ggm-submission-answer"><strong>' . esc_html__( 'Health', 'ggm-member-dashboard' ) . ':</strong> ' . esc_html( $health ) . '</div>',
						'files'     => $files_html,
						'submitted' => (string) ( $item['submitted_at'] ?? '' ),
						'sort'      => strtotime( (string) ( $item['submitted_at'] ?? '' ) ) ?: 0,
						'delete'    => $delete_url,
						'confirm'   => __( 'Delete this legacy health intake response permanently? Uploaded report file for this response will also be removed.', 'ggm-member-dashboard' ),
						'review'    => '',
						'reviewed'  => '',
						'search'    => implode( ' ', array( 'Legacy Health Intake', $item['full_name'] ?? $user->display_name, $user->user_email, $workshop_title, $contact, $diseases, $health, $item['submitted_at'] ?? '' ) ),
					);
				}
			}
		}

		if ( $show_builder ) {
			$where_clauses = array( "s.status='submitted'" );
			$where_values  = array();
			if ( $selected_form_id ) {
				$where_clauses[] = 's.form_id=%d';
				$where_values[]  = $selected_form_id;
			}
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			$new_rows_sql = "SELECT s.*,f.title,u.display_name,u.user_email FROM {$wpdb->prefix}ggm_form_submissions s LEFT JOIN {$wpdb->prefix}ggm_forms f ON f.id=s.form_id LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id {$where_sql} ORDER BY s.submitted_at DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$new_rows = $where_values ? $wpdb->get_results( $wpdb->prepare( $new_rows_sql, $where_values ) ) : $wpdb->get_results( $new_rows_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $new_rows as $row ) {
				$answers = json_decode( $row->answers_json, true );
				$schema = json_decode( $row->schema_snapshot_json, true );
				$labels = array(); $submission_fields = array();
				foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) foreach ( (array) ( $section['fields'] ?? array() ) as $field ) { $labels[ $field['key'] ] = $field['label']; $submission_fields[ $field['key'] ] = $field; }
				ob_start();
				foreach ( (array) $answers as $key=>$value ) {
					if ( 0 === strpos( (string) $key, '_ggm_' ) ) { continue; }
					$field = $submission_fields[ $key ] ?? array();
					echo '<div class="ggm-submission-answer"><strong>' . esc_html( $labels[$key] ?? $key ) . ':</strong> ';
					if ( 'chip_selector' === ( $field['type'] ?? '' ) ) {
						foreach ( (array) $value as $chip ) { echo '<span class="ggm-answer-chip">' . esc_html( $chip ) . '</span> '; }
					} else {
						$formatted = $field ? GGM_Form_Builder::format_answer( $field, $value ) : ( is_array( $value ) ? wp_json_encode( $value ) : $value );
						echo 'checkboxes' === ( $field['type'] ?? '' ) ? wp_kses_post( $formatted ) : esc_html( $formatted );
					}
					echo '</div>';
				}
				$additional_history = isset( $answers['_ggm_additional_history'] ) && is_array( $answers['_ggm_additional_history'] ) ? $answers['_ggm_additional_history'] : array();
				if ( $additional_history ) {
					echo '<details class="ggm-submission-answer"><summary><strong>' . esc_html__( 'Additional submission history', 'ggm-member-dashboard' ) . '</strong></summary>';
					foreach ( $additional_history as $history_entry ) {
						$saved_at = sanitize_text_field( $history_entry['saved_at'] ?? '' );
						echo '<div class="ggm-additional-history-entry"><small>' . esc_html( $saved_at ) . '</small>';
						foreach ( (array) ( $history_entry['answers'] ?? array() ) as $history_key=>$history_value ) {
							$field = $submission_fields[ $history_key ] ?? array();
							$formatted = $field ? GGM_Form_Builder::format_answer( $field, $history_value ) : ( is_array( $history_value ) ? wp_json_encode( $history_value ) : $history_value );
							echo '<div><strong>' . esc_html( $labels[ $history_key ] ?? $history_key ) . ':</strong> ' . esc_html( $formatted ) . '</div>';
						}
						echo '</div>';
					}
					echo '</details>';
				}
				$answers_html = ob_get_clean();
				$submission_files = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ggm_form_files WHERE submission_id=%d", $row->id ) );
				ob_start();
				if ( $submission_files ) {
					foreach ( $submission_files as $file ) {
						$preview_url=wp_nonce_url(admin_url('admin-post.php?action=ggm_form_file&mode=preview&file_id='.$file->id),'ggm_form_file_'.$file->id.'_preview');
						$download_url=wp_nonce_url(admin_url('admin-post.php?action=ggm_form_file&mode=download&file_id='.$file->id),'ggm_form_file_'.$file->id.'_download');
						?><div><strong><?php echo esc_html($labels[$file->field_key]??__('File','ggm-member-dashboard')); ?>:</strong> <?php echo esc_html($file->original_name); ?><br><a class="button button-small" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($preview_url); ?>"><?php esc_html_e('Preview','ggm-member-dashboard'); ?></a> <a class="button button-small" href="<?php echo esc_url($download_url); ?>"><?php esc_html_e('Download','ggm-member-dashboard'); ?></a></div><?php
					}
				} else {
					echo '—';
				}
				$files_html = ob_get_clean();
				$delete_url = wp_nonce_url(
					add_query_arg(
						array_filter(
							array(
								'action'           => 'ggm_delete_form_submission',
								'submission_id'    => (int) $row->id,
								'filter_form_id'   => $selected_form_id ?: null,
								'submission_search'=> '' !== $submission_search ? $submission_search : null,
								'submissions_page' => $submissions_page,
							),
							static function( $value ) { return null !== $value && '' !== $value; }
						),
						admin_url( 'admin-post.php' )
					),
					'ggm_delete_form_submission_' . (int) $row->id
				);
				$review_mode = empty( $row->reviewed_at ) ? 'review' : 'reopen';
				$review_url = wp_nonce_url( admin_url( 'admin-post.php?action=ggm_review_form_submission&submission_id=' . (int) $row->id . '&mode=' . $review_mode ), 'ggm_review_form_submission_' . (int) $row->id . '_' . $review_mode );
				$submission_rows[] = array(
					'user_id'   => (int) $row->user_id,
					'form'      => esc_html( $row->title ?: '#' . $row->form_id ),
					'member'    => esc_html( $row->user_id ? trim( $row->display_name . ' (' . $row->user_email . ')' ) : __( 'Guest', 'ggm-member-dashboard' ) ),
					'placement' => esc_html( ucfirst( $row->context_type ) . ( $row->context_id ? ': ' . ( get_the_title( $row->context_id ) ?: '#' . $row->context_id ) : '' ) ),
					'answers'   => $answers_html ?: '—',
					'files'     => $files_html,
					'submitted' => (string) $row->submitted_at,
					'sort'      => strtotime( (string) $row->submitted_at ) ?: 0,
					'delete'    => $delete_url,
					'confirm'   => __( 'Delete this response permanently? Uploaded files and payment record for this response will also be removed.', 'ggm-member-dashboard' ),
					'review'    => $review_url,
					'reviewed'  => empty( $row->reviewed_at ) ? '' : sprintf( __( 'Reviewed %s', 'ggm-member-dashboard' ), $row->reviewed_at ),
					'search'    => implode( ' ', array( $row->title ?: '#' . $row->form_id, $row->display_name, $row->user_email, ucfirst( $row->context_type ), wp_strip_all_tags( $answers_html ), $row->submitted_at ) ),
				);
			}
		}
		if ( '' !== $submission_search ) {
			$needle = strtolower( $submission_search );
			$submission_rows = array_values( array_filter( $submission_rows, static function( $row ) use ( $needle ) {
				return false !== strpos( strtolower( wp_strip_all_tags( (string) ( $row['search'] ?? '' ) ) ), $needle );
			} ) );
		}
		usort( $submission_rows, static function( $a, $b ) { return (int) $b['sort'] <=> (int) $a['sort']; } );
		$total_new_rows   = count( $submission_rows );
		$total_pages      = max( 1, (int) ceil( $total_new_rows / $submissions_limit ) );
		$submissions_page = min( $submissions_page, $total_pages );
		$offset           = ( $submissions_page - 1 ) * $submissions_limit;
		$visible_rows     = array_slice( $submission_rows, $offset, $submissions_limit );
		$pagination_base  = add_query_arg(
			array_filter(
				array(
					'page'             => 'ggm-health-intakes',
					'tab'              => 'submissions',
					'form_id'          => '0' !== $selected_form_key ? $selected_form_key : null,
					'submission_search'=> '' !== $submission_search ? $submission_search : null,
					'submissions_page' => '%#%',
				),
				static function( $value ) { return null !== $value && '' !== $value; }
			),
			admin_url( 'admin.php' )
		);
		?>
		<h2><?php esc_html_e( 'Submitted Forms', 'ggm-member-dashboard' ); ?></h2>
		<?php if ( isset( $_GET['submission_deleted'] ) ) : ?>
			<?php if ( '1' === sanitize_key( wp_unslash( $_GET['submission_deleted'] ) ) ) : ?><div class="notice notice-success inline"><p><?php esc_html_e( 'Response deleted.', 'ggm-member-dashboard' ); ?></p></div>
			<?php elseif ( 'missing' === sanitize_key( wp_unslash( $_GET['submission_deleted'] ) ) ) : ?><div class="notice notice-warning inline"><p><?php esc_html_e( 'That response was not found or was already deleted.', 'ggm-member-dashboard' ); ?></p></div>
			<?php else : ?><div class="notice notice-error inline"><p><?php esc_html_e( 'The response could not be deleted. Please try again.', 'ggm-member-dashboard' ); ?></p></div><?php endif; ?>
		<?php endif; ?>
		<p><?php esc_html_e( 'Legacy health intake responses and custom form builder responses are listed together here.', 'ggm-member-dashboard' ); ?></p>
		<form class="ggm-submissions-filter" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="ggm-health-intakes">
			<input type="hidden" name="tab" value="submissions">
			<label for="ggm-submissions-form-filter"><?php esc_html_e( 'Filter by form', 'ggm-member-dashboard' ); ?></label>
			<select id="ggm-submissions-form-filter" name="form_id">
				<option value="0" <?php selected( $selected_form_key, '0' ); ?>><?php esc_html_e( 'All responses', 'ggm-member-dashboard' ); ?></option>
				<option value="legacy" <?php selected( $selected_form_key, 'legacy' ); ?>><?php esc_html_e( 'Legacy Health Intake', 'ggm-member-dashboard' ); ?></option>
				<?php foreach ( $submission_forms as $submission_form ) : ?><option value="<?php echo esc_attr( $submission_form->id ); ?>" <?php selected( $selected_form_id, (int) $submission_form->id ); ?>><?php echo esc_html( $submission_form->title ?: '#' . $submission_form->id ); ?></option><?php endforeach; ?>
			</select>
			<label for="ggm-submissions-search"><?php esc_html_e( 'Search responses', 'ggm-member-dashboard' ); ?></label>
			<input id="ggm-submissions-search" type="search" name="submission_search" value="<?php echo esc_attr( $submission_search ); ?>" placeholder="<?php esc_attr_e( 'Member, email, answer...', 'ggm-member-dashboard' ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Apply Filter', 'ggm-member-dashboard' ); ?></button>
			<?php if ( '0' !== $selected_form_key || '' !== $submission_search ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page'=>'ggm-health-intakes', 'tab'=>'submissions' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Reset', 'ggm-member-dashboard' ); ?></a><?php endif; ?>
			<span class="ggm-submissions-count"><?php echo esc_html( sprintf( _n( '%d response', '%d responses', $total_new_rows, 'ggm-member-dashboard' ), $total_new_rows ) ); ?></span>
		</form>
		<div class="ggm-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Form', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Member', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Placement', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Answers', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Uploaded Files', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Submitted', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Actions', 'ggm-member-dashboard' ); ?></th></tr></thead><tbody>
		<?php foreach ( $visible_rows as $row ) : ?>
			<tr>
				<td><?php echo wp_kses_post( $row['form'] ); ?></td>
				<td><?php echo wp_kses_post( $row['member'] ); ?></td>
				<td><?php echo wp_kses_post( $row['placement'] ); ?></td>
				<td><?php echo wp_kses_post( $row['answers'] ); ?></td>
				<td><?php echo wp_kses_post( $row['files'] ); ?></td>
				<td><?php echo esc_html( $row['submitted'] ?: '—' ); ?></td>
				<td>
					<?php if ( ! empty( $row['review'] ) ) : ?><div style="margin-bottom:6px"><strong><?php echo esc_html( $row['reviewed'] ?: __( 'Awaiting review', 'ggm-member-dashboard' ) ); ?></strong><br><a class="button button-small" href="<?php echo esc_url( $row['review'] ); ?>"><?php echo esc_html( $row['reviewed'] ? __( 'Reopen for Editing', 'ggm-member-dashboard' ) : __( 'Mark Reviewed', 'ggm-member-dashboard' ) ); ?></a></div><?php endif; ?>
					<a class="button button-small ggm-delete-response-link" href="<?php echo esc_url( $row['delete'] ); ?>" onclick="return window.confirm('<?php echo esc_js( $row['confirm'] ); ?>');"><?php esc_html_e( 'Delete', 'ggm-member-dashboard' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $visible_rows ) : ?><tr><td colspan="7"><?php esc_html_e( 'No form responses found.', 'ggm-member-dashboard' ); ?></td></tr><?php endif; ?>
		</tbody></table></div>
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom ggm-submissions-pagination"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base'=>$pagination_base, 'format'=>'', 'current'=>$submissions_page, 'total'=>$total_pages, 'prev_text'=>__( '&laquo; Previous', 'ggm-member-dashboard' ), 'next_text'=>__( 'Next &raquo;', 'ggm-member-dashboard' ) ) ) ); ?></div></div>
		<?php endif; ?>
		<style>.ggm-submissions-filter{display:flex;align-items:center;gap:10px;margin:12px 0 16px;flex-wrap:wrap}.ggm-submissions-filter label{font-weight:600}.ggm-submissions-filter select{min-width:260px}.ggm-submissions-filter input[type=search]{min-width:240px}.ggm-submissions-count{color:#64748b}.ggm-delete-response-link{color:#b42318!important;border-color:#f3b6b0!important}.ggm-submissions-pagination{margin-top:12px}</style>
	<?php elseif ( 'myforms' === $tab ) : ?>
		<?php $forms = GGM_Form_Builder::get_forms(); ?>
		<section class="ggm-admin-card ggm-form-list">
			<div class="ggm-card-heading"><h2><?php esc_html_e( 'My Forms', 'ggm-member-dashboard' ); ?></h2><div class="ggm-form-toolbar"><label><span class="screen-reader-text"><?php esc_html_e( 'Search forms', 'ggm-member-dashboard' ); ?></span><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" id="ggm-form-search" placeholder="<?php esc_attr_e( 'Search forms…', 'ggm-member-dashboard' ); ?>"></label><a class="button button-primary ggm-discard-builder-link" href="<?php echo esc_url( add_query_arg( array('page'=>'ggm-health-intakes','tab'=>'builder'), admin_url('admin.php') ) ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Create New Form', 'ggm-member-dashboard' ); ?></a></div></div>
			<div class="ggm-table-wrap"><table class="widefat" id="ggm-forms-table"><thead><tr><th><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Updated', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Actions', 'ggm-member-dashboard' ); ?></th></tr></thead><tbody>
			<?php foreach($forms as $saved): ?><tr id="ggm-form-row-<?php echo esc_attr($saved->id); ?>"><td><strong><?php echo esc_html($saved->title); ?></strong></td><td><span class="ggm-status-badge is-<?php echo esc_attr($saved->status); ?>"><?php echo esc_html(ucfirst($saved->status)); ?></span></td><td><?php echo esc_html(mysql2date(get_option('date_format'),$saved->updated_at)); ?></td><td><a href="<?php echo esc_url(add_query_arg(array('page'=>'ggm-health-intakes','tab'=>'builder','edit_form'=>$saved->id),admin_url('admin.php'))); ?>">Edit</a> <span class="ggm-action-separator">·</span> <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ggm_preview_form&form_id='.$saved->id),'ggm_preview_form_'.$saved->id)); ?>">Preview</a><?php if('archived'!==$saved->status): ?> <span class="ggm-action-separator">·</span> <a class="ggm-archive-link" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ggm_archive_form&form_id='.$saved->id),'ggm_archive_form_'.$saved->id)); ?>">Archive</a><?php endif; ?> <span class="ggm-action-separator">·</span> <a href="#" class="ggm-archive-link ggm-delete-form-link" data-id="<?php echo esc_attr($saved->id); ?>" data-title="<?php echo esc_attr($saved->title ?: ('#' . $saved->id)); ?>">Delete</a></td></tr><?php endforeach; ?>
			<?php if(!$forms): ?><tr class="ggm-empty-row"><td colspan="4"><span class="dashicons dashicons-clipboard"></span><strong><?php esc_html_e('No forms yet','ggm-member-dashboard'); ?></strong><small><?php esc_html_e('Create your first health intake form to get started.','ggm-member-dashboard'); ?></small></td></tr><?php endif; ?>
			</tbody></table></div>
		</section>
		<div id="ggm-delete-form-modal" class="ggm-delete-form-modal" hidden>
			<div class="ggm-delete-form-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ggm-delete-form-modal-title">
				<h2 id="ggm-delete-form-modal-title"><?php esc_html_e( 'Delete this form?', 'ggm-member-dashboard' ); ?></h2>
				<p><?php esc_html_e( 'This permanently deletes', 'ggm-member-dashboard' ); ?> <strong id="ggm-delete-form-modal-name"></strong> <?php esc_html_e( 'and every response, uploaded file, version history, and placement for it.', 'ggm-member-dashboard' ); ?> <strong><?php esc_html_e( 'This cannot be undone.', 'ggm-member-dashboard' ); ?></strong></p>
				<p><label for="ggm-delete-form-modal-input"><?php esc_html_e( 'Type the form title below to confirm:', 'ggm-member-dashboard' ); ?></label></p>
				<p class="ggm-delete-form-modal-target" id="ggm-delete-form-modal-target-title"></p>
				<input type="text" id="ggm-delete-form-modal-input" autocomplete="off" autocapitalize="off" spellcheck="false">
				<p id="ggm-delete-form-modal-error" class="ggm-delete-form-modal-error" role="alert" hidden></p>
				<div class="ggm-delete-form-modal-actions">
					<button type="button" class="button" id="ggm-delete-form-modal-cancel"><?php esc_html_e( 'Cancel', 'ggm-member-dashboard' ); ?></button>
					<button type="button" class="button button-primary ggm-delete-form-modal-confirm" id="ggm-delete-form-modal-confirm" disabled><?php esc_html_e( 'Delete permanently', 'ggm-member-dashboard' ); ?></button>
				</div>
			</div>
		</div>
		<script>
		(function(){
			var search=document.getElementById('ggm-form-search');
			if(search)search.addEventListener('input',function(){document.querySelectorAll('#ggm-forms-table tbody tr:not(.ggm-empty-row)').forEach(function(row){row.hidden=row.textContent.toLowerCase().indexOf(search.value.toLowerCase())===-1})});
			var modal=document.getElementById('ggm-delete-form-modal');
			if(!modal)return;
			var ajaxUrl=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce=<?php echo wp_json_encode( wp_create_nonce( 'ggm_admin_nonce' ) ); ?>;
			var nameEl=document.getElementById('ggm-delete-form-modal-name');
			var targetTitleEl=document.getElementById('ggm-delete-form-modal-target-title');
			var input=document.getElementById('ggm-delete-form-modal-input');
			var confirmBtn=document.getElementById('ggm-delete-form-modal-confirm');
			var cancelBtn=document.getElementById('ggm-delete-form-modal-cancel');
			var errorEl=document.getElementById('ggm-delete-form-modal-error');
			var confirmIdleLabel=confirmBtn.textContent;
			var currentId=0,currentTitle='';

			function openModal(id,title){
				currentId=id;currentTitle=title;
				nameEl.textContent=title;
				targetTitleEl.textContent=title;
				input.value='';
				errorEl.hidden=true;
				confirmBtn.disabled=true;
				modal.hidden=false;
				input.focus();
			}
			function closeModal(){
				modal.hidden=true;
			}

			document.querySelectorAll('.ggm-delete-form-link').forEach(function(link){
				link.addEventListener('click',function(e){
					e.preventDefault();
					openModal(link.dataset.id,link.dataset.title);
				});
			});

			input.addEventListener('input',function(){
				confirmBtn.disabled=input.value.trim()!==currentTitle.trim();
			});
			cancelBtn.addEventListener('click',closeModal);
			modal.addEventListener('mousedown',function(e){if(e.target===modal)closeModal()});
			document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)closeModal()});

			confirmBtn.addEventListener('click',function(){
				if(confirmBtn.disabled)return;
				confirmBtn.disabled=true;
				confirmBtn.textContent=<?php echo wp_json_encode( __( 'Deleting…', 'ggm-member-dashboard' ) ); ?>;
				errorEl.hidden=true;
				var params=new URLSearchParams({action:'ggm_delete_form',nonce:nonce,form_id:currentId,confirm_title:input.value});
				fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()})
					.then(function(r){return r.json()})
					.then(function(res){
						if(res&&res.success){
							var row=document.getElementById('ggm-form-row-'+currentId);
							closeModal();
							if(row)row.remove();
						}else{
							errorEl.textContent=(res&&res.data&&res.data.message)||<?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'ggm-member-dashboard' ) ); ?>;
							errorEl.hidden=false;
							confirmBtn.disabled=false;
							confirmBtn.textContent=confirmIdleLabel;
						}
					})
					.catch(function(){
						errorEl.textContent=<?php echo wp_json_encode( __( 'Request failed. Please try again.', 'ggm-member-dashboard' ) ); ?>;
						errorEl.hidden=false;
						confirmBtn.disabled=false;
						confirmBtn.textContent=confirmIdleLabel;
					});
			});
		})();
		</script>
	<?php else : ?>
		<?php
		$edit_id = absint( $_GET['edit_form'] ?? 0 );
		$form = $edit_id ? GGM_Form_Builder::get_form( $edit_id ) : null;
		$starter_template = GGM_Form_Builder::workshop_health_template();
		$starter_key = 'blank' === sanitize_key( wp_unslash( $_GET['starter'] ?? '' ) ) ? 'blank' : 'workshop_health';
		$uses_workshop_starter = ! $form && 'workshop_health' === $starter_key;
		$builder_error = 'invalid_form' === sanitize_key( wp_unslash( $_GET['error'] ?? '' ) ) ? __( 'The form could not be saved. Add a title and at least one field, section title, or section description.', 'ggm-member-dashboard' ) : '';
		$schema = $form
			? GGM_Form_Builder::get_schema( $form )
			: ( $uses_workshop_starter ? $starter_template['schema'] : array( 'sections'=>array( array( 'key'=>wp_generate_uuid4(), 'title'=>'', 'description'=>'', 'fields'=>array() ) ) ) );
		$settings = $form ? json_decode( $form->settings_json, true ) : ( $uses_workshop_starter ? $starter_template['settings'] : array() );
		$settings = is_array( $settings ) ? $settings : array();
		$form_title_value = $form ? $form->title : ( $uses_workshop_starter ? $starter_template['title'] : '' );
		$form_description_value = $form ? ( $settings['description'] ?? '' ) : ( $uses_workshop_starter ? $starter_template['description'] : '' );
		$assignments = $form ? GGM_Form_Builder::get_assignments( $form->id ) : array();
		$locations = $form ? wp_list_pluck( $assignments, 'location_type' ) : ( $uses_workshop_starter ? array( 'dashboard' ) : array() );
		$workshop_ids = array_map( 'intval', wp_list_pluck( array_filter( $assignments, static function($a){return 'workshop_specific'===$a->location_type;} ), 'object_id' ) );
		$course_ids = array_map( 'intval', wp_list_pluck( array_filter( $assignments, static function($a){return 'course_specific'===$a->location_type;} ), 'object_id' ) );
		$workshops = get_posts( array( 'post_type'=>array('workshop','ggm_workshop'), 'post_status'=>array('publish','draft'), 'posts_per_page'=>-1, 'orderby'=>'title','order'=>'ASC' ) );
		$courses = get_posts( array( 'post_type'=>'course', 'post_status'=>array('publish','draft'), 'posts_per_page'=>-1, 'orderby'=>'title','order'=>'ASC' ) );
		$rich_editor_settings = array(
			'textarea_rows' => 6,
			'media_buttons' => false,
			'teeny'         => false,
			'quicktags'     => true,
			'tinymce'       => array(
				'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo',
				'toolbar2' => '',
			),
		);
		?>
		<section class="ggm-admin-card ggm-builder-card">
		<h2><?php echo $form ? esc_html__( 'Edit Form', 'ggm-member-dashboard' ) : esc_html__( 'Create New Form', 'ggm-member-dashboard' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="ggm-builder-form">
			<input type="hidden" name="action" value="ggm_save_form"><input type="hidden" name="form_id" value="<?php echo esc_attr($edit_id); ?>"><?php wp_nonce_field('ggm_save_form'); ?>
			<input type="hidden" name="schema_json" id="ggm-schema-json">
			<div id="ggm-builder-error" class="notice notice-error inline ggm-builder-error" role="alert" tabindex="-1" <?php echo $builder_error ? '' : 'hidden'; ?>><?php echo esc_html( $builder_error ); ?></div>
			<div class="ggm-builder-layout">
				<div class="ggm-builder-nav" role="tablist" aria-label="<?php esc_attr_e( 'Form builder steps', 'ggm-member-dashboard' ); ?>" aria-orientation="vertical">
					<button type="button" class="is-active" id="ggm-builder-tab-general" role="tab" aria-selected="true" aria-controls="ggm-general" tabindex="0"><?php esc_html_e( 'General', 'ggm-member-dashboard' ); ?></button>
					<button type="button" id="ggm-builder-tab-fields" role="tab" aria-selected="false" aria-controls="ggm-fields" tabindex="-1"><?php esc_html_e( 'Fields & Sections', 'ggm-member-dashboard' ); ?></button>
					<button type="button" id="ggm-builder-tab-settings" role="tab" aria-selected="false" aria-controls="ggm-settings" tabindex="-1"><?php esc_html_e( 'Settings', 'ggm-member-dashboard' ); ?></button>
					<button type="button" id="ggm-builder-tab-positioning" role="tab" aria-selected="false" aria-controls="ggm-positioning" tabindex="-1"><?php esc_html_e( 'Positioning', 'ggm-member-dashboard' ); ?></button>
					<button type="button" id="ggm-builder-tab-review" role="tab" aria-selected="false" aria-controls="ggm-review" tabindex="-1"><?php esc_html_e( 'Review', 'ggm-member-dashboard' ); ?></button>
				</div>
				<div class="ggm-builder-content">
					<section id="ggm-general" class="ggm-builder-panel ggm-builder-tab-panel is-active" role="tabpanel" aria-labelledby="ggm-builder-tab-general" tabindex="0">
						<div class="ggm-panel-title"><h3><?php esc_html_e( 'General Information', 'ggm-member-dashboard' ); ?></h3><p><?php esc_html_e( 'Configure the basic details and behavior of your form.', 'ggm-member-dashboard' ); ?></p></div>
						<?php if ( ! $form ) : ?>
							<div class="ggm-template-callout ggm-span-2">
								<span class="dashicons dashicons-clipboard"></span>
				<div><strong><?php echo $uses_workshop_starter ? esc_html__( 'Workshop health form starter loaded', 'ggm-member-dashboard' ) : esc_html__( 'Blank form loaded', 'ggm-member-dashboard' ); ?></strong><p><?php echo $uses_workshop_starter ? esc_html__( 'This editable starter copies the 16 questions from the former workshop dashboard health intake. Member Dashboard placement is preselected; save it as Published when it is ready for members.', 'ggm-member-dashboard' ) : esc_html__( 'Build your own form from an empty section, or load the workshop health form starter.', 'ggm-member-dashboard' ); ?></p></div>
								<a class="button ggm-discard-builder-link" href="<?php echo esc_url( add_query_arg( array( 'page'=>'ggm-health-intakes', 'tab'=>'builder', 'starter'=>$uses_workshop_starter ? 'blank' : 'workshop_health' ), admin_url( 'admin.php' ) ) ); ?>"><?php echo $uses_workshop_starter ? esc_html__( 'Start blank instead', 'ggm-member-dashboard' ) : esc_html__( 'Load workshop starter', 'ggm-member-dashboard' ); ?></a>
							</div>
						<?php endif; ?>
						<div class="ggm-input-group ggm-span-2"><label for="form_title"><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?></label><input type="text" required id="form_title" name="form_title" value="<?php echo esc_attr( $form_title_value ); ?>" placeholder="<?php esc_attr_e( 'Enter form title', 'ggm-member-dashboard' ); ?>"><div class="ggm-inline-inputs"><label><?php esc_html_e( 'HTML type', 'ggm-member-dashboard' ); ?><select name="form_heading_tag"><?php foreach ( array( 'h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','p'=>__( 'Paragraph', 'ggm-member-dashboard' ) ) as $tag=>$label ) : ?><option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $settings['form_heading_tag'] ?? 'h2', $tag ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><label><?php esc_html_e( 'Text size (px)', 'ggm-member-dashboard' ); ?><input type="number" name="form_heading_size" min="10" max="96" step="1" value="<?php echo esc_attr( $settings['form_heading_size'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Theme default', 'ggm-member-dashboard' ); ?>"></label></div><small><?php esc_html_e( 'Choose the semantic HTML element and optionally override its display size.', 'ggm-member-dashboard' ); ?></small></div>
						<div class="ggm-input-group ggm-span-2"><label for="form_description"><?php esc_html_e( 'Description', 'ggm-member-dashboard' ); ?></label><textarea id="form_description" name="form_description" placeholder="<?php esc_attr_e( 'Enter form description (optional)', 'ggm-member-dashboard' ); ?>"><?php echo esc_textarea( $form_description_value ); ?></textarea></div>
						<div class="ggm-input-group"><label for="form_status"><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></label><select id="form_status" name="form_status"><option value="draft" <?php selected($form->status??'draft','draft'); ?>><?php esc_html_e( 'Draft', 'ggm-member-dashboard' ); ?></option><option value="published" <?php selected($form->status??'','published'); ?>><?php esc_html_e( 'Published', 'ggm-member-dashboard' ); ?></option></select></div>
						<div class="ggm-input-group"><label for="form_progress"><?php esc_html_e( 'Progress Indicator', 'ggm-member-dashboard' ); ?></label><select id="form_progress" name="progress"><?php foreach(array('none'=>__('None','ggm-member-dashboard'),'pages'=>__('Page number','ggm-member-dashboard'),'bar'=>__('Progress bar','ggm-member-dashboard')) as $v=>$l): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($settings['progress']??'bar',$v); ?>><?php echo esc_html($l); ?></option><?php endforeach; ?></select></div>
					</section>

					<section id="ggm-fields" class="ggm-builder-panel ggm-builder-tab-panel ggm-fields-panel" role="tabpanel" aria-labelledby="ggm-builder-tab-fields" tabindex="0" hidden>
						<div class="ggm-panel-title"><h3><?php esc_html_e( 'Fields & Sections', 'ggm-member-dashboard' ); ?></h3><p><?php esc_html_e( 'Add fields to collect information. Use the controls to reorder.', 'ggm-member-dashboard' ); ?></p></div>
						<div class="ggm-fields-workspace">
							<aside class="ggm-fields-sidebar" aria-label="<?php esc_attr_e( 'Field builder tools', 'ggm-member-dashboard' ); ?>">
								<div class="ggm-fields-sidebar-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Field builder tools', 'ggm-member-dashboard' ); ?>">
									<button type="button" class="is-active" role="tab" aria-selected="true" data-fields-sidebar-tab="palette" aria-controls="ggm-field-palette-panel"><?php esc_html_e( 'Fields', 'ggm-member-dashboard' ); ?></button>
									<button type="button" role="tab" aria-selected="false" data-fields-sidebar-tab="steps" aria-controls="ggm-section-steps-panel"><?php esc_html_e( 'Steps', 'ggm-member-dashboard' ); ?></button>
								</div>
								<div id="ggm-field-palette-panel" class="ggm-fields-sidebar-panel" role="tabpanel">
									<div class="ggm-field-palette"><?php $icons=array('short_answer'=>'editor-alignleft','paragraph'=>'text-page','multiple_choice'=>'marker','checkboxes'=>'yes-alt','dropdown'=>'arrow-down-alt2','file_upload'=>'cloud-upload','linear_scale'=>'chart-line','multiple_choice_grid'=>'grid-view','checkbox_grid'=>'screenoptions','date'=>'calendar-alt','time'=>'clock','chip_selector'=>'tag','search_select'=>'search'); foreach(array('short_answer'=>'Short answer','paragraph'=>'Paragraph','multiple_choice'=>'Multiple choice','checkboxes'=>'Checkboxes','dropdown'=>'Dropdown','file_upload'=>'File upload','linear_scale'=>'Linear scale','multiple_choice_grid'=>'Multiple-choice grid','checkbox_grid'=>'Checkbox grid','date'=>'Date','time'=>'Time','chip_selector'=>'Chip Selector','search_select'=>'Search select') as $type=>$label): ?><button type="button" class="button ggm-add-field" data-type="<?php echo esc_attr($type); ?>"><span class="dashicons dashicons-<?php echo esc_attr($icons[$type]); ?>"></span><?php echo esc_html($label); ?></button><?php endforeach; ?></div>
									<button type="button" class="button ggm-add-section-button" id="ggm-add-section"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Section / Page', 'ggm-member-dashboard' ); ?></button>
								</div>
								<div id="ggm-section-steps-panel" class="ggm-fields-sidebar-panel" role="tabpanel" hidden>
									<div id="ggm-section-tabs" class="ggm-section-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Form sections', 'ggm-member-dashboard' ); ?>"></div>
								</div>
							</aside>
							<div class="ggm-section-editor">
								<div id="ggm-section-status" class="ggm-section-status" aria-live="polite"></div>
								<div id="ggm-sections"></div>
							</div>
						</div>
					</section>

					<section id="ggm-settings" class="ggm-builder-panel ggm-builder-tab-panel" role="tabpanel" aria-labelledby="ggm-builder-tab-settings" tabindex="0" hidden>
						<div class="ggm-panel-title"><h3><?php esc_html_e( 'Form Settings', 'ggm-member-dashboard' ); ?></h3><p><?php esc_html_e( 'Customize navigation, confirmations, and response behavior.', 'ggm-member-dashboard' ); ?></p></div>
						<div class="ggm-input-group ggm-span-2" role="group" aria-labelledby="ggm-navigation-buttons-label"><span class="ggm-input-label" id="ggm-navigation-buttons-label"><?php esc_html_e( 'Navigation Buttons', 'ggm-member-dashboard' ); ?></span><div class="ggm-inline-inputs"><label><span class="screen-reader-text"><?php esc_html_e( 'Back button text', 'ggm-member-dashboard' ); ?></span><input type="text" name="back_label" value="<?php echo esc_attr($settings['back_label']??'Back'); ?>" placeholder="← Back"></label><label><span class="screen-reader-text"><?php esc_html_e( 'Next button text', 'ggm-member-dashboard' ); ?></span><input type="text" name="next_label" value="<?php echo esc_attr($settings['next_label']??'Next'); ?>" placeholder="Next →"></label><label><span class="screen-reader-text"><?php esc_html_e( 'Submit button text', 'ggm-member-dashboard' ); ?></span><input type="text" name="submit_label" value="<?php echo esc_attr($settings['submit_label']??'Submit'); ?>" placeholder="Submit"></label></div><small><?php esc_html_e( 'Customize the navigation button text.', 'ggm-member-dashboard' ); ?></small></div>
						<div class="ggm-input-group ggm-span-2 ggm-rich-message-editor"><label for="form_confirmation"><?php esc_html_e( 'Confirmation Message', 'ggm-member-dashboard' ); ?></label><?php wp_editor( $settings['confirmation'] ?? 'Thank you. Your response has been submitted.', 'form_confirmation', array_merge( $rich_editor_settings, array( 'textarea_name' => 'confirmation' ) ) ); ?></div>
						<div class="ggm-input-group ggm-span-2"><label for="form_whatsapp_group_url"><?php esc_html_e( 'WhatsApp Group Link', 'ggm-member-dashboard' ); ?></label><input type="url" id="form_whatsapp_group_url" name="whatsapp_group_url" value="<?php echo esc_attr($settings['whatsapp_group_url']??''); ?>" placeholder="https://chat.whatsapp.com/…"><small><?php esc_html_e( 'Optional. When set, a "Join WhatsApp Group" button is shown after a member submits this form.', 'ggm-member-dashboard' ); ?></small></div>
						<div class="ggm-input-group ggm-span-2"><span class="ggm-input-label"><?php esc_html_e( 'Responses', 'ggm-member-dashboard' ); ?></span><label class="ggm-checkbox-label"><input type="checkbox" name="allow_multiple" value="1" <?php checked(!empty($settings['allow_multiple'])); ?>> <span><?php esc_html_e( 'Allow multiple submissions per member and context', 'ggm-member-dashboard' ); ?><small><?php esc_html_e( 'If enabled, logged-in members can submit this form multiple times.', 'ggm-member-dashboard' ); ?></small></span></label><label class="ggm-checkbox-label"><input type="checkbox" name="allow_guests" value="1" <?php checked(!empty($settings['allow_guests'])); ?>> <span><?php esc_html_e( 'Allow logged-out users to submit this form', 'ggm-member-dashboard' ); ?><small><?php esc_html_e( 'Guest responses are saved as Guest. Single-submission limits only apply to logged-in members.', 'ggm-member-dashboard' ); ?></small></span></label></div>
						<div class="ggm-input-group ggm-span-2"><label class="ggm-checkbox-label"><input type="checkbox" name="allow_additional_resubmission" value="1" <?php checked(!empty($settings['allow_additional_resubmission'])); ?>> <span><?php esc_html_e( 'Allow additional details to be submitted or updated more than once', 'ggm-member-dashboard' ); ?><small><?php esc_html_e( 'Keep the additional-details action available after a successful save.', 'ggm-member-dashboard' ); ?></small></span></label></div>
						<div class="ggm-input-group ggm-span-2"><label><?php esc_html_e( 'Payment flow', 'ggm-member-dashboard' ); ?><select name="payment_flow"><option value="submit" <?php selected($settings['payment_flow']??'submit','submit'); ?>><?php esc_html_e( 'Pay when submitting form', 'ggm-member-dashboard' ); ?></option><option value="before_form" <?php selected($settings['payment_flow']??'submit','before_form'); ?>><?php esc_html_e( 'Pay before showing form fields', 'ggm-member-dashboard' ); ?></option></select><small><?php esc_html_e( 'Existing paid forms keep their current behavior unless switched here.', 'ggm-member-dashboard' ); ?></small></label></div>
						<div class="ggm-input-group ggm-span-2 ggm-payment-first-copy"><span class="ggm-input-label"><?php esc_html_e( 'Payment-first screen text', 'ggm-member-dashboard' ); ?></span><label><?php esc_html_e( 'Heading', 'ggm-member-dashboard' ); ?><input type="text" name="payment_first_heading" value="<?php echo esc_attr($settings['payment_first_heading']??'Complete payment to continue'); ?>"></label><div class="ggm-inline-inputs"><label><?php esc_html_e( 'HTML type', 'ggm-member-dashboard' ); ?><select name="payment_first_heading_tag"><?php foreach ( array( 'h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','p'=>__( 'Paragraph', 'ggm-member-dashboard' ) ) as $tag=>$label ) : ?><option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $settings['payment_first_heading_tag'] ?? 'h3', $tag ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><label><?php esc_html_e( 'Text size (px)', 'ggm-member-dashboard' ); ?><input type="number" name="payment_first_heading_size" min="10" max="96" step="1" value="<?php echo esc_attr( $settings['payment_first_heading_size'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Theme default', 'ggm-member-dashboard' ); ?>"></label></div><label><?php esc_html_e( 'Description', 'ggm-member-dashboard' ); ?><textarea name="payment_first_text" rows="3"><?php echo esc_textarea($settings['payment_first_text']??'After payment is verified, the form fields will be available immediately.'); ?></textarea></label><small><?php esc_html_e( 'Shown before the fields when Pay before showing form fields is selected. The payment button text is controlled by Button label below.', 'ggm-member-dashboard' ); ?></small></div>
						<div class="ggm-input-group ggm-span-2 ggm-payment-addon-settings"><span class="ggm-input-label"><?php esc_html_e( 'Payment Button Add-on', 'ggm-member-dashboard' ); ?></span><label class="ggm-checkbox-label"><input type="checkbox" name="payment_enabled" value="1" <?php checked(!empty($settings['payment_enabled'])); ?>> <span><?php esc_html_e( 'Replace the final submit button with a paid button', 'ggm-member-dashboard' ); ?><small><?php esc_html_e( 'When enabled, members must pay first; the form response is submitted after successful payment.', 'ggm-member-dashboard' ); ?></small></span></label><div class="ggm-inline-inputs"><label><?php esc_html_e( 'Button label', 'ggm-member-dashboard' ); ?><input type="text" name="payment_label" value="<?php echo esc_attr($settings['payment_label']??'Pay & Submit'); ?>" placeholder="<?php esc_attr_e( 'Pay & Submit', 'ggm-member-dashboard' ); ?>"></label><label><?php esc_html_e( 'Amount', 'ggm-member-dashboard' ); ?><input type="number" name="payment_amount" min="0" step="0.01" value="<?php echo esc_attr($settings['payment_amount']??''); ?>" placeholder="499"></label><label><?php esc_html_e( 'Currency', 'ggm-member-dashboard' ); ?><input type="text" name="payment_currency" maxlength="10" value="<?php echo esc_attr($settings['payment_currency']??ggm_get_setting('ggm_currency','INR')); ?>" placeholder="INR"></label></div><label><?php esc_html_e( 'Payment description', 'ggm-member-dashboard' ); ?><input type="text" name="payment_description" value="<?php echo esc_attr($settings['payment_description']??''); ?>" placeholder="<?php esc_attr_e( 'Shown in Razorpay checkout', 'ggm-member-dashboard' ); ?>"></label><div class="ggm-rich-message-editor"><label for="form_payment_success"><?php esc_html_e( 'Paid success message', 'ggm-member-dashboard' ); ?></label><?php wp_editor( $settings['payment_success'] ?? 'Payment received. Your response has been submitted.', 'form_payment_success', array_merge( $rich_editor_settings, array( 'textarea_name' => 'payment_success', 'textarea_rows' => 5 ) ) ); ?></div></div>
					</section>

					<section id="ggm-positioning" class="ggm-builder-panel ggm-builder-tab-panel ggm-position-panel" role="tabpanel" aria-labelledby="ggm-builder-tab-positioning" tabindex="0" hidden>
						<div class="ggm-panel-title"><h3><?php esc_html_e( 'Positioning', 'ggm-member-dashboard' ); ?></h3><p><?php esc_html_e( 'Choose where members will see this form.', 'ggm-member-dashboard' ); ?></p></div>
						<div class="ggm-placement-grid"><label class="ggm-placement-card"><input type="checkbox" name="locations[]" value="dashboard" <?php checked(in_array('dashboard',$locations,true)); ?>><span><strong><?php esc_html_e( 'Member Dashboard', 'ggm-member-dashboard' ); ?></strong><small><?php esc_html_e( 'Show under Health Information', 'ggm-member-dashboard' ); ?></small></span></label><label class="ggm-placement-card"><input type="checkbox" name="locations[]" value="workshop_all" <?php checked(in_array('workshop_all',$locations,true)); ?>><span><strong><?php esc_html_e( 'All Workshops', 'ggm-member-dashboard' ); ?></strong><small><?php esc_html_e( 'Show on every workshop', 'ggm-member-dashboard' ); ?></small></span></label><label class="ggm-placement-card"><input type="checkbox" name="locations[]" value="course_all" <?php checked(in_array('course_all',$locations,true)); ?>><span><strong><?php esc_html_e( 'All Courses', 'ggm-member-dashboard' ); ?></strong><small><?php esc_html_e( 'Show on every course', 'ggm-member-dashboard' ); ?></small></span></label><label class="ggm-placement-card"><input type="checkbox" name="locations[]" value="elementor_popup" <?php checked(in_array('elementor_popup',$locations,true)); ?>><span><strong><?php esc_html_e( 'Elementor Button Popup', 'ggm-member-dashboard' ); ?></strong><small><?php esc_html_e( 'Use Button Link → Dynamic Tags → GGM Form Popup URL', 'ggm-member-dashboard' ); ?></small></span></label><label class="ggm-placement-card"><input type="checkbox" name="locations[]" value="shortcode" <?php checked(in_array('shortcode',$locations,true)); ?>><span><strong><?php esc_html_e( 'Shortcode Embed', 'ggm-member-dashboard' ); ?></strong><small><?php esc_html_e( 'Allow this form to render anywhere with the shortcode.', 'ggm-member-dashboard' ); ?></small></span></label></div>
						<div class="ggm-input-group ggm-span-2 ggm-shortcode-output"><span class="ggm-input-label"><?php esc_html_e( 'Form Shortcode', 'ggm-member-dashboard' ); ?></span><?php if ( $edit_id ) : ?><code>[ggm_form id="<?php echo esc_attr( $edit_id ); ?>"]</code><small><?php esc_html_e( 'Enable Shortcode Embed above, save the form, then place this shortcode on any page, post, or shortcode widget.', 'ggm-member-dashboard' ); ?></small><?php else : ?><code>[ggm_form id="..."]</code><small><?php esc_html_e( 'Save the form first to generate its shortcode ID.', 'ggm-member-dashboard' ); ?></small><?php endif; ?></div>
						<div class="ggm-specific-grid"><div class="ggm-input-group"><label for="form_workshops"><?php esc_html_e( 'Specific Workshops', 'ggm-member-dashboard' ); ?></label><select id="form_workshops" name="workshop_ids[]" multiple size="6"><?php foreach($workshops as $p): ?><option value="<?php echo esc_attr($p->ID); ?>" <?php selected(in_array($p->ID,$workshop_ids,true)); ?>><?php echo esc_html($p->post_title); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Use Ctrl/Cmd to choose more than one.', 'ggm-member-dashboard' ); ?></small></div><div class="ggm-input-group"><label for="form_courses"><?php esc_html_e( 'Specific Courses', 'ggm-member-dashboard' ); ?></label><select id="form_courses" name="course_ids[]" multiple size="6"><?php foreach($courses as $p): ?><option value="<?php echo esc_attr($p->ID); ?>" <?php selected(in_array($p->ID,$course_ids,true)); ?>><?php echo esc_html($p->post_title); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Use Ctrl/Cmd to choose more than one.', 'ggm-member-dashboard' ); ?></small></div></div>
					</section>

					<section id="ggm-review" class="ggm-builder-panel ggm-builder-tab-panel ggm-review-panel" role="tabpanel" aria-labelledby="ggm-builder-tab-review" tabindex="0" hidden>
						<div class="ggm-panel-title"><h3><?php esc_html_e( 'Review & Save', 'ggm-member-dashboard' ); ?></h3><p><?php esc_html_e( 'Check the form structure and placement before saving.', 'ggm-member-dashboard' ); ?></p></div>
						<div class="ggm-review-summary" aria-live="polite">
							<div><span><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?></span><strong data-review="title">—</strong></div>
							<div><span><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></span><strong data-review="status">—</strong></div>
							<div><span><?php esc_html_e( 'Structure', 'ggm-member-dashboard' ); ?></span><strong data-review="structure">—</strong></div>
							<div><span><?php esc_html_e( 'Placement', 'ggm-member-dashboard' ); ?></span><strong data-review="placement">—</strong></div>
						</div>
						<div class="ggm-builder-actions"><button type="button" class="button" id="ggm-preview-form" data-preview-nonce="<?php echo esc_attr( wp_create_nonce( 'ggm_preview_form_builder' ) ); ?>"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Preview Form', 'ggm-member-dashboard' ); ?></button><div><button type="submit" class="button ggm-save-draft" data-status="draft"><?php esc_html_e( 'Save as Draft', 'ggm-member-dashboard' ); ?></button><button type="submit" class="button button-primary ggm-create-form"><?php echo $form ? esc_html__('Update Form','ggm-member-dashboard') : esc_html__('Create Form','ggm-member-dashboard'); ?> <span class="dashicons dashicons-arrow-right-alt"></span></button></div></div>
					</section>
				</div>
			</div>
		</form>
		</section>
		<style>
		.ggm-health-admin{max-width:1240px;margin:26px 28px 40px 20px;color:#172033}.ggm-admin-page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px}.ggm-admin-page-header h1{font-size:28px;font-weight:700;line-height:1.2;margin:0 0 8px;color:#172033}.ggm-admin-page-header p,.ggm-panel-title p{margin:0;color:#697386}.ggm-help-button{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border:1px solid #dfe3eb;border-radius:8px;background:#fff;color:#172033;text-decoration:none}.ggm-health-admin .nav-tab-wrapper{border-bottom:1px solid #e1e5ed;margin-bottom:26px;padding:0}.ggm-health-admin .nav-tab{margin:0;background:#fff;border:1px solid #e1e5ed;border-bottom:0;padding:13px 26px;color:#4b5565}.ggm-health-admin .nav-tab:first-child{border-radius:8px 0 0 0}.ggm-health-admin .nav-tab:last-child{border-radius:0 8px 0 0}.ggm-health-admin .nav-tab-active{color:#5146e5;border-bottom:2px solid #5b50ec}.ggm-admin-card{background:#fff;border:1px solid #e4e7ee;border-radius:16px;box-shadow:0 5px 18px rgba(30,41,59,.05);padding:26px;margin:0 0 24px}.ggm-card-heading{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:18px}.ggm-card-heading h2,.ggm-builder-card>h2{font-size:18px;margin:0}.ggm-form-toolbar{display:flex;gap:12px;align-items:center}.ggm-form-toolbar label{position:relative}.ggm-form-toolbar label .dashicons{position:absolute;left:11px;top:9px;color:#64748b}.ggm-form-toolbar input{width:220px;padding-left:36px!important}.ggm-health-admin .button-primary{background:#5b50ec;border-color:#5b50ec;box-shadow:none}.ggm-health-admin .button-primary:hover{background:#493fd7;border-color:#493fd7}.ggm-form-toolbar .button{display:inline-flex;align-items:center;gap:5px;padding:3px 15px;min-height:38px}.ggm-table-wrap{border:1px solid #e5e8ef;border-radius:10px;overflow:hidden}.ggm-health-admin table.widefat{border:0}.ggm-health-admin table.widefat th{background:#fafbfc;padding:13px 16px;color:#596274;font-weight:600}.ggm-health-admin table.widefat td{padding:15px 16px;vertical-align:middle}.ggm-status-badge{display:inline-flex;padding:4px 9px;border-radius:99px;background:#eef2ff;color:#4f46e5;font-size:12px;font-weight:600}.ggm-status-badge.is-draft{background:#fff7ed;color:#c2410c}.ggm-status-badge.is-archived{background:#f1f5f9;color:#64748b}.ggm-action-separator{color:#cbd5e1;margin:0 5px}.ggm-archive-link{color:#b42318}.ggm-empty-row td{text-align:center!important;padding:45px!important}.ggm-empty-row .dashicons{display:block;margin:0 auto 12px;font-size:42px;width:42px;height:42px;color:#a9b1c4}.ggm-empty-row strong,.ggm-empty-row small{display:block}.ggm-empty-row small{color:#7a8496;margin-top:7px}.ggm-builder-card>h2{margin-bottom:24px}.ggm-builder-layout{display:grid;grid-template-columns:210px minmax(0,1fr);gap:28px}.ggm-builder-nav{position:sticky;top:48px;align-self:start;border-right:1px solid #edf0f5;padding-right:18px}.ggm-builder-nav button{display:block;width:100%;padding:12px 14px;border:0;border-left:2px solid transparent;border-radius:0 7px 7px 0;background:transparent;color:#4d5768;text-align:left;font:inherit;cursor:pointer;margin-bottom:3px}.ggm-builder-nav button:hover,.ggm-builder-nav button.is-active{color:#5146e5;background:linear-gradient(90deg,#f2f0ff,#fafaff);border-left-color:#5b50ec}.ggm-builder-nav button:focus-visible{outline:2px solid #5146e5;outline-offset:2px}.ggm-builder-content{min-width:0;min-height:420px}.ggm-builder-panel{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 22px;padding:4px 0 26px}.ggm-builder-tab-panel[hidden]{display:none!important}.ggm-panel-title{grid-column:1/-1;margin-bottom:5px}.ggm-panel-title h3{font-size:16px;margin:0 0 6px}.ggm-input-group{display:flex;flex-direction:column;gap:7px}.ggm-input-group label,.ggm-input-label{font-weight:600}.ggm-input-group small,.ggm-checkbox-label small{color:#7a8496;font-weight:400}.ggm-span-2{grid-column:1/-1}.ggm-health-admin input[type=text],.ggm-health-admin input[type=search],.ggm-health-admin input[type=number],.ggm-health-admin select,.ggm-health-admin textarea{border:1px solid #dce1ea;border-radius:7px;min-height:40px;padding:8px 11px;box-shadow:none}.ggm-health-admin textarea{min-height:82px;resize:vertical}.ggm-builder-panel input:not([type=checkbox]):not([type=radio]),.ggm-builder-panel select,.ggm-builder-panel textarea{width:100%;max-width:none}.ggm-inline-inputs{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.ggm-inline-inputs label{display:block}.ggm-checkbox-label{display:flex;gap:10px;align-items:flex-start;font-weight:400!important}.ggm-checkbox-label span,.ggm-checkbox-label small{display:block}.ggm-fields-panel,.ggm-position-panel{border-top:0;padding-top:4px;margin-top:0}.ggm-field-palette{grid-column:1/-1;display:grid;grid-template-columns:repeat(6,minmax(125px,1fr));gap:12px}.ggm-field-palette .button{height:48px;display:flex;align-items:center;justify-content:flex-start;gap:8px;padding:0 13px;border-color:#dde2eb;background:#fff;color:#273244}.ggm-field-palette .button:hover{border-color:#6b61ef;color:#5146e5}.ggm-field-palette #ggm-add-section{border-style:dashed;border-color:#887ff4;color:#5146e5}.ggm-fields-panel #ggm-sections{grid-column:1/-1;min-height:110px;border:1px dashed #c9c6fa;border-radius:10px;padding:15px;background:#fbfbff}.ggm-fields-panel #ggm-sections:empty:after{content:'Start building your form — add fields and sections above.';display:flex;min-height:80px;align-items:center;justify-content:center;color:#7b8494}.ggm-builder-section{background:#fff;border:1px solid #dfe3eb;border-radius:10px;padding:16px;margin:0 0 14px}.ggm-builder-section:last-child{margin-bottom:0}.ggm-builder-field{clear:both;background:#f8f9fc;border:1px solid #e5e8ef;border-left:3px solid #5b50ec;border-radius:8px;padding:14px;margin:12px 0}.ggm-builder-field input[type=text],.ggm-builder-field textarea{width:100%}.ggm-field-actions{float:right;display:flex;gap:5px;align-items:center}.ggm-field-actions .button{min-height:28px;padding:0 8px}.ggm-drag{cursor:move;color:#657085;font-weight:600;text-transform:capitalize}.ggm-placement-grid{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.ggm-placement-card{display:flex;gap:11px;border:1px solid #dfe3eb;border-radius:10px;padding:16px;cursor:pointer}.ggm-placement-card:has(input:checked){border-color:#6d63ee;background:#f8f7ff}.ggm-placement-card strong,.ggm-placement-card small{display:block}.ggm-placement-card small{color:#7a8496;margin-top:4px}.ggm-specific-grid{grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:20px}.ggm-specific-grid select{min-height:135px}.ggm-builder-actions{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;border-top:1px solid #edf0f5;padding-top:22px;margin-top:2px}.ggm-builder-actions>div{display:flex;gap:10px}.ggm-builder-actions .button{display:inline-flex;align-items:center;gap:7px;min-height:40px;padding:3px 17px}.ggm-save-draft{border-color:#5b50ec!important;color:#5146e5!important}.ggm-create-form{min-width:145px;justify-content:center}.ggm-health-rule{border-radius:12px;border-color:#e4e7ee;box-shadow:0 4px 16px rgba(30,41,59,.04)}
		.ggm-builder-field h4{font-size:15px;text-transform:capitalize;margin:0 0 18px}.ggm-common-settings,.ggm-type-settings{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.ggm-common-settings .ggm-config-control:first-child,.ggm-common-settings .ggm-config-control:nth-child(2),.ggm-span-all,.ggm-items-editor,.ggm-inline-field-preview{grid-column:1/-1}.ggm-builder-field hr{border:0;border-top:1px solid #e4e8f0;margin:18px 0}.ggm-config-control{display:flex;flex-direction:column;gap:6px}.ggm-config-control>span,.ggm-items-editor>strong{font-size:12px;font-weight:700;color:#667085;letter-spacing:.04em;text-transform:uppercase}.ggm-config-check{display:flex;align-items:center;gap:7px}.ggm-country-config-preview,.ggm-country-preview-pill{display:flex;align-items:center;gap:8px;width:max-content;min-height:34px;padding:7px 9px;border:1px solid #dce1ea;border-radius:7px;background:#fff;color:#273244;font-weight:600}.ggm-country-config-preview img,.ggm-country-preview-pill img{width:24px;height:18px;object-fit:cover;border:1px solid #d0d7e2;border-radius:2px;background:#fff}.ggm-item-list{margin:8px 0}.ggm-config-item{display:grid;grid-template-columns:55px minmax(0,1fr) 34px 34px 25px;gap:6px;align-items:center;margin:7px 0}.ggm-config-item .button{min-width:30px!important;padding:0!important}.ggm-item-symbol{text-align:center;font-size:15px;color:#5865e8;cursor:grab;user-select:none}.ggm-file-groups{display:flex;flex-wrap:wrap;gap:9px 18px;padding:10px;border:1px solid #dfe3eb;border-radius:7px;background:#fff}.ggm-file-groups label{text-transform:capitalize}.ggm-server-limit{align-self:end;color:#667085}.ggm-inline-field-preview{background:#fff;border:1px dashed #b8b4f4;border-radius:8px;padding:14px;margin-top:5px}.ggm-inline-field-preview>small{display:block;color:#655bea;font-weight:700;margin-bottom:8px}.ggm-inline-field-preview>strong{display:block;margin-bottom:9px}.ggm-inline-field-preview>span{display:block;margin:5px 0}.ggm-inline-field-preview input,.ggm-inline-field-preview textarea,.ggm-inline-field-preview select{max-width:100%}.ggm-inline-field-preview table{width:100%;border-collapse:collapse}.ggm-inline-field-preview th,.ggm-inline-field-preview td{border:1px solid #e2e6ee;padding:6px;text-align:center}.ggm-phone-preview{display:grid;grid-template-columns:max-content minmax(0,1fr);gap:8px}.preview-scale{display:flex;align-items:end;justify-content:center;gap:13px}.preview-scale span{display:flex;flex-direction:column;text-align:center}.preview-scale b{font-size:18px;font-weight:400}.preview-scale em{font-size:12px;color:#667085}
.ggm-richtext{border:1px solid #dce1ea;border-radius:7px;background:#fff;overflow:hidden}.ggm-richtext-toolbar{display:flex;gap:2px;padding:4px;background:#f8f9fc}.ggm-richtext>.ggm-richtext-toolbar{border-bottom:1px solid #e4e8f0}.ggm-richtext-toolbar button{min-width:26px;height:24px;padding:0 6px;border:1px solid transparent;border-radius:5px;background:transparent;color:#4d5768;cursor:pointer;font-size:12px;white-space:nowrap}.ggm-richtext-toolbar button:hover,.ggm-richtext-toolbar button:focus-visible{background:#eef2ff;border-color:#c7d2fe}.ggm-richtext-input{min-height:70px;padding:10px 11px;outline:none}.ggm-richtext-input:empty:before{content:attr(aria-label);color:#98a2b3}.ggm-richtext--inline{display:flex;align-items:center;gap:4px;border:0;background:transparent}.ggm-richtext--inline .ggm-richtext-toolbar{border:0;background:transparent;padding:0;flex:0 0 auto}.ggm-richtext-input--inline{flex:1;min-width:0;min-height:36px;padding:8px 11px;border:1px solid #dce1ea;border-radius:7px;white-space:pre-wrap;overflow-wrap:anywhere}
		.ggm-chip-admin-options{margin-top:10px;padding:12px;border:1px solid #e1e5ed;border-radius:8px;background:#fff}.ggm-chip-admin-options>strong{display:block;margin-bottom:8px}.ggm-chip-admin-options span,.ggm-chip-admin-preview span,.ggm-answer-chip{display:inline-flex!important;margin:3px 5px 3px 0!important;padding:5px 10px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:12px;font-weight:600}.ggm-chip-admin-preview{display:flex;flex-wrap:wrap;margin-top:9px}.ggm-search-admin-preview{display:grid;gap:8px;max-width:420px}.ggm-search-admin-preview>span{display:inline-flex;width:max-content;align-items:center;gap:7px;padding:6px 9px;border:1px solid #cbd5e1;border-radius:999px;background:#f8fafc;color:#111827;font-weight:600}.ggm-search-admin-preview button{display:inline-grid;place-items:center;width:18px;height:18px;padding:0;border:0;border-radius:999px;background:#e2e8f0;color:#111827}.ggm-search-admin-preview input{width:100%}.ggm-submission-answer{margin-bottom:8px}
		.ggm-upload-admin-preview{display:flex;flex-wrap:wrap;gap:8px;align-items:center;max-width:420px;padding:12px;border:1px dashed #94a3b8;border-radius:8px;background:#f8fafc}.ggm-upload-admin-preview strong{flex:1 0 100%;color:#111827}.ggm-upload-admin-preview span{display:inline-flex;padding:5px 9px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#273244;font-size:12px;font-weight:600}.ggm-upload-admin-preview small{flex:1 0 100%;color:#667085}
		.ggm-template-callout{display:flex;align-items:flex-start;gap:14px;padding:16px;border:1px solid #c9c6fa;border-radius:10px;background:#f8f7ff}.ggm-template-callout>.dashicons{flex:0 0 auto;width:28px;height:28px;font-size:28px;color:#5b50ec}.ggm-template-callout>div{flex:1;min-width:0}.ggm-template-callout strong{display:block;margin-bottom:4px;color:#312e81}.ggm-template-callout p{margin:0;color:#5c6480}.ggm-template-callout .button{flex:0 0 auto}.ggm-review-summary{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ggm-review-summary>div{min-width:0;padding:15px;border:1px solid #e2e6ee;border-radius:9px;background:#fafbfc}.ggm-review-summary span,.ggm-review-summary strong{display:block}.ggm-review-summary span{margin-bottom:6px;color:#7a8496;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase}.ggm-review-summary strong{overflow-wrap:anywhere;color:#273244}.ggm-review-panel .ggm-builder-actions{margin-top:8px}.ggm-builder-tab-panel:focus-visible{outline:2px solid #5146e5;outline-offset:4px}
		.ggm-builder-error{margin:0 0 20px;padding:10px 12px}.ggm-builder-error[hidden]{display:none!important}
		.ggm-fields-workspace{grid-column:1/-1;display:grid;grid-template-columns:235px minmax(0,1fr);gap:20px;align-items:start}.ggm-fields-sidebar{position:sticky;top:58px;display:flex;flex-direction:column;gap:12px;max-height:calc(100vh - 90px);overflow:auto;padding:12px;border:1px solid #e1e5ed;border-radius:10px;background:#fff}.ggm-fields-sidebar-tabs{display:grid;grid-template-columns:1fr 1fr;gap:5px}.ggm-fields-sidebar-tabs button{min-height:34px;border:1px solid #dde2eb;border-radius:7px;background:#f8f9fc;color:#4d5768;cursor:pointer;font-weight:600}.ggm-fields-sidebar-tabs button.is-active{border-color:#5b50ec;background:#f2f0ff;color:#5146e5}.ggm-fields-sidebar-panel[hidden]{display:none!important}.ggm-fields-sidebar .ggm-field-palette{display:flex;flex-direction:column;gap:7px}.ggm-fields-sidebar .ggm-field-palette .button{width:100%;height:40px;min-height:40px;justify-content:flex-start}.ggm-add-section-button{width:100%;justify-content:flex-start;margin-top:10px;border-style:dashed!important;border-color:#887ff4!important;color:#5146e5!important}.ggm-section-tabs{display:flex;flex-direction:column;gap:7px}.ggm-section-tabs button{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;min-height:38px;padding:8px 10px;border:1px solid #dde2eb;border-radius:7px;background:#fff;color:#273244;text-align:left;cursor:pointer}.ggm-section-tabs button span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ggm-section-tabs button small{flex:0 0 auto;color:#7a8496}.ggm-section-tabs button.is-active{border-color:#5b50ec;background:#f8f7ff;color:#5146e5}.ggm-section-editor{min-width:0}.ggm-section-status{margin:0 0 10px;color:#667085;font-weight:600}.ggm-builder-section:not(.is-active){display:none}.ggm-section-add-field{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;margin:12px 0 4px;padding:11px;border:1px dashed #c9c6fa;border-radius:8px;background:#fbfbff}.ggm-section-add-field label{display:block}.ggm-section-add-field select{width:100%}.ggm-section-add-field .button{min-height:40px}
		@media(max-width:900px){.ggm-fields-workspace{grid-template-columns:1fr}.ggm-fields-sidebar{position:static;max-height:none}.ggm-fields-sidebar .ggm-field-palette,.ggm-section-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.ggm-fields-sidebar .ggm-field-palette,.ggm-section-tabs,.ggm-section-add-field{grid-template-columns:1fr}}
		.ggm-delete-form-modal{position:fixed;inset:0;z-index:100000;display:grid;place-items:center;padding:20px;background:rgba(23,32,51,.55)}.ggm-delete-form-modal[hidden]{display:none!important}.ggm-delete-form-modal-dialog{width:min(480px,100%);padding:26px;border-radius:12px;background:#fff;box-shadow:0 20px 60px rgba(23,32,51,.28)}.ggm-delete-form-modal-dialog h2{margin:0 0 12px;font-size:18px;color:#b42318}.ggm-delete-form-modal-dialog p{margin:0 0 12px;color:#344054}.ggm-delete-form-modal-target{padding:8px 11px;border-radius:6px;background:#f8f9fc;font-weight:600;color:#172033;overflow-wrap:anywhere}#ggm-delete-form-modal-input{width:100%;box-sizing:border-box;margin-bottom:6px}.ggm-delete-form-modal-error{color:#b42318}.ggm-delete-form-modal-error[hidden]{display:none!important}.ggm-delete-form-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}.ggm-delete-form-modal-confirm{background:#b42318!important;border-color:#b42318!important;color:#fff!important}.ggm-delete-form-modal-confirm:disabled{opacity:.5;cursor:not-allowed}
		@media(max-width:1100px){.ggm-field-palette{grid-template-columns:repeat(3,1fr)}.ggm-builder-layout{grid-template-columns:170px minmax(0,1fr)}.ggm-review-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:782px){.ggm-health-admin{margin:18px 12px}.ggm-admin-page-header,.ggm-card-heading,.ggm-builder-actions,.ggm-template-callout{align-items:stretch;flex-direction:column}.ggm-form-toolbar{flex-wrap:wrap}.ggm-form-toolbar label,.ggm-form-toolbar input{width:100%}.ggm-health-admin .nav-tab{padding:10px 12px}.ggm-builder-layout{display:block}.ggm-builder-nav{position:sticky;top:46px;z-index:5;border:0;display:flex;overflow:auto;padding:4px 0 14px;background:#fff}.ggm-builder-nav button{flex:0 0 auto;width:auto;white-space:nowrap;border-left:0;border-bottom:2px solid transparent;border-radius:7px 7px 0 0}.ggm-builder-nav button.is-active{border-bottom-color:#5b50ec}.ggm-builder-panel,.ggm-specific-grid,.ggm-placement-grid,.ggm-common-settings,.ggm-type-settings{grid-template-columns:1fr}.ggm-span-2{grid-column:auto}.ggm-field-palette{grid-template-columns:repeat(2,1fr)}.ggm-inline-inputs{grid-template-columns:1fr}.ggm-field-actions{float:none;margin-bottom:10px;flex-wrap:wrap}.ggm-config-item{grid-template-columns:22px minmax(0,1fr) 30px 30px 22px}.ggm-template-callout .button{align-self:flex-start}}@media(max-width:600px){.ggm-builder-nav{top:0}}@media(max-width:480px){.ggm-admin-card{padding:18px}.ggm-field-palette,.ggm-review-summary{grid-template-columns:1fr}.ggm-field-palette .button{height:auto;min-height:46px}.ggm-builder-actions>div{flex-direction:column}.ggm-builder-actions .button{width:100%;justify-content:center}}
		</style>
		<script>
		(function(){var schema=<?php echo wp_json_encode($schema); ?>,countryCodes=<?php echo wp_json_encode( GGM_Form_Builder::country_calling_codes() ); ?>,diseaseOptions=<?php echo wp_json_encode( class_exists( 'GGM_Diseases' ) ? GGM_Diseases::active_labels() : array() ); ?>,sections=document.getElementById('ggm-sections'),form=document.getElementById('ggm-builder-form'),active=0,sectionTabs=document.getElementById('ggm-section-tabs'),sectionStatus=document.getElementById('ggm-section-status'),fieldLabels={short_answer:'Short answer',paragraph:'Paragraph',multiple_choice:'Multiple choice',checkboxes:'Checkboxes',dropdown:'Dropdown',file_upload:'File upload',linear_scale:'Linear scale',multiple_choice_grid:'Multiple-choice grid',checkbox_grid:'Checkbox grid',date:'Date',time:'Time',chip_selector:'Chip Selector',search_select:'Search select'};
		function key(){return 'k_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,8)}
		function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
		function normalizeField(f){f.options=Array.isArray(f.options)?f.options:[];f.rows=Array.isArray(f.rows)?f.rows:[];f.placeholder=f.placeholder||'';f.validation_type=f.validation_type||(f.subtype&&['email','number','phone'].includes(f.subtype)?f.subtype:'none');f.phone_country_enabled=!!f.phone_country_enabled;f.phone_country_iso=countryCodes.some(function(c){return c.iso===String(f.phone_country_iso||'').toUpperCase()})?String(f.phone_country_iso).toUpperCase():'IN';f.phone_country_code=(countryCodes.find(function(c){return c.iso===f.phone_country_iso})||{dial:'+91'}).dial;f.field_width=['30','50','100'].includes(String(f.field_width||''))?String(f.field_width):'100';f.show_after_submit=!!f.show_after_submit;f.disease_source=!!f.disease_source;f.number_rule=f.number_rule||'none';f.min_length=Number(f.min_length||0);f.max_length=Number(f.max_length||0);f.validation_message=f.validation_message||'';f.select_prompt=f.select_prompt||'Choose an option';f.selection_rule=f.selection_rule||'none';f.selection_count=Number(f.selection_count||0);f.selection_mode=f.selection_mode==='single'?'single':'multiple';f.allow_custom_values=!!f.allow_custom_values;f.min_selections=Number(f.min_selections||0);f.max_selections=f.type==='search_select'?Math.max(1,Number(f.max_selections||1)):Number(f.max_selections||0);f.allowed_type_groups=Array.isArray(f.allowed_type_groups)?f.allowed_type_groups:['image','pdf','document'];f.custom_extensions=Array.isArray(f.custom_extensions)?f.custom_extensions.join(', '):(f.custom_extensions||'');f.max_files=Math.max(1,Math.min(50,Number(f.max_files||1)));f.max_size=Number(f.max_size||10);f.min=f.min??1;f.max=f.max??5;f.min_label=f.min_label||'';f.max_label=f.max_label||'';f.include_year=true;f.time_type=f.time_type||'time_of_day';f.time_format=String(f.time_format||'24');f.duration_units=Array.isArray(f.duration_units)?f.duration_units:['hours','minutes'];return f}
		var normalizeFieldVisibilityBase=normalizeField;normalizeField=function(f){f=normalizeFieldVisibilityBase(f);f.show_uploaded_files_to_user=!!f.show_uploaded_files_to_user;f.allow_multiple_entries=!!f.allow_multiple_entries;return f}
		function cfg(key,label,value,type,extra){type=type||'text';extra=extra||'';if(type==='checkbox')return '<label class="ggm-config-check"><input type="checkbox" data-config="'+key+'" '+(value?'checked':'')+'> '+label+'</label>';if(type==='textarea')return '<label class="ggm-config-control"><span>'+label+'</span><textarea data-config="'+key+'" '+extra+'>'+esc(value||'')+'</textarea></label>';return '<label class="ggm-config-control"><span>'+label+'</span><input type="'+type+'" data-config="'+key+'" value="'+esc(value??'')+'" '+extra+'></label>'}
		function selectCfg(key,label,value,choices){var h='<label class="ggm-config-control"><span>'+label+'</span><select data-config="'+key+'">';Object.keys(choices).forEach(function(v){h+='<option value="'+v+'" '+(String(value)===String(v)?'selected':'')+'>'+choices[v]+'</option>'});return h+'</select></label>'}
		function flagEmoji(iso){return String(iso||'').toUpperCase().replace(/./g,function(c){return String.fromCodePoint(127397+c.charCodeAt(0))})}
		function flagUrl(iso){var c=(countryCodes.find(function(item){return item.iso===String(iso||'').toUpperCase()})||{});return c.flag||('https://flagcdn.com/24x18/'+String(iso||'in').toLowerCase()+'.png')}
		function countryName(iso){try{return new Intl.DisplayNames([navigator.language||'en'],{type:'region'}).of(iso)||iso}catch(e){return iso}}
		function countrySelectCfg(key,label,value){var selected=countryCodes.find(function(c){return c.iso===String(value||'').toUpperCase()})||countryCodes.find(function(c){return c.iso==='IN'})||countryCodes[0],h='<label class="ggm-config-control ggm-country-config"><span>'+label+'</span><span class="ggm-country-config-preview"><img src="'+esc(selected.flag||flagUrl(selected.iso))+'" alt=""> '+esc(selected.dial)+'</span><select data-config="'+key+'">';countryCodes.forEach(function(c){h+='<option value="'+esc(c.iso)+'" data-dial="'+esc(c.dial)+'" '+(String(value)===String(c.iso)?'selected':'')+'>'+esc(c.dial)+'</option>'});return h+'</select></label>'}
		function richTextToolbar(inline){var h='<div class="ggm-richtext-toolbar" role="toolbar" aria-label="Formatting"><button type="button" data-cmd="bold" title="Bold"><b>B</b></button><button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';if(!inline)h+='<button type="button" data-cmd="insertUnorderedList" title="Bulleted list">• List</button><button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>';h+='<button type="button" data-cmd="createLink" title="Insert link">Link</button><button type="button" data-cmd="unlink" title="Remove link">Unlink</button></div>';return h}
		function richText(key,label,value){return '<div class="ggm-config-control ggm-span-all ggm-richtext-field"><span>'+label+'</span><div class="ggm-richtext">'+richTextToolbar(false)+'<div class="ggm-richtext-input" contenteditable="true" role="textbox" aria-multiline="true" data-config="'+key+'" data-richtext="1" aria-label="'+label+'">'+(value||'')+'</div></div></div>'}
		function richTextInline(label,value){return '<div class="ggm-richtext ggm-richtext--inline"><div class="ggm-richtext-input ggm-richtext-input--inline" contenteditable="true" role="textbox" data-richtext="1" aria-label="'+label+'">'+(value||'')+'</div>'+richTextToolbar(true)+'</div>'}
		function itemEditor(f,prop,title,kind){var items=f[prop]||[],symbol=kind==='row'?'↕':(f.type==='checkboxes'||f.type==='checkbox_grid'?'□':'○'),rich=f.type==='checkboxes'&&prop==='options',h='<div class="ggm-items-editor"><strong>'+title+'</strong><div class="ggm-item-list" data-prop="'+prop+'">';items.forEach(function(item,i){var ariaLabel=(kind==='row'?'Row':'Option')+' '+(i+1)+' label',field=rich?richTextInline(ariaLabel,item.label||item):'<input type="text" aria-label="'+ariaLabel+'" value="'+esc(item.label||item)+'" placeholder="'+(kind==='row'?'Row':'Option')+' '+(i+1)+'">';h+='<div class="ggm-config-item" data-key="'+esc(item.key||key())+'"><span class="ggm-item-symbol" draggable="true" title="Drag to reorder">⋮⋮ '+symbol+'</span>'+field+'<button type="button" class="button item-up" title="Move up" aria-label="Move item up">↑</button><button type="button" class="button item-down" title="Move down" aria-label="Move item down">↓</button><button type="button" class="button-link-delete delete-item" title="Delete" aria-label="Delete item">×</button></div>'});h+='</div><button type="button" class="button add-config-item" data-prop="'+prop+'">+ Add '+(kind==='row'?'row':kind==='column'?'column':'option')+'</button></div>';return h}
		function chipOptionsEditor(f){return '<div class="ggm-config-control ggm-span-all"><span>Available Options</span><textarea class="ggm-chip-options-input" placeholder="Headache, Fatigue, Anxiety">'+esc((f.options||[]).map(function(o){return o.label}).join(', '))+'</textarea><small>Separate options with commas. Empty and duplicate values are ignored.</small><div class="ggm-chip-admin-options"><strong>Available options</strong><div>'+(f.options||[]).map(function(o){return '<span>'+esc(o.label)+'</span>'}).join('')+'</div></div></div>'}
		function searchOptionsEditor(f){var source=cfg('disease_source','Use Diseases List',f.disease_source,'checkbox');if(f.disease_source){return source+'<div class="ggm-config-control ggm-span-all"><span>Search options</span><p class="description">This field will use the current DZ LMS Diseases List titles.</p><div class="ggm-chip-admin-options"><strong>Diseases List options</strong><div>'+(diseaseOptions.length?diseaseOptions.map(function(label){return '<span>'+esc(label)+'</span>'}).join(''):'<em>No diseases created yet.</em>')+'</div></div></div>'}return source+'<div class="ggm-config-control ggm-span-all"><span>Search options</span><textarea class="ggm-chip-options-input" placeholder="Cardiology, Dermatology, Nutrition">'+esc((f.options||[]).map(function(o){return o.label}).join(', '))+'</textarea><small>Separate options with commas. Respondents will see ranked matches as they type.</small><div class="ggm-chip-admin-options"><strong>Searchable options</strong><div>'+(f.options||[]).map(function(o){return '<span>'+esc(o.label)+'</span>'}).join('')+'</div></div></div>'}
		function preview(f){var label=esc(f.label||'Question preview'),h='<div class="ggm-inline-field-preview"><small>RESPONDENT PREVIEW</small><strong>'+label+'</strong>';if(f.type==='chip_selector'){h+='<div class="ggm-chip-admin-preview">'+(f.options||[]).map(function(o){return '<span>'+esc(o.label)+'</span>'}).join('')+(f.allow_custom_values?'<span>Other</span>':'')+'</div>'+(f.allow_custom_values?'<small style="display:block;margin-top:6px;color:#667085">Selecting "Other" reveals a text box for a single custom answer.</small>':'')}else if(f.type==='search_select')h+='<div class="ggm-search-admin-preview"><span>'+esc((f.options&&f.options[0]&&f.options[0].label)||'Selected option')+' <button type="button" disabled>x</button></span><input disabled type="search" placeholder="Start typing to search"></div>';else if(f.type==='paragraph')h+='<textarea disabled placeholder="'+esc(f.placeholder)+'"></textarea>';else if(f.type==='multiple_choice'||f.type==='checkboxes'){(f.options||[]).forEach(function(o){h+='<span>'+(f.type==='checkboxes'?'□':'○')+' '+(f.type==='checkboxes'?(o.label||''):esc(o.label))+'</span>'});if(f.allow_other)h+='<span>'+(f.type==='checkboxes'?'□':'○')+' Other</span>'}else if(f.type==='dropdown')h+='<select disabled><option>'+esc(f.select_prompt)+'</option></select>';else if(f.type==='file_upload')h+='<button type="button" class="button" disabled>Choose file'+(f.max_files>1?'s':'')+'</button>';else if(f.type==='linear_scale'){h+='<div class="preview-scale"><em>'+esc(f.min_label)+'</em>';for(var i=Number(f.min);i<=Number(f.max);i++)h+='<span>'+i+'<b>○</b></span>';h+='<em>'+esc(f.max_label)+'</em></div>'}else if(f.type.includes('grid')){h+='<table><tr><th></th>';f.options.forEach(function(o){h+='<th>'+esc(o.label)+'</th>'});h+='</tr>';f.rows.forEach(function(r){h+='<tr><th>'+esc(r.label)+'</th>';f.options.forEach(function(){h+='<td>'+(f.type==='checkbox_grid'?'□':'○')+'</td>'});h+='</tr>'});h+='</table>'}else if(f.type==='date')h+='<input disabled type="date" min="'+esc(f.min_date||'')+'" max="'+esc(f.max_date||'')+'">';else if(f.type==='time')h+=f.time_type==='duration'?'<span>Hours [ ] Minutes [ ] Seconds [ ]</span>':'<input disabled type="time">';else if(f.validation_type==='number'&&f.phone_country_enabled)h+='<div class="ggm-phone-preview"><span class="ggm-country-preview-pill"><img src="'+esc(flagUrl(f.phone_country_iso))+'" alt=""> '+esc(f.phone_country_code)+'</span><input disabled type="tel" placeholder="'+esc(f.placeholder||'Phone number')+'"></div>';else h+='<input disabled type="'+({email:'email',number:'number',phone:'tel',url:'url'}[f.validation_type]||'text')+'" placeholder="'+esc(f.placeholder)+'">';return h+'</div>'}
		function typeSettings(f){var h='';if(f.type==='short_answer'){h+=cfg('placeholder','Placeholder',f.placeholder);h+=selectCfg('validation_type','Response validation',f.validation_type,{none:'None / Text',email:'Email',number:'Number',phone:'Phone',url:'URL',regex:'Regular expression'});if(f.validation_type==='number'){h+=cfg('phone_country_enabled','Use country code for phone number',f.phone_country_enabled,'checkbox');if(f.phone_country_enabled){h+=countrySelectCfg('phone_country_iso','Default country code',f.phone_country_iso)}else{h+=selectCfg('number_rule','Number rule',f.number_rule,{none:'None',greater_than:'Greater than',greater_or_equal:'Greater than or equal to',less_than:'Less than',less_or_equal:'Less than or equal to',equal:'Equal to',not_equal:'Not equal to',between:'Between'});if(f.number_rule!=='none')h+=cfg('number_value','Value',f.number_value,'number','step="any"');if(f.number_rule==='between')h+=cfg('number_value_to','Second value',f.number_value_to,'number','step="any"')}}if(f.validation_type==='regex')h+=cfg('regex_pattern','Regular expression',f.regex_pattern);h+=cfg('min_length','Minimum length',f.min_length,'number','min="0"');h+=cfg('max_length','Maximum length',f.max_length,'number','min="0"');h+=cfg('validation_message','Custom validation error',f.validation_message)}else if(f.type==='paragraph'){h+=cfg('placeholder','Placeholder',f.placeholder);h+=cfg('min_length','Minimum character length',f.min_length,'number','min="0"');h+=cfg('max_length','Maximum character length',f.max_length,'number','min="0"');h+=cfg('regex_pattern','Regular expression',f.regex_pattern);h+=cfg('validation_message','Custom validation error',f.validation_message)}else if(['multiple_choice','checkboxes','dropdown'].includes(f.type)){h+=itemEditor(f,'options',f.type==='dropdown'?'OPTIONS':'CHOICES','option');if(f.type!=='dropdown')h+='<button type="button" class="button toggle-other">'+(f.allow_other?'− Remove “Other”':'+ Add “Other”')+'</button>';if(f.type==='dropdown')h+=cfg('select_prompt','Default prompt',f.select_prompt);h+=cfg('shuffle_options','Shuffle option order',f.shuffle_options,'checkbox');if(f.type==='checkboxes'){h+=selectCfg('selection_rule','Response validation',f.selection_rule,{none:'None',at_least:'Select at least',at_most:'Select at most',exactly:'Select exactly'});if(f.selection_rule!=='none')h+=cfg('selection_count','Number of selections',f.selection_count,'number','min="1"');h+=cfg('validation_message','Custom validation error',f.validation_message)}}else if(f.type==='file_upload'){h+='<div class="ggm-config-control ggm-span-all"><span>Allowed File Types</span><div class="ggm-file-groups">';['image','pdf','document','spreadsheet','presentation','video','audio'].forEach(function(group){h+='<label><input type="checkbox" data-file-group="'+group+'" '+(f.allowed_type_groups.includes(group)?'checked':'')+'> '+group.charAt(0).toUpperCase()+group.slice(1)+'</label>'});h+='</div></div>';h+=cfg('custom_extensions','Custom extensions',f.custom_extensions,'text','placeholder="e.g. json, xml"');h+=selectCfg('max_files','Maximum files',f.max_files,{1:'1',2:'2',5:'5',10:'10'});h+=selectCfg('max_size','Maximum size per file',f.max_size,{1:'1 MB',5:'5 MB',10:'10 MB',25:'25 MB',50:'50 MB',100:'100 MB'});h+='<small class="ggm-server-limit">Server maximum: <?php echo esc_js(size_format(wp_max_upload_size())); ?></small>'}else if(f.type==='linear_scale'){h+=selectCfg('min','Scale start',f.min,{0:'0',1:'1'});var ends={};for(var e=2;e<=10;e++)ends[e]=String(e);h+=selectCfg('max','Scale end',f.max,ends);h+=cfg('min_label','Left label',f.min_label);h+=cfg('max_label','Right label',f.max_label)}else if(f.type.includes('grid')){h+=itemEditor(f,'rows','ROWS','row');h+=itemEditor(f,'options','COLUMNS','column');h+=cfg('require_each_row','Require a response in each row',f.require_each_row,'checkbox');if(f.type==='multiple_choice_grid')h+=cfg('limit_one_per_column','Limit to one response per column',f.limit_one_per_column,'checkbox');h+=cfg('shuffle_rows','Shuffle row order',f.shuffle_rows,'checkbox')}else if(f.type==='chip_selector'){h+=chipOptionsEditor(f);h+=selectCfg('selection_mode','Selection Mode',f.selection_mode,{multiple:'Multiple selection',single:'Single selection'});h+=cfg('allow_custom_values','Add an "Other" option',f.allow_custom_values,'checkbox');h+='<small class="ggm-config-hint" style="display:block;margin-top:-8px;color:#667085">Adds one extra "Other" chip. Selecting it reveals a single text box for a custom answer — only one custom answer is ever allowed.</small>';if(f.selection_mode==='multiple'){h+=cfg('min_selections','Minimum selections',f.min_selections,'number','min="0"');h+=cfg('max_selections','Maximum selections',f.max_selections,'number','min="0"')}}else if(f.type==='date'){h+=cfg('min_date','Minimum date',f.min_date,'date');h+=cfg('max_date','Maximum date',f.max_date,'date')}else if(f.type==='time'){h+=selectCfg('time_type','Time type',f.time_type,{time_of_day:'Time of day',duration:'Duration'});if(f.time_type==='time_of_day')h+=selectCfg('time_format','Display format',f.time_format,{24:'24-hour',12:'12-hour'});else{h+='<div class="ggm-config-control"><span>Duration parts</span>';['hours','minutes','seconds'].forEach(function(unit){h+='<label><input type="checkbox" data-duration-unit="'+unit+'" '+(f.duration_units.includes(unit)?'checked':'')+'> '+unit+'</label> '});h+='</div>'}}return '<div class="ggm-type-settings">'+h+'</div>'}
		var defaultTypeSettings=typeSettings;typeSettings=function(f){var post=cfg('show_after_submit','Show this field after form submit',f.show_after_submit,'checkbox');if(f.type==='search_select'){var h=searchOptionsEditor(f)+cfg('max_selections','Maximum selections',f.max_selections,'number','min="1" max="100"')+post;return '<div class="ggm-type-settings">'+h+'</div>'}if(f.type==='file_upload'){var h='<div class="ggm-config-control ggm-span-all"><span>Allowed File Types</span><div class="ggm-file-groups">';['image','pdf','document','spreadsheet','presentation','video','audio'].forEach(function(group){h+='<label><input type="checkbox" data-file-group="'+group+'" '+(f.allowed_type_groups.includes(group)?'checked':'')+'> '+group.charAt(0).toUpperCase()+group.slice(1)+'</label>'});h+='</div></div>';h+=cfg('custom_extensions','Custom extensions',f.custom_extensions,'text','placeholder="e.g. json, xml"');h+=cfg('max_files','Maximum files',f.max_files,'number','min="1" max="50"');h+=selectCfg('max_size','Maximum size per file',f.max_size,{1:'1 MB',5:'5 MB',10:'10 MB',25:'25 MB',50:'50 MB',100:'100 MB'});h+='<small class="ggm-server-limit">Server maximum: <?php echo esc_js(size_format(wp_max_upload_size())); ?></small>'+post;return '<div class="ggm-type-settings">'+h+'</div>'}return defaultTypeSettings(f).replace(/<\/div>$/,post+'</div>')}
		var defaultPreview=preview;preview=function(f){if(f.type==='file_upload'){var label=esc(f.label||'Question preview');return '<div class="ggm-inline-field-preview"><small>RESPONDENT PREVIEW</small><strong>'+label+'</strong><div class="ggm-upload-admin-preview"><strong>Drop files here</strong><span>Upload from device</span><span>Capture from camera</span><small>Up to '+esc(f.max_files)+' file'+(Number(f.max_files)===1?'':'s')+'</small></div></div>'}return defaultPreview(f)}
		var visibilityTypeSettingsBase=typeSettings;typeSettings=function(f){var html=visibilityTypeSettingsBase(f),repeatLabel=f.type==='file_upload'?'Allow the user to upload more files later without removing earlier uploads':'Allow the user to submit this additional field multiple times';if(f.show_after_submit)html=html.replace(/<\/div>$/ ,cfg('allow_multiple_entries',repeatLabel,f.allow_multiple_entries,'checkbox')+'</div>');if(f.type==='file_upload')html=html.replace(/<\/div>$/ ,cfg('show_uploaded_files_to_user','Allow the user to view and download uploaded files',f.show_uploaded_files_to_user,'checkbox')+'</div>');return html}
		function previewDescription(f){var text=String(f.description||'').trim();if(!text)return '';return f.type==='checkboxes'?'<div class="description">'+text+'</div>':'<p class="description">'+esc(text)+'</p>'}
		var previewBase=preview;preview=function(f){var helper=previewDescription(f),html=previewBase(f);return helper?html.replace('</strong>','</strong>'+helper):html}
		function fieldLabel(type){return fieldLabels[type]||String(type||'field').replaceAll('_',' ')}
		function fieldTypeOptions(){return Object.keys(fieldLabels).map(function(type){return '<option value="'+esc(type)+'">'+esc(fieldLabels[type])+'</option>'}).join('')}
		function newField(t){var choice=['multiple_choice','checkboxes','dropdown','multiple_choice_grid','checkbox_grid','chip_selector','search_select'].includes(t),grid=String(t).includes('grid');return normalizeField({key:key(),type:t,label:'',description:'',required:false,placeholder:'',validation_type:'none',options:choice?[{key:key(),label:'Option 1'}]:[],rows:grid?[{key:key(),label:'Row 1'}]:[],selection_mode:'multiple',max_selections:t==='search_select'?1:0,disease_source:false,min:1,max:5,max_size:10})}
		function addFieldToSection(type,sectionIndex){sync();sectionIndex=Math.max(0,Math.min(Number(sectionIndex)||0,schema.sections.length-1));schema.sections[sectionIndex].fields.push(newField(type));active=sectionIndex;render()}
		function renderSectionTabs(){if(!sectionTabs)return;sectionTabs.innerHTML='';schema.sections.forEach(function(s,si){var b=document.createElement('button');b.type='button';b.dataset.sectionIndex=si;b.className=si===active?'is-active':'';b.setAttribute('role','tab');b.setAttribute('aria-selected',si===active?'true':'false');b.innerHTML='<span>'+esc((s.title&&s.title.trim())?s.title:'Section '+(si+1))+'</span><small>'+(s.fields||[]).length+'</small>';sectionTabs.appendChild(b)})}
		function render(){sections.innerHTML='';active=Math.max(0,Math.min(active,schema.sections.length-1));schema.sections.forEach(function(s,si){var box=document.createElement('div'),sectionTitleId='ggm-section-title-'+si,sectionDescriptionId='ggm-section-description-'+si;box.className='ggm-builder-section'+(si===active?' is-active':'');box.dataset.index=si;box.innerHTML='<div class="ggm-field-actions"><button type="button" class="button section-up" aria-label="Move section '+(si+1)+' up">↑</button> <button type="button" class="button section-down" aria-label="Move section '+(si+1)+' down">↓</button> <button type="button" class="button duplicate-section" aria-label="Duplicate section '+(si+1)+'">Duplicate</button> <button type="button" class="button-link-delete delete-section" aria-label="Remove section '+(si+1)+'">Remove section</button></div><span class="ggm-drag">☰ Section '+(si+1)+'</span><p><label class="screen-reader-text" for="'+sectionTitleId+'">Section '+(si+1)+' title</label><input id="'+sectionTitleId+'" type="text" class="section-title" placeholder="Section title" value="'+esc(s.title)+'"></p><p><label class="screen-reader-text" for="'+sectionDescriptionId+'">Section '+(si+1)+' description</label><textarea id="'+sectionDescriptionId+'" class="section-description" placeholder="Section description">'+esc(s.description)+'</textarea></p><div class="ggm-section-add-field"><label><span class="screen-reader-text">Field type for section '+(si+1)+'</span><select class="section-add-type">'+fieldTypeOptions()+'</select></label><button type="button" class="button button-primary section-add-field">+ Add field</button></div><div class="section-fields"></div>';var fs=box.querySelector('.section-fields');(s.fields||[]).forEach(function(raw,fi){var f=normalizeField(raw),d=document.createElement('div'),context='field '+(fi+1)+' in section '+(si+1);d.className='ggm-builder-field';d.dataset.index=fi;d.innerHTML='<div class="ggm-field-actions"><button type="button" class="button field-up" aria-label="Move '+context+' up">↑</button><button type="button" class="button field-down" aria-label="Move '+context+' down">↓</button><button type="button" class="button duplicate-field" aria-label="Duplicate '+context+'">Duplicate</button><button type="button" class="button move-field" aria-label="Move '+context+' to next page" title="Moves this field and every field below it in this section onto the next page">Move to next page</button><button type="button" class="button-link-delete delete-field" aria-label="Remove '+context+'">Remove</button></div><h4><span class="ggm-drag">☰</span> '+esc(fieldLabel(f.type))+'</h4><div class="ggm-common-settings">'+cfg('label','Question / Field Label',f.label)+(f.type==='checkboxes'?richText('description','Help Text / Description',f.description):cfg('description','Help Text / Description',f.description,'textarea'))+cfg('required','Required',f.required,'checkbox')+selectCfg('field_width','Field width',f.field_width,{100:'100%',50:'50%',30:'30%'})+'</div><hr>'+typeSettings(f)+preview(f);fs.appendChild(d)});sections.appendChild(box)});renderSectionTabs();if(sectionStatus)sectionStatus.textContent='Editing section '+(active+1)+' of '+schema.sections.length}
		function sync(){[].slice.call(sections.children).forEach(function(box,si){var s=schema.sections[si];s.title=box.querySelector('.section-title').value;s.description=box.querySelector('.section-description').value;[].slice.call(box.querySelectorAll('.ggm-builder-field')).forEach(function(d,fi){var f=normalizeField(s.fields[fi]);d.querySelectorAll('[data-config]').forEach(function(el){var k=el.dataset.config,v;if(el.dataset.richtext==='1')v=el.innerHTML;else if(el.type==='checkbox')v=el.checked;else v=el.value;if(el.type==='number'&&v!=='')v=Number(v);f[k]=v});var countrySelect=d.querySelector('select[data-config="phone_country_iso"]');if(countrySelect){f.phone_country_code=(countrySelect.options[countrySelect.selectedIndex]||{}).dataset.dial||'+91';};['options','rows'].forEach(function(prop){var list=d.querySelector('.ggm-item-list[data-prop="'+prop+'"]');if(list)f[prop]=[].slice.call(list.querySelectorAll('.ggm-config-item')).map(function(item){var rt=item.querySelector('[data-richtext]'),lbl=rt?rt.innerHTML.trim():item.querySelector('input').value.trim();return {key:item.dataset.key||key(),label:lbl}}).filter(function(item){return item.label})});var chipInput=d.querySelector('.ggm-chip-options-input');if(chipInput&&!f.disease_source){var existing={};f.options.forEach(function(o){existing[o.label.toLocaleLowerCase()]=o.key});var seen={};f.options=chipInput.value.split(',').map(function(label){label=label.trim();var id=label.toLocaleLowerCase();if(!label||seen[id])return null;seen[id]=true;return {key:existing[id]||key(),label:label}}).filter(Boolean)}if(f.type==='search_select'&&f.disease_source){f.options=[]}var groups=d.querySelectorAll('[data-file-group]');if(groups.length)f.allowed_type_groups=[].slice.call(groups).filter(function(x){return x.checked}).map(function(x){return x.dataset.fileGroup});var units=d.querySelectorAll('[data-duration-unit]');if(units.length)f.duration_units=[].slice.call(units).filter(function(x){return x.checked}).map(function(x){return x.dataset.durationUnit})})})}
		var builderError=document.getElementById('ggm-builder-error'),builderMessages=<?php echo wp_json_encode( array( 'discard'=>__( 'This will discard your unsaved form changes. Continue?', 'ggm-member-dashboard' ), 'empty_preview'=>__( 'Add at least one field, section title, or section description before previewing.', 'ggm-member-dashboard' ), 'empty_save'=>__( 'Add at least one field, section title, or section description before saving.', 'ggm-member-dashboard' ), 'minimum_sections'=>__( 'A form needs at least one section.', 'ggm-member-dashboard' ) ) ); ?>;
		function schemaHasContent(){return schema.sections.some(function(section){return String(section.title||'').trim()||String(section.description||'').trim()||(section.fields||[]).length})}
		function showBuilderError(message,panelId){if(builderError){builderError.textContent=message;builderError.hidden=false}activateBuilderPanel(panelId||'ggm-fields',false);if(builderError)builderError.focus()}
		function clearBuilderError(){if(builderError){builderError.hidden=true;builderError.textContent=''}}
		document.querySelectorAll('.ggm-add-field').forEach(function(b){b.onclick=function(){addFieldToSection(b.dataset.type,schema.sections.length-1)}});
		document.getElementById('ggm-add-section').onclick=function(){sync();schema.sections.push({key:key(),title:'',description:'',fields:[]});active=schema.sections.length-1;render()};
		document.querySelectorAll('[data-fields-sidebar-tab]').forEach(function(tab){tab.addEventListener('click',function(){var target=tab.dataset.fieldsSidebarTab;document.querySelectorAll('[data-fields-sidebar-tab]').forEach(function(btn){var selected=btn===tab;btn.classList.toggle('is-active',selected);btn.setAttribute('aria-selected',selected?'true':'false')});document.querySelectorAll('.ggm-fields-sidebar-panel').forEach(function(panel){panel.hidden=panel.id!==('ggm-'+(target==='palette'?'field-palette':'section-steps')+'-panel')})})});
		if(sectionTabs)sectionTabs.onclick=function(e){var btn=e.target.closest('[data-section-index]');if(!btn)return;sync();active=Number(btn.dataset.sectionIndex);render()};
		sections.onclick=function(e){var cmdBtn=e.target.closest('[data-cmd]');if(cmdBtn){e.preventDefault();var editor=cmdBtn.closest('.ggm-richtext').querySelector('.ggm-richtext-input');if(editor){editor.focus();if(cmdBtn.dataset.cmd==='createLink'){var url=window.prompt('Link URL (https://...)','https://');if(url)document.execCommand('createLink',false,url)}else{document.execCommand(cmdBtn.dataset.cmd,false,null)}builderDirty=true}return}var box=e.target.closest('.ggm-builder-section');if(box)active=Number(box.dataset.index);if(e.target.classList.contains('section-add-field')){var picker=box?box.querySelector('.section-add-type'):null;addFieldToSection(picker?picker.value:'short_answer',active);return}var d=e.target.closest('.ggm-builder-field'),fi=d?Number(d.dataset.index):-1;sync();var field=fi>=0?schema.sections[active].fields[fi]:null,item=e.target.closest('.ggm-config-item'),list=item?item.closest('.ggm-item-list'):null,prop=list?list.dataset.prop:(e.target.dataset.prop||''),ii=item?[].indexOf.call(list.children,item):-1;if(e.target.classList.contains('add-config-item'))field[prop].push({key:key(),label:(prop==='rows'?'Row ':'Option ')+(field[prop].length+1)});else if(e.target.classList.contains('toggle-other'))field.allow_other=!field.allow_other;else if(e.target.classList.contains('delete-item'))field[prop].splice(ii,1);else if(e.target.classList.contains('item-up')&&ii>0){var x=field[prop].splice(ii,1)[0];field[prop].splice(ii-1,0,x)}else if(e.target.classList.contains('item-down')&&ii<field[prop].length-1){var x=field[prop].splice(ii,1)[0];field[prop].splice(ii+1,0,x)}else if(e.target.classList.contains('delete-field'))schema.sections[active].fields.splice(fi,1);else if(e.target.classList.contains('field-up')&&fi>0){var x=schema.sections[active].fields.splice(fi,1)[0];schema.sections[active].fields.splice(fi-1,0,x)}else if(e.target.classList.contains('field-down')&&fi<schema.sections[active].fields.length-1){var x=schema.sections[active].fields.splice(fi,1)[0];schema.sections[active].fields.splice(fi+1,0,x)}else if(e.target.classList.contains('duplicate-field')){var x=JSON.parse(JSON.stringify(schema.sections[active].fields[fi]));x.key=key();schema.sections[active].fields.splice(fi+1,0,x)}else if(e.target.classList.contains('move-field')){var moved=schema.sections[active].fields.splice(fi);if(!moved.length){return}if(active===schema.sections.length-1){schema.sections.splice(active+1,0,{key:key(),title:'',description:'',fields:[]})}schema.sections[active+1].fields=moved.concat(schema.sections[active+1].fields);active=active+1}else if(e.target.classList.contains('delete-section')){if(schema.sections.length===1){alert(builderMessages.minimum_sections);return}schema.sections.splice(active,1);active=Math.max(0,active-1)}else if(e.target.classList.contains('section-up')&&active>0){var x=schema.sections.splice(active,1)[0];schema.sections.splice(active-1,0,x);active--}else if(e.target.classList.contains('section-down')&&active<schema.sections.length-1){var x=schema.sections.splice(active,1)[0];schema.sections.splice(active+1,0,x);active++}else if(e.target.classList.contains('duplicate-section')){var x=JSON.parse(JSON.stringify(schema.sections[active]));x.key=key();x.fields.forEach(function(f){f.key=key()});schema.sections.splice(active+1,0,x);active++}else{return}render()};
		sections.onchange=function(e){if(e.target.matches('select[data-config],input[type=checkbox][data-config],[data-file-group],[data-duration-unit]')){sync();render()}};
		sections.addEventListener('keydown',function(e){
			if(e.key!=='Enter')return;
			var editor=e.target.closest('.ggm-richtext-input--inline');
			if(!editor)return;
			e.preventDefault();
			var sel=window.getSelection();
			if(!sel.rangeCount)return;
			var range=sel.getRangeAt(0);
			range.deleteContents();
			var br=document.createElement('br');
			range.insertNode(br);
			range.setStartAfter(br);
			range.collapse(true);
			sel.removeAllRanges();
			sel.addRange(range);
			editor.dispatchEvent(new Event('input',{bubbles:true}));
		});
		sections.oninput=function(e){if(!e.target.matches('[data-config],.ggm-config-item input,.ggm-config-item [data-richtext],.ggm-chip-options-input'))return;var box=e.target.closest('.ggm-builder-section'),d=e.target.closest('.ggm-builder-field');if(!box||!d)return;active=Number(box.dataset.index);sync();var field=schema.sections[active].fields[Number(d.dataset.index)],old=d.querySelector('.ggm-inline-field-preview');if(old)old.outerHTML=preview(field);if(e.target.classList.contains('ggm-chip-options-input')){var optionPreview=d.querySelector('.ggm-chip-admin-options>div');if(optionPreview)optionPreview.innerHTML=(field.options||[]).map(function(o){return '<span>'+esc(o.label)+'</span>'}).join('')}};
		var draggedItem=null;sections.ondragstart=function(e){if(!e.target.classList.contains('ggm-item-symbol'))return;var box=e.target.closest('.ggm-builder-section'),d=e.target.closest('.ggm-builder-field'),item=e.target.closest('.ggm-config-item'),list=item.closest('.ggm-item-list');sync();draggedItem={section:Number(box.dataset.index),field:Number(d.dataset.index),prop:list.dataset.prop,index:[].indexOf.call(list.children,item)};e.dataTransfer.effectAllowed='move'};sections.ondragover=function(e){if(draggedItem&&e.target.closest('.ggm-config-item'))e.preventDefault()};sections.ondrop=function(e){var target=e.target.closest('.ggm-config-item');if(!draggedItem||!target)return;var box=target.closest('.ggm-builder-section'),d=target.closest('.ggm-builder-field'),list=target.closest('.ggm-item-list'),targetIndex=[].indexOf.call(list.children,target);if(Number(box.dataset.index)===draggedItem.section&&Number(d.dataset.index)===draggedItem.field&&list.dataset.prop===draggedItem.prop){var items=schema.sections[draggedItem.section].fields[draggedItem.field][draggedItem.prop],moved=items.splice(draggedItem.index,1)[0];items.splice(targetIndex,0,moved);active=draggedItem.section;render()}draggedItem=null};
		var tabList=document.querySelector('.ggm-builder-nav[role="tablist"]'),tabButtons=[].slice.call(document.querySelectorAll('.ggm-builder-nav [role="tab"]')),tabPanels=[].slice.call(document.querySelectorAll('.ggm-builder-tab-panel')),tabMedia=window.matchMedia('(max-width: 782px)');
		function updateTabOrientation(){if(tabList)tabList.setAttribute('aria-orientation',tabMedia.matches?'horizontal':'vertical')}
		updateTabOrientation();if(tabMedia.addEventListener)tabMedia.addEventListener('change',updateTabOrientation);else if(tabMedia.addListener)tabMedia.addListener(updateTabOrientation);
		function reviewText(name,value){var node=form.querySelector('[data-review="'+name+'"]');if(node)node.textContent=value}
		function updateReview(){
			sync();
			var fieldCount=schema.sections.reduce(function(total,section){return total+(section.fields||[]).length},0),status=form.querySelector('[name="form_status"]'),placements=[];
			form.querySelectorAll('[name="locations[]"]:checked').forEach(function(input){var label=input.closest('.ggm-placement-card');placements.push(label&&label.querySelector('strong')?label.querySelector('strong').textContent.trim():input.value)});
			[['workshop_ids[]','specific workshop'],['course_ids[]','specific course']].forEach(function(item){var select=form.querySelector('[name="'+item[0]+'"]'),count=select?[].slice.call(select.options).filter(function(option){return option.selected}).length:0;if(count)placements.push(count+' '+item[1]+(count===1?'':'s'))});
			reviewText('title',document.getElementById('form_title').value.trim()||'Untitled form');
			reviewText('status',status?status.options[status.selectedIndex].text:'Draft');
			reviewText('structure',schema.sections.length+' section'+(schema.sections.length===1?'':'s')+' · '+fieldCount+' field'+(fieldCount===1?'':'s'));
			reviewText('placement',placements.length?placements.join(', '):'Not assigned');
		}
		function syncRichEditors(){if(window.tinyMCE&&window.tinyMCE.triggerSave)window.tinyMCE.triggerSave();}
		function refreshRichEditors(){if(!window.tinyMCE)return;['form_confirmation','form_payment_success'].forEach(function(id){var ed=window.tinyMCE.get(id);if(ed&&ed.execCommand)ed.execCommand('mceRepaint');});}
		function activateBuilderPanel(panelId,focusTab){
			var selectedTab=tabButtons.find(function(tab){return tab.getAttribute('aria-controls')===panelId});
			if(!selectedTab)return;
			tabButtons.forEach(function(tab){var selected=tab===selectedTab;tab.classList.toggle('is-active',selected);tab.setAttribute('aria-selected',selected?'true':'false');tab.tabIndex=selected?0:-1});
			tabPanels.forEach(function(panel){var selected=panel.id===panelId;panel.hidden=!selected;panel.classList.toggle('is-active',selected)});
			if(panelId==='ggm-settings')window.setTimeout(refreshRichEditors,0);
			if(panelId==='ggm-review')updateReview();
			if(focusTab)selectedTab.focus();
		}
		tabButtons.forEach(function(tab,index){
			tab.addEventListener('click',function(){activateBuilderPanel(tab.getAttribute('aria-controls'),false)});
			tab.addEventListener('keydown',function(event){var next=index,orientation=tabList?tabList.getAttribute('aria-orientation'):'horizontal';if((orientation==='horizontal'&&event.key==='ArrowRight')||(orientation==='vertical'&&event.key==='ArrowDown'))next=(index+1)%tabButtons.length;else if((orientation==='horizontal'&&event.key==='ArrowLeft')||(orientation==='vertical'&&event.key==='ArrowUp'))next=(index-1+tabButtons.length)%tabButtons.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=tabButtons.length-1;else return;event.preventDefault();activateBuilderPanel(tabButtons[next].getAttribute('aria-controls'),true)});
		});
		form.addEventListener('invalid',function(event){var panel=event.target.closest('.ggm-builder-tab-panel');if(panel&&panel.hidden)activateBuilderPanel(panel.id,false)},true);
		var builderDirty=false;form.addEventListener('input',function(){builderDirty=true;clearBuilderError()});form.addEventListener('change',function(){builderDirty=true;clearBuilderError()});form.addEventListener('click',function(event){if(event.target.closest('.ggm-add-field,#ggm-add-section,#ggm-sections button')){builderDirty=true;clearBuilderError()}});sections.addEventListener('drop',function(){builderDirty=true;clearBuilderError()});
		document.querySelectorAll('.ggm-discard-builder-link').forEach(function(link){link.addEventListener('click',function(event){if(builderDirty&&!window.confirm(builderMessages.discard))event.preventDefault()})});
		document.querySelector('.ggm-save-draft').addEventListener('click',function(){form.querySelector('[name=form_status]').value='draft'});
		document.getElementById('ggm-preview-form').addEventListener('click',function(){
			sync();
			if(!schemaHasContent()){showBuilderError(builderMessages.empty_preview,'ggm-fields');return}
			var button=this,preview=document.createElement('form');
			preview.method='post';
			preview.action=<?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;
			preview.target='_blank';
			preview.style.display='none';
			function field(name,value){var input=document.createElement('input');input.type='hidden';input.name=name;input.value=value||'';preview.appendChild(input)}
			field('action','ggm_preview_form');
			field('preview_nonce',button.dataset.previewNonce);
			field('schema_json',JSON.stringify(schema));
			field('form_title',document.getElementById('form_title').value);
			field('form_description',document.querySelector('[name="form_description"]').value);
			field('form_heading_tag',document.querySelector('[name="form_heading_tag"]').value);
			field('form_heading_size',document.querySelector('[name="form_heading_size"]').value);
			document.body.appendChild(preview);
			preview.submit();
			preview.remove();
		});
		form.onsubmit=function(event){syncRichEditors();sync();if(!schemaHasContent()){event.preventDefault();showBuilderError(builderMessages.empty_save,'ggm-fields');return false}document.getElementById('ggm-schema-json').value=JSON.stringify(schema);builderDirty=false};
		render()})();
		</script>
	<?php endif; ?>
</div>
