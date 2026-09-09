<?php
/**
 * Elementor URL Dynamic Tag for a normal post's blog video URL.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Elementor_Blog_Video_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name(): string {
		return 'ggm-blog-video';
	}

	public function get_title(): string {
		return esc_html__( 'Success Story Video', 'ggm-member-dashboard' );
	}

	public function get_group(): array {
		return array( 'ggm-blog' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
	}

	protected function register_controls(): void {}

	/**
	 * Print the post video URL for Elementor URL controls.
	 */
	public function render(): void {
		$post_id = $this->resolve_post_id();
		if ( ! $post_id ) {
			return;
		}

		$url = esc_url_raw( get_post_meta( $post_id, 'ggm_blog_video_url', true ), array( 'http', 'https' ) );
		if ( $url ) {
			echo esc_url( $url );
		}
	}

	/**
	 * Resolve the normal blog post from singular, loop, or editor context.
	 *
	 * @return int
	 */
	private function resolve_post_id() {
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
			$editor  = \Elementor\Plugin::$instance->editor ?? null;
			$preview = \Elementor\Plugin::$instance->preview ?? null;
			$is_elementor_preview = ( $editor && method_exists( $editor, 'is_edit_mode' ) && $editor->is_edit_mode() )
				|| ( $preview && method_exists( $preview, 'is_preview_mode' ) && $preview->is_preview_mode() );
		}

		foreach ( array_unique( array_filter( array_map( 'absint', $candidates ) ) ) as $post_id ) {
			if ( 'post' !== get_post_type( $post_id ) ) {
				continue;
			}

			if ( 'publish' === get_post_status( $post_id ) || ( $is_elementor_preview && current_user_can( 'edit_post', $post_id ) ) ) {
				return $post_id;
			}
		}

		return 0;
	}
}
