<div class="ggm-health-gate" role="dialog" aria-modal="true" aria-labelledby="ggm-health-title">
	<div class="ggm-health-card">
		<div class="ggm-health-head">
			<form class="ggm-health-skip-form" method="post">
				<input type="hidden" name="ggm_health_action" value="skip">
				<input type="hidden" name="workshop_id" value="<?php echo esc_attr( $workshop_id ); ?>">
				<?php wp_nonce_field( 'ggm_skip_health_intake', 'ggm_health_skip_nonce' ); ?>
				<button type="submit" class="ggm-health-skip"><?php esc_html_e( 'Skip', 'ggm-member-dashboard' ); ?></button>
			</form>
			<h2 id="ggm-health-title"><?php esc_html_e( 'Workshop Health Information Form', 'ggm-member-dashboard' ); ?></h2>
			<p><?php printf( esc_html__( 'Please complete this form for “%s” before continuing to your dashboard.', 'ggm-member-dashboard' ), esc_html( get_the_title( $workshop_id ) ) ); ?></p>
		</div>
		<form id="ggm-health-form" enctype="multipart/form-data">
			<input type="hidden" name="action" value="ggm_save_health_intake"><input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'ggm_nonce' ) ); ?>"><input type="hidden" name="workshop_id" value="<?php echo esc_attr( $workshop_id ); ?>">
			<div class="ggm-health-grid">
				<label><span><?php esc_html_e( 'Full Name *', 'ggm-member-dashboard' ); ?></span><input name="full_name" value="<?php echo esc_attr( $user->display_name ); ?>" required></label>
				<label><span><?php esc_html_e( 'WhatsApp Mobile Number *', 'ggm-member-dashboard' ); ?></span><div class="ggm-global-phone-control"><?php echo class_exists( 'GGM_Form_Builder' ) ? GGM_Form_Builder::country_picker_html( 'country_code', 'ggm-health-country-code', get_user_meta( $user->ID, 'ggm_whatsapp_country_code', true ) ?: '+91' ) : '<input type="hidden" name="country_code" value="+91">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="tel" name="phone" value="<?php echo esc_attr( $phone ); ?>" required inputmode="tel" autocomplete="tel-national"></div></label>
				<label><span><?php esc_html_e( 'Gender *', 'ggm-member-dashboard' ); ?></span><select name="gender" required><option value=""><?php esc_html_e( 'Select', 'ggm-member-dashboard' ); ?></option><option>Male</option><option>Female</option><option>Other</option><option>Prefer not to say</option></select></label>
				<label><span><?php esc_html_e( 'Date of Birth / Approx. Age *', 'ggm-member-dashboard' ); ?></span><input name="dob_age" required></label>
				<label><span><?php esc_html_e( 'Email *', 'ggm-member-dashboard' ); ?></span><input type="email" name="email" value="<?php echo esc_attr( $form_email ); ?>" autocomplete="email" required></label>
				<label><span><?php esc_html_e( 'City *', 'ggm-member-dashboard' ); ?></span><input name="city" required></label>
				<label class="ggm-health-wide"><span><?php esc_html_e( 'Full Address *', 'ggm-member-dashboard' ); ?></span><textarea name="address" required></textarea></label>
				<label><span><?php esc_html_e( 'State *', 'ggm-member-dashboard' ); ?></span><input name="state" required></label>
				<label><span><?php esc_html_e( 'Weight', 'ggm-member-dashboard' ); ?></span><input name="weight"></label>
				<label><span><?php esc_html_e( 'Blood Pressure', 'ggm-member-dashboard' ); ?></span><input name="bp"></label>
				<label><span><?php esc_html_e( 'Sugar / Glucose Level', 'ggm-member-dashboard' ); ?></span><input name="sugar_level"></label>
				<label class="ggm-health-wide"><span><?php esc_html_e( 'List up to 3 diseases or ailments you want to heal naturally *', 'ggm-member-dashboard' ); ?></span><textarea name="diseases" required placeholder="1)&#10;2)&#10;3)"></textarea></label>
				<label class="ggm-health-wide"><span><?php esc_html_e( 'Mention diseases (one per line)', 'ggm-member-dashboard' ); ?></span><textarea name="mentioned_diseases" placeholder="1)&#10;2)&#10;3)"></textarea></label>
				<label class="ggm-health-wide"><span><?php esc_html_e( 'Medical reports (PDF, JPG or PNG; maximum 10 MB)', 'ggm-member-dashboard' ); ?></span><input type="file" name="medical_report" accept=".pdf,.jpg,.jpeg,.png"></label>
				<label class="ggm-health-wide"><span><?php esc_html_e( 'Additional Note', 'ggm-member-dashboard' ); ?></span><textarea name="note"></textarea></label>
			</div>
			<label class="ggm-health-consent"><input type="checkbox" name="declaration" value="1" required> <span><?php esc_html_e( 'I confirm that the information is complete and correct. I understand the workshop is educational, does not replace professional medical care, and I remain responsible for consulting a qualified physician for medical conditions.', 'ggm-member-dashboard' ); ?></span></label>
			<div id="ggm-health-feedback" aria-live="polite"></div><button type="submit" class="ggm-health-submit"><?php esc_html_e( 'Submit Health Form', 'ggm-member-dashboard' ); ?></button>
		</form>
	</div>
</div>
<script>(function(){var f=document.getElementById('ggm-health-form'),b=f.querySelector('.ggm-health-submit'),m=document.getElementById('ggm-health-feedback');f.addEventListener('submit',function(e){e.preventDefault();b.disabled=true;b.textContent='Submitting…';m.textContent='';fetch(ggm_public.ajax_url,{method:'POST',credentials:'same-origin',body:new FormData(f)}).then(function(r){return r.json();}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Submission failed.');m.className='success';m.textContent=r.data.message;setTimeout(function(){window.location.reload();},700);}).catch(function(e){m.className='error';m.textContent=e.message;b.disabled=false;b.textContent='Submit Health Form';});});})();</script>
