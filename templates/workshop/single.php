<?php
/**
 * Single Workshop template — rich display with all GGM meta fields.
 *
 * Two-column layout: 7/12 content left, 5/12 sticky sidebar right.
 * Mobile: single column, sidebar stacks below.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$workshop_id = get_the_ID();
$meta        = GGM_Workshop::get_meta( $workshop_id );
$user_id     = get_current_user_id();
$has_access  = GGM_Workshop::user_has_access( get_current_user_id(), $workshop_id );
$lessons     = GGM_Lesson::get_by_workshop( $workshop_id );

$block_headings = array(
	'discover'      => ggm_get_workshop_block_heading( 'discover', $workshop_id ),
	'why_different' => ggm_get_workshop_block_heading( 'why_different', $workshop_id ),
	'perfect_for'   => ggm_get_workshop_block_heading( 'perfect_for', $workshop_id ),
	'faq'           => ggm_get_workshop_block_heading( 'faq', $workshop_id ),
);

$is_free         = ( isset( $meta['is_free'] ) && $meta['is_free'] === '1' );
$currency_symbol = function_exists( 'ggm_get_setting' ) ? ggm_get_setting( 'ggm_currency_symbol', '₹' ) : '₹';
$selected_currency = class_exists( 'GGM_Currency' ) && GGM_Currency::is_enabled()
	? GGM_Currency::selected_currency()
	: ( function_exists( 'ggm_get_setting' ) ? ggm_get_setting( 'ggm_currency', 'INR' ) : 'INR' );

// First lesson permalink (used by multiple CTAs).
$first_lesson_url = ( ! empty( $lessons ) ) ? get_permalink( $lessons[0]->ID ) : '';

// Time slots.
$active_slots      = class_exists( 'GGM_Workshop_Slot' ) ? GGM_Workshop_Slot::get_for_workshop( $workshop_id, 'active' ) : array();
$needs_slot_choice = count( $active_slots ) > 1;
$registration_nonce   = wp_create_nonce( 'ggm_nonce' );
$user_registration    = null;
if ( $user_id ) {
	global $wpdb;
	$access_table = $wpdb->prefix . 'ggm_workshop_access';
	$user_registration = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$access_table} WHERE user_id = %d AND workshop_id = %d",
		$user_id,
		$workshop_id
	) );
}
$registered_slot_time = '';
if ( $user_registration && ! empty( $user_registration->slot_id ) && class_exists( 'GGM_Workshop_Slot' ) ) {
	$registered_slot = GGM_Workshop_Slot::get( $user_registration->slot_id );
	if ( $registered_slot ) {
		$registered_slot_time = GGM_Workshop_Slot::format_range( $registered_slot );
	}
}

// Enroll URL — internal checkout for paid workshops.
$checkout_page_url = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );
$enroll_url         = add_query_arg( array( 'workshop_id' => $workshop_id, 'ggm_currency' => $selected_currency ), $checkout_page_url );

// Instructor.
$instructor = get_post_meta( $workshop_id, 'instructor', true );

// Helpers — safe array field.
$arr = function( $key ) use ( $meta ) {
	return ( ! empty( $meta[ $key ] ) && is_array( $meta[ $key ] ) ) ? $meta[ $key ] : array();
};
?>
<!-- ============================================================
     INLINE STYLES
     ============================================================ -->
<style>
/* ---- Reset ---- */
.ggm-ws-wrap *,
.ggm-ws-wrap *::before,
.ggm-ws-wrap *::after { box-sizing: border-box; }

/* ---- Page wrapper ---- */
.ggm-ws-wrap {
	max-width: 1200px;
	margin: 0 auto;
	padding: 32px 20px 64px;
	font-family: inherit;
	color: #1e293b;
}

/* ---- Two-column grid ---- */
.ggm-ws-grid {
	display: grid;
	grid-template-columns: 7fr 5fr;
	gap: 40px;
	align-items: start;
}
@media (max-width: 860px) {
	.ggm-ws-grid {
		grid-template-columns: 1fr;
	}
	.ggm-ws-sidebar {
		order: -1; /* sidebar above content on mobile */
	}
}

