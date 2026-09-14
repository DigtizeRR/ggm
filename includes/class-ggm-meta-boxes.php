<?php
/**
 * Native Meta Boxes Manager.
 *
 * Implements native post meta boxes, image uploaders, repeaters,
 * and saving logic without external plugins.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Meta_Boxes
 */
class GGM_Meta_Boxes {

	/**
	 * Basic inline formatting allowed in the workshop meta box's free-text
	 * fields (Short Description, and the "Description"/"Answer" columns of
	 * the Webinar Additional Blocks repeaters) — enough for bold/italic/
	 * line-break emphasis without opening up full post-content HTML in
	 * fields that are meant to stay short summaries.
	 */
	const SHORT_DESC_ALLOWED_TAGS = array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'br'     => array(),
	);

	/**
	 * Inline formatting accepted by the Booking Card Hero Header editor.
	 * A span style is deliberately permitted so administrators can use the
	 * editor's text-colour control without allowing arbitrary block markup.
	 */
	const HERO_HEADER_ALLOWED_TAGS = array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'br'     => array(),
		'span'   => array( 'style' => array() ),
		'p'      => array( 'style' => array() ),
	);

	/**
	 * Register actions via loader.
	 *
	 * @param GGM_Loader $loader
	 */
	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'add_meta_boxes', $this, 'register_meta_boxes' );
		$loader->add_action( 'save_post', $this, 'save_meta_boxes', 10, 2 );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_assets' );
		$loader->add_action( 'admin_notices', $this, 'show_slot_admin_notice' );
	}

	/**
	 * Display a one-time notice if any workshop time slot rows were skipped on save.
	 */
	public function show_slot_admin_notice() {
		$key     = 'ggm_slot_notice_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! $message ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Enqueue admin scripts for WP Media selector.
	 */
	public function enqueue_admin_assets() {
		global $post_type;
		if ( in_array( $post_type, array( 'post', 'workshop', 'course', 'lesson', 'ggm_mentor' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
		if ( 'ggm_mentor' === $post_type && function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}
		if ( 'course' === $post_type ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	}

	/**
	 * Register metaboxes for the three post types.
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'ggm_workshop_details',
			__( 'Workshop Details', 'ggm-member-dashboard' ),
			array( $this, 'render_workshop_details_mb' ),
			'workshop',
			'normal',
			'high'
		);

		add_meta_box(
			'ggm_course_details',
			__( 'Course Details', 'ggm-member-dashboard' ),
			array( $this, 'render_course_details_mb' ),
			'course',
			'normal',
			'high'
		);

		// Course Lessons manager — shows all lessons for this course inline.
		add_meta_box(
			'ggm_course_lessons',
			__( 'Course Lessons', 'ggm-member-dashboard' ),
			array( $this, 'render_course_lessons_mb' ),
			'course',
			'normal',
			'default'
		);

		add_meta_box(
			'ggm_lesson_details',
			__( 'Lesson Details', 'ggm-member-dashboard' ),
			array( $this, 'render_lesson_details_mb' ),
			'lesson',
			'normal',
			'high'
		);

		add_meta_box(
			'ggm_mentor_details',
			__( 'Mentor Details', 'ggm-member-dashboard' ),
			array( $this, 'render_mentor_details_mb' ),
			'ggm_mentor',
			'normal',
			'high'
		);

		add_meta_box(
			'ggm_blog_video',
			__( 'Success Story Video', 'ggm-member-dashboard' ),
			array( $this, 'render_blog_video_mb' ),
			'post',
			'normal',
			'default'
		);
	}

	/**
	 * Render the blog video field for normal WordPress posts.
	 */
	public function render_blog_video_mb( $post ) {
		wp_nonce_field( 'ggm_meta_box_save', 'ggm_meta_nonce' );

		$video_url = get_post_meta( $post->ID, 'ggm_blog_video_url', true );
		?>
		<div class="ggm-meta-container">
			<table class="form-table ggm-meta-table">
				<tr>
					<th><label for="ggm-blog-video-url"><?php esc_html_e( 'Video URL', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="url" name="ggm_blog_video_url" id="ggm-blog-video-url" value="<?php echo esc_url( $video_url ); ?>" class="large-text" placeholder="https://www.youtube.com/watch?v=...">
						<button type="button" class="button ggm-media-upload-btn" data-target="ggm-blog-video-url"><?php esc_html_e( 'Select / Upload Video', 'ggm-member-dashboard' ); ?></button>
						<p class="description"><?php esc_html_e( 'Use this in Elementor Video widget with Dynamic Tags → Success Story Video.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
		$this->print_media_uploader_js();
	}

	/**
	 * Render Workshop details meta box.
	 */
	public function render_workshop_details_mb( $post ) {
		wp_nonce_field( 'ggm_meta_box_save', 'ggm_meta_nonce' );

		$ggm_mentor_ids = get_post_meta( $post->ID, 'ggm_mentor_ids', true );
		$ggm_mentor_ids = is_array( $ggm_mentor_ids ) ? array_map( 'absint', $ggm_mentor_ids ) : array();
		if ( empty( $ggm_mentor_ids ) ) {
			// Fall back to the pre-multi-mentor single "ggm_mentor_id" field.
			$legacy_mentor_id = (int) get_post_meta( $post->ID, 'ggm_mentor_id', true );
			if ( $legacy_mentor_id ) {
				$ggm_mentor_ids = array( $legacy_mentor_id );
			}
		}
		$counter_start_date  = get_post_meta( $post->ID, 'Counter_Start_Date', true );
		$preparatory_date    = get_post_meta( $post->ID, 'workshop_preparatory_date', true );
		$workshop_mode       = get_post_meta( $post->ID, 'workshop_mode', true );
		$whatsapp_group_url  = get_post_meta( $post->ID, 'ggm_workshop_whatsapp_group_url', true );
		$workshop_language   = get_post_meta( $post->ID, 'workshop_language', true ) ?: 'english';
		$duration            = get_post_meta( $post->ID, 'duration', true );
		$is_free             = get_post_meta( $post->ID, 'is_free', true );
		$featured_video_url  = get_post_meta( $post->ID, 'ggm_workshop_featured_video_url', true );
		$bottom_image_id     = absint( get_post_meta( $post->ID, 'ggm_workshop_bottom_image_id', true ) );
		$booking_card_cta_text = get_post_meta( $post->ID, 'ggm_workshop_booking_card_cta_text', true );
		$booking_card_cta_heading = get_post_meta( $post->ID, 'ggm_workshop_booking_card_cta_heading', true );
		$header_pill_text = get_post_meta( $post->ID, 'ggm_workshop_header_pill_text', true );
		$booking_card_hero_header = get_post_meta( $post->ID, 'ggm_workshop_booking_card_hero_header', true );
		$booking_card_hero_items = get_post_meta( $post->ID, 'ggm_workshop_booking_card_hero_items', true );
		$booking_card_hero_items = is_array( $booking_card_hero_items ) ? array_slice( $booking_card_hero_items, 0, 4 ) : array();
		$booking_card_testimonial_text = get_post_meta( $post->ID, 'ggm_workshop_booking_card_testimonial_text', true );
		$booking_card_rating_text      = get_post_meta( $post->ID, 'ggm_workshop_booking_card_rating_text', true );
		$form_text_heading              = get_post_meta( $post->ID, 'ggm_workshop_form_text_heading', true );
		$form_text_content              = get_post_meta( $post->ID, 'ggm_workshop_form_text_content', true );
		$additional_block_layouts      = get_post_meta( $post->ID, 'ggm_workshop_additional_block_layouts', true );
		$additional_block_layouts      = is_array( $additional_block_layouts ) ? $additional_block_layouts : array();
		$linked_course_id    = (int) get_post_meta( $post->ID, 'linked_course_id', true );
		$language_options = array(
			'english' => __( 'English', 'ggm-member-dashboard' ),
			'hindi'   => __( 'Hindi', 'ggm-member-dashboard' ),
		);

		// Workshop Date → Start/End Date (v2). Fall back to the pre-v2 single
		// "Workshop Date" field for workshops saved before this upgrade.
		$workshop_start_date = get_post_meta( $post->ID, 'workshop_start_date', true );
		if ( '' === $workshop_start_date ) {
			$workshop_start_date = get_post_meta( $post->ID, 'workshop_date', true );
		}
		$workshop_end_date = get_post_meta( $post->ID, 'workshop_end_date', true );

		// Workshop price is stored in the global base currency from settings.
		// Other currencies are derived from settings/API plus manual extras.
		$workshop_regular_price = get_post_meta( $post->ID, 'workshop_regular_price', true );
		if ( '' === $workshop_regular_price ) {
			$workshop_regular_price = get_post_meta( $post->ID, 'workshop_money', true );
		}
		$workshop_sale_price = get_post_meta( $post->ID, 'workshop_sale_price', true );
		$contribution_enabled = get_post_meta( $post->ID, 'ggm_contribution_enabled', true );
		$contribution_options = get_post_meta( $post->ID, 'ggm_contribution_options', true );
		$contribution_options = is_array( $contribution_options ) ? $contribution_options : array();
		$currency_adjustments = class_exists( 'GGM_Currency' ) ? GGM_Currency::adjustments_for_item( $post->ID ) : array();
		$enabled_currencies   = class_exists( 'GGM_Currency' ) ? GGM_Currency::enabled_codes() : array( 'INR' );
		$base_currency_code   = class_exists( 'GGM_Currency' ) ? GGM_Currency::base_currency() : 'INR';

		$mentors = get_posts( array(
			'post_type'      => 'ggm_mentor',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$courses = get_posts( array(
			'post_type'      => 'course',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$slots = ( $post->ID && class_exists( 'GGM_Workshop_Slot' ) ) ? GGM_Workshop_Slot::get_for_workshop( $post->ID, 'all' ) : array();
		?>
		<div class="ggm-meta-container">
			<table class="form-table ggm-meta-table">
				<tr>
					<th><label for="ggm-workshop-mentor"><?php esc_html_e( 'Mentors', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<div id="ggm-workshop-mentor" class="ggm-checkbox-list">
							<?php if ( empty( $mentors ) ) : ?>
								<p class="description"><?php esc_html_e( 'No mentor profiles found. Add one under the Mentors menu.', 'ggm-member-dashboard' ); ?></p>
							<?php endif; ?>
							<?php foreach ( $mentors as $mentor ) : ?>
								<label class="ggm-checkbox-list__item">
									<input type="checkbox" name="ggm_mentor_ids[]" value="<?php echo esc_attr( $mentor->ID ); ?>" <?php checked( in_array( $mentor->ID, $ggm_mentor_ids, true ) ); ?>>
									<?php echo esc_html( $mentor->post_title ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Select one or more mentors. Shown via [ggm_workshop_mentor]. Manage mentor profiles under the Mentors menu.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-linked-course"><?php esc_html_e( 'Linked Course', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<select name="linked_course_id" id="ggm-linked-course" class="regular-text">
							<option value="0"><?php esc_html_e( '— None —', 'ggm-member-dashboard' ); ?></option>
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $linked_course_id, $course->ID ); ?>><?php echo esc_html( $course->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Optional. The course shown on this workshop\'s page and in the dashboard as the related follow-up course.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-counter-date"><?php esc_html_e( 'Counter Start Date & Time', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="datetime-local" name="Counter_Start_Date" id="ggm-counter-date" value="<?php echo esc_attr( $counter_start_date ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-preparatory-date"><?php esc_html_e( 'Preparatory Date', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="date" name="workshop_preparatory_date" id="ggm-workshop-preparatory-date" value="<?php echo esc_attr( $preparatory_date ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Optional. Display it with [ggm_workshop_preparatory_date_detail]; nothing is shown when no date is selected.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-start-date"><?php esc_html_e( 'Workshop Date Range', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<div class="ggm-workshop-date-range" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
							<label><span class="screen-reader-text"><?php esc_html_e( 'Start date', 'ggm-member-dashboard' ); ?></span><input type="date" name="workshop_start_date" id="ggm-workshop-start-date" value="<?php echo esc_attr( $workshop_start_date ); ?>"></label>
							<span aria-hidden="true">&ndash;</span>
							<label><span class="screen-reader-text"><?php esc_html_e( 'End date', 'ggm-member-dashboard' ); ?></span><input type="date" name="workshop_end_date" id="ggm-workshop-end-date" value="<?php echo esc_attr( $workshop_end_date ); ?>" min="<?php echo esc_attr( $workshop_start_date ); ?>"></label>
						</div>
						<p class="description"><?php esc_html_e( 'Choose the workshop start and end as one date range.', 'ggm-member-dashboard' ); ?></p>
						<p class="description"><?php esc_html_e( 'Once this date has passed, the workshop is treated as expired and is hidden from the member dashboard.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-regular-price"><?php esc_html_e( 'Regular Price', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="number" step="0.01" min="0" name="workshop_regular_price" id="ggm-workshop-regular-price" value="<?php echo esc_attr( $workshop_regular_price ); ?>" class="regular-text" placeholder="e.g. 9000">
						<p class="description"><?php printf( esc_html__( 'Enter the base amount in %s. Other currencies are controlled from Multi Currency settings.', 'ggm-member-dashboard' ), esc_html( $base_currency_code ) ); ?></p>
					</td>
				</tr>
				<tr id="ggm-sale-price-row">
					<th><label for="ggm-workshop-sale-price"><?php esc_html_e( 'Sale Price', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="number" step="0.01" min="0" name="workshop_sale_price" id="ggm-workshop-sale-price" value="<?php echo esc_attr( $workshop_sale_price ); ?>" class="regular-text" placeholder="e.g. 1800 (optional)">
						<p class="description" id="ggm-sale-price-error" style="display:none; color:#b32d2e;"><?php esc_html_e( 'Sale Price cannot exceed Regular Price.', 'ggm-member-dashboard' ); ?></p>
						<p class="description"><?php esc_html_e( 'Optional. When set, the frontend shows the Regular Price struck through next to the Sale Price. Leave blank to show only the Regular Price.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<?php if ( class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() ) : ?>
				<tr>
					<th><?php esc_html_e( 'Manual Currency Extras', 'ggm-member-dashboard' ); ?></th>
					<td>
						<div class="ggm-currency-adjustments">
							<?php foreach ( $enabled_currencies as $currency_code ) : if ( $currency_code === $base_currency_code ) { continue; } ?>
								<label>
									<span><?php echo esc_html( $currency_code ); ?></span>
									<input type="number" min="0" step="0.01" name="ggm_currency_adjustments[<?php echo esc_attr( $currency_code ); ?>]" value="<?php echo esc_attr( $currency_adjustments[ $currency_code ] ?? '' ); ?>" placeholder="0.00">
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php printf( esc_html__( 'Optional extra amount added after the %s price is converted. Example: converted USD price + USD extra = visitor price.', 'ggm-member-dashboard' ), esc_html( $base_currency_code ) ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Contribution Pricing', 'ggm-member-dashboard' ); ?></th>
					<td>
						<label><input type="checkbox" name="ggm_contribution_enabled" id="ggm-contribution-enabled" value="1" <?php checked( $contribution_enabled, '1' ); ?>> <?php esc_html_e( 'Let members choose from contribution prices', 'ggm-member-dashboard' ); ?></label>
						<input type="hidden" name="ggm_contributions_present" value="1">
						<div id="ggm-contribution-options" style="margin-top:10px">
							<?php foreach ( $contribution_options as $i => $option ) :
								$option_type = isset( $option['type'] ) && 'free' === $option['type'] ? 'free' : 'paid';
								?>
								<p class="ggm-contribution-row">
									<input type="hidden" name="ggm_contributions[<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $option['id'] ?? '' ); ?>">
									<input class="ggm-contribution-amount" type="number" min="0.01" step="0.01" name="ggm_contributions[<?php echo esc_attr( $i ); ?>][amount]" value="<?php echo esc_attr( 'free' === $option_type ? '' : ( $option['amount'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Amount', 'ggm-member-dashboard' ); ?>" <?php disabled( 'free', $option_type ); ?>>
									<input type="text" name="ggm_contributions[<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $option['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Optional label', 'ggm-member-dashboard' ); ?>">
									<label><input class="ggm-contribution-free" type="checkbox" name="ggm_contributions[<?php echo esc_attr( $i ); ?>][is_free]" value="1" <?php checked( 'free', $option_type ); ?>> <?php esc_html_e( 'Free option', 'ggm-member-dashboard' ); ?></label>
									<label><input type="radio" name="ggm_contribution_default" value="<?php echo esc_attr( $i ); ?>" <?php checked( ! empty( $option['is_default'] ) ); ?>> <?php esc_html_e( 'Default', 'ggm-member-dashboard' ); ?></label>
									<button type="button" class="button-link-delete ggm-remove-contribution"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button>
								</p>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="ggm-add-contribution"><?php esc_html_e( 'Add contribution price', 'ggm-member-dashboard' ); ?></button>
						<p class="description"><?php esc_html_e( 'Add multiple paid prices and optionally one Free choice. Exactly one valid option is selected by default. Regular/Sale prices and the workshop-wide Is Free setting are ignored while Contribution Pricing is enabled.', 'ggm-member-dashboard' ); ?></p>
						<script>
						(function(){
							var list=document.getElementById('ggm-contribution-options'),add=document.getElementById('ggm-add-contribution');
							function sync(row){
								var free=row.querySelector('.ggm-contribution-free'),amount=row.querySelector('.ggm-contribution-amount');
								if(!free||!amount)return;
								amount.disabled=free.checked;
								if(free.checked)amount.value='';
							}
							add.onclick=function(){
								var i=Date.now(),p=document.createElement('p');
								p.className='ggm-contribution-row';
								p.innerHTML='<input type="hidden" name="ggm_contributions['+i+'][id]" value=""><input class="ggm-contribution-amount" type="number" min="0.01" step="0.01" name="ggm_contributions['+i+'][amount]" placeholder="<?php echo esc_js( __( 'Amount', 'ggm-member-dashboard' ) ); ?>"> <input type="text" name="ggm_contributions['+i+'][label]" placeholder="<?php echo esc_js( __( 'Optional label', 'ggm-member-dashboard' ) ); ?>"> <label><input class="ggm-contribution-free" type="checkbox" name="ggm_contributions['+i+'][is_free]" value="1"> <?php echo esc_js( __( 'Free option', 'ggm-member-dashboard' ) ); ?></label> <label><input type="radio" name="ggm_contribution_default" value="'+i+'"> <?php echo esc_js( __( 'Default', 'ggm-member-dashboard' ) ); ?></label> <button type="button" class="button-link-delete ggm-remove-contribution"><?php echo esc_js( __( 'Remove', 'ggm-member-dashboard' ) ); ?></button>';
								list.appendChild(p);
							};
							list.addEventListener('change',function(e){
								if(e.target.classList.contains('ggm-contribution-free')){
									if(e.target.checked){
										list.querySelectorAll('.ggm-contribution-free').forEach(function(box){if(box!==e.target){box.checked=false;sync(box.closest('.ggm-contribution-row'));}});
									}
									sync(e.target.closest('.ggm-contribution-row'));
								}
							});
							list.addEventListener('click',function(e){if(e.target.classList.contains('ggm-remove-contribution'))e.target.closest('p').remove();});
							list.querySelectorAll('.ggm-contribution-row').forEach(sync);
						})();
						</script>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-mode"><?php esc_html_e( 'Workshop Mode', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="text" name="workshop_mode" id="ggm-workshop-mode" value="<?php echo esc_attr( $workshop_mode ); ?>" class="regular-text" placeholder="e.g. Zoom, In-person">
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-header-pill-text"><?php esc_html_e( 'Header Pill Text', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="text" name="ggm_workshop_header_pill_text" id="ggm-workshop-header-pill-text" value="<?php echo esc_attr( $header_pill_text ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Women Only · Live · Interactive · Transformational', 'ggm-member-dashboard' ); ?>">
						<p class="description"><?php esc_html_e( 'Shown by [ggm_workshop_header_pill]. Leave blank to use this Workshop’s title.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Booking Card Hero', 'ggm-member-dashboard' ); ?></th>
					<td>
						<p class="description"><?php esc_html_e( 'Configure an optional rich header and up to four icon-and-text boxes shown at the top of [ggm_workshop_booking_card]. Blank content is not shown.', 'ggm-member-dashboard' ); ?></p>
						<p><strong><?php esc_html_e( 'Hero Header (optional)', 'ggm-member-dashboard' ); ?></strong></p>
						<?php wp_editor( $booking_card_hero_header, 'ggm-workshop-booking-card-hero-header', array( 'textarea_name' => 'ggm_workshop_booking_card_hero_header', 'textarea_rows' => 3, 'media_buttons' => false, 'tinymce' => array( 'toolbar1' => 'formatselect,bold,italic,forecolor,removeformat,undo,redo', 'toolbar2' => '' ), 'quicktags' => false ) ); ?>
						<p class="description"><?php esc_html_e( 'Use the text-colour control to style only selected words. The header is displayed above the Hero boxes.', 'ggm-member-dashboard' ); ?></p>
						<div style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:16px;max-width:700px;">
							<?php for ( $hero_index = 0; $hero_index < 4; $hero_index++ ) : $hero_item = is_array( $booking_card_hero_items[ $hero_index ] ?? null ) ? $booking_card_hero_items[ $hero_index ] : array(); ?>
								<div style="padding:12px;border:1px solid #dcdcde;border-radius:4px;">
									<p style="margin:0 0 8px;"><strong><?php echo esc_html( sprintf( __( 'Hero box %d', 'ggm-member-dashboard' ), $hero_index + 1 ) ); ?></strong></p>
									<?php $this->render_repeater_image_control( 'ggm_workshop_booking_card_hero_items[' . $hero_index . '][icon]', 'ggm-workshop-booking-card-hero-icon-' . $hero_index, $hero_item['icon'] ?? '' ); ?>
									<p style="margin:10px 0 0;"><label><?php esc_html_e( 'Text', 'ggm-member-dashboard' ); ?><br><input type="text" class="widefat" name="ggm_workshop_booking_card_hero_items[<?php echo esc_attr( $hero_index ); ?>][text]" value="<?php echo esc_attr( $hero_item['text'] ?? '' ); ?>"></label></p>
								</div>
							<?php endfor; ?>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-booking-card-cta-heading"><?php esc_html_e( 'Booking Card CTA Heading', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="text" name="ggm_workshop_booking_card_cta_heading" id="ggm-workshop-booking-card-cta-heading" value="<?php echo esc_attr( $booking_card_cta_heading ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Reserve My Spot Now', 'ggm-member-dashboard' ); ?>">
						<p class="description"><?php esc_html_e( 'The prominent first line inside the booking-card button.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-booking-card-cta-text"><?php esc_html_e( 'Booking Card Button Text', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="text" name="ggm_workshop_booking_card_cta_text" id="ggm-workshop-booking-card-cta-text" value="<?php echo esc_attr( $booking_card_cta_text ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Reserve My Spot Now', 'ggm-member-dashboard' ); ?>">
						<p class="description"><?php esc_html_e( 'The supporting second line inside the booking-card button. The button scrolls to [ggm_workshop_join_form_full] for this Workshop.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Booking Card Testimonial', 'ggm-member-dashboard' ); ?></th>
					<td>
						<p><label for="ggm-workshop-booking-card-testimonial-text"><?php esc_html_e( 'Testimonial text', 'ggm-member-dashboard' ); ?><br><input type="text" name="ggm_workshop_booking_card_testimonial_text" id="ggm-workshop-booking-card-testimonial-text" value="<?php echo esc_attr( $booking_card_testimonial_text ); ?>" class="large-text" placeholder="<?php esc_attr_e( '21,300+ Women Already Transformed', 'ggm-member-dashboard' ); ?>"></label></p>
						<p><label for="ggm-workshop-booking-card-rating-text"><?php esc_html_e( 'Rating text', 'ggm-member-dashboard' ); ?><br><input type="text" name="ggm_workshop_booking_card_rating_text" id="ggm-workshop-booking-card-rating-text" value="<?php echo esc_attr( $booking_card_rating_text ); ?>" class="large-text" placeholder="<?php esc_attr_e( '4.9/5 from 1,200+ Reviews', 'ggm-member-dashboard' ); ?>"></label></p>
						<p class="description"><?php esc_html_e( 'Shown beneath the CTA. Leave either field blank to hide that line.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-whatsapp-group-url"><?php esc_html_e( 'WhatsApp Group Link', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="url" name="ggm_workshop_whatsapp_group_url" id="ggm-workshop-whatsapp-group-url" value="<?php echo esc_url( $whatsapp_group_url ); ?>" class="large-text" placeholder="https://chat.whatsapp.com/…">
						<p class="description"><?php esc_html_e( 'Optional. Members with access will see a Join WhatsApp Group button. No button is shown while this is blank.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-language"><?php esc_html_e( 'Workshop Language', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<select name="workshop_language" id="ggm-workshop-language" class="regular-text">
							<?php foreach ( $language_options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $workshop_language, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-duration"><?php esc_html_e( 'Label Duration', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="text" name="duration" id="ggm-duration" value="<?php echo esc_attr( $duration ); ?>" class="regular-text" placeholder="e.g. 21-Day Live Online Workshop">
						<p class="description"><?php esc_html_e( 'Displayed exactly as entered by [ggm_workshop_duration], including spaces, hyphens, and words such as “Live” or “Online”. The shortcode adds nothing before or after this text.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Is Free', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="is_free" id="ggm-workshop-is-free" value="1" <?php checked( $is_free, '1' ); ?>>
							<?php esc_html_e( 'Check if this workshop is free.', 'ggm-member-dashboard' ); ?>
						</label>
					</td>
				</tr>
				<?php
				// The legacy workshop_short_desc value is intentionally retained in
				// post meta for existing workshops, but is no longer editable here.
				?>
				<tr>
					<th><label for="ggm-workshop-featured-video"><?php esc_html_e( 'Featured YouTube Video URL', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="url" name="ggm_workshop_featured_video_url" id="ggm-workshop-featured-video" class="large-text" value="<?php echo esc_url( $featured_video_url ); ?>" placeholder="https://www.youtube.com/watch?v=...">
						<p class="description"><?php esc_html_e( 'Optional public promotional video. In Elementor, add a Video widget and select Dynamic Tags → GGM Workshop Featured Video URL for its video URL. YouTube watch, share, embed, and Shorts URLs are supported.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Featured Image', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<p class="description"><?php esc_html_e( 'Use the "Featured Image" panel in the sidebar of this edit screen — it\'s used everywhere a workshop thumbnail is shown (dashboard, archive, single page).', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-form-text-heading"><?php esc_html_e( 'Form Text', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<p><strong><?php esc_html_e( 'Heading', 'ggm-member-dashboard' ); ?></strong></p>
						<input type="text" name="ggm_workshop_form_text_heading" id="ggm-workshop-form-text-heading" value="<?php echo esc_attr( $form_text_heading ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Ready to Decode Your Glow?', 'ggm-member-dashboard' ); ?>">
						<p><strong><?php esc_html_e( 'Content', 'ggm-member-dashboard' ); ?></strong></p>
						<?php wp_editor( $form_text_content, 'ggm-workshop-form-text-content-editor', array( 'textarea_name' => 'ggm_workshop_form_text_content', 'textarea_rows' => 10, 'media_buttons' => true, 'teeny' => false, 'quicktags' => true ) ); ?>
						<p class="description"><?php esc_html_e( 'Add formatted supporting text and place it with [ggm_workshop_form_text]. Both fields are optional.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-workshop-bottom-image"><?php esc_html_e( 'Bottom Image', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<?php $this->render_attachment_image_control( 'ggm_workshop_bottom_image_id', 'ggm-workshop-bottom-image', $bottom_image_id ); ?>
						<p class="description"><?php esc_html_e( 'Select this image in Elementor with Dynamic Tags → GGM Workshop → Bottom Image.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
			</table>

			<!-- Time Slots -->
			<hr/>
			<div class="ggm-repeater-block" data-key="workshop_time_slots">
				<h4><?php esc_html_e( 'Workshop Time Slots', 'ggm-member-dashboard' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Add one or more daily sessions. Each slot has its own type, start/end time, and its own Zoom/Google Meet link — it repeats every day from the Workshop Start Date through the Workshop End Date above. After purchase, buyers automatically get access to every enabled slot — the dashboard\'s "Start Learning" button always points at whichever slot is current or coming up next.', 'ggm-member-dashboard' ); ?></p>
				<table class="widefat striped ggm-repeater-table" id="ggm-table-workshop_time_slots">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Type', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Start Time', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'End Time', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Zoom / Meet Link', 'ggm-member-dashboard' ); ?></th>
							<th style="width: 70px;"><?php esc_html_e( 'Enabled', 'ggm-member-dashboard' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Action', 'ggm-member-dashboard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $slots as $index => $slot ) : ?>
							<tr data-index="<?php echo esc_attr( $index ); ?>">
								<td>
									<input type="hidden" name="workshop_time_slots[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $slot->id ); ?>">
									<select name="workshop_time_slots[<?php echo esc_attr( $index ); ?>][type]">
									<?php foreach ( GGM_Workshop_Slot::types() as $type => $type_label ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $slot->slot_type, $type ); ?>><?php echo esc_html( $type_label ); ?></option>
									<?php endforeach; ?>
									<?php if ( ! array_key_exists( $slot->slot_type, GGM_Workshop_Slot::types() ) ) : ?><option value="<?php echo esc_attr( $slot->slot_type ); ?>" selected><?php echo esc_html( GGM_Workshop_Slot::type_label( $slot->slot_type ) ); ?></option><?php endif; ?>
									</select>
								</td>
								<td><input type="time" name="workshop_time_slots[<?php echo esc_attr( $index ); ?>][start]" value="<?php echo esc_attr( substr( $slot->start_time, 0, 5 ) ); ?>" required></td>
								<td><input type="time" name="workshop_time_slots[<?php echo esc_attr( $index ); ?>][end]" value="<?php echo esc_attr( substr( $slot->end_time, 0, 5 ) ); ?>" required></td>
								<td><input type="url" name="workshop_time_slots[<?php echo esc_attr( $index ); ?>][meeting_link]" value="<?php echo esc_url( $slot->meeting_link ); ?>" class="widefat" placeholder="https://zoom.us/j/..."></td>
								<td style="text-align:center;"><input type="checkbox" name="workshop_time_slots[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( 'active', $slot->status ); ?>></td>
								<td><button type="button" class="button button-link-delete ggm-remove-row-btn"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button ggm-add-row-btn" data-key="workshop_time_slots"><?php esc_html_e( '+ Add Time Slot', 'ggm-member-dashboard' ); ?></button></p>
			</div>

			<!-- Repeaters Grid -->
			<hr/>
			<h3><?php esc_html_e( 'Webinar Additional Blocks (Repeaters)', 'ggm-member-dashboard' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Description/Answer fields below allow basic HTML, e.g. <strong>bold text</strong>.', 'ggm-member-dashboard' ); ?></p>
			<div class="ggm-repeater-block" style="padding:16px;border:1px solid #dcdcde;border-radius:4px;">
				<h4 style="margin-top:0;"><?php esc_html_e( 'Additional Block Layout', 'ggm-member-dashboard' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Choose columns and visible rows for each shortcode. Default keeps its existing layout and shows all valid entries. A selected row limit displays at most Columns × Rows entries.', 'ggm-member-dashboard' ); ?></p>
				<table class="widefat striped" style="max-width:680px;">
					<thead><tr><th><?php esc_html_e( 'Section', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Columns', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Rows', 'ggm-member-dashboard' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( array( 'discover', 'why_different', 'perfect_for' ) as $layout_key ) : $layout = is_array( $additional_block_layouts[ $layout_key ] ?? null ) ? $additional_block_layouts[ $layout_key ] : array(); $layout_label = ggm_get_workshop_block_heading( $layout_key, $post->ID ); ?>
							<tr>
								<td><strong><?php echo esc_html( $layout_label ); ?></strong></td>
								<td><select name="ggm_workshop_additional_block_layouts[<?php echo esc_attr( $layout_key ); ?>][columns]"><option value="0" <?php selected( absint( $layout['columns'] ?? 0 ), 0 ); ?>><?php esc_html_e( 'Default (current layout)', 'ggm-member-dashboard' ); ?></option><?php for ( $layout_number = 1; $layout_number <= 8; $layout_number++ ) : ?><option value="<?php echo esc_attr( $layout_number ); ?>" <?php selected( absint( $layout['columns'] ?? 0 ), $layout_number ); ?>><?php echo esc_html( $layout_number ); ?></option><?php endfor; ?></select></td>
								<td><select name="ggm_workshop_additional_block_layouts[<?php echo esc_attr( $layout_key ); ?>][rows]"><option value="0" <?php selected( absint( $layout['rows'] ?? 0 ), 0 ); ?>><?php esc_html_e( 'All rows (default)', 'ggm-member-dashboard' ); ?></option><?php for ( $layout_number = 1; $layout_number <= 10; $layout_number++ ) : ?><option value="<?php echo esc_attr( $layout_number ); ?>" <?php selected( absint( $layout['rows'] ?? 0 ), $layout_number ); ?>><?php echo esc_html( $layout_number ); ?></option><?php endfor; ?></select></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="ggm-repeater-block ggm-workshop-why-workshop-different-settings" data-key="ggm_workshop_why_workshop_different_points">
				<?php $why_workshop_default_heading = sprintf( __( 'Why %s is Different', 'ggm-member-dashboard' ), get_the_title( $post->ID ) ?: __( 'This Workshop', 'ggm-member-dashboard' ) ); $why_workshop_heading = get_post_meta( $post->ID, 'ggm_workshop_why_workshop_different_heading', true ) ?: $why_workshop_default_heading; ?>
				<div class="ggm-block-heading-editor">
					<div class="ggm-block-heading-editor__display"><h4><?php echo esc_html( $why_workshop_heading ); ?> <button type="button" class="ggm-block-heading-edit" aria-label="<?php esc_attr_e( 'Edit section heading', 'ggm-member-dashboard' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button></h4></div>
					<div class="ggm-block-heading-editor__form" hidden><label class="screen-reader-text" for="ggm-workshop-why-workshop-different-heading"><?php esc_html_e( 'Section heading', 'ggm-member-dashboard' ); ?></label><input type="text" name="ggm_workshop_why_workshop_different_heading" id="ggm-workshop-why-workshop-different-heading" value="<?php echo esc_attr( get_post_meta( $post->ID, 'ggm_workshop_why_workshop_different_heading', true ) ); ?>" placeholder="<?php echo esc_attr( $why_workshop_default_heading ); ?>" class="large-text" disabled><button type="submit" class="button button-primary ggm-block-heading-save"><?php esc_html_e( 'Save heading', 'ggm-member-dashboard' ); ?></button></div>
				</div>
				<p class="description"><?php esc_html_e( 'Use [ggm_workshop_why_workshop_is_different]. Edit the heading above to change the frontend title. Leave it blank to use “Why {Workshop Name} is Different”.', 'ggm-member-dashboard' ); ?></p>
				<p><label for="ggm-workshop-why-workshop-different-intro"><strong><?php esc_html_e( 'Introductory Text', 'ggm-member-dashboard' ); ?></strong><br><textarea name="ggm_workshop_why_workshop_different_intro" id="ggm-workshop-why-workshop-different-intro" class="large-text" rows="3"><?php echo esc_textarea( get_post_meta( $post->ID, 'ggm_workshop_why_workshop_different_intro', true ) ); ?></textarea></label></p>
				<p><strong><?php esc_html_e( 'Tick Points', 'ggm-member-dashboard' ); ?></strong></p>
				<table class="widefat striped ggm-repeater-table" id="ggm-table-ggm_workshop_why_workshop_different_points"><thead><tr><th><?php esc_html_e( 'Point', 'ggm-member-dashboard' ); ?></th><th style="width:80px;"><?php esc_html_e( 'Action', 'ggm-member-dashboard' ); ?></th></tr></thead><tbody>
					<?php $why_workshop_points_raw = get_post_meta( $post->ID, 'ggm_workshop_why_workshop_different_points', true ); $why_workshop_points = json_decode( $why_workshop_points_raw, true ); if ( ! is_array( $why_workshop_points ) && '' !== trim( (string) $why_workshop_points_raw ) ) { $why_workshop_points = array_map( static function( $point ) { return array( 'text' => trim( $point ) ); }, preg_split( '/\r\n|\r|\n/', $why_workshop_points_raw ) ); } $why_workshop_points = is_array( $why_workshop_points ) ? $why_workshop_points : array(); foreach ( $why_workshop_points as $index => $point ) : ?>
						<tr data-index="<?php echo esc_attr( $index ); ?>"><td><input type="text" name="ggm_workshop_why_workshop_different_points[<?php echo esc_attr( $index ); ?>][text]" value="<?php echo esc_attr( $point['text'] ?? '' ); ?>" class="widefat"></td><td><button type="button" class="button button-link-delete ggm-remove-row-btn"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button></td></tr>
					<?php endforeach; ?>
				</tbody></table>
				<p><button type="button" class="button ggm-add-row-btn" data-key="ggm_workshop_why_workshop_different_points"><?php esc_html_e( '+ Add Tick Point', 'ggm-member-dashboard' ); ?></button></p>
				<p><strong><?php esc_html_e( 'Right-side Image', 'ggm-member-dashboard' ); ?></strong><br><?php $this->render_repeater_image_control( 'ggm_workshop_why_workshop_different_image', 'ggm-workshop-why-workshop-different-image', get_post_meta( $post->ID, 'ggm_workshop_why_workshop_different_image', true ) ); ?></p>
			</div>

			<div class="ggm-repeater-block" data-key="ggm_workshop_journey_days">
				<h4><?php esc_html_e( 'Workshop Journey', 'ggm-member-dashboard' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Use [ggm_workshop_journey]. Seven day cards display at once; additional cards are available with arrows. Cards are linked in the frontend timeline.', 'ggm-member-dashboard' ); ?></p>
				<p><label for="ggm-workshop-journey-heading"><strong><?php esc_html_e( 'Section Heading', 'ggm-member-dashboard' ); ?></strong><br><input type="text" name="ggm_workshop_journey_heading" id="ggm-workshop-journey-heading" value="<?php echo esc_attr( get_post_meta( $post->ID, 'ggm_workshop_journey_heading', true ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Your 7-Day Journey', 'ggm-member-dashboard' ); ?>"></label></p>
				<table class="widefat striped ggm-repeater-table ggm-workshop-content-repeater" id="ggm-table-ggm_workshop_journey_days"><thead><tr><th><?php esc_html_e( 'Day Label', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Short Description', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'Icon or Image', 'ggm-member-dashboard' ); ?></th><th style="width:80px;"><?php esc_html_e( 'Action', 'ggm-member-dashboard' ); ?></th></tr></thead><tbody>
					<?php $journey_days = json_decode( get_post_meta( $post->ID, 'ggm_workshop_journey_days', true ), true ); $journey_days = is_array( $journey_days ) ? $journey_days : array(); foreach ( $journey_days as $index => $journey_day ) : ?>
						<tr data-index="<?php echo esc_attr( $index ); ?>"><td><input type="text" name="ggm_workshop_journey_days[<?php echo esc_attr( $index ); ?>][day]" value="<?php echo esc_attr( $journey_day['day'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Day 1', 'ggm-member-dashboard' ); ?>"></td><td><textarea name="ggm_workshop_journey_days[<?php echo esc_attr( $index ); ?>][description]" class="widefat" rows="2"><?php echo esc_textarea( $journey_day['description'] ?? '' ); ?></textarea></td><td class="ggm-repeater-media-cell"><?php $this->render_repeater_image_control( 'ggm_workshop_journey_days[' . $index . '][image]', 'ggm-journey-day-' . $index, $journey_day['image'] ?? '' ); ?></td><td><button type="button" class="button button-link-delete ggm-remove-row-btn"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button></td></tr>
					<?php endforeach; ?>
				</tbody></table>
				<p><button type="button" class="button ggm-add-row-btn" data-key="ggm_workshop_journey_days"><?php esc_html_e( '+ Add Day', 'ggm-member-dashboard' ); ?></button></p>
				<p><strong><?php esc_html_e( 'Footer Boxes (maximum four)', 'ggm-member-dashboard' ); ?></strong></p>
				<?php $journey_footer_items = get_post_meta( $post->ID, 'ggm_workshop_journey_footer_items', true ); $journey_footer_items = is_array( $journey_footer_items ) ? array_slice( $journey_footer_items, 0, 4 ) : array(); ?>
				<div style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:16px;max-width:760px;">
					<?php for ( $journey_footer_index = 0; $journey_footer_index < 4; $journey_footer_index++ ) : $journey_footer_item = $journey_footer_items[ $journey_footer_index ] ?? array(); ?>
						<div><p style="margin:0 0 6px;"><strong><?php printf( esc_html__( 'Box %d', 'ggm-member-dashboard' ), $journey_footer_index + 1 ); ?></strong></p><?php $this->render_repeater_image_control( 'ggm_workshop_journey_footer_items[' . $journey_footer_index . '][icon]', 'ggm-journey-footer-' . $journey_footer_index, $journey_footer_item['icon'] ?? '' ); ?><p style="margin:8px 0 0;"><input type="text" name="ggm_workshop_journey_footer_items[<?php echo esc_attr( $journey_footer_index ); ?>][text]" value="<?php echo esc_attr( $journey_footer_item['text'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Footer box text', 'ggm-member-dashboard' ); ?>"></p></div>
					<?php endfor; ?>
				</div>
			</div>

			<!-- Repeater: ggm_you_will_discover -->
			<div class="ggm-repeater-block" data-key="ggm_you_will_discover">
				<div class="ggm-block-heading-editor">
					<div class="ggm-block-heading-editor__display">
						<h4><?php echo esc_html( ggm_get_workshop_block_heading( 'discover', $post->ID ) ); ?> <button type="button" class="ggm-block-heading-edit" aria-label="<?php esc_attr_e( 'Edit block heading', 'ggm-member-dashboard' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button></h4>
					</div>
					<div class="ggm-block-heading-editor__form" hidden>
						<label class="screen-reader-text" for="ggm-workshop-discover-heading-override"><?php esc_html_e( 'Block heading', 'ggm-member-dashboard' ); ?></label>
						<input type="text" name="ggm_workshop_discover_heading_override" id="ggm-workshop-discover-heading-override" value="<?php echo esc_attr( get_post_meta( $post->ID, 'ggm_workshop_discover_heading_override', true ) ); ?>" placeholder="<?php echo esc_attr( ggm_get_workshop_block_heading( 'discover' ) ); ?>" class="large-text" disabled>
						<button type="submit" class="button button-primary ggm-block-heading-save"><?php esc_html_e( 'Save heading', 'ggm-member-dashboard' ); ?></button>
					</div>
				</div>
				<table class="widefat striped ggm-repeater-table ggm-workshop-content-repeater ggm-workshop-content-repeater--description" id="ggm-table-ggm_you_will_discover">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Heading', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Description', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Icon Image', 'ggm-member-dashboard' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Action', 'ggm-member-dashboard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// Renamed from the unprefixed 'you_will_discover' meta key, which
						// collided with a leftover ACF field of the same name on this
						// site — ACF's own save routine was overwriting our saved rows
						// right after we wrote them. Old data still displays via the
						// fallback below; every save from now on writes the new key.
						$discover_raw = get_post_meta( $post->ID, 'ggm_you_will_discover', true );
						if ( '' === $discover_raw ) {
							$discover_raw = get_post_meta( $post->ID, 'you_will_discover', true );
						}
						$discover = json_decode( $discover_raw, true ) ?: array();
						foreach ( $discover as $index => $item ) :
							?>
							<tr data-index="<?php echo esc_attr( $index ); ?>">
								<td><input type="text" name="ggm_you_will_discover[<?php echo esc_attr( $index ); ?>][heading]" value="<?php echo esc_attr( $item['heading'] ?? '' ); ?>" class="widefat"></td>
								<td><textarea name="ggm_you_will_discover[<?php echo esc_attr( $index ); ?>][description]" class="widefat" rows="2"><?php echo esc_textarea( $item['description'] ?? '' ); ?></textarea></td>
								<td class="ggm-repeater-media-cell">
									<?php $this->render_repeater_image_control( 'ggm_you_will_discover[' . $index . '][image]', 'yd-' . $index, $item['image'] ?? '' ); ?>
								</td>
								<td><button type="button" class="button button-link-delete ggm-remove-row-btn"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button ggm-add-row-btn" data-key="ggm_you_will_discover"><?php esc_html_e( '+ Add Discover Point', 'ggm-member-dashboard' ); ?></button></p>
				<p><label for="ggm-workshop-discover-bottom-text"><strong><?php esc_html_e( 'Bottom Text (optional)', 'ggm-member-dashboard' ); ?></strong><br><textarea name="ggm_workshop_discover_bottom_text" id="ggm-workshop-discover-bottom-text" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'Shown below this section’s configured items.', 'ggm-member-dashboard' ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, 'ggm_workshop_discover_bottom_text', true ) ); ?></textarea></label></p>
			</div>

			<!-- Repeater: ggm_why_different_points -->
			<div class="ggm-repeater-block" data-key="ggm_why_different_points">
				<div class="ggm-block-heading-editor">
					<div class="ggm-block-heading-editor__display">
						<h4><?php echo esc_html( ggm_get_workshop_block_heading( 'why_different', $post->ID ) ); ?> <button type="button" class="ggm-block-heading-edit" aria-label="<?php esc_attr_e( 'Edit block heading', 'ggm-member-dashboard' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button></h4>
					</div>
					<div class="ggm-block-heading-editor__form" hidden>
						<label class="screen-reader-text" for="ggm-workshop-why-different-heading-override"><?php esc_html_e( 'Block heading', 'ggm-member-dashboard' ); ?></label>
						<input type="text" name="ggm_workshop_why_different_heading_override" id="ggm-workshop-why-different-heading-override" value="<?php echo esc_attr( get_post_meta( $post->ID, 'ggm_workshop_why_different_heading_override', true ) ); ?>" placeholder="<?php echo esc_attr( ggm_get_workshop_block_heading( 'why_different' ) ); ?>" class="large-text" disabled>
						<button type="submit" class="button button-primary ggm-block-heading-save"><?php esc_html_e( 'Save heading', 'ggm-member-dashboard' ); ?></button>
					</div>
				</div>
				<table class="widefat striped ggm-repeater-table ggm-workshop-content-repeater ggm-workshop-content-repeater--media" id="ggm-table-ggm_why_different_points">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Icon Image', 'ggm-member-dashboard' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Action', 'ggm-member-dashboard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// Renamed from the unprefixed 'why_different_points' meta key to
						// avoid any future collision with an ACF/SCF field of the same
						// name (the same class of bug that hit 'you_will_discover').
						$diff_raw = get_post_meta( $post->ID, 'ggm_why_different_points', true );
						if ( '' === $diff_raw ) {
							$diff_raw = get_post_meta( $post->ID, 'why_different_points', true );
						}
						$diff = json_decode( $diff_raw, true ) ?: array();
						foreach ( $diff as $index => $item ) :
							?>
							<tr data-index="<?php echo esc_attr( $index ); ?>">
								<td><input type="text" name="ggm_why_different_points[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>" class="widefat"></td>
								<td class="ggm-repeater-media-cell">
									<?php $this->render_repeater_image_control( 'ggm_why_different_points[' . $index . '][icon]', 'wd-' . $index, $item['icon'] ?? '' ); ?>
								</td>
								<td><button type="button" class="button button-link-delete ggm-remove-row-btn"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button ggm-add-row-btn" data-key="ggm_why_different_points"><?php esc_html_e( '+ Add Point', 'ggm-member-dashboard' ); ?></button></p>
				<p><label for="ggm-workshop-why-different-bottom-text"><strong><?php esc_html_e( 'Bottom Text (optional)', 'ggm-member-dashboard' ); ?></strong><br><textarea name="ggm_workshop_why_different_bottom_text" id="ggm-workshop-why-different-bottom-text" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'Shown below this section’s configured items.', 'ggm-member-dashboard' ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, 'ggm_workshop_why_different_bottom_text', true ) ); ?></textarea></label></p>
			</div>

			<!-- Repeater: ggm_perfect_for_you -->
			<div class="ggm-repeater-block" data-key="ggm_perfect_for_you">
				<div class="ggm-block-heading-editor">
					<div class="ggm-block-heading-editor__display">
						<h4><?php echo esc_html( ggm_get_workshop_block_heading( 'perfect_for', $post->ID ) ); ?> <button type="button" class="ggm-block-heading-edit" aria-label="<?php esc_attr_e( 'Edit block heading', 'ggm-member-dashboard' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button></h4>
					</div>
					<div class="ggm-block-heading-editor__form" hidden>
						<label class="screen-reader-text" for="ggm-workshop-perfect-for-heading-override"><?php esc_html_e( 'Block heading', 'ggm-member-dashboard' ); ?></label>
						<input type="text" name="ggm_workshop_perfect_for_heading_override" id="ggm-workshop-perfect-for-heading-override" value="<?php echo esc_attr( get_post_meta( $post->ID, 'ggm_workshop_perfect_for_heading_override', true ) ); ?>" placeholder="<?php echo esc_attr( ggm_get_workshop_block_heading( 'perfect_for' ) ); ?>" class="large-text" disabled>
						<button type="submit" class="button button-primary ggm-block-heading-save"><?php esc_html_e( 'Save heading', 'ggm-member-dashboard' ); ?></button>
					</div>
				</div>
				<table class="widefat striped ggm-repeater-table ggm-workshop-content-repeater ggm-workshop-content-repeater--description" id="ggm-table-ggm_perfect_for_you">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Description', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Image', 'ggm-member-dashboard' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Action', 'ggm-member-dashboard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// Renamed from the unprefixed 'perfect_for_you' meta key — see note above.
						$perf_raw = get_post_meta( $post->ID, 'ggm_perfect_for_you', true );
						if ( '' === $perf_raw ) {
							$perf_raw = get_post_meta( $post->ID, 'perfect_for_you', true );
						}
						$perf = json_decode( $perf_raw, true ) ?: array();
						foreach ( $perf as $index => $item ) :
							?>
							<tr data-index="<?php echo esc_attr( $index ); ?>">
								<td><input type="text" name="ggm_perfect_for_you[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>" class="widefat"></td>
								<td><textarea name="ggm_perfect_for_you[<?php echo esc_attr( $index ); ?>][description]" class="widefat" rows="2"><?php echo esc_textarea( $item['description'] ?? '' ); ?></textarea></td>
								<td class="ggm-repeater-media-cell">
									<?php $this->render_repeater_image_control( 'ggm_perfect_for_you[' . $index . '][image]', 'pf-' . $index, $item['image'] ?? '' ); ?>
								</td>
								<td><button type="button" class="button button-link-delete ggm-remove-row-btn"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button ggm-add-row-btn" data-key="ggm_perfect_for_you"><?php esc_html_e( '+ Add Reason', 'ggm-member-dashboard' ); ?></button></p>
				<p><label for="ggm-workshop-perfect-for-bottom-text"><strong><?php esc_html_e( 'Bottom Text (optional)', 'ggm-member-dashboard' ); ?></strong><br><textarea name="ggm_workshop_perfect_for_bottom_text" id="ggm-workshop-perfect-for-bottom-text" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'Shown below this section’s configured items.', 'ggm-member-dashboard' ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, 'ggm_workshop_perfect_for_bottom_text', true ) ); ?></textarea></label></p>
			</div>

			<!-- Repeater: ggm_faq -->
			<div class="ggm-repeater-block" data-key="ggm_faq">
				<div class="ggm-block-heading-editor">
					<div class="ggm-block-heading-editor__display">
						<h4><?php echo esc_html( ggm_get_workshop_block_heading( 'faq', $post->ID ) ); ?> <button type="button" class="ggm-block-heading-edit" aria-label="<?php esc_attr_e( 'Edit block heading', 'ggm-member-dashboard' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button></h4>
					</div>
					<div class="ggm-block-heading-editor__form" hidden>
						<label class="screen-reader-text" for="ggm-workshop-faq-heading-override"><?php esc_html_e( 'Block heading', 'ggm-member-dashboard' ); ?></label>
						<input type="text" name="ggm_workshop_faq_heading_override" id="ggm-workshop-faq-heading-override" value="<?php echo esc_attr( get_post_meta( $post->ID, 'ggm_workshop_faq_heading_override', true ) ); ?>" placeholder="<?php echo esc_attr( ggm_get_workshop_block_heading( 'faq' ) ); ?>" class="large-text" disabled>
						<button type="submit" class="button button-primary ggm-block-heading-save"><?php esc_html_e( 'Save heading', 'ggm-member-dashboard' ); ?></button>
					</div>
				</div>
				<table class="widefat striped ggm-repeater-table ggm-workshop-content-repeater ggm-workshop-content-repeater--faq" id="ggm-table-ggm_faq">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Question', 'ggm-member-dashboard' ); ?></th>
							<th><?php esc_html_e( 'Answer', 'ggm-member-dashboard' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Action', 'ggm-member-dashboard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// Renamed from the unprefixed 'faq' meta key, which collides
						// directly with an active ACF/SCF field of the same name on
						// this site — that plugin's own save routine was overwriting
						// our saved rows right after we wrote them (and export/import
						// read back its empty value instead of ours).
						$faqs_raw = get_post_meta( $post->ID, 'ggm_faq', true );
						if ( '' === $faqs_raw ) {
							$faqs_raw = get_post_meta( $post->ID, 'faq', true );
						}
						$faqs = json_decode( $faqs_raw, true ) ?: array();
						foreach ( $faqs as $index => $item ) :
							?>
							<tr data-index="<?php echo esc_attr( $index ); ?>">
								<td><input type="text" name="ggm_faq[<?php echo esc_attr( $index ); ?>][question]" value="<?php echo esc_attr( $item['question'] ?? '' ); ?>" class="widefat"></td>
								<td><textarea name="ggm_faq[<?php echo esc_attr( $index ); ?>][answer]" class="widefat" rows="2"><?php echo esc_textarea( $item['answer'] ?? '' ); ?></textarea></td>
								<td><button type="button" class="button button-link-delete ggm-remove-row-btn"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button ggm-add-row-btn" data-key="ggm_faq"><?php esc_html_e( '+ Add FAQ Row', 'ggm-member-dashboard' ); ?></button></p>
			</div>
		</div>
		<?php
		$this->print_repeaters_js();
		$this->print_workshop_admin_configuration_js();
	}

	/** Apply the global admin-only Workshop editor order/visibility configuration. */
	private function print_workshop_admin_configuration_js() {
		if ( ! class_exists( 'GGM_Workshop_Admin_Config' ) ) { return; }
		$config = GGM_Workshop_Admin_Config::get();
		?>
		<script>
		(function($){
			var config=<?php echo wp_json_encode( $config ); ?>;
			function row(selector){return $(selector).first().closest('tr');}
			var fields={
				mentors:row('[name="ggm_mentor_ids[]"]'), linked_course:row('[name="linked_course_id"]'), countdown:row('[name="Counter_Start_Date"]'), preparatory_date:row('[name="workshop_preparatory_date"]'), date_range:row('[name="workshop_start_date"]'), pricing:row('[name="workshop_regular_price"]'), mode:row('[name="workshop_mode"]'), header_pill:row('[name="ggm_workshop_header_pill_text"]'), whatsapp:row('[name="ggm_workshop_whatsapp_group_url"]'), language:row('[name="workshop_language"]'), duration:row('[name="duration"]'), is_free:row('[name="is_free"]'), featured_video:row('[name="ggm_workshop_featured_video_url"]'), bottom_image:row('[name="ggm_workshop_bottom_image_id"]'), hero:row('[name="ggm_workshop_booking_card_hero_header"]'), cta:row('[name="ggm_workshop_booking_card_cta_heading"]'), social_proof:row('[name="ggm_workshop_booking_card_testimonial_text"]'), heading:row('[name="ggm_workshop_form_text_heading"]'), content:row('[name="ggm_workshop_form_text_content"]')
			};
			var sections={
				time_slots:$('#ggm-table-workshop_time_slots').closest('.ggm-repeater-block,div'), additional_layout:$('[name^="ggm_workshop_additional_block_layouts"]').closest('div'), discover:$('#ggm-table-ggm_you_will_discover').closest('.ggm-repeater-block'), why_different:$('#ggm-table-ggm_why_different_points').closest('.ggm-repeater-block'), why_workshop_different:$('#ggm-table-ggm_workshop_why_workshop_different_points').closest('.ggm-repeater-block'), journey:$('#ggm-table-ggm_workshop_journey_days').closest('.ggm-repeater-block'), perfect_for:$('#ggm-table-ggm_perfect_for_you').closest('.ggm-repeater-block'), faq:$('#ggm-table-ggm_faq').closest('.ggm-repeater-block')
			};
			var $root=$('#ggm_workshop_details');
			if(!$root.length)return;
			$.each(config.fields||{},function(section,items){$.each(items,function(_,item){var $node=fields[item.id];if(!$node||!$node.length)return;$node.toggle(!!item.enabled);if(item.enabled){$node.appendTo($node.parent());}});});
			$.each(config.sections||[],function(_,section){var $node=sections[section.id];if(!$node||!$node.length)return;$node.toggle(!!section.enabled);if(section.enabled){$node.appendTo($root.find('.ggm-meta-container').last());}});
			var $table=$root.find('table.ggm-meta-table').first();$.each((config.fields||{}).workshop_details||[],function(_,item){var $node=fields[item.id];if($node&&$node.length&&item.enabled)$table.append($node);});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Render Course details meta box.
	 */
	public function render_course_details_mb( $post ) {
		wp_nonce_field( 'ggm_meta_box_save', 'ggm_meta_nonce' );

		$course_price              = get_post_meta( $post->ID, 'course_price', true );
		$course_short_description  = get_post_meta( $post->ID, 'course_short_description', true );
		$course_language           = strtolower( get_post_meta( $post->ID, 'course_language', true ) ?: 'english' );
		$course_language           = in_array( $course_language, array( 'english', 'hindi' ), true ) ? $course_language : 'english';
		$ggm_mentor_ids            = get_post_meta( $post->ID, 'ggm_mentor_ids', true );
		$ggm_mentor_ids            = is_array( $ggm_mentor_ids ) ? array_map( 'absint', $ggm_mentor_ids ) : array();
		$currency_adjustments      = class_exists( 'GGM_Currency' ) ? GGM_Currency::adjustments_for_item( $post->ID ) : array();
		$enabled_currencies        = class_exists( 'GGM_Currency' ) ? GGM_Currency::enabled_codes() : array( 'INR' );
		$base_currency_code        = class_exists( 'GGM_Currency' ) ? GGM_Currency::base_currency() : 'INR';
		$mentors                   = get_posts( array(
			'post_type'      => 'ggm_mentor',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		?>
		<div class="ggm-meta-container">
			<table class="form-table ggm-meta-table">
				<tr>
					<th><label for="ggm-course-price"><?php esc_html_e( 'Course Price', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="text" name="course_price" id="ggm-course-price" value="<?php echo esc_attr( $course_price ); ?>" class="regular-text" placeholder="e.g. Free or 999">
						<p class="description"><?php esc_html_e( 'Set 0 or Free to unlock for everyone. Set a paid amount for direct course purchase.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<?php if ( class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled() ) : ?>
				<tr>
					<th><?php esc_html_e( 'Manual Currency Extras', 'ggm-member-dashboard' ); ?></th>
					<td>
						<div class="ggm-currency-adjustments">
							<?php foreach ( $enabled_currencies as $currency_code ) : if ( $currency_code === $base_currency_code ) { continue; } ?>
								<label>
									<span><?php echo esc_html( $currency_code ); ?></span>
									<input type="number" min="0" step="0.01" name="ggm_currency_adjustments[<?php echo esc_attr( $currency_code ); ?>]" value="<?php echo esc_attr( $currency_adjustments[ $currency_code ] ?? '' ); ?>" placeholder="0.00">
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php printf( esc_html__( 'Optional extra amount added after the %s course price is converted.', 'ggm-member-dashboard' ), esc_html( $base_currency_code ) ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><label for="ggm-course-desc"><?php esc_html_e( 'Course Short Description', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<textarea name="course_short_description" id="ggm-course-desc" class="large-text" rows="3"><?php echo esc_textarea( $course_short_description ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Instructors / Mentors', 'ggm-member-dashboard' ); ?></th>
					<td>
						<div class="ggm-checkbox-list">
							<?php if ( empty( $mentors ) ) : ?><p class="description"><?php esc_html_e( 'No mentor profiles found. Add one under the Mentors menu.', 'ggm-member-dashboard' ); ?></p><?php endif; ?>
							<?php foreach ( $mentors as $mentor ) : ?>
								<label class="ggm-checkbox-list__item"><input type="checkbox" name="ggm_mentor_ids[]" value="<?php echo esc_attr( $mentor->ID ); ?>" <?php checked( in_array( $mentor->ID, $ggm_mentor_ids, true ) ); ?>> <?php echo esc_html( $mentor->post_title ); ?></label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Select one or more mentors. Their names are used as the course instructors.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-course-lang"><?php esc_html_e( 'Language', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<select name="course_language" id="ggm-course-lang" class="regular-text">
							<option value="english" <?php selected( $course_language, 'english' ); ?>><?php esc_html_e( 'English', 'ggm-member-dashboard' ); ?></option>
							<option value="hindi" <?php selected( $course_language, 'hindi' ); ?>><?php esc_html_e( 'Hindi', 'ggm-member-dashboard' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render Course Lessons meta box — inline add form + paginated + sortable table.
	 */
	public function render_course_lessons_mb( $post ) {
		if ( ! $post->ID ) {
			echo '<p class="description">' . esc_html__( 'Save this course first, then you can add lessons.', 'ggm-member-dashboard' ) . '</p>';
			return;
		}

		$cid   = (int) $post->ID;
		$per_page = 10;
		$nonce = wp_create_nonce( 'ggm_inline_lesson_' . $cid );

		// Correct total — include both publish and draft.
		$total = count( get_posts( array(
			'post_type'      => array( 'lesson', 'ggm_lesson' ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => 'course', 'value' => $cid ) ),
		) ) );
		?>
		<style>
		.ggm-drag-handle { cursor: grab; color: #94a3b8; font-size: 16px; user-select: none; text-align: center; }
		.ggm-drag-handle:hover { color: #475569; }
		.ggm-sort-placeholder { background: #e0f2fe !important; border: 2px dashed #38bdf8 !important; visibility: visible !important; height: 38px; }
		.ggm-sort-placeholder td { padding: 0 !important; }
		tbody.ui-sortable tr { transition: background 0.15s; }
		tbody.ui-sortable tr.ui-sortable-helper { box-shadow: 0 4px 12px rgba(0,0,0,0.15); background: #fff; opacity: 0.95; }
		.ggm-move-btn { padding: 0 5px !important; min-width: 24px; line-height: 22px !important; height: 24px; font-size: 12px !important; }
		.ggm-edit-row > td { padding: 0 !important; }
		.ggm-edit-row .ggm-el-inner { padding: 18px 22px; background: #f0f9ff; border-top: 3px solid #3b82f6; border-bottom: 3px solid #3b82f6; }
		.ggm-edit-row .form-table th { width: 150px; padding: 7px 10px; font-weight: 600; }
		.ggm-edit-row .form-table td { padding: 7px 10px; }
		.ggm-url-base { color: #64748b; font-size: 12px; word-break: break-all; }
		tr[data-lesson-id].ggm-editing > td { background: #e0f2fe !important; }
		</style>

		<div class="ggm-course-lessons-mb" id="ggm-lessons-wrap-<?php echo $cid; ?>">

			<!-- Header row -->
			<div style="margin-bottom:14px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
				<button type="button"
					class="button button-primary ggm-lesson-add-toggle"
					id="ggm-lesson-add-toggle-<?php echo $cid; ?>"
					data-course="<?php echo $cid; ?>">
					<?php esc_html_e( '+ Add New Lesson', 'ggm-member-dashboard' ); ?>
				</button>
				<span id="ggm-lessons-count-<?php echo $cid; ?>" class="description">
					<?php printf(
						esc_html( _n( '%d lesson in this course', '%d lessons in this course', $total, 'ggm-member-dashboard' ) ),
						$total
					); ?>
				</span>
				<span id="ggm-reorder-status-<?php echo $cid; ?>" style="font-size:12px; font-weight:600;"></span>
			</div>

			<!-- Inline Add Lesson Form -->
			<div id="ggm-lesson-add-form-<?php echo $cid; ?>"
				style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:20px 22px; margin-bottom:16px;">
				<h4 style="margin:0 0 16px; font-size:14px; font-weight:700; color:#1e293b;">
					<?php esc_html_e( 'New Lesson', 'ggm-member-dashboard' ); ?>
				</h4>
				<table class="form-table" style="margin:0;">
					<tr>
						<th style="width:160px; padding:8px 10px;"><label><?php esc_html_e( 'Lesson Title', 'ggm-member-dashboard' ); ?> <span style="color:red;">*</span></label></th>
						<td style="padding:8px 10px;"><input type="text" id="ggm-il-title-<?php echo $cid; ?>" class="regular-text" style="width:100%;"></td>
					</tr>
					<tr>
						<th style="padding:8px 10px;"><label><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></label></th>
						<td style="padding:8px 10px;">
							<select id="ggm-il-status-<?php echo $cid; ?>" class="regular-text">
								<option value="publish"><?php esc_html_e( 'Published', 'ggm-member-dashboard' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft', 'ggm-member-dashboard' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th style="padding:8px 10px;"><label><?php esc_html_e( 'Order', 'ggm-member-dashboard' ); ?></label></th>
						<td style="padding:8px 10px;">
							<input type="number" id="ggm-il-order-<?php echo $cid; ?>" class="small-text" value="<?php echo $total + 1; ?>" min="1">
							<span style="font-size:12px; color:#64748b; margin-left:6px;"><?php esc_html_e( '(auto-set to next available)', 'ggm-member-dashboard' ); ?></span>
						</td>
					</tr>
					<tr>
						<th style="padding:8px 10px;"><label><?php esc_html_e( 'Free Preview', 'ggm-member-dashboard' ); ?></label></th>
						<td style="padding:8px 10px;">
							<label>
								<input type="checkbox" id="ggm-il-preview-<?php echo $cid; ?>" value="1">
								<?php esc_html_e( 'Allow preview without membership', 'ggm-member-dashboard' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th style="padding:8px 10px;"><label><?php esc_html_e( 'YouTube Video URL', 'ggm-member-dashboard' ); ?></label></th>
						<td style="padding:8px 10px;">
							<input type="url" id="ggm-il-video-<?php echo $cid; ?>" class="large-text" placeholder="https://www.youtube.com/watch?v=...">
						</td>
					</tr>
				</table>
				<div style="margin-top:14px; display:flex; align-items:center; gap:10px;">
					<button type="button" class="button button-primary ggm-lesson-submit" data-course="<?php echo $cid; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Add Lesson', 'ggm-member-dashboard' ); ?>
					</button>
					<button type="button" class="button ggm-lesson-cancel" data-course="<?php echo $cid; ?>">
						<?php esc_html_e( 'Cancel', 'ggm-member-dashboard' ); ?>
					</button>
					<span class="spinner ggm-il-spinner-<?php echo $cid; ?>" style="float:none; margin:0;"></span>
					<span class="ggm-il-feedback-<?php echo $cid; ?>" style="font-weight:600; font-size:13px;"></span>
				</div>
			</div>

			<!-- Table + Pagination (refreshed by AJAX) -->
			<div id="ggm-lessons-table-<?php echo $cid; ?>">
				<?php echo self::render_lessons_table_html( $cid, 1, $per_page ); ?>
			</div>
		</div>

		<script>
		(function($){
			var cid      = <?php echo $cid; ?>;
			var perPage  = <?php echo $per_page; ?>;
			var nonce    = '<?php echo esc_js( $nonce ); ?>';
			var curPage  = 1;

			// ── Sortable drag-and-drop ────────────────────────────────────────
			function initSortable() {
				var $tbody = $('#ggm-lessons-table-' + cid + ' tbody');
				if ( ! $tbody.length || ! $.fn.sortable ) return;
				if ( $tbody.hasClass('ui-sortable') ) {
					try { $tbody.sortable('destroy'); } catch(e) {}
				}
				$tbody.sortable({
					handle      : '.ggm-drag-handle',
					axis        : 'y',
					cursor      : 'grabbing',
					placeholder : 'ggm-sort-placeholder',
					forcePlaceholderSize: true,
					helper: function(e, tr) {
						var $cells = tr.children(), helper = tr.clone();
						helper.children().each(function(i){ $(this).width($cells.eq(i).width()); });
						return helper;
					},
					stop: function() { saveOrder(); }
				});
			}

			// ── Send new order to server ──────────────────────────────────────
			function saveOrder() {
				var ids = [];
				$('#ggm-lessons-table-' + cid + ' tbody tr[data-lesson-id]').each(function(){
					ids.push( $(this).data('lesson-id') );
				});
				if ( ! ids.length ) return;

				var $st = $('#ggm-reorder-status-' + cid);
				$st.css('color','#64748b').text('<?php echo esc_js( __( 'Saving order…', 'ggm-member-dashboard' ) ); ?>');

				$.post(ajaxurl, {
					action      : 'ggm_reorder_lessons',
					nonce       : nonce,
					course_id   : cid,
					ordered_ids : ids,
					paged       : curPage,
					per_page    : perPage
				}, function(res){
					if ( res.success ) {
						$('#ggm-lessons-table-' + cid).html( res.data.table_html );
						$st.css('color','#15803d').text('<?php echo esc_js( __( 'Order saved ✓', 'ggm-member-dashboard' ) ); ?>');
						initSortable();
						setTimeout(function(){ $st.text(''); }, 3000);
					} else {
						$st.css('color','#b91c1c').text('<?php echo esc_js( __( 'Failed to save order.', 'ggm-member-dashboard' ) ); ?>');
					}
				}).fail(function(){
					$st.css('color','#b91c1c').text('<?php echo esc_js( __( 'Server error.', 'ggm-member-dashboard' ) ); ?>');
				});
			}

			// ── ↑ / ↓ move buttons (cross-page safe) ─────────────────────────
			$(document).on('click', '.ggm-move-up[data-course="' + cid + '"], .ggm-move-down[data-course="' + cid + '"]', function(){
				var dir    = $(this).hasClass('ggm-move-up') ? 'up' : 'down';
				var lid    = $(this).data('lesson-id');
				var $st    = $('#ggm-reorder-status-' + cid);
				var $table = $('#ggm-lessons-table-' + cid);

				$table.css('opacity', 0.5);
				$st.css('color','#64748b').text('<?php echo esc_js( __( 'Moving…', 'ggm-member-dashboard' ) ); ?>');

				$.post(ajaxurl, {
					action    : 'ggm_move_lesson',
					nonce     : nonce,
					course_id : cid,
					lesson_id : lid,
					direction : dir,
					paged     : curPage,
					per_page  : perPage
				}, function(res){
					$table.css('opacity', 1);
					if ( res.success ) {
						$table.html( res.data.table_html );
						if ( res.data.paged ) curPage = res.data.paged;
						$st.css('color','#15803d').text('<?php echo esc_js( __( 'Order saved ✓', 'ggm-member-dashboard' ) ); ?>');
						initSortable();
						setTimeout(function(){ $st.text(''); }, 3000);
					} else {
						$st.css('color','#b91c1c').text('<?php echo esc_js( __( 'Failed to move.', 'ggm-member-dashboard' ) ); ?>');
					}
				}).fail(function(){
					$table.css('opacity', 1);
					$st.css('color','#b91c1c').text('<?php echo esc_js( __( 'Server error.', 'ggm-member-dashboard' ) ); ?>');
				});
			});

			// ── Toggle add form ───────────────────────────────────────────────
			$(document).on('click', '#ggm-lesson-add-toggle-' + cid, function(){
				var $form = $('#ggm-lesson-add-form-' + cid);
				if ( $form.is(':hidden') ) {
					$form.slideDown(200);
					$(this).text('<?php echo esc_js( __( '✕ Cancel', 'ggm-member-dashboard' ) ); ?>').removeClass('button-primary');
				} else {
					$form.slideUp(200);
					$(this).text('<?php echo esc_js( __( '+ Add New Lesson', 'ggm-member-dashboard' ) ); ?>').addClass('button-primary');
				}
			});

			$(document).on('click', '.ggm-lesson-cancel[data-course="' + cid + '"]', function(){
				$('#ggm-lesson-add-form-' + cid).slideUp(200);
				$('#ggm-lesson-add-toggle-' + cid).text('<?php echo esc_js( __( '+ Add New Lesson', 'ggm-member-dashboard' ) ); ?>').addClass('button-primary');
			});

			// ── Submit new lesson ─────────────────────────────────────────────
			$(document).on('click', '.ggm-lesson-submit[data-course="' + cid + '"]', function(){
				var title = $('#ggm-il-title-' + cid).val().trim();
				if ( ! title ) {
					$('.ggm-il-feedback-' + cid).css('color','#b91c1c').text('<?php echo esc_js( __( 'Lesson title is required.', 'ggm-member-dashboard' ) ); ?>');
					return;
				}
				var $btn = $(this);
				$btn.prop('disabled', true);
				$('.ggm-il-spinner-' + cid).addClass('is-active');
				$('.ggm-il-feedback-' + cid).text('');

				$.post(ajaxurl, {
					action       : 'ggm_inline_create_lesson',
					nonce        : $btn.data('nonce'),
					course_id    : cid,
					title        : title,
					status       : $('#ggm-il-status-' + cid).val(),
					lesson_order : $('#ggm-il-order-' + cid).val(),
					is_preview   : $('#ggm-il-preview-' + cid).is(':checked') ? 1 : 0,
					video_embed  : $('#ggm-il-video-' + cid).val()
				}, function(res){
					$btn.prop('disabled', false);
					$('.ggm-il-spinner-' + cid).removeClass('is-active');
					if ( res.success ) {
						$('#ggm-il-title-' + cid).val('');
						$('#ggm-il-video-' + cid).val('');
						$('#ggm-il-preview-' + cid).prop('checked', false);
						$('.ggm-il-feedback-' + cid).css('color','#15803d').text( res.data.message );
						$('#ggm-lessons-table-' + cid).html( res.data.table_html );
						$('#ggm-lessons-count-' + cid).text( res.data.count_label );
						$('#ggm-il-order-' + cid).val( res.data.next_order );
						initSortable();
					} else {
						$('.ggm-il-feedback-' + cid).css('color','#b91c1c').text( res.data.message || '<?php echo esc_js( __( 'Failed to create lesson.', 'ggm-member-dashboard' ) ); ?>' );
					}
				}).fail(function(){
					$btn.prop('disabled', false);
					$('.ggm-il-spinner-' + cid).removeClass('is-active');
					$('.ggm-il-feedback-' + cid).css('color','#b91c1c').text('<?php echo esc_js( __( 'Server error. Please try again.', 'ggm-member-dashboard' ) ); ?>');
				});
			});

			// ── Pagination ────────────────────────────────────────────────────
			$(document).on('click', '.ggm-lessons-page-btn[data-course="' + cid + '"]', function(){
				var page   = $(this).data('page');
				var $table = $('#ggm-lessons-table-' + cid);
				$table.css('opacity', 0.5);
				$.post(ajaxurl, {
					action    : 'ggm_get_course_lessons_page',
					nonce     : nonce,
					course_id : cid,
					paged     : page,
					per_page  : perPage
				}, function(res){
					$table.css('opacity', 1);
					if ( res.success ) {
						curPage = page;
						$table.html( res.data.table_html );
						initSortable();
					}
				}).fail(function(){ $table.css('opacity', 1); });
			});

			// ── Inline Edit helpers ───────────────────────────────────────────
			function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
			function escA(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

			function closeEditRows() {
				$('#ggm-lessons-table-' + cid + ' .ggm-edit-row').remove();
				$('#ggm-lessons-table-' + cid + ' tr.ggm-editing').removeClass('ggm-editing');
				$('#ggm-lessons-table-' + cid + ' .ggm-lesson-edit-btn').prop('disabled', false).text('<?php echo esc_js( __( 'Edit', 'ggm-member-dashboard' ) ); ?>');
			}

			function buildEditRow(lid, d, baseUrl) {
				var slug = d.slug || '';
				var prev = baseUrl + slug + '/';
				var chk  = d.is_preview === '1' ? ' checked' : '';
				return '<tr class="ggm-edit-row" id="ggm-el-row-' + lid + '"><td colspan="8"><div class="ggm-el-inner">' +
					'<h4 style="margin:0 0 14px;font-size:13px;font-weight:700;color:#1e40af;"><?php echo esc_js( __( 'Editing:', 'ggm-member-dashboard' ) ); ?> ' + escH(d.title) + '</h4>' +
					'<table class="form-table" style="margin:0;">' +
						'<tr><th><?php echo esc_js( __( 'Title', 'ggm-member-dashboard' ) ); ?> <span style="color:red">*</span></th>' +
							'<td><input type="text" id="ggm-el-title-' + lid + '" class="regular-text" value="' + escA(d.title) + '" style="width:100%"></td></tr>' +
						'<tr><th><?php echo esc_js( __( 'Lesson URL', 'ggm-member-dashboard' ) ); ?></th>' +
							'<td>' +
								'<div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;">' +
									'<span class="ggm-url-base">' + escH(baseUrl) + '</span>' +
									'<input type="text" id="ggm-el-slug-' + lid + '" class="regular-text" value="' + escA(slug) + '" style="max-width:220px;" placeholder="lesson-slug">' +
									'<span class="ggm-url-base">/</span>' +
								'</div>' +
								'<p style="margin:4px 0 0;font-size:11px;color:#64748b;"><?php echo esc_js( __( 'Preview:', 'ggm-member-dashboard' ) ); ?> ' +
									'<a id="ggm-el-url-preview-' + lid + '" href="' + escA(prev) + '" target="_blank" rel="noopener" style="color:#2563eb;word-break:break-all;">' + escH(prev) + '</a>' +
								'</p>' +
							'</td></tr>' +
						'<tr><th><?php echo esc_js( __( 'Status', 'ggm-member-dashboard' ) ); ?></th>' +
							'<td><select id="ggm-el-status-' + lid + '" class="regular-text">' +
								'<option value="publish"' + (d.status==='publish'?' selected':'') + '><?php echo esc_js( __( 'Published', 'ggm-member-dashboard' ) ); ?></option>' +
								'<option value="draft"' + (d.status==='draft'?' selected':'') + '><?php echo esc_js( __( 'Draft', 'ggm-member-dashboard' ) ); ?></option>' +
							'</select></td></tr>' +
						'<tr><th><?php echo esc_js( __( 'Order', 'ggm-member-dashboard' ) ); ?></th>' +
							'<td><input type="number" id="ggm-el-order-' + lid + '" class="small-text" value="' + escA(d.lesson_order) + '" min="1"></td></tr>' +
						'<tr><th><?php echo esc_js( __( 'Free Preview', 'ggm-member-dashboard' ) ); ?></th>' +
							'<td><label><input type="checkbox" id="ggm-el-preview-' + lid + '" value="1"' + chk + '> <?php echo esc_js( __( 'Allow preview without membership', 'ggm-member-dashboard' ) ); ?></label></td></tr>' +
						'<tr><th><?php echo esc_js( __( 'YouTube Video URL', 'ggm-member-dashboard' ) ); ?></th>' +
							'<td><input type="url" id="ggm-el-video-' + lid + '" class="large-text" value="' + escA(d.video_embed) + '" placeholder="https://www.youtube.com/watch?v=..."></td></tr>' +
					'</table>' +
					'<div style="margin-top:12px;display:flex;align-items:center;gap:10px;">' +
						'<button type="button" class="button button-primary ggm-el-update" data-course="' + cid + '" data-lesson-id="' + lid + '"><?php echo esc_js( __( 'Update Lesson', 'ggm-member-dashboard' ) ); ?></button>' +
						'<button type="button" class="button ggm-el-close" data-course="' + cid + '"><?php echo esc_js( __( 'Cancel', 'ggm-member-dashboard' ) ); ?></button>' +
						'<span class="spinner" id="ggm-el-spin-' + lid + '" style="float:none;margin:0;"></span>' +
						'<span id="ggm-el-fb-' + lid + '" style="font-weight:600;font-size:13px;"></span>' +
					'</div>' +
				'</div></td></tr>';
			}

			// Open inline edit row.
			$(document).on('click', '.ggm-lesson-edit-btn[data-course="' + cid + '"]', function(){
				var lid  = $(this).data('lesson-id');
				var $btn = $(this);
				if ( $('#ggm-el-row-' + lid).length ) { closeEditRows(); return; }
				closeEditRows();
				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Loading…', 'ggm-member-dashboard' ) ); ?>');
				$.post(ajaxurl, {
					action    : 'ggm_get_lesson_data',
					nonce     : nonce,
					lesson_id : lid,
					course_id : cid
				}, function(res){
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Edit', 'ggm-member-dashboard' ) ); ?>');
					if ( ! res.success ) return;
					var baseUrl = res.data.home_url + res.data.course_slug + '/';
					$('tr[data-lesson-id="' + lid + '"]', '#ggm-lessons-table-' + cid)
						.addClass('ggm-editing')
						.after( buildEditRow(lid, res.data, baseUrl) );
					// Live URL preview.
					var savedSlug = res.data.slug;
					$('#ggm-el-slug-' + lid).on('input', function(){
						var s = $(this).val().trim() || savedSlug;
						var u = baseUrl + s + '/';
						$('#ggm-el-url-preview-' + lid).text(u).attr('href', u);
					});
				}).fail(function(){
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Edit', 'ggm-member-dashboard' ) ); ?>');
				});
			});

			// Close edit row.
			$(document).on('click', '.ggm-el-close[data-course="' + cid + '"]', function(){ closeEditRows(); });

			// Submit update.
			$(document).on('click', '.ggm-el-update[data-course="' + cid + '"]', function(){
				var lid   = $(this).data('lesson-id');
				var title = $('#ggm-el-title-' + lid).val().trim();
				if ( ! title ) { $('#ggm-el-fb-' + lid).css('color','#b91c1c').text('<?php echo esc_js( __( 'Title is required.', 'ggm-member-dashboard' ) ); ?>'); return; }
				var $btn = $(this);
				$btn.prop('disabled', true);
				$('#ggm-el-spin-' + lid).addClass('is-active');
				$('#ggm-el-fb-' + lid).text('');
				$.post(ajaxurl, {
					action       : 'ggm_inline_update_lesson',
					nonce        : nonce,
					course_id    : cid,
					lesson_id    : lid,
					title        : title,
					slug         : $('#ggm-el-slug-' + lid).val().trim(),
					status       : $('#ggm-el-status-' + lid).val(),
					lesson_order : $('#ggm-el-order-' + lid).val(),
					is_preview   : $('#ggm-el-preview-' + lid).is(':checked') ? 1 : 0,
					video_embed  : $('#ggm-el-video-' + lid).val(),
					paged        : curPage,
					per_page     : perPage
				}, function(res){
					$btn.prop('disabled', false);
					$('#ggm-el-spin-' + lid).removeClass('is-active');
					if ( res.success ) {
						$('#ggm-lessons-table-' + cid).html( res.data.table_html );
						initSortable();
						var $st = $('#ggm-reorder-status-' + cid);
						$st.css('color','#15803d').text( res.data.message );
						setTimeout(function(){ $st.text(''); }, 3000);
					} else {
						$('#ggm-el-fb-' + lid).css('color','#b91c1c').text( res.data.message || '<?php echo esc_js( __( 'Update failed.', 'ggm-member-dashboard' ) ); ?>' );
					}
				}).fail(function(){
					$btn.prop('disabled', false);
					$('#ggm-el-spin-' + lid).removeClass('is-active');
					$('#ggm-el-fb-' + lid).css('color','#b91c1c').text('<?php echo esc_js( __( 'Server error.', 'ggm-member-dashboard' ) ); ?>');
				});
			});

			// ── Init ──────────────────────────────────────────────────────────
			initSortable();
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Render the lessons table + pagination HTML for a given page.
	 * Used both in the initial meta box render and in AJAX responses.
	 *
	 * @param int $course_id
	 * @param int $paged
	 * @param int $per_page
	 * @return string HTML string
	 */
	public static function render_lessons_table_html( $course_id, $paged = 1, $per_page = 10 ) {
		// Query all statuses so the admin sees both published and draft lessons.
		$all_lessons = get_posts( array(
			'post_type'      => array( 'lesson', 'ggm_lesson' ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'meta_key'       => 'lesson_order',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array( array(
				'key'   => 'course',
				'value' => $course_id,
			) ),
		) );
		$total       = count( $all_lessons );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$paged       = max( 1, min( $paged, $total_pages ) );
		$offset      = ( $paged - 1 ) * $per_page;
		$lessons     = array_slice( $all_lessons, $offset, $per_page );

		ob_start();

		if ( empty( $all_lessons ) ) {
			echo '<p style="padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; text-align:center; color:#64748b; margin:0;">'
				. esc_html__( 'No lessons yet. Click "+ Add New Lesson" to create the first lesson for this course.', 'ggm-member-dashboard' )
				. '</p>';
			return ob_get_clean();
		}
		?>
		<table class="widefat fixed striped" style="margin-top:4px;">
			<thead>
				<tr>
					<th style="width:28px;" title="<?php esc_attr_e( 'Drag to reorder', 'ggm-member-dashboard' ); ?>">⠿</th>
					<th style="width:36px;"><?php esc_html_e( '#', 'ggm-member-dashboard' ); ?></th>
					<th><?php esc_html_e( 'Lesson Title', 'ggm-member-dashboard' ); ?></th>
					<th style="width:76px;"><?php esc_html_e( 'Preview', 'ggm-member-dashboard' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Status', 'ggm-member-dashboard' ); ?></th>
					<th style="width:60px;" title="<?php esc_attr_e( 'Move up / down across all pages', 'ggm-member-dashboard' ); ?>"><?php esc_html_e( 'Move', 'ggm-member-dashboard' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Actions', 'ggm-member-dashboard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$global_total = count( $all_lessons );
				foreach ( $lessons as $i => $lesson ) :
					$global_pos = $offset + $i; // 0-based position in full list
					$row_num    = $global_pos + 1;
					$is_preview = get_post_meta( $lesson->ID, 'is_preview', true );
					$edit_url   = get_edit_post_link( $lesson->ID );
					$view_url   = get_permalink( $lesson->ID );
					$is_first   = ( 0 === $global_pos );
					$is_last    = ( $global_pos === $global_total - 1 );
				?>
				<tr data-lesson-id="<?php echo esc_attr( $lesson->ID ); ?>">
					<td class="ggm-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'ggm-member-dashboard' ); ?>">⠿</td>
					<td style="font-weight:600; color:#64748b;"><?php echo esc_html( $row_num ); ?></td>
					<td>
						<strong>
							<a href="<?php echo esc_url( $edit_url ); ?>">
								<?php echo esc_html( $lesson->post_title ); ?>
							</a>
						</strong>
					</td>
					<td>
						<?php if ( '1' === $is_preview ) : ?>
							<span style="background:#e8faf3; color:#0e9e6e; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600;">
								<?php esc_html_e( 'Free', 'ggm-member-dashboard' ); ?>
							</span>
						<?php else : ?>
							<span style="color:#aaa; font-size:12px;">—</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( ucfirst( $lesson->post_status ) ); ?></td>
					<td style="white-space:nowrap;">
						<button type="button"
							class="button button-small ggm-move-btn ggm-move-up"
							data-course="<?php echo esc_attr( $course_id ); ?>"
							data-lesson-id="<?php echo esc_attr( $lesson->ID ); ?>"
							title="<?php esc_attr_e( 'Move up', 'ggm-member-dashboard' ); ?>"
							<?php disabled( $is_first ); ?>>▲</button>
						<button type="button"
							class="button button-small ggm-move-btn ggm-move-down"
							data-course="<?php echo esc_attr( $course_id ); ?>"
							data-lesson-id="<?php echo esc_attr( $lesson->ID ); ?>"
							title="<?php esc_attr_e( 'Move down', 'ggm-member-dashboard' ); ?>"
							<?php disabled( $is_last ); ?>>▼</button>
					</td>
					<td style="white-space:nowrap;">
						<button type="button"
							class="button button-small ggm-lesson-edit-btn"
							data-lesson-id="<?php echo esc_attr( $lesson->ID ); ?>"
							data-course="<?php echo esc_attr( $course_id ); ?>">
							<?php esc_html_e( 'Edit', 'ggm-member-dashboard' ); ?>
						</button>
						<?php if ( $view_url && 'publish' === $lesson->post_status ) : ?>
							<a href="<?php echo esc_url( $view_url ); ?>" class="button button-small" target="_blank" rel="noopener">
								<?php esc_html_e( 'View', 'ggm-member-dashboard' ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
		<div style="margin-top:12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
			<span style="font-size:13px; color:#64748b;">
				<?php printf(
					esc_html__( 'Page %1$d of %2$d', 'ggm-member-dashboard' ),
					$paged,
					$total_pages
				); ?>
			</span>
			<?php if ( $paged > 1 ) : ?>
				<button type="button" class="button button-small ggm-lessons-page-btn"
					data-course="<?php echo esc_attr( $course_id ); ?>"
					data-page="<?php echo esc_attr( $paged - 1 ); ?>">
					&larr; <?php esc_html_e( 'Prev', 'ggm-member-dashboard' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $paged < $total_pages ) : ?>
				<button type="button" class="button button-small ggm-lessons-page-btn"
					data-course="<?php echo esc_attr( $course_id ); ?>"
					data-page="<?php echo esc_attr( $paged + 1 ); ?>">
					<?php esc_html_e( 'Next', 'ggm-member-dashboard' ); ?> &rarr;
				</button>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Lesson details meta box.
	 */
	public function render_lesson_details_mb( $post ) {
		wp_nonce_field( 'ggm_meta_box_save', 'ggm_meta_nonce' );

		$course             = get_post_meta( $post->ID, 'course', true );
		$lesson_order       = get_post_meta( $post->ID, 'lesson_order', true );
		$video_embed        = get_post_meta( $post->ID, 'video_embed', true );
		$is_preview         = get_post_meta( $post->ID, 'is_preview', true );
		$what_to_expect     = get_post_meta( $post->ID, 'what_to_expect', true );
		$best_practices     = get_post_meta( $post->ID, 'best_practices', true );
		$lesson_resources_pdf = get_post_meta( $post->ID, 'lesson_resources_pdf', true );
		$lesson_summary     = get_post_meta( $post->ID, 'lesson_summary', true );

		// Pre-fill the parent course when navigated from the Course Lessons meta box.
		if ( empty( $course ) && isset( $_GET['course_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$course = absint( $_GET['course_id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		$courses = get_posts( array(
			'post_type'      => 'course',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );
		?>
		<div class="ggm-meta-container">
			<?php if ( ! empty( $_GET['course_id'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div style="margin-bottom:14px; padding:10px 14px; background:#eff6ff; border-left:4px solid #3b82f6; border-radius:4px;">
				<?php
				$parent_course = get_post( absint( $_GET['course_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				if ( $parent_course ) :
				?>
				<strong><?php esc_html_e( 'Creating lesson for course:', 'ggm-member-dashboard' ); ?></strong>
				<a href="<?php echo esc_url( get_edit_post_link( $parent_course->ID ) ); ?>">
					<?php echo esc_html( $parent_course->post_title ); ?>
				</a>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<table class="form-table ggm-meta-table">
				<tr>
					<th><label for="ggm-lesson-course"><?php esc_html_e( 'Course', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<select name="course" id="ggm-lesson-course" class="regular-text">
							<option value=""><?php esc_html_e( '— Select Course —', 'ggm-member-dashboard' ); ?></option>
							<?php foreach ( $courses as $c ) : ?>
								<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( (int) $course, $c->ID ); ?>><?php echo esc_html( $c->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-lesson-order"><?php esc_html_e( 'Lesson Order', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="number" name="lesson_order" id="ggm-lesson-order" value="<?php echo esc_attr( $lesson_order ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th><label for="ggm-video-embed"><?php esc_html_e( 'YouTube Video URL', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<input type="url" name="video_embed" id="ggm-video-embed" class="large-text" value="<?php echo esc_url( $video_embed ); ?>" placeholder="https://www.youtube.com/watch?v=...">
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Allow Free Preview', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="is_preview" value="1" <?php checked( $is_preview, '1' ); ?>>
							<?php esc_html_e( 'Allow users to preview this lesson without a membership level.', 'ggm-member-dashboard' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-what-to-expect"><?php esc_html_e( 'What to Expect', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<textarea name="what_to_expect" id="ggm-what-to-expect" class="large-text" rows="3"><?php echo esc_textarea( $what_to_expect ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-best-practices"><?php esc_html_e( 'Best Practices', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<textarea name="best_practices" id="ggm-best-practices" class="large-text" rows="3"><?php echo esc_textarea( $best_practices ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Resources PDF', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<div class="ggm-image-upload-wrap">
							<input type="text" name="lesson_resources_pdf" id="ggm-resources-pdf" value="<?php echo esc_url( $lesson_resources_pdf ); ?>" class="regular-text ggm-media-url">
							<button type="button" class="button ggm-media-upload-btn" data-target="ggm-resources-pdf"><?php esc_html_e( 'Upload PDF', 'ggm-member-dashboard' ); ?></button>
							<button type="button" class="button ggm-media-clear-btn" data-target="ggm-resources-pdf"><?php esc_html_e( 'Clear', 'ggm-member-dashboard' ); ?></button>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="ggm-lesson-summary"><?php esc_html_e( 'Lesson Summary', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<textarea name="lesson_summary" id="ggm-lesson-summary" class="large-text" rows="3"><?php echo esc_textarea( $lesson_summary ); ?></textarea>
					</td>
				</tr>
			</table>

			<!-- Repeater: lesson_focus_areas -->
			<hr/>
			<div class="ggm-repeater-block" data-key="lesson_focus_areas">
				<h4><?php esc_html_e( 'Lesson Focus Areas', 'ggm-member-dashboard' ); ?></h4>
				<table class="widefat striped ggm-repeater-table" id="ggm-table-lesson_focus_areas">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Focus Area', 'ggm-member-dashboard' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Action', 'ggm-member-dashboard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$focus = json_decode( get_post_meta( $post->ID, 'lesson_focus_areas', true ), true ) ?: array();
						foreach ( $focus as $index => $item ) :
							?>
							<tr data-index="<?php echo esc_attr( $index ); ?>">
								<td><input type="text" name="lesson_focus_areas[<?php echo esc_attr( $index ); ?>][focus_area]" value="<?php echo esc_attr( $item['focus_area'] ?? '' ); ?>" class="widefat"></td>
								<td><button type="button" class="button button-link-delete ggm-remove-row-btn"><?php esc_html_e( 'Remove', 'ggm-member-dashboard' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button ggm-add-row-btn" data-key="lesson_focus_areas"><?php esc_html_e( '+ Add Focus Area', 'ggm-member-dashboard' ); ?></button></p>
			</div>
		</div>
		<?php
		$this->print_repeaters_js();
	}

	/**
	 * Render Mentor details meta box (Image + Bio — Name is the post title).
	 */
	public function render_mentor_details_mb( $post ) {
		wp_nonce_field( 'ggm_meta_box_save', 'ggm_meta_nonce' );

		$mentor_image = get_post_meta( $post->ID, 'ggm_mentor_image', true );
		$mentor_bio   = get_post_meta( $post->ID, 'ggm_mentor_bio', true );
		?>
		<div class="ggm-meta-container">
			<table class="form-table ggm-meta-table">
				<tr>
					<th><label><?php esc_html_e( 'Mentor Photo', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<div class="ggm-image-upload-wrap">
							<input type="text" name="ggm_mentor_image" id="ggm-mentor-image" value="<?php echo esc_url( $mentor_image ); ?>" class="regular-text ggm-media-url">
							<button type="button" class="button ggm-media-upload-btn" data-target="ggm-mentor-image"><?php esc_html_e( 'Upload Image', 'ggm-member-dashboard' ); ?></button>
							<button type="button" class="button ggm-media-clear-btn" data-target="ggm-mentor-image"><?php esc_html_e( 'Clear', 'ggm-member-dashboard' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Shown as a circular photo wherever this mentor is assigned to a workshop.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ggm_mentor_bio_editor"><?php esc_html_e( 'Bio', 'ggm-member-dashboard' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$mentor_bio,
							'ggm_mentor_bio_editor',
							array(
								'textarea_name' => 'ggm_mentor_bio',
								'textarea_rows' => 10,
								'media_buttons' => false,
								'teeny'         => false,
								'quicktags'     => true,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Use the Visual editor to format paragraphs, headings, links, lists, quotes, bold, and italic text. The Text tab is available for supported HTML.', 'ggm-member-dashboard' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
		$this->print_media_uploader_js();
	}

	/**
	 * Save Metabox data.
	 */
	public function save_meta_boxes( $post_id, $post ) {
		if ( ! isset( $_POST['ggm_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ggm_meta_nonce'] ) ), 'ggm_meta_box_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$save_callback = null;
		if ( 'workshop' === $post->post_type ) {
			$save_callback = array( $this, 'save_workshop_fields' );
		} elseif ( 'course' === $post->post_type ) {
			$save_callback = array( $this, 'save_course_fields' );
		} elseif ( 'lesson' === $post->post_type ) {
			$save_callback = array( $this, 'save_lesson_fields' );
		} elseif ( 'ggm_mentor' === $post->post_type ) {
			$save_callback = array( $this, 'save_mentor_fields' );
		} elseif ( 'post' === $post->post_type ) {
			$save_callback = array( $this, 'save_blog_post_fields' );
		}

		if ( ! $save_callback ) {
			return;
		}

		// Capture any PHP warning/notice/exception raised while saving these
		// fields. On production, display_errors is normally off, so a save
		// that aborts partway through a repeater loop looks like "the field
		// just didn't save" with no visible cause anywhere. See the
		// Settings → Error Log tab.
		$context = array(
			'post_id'   => $post_id,
			'post_type' => $post->post_type,
		);

		set_error_handler( function ( $errno, $errstr, $errfile, $errline ) use ( $context ) {
			if ( ! ( error_reporting() & $errno ) ) {
				return false; // Respect @-suppressed errors and error_reporting level.
			}
			self::log_error( $errstr, $context + array(
				'file' => $errfile,
				'line' => $errline,
			) );
			return false; // Still let PHP's own handler/log run too.
		} );

		try {
			call_user_func( $save_callback, $post_id );
		} catch ( \Throwable $e ) {
			self::log_error( $e->getMessage(), $context + array(
				'file'  => $e->getFile(),
				'line'  => $e->getLine(),
				'trace' => $e->getTraceAsString(),
			) );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Append an entry to the saved-fields error log (capped at the most
	 * recent 100 entries), shown under Settings → Error Log.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public static function log_error( $message, $context = array() ) {
		$log   = get_option( 'ggm_error_log', array() );
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'message' => $message,
			'context' => $context,
		);
		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, -100 );
		}
		update_option( 'ggm_error_log', $log, false );
	}

	/**
	 * Clear the saved-fields error log.
	 */
	public static function clear_error_log() {
		delete_option( 'ggm_error_log' );
	}

	/**
	 * Describe what $_POST actually holds for a repeater field, for the
	 * diagnostic snapshot in save_workshop_fields().
	 *
	 * @param string $key
	 * @return string
	 */
	private static function describe_repeater_post( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return 'NOT received (missing from $_POST)';
		}
		if ( ! is_array( $_POST[ $key ] ) ) {
			return 'received but not an array (' . gettype( $_POST[ $key ] ) . ')';
		}
		return count( $_POST[ $key ] ) . ' row(s) received';
	}

	/**
	 * Save mentor specific fields.
	 */
	private function save_mentor_fields( $post_id ) {
		if ( isset( $_POST['ggm_mentor_image'] ) ) {
			update_post_meta( $post_id, 'ggm_mentor_image', esc_url_raw( wp_unslash( $_POST['ggm_mentor_image'] ) ) );
		}
		if ( isset( $_POST['ggm_mentor_bio'] ) ) {
			update_post_meta( $post_id, 'ggm_mentor_bio', wp_kses_post( wp_unslash( $_POST['ggm_mentor_bio'] ) ) );
		}
	}

	/**
	 * Save normal WordPress post fields.
	 */
	private function save_blog_post_fields( $post_id ) {
		if ( isset( $_POST['ggm_blog_video_url'] ) ) {
			$video_url = esc_url_raw( trim( (string) wp_unslash( $_POST['ggm_blog_video_url'] ) ), array( 'http', 'https' ) );
			if ( $video_url ) {
				update_post_meta( $post_id, 'ggm_blog_video_url', $video_url );
			} else {
				delete_post_meta( $post_id, 'ggm_blog_video_url' );
			}
		}
	}

	/**
	 * Save workshop specific fields.
	 */
	private function save_workshop_fields( $post_id ) {
		// Diagnostic snapshot on every save (not just on error) — since a
		// missing repeater array produces no PHP warning/exception at all
		// (it's handled as "no rows submitted"), this is the only way to see
		// whether the browser is actually sending these fields to the server.
		self::log_error( 'Workshop save invoked — repeater payload snapshot', array(
			'level'                => 'debug',
			'post_id'              => $post_id,
			'max_input_vars'       => ini_get( 'max_input_vars' ),
			'post_key_count'       => count( $_POST ),
			'ggm_you_will_discover'    => self::describe_repeater_post( 'ggm_you_will_discover' ),
			'ggm_why_different_points' => self::describe_repeater_post( 'ggm_why_different_points' ),
			'ggm_perfect_for_you'      => self::describe_repeater_post( 'ggm_perfect_for_you' ),
			'ggm_faq'                  => self::describe_repeater_post( 'ggm_faq' ),
		) );

		$text_fields = array(
			'Counter_Start_Date',
			'workshop_preparatory_date',
			'workshop_mode',
			'duration',
			'ggm_workshop_booking_card_cta_text',
			'ggm_workshop_booking_card_cta_heading',
			'ggm_workshop_header_pill_text',
			'ggm_workshop_booking_card_testimonial_text',
			'ggm_workshop_booking_card_rating_text',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		if ( isset( $_POST['workshop_start_date'], $_POST['workshop_end_date'] ) ) {
			$range_start = sanitize_text_field( wp_unslash( $_POST['workshop_start_date'] ) );
			$range_end   = sanitize_text_field( wp_unslash( $_POST['workshop_end_date'] ) );
			$valid_start = '' === $range_start || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $range_start );
			$valid_end   = '' === $range_end || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $range_end );
			if ( $valid_start && $valid_end && ( '' === $range_start || '' === $range_end || $range_end >= $range_start ) ) {
				update_post_meta( $post_id, 'workshop_start_date', $range_start );
				update_post_meta( $post_id, 'workshop_end_date', $range_end );
			} else {
				set_transient( 'ggm_slot_notice_' . get_current_user_id(), __( 'Workshop Date Range was not saved because the end date is before the start date.', 'ggm-member-dashboard' ), 45 );
			}
		}

		$block_heading_fields = array(
			'ggm_workshop_discover_heading_override',
			'ggm_workshop_why_different_heading_override',
			'ggm_workshop_perfect_for_heading_override',
			'ggm_workshop_faq_heading_override',
		);
		foreach ( $block_heading_fields as $field ) {
			// Disabled inline editors are intentionally absent from normal saves;
			// preserve their existing overrides unless an editor was opened.
			if ( ! array_key_exists( $field, $_POST ) ) {
				continue;
			}
			$raw_value = wp_unslash( $_POST[ $field ] );
			$value     = is_scalar( $raw_value ) ? trim( sanitize_text_field( (string) $raw_value ) ) : '';
			if ( '' === $value ) {
				delete_post_meta( $post_id, $field );
			} else {
				update_post_meta( $post_id, $field, $value );
			}
		}

		foreach ( array( 'discover', 'why_different', 'perfect_for' ) as $section ) {
			$field = 'ggm_workshop_' . $section . '_bottom_text';
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$value = trim( sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $field );
			} else {
				update_post_meta( $post_id, $field, $value );
			}
		}

		$why_workshop_text_fields = array( 'ggm_workshop_why_workshop_different_intro', 'ggm_workshop_why_workshop_different_heading', 'ggm_workshop_journey_heading' );
		foreach ( $why_workshop_text_fields as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$value = trim( sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $field );
			} else {
				update_post_meta( $post_id, $field, $value );
			}
		}

		if ( isset( $_POST['ggm_workshop_why_workshop_different_image'] ) ) {
			$image = esc_url_raw( wp_unslash( $_POST['ggm_workshop_why_workshop_different_image'] ), array( 'http', 'https' ) );
			if ( $image ) {
				update_post_meta( $post_id, 'ggm_workshop_why_workshop_different_image', $image );
			} else {
				delete_post_meta( $post_id, 'ggm_workshop_why_workshop_different_image' );
			}
		}

		if ( isset( $_POST['ggm_workshop_journey_footer_items'] ) && is_array( $_POST['ggm_workshop_journey_footer_items'] ) ) {
			$journey_footer_items = array();
			for ( $journey_footer_index = 0; $journey_footer_index < 4; $journey_footer_index++ ) {
				$row      = $_POST['ggm_workshop_journey_footer_items'][ $journey_footer_index ] ?? array();
				$icon_raw = is_array( $row ) && isset( $row['icon'] ) && is_scalar( $row['icon'] ) ? wp_unslash( $row['icon'] ) : '';
				$text_raw = is_array( $row ) && isset( $row['text'] ) && is_scalar( $row['text'] ) ? wp_unslash( $row['text'] ) : '';
				$journey_footer_items[] = array( 'icon' => esc_url_raw( $icon_raw, array( 'http', 'https' ) ), 'text' => sanitize_text_field( $text_raw ) );
			}
			update_post_meta( $post_id, 'ggm_workshop_journey_footer_items', $journey_footer_items );
		}

		if ( isset( $_POST['ggm_workshop_whatsapp_group_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['ggm_workshop_whatsapp_group_url'] ) );
			if ( $url ) {
				update_post_meta( $post_id, 'ggm_workshop_whatsapp_group_url', $url );
			} else {
				delete_post_meta( $post_id, 'ggm_workshop_whatsapp_group_url' );
			}
		}

		// Short Description allows a small set of basic inline formatting tags
		// (bold/italic/line-break) instead of sanitize_text_field(), which
		// would otherwise strip them out entirely.
		if ( isset( $_POST['workshop_short_desc'] ) ) {
			update_post_meta( $post_id, 'workshop_short_desc', wp_kses( wp_unslash( $_POST['workshop_short_desc'] ), self::SHORT_DESC_ALLOWED_TAGS ) );
		}

		if ( isset( $_POST['ggm_workshop_featured_video_url'] ) ) {
			update_post_meta(
				$post_id,
				'ggm_workshop_featured_video_url',
				ggm_sanitize_youtube_url( wp_unslash( $_POST['ggm_workshop_featured_video_url'] ) )
			);
		}

		if ( array_key_exists( 'ggm_workshop_bottom_image_id', $_POST ) ) {
			$bottom_image_id = absint( wp_unslash( $_POST['ggm_workshop_bottom_image_id'] ) );
			if ( 0 === $bottom_image_id ) {
				delete_post_meta( $post_id, 'ggm_workshop_bottom_image_id' );
			} elseif ( wp_attachment_is_image( $bottom_image_id ) ) {
				update_post_meta( $post_id, 'ggm_workshop_bottom_image_id', $bottom_image_id );
			}
		}

		if ( isset( $_POST['ggm_workshop_booking_card_hero_header'] ) ) {
			$hero_header = wp_kses( wp_unslash( $_POST['ggm_workshop_booking_card_hero_header'] ), self::HERO_HEADER_ALLOWED_TAGS );
			if ( '' === trim( wp_strip_all_tags( $hero_header ) ) ) {
				delete_post_meta( $post_id, 'ggm_workshop_booking_card_hero_header' );
			} else {
				update_post_meta( $post_id, 'ggm_workshop_booking_card_hero_header', $hero_header );
			}
		}

		if ( array_key_exists( 'ggm_workshop_form_text_heading', $_POST ) ) {
			$form_text_heading_raw = wp_unslash( $_POST['ggm_workshop_form_text_heading'] );
			$form_text_heading     = is_scalar( $form_text_heading_raw ) ? trim( sanitize_text_field( (string) $form_text_heading_raw ) ) : '';
			if ( '' === $form_text_heading ) {
				delete_post_meta( $post_id, 'ggm_workshop_form_text_heading' );
			} else {
				update_post_meta( $post_id, 'ggm_workshop_form_text_heading', $form_text_heading );
			}
		}

		if ( array_key_exists( 'ggm_workshop_form_text_content', $_POST ) ) {
			$form_text_content_raw = wp_unslash( $_POST['ggm_workshop_form_text_content'] );
			$form_text_content     = is_scalar( $form_text_content_raw ) ? wp_kses_post( (string) $form_text_content_raw ) : '';
			$has_rich_content  = '' !== trim( wp_strip_all_tags( $form_text_content ) ) || (bool) preg_match( '/<(?:audio|figure|gallery|img|video)\b/i', $form_text_content );
			if ( ! $has_rich_content ) {
				delete_post_meta( $post_id, 'ggm_workshop_form_text_content' );
			} else {
				update_post_meta( $post_id, 'ggm_workshop_form_text_content', $form_text_content );
			}
		}

		if ( isset( $_POST['ggm_workshop_booking_card_hero_items'] ) && is_array( $_POST['ggm_workshop_booking_card_hero_items'] ) ) {
			$hero_items = array();
			for ( $hero_index = 0; $hero_index < 4; $hero_index++ ) {
				$row  = $_POST['ggm_workshop_booking_card_hero_items'][ $hero_index ] ?? array();
				$icon_raw = is_array( $row ) && isset( $row['icon'] ) && is_scalar( $row['icon'] ) ? wp_unslash( $row['icon'] ) : '';
				$text_raw = is_array( $row ) && isset( $row['text'] ) && is_scalar( $row['text'] ) ? wp_unslash( $row['text'] ) : '';
				$icon = esc_url_raw( $icon_raw, array( 'http', 'https' ) );
				$text = sanitize_text_field( $text_raw );
				$hero_items[] = array( 'icon' => $icon, 'text' => $text );
			}
			update_post_meta( $post_id, 'ggm_workshop_booking_card_hero_items', $hero_items );
		}

		if ( isset( $_POST['ggm_workshop_additional_block_layouts'] ) && is_array( $_POST['ggm_workshop_additional_block_layouts'] ) ) {
			$layouts = array();
			foreach ( array( 'discover', 'why_different', 'perfect_for' ) as $layout_key ) {
				$row = $_POST['ggm_workshop_additional_block_layouts'][ $layout_key ] ?? array();
				$layouts[ $layout_key ] = array(
					'columns' => is_array( $row ) ? min( 8, absint( $row['columns'] ?? 0 ) ) : 0,
					'rows'    => is_array( $row ) ? min( 10, absint( $row['rows'] ?? 0 ) ) : 0,
				);
			}
			update_post_meta( $post_id, 'ggm_workshop_additional_block_layouts', $layouts );
		}

		$language = sanitize_key( wp_unslash( $_POST['workshop_language'] ?? '' ) );
		update_post_meta( $post_id, 'workshop_language', in_array( $language, array( 'english', 'hindi' ), true ) ? $language : 'english' );

		$mentor_ids = isset( $_POST['ggm_mentor_ids'] ) && is_array( $_POST['ggm_mentor_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['ggm_mentor_ids'] ) ) ) ) )
			: array();
		update_post_meta( $post_id, 'ggm_mentor_ids', $mentor_ids );
		// Kept in sync (first selected mentor) for any older code still reading the single-mentor field.
		update_post_meta( $post_id, 'ggm_mentor_id', $mentor_ids[0] ?? 0 );
		update_post_meta( $post_id, 'linked_course_id', absint( $_POST['linked_course_id'] ?? 0 ) );

		// Regular/Sale price. Sale Price is only saved when it
		// doesn't exceed Regular Price — otherwise it's dropped and an admin
		// notice queued, matching the same "skip + notify" pattern used for
		// invalid time slot rows below.
		$regular_price = (float) ( $_POST['workshop_regular_price'] ?? 0 );
		$sale_price    = '' === trim( (string) ( $_POST['workshop_sale_price'] ?? '' ) ) ? '' : (float) $_POST['workshop_sale_price'];

		update_post_meta( $post_id, 'workshop_regular_price', $regular_price > 0 ? $regular_price : '' );

		if ( '' === $sale_price ) {
			update_post_meta( $post_id, 'workshop_sale_price', '' );
		} elseif ( $sale_price > $regular_price ) {
			update_post_meta( $post_id, 'workshop_sale_price', '' );
			set_transient(
				'ggm_slot_notice_' . get_current_user_id(),
				__( 'Sale Price was not saved because it exceeded the Regular Price.', 'ggm-member-dashboard' ),
				45
			);
		} else {
			update_post_meta( $post_id, 'workshop_sale_price', $sale_price );
		}

		$contribution_enabled = isset( $_POST['ggm_contribution_enabled'] );
		$contributions_present = isset( $_POST['ggm_contributions_present'] );
		$contribution_default = sanitize_key( wp_unslash( $_POST['ggm_contribution_default'] ?? '' ) );
		$contributions        = array();
		$free_seen           = false;
		foreach ( (array) ( $_POST['ggm_contributions'] ?? array() ) as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$is_free_option = ! $free_seen && ! empty( $row['is_free'] );
			$amount         = $is_free_option ? 0.0 : round( (float) ( $row['amount'] ?? 0 ), 2 );
			if ( ! $is_free_option && $amount <= 0 ) {
				continue;
			}
			$free_seen = $free_seen || $is_free_option;
			$contributions[] = array(
				'id'         => sanitize_key( $row['id'] ?? '' ) ?: wp_generate_uuid4(),
				'type'       => $is_free_option ? 'free' : 'paid',
				'amount'     => $amount,
				'label'      => sanitize_text_field( wp_unslash( $row['label'] ?? '' ) ),
				'is_default' => sanitize_key( (string) $i ) === $contribution_default,
			);
		}
		if ( $contribution_enabled && $contributions ) {
			if ( ! array_filter( $contributions, static function($row){return $row['is_default'];} ) ) { $contributions[0]['is_default'] = true; }
			update_post_meta( $post_id, 'ggm_contribution_enabled', '1' );
			update_post_meta( $post_id, 'ggm_contribution_options', $contributions );
		} else {
			update_post_meta( $post_id, 'ggm_contribution_enabled', '' );
			if ( $contributions ) {
				// Preserve valid rows when Contribution Pricing is merely
				// switched off, so an admin can turn it back on later.
				update_post_meta( $post_id, 'ggm_contribution_options', $contributions );
			} elseif ( $contributions_present ) {
				// The meta box submitted successfully and contains no valid
				// rows: every option was removed (or cleared), so remove the
				// old metadata instead of letting stale prices reappear.
				delete_post_meta( $post_id, 'ggm_contribution_options' );
			}
		}
		// Contribution Pricing is a distinct selection/enrollment flow. A Free
		// contribution option must not turn on global free access.
		$is_free = ! $contribution_enabled && isset( $_POST['is_free'] ) ? '1' : '';
		update_post_meta( $post_id, 'is_free', $is_free );
		if ( class_exists( 'GGM_Currency' ) ) {
			GGM_Currency::save_adjustments_for_item( $post_id, wp_unslash( $_POST['ggm_currency_adjustments'] ?? array() ) );
		}

		$this->save_workshop_time_slots( $post_id );

		// Save Repeaters
		$repeaters    = array( 'ggm_you_will_discover', 'ggm_why_different_points', 'ggm_perfect_for_you', 'ggm_faq', 'ggm_workshop_why_workshop_different_points', 'ggm_workshop_journey_days' );
		// These multi-line subkeys allow the same basic inline formatting tags
		// as the retained legacy Workshop Short Description value, instead of
		// sanitize_textarea_field(), which would otherwise strip them out entirely.
		$html_subkeys = array( 'description', 'answer' );
		foreach ( $repeaters as $rep ) {
			if ( isset( $_POST[ $rep ] ) && is_array( $_POST[ $rep ] ) ) {
				$sanitized = array();
				foreach ( wp_unslash( $_POST[ $rep ] ) as $row ) {
					$clean_row = array();
					foreach ( $row as $k => $v ) {
						$key               = sanitize_key( $k );
						$clean_row[ $key ] = in_array( $key, $html_subkeys, true ) ? wp_kses( $v, self::SHORT_DESC_ALLOWED_TAGS ) : sanitize_text_field( $v );
					}
					$sanitized[] = $clean_row;
				}
				update_post_meta( $post_id, $rep, wp_json_encode( $sanitized ) );
			} else {
				update_post_meta( $post_id, $rep, wp_json_encode( array() ) );
			}
		}
	}

	/**
	 * Upsert submitted workshop time slot rows into wp_ggm_workshop_slots.
	 *
	 * Rows with an invalid time range (end <= start) are skipped and an
	 * admin notice is queued so the editor knows what wasn't saved.
	 *
	 * @param int $post_id
	 */
	private function save_workshop_time_slots( $post_id ) {
		if ( ! class_exists( 'GGM_Workshop_Slot' ) ) {
			return;
		}

		$rows       = isset( $_POST['workshop_time_slots'] ) && is_array( $_POST['workshop_time_slots'] ) ? wp_unslash( $_POST['workshop_time_slots'] ) : array();
		$keep_ids   = array();
		$skipped    = 0;
		$sort_order = 0;

		foreach ( $rows as $row ) {
			$slot_id      = absint( $row['id'] ?? 0 );
			$start        = sanitize_text_field( $row['start'] ?? '' );
			$end          = sanitize_text_field( $row['end'] ?? '' );
			$slot_type    = sanitize_key( $row['type'] ?? 'live' );
			$meeting_link = esc_url_raw( $row['meeting_link'] ?? '' );
			$enabled      = ! empty( $row['enabled'] );

			if ( '' === $start && '' === $end ) {
				continue; // Blank row, ignore.
			}

			if ( ! GGM_Workshop_Slot::validate_range( $start, $end ) ) {
				$skipped++;
				continue;
			}

			$data = array(
				'workshop_id'  => $post_id,
				'slot_type'    => $slot_type,
				'start_time'   => $start,
				'end_time'     => $end,
				'meeting_link' => $meeting_link,
				'sort_order'   => $sort_order,
				'status'       => $enabled ? 'active' : 'inactive',
			);

			if ( $slot_id ) {
				GGM_Workshop_Slot::update( $slot_id, $data );
				$keep_ids[] = $slot_id;
			} else {
				$new_id = GGM_Workshop_Slot::create( $data );
				if ( $new_id ) {
					$keep_ids[] = $new_id;
				}
			}

			$sort_order++;
		}

		GGM_Workshop_Slot::delete_missing( $post_id, $keep_ids );

		if ( $skipped > 0 ) {
			set_transient(
				'ggm_slot_notice_' . get_current_user_id(),
				sprintf(
					/* translators: %d: number of skipped rows */
					_n(
						'%d workshop time slot was not saved because its end time was not after its start time.',
						'%d workshop time slots were not saved because their end time was not after their start time.',
						$skipped,
						'ggm-member-dashboard'
					),
					$skipped
				),
				45
			);
		}
	}

	/**
	 * Save course specific fields.
	 */
	private function save_course_fields( $post_id ) {
		$fields = array(
			'course_price',
			'course_short_description',
			'course_language',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$val = 'course_short_description' === $field
					? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) )
					: sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				if ( 'course_language' === $field ) {
					$val = in_array( strtolower( $val ), array( 'english', 'hindi' ), true ) ? strtolower( $val ) : 'english';
				}
				update_post_meta( $post_id, $field, $val );
			}
		}

		$mentor_ids = isset( $_POST['ggm_mentor_ids'] ) && is_array( $_POST['ggm_mentor_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['ggm_mentor_ids'] ) ) ) ) )
			: array();
		update_post_meta( $post_id, 'ggm_mentor_ids', $mentor_ids );
		update_post_meta( $post_id, 'ggm_mentor_id', $mentor_ids[0] ?? 0 );
		if ( class_exists( 'GGM_Currency' ) ) {
			GGM_Currency::save_adjustments_for_item( $post_id, wp_unslash( $_POST['ggm_currency_adjustments'] ?? array() ) );
		}
	}

	/**
	 * Save lesson specific fields.
	 */
	private function save_lesson_fields( $post_id ) {
		$fields = array(
			'course',
			'lesson_order',
			'video_embed',
			'what_to_expect',
			'best_practices',
			'lesson_resources_pdf',
			'lesson_summary',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				if ( 'video_embed' === $field ) {
					$val = ggm_sanitize_youtube_url( wp_unslash( $_POST[ $field ] ) );
				} else {
					$val = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				}
				if ( 'lesson_resources_pdf' === $field ) {
					$val = esc_url_raw( $val );
				}
				update_post_meta( $post_id, $field, $val );
			}
		}

		$is_preview = isset( $_POST['is_preview'] ) ? '1' : '';
		update_post_meta( $post_id, 'is_preview', $is_preview );

		// Save Repeater: lesson_focus_areas
		if ( isset( $_POST['lesson_focus_areas'] ) && is_array( $_POST['lesson_focus_areas'] ) ) {
			$sanitized = array();
			foreach ( wp_unslash( $_POST['lesson_focus_areas'] ) as $row ) {
				if ( ! empty( $row['focus_area'] ) ) {
					$sanitized[] = array( 'focus_area' => sanitize_text_field( $row['focus_area'] ) );
				}
			}
			update_post_meta( $post_id, 'lesson_focus_areas', wp_json_encode( $sanitized ) );
		} else {
			update_post_meta( $post_id, 'lesson_focus_areas', wp_json_encode( array() ) );
		}
	}

	/**
	 * Render an attachment-ID image selector for a standalone workshop field.
	 *
	 * Keeping the attachment ID allows Elementor to retain WordPress image
	 * metadata and responsive sizes instead of treating the image as a bare URL.
	 *
	 * @param string $name          Form field name.
	 * @param string $id            Unique input ID used by the media buttons.
	 * @param int    $attachment_id Current image attachment ID.
	 */
	private function render_attachment_image_control( $name, $id, $attachment_id = 0 ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id && ! wp_attachment_is_image( $attachment_id ) ) {
			$attachment_id = 0;
		}
		$preview_url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		?>
		<div class="ggm-repeater-media">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $attachment_id ); ?>" class="ggm-media-id" id="<?php echo esc_attr( $id ); ?>" data-preview-url="<?php echo esc_url( $preview_url ); ?>">
			<div class="ggm-repeater-media__preview">
				<img src="<?php echo esc_url( $preview_url ); ?>" alt=""<?php echo $preview_url ? '' : ' hidden'; ?>>
				<span<?php echo $preview_url ? ' hidden' : ''; ?>><?php esc_html_e( 'No image', 'ggm-member-dashboard' ); ?></span>
			</div>
			<div class="ggm-repeater-media__actions">
				<button type="button" class="button ggm-media-upload-btn" data-target="<?php echo esc_attr( $id ); ?>" data-value-type="id" data-media-type="image"><?php esc_html_e( 'Select / Replace Image', 'ggm-member-dashboard' ); ?></button>
				<button type="button" class="button ggm-media-clear-btn" data-target="<?php echo esc_attr( $id ); ?>"<?php echo $attachment_id ? '' : ' disabled'; ?>><?php esc_html_e( 'Remove Image', 'ggm-member-dashboard' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a compact image selector for workshop repeater rows.
	 *
	 * The URL remains a submitted hidden field so storage and front-end output
	 * contracts do not change; the editor shows a bounded thumbnail instead of
	 * allowing a long URL input to determine the table's column width.
	 *
	 * @param string $name  Form field name.
	 * @param string $id    Unique input ID used by the media buttons.
	 * @param string $value Current image URL.
	 */
	private function render_repeater_image_control( $name, $id, $value = '' ) {
		$value = esc_url_raw( (string) $value );
		?>
		<div class="ggm-repeater-media">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_url( $value ); ?>" class="ggm-media-url" id="<?php echo esc_attr( $id ); ?>">
			<div class="ggm-repeater-media__preview">
				<img src="<?php echo esc_url( $value ); ?>" alt=""<?php echo $value ? '' : ' hidden'; ?>>
				<span<?php echo $value ? ' hidden' : ''; ?>><?php esc_html_e( 'No image', 'ggm-member-dashboard' ); ?></span>
			</div>
			<div class="ggm-repeater-media__actions">
				<button type="button" class="button ggm-media-upload-btn" data-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Upload', 'ggm-member-dashboard' ); ?></button>
				<button type="button" class="button ggm-media-clear-btn" data-target="<?php echo esc_attr( $id ); ?>"<?php echo $value ? '' : ' disabled'; ?>><?php esc_html_e( 'Clear', 'ggm-member-dashboard' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Print dynamic JavaScript routines for media uploads.
	 */
	private function print_media_uploader_js() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		<script>
		jQuery(document).ready(function($) {
			function syncRepeaterMediaPreview(targetInput, selectedPreviewUrl) {
				var control = targetInput.closest('.ggm-repeater-media');
				if (!control.length) return;
				var isAttachmentId = targetInput.hasClass('ggm-media-id');
				var url = typeof selectedPreviewUrl === 'string'
					? selectedPreviewUrl
					: $.trim(isAttachmentId ? (targetInput.attr('data-preview-url') || '') : (targetInput.val() || ''));
				var image = control.find('.ggm-repeater-media__preview img');
				var empty = control.find('.ggm-repeater-media__preview span');
				var clear = control.find('.ggm-media-clear-btn');

				if ($.trim(targetInput.val() || '') && url) {
					image.attr('src', url).prop('hidden', false);
					empty.prop('hidden', true);
					clear.prop('disabled', false);
				} else {
					image.attr('src', '').prop('hidden', true);
					empty.prop('hidden', false);
					clear.prop('disabled', true);
				}
			}

			$('.ggm-repeater-media .ggm-media-url').each(function() {
				syncRepeaterMediaPreview($(this));
			});
			$('.ggm-repeater-media .ggm-media-id').each(function() {
				syncRepeaterMediaPreview($(this));
			});

			$(document).on('error', '.ggm-repeater-media__preview img', function() {
				$(this).prop('hidden', true).siblings('span').prop('hidden', false);
			});

			$(document).on('click', '.ggm-media-upload-btn', function(e) {
				e.preventDefault();
				var button = $(this);
				var targetId = button.data('target');
				var targetInput = $('#' + targetId);
				var mediaType = button.data('media-type');

				var file_frame = wp.media.frames.file_frame = wp.media({
					title: 'Select File',
					button: { text: 'Use File' },
					multiple: false,
					library: mediaType ? { type: mediaType } : undefined
				});

				file_frame.on('select', function() {
					var attachment = file_frame.state().get('selection').first().toJSON();
					var previewUrl = attachment.sizes && attachment.sizes.thumbnail
						? attachment.sizes.thumbnail.url
						: attachment.url;
					if ('id' === button.data('value-type')) {
						targetInput.val(attachment.id).attr('data-preview-url', previewUrl).trigger('change');
						syncRepeaterMediaPreview(targetInput, previewUrl);
					} else {
						targetInput.val(attachment.url).trigger('change');
						syncRepeaterMediaPreview(targetInput);
					}
				});

				file_frame.open();
			});

			$(document).on('click', '.ggm-media-clear-btn', function(e) {
				e.preventDefault();
				var targetId = $(this).data('target');
				var targetInput = $('#' + targetId);
				targetInput.val('').attr('data-preview-url', '').trigger('change');
				syncRepeaterMediaPreview(targetInput);
			});
		});
		</script>
		<?php
	}

	/**
	 * Print repeaters logic and templates scripts.
	 */
	private function print_repeaters_js() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		$this->print_media_uploader_js();
		$slot_types = class_exists( 'GGM_Workshop_Slot' ) ? GGM_Workshop_Slot::types() : array( 'live' => __( 'Live', 'ggm-member-dashboard' ) );
		?>
		<script>
		jQuery(document).ready(function($) {
			var ggmWorkshopSlotTypes = <?php echo wp_json_encode( $slot_types ); ?>;
			function repeaterMediaHtml(name, id) {
				return '<div class="ggm-repeater-media">' +
					'<input type="hidden" name="' + name + '" class="ggm-media-url" id="' + id + '">' +
					'<div class="ggm-repeater-media__preview"><img src="" alt="" hidden><span>No image</span></div>' +
					'<div class="ggm-repeater-media__actions">' +
						'<button type="button" class="button ggm-media-upload-btn" data-target="' + id + '">Upload</button>' +
						'<button type="button" class="button ggm-media-clear-btn" data-target="' + id + '" disabled>Clear</button>' +
					'</div>' +
				'</div>';
			}
			var workshopStart = document.getElementById('ggm-workshop-start-date');
			var workshopEnd = document.getElementById('ggm-workshop-end-date');
			if (workshopStart && workshopEnd) {
				workshopStart.addEventListener('change', function() {
					workshopEnd.min = workshopStart.value;
					if (workshopEnd.value && workshopStart.value && workshopEnd.value < workshopStart.value) {
						workshopEnd.value = workshopStart.value;
					}
				});
			}
			$(document).on('click', '.ggm-block-heading-edit', function(e) {
				e.preventDefault();
				var editor = $(this).closest('.ggm-block-heading-editor');
				var display = editor.find('.ggm-block-heading-editor__display');
				var form = editor.find('.ggm-block-heading-editor__form');
				var input = form.find('input[type="text"]');

				display.attr('hidden', true);
				form.prop('hidden', false);
				input.prop('disabled', false).trigger('focus').trigger('select');
			});

			// Add Row handlers.
			$(document).on('click', '.ggm-add-row-btn', function(e) {
				e.preventDefault();
				var key = $(this).data('key');
				var table = $('#ggm-table-' + key + ' tbody');
				// Use max existing data-index + 1 (not row count): if a row is
				// removed and a new one added, count-based indexing can reuse
				// an index still held by another row, and duplicate array keys
				// in $_POST silently overwrite the earlier row on save.
				var index = 0;
				table.find('tr').each(function () {
					var rowIndex = parseInt($(this).attr('data-index'), 10);
					if (!isNaN(rowIndex) && rowIndex >= index) {
						index = rowIndex + 1;
					}
				});
				var rowHtml = '';

				if (key === 'workshop_time_slots') {
					var slotTypeOptions = '';
					$.each(ggmWorkshopSlotTypes, function(typeKey, typeLabel) {
						slotTypeOptions += '<option value="' + $('<div>').text(typeKey).html() + '">' + $('<div>').text(typeLabel).html() + '</option>';
					});
					rowHtml = '<tr data-index="' + index + '">' +
						'<td>' +
							'<input type="hidden" name="workshop_time_slots[' + index + '][id]" value="0">' +
							'<select name="workshop_time_slots[' + index + '][type]">' + slotTypeOptions + '</select>' +
						'</td>' +
						'<td><input type="time" name="workshop_time_slots[' + index + '][start]" required></td>' +
						'<td><input type="time" name="workshop_time_slots[' + index + '][end]" required></td>' +
						'<td><input type="url" name="workshop_time_slots[' + index + '][meeting_link]" class="widefat" placeholder="https://zoom.us/j/..."></td>' +
						'<td style="text-align:center;"><input type="checkbox" name="workshop_time_slots[' + index + '][enabled]" value="1" checked></td>' +
						'<td><button type="button" class="button button-link-delete ggm-remove-row-btn">Remove</button></td>' +
					'</tr>';
				} else if (key === 'ggm_you_will_discover') {
					rowHtml = '<tr data-index="' + index + '">' +
						'<td><input type="text" name="ggm_you_will_discover[' + index + '][heading]" class="widefat"></td>' +
						'<td><textarea name="ggm_you_will_discover[' + index + '][description]" class="widefat" rows="2"></textarea></td>' +
						'<td class="ggm-repeater-media-cell">' + repeaterMediaHtml('ggm_you_will_discover[' + index + '][image]', 'yd-' + index) + '</td>' +
						'<td><button type="button" class="button button-link-delete ggm-remove-row-btn">Remove</button></td>' +
					'</tr>';
				} else if (key === 'ggm_why_different_points') {
					rowHtml = '<tr data-index="' + index + '">' +
						'<td><input type="text" name="ggm_why_different_points[' + index + '][title]" class="widefat"></td>' +
						'<td class="ggm-repeater-media-cell">' + repeaterMediaHtml('ggm_why_different_points[' + index + '][icon]', 'wd-' + index) + '</td>' +
						'<td><button type="button" class="button button-link-delete ggm-remove-row-btn">Remove</button></td>' +
					'</tr>';
				} else if (key === 'ggm_workshop_why_workshop_different_points') {
					rowHtml = '<tr data-index="' + index + '">' +
						'<td><input type="text" name="ggm_workshop_why_workshop_different_points[' + index + '][text]" class="widefat"></td>' +
						'<td><button type="button" class="button button-link-delete ggm-remove-row-btn">Remove</button></td>' +
					'</tr>';
				} else if (key === 'ggm_workshop_journey_days') {
					rowHtml = '<tr data-index="' + index + '">' +
						'<td><input type="text" name="ggm_workshop_journey_days[' + index + '][day]" class="widefat" placeholder="Day ' + (index + 1) + '"></td>' +
						'<td><textarea name="ggm_workshop_journey_days[' + index + '][description]" class="widefat" rows="2"></textarea></td>' +
						'<td class="ggm-repeater-media-cell">' + repeaterMediaHtml('ggm_workshop_journey_days[' + index + '][image]', 'ggm-journey-day-' + index) + '</td>' +
						'<td><button type="button" class="button button-link-delete ggm-remove-row-btn">Remove</button></td>' +
					'</tr>';
				} else if (key === 'ggm_perfect_for_you') {
					rowHtml = '<tr data-index="' + index + '">' +
						'<td><input type="text" name="ggm_perfect_for_you[' + index + '][title]" class="widefat"></td>' +
						'<td><textarea name="ggm_perfect_for_you[' + index + '][description]" class="widefat" rows="2"></textarea></td>' +
						'<td class="ggm-repeater-media-cell">' + repeaterMediaHtml('ggm_perfect_for_you[' + index + '][image]', 'pf-' + index) + '</td>' +
						'<td><button type="button" class="button button-link-delete ggm-remove-row-btn">Remove</button></td>' +
					'</tr>';
				} else if (key === 'ggm_faq') {
					rowHtml = '<tr data-index="' + index + '">' +
						'<td><input type="text" name="ggm_faq[' + index + '][question]" class="widefat"></td>' +
						'<td><textarea name="ggm_faq[' + index + '][answer]" class="widefat" rows="2"></textarea></td>' +
						'<td><button type="button" class="button button-link-delete ggm-remove-row-btn">Remove</button></td>' +
					'</tr>';
				} else if (key === 'lesson_focus_areas') {
					rowHtml = '<tr data-index="' + index + '">' +
						'<td><input type="text" name="lesson_focus_areas[' + index + '][focus_area]" class="widefat"></td>' +
						'<td><button type="button" class="button button-link-delete ggm-remove-row-btn">Remove</button></td>' +
					'</tr>';
				}

				table.append(rowHtml);
			});

			// Remove Row handler.
			$(document).on('click', '.ggm-remove-row-btn', function(e) {
				e.preventDefault();
				$(this).closest('tr').remove();
			});

			// Sale Price must not exceed Regular Price — inline warning only;
			// the authoritative check happens server-side on save.
			function checkSalePrice() {
				var regular = parseFloat($('#ggm-workshop-regular-price').val()) || 0;
				var sale    = parseFloat($('#ggm-workshop-sale-price').val());
				$('#ggm-sale-price-error').toggle(!isNaN(sale) && sale > regular);
			}
			$(document).on('input', '#ggm-workshop-regular-price, #ggm-workshop-sale-price', checkSalePrice);
		});
		</script>
		<style>
		.ggm-meta-table th { width: 200px; font-weight: 600; }
		.ggm-repeater-block { margin-top: 25px; padding-bottom: 25px; border-bottom: 1px solid #eee; }
		.ggm-repeater-block h4 { font-weight: 600; margin-bottom: 10px; }
		.ggm-block-heading-editor__display h4 { display: flex; align-items: center; gap: 5px; }
		.ggm-block-heading-edit { display: inline-flex; align-items: center; justify-content: center; padding: 2px; border: 0; background: transparent; color: #2271b1; cursor: pointer; }
		.ggm-block-heading-edit:hover, .ggm-block-heading-edit:focus { color: #135e96; }
		.ggm-block-heading-edit .dashicons { width: 18px; height: 18px; font-size: 18px; }
		.ggm-block-heading-editor__form { display: flex; align-items: center; gap: 8px; margin: 8px 0 12px; }
		.ggm-block-heading-editor__form[hidden] { display: none; }
		.ggm-block-heading-editor__form .large-text { flex: 1 1 auto; width: auto; }
		.ggm-block-heading-save { flex: 0 0 auto; }
		.ggm-repeater-table { margin-bottom: 15px; }
		.ggm-repeater-table th { font-weight: 600; }
		.ggm-workshop-content-repeater { width: 100%; table-layout: fixed; }
		.ggm-workshop-content-repeater--description th:nth-child(1),
		.ggm-workshop-content-repeater--description td:nth-child(1) { width: 21%; }
		.ggm-workshop-content-repeater--description th:nth-child(2),
		.ggm-workshop-content-repeater--description td:nth-child(2) { width: 49%; }
		.ggm-workshop-content-repeater--description th:nth-child(3),
		.ggm-workshop-content-repeater--description td:nth-child(3) { width: 22%; }
		.ggm-workshop-content-repeater--media th:nth-child(1),
		.ggm-workshop-content-repeater--media td:nth-child(1) { width: 63%; }
		.ggm-workshop-content-repeater--media th:nth-child(2),
		.ggm-workshop-content-repeater--media td:nth-child(2) { width: 29%; }
		.ggm-workshop-content-repeater--faq th:nth-child(1),
		.ggm-workshop-content-repeater--faq td:nth-child(1) { width: 34%; }
		.ggm-workshop-content-repeater--faq th:nth-child(2),
		.ggm-workshop-content-repeater--faq td:nth-child(2) { width: 58%; }
		.ggm-workshop-content-repeater textarea { box-sizing: border-box; width: 100%; min-height: 68px; resize: vertical; }
		.ggm-repeater-media-cell { vertical-align: middle; }
		.ggm-repeater-media { display: flex; align-items: center; gap: 8px; min-width: 0; }
		.ggm-repeater-media__preview { display: grid; flex: 0 0 76px; place-items: center; box-sizing: border-box; width: 76px; height: 50px; overflow: hidden; border: 1px solid #dcdcde; border-radius: 4px; background: #f6f7f7; }
		.ggm-repeater-media__preview img { display: block; width: 100%; height: 100%; object-fit: contain; }
		.ggm-repeater-media__preview img[hidden], .ggm-repeater-media__preview span[hidden] { display: none; }
		.ggm-repeater-media__preview span { padding: 4px; color: #646970; font-size: 11px; line-height: 1.15; text-align: center; }
		.ggm-repeater-media__actions { display: flex; flex: 1 1 auto; min-width: 0; flex-wrap: wrap; gap: 5px; }
		.ggm-repeater-media__actions .button { min-height: 30px; margin: 0; }
		.ggm-image-upload-wrap { display: flex; align-items: center; gap: 8px; }
		.ggm-checkbox-list { max-height: 180px; overflow-y: auto; border: 1px solid #dcdcde; border-radius: 4px; padding: 8px 12px; background: #fff; }
		.ggm-checkbox-list__item { display: block; padding: 3px 0; font-weight: normal; }
		.ggm-currency-adjustments{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;max-width:720px}.ggm-currency-adjustments label{display:flex;align-items:center;gap:8px}.ggm-currency-adjustments span{min-width:42px;font-weight:700}.ggm-currency-adjustments input{width:100%}
		@media (max-width: 1100px) {
			.ggm-repeater-block { overflow-x: auto; }
			.ggm-workshop-content-repeater { min-width: 760px; }
		}
		</style>
		<?php
	}
}
