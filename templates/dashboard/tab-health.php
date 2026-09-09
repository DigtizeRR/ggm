<?php
/** State-driven Health Information dashboard tab. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$health_whatsapp_url = '';
if ( is_user_logged_in() && class_exists( 'GGM_Health_Intake' ) ) {
	foreach ( GGM_Health_Intake::workshops_for_user( get_current_user_id() ) as $health_workshop ) {
		$candidate = esc_url_raw( get_post_meta( (int) $health_workshop->workshop_id, 'ggm_workshop_whatsapp_group_url', true ) );
		if ( $candidate ) { $health_whatsapp_url = $candidate; break; }
	}
}
?>
<div class="ggm-section-header ggm-health-page-heading">
	<div><h2><?php esc_html_e( 'Health Information', 'ggm-member-dashboard' ); ?></h2><p><?php esc_html_e( 'Complete and manage your health registration and medical reports.', 'ggm-member-dashboard' ); ?></p></div>
	<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
</div>
<?php if ( class_exists( 'GGM_Form_Builder' ) ) : ?>
	<div class="ggm-health-dashboard-layout">
		<section class="ggm-health-panel ggm-health-registration-panel is-edit-panel-hidden" aria-labelledby="ggm-health-registration-title">
			<div class="ggm-health-panel-icon"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span></div>
			<div class="ggm-health-registration-main">
				<h3 id="ggm-health-registration-title"><?php esc_html_e( 'Health Registration', 'ggm-member-dashboard' ); ?></h3>
				<div class="ggm-dashboard-assigned-forms" data-popup-label="<?php esc_attr_e( 'Health information forms', 'ggm-member-dashboard' ); ?>" data-popup-close-label="<?php esc_attr_e( 'Close health form popup', 'ggm-member-dashboard' ); ?>" data-popup-continue-label="<?php esc_attr_e( 'Continue to next form', 'ggm-member-dashboard' ); ?>" data-popup-done-label="<?php esc_attr_e( 'Close', 'ggm-member-dashboard' ); ?>">
					<?php $published_form_count = GGM_Form_Builder::render_dashboard_forms(); ?>
				</div>
				<div class="ggm-health-primary-actions"><div class="ggm-health-additional-action-host"></div>
					<?php if ( $health_whatsapp_url ) : ?><a class="ggm-health-action-button is-outline" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $health_whatsapp_url ); ?>"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><?php esc_html_e( 'Join WhatsApp Group', 'ggm-member-dashboard' ); ?></a><?php endif; ?>
				</div>
			</div>
			<aside class="ggm-health-edit-panel" hidden>
				<div class="ggm-health-panel-icon is-small"><span class="dashicons dashicons-edit" aria-hidden="true"></span></div>
				<h4><?php esc_html_e( 'Need to update something?', 'ggm-member-dashboard' ); ?></h4>
				<p><?php esc_html_e( 'You can update your information before our team reviews it.', 'ggm-member-dashboard' ); ?></p>
				<div class="ggm-health-edit-action-host"></div>
			</aside>
		</section>
		<section class="ggm-health-panel ggm-health-reports-panel" aria-labelledby="ggm-health-reports-title">
			<div class="ggm-health-panel-icon"><span class="dashicons dashicons-upload" aria-hidden="true"></span></div>
			<div class="ggm-health-reports-copy"><h3 id="ggm-health-reports-title"><?php esc_html_e( 'Upload Medical Reports (Optional)', 'ggm-member-dashboard' ); ?></h3><p><?php esc_html_e( 'Upload any recent medical reports to help Dr. Dhiren better understand your health and offer more personalised guidance.', 'ggm-member-dashboard' ); ?></p>
				<button type="button" class="ggm-health-action-button ggm-health-upload-reports"><span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span><?php esc_html_e( 'Upload Reports', 'ggm-member-dashboard' ); ?></button><div class="ggm-health-report-form-host"></div>
			</div>
			<div class="ggm-health-report-art" aria-hidden="true"><span class="dashicons dashicons-media-document"></span><span class="dashicons dashicons-shield-alt"></span></div>
		</section>
		<aside class="ggm-health-support-panel ggm-health-uploaded-reports-panel" aria-labelledby="ggm-health-uploaded-title">
			<div class="ggm-health-panel-icon is-small"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span></div><h3 id="ggm-health-uploaded-title"><?php esc_html_e( 'Uploaded Reports', 'ggm-member-dashboard' ); ?></h3><p class="ggm-health-uploaded-intro"><?php esc_html_e( 'Your medical reports that are available for you to view will appear here.', 'ggm-member-dashboard' ); ?></p>
			<p class="ggm-health-uploaded-summary" hidden></p><button type="button" class="ggm-health-action-button ggm-health-view-reports" hidden><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e( 'View Reports', 'ggm-member-dashboard' ); ?></button>
		</aside>
		<section class="ggm-health-reports-library" hidden aria-labelledby="ggm-health-library-title">
			<div class="ggm-health-library-header"><div><button type="button" class="ggm-health-library-back"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e( 'Back to Health Information', 'ggm-member-dashboard' ); ?></button><h2 id="ggm-health-library-title"><?php esc_html_e( 'Your Medical Reports', 'ggm-member-dashboard' ); ?></h2><p><?php esc_html_e( 'Preview or download your uploaded medical reports.', 'ggm-member-dashboard' ); ?></p></div><button type="button" class="ggm-health-action-button ggm-health-upload-more" hidden><span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span><?php esc_html_e( 'Upload More Reports', 'ggm-member-dashboard' ); ?></button></div>
			<div class="ggm-health-uploaded-files-host"></div>
		</section>
	</div>
	<?php if ( ! $published_form_count ) : ?><div class="ggm-empty-state ggm-dashboard-form-empty"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><h3><?php esc_html_e( 'No published health form is available', 'ggm-member-dashboard' ); ?></h3><p><?php esc_html_e( 'A form will appear here after it is published and positioned on the Member Dashboard.', 'ggm-member-dashboard' ); ?></p></div><?php endif; ?>
	<script>
	(function(){var tab=document.getElementById('tab-health');if(!tab)return;var registration=tab.querySelector('.ggm-health-registration-panel'),assigned=registration&&registration.querySelector('.ggm-dashboard-assigned-forms'),complete=assigned&&assigned.querySelector('.ggm-form-complete'),title=tab.querySelector('#ggm-health-registration-title'),editHost=tab.querySelector('.ggm-health-edit-action-host'),additionalHost=tab.querySelector('.ggm-health-additional-action-host'),reportHost=tab.querySelector('.ggm-health-report-form-host'),uploadedHost=tab.querySelector('.ggm-health-uploaded-files-host'),library=tab.querySelector('.ggm-health-reports-library'),viewButton=tab.querySelector('.ggm-health-view-reports'),backButton=tab.querySelector('.ggm-health-library-back'),uploadMore=tab.querySelector('.ggm-health-upload-more'),summary=tab.querySelector('.ggm-health-uploaded-summary'),intro=tab.querySelector('.ggm-health-uploaded-intro'),heading=tab.querySelector('.ggm-health-page-heading');if(complete&&title)title.textContent=<?php echo wp_json_encode( __( 'Registration Complete', 'ggm-member-dashboard' ) ); ?>;if(!complete&&registration)registration.classList.add('is-registration-pending');var edit=complete&&complete.querySelector('a[href*="ggm_edit_submission"],button[disabled][aria-disabled="true"]');if(edit&&editHost){edit.classList.add('ggm-health-action-button');if(edit.tagName==='A')edit.textContent=<?php echo wp_json_encode( __( 'Edit My Details', 'ggm-member-dashboard' ) ); ?>;editHost.appendChild(edit);}else if(editHost&&editHost.closest('.ggm-health-edit-panel'))editHost.closest('.ggm-health-edit-panel').hidden=true;var postForm=complete&&complete.querySelector('.ggm-post-submit-form'),open=postForm&&postForm.querySelector('.ggm-post-submit-open');if(open&&additionalHost){open.classList.add('ggm-health-action-button');additionalHost.appendChild(open);}if(postForm&&reportHost)reportHost.appendChild(postForm);var files=complete&&complete.querySelector('.ggm-member-files');if(files&&uploadedHost)uploadedHost.appendChild(files);function updateLibrary(){var count=uploadedHost?uploadedHost.querySelectorAll('.ggm-member-file-card').length:0;if(summary){summary.textContent=count===1?<?php echo wp_json_encode( __( '1 medical report uploaded', 'ggm-member-dashboard' ) ); ?>:count+' '+<?php echo wp_json_encode( __( 'medical reports uploaded', 'ggm-member-dashboard' ) ); ?>;summary.hidden=!count;}if(intro)intro.hidden=!!count;if(viewButton)viewButton.hidden=!count;}updateLibrary();if(uploadedHost&&window.MutationObserver)new MutationObserver(updateLibrary).observe(uploadedHost,{childList:true,subtree:true});function showLibrary(show){[registration,tab.querySelector('.ggm-health-reports-panel'),tab.querySelector('.ggm-health-uploaded-reports-panel')].forEach(function(card){if(card)card.hidden=show;});if(heading)heading.hidden=show;if(library)library.hidden=!show;if(show){library.scrollIntoView({behavior:'smooth',block:'start'});var focus=library.querySelector('h2');if(focus){focus.setAttribute('tabindex','-1');focus.focus();}}}if(viewButton)viewButton.addEventListener('click',function(){showLibrary(true);});if(backButton)backButton.addEventListener('click',function(){showLibrary(false);viewButton.focus();});var uploadButton=tab.querySelector('.ggm-health-upload-reports');function openUpload(){showLibrary(false);var movedOpen=reportHost&&reportHost.querySelector('.ggm-post-submit-open');if(movedOpen)movedOpen.click();var target=reportHost&&reportHost.querySelector('.ggm-post-submit-fields');if(target)target.scrollIntoView({behavior:'smooth',block:'center'});}if(uploadButton)uploadButton.addEventListener('click',openUpload);if(postForm&&uploadMore){uploadMore.hidden=false;uploadMore.addEventListener('click',openUpload);}if(!postForm&&uploadButton){uploadButton.disabled=true;uploadButton.setAttribute('aria-disabled','true');}})();
	</script>
	<script>
	(function(){var tab=document.getElementById('tab-health');if(!tab)return;var host=tab.querySelector('.ggm-health-additional-action-host'),form=tab.querySelector('.ggm-health-report-form-host .ggm-post-submit-form'),moved=host&&host.querySelector('.ggm-post-submit-open'),disclosure=form&&form.querySelector('.ggm-post-submit-disclosure');if(!moved||!disclosure)return;disclosure.insertBefore(moved,disclosure.firstChild);var proxy=document.createElement('button');proxy.type='button';proxy.className='ggm-health-action-button';proxy.innerHTML='<span class="dashicons dashicons-edit" aria-hidden="true"></span>'+<?php echo wp_json_encode( __( 'Submit Additional Details', 'ggm-member-dashboard' ) ); ?>;proxy.addEventListener('click',function(){moved.click();var fields=form.querySelector('.ggm-post-submit-fields');if(fields)fields.scrollIntoView({behavior:'smooth',block:'center'});});host.appendChild(proxy);})();
	</script>
	<script>(function(){var tab=document.getElementById('tab-health'),host=tab&&tab.querySelector('.ggm-health-additional-action-host');if(host)host.remove();})();</script>
<?php else : ?><div class="ggm-empty-state ggm-dashboard-form-empty"><h3><?php esc_html_e( 'Health forms are temporarily unavailable', 'ggm-member-dashboard' ); ?></h3></div><?php endif; ?>
