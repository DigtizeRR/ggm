<?php
/**
 * Admin view — Workshop / Course Import & Export.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ggm_import_flash_key = 'ggm_import_result_' . get_current_user_id();
$ggm_import_flash     = get_transient( $ggm_import_flash_key );
if ( $ggm_import_flash ) {
	delete_transient( $ggm_import_flash_key );
}
$ggm_pending_workshop_package = get_transient( 'ggm_workshop_package_pending_' . get_current_user_id() );

$export_workshops_url = wp_nonce_url( admin_url( 'admin-post.php?action=ggm_export_workshops' ), 'ggm_export_workshops' );
$export_courses_url   = wp_nonce_url( admin_url( 'admin-post.php?action=ggm_export_courses' ), 'ggm_export_courses' );
$member_sample_url    = wp_nonce_url( admin_url( 'admin-post.php?action=ggm_member_csv_sample' ), 'ggm_member_csv_sample' );
$member_import_nonce  = wp_create_nonce( 'ggm_member_import_batches' );
$member_import_logs   = GGM_Admin::get_member_import_logs();
$legacy_result_key    = 'ggm_legacy_access_import_' . get_current_user_id();
$legacy_result        = get_transient( $legacy_result_key );
if ( false !== $legacy_result ) { delete_transient( $legacy_result_key ); }
$legacy_workshops = get_posts( array( 'post_type'=>array( 'workshop','ggm_workshop' ), 'post_status'=>'publish', 'posts_per_page'=>-1, 'orderby'=>'title', 'order'=>'ASC' ) );
$legacy_sample_url = wp_nonce_url( admin_url( 'admin-post.php?action=ggm_legacy_enrollment_sample' ), 'ggm_legacy_enrollment_sample' );
$single_workshops = get_posts( array( 'post_type' => array( 'workshop', 'ggm_workshop' ), 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
?>
<div class="wrap ggm-admin-wrap">
	<h1 class="ggm-admin-title"><?php esc_html_e( 'Import / Export', 'ggm-member-dashboard' ); ?></h1>
	<p class="ggm-admin-subtitle">
		<?php esc_html_e( 'Export or import Workshops and Courses, and bulk-import members as WordPress users from CSV.', 'ggm-member-dashboard' ); ?>
	</p>

	<?php if ( $ggm_import_flash ) : ?>
		<?php if ( 'error' === ( $ggm_import_flash['type'] ?? '' ) ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $ggm_import_flash['message'] ?? __( 'Import failed.', 'ggm-member-dashboard' ) ); ?></p>
			</div>
		<?php else :
			$ggm_summary = $ggm_import_flash['summary'] ?? array();
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: "workshops" or "courses", 2: created count, 3: updated count, 4: skipped count */
						esc_html__( 'Import finished for %1$s — %2$d created, %3$d updated, %4$d skipped.', 'ggm-member-dashboard' ),
						esc_html( $ggm_import_flash['kind'] ?? '' ),
						(int) ( $ggm_summary['created'] ?? 0 ),
						(int) ( $ggm_summary['updated'] ?? 0 ),
						(int) ( $ggm_summary['skipped'] ?? 0 )
					);
					?>
				</p>
				<?php if ( ! empty( $ggm_summary['messages'] ) ) : ?>
					<ul style="margin:0 0 12px 20px; list-style:disc;">
						<?php foreach ( $ggm_summary['messages'] as $ggm_msg ) : ?>
							<li><?php echo esc_html( $ggm_msg ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( is_array( $ggm_pending_workshop_package ) ) : ?>
		<div class="notice notice-warning"><p><?php printf( esc_html__( 'A Workshop named “%s” already exists. Do you want to update it with this imported package?', 'ggm-member-dashboard' ), esc_html( $ggm_pending_workshop_package['title'] ?? '' ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ggm_confirm_import_workshop_package"><?php wp_nonce_field( 'ggm_confirm_import_workshop_package' ); ?><button class="button button-primary" name="decision" value="update" type="submit"><?php esc_html_e( 'Update Existing Workshop', 'ggm-member-dashboard' ); ?></button> <button class="button" name="decision" value="cancel" type="submit"><?php esc_html_e( 'Cancel Import', 'ggm-member-dashboard' ); ?></button></form></div>
	<?php endif; ?>

	<div class="ggm-dashboard-card" style="margin-bottom:20px;">
		<div class="card-body">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Export', 'ggm-member-dashboard' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Downloads a JSON file with every workshop or course: its fields, content-block repeaters (You Will Discover, Why Different, Perfect For You, FAQ), and — for workshops, time slots; for courses, all lessons.', 'ggm-member-dashboard' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $export_workshops_url ); ?>" class="button button-primary"><?php esc_html_e( 'Export Workshops', 'ggm-member-dashboard' ); ?></a>
				<a href="<?php echo esc_url( $export_courses_url ); ?>" class="button button-primary"><?php esc_html_e( 'Export Courses', 'ggm-member-dashboard' ); ?></a>
			</p>
		</div>
	</div>

	<div class="ggm-dashboard-card" style="margin-bottom:20px;">
		<div class="card-body">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Export One Workshop Package', 'ggm-member-dashboard' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Choose one Workshop to download a portable ZIP containing all of its saved fields, repeaters, time slots, terms, featured image, and locally hosted Media Library files referenced by the Workshop.', 'ggm-member-dashboard' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ggm_export_workshop_package">
				<?php wp_nonce_field( 'ggm_export_workshop_package' ); ?>
				<select name="workshop_id" required><option value=""><?php esc_html_e( 'Select a Workshop…', 'ggm-member-dashboard' ); ?></option><?php foreach ( $single_workshops as $single_workshop ) : ?><option value="<?php echo esc_attr( $single_workshop->ID ); ?>"><?php echo esc_html( $single_workshop->post_title ); ?></option><?php endforeach; ?></select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Export Selected Workshop ZIP', 'ggm-member-dashboard' ); ?></button>
			</form>
		</div>
	</div>

	<div class="ggm-dashboard-card" style="margin-bottom:20px;">
		<div class="card-body">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Import One Workshop Package', 'ggm-member-dashboard' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Upload a ZIP made by Export One Workshop Package. It always creates a new Workshop and imports its bundled media into this Media Library.', 'ggm-member-dashboard' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ggm_import_workshop_package"><?php wp_nonce_field( 'ggm_import_workshop_package' ); ?><input type="file" name="workshop_package" accept="application/zip,.zip" required> <button type="submit" class="button button-primary"><?php esc_html_e( 'Import Workshop ZIP', 'ggm-member-dashboard' ); ?></button></form>
		</div>
	</div>

	<div class="ggm-dashboard-card" style="margin-bottom:20px;">
		<div class="card-body">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Import Workshops', 'ggm-member-dashboard' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload a Workshops export JSON file. A workshop whose title exactly matches an existing one is updated in place (its time slots are replaced); otherwise a new workshop is created. Required Membership and Mentor are matched by name — make sure they already exist here first, or they\'ll be left unset.', 'ggm-member-dashboard' ); ?>
			</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ggm_import_workshops">
				<?php wp_nonce_field( 'ggm_import_workshops' ); ?>
				<input type="file" name="import_file" accept="application/json,.json" required>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Import Workshops', 'ggm-member-dashboard' ); ?></button>
			</form>
		</div>
	</div>

	<div class="ggm-dashboard-card" style="margin-bottom:20px;">
		<div class="card-body">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Import Courses', 'ggm-member-dashboard' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload a Courses export JSON file. A course whose title exactly matches an existing one is updated in place, with its lessons matched by title (updated) or created; otherwise a new course and its lessons are created.', 'ggm-member-dashboard' ); ?>
			</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ggm_import_courses">
				<?php wp_nonce_field( 'ggm_import_courses' ); ?>
				<input type="file" name="import_file" accept="application/json,.json" required>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Import Courses', 'ggm-member-dashboard' ); ?></button>
			</form>
		</div>
	</div>

	<div class="ggm-dashboard-card">
		<div class="card-body" id="ggm-legacy-enrollment-import">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Import Old Workshop Purchasers', 'ggm-member-dashboard' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Upload purchasers from your old platform. Existing customers are matched by email or phone; missing customers are created. Each person receives the selected workshop and the course currently linked inside that workshop. The linked course expiry is calculated from today using that course’s Access duration setting.', 'ggm-member-dashboard' ); ?></p>
			<?php if ( is_array( $legacy_result ) ) : ?>
				<?php if ( ! empty( $legacy_result['error'] ) ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $legacy_result['error'] ); ?></p></div>
				<?php else : ?><div class="notice notice-success inline"><p><?php printf( esc_html__( 'Import complete: %1$d rows processed, %2$d accounts created, %3$d existing accounts matched, %4$d enrolled, %5$d skipped.', 'ggm-member-dashboard' ), (int)$legacy_result['processed'], (int)$legacy_result['created'], (int)$legacy_result['matched'], (int)$legacy_result['enrolled'], (int)$legacy_result['skipped'] ); ?></p>
				<?php if ( ! empty( $legacy_result['messages'] ) ) : ?><details><summary><?php esc_html_e( 'View skipped rows', 'ggm-member-dashboard' ); ?></summary><ol><?php foreach ( $legacy_result['messages'] as $message ) : ?><li><?php echo esc_html( $message ); ?></li><?php endforeach; ?></ol></details><?php endif; ?></div><?php endif; ?>
			<?php endif; ?>
			<p><a class="button" href="<?php echo esc_url( $legacy_sample_url ); ?>"><?php esc_html_e( 'Download CSV Template', 'ggm-member-dashboard' ); ?></a></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ggm_import_legacy_enrollments">
				<?php wp_nonce_field( 'ggm_import_legacy_enrollments' ); ?>
				<table class="form-table"><tr><th><label for="ggm-legacy-workshop"><?php esc_html_e( 'Workshop', 'ggm-member-dashboard' ); ?></label></th><td>
					<select id="ggm-legacy-workshop" name="workshop_id" required><option value=""><?php esc_html_e( 'Select workshop…', 'ggm-member-dashboard' ); ?></option>
					<?php foreach ( $legacy_workshops as $workshop ) : $linked = class_exists( 'GGM_Workshop' ) ? GGM_Workshop::get_linked_course( $workshop->ID ) : null; ?>
						<option value="<?php echo esc_attr( $workshop->ID ); ?>" data-course="<?php echo esc_attr( $linked ? $linked->post_title : '' ); ?>" <?php disabled( ! $linked ); ?>><?php echo esc_html( $workshop->post_title . ( $linked ? '' : ' — No linked course' ) ); ?></option>
					<?php endforeach; ?></select>
					<p class="description" id="ggm-legacy-linked-course"><?php esc_html_e( 'Select a workshop to see its linked course.', 'ggm-member-dashboard' ); ?></p>
				</td></tr><tr><th><label for="ggm-legacy-file"><?php esc_html_e( 'Purchasers CSV', 'ggm-member-dashboard' ); ?></label></th><td><input id="ggm-legacy-file" type="file" name="legacy_members_file" accept="text/csv,.csv" required><p class="description"><?php esc_html_e( 'Supported identity columns: email and phone/mobile/mobile_no. Name/full_name is optional.', 'ggm-member-dashboard' ); ?></p></td></tr></table>
				<button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Import these purchasers and assign access now?', 'ggm-member-dashboard' ) ); ?>');"><?php esc_html_e( 'Import and Assign Access', 'ggm-member-dashboard' ); ?></button>
			</form>
		</div>
	</div>

	<div class="ggm-dashboard-card" style="margin-top:20px;">
		<div class="card-body">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Import Members', 'ggm-member-dashboard' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload a UTF-8 CSV using any of the supported columns below, in any order. Missing columns and blank optional values are allowed; every available value is imported without erasing existing profile data. Each row needs at least a valid email or 10-digit mobile number. Every imported member becomes a WooCommerce Customer, and existing users are safely matched by email or mobile number.', 'ggm-member-dashboard' ); ?>
			</p>
			<p><code style="white-space:normal;word-break:break-word;">full_name,mobile_no,email,full_address,pincode,gender,age,weight,bp,glucose_level,diseases,payment_details,declaration</code></p>
			<p>
				<a href="<?php echo esc_url( $member_sample_url ); ?>" class="button"><?php esc_html_e( 'Download CSV Template', 'ggm-member-dashboard' ); ?></a>
			</p>
			<form id="ggm-member-import-form" enctype="multipart/form-data">
				<input type="file" name="members_file" accept="text/csv,.csv" required>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Import Members', 'ggm-member-dashboard' ); ?></button>
			</form>
			<div id="ggm-member-import-progress" style="display:none;margin-top:18px;max-width:760px;" aria-live="polite">
				<div style="height:18px;border-radius:20px;overflow:hidden;background:#e5e7eb;"><div class="bar" style="width:0;height:100%;background:linear-gradient(90deg,#3157ed,#16a6a1);transition:width .25s;"></div></div>
				<p class="status" style="margin:8px 0 0;font-weight:600;"></p>
				<p class="counts" style="margin:4px 0 0;color:#555;"></p>
			</div>
			<p class="description" style="margin-top:12px;">
				<?php esc_html_e( 'Contacts are processed in batches of 100. You may resume an interrupted import from the log below. Up to 500 row errors are retained per import.', 'ggm-member-dashboard' ); ?>
			</p>
		</div>
	</div>

	<div class="ggm-dashboard-card" style="margin-top:20px;">
		<div class="card-body">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Member Import Logs', 'ggm-member-dashboard' ); ?></h2>
			<?php if ( ! $member_import_logs ) : ?><p class="description"><?php esc_html_e( 'No tracked member imports yet.', 'ggm-member-dashboard' ); ?></p>
			<?php else : ?><div style="overflow-x:auto;"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Started', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'File', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Progress', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Results', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Details', 'ggm-member-dashboard' ); ?></th></tr></thead><tbody>
			<?php foreach ( $member_import_logs as $log ) : ?><tr><td><?php echo esc_html( $log['started_at'] ); ?></td><td><?php echo esc_html( $log['file'] ); ?></td><td><strong><?php echo esc_html( ucfirst( $log['status'] ) ); ?></strong><?php if ( in_array( $log['status'], array( 'pending','processing','failed' ), true ) ) : ?><br><button type="button" class="button-link ggm-resume-import" data-id="<?php echo esc_attr( $log['id'] ); ?>"><?php esc_html_e( 'Resume', 'ggm-member-dashboard' ); ?></button><?php endif; ?></td><td><?php echo esc_html( $log['processed'] . ' / ' . $log['total'] . ' (' . $log['percent'] . '%)' ); ?></td><td><?php echo esc_html( sprintf( '%d created, %d updated, %d skipped', $log['created'], $log['updated'], $log['skipped'] ) ); ?></td><td><?php if ( $log['messages'] ) : ?><details><summary><?php echo esc_html( sprintf( __( '%d log entries', 'ggm-member-dashboard' ), count( $log['messages'] ) ) ); ?></summary><ol style="min-width:320px;max-height:240px;overflow:auto;"><?php foreach ( $log['messages'] as $message ) : ?><li><?php echo esc_html( $message ); ?></li><?php endforeach; ?></ol></details><?php else : ?>—<?php endif; ?></td></tr><?php endforeach; ?>
			</tbody></table></div><?php endif; ?>
		</div>
	</div>
