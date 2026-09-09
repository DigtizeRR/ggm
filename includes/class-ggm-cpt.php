<?php
/**
 * Custom Post Types registration.
 *
 * Registers ggm_workshop, ggm_lesson, ggm_product CPTs with
 * full label sets and meta-box support.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_CPT {

	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'init', $this, 'register_post_types', 10 );
	}

	public function register_post_types() {
		$this->register_workshop();
		$this->register_lesson();
		$this->register_product();
	}

	private function register_workshop() {
		if ( post_type_exists( 'ggm_workshop' ) ) {
			return;
		}
		register_post_type( 'ggm_workshop', array(
			'labels'             => $this->labels( 'Workshop', 'Workshops' ),
			'public'             => true,
			'show_in_menu'       => false,
			'show_in_rest'       => true,
			'rewrite'            => array( 'slug' => 'ggm-workshop' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'menu_icon'          => 'dashicons-welcome-learn-more',
		) );
	}

	private function register_lesson() {
		if ( post_type_exists( 'ggm_lesson' ) ) {
			return;
		}
		register_post_type( 'ggm_lesson', array(
			'labels'          => $this->labels( 'Lesson', 'Lessons' ),
			'public'          => true,
			'show_in_menu'    => false,
			'show_in_rest'    => true,
			'rewrite'         => array( 'slug' => 'course/%course_name%', 'with_front' => false ),
			'capability_type' => 'post',
			'has_archive'     => false,
			'hierarchical'    => false,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
		) );
	}

	private function register_product() {
		if ( post_type_exists( 'ggm_product' ) ) {
			return;
		}
		register_post_type( 'ggm_product', array(
			'labels'          => $this->labels( 'Product', 'Products' ),
			'public'          => false,
			'show_in_menu'    => false,
			'show_in_rest'    => true,
			'rewrite'         => array( 'slug' => 'ggm-product' ),
			'capability_type' => 'post',
			'has_archive'     => true,
			'hierarchical'    => false,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
		) );
	}

	private function labels( $singular, $plural ) {
		return array(
			'name'               => _x( $plural,               'post type general name', 'ggm-member-dashboard' ),
			'singular_name'      => _x( $singular,             'post type singular name', 'ggm-member-dashboard' ),
			'menu_name'          => _x( $plural,               'admin menu',             'ggm-member-dashboard' ),
			'add_new'            => __( 'Add New',                                        'ggm-member-dashboard' ),
			'add_new_item'       => sprintf( __( 'Add New %s', 'ggm-member-dashboard' ), $singular ),
			'edit_item'          => sprintf( __( 'Edit %s',    'ggm-member-dashboard' ), $singular ),
			'new_item'           => sprintf( __( 'New %s',     'ggm-member-dashboard' ), $singular ),
			'view_item'          => sprintf( __( 'View %s',    'ggm-member-dashboard' ), $singular ),
			'all_items'          => sprintf( __( 'All %s',     'ggm-member-dashboard' ), $plural ),
			'search_items'       => sprintf( __( 'Search %s',  'ggm-member-dashboard' ), $plural ),
			'not_found'          => sprintf( __( 'No %s found.',         'ggm-member-dashboard' ), strtolower( $plural ) ),
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash.','ggm-member-dashboard' ), strtolower( $plural ) ),
		);
	}
}
