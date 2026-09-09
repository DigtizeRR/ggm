<?php
/**
 * Elementor URL Dynamic Tag for opening a GGM form in a popup.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Elementor_Form_Popup_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name(): string {
		return 'ggm-form-popup-url';
	}

	public function get_title(): string {
		return esc_html__( 'GGM Form Popup URL', 'ggm-member-dashboard' );
	}

	public function get_group(): array {
		return array( 'ggm-forms' );
	}

	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
	}

	protected function register_controls(): void {
		$options = array( '' => esc_html__( 'Select a published popup form', 'ggm-member-dashboard' ) );
		if ( class_exists( 'GGM_Form_Builder' ) ) {
			foreach ( GGM_Form_Builder::get_elementor_popup_forms() as $form ) {
				$options[ (string) $form->id ] = $form->title;
			}
		}

		$this->add_control(
			'form_id',
			array(
				'label'   => esc_html__( 'Form', 'ggm-member-dashboard' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);
	}

	public function render(): void {
		$form_id = absint( $this->get_settings( 'form_id' ) );
		if ( ! $form_id ) {
			return;
		}

		echo esc_url( '#ggm-form-popup-' . $form_id );
	}
}
