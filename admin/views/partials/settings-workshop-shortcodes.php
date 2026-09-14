<?php
/**
 * Settings partial — Workshop Shortcodes reference.
 *
 * Documents the shortcodes used to build a single-workshop page (e.g. in
 * Elementor) without relying on ACF.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = isset( $settings ) && is_array( $settings ) ? $settings : get_option( 'ggm_settings', array() );

$validation_method = $settings['ggm_workshop_validation_method'] ?? 'phone';
$slot_types_value = $settings['ggm_workshop_slot_types'] ?? "live=Live\nrepeat=Repeat\nmorning_repeat=Morning Repeat";
$featured_media_fallback_enabled = ! array_key_exists( 'ggm_elementor_workshop_featured_media_fallback_enabled', $settings ) || ! empty( $settings['ggm_elementor_workshop_featured_media_fallback_enabled'] );

$workshop_heading_settings = array(
	'ggm_workshop_discover_heading'      => __( 'You Will Discover', 'ggm-member-dashboard' ),
	'ggm_workshop_why_different_heading' => __( 'Why This Webinar Is Different', 'ggm-member-dashboard' ),
	'ggm_workshop_perfect_for_heading'   => __( 'Perfect For You If You Want To', 'ggm-member-dashboard' ),
	'ggm_workshop_faq_heading'           => __( 'Frequently Asked Questions', 'ggm-member-dashboard' ),
);
$workshop_display_config = class_exists( 'GGM_Workshop_Admin_Config' ) ? GGM_Workshop_Admin_Config::get() : array( 'sections' => array(), 'fields' => array() );
$workshop_display_sections = class_exists( 'GGM_Workshop_Admin_Config' ) ? GGM_Workshop_Admin_Config::sections() : array();
$workshop_display_fields = class_exists( 'GGM_Workshop_Admin_Config' ) ? GGM_Workshop_Admin_Config::fields() : array();
$workshop_display_defaults = array(
	'sections' => array_keys( $workshop_display_sections ),
	'information_fields' => array_keys( $workshop_display_fields ),
);
?>
<?php if ( false ) : // Superseded frontend-composite controls; retained only as patch context until removal. ?>

<div id="ggm-workshop-configuration" class="ggm-workshop-display-config" style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:20px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Workshop Configuration', 'ggm-member-dashboard' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Superseded settings markup retained only as inactive patch context.', 'ggm-member-dashboard' ); ?></p>
	<p class="description"><?php esc_html_e( 'Drag a row, or use its Move up/Move down controls. Turn a row off to omit it from the composite Workshop page when its normal content and access conditions allow it to render.', 'ggm-member-dashboard' ); ?></p>
	<?php
	$render_display_rows = static function( $rows, $labels, $key ) {
		foreach ( $rows as $index => $row ) {
			$id = $row['id'] ?? '';
			if ( ! isset( $labels[ $id ] ) ) { continue; }
			?>
			<li class="ggm-workshop-display-config__row" data-config-row>
				<input type="hidden" name="settings[obsolete_workshop_configuration][<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
				<span class="dashicons dashicons-menu ggm-workshop-display-config__handle" aria-hidden="true"></span>
				<strong><?php echo esc_html( $labels[ $id ] ); ?></strong>
				<label><input type="checkbox" name="settings[obsolete_workshop_configuration][<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?>> <?php esc_html_e( 'Show', 'ggm-member-dashboard' ); ?></label>
				<span class="ggm-workshop-display-config__moves"><button type="button" class="button-link" data-move="up"><?php esc_html_e( 'Move up', 'ggm-member-dashboard' ); ?></button><button type="button" class="button-link" data-move="down"><?php esc_html_e( 'Move down', 'ggm-member-dashboard' ); ?></button></span>
			</li>
			<?php
		}
	};
	?>
	<h4><?php esc_html_e( 'Frontend Sections', 'ggm-member-dashboard' ); ?></h4>
	<ul class="ggm-workshop-display-config__list" data-config-list="sections"><?php $render_display_rows( $workshop_display_config['sections'], $workshop_display_sections, 'sections' ); ?></ul>
	<h4><?php esc_html_e( 'Workshop Information Fields', 'ggm-member-dashboard' ); ?></h4>
	<p class="description"><?php esc_html_e( 'These controls affect information display only. Dates, price, contribution options, and time slots remain active functional configuration even when their display row is hidden.', 'ggm-member-dashboard' ); ?></p>
	<ul class="ggm-workshop-display-config__list" data-config-list="information_fields"><?php $render_display_rows( $workshop_display_config['information_fields'], $workshop_display_fields, 'information_fields' ); ?></ul>
	<p><button type="button" class="button" data-config-reset><?php esc_html_e( 'Reset Workshop Configuration Defaults', 'ggm-member-dashboard' ); ?></button></p>
</div>
<style>
.ggm-workshop-display-config__list{margin:10px 0 18px;max-width:760px}.ggm-workshop-display-config__row{align-items:center;background:#fff;border:1px solid #dcdcde;border-radius:4px;display:flex;gap:12px;margin:6px 0;padding:10px}.ggm-workshop-display-config__handle{color:#646970;cursor:move}.ggm-workshop-display-config__row strong{flex:1}.ggm-workshop-display-config__moves{display:flex;gap:8px}.ggm-workshop-display-config__moves .button-link{cursor:pointer}
</style>
<script>
(function($){
	var defaults=<?php echo wp_json_encode( $workshop_display_defaults ); ?>;
	function renumber($list){$list.children('[data-config-row]').each(function(i){$(this).find('input').each(function(){this.name=this.name.replace(/\[(sections|information_fields)\]\[\d+\]/,function(match,key){return '['+key+']['+i+']';});});});}
	function init(){var $lists=$('.ggm-workshop-display-config__list');if($.fn.sortable){$lists.sortable({axis:'y',handle:'.ggm-workshop-display-config__handle',update:function(){renumber($(this));}});}$lists.on('click','[data-move]',function(){var $row=$(this).closest('[data-config-row]'),$list=$row.parent();if($(this).data('move')==='up'){$row.prev().before($row);}else{$row.next().after($row);}renumber($list);});$('[data-config-reset]').on('click',function(){if(!window.confirm('<?php echo esc_js( __( 'Reset both Workshop Configuration lists to their defaults? Save settings to apply the reset.', 'ggm-member-dashboard' ) ); ?>')){return;}var $box=$(this).closest('.ggm-workshop-display-config');$box.find('input[type=checkbox]').prop('checked',true);$box.find('[data-config-list]').each(function(){var $list=$(this),order=defaults[$list.data('config-list')]||[];$list.children().sort(function(a,b){return order.indexOf($(a).find('input[type=hidden]').val())-order.indexOf($(b).find('input[type=hidden]').val());}).appendTo($list);renumber($list);});});}
	$(init);
})(jQuery);
</script>
<?php endif; ?>

<div id="ggm-workshop-configuration" class="ggm-workshop-admin-config" style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:20px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Workshop Admin Create/Edit Configuration', 'ggm-member-dashboard' ); ?></h3>
	<p class="description"><?php esc_html_e( 'These settings control what administrators see and the order in which it appears on Workshop Add/Edit screens. They never delete Workshop data or change the frontend, Elementor, checkout, payments, access, or time slots.', 'ggm-member-dashboard' ); ?></p>
	<?php foreach ( $workshop_display_config['sections'] as $section_index => $section ) : $section_id = $section['id']; if ( ! isset( $workshop_display_sections[ $section_id ] ) ) { continue; } ?>
		<div class="ggm-workshop-admin-config__section">
			<input type="hidden" name="settings[ggm_workshop_admin_configuration][sections][<?php echo esc_attr( $section_index ); ?>][id]" value="<?php echo esc_attr( $section_id ); ?>">
			<h4><span class="dashicons dashicons-menu" aria-hidden="true"></span> <?php echo esc_html( $workshop_display_sections[ $section_id ] ); ?> <label><input type="checkbox" name="settings[ggm_workshop_admin_configuration][sections][<?php echo esc_attr( $section_index ); ?>][enabled]" value="1" <?php checked( ! empty( $section['enabled'] ) ); ?>> <?php esc_html_e( 'Show section', 'ggm-member-dashboard' ); ?></label></h4>
			<p><button type="button" class="button-link" data-admin-config-move="up"><?php esc_html_e( 'Move section up', 'ggm-member-dashboard' ); ?></button> <button type="button" class="button-link" data-admin-config-move="down"><?php esc_html_e( 'Move section down', 'ggm-member-dashboard' ); ?></button></p>
			<ul class="ggm-workshop-admin-config__fields">
			<?php foreach ( $workshop_display_config['fields'][ $section_id ] as $field_index => $field ) : $field_id = $field['id']; if ( ! isset( $workshop_display_fields[ $section_id ][ $field_id ] ) ) { continue; } ?>
				<li><input type="hidden" name="settings[ggm_workshop_admin_configuration][fields][<?php echo esc_attr( $section_id ); ?>][<?php echo esc_attr( $field_index ); ?>][id]" value="<?php echo esc_attr( $field_id ); ?>"><span class="dashicons dashicons-menu" aria-hidden="true"></span> <?php echo esc_html( $workshop_display_fields[ $section_id ][ $field_id ] ); ?> <label><input type="checkbox" name="settings[ggm_workshop_admin_configuration][fields][<?php echo esc_attr( $section_id ); ?>][<?php echo esc_attr( $field_index ); ?>][enabled]" value="1" <?php checked( ! empty( $field['enabled'] ) ); ?>> <?php esc_html_e( 'Show', 'ggm-member-dashboard' ); ?></label> <button type="button" class="button-link" data-admin-config-move="up"><?php esc_html_e( 'Up', 'ggm-member-dashboard' ); ?></button> <button type="button" class="button-link" data-admin-config-move="down"><?php esc_html_e( 'Down', 'ggm-member-dashboard' ); ?></button></li>
			<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
</div>
<style>.ggm-workshop-admin-config__section{background:#fff;border:1px solid #dcdcde;border-radius:5px;margin:12px 0;padding:12px}.ggm-workshop-admin-config__section h4{margin:0}.ggm-workshop-admin-config__fields li{align-items:center;display:flex;gap:8px;margin:6px 0}.ggm-workshop-admin-config__fields .dashicons,.ggm-workshop-admin-config__section h4 .dashicons{color:#646970}</style>
<script>(function($){function renumber($list){$list.children().each(function(i){$(this).find('input').each(function(){this.name=this.name.replace(/\[(sections|fields)(?:\]\[[^\]]+\])?\]\[\d+\]/,function(match,key){return match.replace(/\[\d+\]$/, '['+i+']');});});});}$(function(){$('.ggm-workshop-admin-config__section,.ggm-workshop-admin-config__fields li').on('click','[data-admin-config-move]',function(){var $row=$(this).closest('li,.ggm-workshop-admin-config__section'),$siblings=$row.siblings($row.is('li')?'li':'.ggm-workshop-admin-config__section');if($(this).data('admin-config-move')==='up'){$row.prev($row.is('li')?'li':'.ggm-workshop-admin-config__section').before($row);}else{$row.next($row.is('li')?'li':'.ggm-workshop-admin-config__section').after($row);}renumber($row.parent());});if($.fn.sortable){$('.ggm-workshop-admin-config').sortable({items:'.ggm-workshop-admin-config__section',handle:'h4 .dashicons',update:function(){renumber($(this));}});$('.ggm-workshop-admin-config__fields').sortable({items:'li',handle:'.dashicons',update:function(){renumber($(this));}});}});})(jQuery);</script>

<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:20px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Workshop Time Slot Types', 'ggm-member-dashboard' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Add or edit one type per line using key=Label. Keep a key unchanged when only renaming its label so existing workshop slots continue using it.', 'ggm-member-dashboard' ); ?></p>
	<textarea name="settings[ggm_workshop_slot_types]" id="ggm_workshop_slot_types" class="large-text code" rows="6" placeholder="live=Live&#10;evening=Evening Session"><?php echo esc_textarea( $slot_types_value ); ?></textarea>
</div>

<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:20px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Webinar Additional Block Headings', 'ggm-member-dashboard' ); ?></h3>
	<p class="description"><?php esc_html_e( 'These are the global defaults for the four block headings shown by their shortcodes, in Workshop edit screens, in the bundled workshop template, and in Elementor Heading widgets connected to the related GGM Dynamic Tag. An individual workshop can override any heading in its Webinar Additional Blocks area. Leave a setting blank to use its built-in default.', 'ggm-member-dashboard' ); ?></p>
	<table class="form-table" style="margin-bottom:0;">
		<?php foreach ( $workshop_heading_settings as $key => $default ) : ?>
			<tr>
				<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $default ); ?></label></th>
				<td>
					<input type="text" name="settings[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ?? $default ); ?>" class="regular-text">
					<p class="description"><?php echo esc_html( sprintf( __( 'Default: %s', 'ggm-member-dashboard' ), $default ) ); ?></p>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<p class="description" style="margin-top:16px;">
		<?php esc_html_e( 'The four Additional Block shortcodes already print their resolved heading. Do not place a separate Elementor Heading widget above the same shortcode, or two headings will appear. The GGM heading Dynamic Tags are only for layouts that do not use the matching Additional Block shortcode.', 'ggm-member-dashboard' ); ?>
	</p>
</div>

<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:20px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Workshop Account Validation', 'ggm-member-dashboard' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Choose how the workshop registration form verifies whether a visitor already has an account.', 'ggm-member-dashboard' ); ?></p>
	<table class="form-table" style="margin-bottom:0;">
		<tr>
			<th><label for="ggm_workshop_validation_method"><?php esc_html_e( 'Validation Method', 'ggm-member-dashboard' ); ?></label></th>
			<td>
				<select name="settings[ggm_workshop_validation_method]" id="ggm_workshop_validation_method" class="regular-text">
					<option value="phone" <?php selected( $validation_method, 'phone' ); ?>><?php esc_html_e( 'Phone Only', 'ggm-member-dashboard' ); ?></option>
					<option value="email" <?php selected( $validation_method, 'email' ); ?>><?php esc_html_e( 'Email Only', 'ggm-member-dashboard' ); ?></option>
					<option value="both" <?php selected( $validation_method, 'both' ); ?>><?php esc_html_e( 'Phone + Email', 'ggm-member-dashboard' ); ?></option>
				</select>
				<p class="description">
					<?php
					$descriptions = array(
						'phone' => __( 'Check the existing account using the phone number. Email is required for registration but does not affect account lookup.', 'ggm-member-dashboard' ),
						'email' => __( 'Check the existing account using the email address. Phone is required for registration but does not affect account lookup.', 'ggm-member-dashboard' ),
						'both'  => __( 'Require both phone number and email address to match the same account. Both must be valid and belong to the same user.', 'ggm-member-dashboard' ),
					);
					echo esc_html( $descriptions[ $validation_method ] ?? $descriptions['phone'] );
					?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:20px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Elementor Featured Video', 'ggm-member-dashboard' ); ?></h3>
	<label>
		<input type="checkbox" name="settings[ggm_elementor_workshop_featured_media_fallback_enabled]" value="1" <?php checked( $featured_media_fallback_enabled ); ?>>
		<strong><?php esc_html_e( 'Enable featured-media fallback for Elementor Video widgets', 'ggm-member-dashboard' ); ?></strong>
	</label>
	<p class="description" style="margin:8px 0 0;"><?php esc_html_e( 'When enabled, that exact GGM dynamic-tagged Video widget shows the current Workshop Featured Image whenever no valid Featured YouTube Video URL is saved. It does not change other Video widgets. Disable it to keep Elementorâ€™s normal empty-video behavior.', 'ggm-member-dashboard' ); ?></p>
	<p style="margin-bottom:0;"><?php esc_html_e( 'Add Elementor’s native Video widget to a Single Workshop template. On its video URL control, choose Dynamic Tags → GGM Workshop Featured Video URL. The tag automatically fetches the current workshop’s saved Featured YouTube Video URL.', 'ggm-member-dashboard' ); ?></p>
</div>

<div style="background:#f8f9fa; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:20px;">
	<h3 style="margin-top:0;"><?php esc_html_e( 'Workshop Detail Shortcodes', 'ggm-member-dashboard' ); ?></h3>
	<p style="color:#666; margin-bottom:16px;">
		<?php esc_html_e( 'Use these to build a single-workshop page in Elementor (or any page builder) without ACF. Drop one into an Elementor "Shortcode" widget, or — on Elementor Pro — attach it via Dynamic Tags → Shortcode to any field that accepts dynamic content (a Heading\'s text, a Button\'s link, etc.). Every shortcode accepts an optional id="123" attribute and defaults to the current workshop when omitted, so they work automatically inside a Single/Loop template.', 'ggm-member-dashboard' ); ?>
	</p>
	<?php
	$workshop_shortcodes = array(
		'[ggm_workshop_start_date_detail]'         => __( 'Standalone Start Date block with a line calendar SVG, heading, and formatted Start Date. Supports id="123", label="Start Date", class="your-class", and format="F j, Y". Existing date shortcodes are unchanged.', 'ggm-member-dashboard' ),
		'[ggm_workshop_preparatory_date_detail]'   => __( 'Optional standalone Preparatory Date block. It renders only when the workshop has a Preparatory Date selected and supports id="123", label="Preparatory Date", class="your-class", and format="F j, Y".', 'ggm-member-dashboard' ),
		'[ggm_workshop_duration_detail]'           => __( 'Standalone Duration block with a line hourglass SVG. Reads the existing Duration field exactly as entered. Supports id="123", label="Duration", and class="your-class".', 'ggm-member-dashboard' ),
		'[ggm_workshop_time_slot_detail]'          => __( 'Standalone Time Slot block with a line clock SVG. Shows every active slot with its type and formatted range. Supports id="123", label="Time Slot", and class="your-class".', 'ggm-member-dashboard' ),
		'[ggm_workshop_language_detail]'           => __( 'Standalone Language block with a line globe SVG matching the supplied design. Supports id="123", label="Language", and class="your-class".', 'ggm-member-dashboard' ),
		'[ggm_workshop_contribution_detail]'       => __( 'Standalone Contribution block with a line heart/currency SVG. Uses the pricing resolver so fixed, free, sale, and contribution pricing remain accurate. Supports id="123", label="Contribution", and class="your-class".', 'ggm-member-dashboard' ),
		'[ggm_workshop_booking_card]'              => __( 'Combined booking card: up to four Workshop-configured Hero icon/text boxes, date range, reference-style price and savings layout, a configurable CTA that scrolls to this Workshop’s full join form, and optional testimonial/rating text. Supports id="123", class="your-class", and date_format="F j, Y". Place [ggm_workshop_join_form_full button_text="Submit Now"] after it in the same template.', 'ggm-member-dashboard' ),
		'[ggm_workshop_date before="📅 "]'       => __( 'The workshop date, formatted per your site\'s Date Format setting.', 'ggm-member-dashboard' ),
		'[ggm_workshop_time before="🕐 "]'       => __( 'The time slot range, or "Multiple time slots available" when the workshop has more than one slot.', 'ggm-member-dashboard' ),
		'[ggm_workshop_price]'                    => __( 'Formatted price with currency symbol — shows the Regular Price struck through next to the Sale Price when one is set, or just the Regular Price otherwise, or a "Free" badge if both are empty.', 'ggm-member-dashboard' ),
		'[ggm_workshop_contribution]'             => __( 'Selectable paid and Free Contribution Pricing options with a white pill Contribute button to checkout. Supports id="123", button_text="Contribute", and class="your-class". It renders only for workshops with Contribution Pricing enabled; in that state, [ggm_workshop_join_now] stays hidden.', 'ggm-member-dashboard' ),
		'[ggm_workshop_contribution_pills]'       => __( 'Compact horizontal contribution-price pills for coloured or image backgrounds. The configured default is filled white; every pill links to checkout with that exact option. Supports id="123" and class="your-class".', 'ggm-member-dashboard' ),
		'[ggm_workshop_mode before="📡 "]'       => __( 'Workshop mode, e.g. Zoom or In-person.', 'ggm-member-dashboard' ),
		'[ggm_workshop_description]'               => __( 'The Short Description field, rendered as a paragraph. Basic formatting like <strong>bold</strong> typed into that field is preserved.', 'ggm-member-dashboard' ),
		'[ggm_workshop_duration]'                  => __( 'Displays the Workshop Details “Label Duration” text exactly as entered, with no automatic prefix, suffix, extra label, or icon.', 'ggm-member-dashboard' ),
		'[ggm_workshop_slots_select]'              => __( 'Standalone time-slot dropdown. Only renders when the workshop has 2+ active slots.', 'ggm-member-dashboard' ),
		'[ggm_workshop_enroll]'                    => __( 'The complete, state-aware call-to-action button — "Start Watching" if enrolled, free registration (with slot picker) for free workshops, or "Enroll Now" linking to this plugin\'s checkout for paid ones. Pass class="your-class" to match your button styling.', 'ggm-member-dashboard' ),
		'[ggm_workshop_discover]'                  => __( 'Discover heading and repeater content. Per-workshop Columns and Rows are configured in Webinar Additional Blocks → Additional Block Layout. Leave both at Default to preserve its current layout. Use show_heading="no" when retaining a separate Elementor Heading widget.', 'ggm-member-dashboard' ),
		'[ggm_workshop_why_different]'             => __( 'Why Different heading and repeater cards. Per-workshop Columns and Rows are configured in Webinar Additional Blocks → Additional Block Layout. Leave both at Default to preserve its current layout. Use show_heading="no" when retaining a separate Elementor Heading widget.', 'ggm-member-dashboard' ),
		'[ggm_workshop_perfect_for]'                => __( 'Perfect For heading plus image and description cards. Per-workshop Columns and Rows are configured in Webinar Additional Blocks → Additional Block Layout. Leave both at Default to preserve its current layout. Use show_heading="no" when retaining a separate Elementor Heading widget.', 'ggm-member-dashboard' ),
		'[ggm_workshop_faq]'                       => __( 'FAQ heading and question-and-answer accordion. Use show_heading="no" when retaining a separate Elementor Heading widget.', 'ggm-member-dashboard' ),
		'[ggm_workshop_icon_list fields="date,preparatory,time,price,mode,language"]' => __( 'Compact icon list. “date” shows the workshop date range and “preparatory” adds the optional Preparatory Date, using three-letter month names by default. When Preparatory Date is empty, neither its row nor its icon is rendered. Multiple slots collapse into one availability row.', 'ggm-member-dashboard' ),
		'[ggm_workshop_countdown]'                    => __( 'Responsive DD:HH:MM:SS countdown with white number cards on a transparent outer background. Numbers use the configured theme accent colour, it updates every second, and remains visible at 00:00:00:00 after the workshop starts.', 'ggm-member-dashboard' ),
		'[ggm_workshop_heading before="GGM " after=" Days Course"]' => __( 'Heading reading "GGM {N} Days Course", where {N} is the number pulled from the Duration field (styled Space Grotesk, 25px, semi-bold, 1.1em line height, white — drop it on a dark/teal background).', 'ggm-member-dashboard' ),
		'[ggm_workshop_join_now text="Join Now"]'  => __( 'White pill "Join Now" button linking to this workshop\'s checkout page. There the visitor sees Name/Phone/Email fields (pre-filled if logged in, editable) and the time slot — a dropdown if there are 2+ slots, or the single slot shown plainly if there\'s only one — then Pay opens the real Razorpay payment popup. If the visitor isn\'t logged in yet, checkout first sends them through the existing phone-OTP login + complete-profile screens and brings them straight back.', 'ggm-member-dashboard' ),
		'[ggm_workshop_join_form button_text="Join Now"]' => __( 'Inline Name + Number + "Join Now" bar (rounded fields, teal-to-green gradient button). On submit it jumps straight to this workshop\'s checkout page with the typed name/number already filled into the Name/Phone fields there — no separate click-through needed.', 'ggm-member-dashboard' ),
		'[ggm_workshop_join_form_full button_text="Pay Now"]' => __( 'Name + Phone row followed by a full-width Email row. Contribution-priced workshops add the dynamic support message and configured amount pills. Pay Now opens the real payment popup right on this page using the typed contact details — no redirect to the checkout page — then goes straight to the dashboard; a free workshop or free contribution option skips the popup and goes straight to the dashboard too. The amount is re-validated server-side either way.', 'ggm-member-dashboard' ),
		'[ggm_assigned_forms]'                    => __( 'Forms assigned to the current workshop or course. Place this in its own Shortcode widget; assigned forms are intentionally kept out of the Post Content widget.', 'ggm-member-dashboard' ),
		'[ggm_workshop_mentor]'                    => __( 'The mentor assigned to this workshop (circular photo, bold name, bio). Create mentor profiles under the Mentors admin menu, then pick one from the "Mentor" field in this workshop\'s Workshop Details box.', 'ggm-member-dashboard' ),
		'[ggm_workshop_header_pill]'              => __( 'Primary-colour, white-text Workshop header pill. Uses Header Pill Text from Workshop Details, then falls back to the Workshop title. Supports id="123", text="Custom label", and class="your-class".', 'ggm-member-dashboard' ),
	);
	foreach ( $workshop_shortcodes as $code => $desc ) :
		?>
		<div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:10px;">
			<code style="background:#fff; border:1px solid #ddd; padding:6px 12px; border-radius:4px; flex:0 0 280px; width:280px; box-sizing:border-box; cursor:pointer; user-select:all; white-space:normal; word-break:break-word; line-height:1.4; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;" title="<?php echo esc_attr( $code ); ?>" onclick="var el=this; el.style.webkitLineClamp='unset'; el.style.overflow='visible'; var r=document.createRange(); r.selectNodeContents(el); window.getSelection().removeAllRanges(); window.getSelection().addRange(r); document.execCommand('copy'); ggmShowCopied(el); setTimeout(function(){ el.style.webkitLineClamp='2'; el.style.overflow='hidden'; window.getSelection().removeAllRanges(); }, 1200);"><?php echo esc_html( $code ); ?></code>
			<span style="color:#555; font-size:13px; padding-top:6px;"><?php echo esc_html( $desc ); ?></span>
		</div>
	<?php endforeach; ?>

	<p style="color:#888; font-size:12px; margin-top:16px; margin-bottom:0;">
		<?php esc_html_e( 'The four Additional Block shortcodes include exactly one resolved H2 heading with the .ggm-ws-block__heading class. A workshop override replaces the global/default heading; it is not printed alongside it. Remove any separate legacy Elementor Heading widget above these shortcodes. The content classes (.ggm-ws-discover, .ggm-ws-why-different, .ggm-ws-perfect-for, and .ggm-ws-faq) remain available for Elementor Custom CSS or your theme stylesheet.', 'ggm-member-dashboard' ); ?>
	</p>
</div>
