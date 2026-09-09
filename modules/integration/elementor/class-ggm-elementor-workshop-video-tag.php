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
		$workshop_id = $this->resolve_workshop_id();
		if ( ! $workshop_id ) {
			return;
		}

		$url = ggm_sanitize_youtube_url( get_post_meta( $workshop_id, 'ggm_workshop_featured_video_url', true ) );
		if ( $url ) {
			echo esc_url( $url );
		}
	}

	/**
	 * Resolve the workshop represented by a singular, loop, or editor context.
	 *
	 * @return int
	 */
	private function resolve_workshop_id() {
		$candidates = array( get_the_ID(), get_queried_object_id() );

		if (
			class_exists( '\Elementor\Plugin' )
			&& isset( \Elementor\Plugin::$instance )
			&& isset( \Elementor\Plugin::$instance->documents )
			&& method_exists( \Elementor\Plugin::$instance->documents, 'get_current' )
		) {
			$document = \Elementor\Plugin::$instance->documents->get_current();
			if ( $document && method_exists( $document, 'get_main_id' ) ) {
				$candidates[] = $document->get_main_id();
			}
		}

		$is_elementor_preview = false;
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
			$editor = \Elementor\Plugin::$instance->editor ?? null;
			$preview = \Elementor\Plugin::$instance->preview ?? null;
			$is_elementor_preview = ( $editor && method_exists( $editor, 'is_edit_mode' ) && $editor->is_edit_mode() )
				|| ( $preview && method_exists( $preview, 'is_preview_mode' ) && $preview->is_preview_mode() );
		}

		foreach ( array_unique( array_filter( array_map( 'absint', $candidates ) ) ) as $post_id ) {
			if ( ! in_array( get_post_type( $post_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
				continue;
			}

			if ( 'publish' === get_post_status( $post_id ) || ( $is_elementor_preview && current_user_can( 'edit_post', $post_id ) ) ) {
				return $post_id;
			}
		}

		return 0;
	}
}
