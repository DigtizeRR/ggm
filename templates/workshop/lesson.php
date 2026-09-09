<?php
/**
 * Single Lesson Custom Post Type template.
 *
 * Checks access and renders lesson video player, overview, focus areas, and lesson list sidebar.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lesson_id        = get_the_ID();
$meta             = GGM_Lesson::get_meta( $lesson_id );
$allow_free_preview = ! empty( $meta['is_preview'] ) && '1' === $meta['is_preview'];
$has_access       = GGM_Lesson::user_has_access( $lesson_id );
$is_locked        = ! $has_access && ! $allow_free_preview;

get_header();

$course_id   = ! empty( $meta['course'] ) ? (int) $meta['course'] : 0;
$lessons     = $course_id ? GGM_Lesson::get_by_workshop( $course_id ) : array();
$adj         = GGM_Lesson::get_adjacent( $lesson_id );
$prev_id     = $adj['prev_id'];
$next_id     = $adj['next_id'];

// Video embed — meta key is video_embed (may be full embed code or a plain URL).
$video_embed_raw = ! empty( $meta['video_embed'] ) ? $meta['video_embed'] : '';
$embed_html      = '';

if ( empty( $video_embed_raw ) ) {
	$embed_html = '<div class="ggm-video-placeholder"><span class="dashicons dashicons-video-alt3"></span><p>' . esc_html__( 'No video uploaded for this lesson.', 'ggm-member-dashboard' ) . '</p></div>';
} elseif ( strpos( $video_embed_raw, '<iframe' ) !== false || strpos( $video_embed_raw, '<video' ) !== false ) {
	// Already an embed/HTML snippet — use as-is.
	$embed_html = $video_embed_raw;
} elseif ( strpos( $video_embed_raw, 'youtube.com' ) !== false || strpos( $video_embed_raw, 'youtu.be' ) !== false ) {
	preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $video_embed_raw, $match );
	$video_id = $match[1] ?? '';
	if ( $video_id ) {
		$embed_html = '<iframe src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '?rel=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="ggm-iframe-player"></iframe>';
	}
} elseif ( strpos( $video_embed_raw, 'vimeo.com' ) !== false ) {
	preg_match( '/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?)/', $video_embed_raw, $matches );
	$video_id = $matches[3] ?? '';
	if ( $video_id ) {
		$embed_html = '<iframe src="https://player.vimeo.com/video/' . esc_attr( $video_id ) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen class="ggm-iframe-player"></iframe>';
	}
} else {
	// Raw video file URL.
	$embed_html = '<video src="' . esc_url( $video_embed_raw ) . '" controls controlsList="nodownload" class="ggm-video-tag-player" style="width:100%; border-radius:12px;"></video>';
}

$what_to_expect   = ! empty( $meta['what_to_expect'] ) ? $meta['what_to_expect'] : '';
$best_practices   = ! empty( $meta['best_practices'] ) ? $meta['best_practices'] : '';
$focus_areas      = ! empty( $meta['lesson_focus_areas'] ) && is_array( $meta['lesson_focus_areas'] ) ? $meta['lesson_focus_areas'] : array();
?>
<div class="ggm-public-container ggm-lesson-page-shell">
	<div class="ggm-lesson-grid">
		<?php
		if ( $course_id ) {
			echo do_shortcode( '[ggm_lesson_sidebar course_id="' . absint( $course_id ) . '" lesson_id="' . absint( $lesson_id ) . '"]' );
		}
		?>

		<div class="ggm-lesson-main">
			<?php if ( $is_locked ) : ?>
				<div class="ggm-lesson-locked-hero">
					<div class="ggm-lesson-locked-icon" aria-hidden="true">&#128274;</div>
					<h1><?php esc_html_e( 'GGM Paid Membership Required', 'ggm-member-dashboard' ); ?></h1>
					<p><?php esc_html_e( 'Please contact admin for access.', 'ggm-member-dashboard' ); ?></p>
				</div>

				<div class="ggm-lesson-locked-card">
					<h2><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( 'GGM Paid Membership Required', 'ggm-member-dashboard' ); ?></h2>
					<p><?php esc_html_e( 'You must be a paid member to access this content.', 'ggm-member-dashboard' ); ?></p>
					<?php
					$checkout_url = get_permalink( (int) ggm_get_setting( 'ggm_checkout_page_id', 0 ) ) ?: home_url( '/membership-checkout/' );
					?>
					<a class="ggm-lesson-join-btn" href="<?php echo esc_url( $checkout_url ); ?>"><?php esc_html_e( 'Join Now', 'ggm-member-dashboard' ); ?></a>
				</div>
			<?php else : ?>
			<!-- Video container wrapper with 16:9 aspect ratio styling -->
			<div class="ggm-video-container-wrapper" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #000; border-radius: 12px;">
				<?php echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<!-- Lesson title -->
			<div class="ggm-lesson-title-nav" style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; border-bottom: 1px solid #edf2f7; padding-bottom: 20px;">
				<div>
					<h1 style="font-size: 24px; font-weight: 700; margin: 0 0 5px 0; color:#0f172a;"><?php the_title(); ?></h1>
				</div>
			</div>

			<!-- Tabs: Overview | Focus Areas -->
			<div class="ggm-lesson-tabs" style="margin-top: 30px;">
				<nav class="nav-tab-wrapper" style="border-bottom: 1px solid #e2e8f0; display:flex; gap:10px;">
					<a href="#ggm-tab-overview" class="nav-tab nav-tab-active" style="cursor:pointer;"><?php esc_html_e( 'Overview', 'ggm-member-dashboard' ); ?></a>
					<?php if ( ! empty( $focus_areas ) ) : ?>
						<a href="#ggm-tab-focus-areas" class="nav-tab" style="cursor:pointer;"><?php esc_html_e( 'Focus Areas', 'ggm-member-dashboard' ); ?></a>
					<?php endif; ?>
				</nav>

				<!-- Overview Tab -->
				<div class="ggm-tab-content active" id="ggm-tab-overview-content" style="padding: 20px 0;">
					<?php if ( $what_to_expect ) : ?>
						<div class="ggm-overview-section" style="margin-bottom: 24px;">
							<h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0 0 10px 0;"><?php esc_html_e( 'What to Expect', 'ggm-member-dashboard' ); ?></h3>
							<div class="ggm-editor-content" style="font-size: 14px; line-height: 1.7; color: #475569;">
								<?php echo wp_kses_post( $what_to_expect ); ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $best_practices ) : ?>
						<div class="ggm-overview-section">
							<h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0 0 10px 0;"><?php esc_html_e( 'Best Practices', 'ggm-member-dashboard' ); ?></h3>
							<div class="ggm-editor-content" style="font-size: 14px; line-height: 1.7; color: #475569;">
								<?php echo wp_kses_post( $best_practices ); ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! $what_to_expect && ! $best_practices ) : ?>
						<p class="text-muted" style="font-size:14px; color:#94a3b8;"><?php esc_html_e( 'No overview content available for this lesson.', 'ggm-member-dashboard' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Focus Areas Tab -->
				<?php if ( ! empty( $focus_areas ) ) : ?>
					<div class="ggm-tab-content" id="ggm-tab-focus-areas-content" style="padding: 20px 0; display:none;">
						<ul class="ggm-focus-areas-list" style="list-style: none; padding: 0; margin: 0;">
							<?php foreach ( $focus_areas as $area ) : ?>
								<li style="display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155;">
									<span class="dashicons dashicons-yes-alt" style="color: #3b82f6; flex-shrink: 0;"></span>
									<?php echo esc_html( is_array( $area ) && isset( $area['focus_area'] ) ? $area['focus_area'] : $area ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Bottom Navigation Bar: Prev / Next lesson -->
	<?php if ( $prev_id || $next_id ) : ?>
		<div class="ggm-lesson-bottom-nav" style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
			<div>
				<?php if ( $prev_id ) : ?>
					<a href="<?php echo esc_url( get_permalink( $prev_id ) ); ?>" class="ggm-btn-secondary button-small" style="display: inline-flex; align-items: center; gap: 4px;">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<span>
							<span style="display:block; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.05em;"><?php esc_html_e( 'Previous', 'ggm-member-dashboard' ); ?></span>
							<span style="font-size:13px;"><?php echo esc_html( get_the_title( $prev_id ) ); ?></span>
						</span>
					</a>
				<?php endif; ?>
			</div>
			<div>
				<?php if ( $next_id ) : ?>
					<a href="<?php echo esc_url( get_permalink( $next_id ) ); ?>" class="ggm-btn-primary button-small" style="display: inline-flex; align-items: center; gap: 4px; text-align:right;">
						<span>
							<span style="display:block; font-size:11px; color:rgba(255,255,255,0.75); text-transform:uppercase; letter-spacing:.05em;"><?php esc_html_e( 'Next', 'ggm-member-dashboard' ); ?></span>
							<span style="font-size:13px;"><?php echo esc_html( get_the_title( $next_id ) ); ?></span>
						</span>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<style>
.ggm-iframe-player {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	border: 0;
}
.ggm-sidebar-lesson-item:hover {
	background-color: #f8fafc;
}
.ggm-sidebar-lesson-item.active {
	background-color: #eff6ff !important;
}
@media (max-width: 768px) {
	.ggm-lesson-grid {
		grid-template-columns: 1fr !important;
	}
}
</style>

<script>
document.querySelectorAll('.ggm-lesson-tabs .nav-tab').forEach(function(tab) {
	tab.addEventListener('click', function(e) {
		e.preventDefault();
		document.querySelectorAll('.ggm-lesson-tabs .nav-tab').forEach(function(el) {
			el.classList.remove('nav-tab-active');
		});
		this.classList.add('nav-tab-active');

		document.querySelectorAll('.ggm-lesson-tabs .ggm-tab-content').forEach(function(content) {
			content.style.display = 'none';
		});

		var target = this.getAttribute('href') + '-content';
		document.querySelector(target).style.display = 'block';
	});
});
</script>
<?php
get_footer();
