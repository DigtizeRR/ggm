<?php
/**
 * Elementor image/media Dynamic Tag for a workshop's Bottom Image.
 *
 * Loaded only after Elementor's Dynamic Tag classes are available.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Elementor_Workshop_Bottom_Image_Tag extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name(): string {
		return 'ggm-workshop-bottom-image';
	}

	public function get_title(): string {
		return esc_html__( 'Bottom Image', 'ggm-member-dashboard' );
	}

	public function get_group(): array {
		return array( 'ggm-workshop' );
	}

	public function get_categories(): array {
		return array(
			\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::MEDIA_CATEGORY,
		);
	}

	protected function register_controls(): void {}

	/**
	 * Return the structured media value expected by Elementor image controls.
	 *
	 * @param array $options Elementor rendering options.
	 * @return array{id:int,url:string}
	 */
	public function get_value( array $options = array() ): array {
		$empty = array(
			'id'  => 0,
			'url' => '',
		);

		if ( ! class_exists( 'GGM_Elementor' ) ) {
			return $empty;
		}

		$workshop_id = GGM_Elementor::resolve_current_workshop_id();
		if ( ! $workshop_id ) {
			return $empty;
		}

		$attachment_id = absint( get_post_meta( $workshop_id, 'ggm_workshop_bottom_image_id', true ) );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return $empty;
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $url ) {
			return $empty;
		}

		return array(
			'id'  => $attachment_id,
			'url' => esc_url_raw( $url ),
		);
	}
}