</div>
<script>
(function(){
	var legacySelect=document.getElementById('ggm-legacy-workshop'), linkedLabel=document.getElementById('ggm-legacy-linked-course');
	if(legacySelect){legacySelect.addEventListener('change',function(){var option=this.options[this.selectedIndex], course=option?option.dataset.course:'';linkedLabel.textContent=course?'Linked course: '+course:'Warning: this workshop has no linked course. Only workshop access will be assigned.';linkedLabel.style.color=course?'#166534':'#b32d2e';});}
	var form=document.getElementById('ggm-member-import-form'), box=document.getElementById('ggm-member-import-progress'), bar=box.querySelector('.bar'), status=box.querySelector('.status'), counts=box.querySelector('.counts'), button=form.querySelector('button');
	var ajaxUrl=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce=<?php echo wp_json_encode( $member_import_nonce ); ?>;
	function show(s){ box.style.display='block'; bar.style.width=(s.percent||0)+'%'; status.textContent=(s.status==='complete'?'Import complete':'Importing contacts…')+' '+(s.percent||0)+'%'; counts.textContent=(s.processed||0)+' of '+(s.total||0)+' processed — '+(s.created||0)+' created, '+(s.updated||0)+' updated, '+(s.skipped||0)+' skipped.'; }
	function fail(message){ status.textContent=message||'Import failed.'; status.style.color='#b32d2e'; button.disabled=false; button.textContent='Import Members'; }
	function process(id){ var data=new URLSearchParams({action:'ggm_process_member_import',nonce:nonce,import_id:id}); fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:data.toString()}).then(function(r){return r.json();}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Import batch failed.');show(r.data);if(r.data.status==='complete'){button.textContent='Completed';setTimeout(function(){location.reload();},900);}else{process(id);}}).catch(function(e){fail(e.message+' You can resume this import from the log.');}); }
	form.addEventListener('submit',function(e){e.preventDefault();button.disabled=true;button.textContent='Uploading…';status.style.color='';box.style.display='block';bar.style.width='0';status.textContent='Uploading CSV…';counts.textContent='';var data=new FormData(form);data.append('action','ggm_start_member_import');data.append('nonce',nonce);var xhr=new XMLHttpRequest();xhr.open('POST',ajaxUrl);xhr.upload.onprogress=function(e){if(e.lengthComputable){bar.style.width=Math.round(e.loaded/e.total*100)+'%';}};xhr.onload=function(){try{var r=JSON.parse(xhr.responseText);if(!r.success)throw new Error((r.data&&r.data.message)||'Upload failed.');button.textContent='Importing…';show(r.data);process(r.data.id);}catch(e){fail(e.message);}};xhr.onerror=function(){fail('Upload failed. Please try again.');};xhr.send(data);});
	document.querySelectorAll('.ggm-resume-import').forEach(function(el){el.addEventListener('click',function(){button.disabled=true;button.textContent='Importing…';status.style.color='';box.style.display='block';status.textContent='Resuming import…';process(el.dataset.id);});});
})();
</script>
