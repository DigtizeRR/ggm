<?php
/**
 * Lightweight contract test for the workshop Bottom Image Elementor tag.
 *
 * Run with: php scripts/test-elementor-bottom-image-tag.php
 */

namespace Elementor\Core\DynamicTags {
	class Data_Tag {}
}

namespace Elementor\Modules\DynamicTags {
	class Module {
		const IMAGE_CATEGORY = 'image';
		const MEDIA_CATEGORY = 'media';
	}
}

namespace {
	define( 'ABSPATH', dirname( __DIR__ ) );

	$GLOBALS['ggm_test_workshop_id']  = 77;
	$GLOBALS['ggm_test_attachment_id'] = 321;
	$GLOBALS['ggm_test_is_image']      = true;

	function esc_html__( $value, $domain ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $value;
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function get_post_meta( $post_id, $key, $single ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $GLOBALS['ggm_test_attachment_id'];
	}

	function wp_attachment_is_image( $attachment_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $GLOBALS['ggm_test_is_image'];
	}

	function wp_get_attachment_image_url( $attachment_id, $size ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $attachment_id ? 'https://example.test/bottom.jpg' : false;
	}

	function esc_url_raw( $value ) {
		return $value;
	}

	class GGM_Elementor {
		public static function resolve_current_workshop_id() {
			return $GLOBALS['ggm_test_workshop_id'];
		}
	}

	require dirname( __DIR__ ) . '/modules/integration/elementor/class-ggm-elementor-workshop-bottom-image-tag.php';

	$assert_same = static function ( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			fwrite( STDERR, $message . PHP_EOL );
			exit( 1 );
		}
	};

	$tag = new \GGM_Elementor_Workshop_Bottom_Image_Tag();
	$assert_same( 'ggm-workshop-bottom-image', $tag->get_name(), 'Tag slug test failed.' );
	$assert_same( 'Bottom Image', $tag->get_title(), 'Tag title test failed.' );
	$assert_same( array( 'ggm-workshop' ), $tag->get_group(), 'Tag group test failed.' );
	$assert_same( array( 'image', 'media' ), $tag->get_categories(), 'Tag category test failed.' );
	$assert_same(
		array( 'id' => 321, 'url' => 'https://example.test/bottom.jpg' ),
		$tag->get_value(),
		'Valid image value test failed.'
	);

	$GLOBALS['ggm_test_is_image'] = false;
	$assert_same( array( 'id' => 0, 'url' => '' ), $tag->get_value(), 'Invalid attachment test failed.' );

	$GLOBALS['ggm_test_is_image']     = true;
	$GLOBALS['ggm_test_workshop_id'] = 0;
	$assert_same( array( 'id' => 0, 'url' => '' ), $tag->get_value(), 'Missing workshop context test failed.' );

	echo 'Bottom Image dynamic tag contract tests passed.' . PHP_EOL;
}
