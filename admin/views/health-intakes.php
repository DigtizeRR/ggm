<?php if ( ! defined( 'ABSPATH' ) ) { exit; } $rules = get_option( GGM_Health_Intake::RULES_OPTION, array() ); if ( ! $rules ) { $rules = array( array() ); } ?>
<div class="wrap"><h1><?php esc_html_e( 'Health Intakes & Disease Automation', 'ggm-member-dashboard' ); ?></h1>
<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Health automation and webhook settings saved.', 'ggm-member-dashboard' ); ?></p></div><?php endif; ?>
<h2><?php esc_html_e( 'Disease Message Rules', 'ggm-member-dashboard' ); ?></h2><p><?php esc_html_e( 'A rule runs when any comma-separated keyword appears in either disease answer. Use {name}, {workshop}, and {diseases} in messages. Only enter content approved by your medical team.', 'ggm-member-dashboard' ); ?></p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="ggm_save_health_rules"><?php wp_nonce_field( 'ggm_save_health_rules' ); ?>
	<div id="ggm-rule-list">
	<?php foreach ( $rules as $i => $rule ) :
		$email_attachment_id = absint( $rule['email_attachment_id'] ?? 0 );
		$whatsapp_attachment_id = absint( $rule['whatsapp_attachment_id'] ?? 0 );
		$email_attachment_name = $email_attachment_id ? basename( (string) get_attached_file( $email_attachment_id ) ) : '';
		$whatsapp_attachment_name = $whatsapp_attachment_id ? basename( (string) get_attached_file( $whatsapp_attachment_id ) ) : '';
		?>
		<div class="postbox ggm-health-rule" style="padding:16px">
			<p><label><strong>Keywords</strong><br><input class="widefat" name="rules[<?php echo esc_attr( $i ); ?>][keywords]" value="<?php echo esc_attr( $rule['keywords'] ?? '' ); ?>" placeholder="diabetes, sugar, glucose"></label></p>
			<p><label><strong>Email subject</strong><br><input class="widefat" name="rules[<?php echo esc_attr( $i ); ?>][email_subject]" value="<?php echo esc_attr( $rule['email_subject'] ?? '' ); ?>"></label></p>
			<p><label><strong>Email body</strong><br><textarea class="widefat" rows="5" name="rules[<?php echo esc_attr( $i ); ?>][email_body]"><?php echo esc_textarea( $rule['email_body'] ?? '' ); ?></textarea></label></p>
			<p class="ggm-attachment-field"><strong>Email attachment</strong><br><input type="hidden" name="rules[<?php echo esc_attr( $i ); ?>][email_attachment_id]" value="<?php echo esc_attr( $email_attachment_id ); ?>"><button type="button" class="button ggm-choose-attachment">Choose file</button> <button type="button" class="button-link-delete ggm-remove-attachment" <?php echo $email_attachment_id ? '' : 'style="display:none"'; ?>>Remove</button> <span class="ggm-attachment-name"><?php echo esc_html( $email_attachment_name ?: 'No file selected' ); ?></span></p>
			<p><label><strong>WhatsApp message</strong><br><textarea class="widefat" rows="4" name="rules[<?php echo esc_attr( $i ); ?>][whatsapp]"><?php echo esc_textarea( $rule['whatsapp'] ?? '' ); ?></textarea></label></p>
			<p class="ggm-attachment-field"><strong>WhatsApp file link</strong><br><input type="hidden" name="rules[<?php echo esc_attr( $i ); ?>][whatsapp_attachment_id]" value="<?php echo esc_attr( $whatsapp_attachment_id ); ?>"><button type="button" class="button ggm-choose-attachment">Choose file</button> <button type="button" class="button-link-delete ggm-remove-attachment" <?php echo $whatsapp_attachment_id ? '' : 'style="display:none"'; ?>>Remove</button> <span class="ggm-attachment-name"><?php echo esc_html( $whatsapp_attachment_name ?: 'No file selected' ); ?></span><br><small>The file's public download link is appended to the WhatsApp message.</small></p>
		</div>
	<?php endforeach; ?>
	</div>
	<button type="button" class="button" id="ggm-add-rule">Add Rule</button> <?php submit_button( 'Save Rules', 'primary', 'submit', false ); ?>
</form>
<hr><h2><?php esc_html_e( 'Submitted Forms', 'ggm-member-dashboard' ); ?></h2><table class="widefat striped"><thead><tr><th>Member</th><th>Workshop</th><th>Contact</th><th>Diseases</th><th>Health</th><th>Report</th><th>Submitted</th></tr></thead><tbody>
<?php $found=false; foreach ( get_users( array( 'meta_key'=>GGM_Health_Intake::META_KEY ) ) as $u ) : $items=get_user_meta($u->ID,GGM_Health_Intake::META_KEY,true); foreach((array)$items as $item): $found=true; ?><tr><td><a href="<?php echo esc_url(get_edit_user_link($u->ID)); ?>"><?php echo esc_html($item['full_name']??$u->display_name); ?></a></td><td><?php echo esc_html($item['workshop']??''); ?></td><td><?php echo esc_html(($item['email']??'').' / '.($item['phone']??'')); ?></td><td style="white-space:pre-wrap"><?php echo esc_html(($item['diseases']??'')."\n".($item['mentioned_diseases']??'')); ?></td><td><?php echo esc_html('Weight: '.($item['weight']??'—').' | BP: '.($item['bp']??'—').' | Sugar: '.($item['sugar_level']??'—')); ?></td><td><?php if(!empty($item['report_path'])):$url=wp_nonce_url(admin_url('admin-post.php?action=ggm_health_report&user_id='.$u->ID.'&workshop_id='.(int)$item['workshop_id']),'ggm_health_report_'.$u->ID.'_'.(int)$item['workshop_id']);?><a href="<?php echo esc_url($url); ?>">Download</a><?php else: ?>—<?php endif; ?></td><td><?php echo esc_html($item['submitted_at']??''); ?></td></tr><?php endforeach; endforeach; if(!$found): ?><tr><td colspan="7">No submissions yet.</td></tr><?php endif; ?></tbody></table></div>
<script>
(function(){
	var list=document.getElementById('ggm-rule-list');
	document.getElementById('ggm-add-rule').onclick=function(){
		var source=list.querySelector('.ggm-health-rule'), rule=source.cloneNode(true), index=list.children.length;
		rule.querySelectorAll('input,textarea').forEach(function(field){field.name=field.name.replace(/rules\[\d+\]/,'rules['+index+']');field.value='';});
		rule.querySelectorAll('.ggm-attachment-name').forEach(function(name){name.textContent='No file selected';});
		rule.querySelectorAll('.ggm-remove-attachment').forEach(function(button){button.style.display='none';});
		list.appendChild(rule);
	};
	list.addEventListener('click',function(event){
		if(event.target.classList.contains('ggm-choose-attachment')){
			event.preventDefault();var field=event.target.closest('.ggm-attachment-field'),frame=wp.media({title:'Choose attachment',button:{text:'Use this file'},multiple:false});
			frame.on('select',function(){var file=frame.state().get('selection').first().toJSON();field.querySelector('input[type=hidden]').value=file.id;field.querySelector('.ggm-attachment-name').textContent=file.filename||file.title;field.querySelector('.ggm-remove-attachment').style.display='inline';});frame.open();
		}
		if(event.target.classList.contains('ggm-remove-attachment')){event.preventDefault();var field=event.target.closest('.ggm-attachment-field');field.querySelector('input[type=hidden]').value='';field.querySelector('.ggm-attachment-name').textContent='No file selected';event.target.style.display='none';}
	});
})();
</script>