/* ---- Section spacing ---- */
.ggm-ws-section {
	margin-bottom: 48px;
}
.ggm-ws-section:last-child {
	margin-bottom: 0;
}

/* ---- Section headings ---- */
.ggm-ws-section-title {
	font-size: 1.35rem;
	font-weight: 700;
	color: var(--ggm-primary, #1d4ed8);
	border-bottom: 2px solid var(--ggm-primary, #1d4ed8);
	padding-bottom: 8px;
	margin: 0 0 24px;
}

/* ---- Workshop title ---- */
.ggm-ws-title {
	font-size: clamp(1.5rem, 3vw, 2.2rem);
	font-weight: 800;
	line-height: 1.25;
	margin: 0 0 14px;
	color: #0f172a;
}

/* ---- Short description ---- */
.ggm-ws-short-desc {
	font-size: 1.05rem;
	color: #475569;
	line-height: 1.7;
	margin: 0 0 40px;
}

/* ---- "You Will Discover" cards ---- */
.ggm-discover-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: 20px;
}
.ggm-discover-card {
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	overflow: hidden;
	background: #f8fafc;
	text-align: center;
	padding-bottom: 14px;
}
.ggm-discover-card img {
	width: 100%;
	height: 120px;
	object-fit: cover;
	display: block;
}
.ggm-discover-card-heading {
	font-size: 0.85rem;
	font-weight: 600;
	color: #334155;
	padding: 10px 10px 0;
	line-height: 1.4;
}

/* ---- "Why Different" points ---- */
.ggm-why-content {
	font-size: 0.975rem;
	color: #475569;
	line-height: 1.75;
	margin-bottom: 20px;
}
.ggm-why-points {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.ggm-why-point {
	display: flex;
	align-items: center;
	gap: 14px;
	background: #f0f9ff;
	border-radius: 8px;
	padding: 12px 16px;
}
.ggm-why-point-icon {
	width: 36px;
	height: 36px;
	object-fit: contain;
	flex-shrink: 0;
}
.ggm-why-point-icon-fallback {
	width: 36px;
	height: 36px;
	flex-shrink: 0;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: var(--ggm-primary, #1d4ed8);
	color: #fff;
	border-radius: 50%;
	font-size: 1rem;
	font-weight: 700;
}
.ggm-why-point-title {
	font-size: 0.9rem;
	font-weight: 600;
	color: #1e293b;
}

/* ---- "Perfect For You" cards ---- */
.ggm-perfect-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
	gap: 20px;
}
.ggm-perfect-card {
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	overflow: hidden;
	background: #fff;
	text-align: center;
	padding-bottom: 14px;
}
.ggm-perfect-card img {
	width: 100%;
	height: 130px;
	object-fit: cover;
	display: block;
}
.ggm-perfect-card-title {
	font-size: 0.875rem;
	font-weight: 600;
	color: #334155;
	padding: 10px 10px 0;
	line-height: 1.4;
}

/* ---- FAQ accordion ---- */
.ggm-faq-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.ggm-faq-item {
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	overflow: hidden;
}
.ggm-faq-question {
	width: 100%;
	background: #f8fafc;
	border: none;
	cursor: pointer;
	text-align: left;
	padding: 16px 20px;
	font-size: 0.95rem;
	font-weight: 600;
	color: #1e293b;
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 10px;
	line-height: 1.45;
}
.ggm-faq-question:hover {
	background: #f0f9ff;
}
.ggm-faq-chevron {
	flex-shrink: 0;
	width: 18px;
	height: 18px;
	transition: transform 0.25s;
	color: var(--ggm-primary, #1d4ed8);
}
.ggm-faq-item.open .ggm-faq-chevron {
	transform: rotate(180deg);
}
.ggm-faq-answer {
	display: none;
	padding: 16px 20px;
	font-size: 0.9rem;
	color: #475569;
	line-height: 1.75;
	background: #fff;
	border-top: 1px solid #e2e8f0;
}
.ggm-faq-item.open .ggm-faq-answer {
	display: block;
}

/* ---- Lesson syllabus ---- */
.ggm-syllabus-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
	counter-reset: lesson-counter;
}
.ggm-syllabus-item {
	counter-increment: lesson-counter;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 14px 18px;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	background: #f8fafc;
}
.ggm-syllabus-left {
	display: flex;
	align-items: center;
	gap: 14px;
	flex: 1;
	min-width: 0;
}
.ggm-lesson-num {
	font-size: 1rem;
	font-weight: 700;
	color: var(--ggm-primary, #1d4ed8);
	width: 28px;
	flex-shrink: 0;
}
.ggm-lesson-title {
	font-size: 0.9rem;
	font-weight: 600;
	color: #1e293b;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.ggm-lesson-duration {
	font-size: 0.8rem;
	color: #64748b;
	margin-top: 2px;
}
.ggm-syllabus-right a {
	font-size: 0.82rem;
	font-weight: 600;
	color: #fff;
	background: var(--ggm-primary, #1d4ed8);
	padding: 6px 14px;
	border-radius: 6px;
	text-decoration: none;
	white-space: nowrap;
}
.ggm-syllabus-right a:hover {
	opacity: 0.85;
}
.ggm-syllabus-lock {
	font-size: 0.8rem;
	color: #94a3b8;
}

/* ---- Sidebar sticky wrapper ---- */
.ggm-ws-sticky {
	position: sticky;
	top: 32px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

/* ---- Featured image ---- */
.ggm-ws-thumb {
	width: 100%;
	border-radius: 12px;
	overflow: hidden;
	line-height: 0;
}
.ggm-ws-thumb img {
	width: 100%;
	height: auto;
	display: block;
}
.ggm-ws-thumb-placeholder {
	background: #e2e8f0;
	height: 200px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #94a3b8;
	font-size: 3rem;
	border-radius: 12px;
}

/* ---- Info card ---- */
.ggm-ws-info-card,
.ggm-ws-cta-card,
.ggm-ws-instructor-card {
	background: #fff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 22px;
}
.ggm-ws-info-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 0;
	border-bottom: 1px solid #f1f5f9;
	font-size: 0.88rem;
}
.ggm-ws-info-row:last-child {
	border-bottom: none;
	padding-bottom: 0;
}
.ggm-info-label {
	color: #64748b;
	font-weight: 500;
}
.ggm-info-value {
	color: #1e293b;
	font-weight: 600;
	text-align: right;
}

/* ---- CTA card ---- */
.ggm-ws-price {
	font-size: 2rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 6px;
}
.ggm-ws-price-note {
	font-size: 0.8rem;
	color: #64748b;
	margin-bottom: 18px;
}
.ggm-btn-enroll {
	display: block;
	width: 100%;
	text-align: center;
	background: var(--ggm-primary, #1d4ed8);
	color: #fff !important;
	font-size: 1rem;
	font-weight: 700;
	padding: 14px 20px;
	border-radius: 8px;
	text-decoration: none !important;
	border: none;
	cursor: pointer;
	transition: opacity 0.2s;
}
.ggm-btn-enroll:hover { opacity: 0.88; }
.ggm-btn-enroll.green {
	background: #16a34a;
}
.ggm-enrolled-badge {
	display: flex;
	align-items: center;
	gap: 10px;
	background: #f0fdf4;
	border: 1px solid #bbf7d0;
	border-radius: 8px;
	padding: 12px 16px;
	margin-bottom: 16px;
	color: #15803d;
	font-weight: 700;
	font-size: 0.9rem;
}
.ggm-enrolled-badge-icon {
	font-size: 1.3rem;
}

/* ---- Instructor card ---- */
.ggm-instructor-name {
	font-size: 1rem;
	font-weight: 700;
	color: #1e293b;
	margin: 0 0 4px;
}
.ggm-instructor-label {
	font-size: 0.8rem;
	color: #64748b;
	margin-bottom: 8px;
	text-transform: uppercase;
	letter-spacing: 0.05em;
}
.ggm-instructor-bio {
	font-size: 0.85rem;
	color: #475569;
	line-height: 1.65;
}
</style>

<div class="ggm-ws-wrap">
<div class="ggm-ws-grid">

	<!-- =========================================================
	     LEFT COLUMN — main content (7/12)
	     ========================================================= -->
	<div class="ggm-ws-main">

		<!-- 1. Title -->
		<h1 class="ggm-ws-title"><?php the_title(); ?></h1>

		<!-- 2. Short description -->
		<?php if ( ! empty( $meta['workshop_short_desc'] ) ) : ?>
			<p class="ggm-ws-short-desc"><?php echo wp_kses_post( $meta['workshop_short_desc'] ); ?></p>
		<?php endif; ?>

		<!-- 3. You Will Discover -->
		<?php $discover_items = $arr( 'You_Will_Discover' ); if ( ! empty( $discover_items ) ) : ?>
		<div class="ggm-ws-section">
			<h2 class="ggm-ws-section-title"><?php echo esc_html( $block_headings['discover'] ); ?></h2>
			<div class="ggm-discover-grid">
				<?php foreach ( $discover_items as $item ) :
					$heading = isset( $item['heading'] ) ? $item['heading'] : '';
					$img_url = isset( $item['image'] ) ? $item['image'] : '';
				?>
				<div class="ggm-discover-card">
					<?php if ( $img_url ) : ?>
						<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $heading ); ?>" loading="lazy">
					<?php endif; ?>
					<?php if ( $heading ) : ?>
						<p class="ggm-discover-card-heading"><?php echo esc_html( $heading ); ?></p>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- 4. Why This Webinar Is Different -->
		<?php
		$why_content = isset( $meta['why_this_webinar_different_content'] ) ? $meta['why_this_webinar_different_content'] : '';
		$why_points  = $arr( 'why_this_webinar_different_points_and_image' );
		if ( $why_content || ! empty( $why_points ) ) :
		?>
		<div class="ggm-ws-section">
			<h2 class="ggm-ws-section-title">
				<?php echo esc_html( $block_headings['why_different'] ); ?>
			</h2>
			<?php if ( $why_content ) : ?>
				<div class="ggm-why-content"><?php echo wp_kses_post( $why_content ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $why_points ) ) : ?>
				<ul class="ggm-why-points">
					<?php foreach ( $why_points as $i => $point ) :
						$title    = isset( $point['title'] ) ? $point['title'] : '';
						$icon_url = isset( $point['icon'] ) ? $point['icon'] : '';
					?>
					<li class="ggm-why-point">
						<?php if ( $icon_url ) : ?>
							<img class="ggm-why-point-icon" src="<?php echo esc_url( $icon_url ); ?>" alt="" loading="lazy">
						<?php else : ?>
							<span class="ggm-why-point-icon-fallback" aria-hidden="true"><?php echo esc_html( $i + 1 ); ?></span>
						<?php endif; ?>
						<span class="ggm-why-point-title"><?php echo esc_html( $title ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<!-- 5. Perfect For You If You Want To -->
		<?php $perfect_items = $arr( 'perfect_for_you_if_you_want_to' ); if ( ! empty( $perfect_items ) ) : ?>
		<div class="ggm-ws-section">
			<h2 class="ggm-ws-section-title"><?php echo esc_html( $block_headings['perfect_for'] ); ?></h2>
			<div class="ggm-perfect-grid">
				<?php foreach ( $perfect_items as $item ) :
					$title   = isset( $item['title'] ) ? $item['title'] : '';
					$img_url = isset( $item['image'] ) ? $item['image'] : '';
				?>
				<div class="ggm-perfect-card">
					<?php if ( $img_url ) : ?>
						<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
					<?php endif; ?>
					<?php if ( $title ) : ?>
						<p class="ggm-perfect-card-title"><?php echo esc_html( $title ); ?></p>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- 6. FAQ accordion -->
		<?php $faq_items = $arr( 'faq' ); if ( ! empty( $faq_items ) ) : ?>
		<div class="ggm-ws-section">
			<h2 class="ggm-ws-section-title"><?php echo esc_html( $block_headings['faq'] ); ?></h2>
			<div class="ggm-faq-list">
				<?php foreach ( $faq_items as $idx => $faq ) :
					$question = isset( $faq['question'] ) ? $faq['question'] : '';
					$answer   = isset( $faq['answer'] ) ? $faq['answer'] : '';
					if ( ! $question && ! $answer ) continue;
					$item_id = 'ggm-faq-' . $workshop_id . '-' . $idx;
				?>
				<div class="ggm-faq-item" id="<?php echo esc_attr( $item_id ); ?>">
					<button
						class="ggm-faq-question"
						type="button"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $item_id . '-answer' ); ?>"
					>
						<span><?php echo esc_html( $question ); ?></span>
						<svg class="ggm-faq-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
						</svg>
					</button>
					<div class="ggm-faq-answer" id="<?php echo esc_attr( $item_id . '-answer' ); ?>">
						<?php echo wp_kses_post( $answer ); ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- 7. Lesson syllabus -->
		<div class="ggm-ws-section">
			<h2 class="ggm-ws-section-title">Lesson Syllabus</h2>
			<?php if ( empty( $lessons ) ) : ?>
				<p style="color:#64748b;font-size:0.9rem;">No lessons have been added to this workshop yet.</p>
			<?php else : ?>
				<div class="ggm-syllabus-list">
					<?php foreach ( $lessons as $index => $lesson ) :
						$lesson_meta     = GGM_Lesson::get_meta( $lesson->ID );
						$lesson_duration = ! empty( $lesson_meta['lesson_duration'] ) ? $lesson_meta['lesson_duration'] : '';
					?>
					<div class="ggm-syllabus-item">
						<div class="ggm-syllabus-left">
							<span class="ggm-lesson-num"><?php echo esc_html( str_pad( $index + 1, 2, '0', STR_PAD_LEFT ) ); ?>.</span>
							<div>
								<div class="ggm-lesson-title"><?php echo esc_html( $lesson->post_title ); ?></div>
								<?php if ( $lesson_duration ) : ?>
									<div class="ggm-lesson-duration">⏱ <?php echo esc_html( $lesson_duration ); ?></div>
								<?php endif; ?>
							</div>
						</div>
						<div class="ggm-syllabus-right">
							<?php if ( $has_access || $is_free ) : ?>
								<a href="<?php echo esc_url( get_permalink( $lesson->ID ) ); ?>">▶ Watch</a>
							<?php else : ?>
								<span class="ggm-syllabus-lock" title="Locked">🔒</span>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

	</div><!-- /.ggm-ws-main -->


	<!-- =========================================================
	     RIGHT COLUMN — sticky sidebar (5/12)
	     ========================================================= -->
	<div class="ggm-ws-sidebar">
		<div class="ggm-ws-sticky">

			<!-- Featured image -->
			<div class="ggm-ws-thumb">
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'decoding' => 'async' ) ); ?>
				<?php else : ?>
					<div class="ggm-ws-thumb-placeholder" aria-hidden="true">🎓</div>
				<?php endif; ?>
			</div>

			<!-- Workshop info card -->
			<div class="ggm-ws-info-card">
				<?php
				$info_rows = array();
				if ( ! empty( $meta['workshop_date'] ) ) {
					$info_rows[] = array( '📅 Date', $meta['workshop_date'] );
				}
				if ( 1 === count( $active_slots ) ) {
					$info_rows[] = array( '🕐 Time', GGM_Workshop_Slot::format_range( $active_slots[0] ) );
				} elseif ( $needs_slot_choice ) {
					$slot_labels = array_map( array( 'GGM_Workshop_Slot', 'format_range' ), $active_slots );
					$info_rows[] = array( '🕐 Time Slots', implode( ', ', $slot_labels ) );
				}
				if ( ! empty( $meta['workshop_mode'] ) ) {
					$info_rows[] = array( '📡 Mode', $meta['workshop_mode'] );
				}
				if ( ! empty( $meta['duration'] ) ) {
					$info_rows[] = array( '⏱ Duration', $meta['duration'] );
				}
				foreach ( $info_rows as $row ) :
				?>
				<div class="ggm-ws-info-row">
					<span class="ggm-info-label"><?php echo esc_html( $row[0] ); ?></span>
					<span class="ggm-info-value"><?php echo esc_html( $row[1] ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- Price / CTA card -->
			<div class="ggm-ws-cta-card">
				<?php if ( $is_free && $needs_slot_choice && ! $user_registration ) : ?>
					<!-- FREE workshop requiring a time slot choice -->
					<p style="font-size:1.1rem;font-weight:700;color:#16a34a;margin:0 0 12px;">🎉 Free Workshop</p>
					<select id="ggm-ws-slot-select" style="width:100%;margin-bottom:10px;padding:10px;border-radius:8px;border:1px solid #d1d5db;">
						<option value=""><?php esc_html_e( 'Choose Your Workshop Time', 'ggm-member-dashboard' ); ?></option>
						<?php foreach ( $active_slots as $slot ) : ?>
							<option value="<?php echo esc_attr( $slot->id ); ?>"><?php echo esc_html( GGM_Workshop_Slot::format_range( $slot ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="ggm-ws-register-btn" class="ggm-btn-enroll green" data-workshop="<?php echo esc_attr( $workshop_id ); ?>" data-nonce="<?php echo esc_attr( $registration_nonce ); ?>">
						Register Now
					</button>
					<p id="ggm-ws-register-feedback" style="margin-top:10px;font-size:13px;"></p>

				<?php elseif ( $is_free ) : ?>
					<!-- FREE workshop -->
					<p style="font-size:1.1rem;font-weight:700;color:#16a34a;margin:0 0 16px;">🎉 Free Workshop</p>
					<?php if ( $registered_slot_time ) : ?>
						<p class="ggm-ws-price-note">✅ <?php echo esc_html( sprintf( __( 'Registered for %s', 'ggm-member-dashboard' ), $registered_slot_time ) ); ?></p>
					<?php endif; ?>
					<?php if ( $first_lesson_url ) : ?>
						<a href="<?php echo esc_url( $first_lesson_url ); ?>" class="ggm-btn-enroll green">
							▶ Start Watching
						</a>
					<?php endif; ?>

				<?php elseif ( $has_access ) : ?>
					<!-- Enrolled -->
					<div class="ggm-enrolled-badge">
						<span class="ggm-enrolled-badge-icon">✅</span>
						<span>You're Enrolled</span>
					</div>
					<?php if ( $registered_slot_time ) : ?>
						<p class="ggm-ws-price-note"><?php echo esc_html( sprintf( __( 'Your time slot: %s', 'ggm-member-dashboard' ), $registered_slot_time ) ); ?></p>
					<?php endif; ?>
					<?php if ( $first_lesson_url ) : ?>
						<a href="<?php echo esc_url( $first_lesson_url ); ?>" class="ggm-btn-enroll">
							▶ Go to Workshop
						</a>
					<?php endif; ?>

				<?php else : ?>
					<!-- Not enrolled — show price + enroll button -->
					<?php $ws_price = GGM_Workshop::resolve_price( $workshop_id ); ?>
					<?php if ( $ws_price > 0 ) : ?>
						<p class="ggm-ws-price">
							<?php echo wp_kses_post( GGM_Workshop::price_html( $workshop_id ) ); ?>
						</p>
						<p class="ggm-ws-price-note">One-time enrollment</p>
					<?php endif; ?>
					<?php
					// Contribution ("pay what you want") workshops still need the
					// dedicated checkout page to let the visitor pick an amount —
					// [ggm_workshop_join_now] deliberately renders nothing for those.
					// For every other paid workshop, a logged-in member pays right
					// here (no checkout-page redirect, straight to the dashboard on
					// success); a guest still falls back to the checkout page inside
					// the shortcode itself.
					$ws_is_contribution = class_exists( 'GGM_Workshop' ) && GGM_Workshop::is_contribution( $workshop_id );
					?>
					<?php if ( $ws_is_contribution ) : ?>
						<a href="<?php echo esc_url( $enroll_url ); ?>" class="ggm-btn-enroll">
							Enroll Now →
						</a>
					<?php else : ?>
						<?php echo do_shortcode( sprintf( '[ggm_workshop_join_now id="%d" text="Enroll Now →" class="ggm-btn-enroll"]', $workshop_id ) ); ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<!-- Instructor card -->
			<?php if ( $instructor ) : ?>
			<div class="ggm-ws-instructor-card">
				<p class="ggm-instructor-label">Instructor</p>
				<?php if ( is_array( $instructor ) ) :
					$name = isset( $instructor['name'] ) ? $instructor['name'] : '';
					$bio  = isset( $instructor['bio'] )  ? $instructor['bio']  : '';
				?>
					<?php if ( $name ) : ?>
						<p class="ggm-instructor-name"><?php echo esc_html( $name ); ?></p>
					<?php endif; ?>
					<?php if ( $bio ) : ?>
						<p class="ggm-instructor-bio"><?php echo wp_kses_post( $bio ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p class="ggm-instructor-name"><?php echo esc_html( $instructor ); ?></p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

		</div><!-- /.ggm-ws-sticky -->
	</div><!-- /.ggm-ws-sidebar -->

</div><!-- /.ggm-ws-grid -->
</div><!-- /.ggm-ws-wrap -->

<script>
(function () {
	document.querySelectorAll('.ggm-faq-question').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var item = btn.closest('.ggm-faq-item');
			var isOpen = item.classList.contains('open');
			// Close all
			document.querySelectorAll('.ggm-faq-item.open').forEach(function (el) {
				el.classList.remove('open');
				el.querySelector('.ggm-faq-question').setAttribute('aria-expanded', 'false');
			});
			// Toggle clicked
			if (!isOpen) {
				item.classList.add('open');
				btn.setAttribute('aria-expanded', 'true');
			}
		});
	});

	var registerBtn = document.getElementById('ggm-ws-register-btn');
	if (registerBtn) {
		registerBtn.addEventListener('click', function () {
			var slotSelect = document.getElementById('ggm-ws-slot-select');
			var feedback   = document.getElementById('ggm-ws-register-feedback');
			var slotId     = slotSelect ? slotSelect.value : '';

			if (slotSelect && !slotId) {
				feedback.style.color = '#b91c1c';
				feedback.textContent = '<?php echo esc_js( __( 'Please choose a workshop time slot.', 'ggm-member-dashboard' ) ); ?>';
				return;
			}

			registerBtn.disabled = true;
			registerBtn.textContent = '<?php echo esc_js( __( 'Registering…', 'ggm-member-dashboard' ) ); ?>';

			var data = new URLSearchParams();
			data.append('action', 'ggm_register_workshop');
			data.append('nonce', registerBtn.getAttribute('data-nonce'));
			data.append('workshop_id', registerBtn.getAttribute('data-workshop'));
			data.append('slot_id', slotId);

			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: data.toString()
			}).then(function (r) { return r.json(); }).then(function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					registerBtn.disabled = false;
					registerBtn.textContent = 'Register Now';
					feedback.style.color = '#b91c1c';
					feedback.textContent = res.data.message || '<?php echo esc_js( __( 'Something went wrong.', 'ggm-member-dashboard' ) ); ?>';
				}
			}).catch(function () {
				registerBtn.disabled = false;
				registerBtn.textContent = 'Register Now';
				feedback.style.color = '#b91c1c';
				feedback.textContent = '<?php echo esc_js( __( 'Something went wrong.', 'ggm-member-dashboard' ) ); ?>';
			});
		});
	}
})();
</script>

<?php get_footer(); ?>
