<?php
/**
 * Elementor URL Dynamic Tag for a workshop's featured YouTube video.
 *
 * Loaded only after Elementor's Dynamic Tag classes are available.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Elementor_Workshop_Video_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name(): string {
		return 'ggm-workshop-featured-video-url';
	}

	public function get_title(): string {
		return esc_html__( 'GGM Workshop Featured Video URL', 'ggm-member-dashboard' );
	}

	public function get_group(): array {
		return array( 'ggm-workshop' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
	}

	protected function register_controls(): void {}

	/**
	 * Print the normalized YouTube URL expected by Elementor's Video control.
	 */
	public function render(): void {
		$workshop_id = class_exists( 'GGM_Elementor' ) ? GGM_Elementor::resolve_current_workshop_id() : 0;
		if ( ! $workshop_id ) {
			return;
		}

		$url = ggm_sanitize_youtube_url( get_post_meta( $workshop_id, 'ggm_workshop_featured_video_url', true ) );
		if ( $url ) {
			echo esc_url( $url );
		}
	}
}
