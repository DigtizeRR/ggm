<?php
/**
 * Elementor text Dynamic Tags for global workshop block headings.
 *
 * Loaded only after Elementor's Dynamic Tag classes are available.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class GGM_Elementor_Workshop_Heading_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_group(): array {
		return array( 'ggm-workshop' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	protected function register_controls(): void {}

	abstract protected function get_section_key(): string;

	public function render(): void {
		echo esc_html( ggm_get_workshop_block_heading( $this->get_section_key(), $this->resolve_workshop_id() ) );
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

		foreach ( array_unique( array_filter( array_map( 'absint', $candidates ) ) ) as $post_id ) {
			if ( in_array( get_post_type( $post_id ), array( 'workshop', 'ggm_workshop' ), true ) ) {
				return $post_id;
			}
		}

		return 0;
	}
}

class GGM_Elementor_Workshop_Discover_Heading_Tag extends GGM_Elementor_Workshop_Heading_Tag {
	public function get_name(): string {
		return 'ggm-workshop-discover-heading';
	}

	public function get_title(): string {
		return esc_html__( 'GGM Discover Heading', 'ggm-member-dashboard' );
	}

	protected function get_section_key(): string {
		return 'discover';
	}
}

class GGM_Elementor_Workshop_Why_Different_Heading_Tag extends GGM_Elementor_Workshop_Heading_Tag {
	public function get_name(): string {
		return 'ggm-workshop-why-different-heading';
	}

	public function get_title(): string {
		return esc_html__( 'GGM Why Different Heading', 'ggm-member-dashboard' );
	}

	protected function get_section_key(): string {
		return 'why_different';
	}
}

class GGM_Elementor_Workshop_Perfect_For_Heading_Tag extends GGM_Elementor_Workshop_Heading_Tag {
	public function get_name(): string {
		return 'ggm-workshop-perfect-for-heading';
	}

	public function get_title(): string {
		return esc_html__( 'GGM Perfect For Heading', 'ggm-member-dashboard' );
	}

	protected function get_section_key(): string {
		return 'perfect_for';
	}
}

class GGM_Elementor_Workshop_FAQ_Heading_Tag extends GGM_Elementor_Workshop_Heading_Tag {
	public function get_name(): string {
		return 'ggm-workshop-faq-heading';
	}

	public function get_title(): string {
		return esc_html__( 'GGM FAQ Heading', 'ggm-member-dashboard' );
	}

	protected function get_section_key(): string {
		return 'faq';
	}
}
