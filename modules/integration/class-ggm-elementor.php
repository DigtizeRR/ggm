<?php
/**
 * Optional Elementor integration bridge.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Elementor {

	/**
	 * Wait until Elementor is available before touching any of its classes.
	 */
	public function init() {
		add_action( 'init', array( $this, 'repair_double_serialized_page_assets' ), 1 );

		if ( did_action( 'elementor/loaded' ) ) {
			$this->on_elementor_loaded();
			return;
		}

		add_action( 'elementor/loaded', array( $this, 'on_elementor_loaded' ) );
	}

	/**
	 * Elementor 3.x expects `_elementor_page_assets` to resolve to an array.
	 * Some older/imported documents contain a serialized array wrapped in a
	 * second serialization layer, so get_post_meta() returns the inner
	 * serialized string and Elementor throws a TypeError in wp_head().
	 *
	 * Normalize only that known malformed shape, once. Valid arrays, empty
	 * values, and every other Elementor meta key are left untouched.
	 */
	public function repair_double_serialized_page_assets() {
		if ( get_option( 'ggm_elementor_page_assets_repaired_v1' ) ) {
			return;
		}

		global $wpdb;
		$post_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_page_assets' LIMIT 500" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		foreach ( array_map( 'absint', (array) $post_ids ) as $post_id ) {
			$value = get_post_meta( $post_id, '_elementor_page_assets', true );
			if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
				continue;
			}

			$normalized = maybe_unserialize( $value );
			if ( is_array( $normalized ) ) {
				update_post_meta( $post_id, '_elementor_page_assets', $normalized );
			}
		}

		update_option( 'ggm_elementor_page_assets_repaired_v1', gmdate( 'c' ), false );
	}

	/**
	 * Attach to Elementor's supported Dynamic Tags registration hook.
	 */
	public function on_elementor_loaded() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_dynamic_tags' ) );
		add_filter( 'elementor/widget/render_content', array( $this, 'render_workshop_featured_media_fallback' ), 10, 2 );
	}

	/**
	 * Replace the explicitly configured GGM Workshop Video widget with the
	 * current workshop's Featured Image when it has no valid featured video.
	 *
	 * Elementor's native Video widget cannot treat an image URL as a fallback:
	 * it would try to play it. Filtering the widget content is therefore the
	 * smallest safe integration point, and checking the dynamic-tag reference
	 * prevents this setting from changing unrelated Video widgets.
	 *
	 * @param string $content Rendered widget content.
	 * @param object $widget  Elementor widget instance.
	 * @return string
	 */
	public function render_workshop_featured_media_fallback( $content, $widget ) {
		if ( ! $this->is_workshop_featured_media_fallback_enabled() || ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'video' !== $widget->get_name() ) {
			return $content;
		}

		if ( ! method_exists( $widget, 'get_settings' ) || ! $this->uses_workshop_video_dynamic_tag( $widget->get_settings() ) ) {
			return $content;
		}

		$workshop_id = self::resolve_current_workshop_id();
		if ( ! $workshop_id || ggm_sanitize_youtube_url( get_post_meta( $workshop_id, 'ggm_workshop_featured_video_url', true ) ) || ! has_post_thumbnail( $workshop_id ) ) {
			return $content;
		}

		$thumbnail_id = get_post_thumbnail_id( $workshop_id );
		$alt          = trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			$alt = get_the_title( $workshop_id );
		}

		$image = get_the_post_thumbnail(
			$workshop_id,
			'full',
			array(
				'class' => 'ggm-elementor-workshop-featured-media__image',
				'alt'   => $alt,
				'style' => 'display:block;height:auto;width:100%;',
			)
		);

		return $image ? '<div class="elementor-wrapper ggm-elementor-workshop-featured-media">' . $image . '</div>' : $content;
	}

	/**
	 * Whether the administrator has opted into the Video-widget image fallback.
	 * The default is on so existing templates that already use the GGM tag gain
	 * the requested fallback immediately after upgrading.
	 */
	private function is_workshop_featured_media_fallback_enabled() {
		return ! empty( ggm_get_setting( 'ggm_elementor_workshop_featured_media_fallback_enabled', '1' ) );
	}

	/**
	 * Detect the GGM tag in Elementor's raw dynamic-control configuration.
	 *
	 * @param array $settings Widget settings.
	 * @return bool
	 */
	private function uses_workshop_video_dynamic_tag( $settings ) {
		if ( ! is_array( $settings ) || empty( $settings['__dynamic__'] ) || ! is_array( $settings['__dynamic__'] ) ) {
			return false;
		}

		$dynamic_value = $settings['__dynamic__']['youtube_url'] ?? '';
		return is_string( $dynamic_value ) && false !== strpos( $dynamic_value, 'ggm-workshop-featured-video-url' );
	}

	/**
	 * Resolve the Workshop represented by a singular, loop, or editor context.
	 *
	 * @return int
	 */
	public static function resolve_current_workshop_id() {
		$candidates = array( get_the_ID(), get_queried_object_id() );

		if (
			class_exists( '\\Elementor\\Plugin' )
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
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
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

	/**
	 * Register the GGM group and workshop featured-video URL tag.
	 *
	 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Dynamic tags manager.
	 */
	public function register_dynamic_tags( $dynamic_tags_manager ) {
		if (
			! is_object( $dynamic_tags_manager )
			|| ! method_exists( $dynamic_tags_manager, 'register_group' )
			|| ! method_exists( $dynamic_tags_manager, 'register' )
			|| ! class_exists( '\Elementor\Core\DynamicTags\Tag' )
			|| ! class_exists( '\Elementor\Modules\DynamicTags\Module' )
		) {
			return;
		}

		$dynamic_tags_manager->register_group(
			'ggm-workshop',
			array( 'title' => esc_html__( 'GGM Workshop', 'ggm-member-dashboard' ) )
		);
		$dynamic_tags_manager->register_group(
			'ggm-forms',
			array( 'title' => esc_html__( 'GGM Forms', 'ggm-member-dashboard' ) )
		);
		$dynamic_tags_manager->register_group(
			'ggm-blog',
			array( 'title' => esc_html__( 'GGM Blog', 'ggm-member-dashboard' ) )
		);

		require_once GGM_PLUGIN_DIR . 'modules/integration/elementor/class-ggm-elementor-workshop-video-tag.php';
		if ( class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) {
			require_once GGM_PLUGIN_DIR . 'modules/integration/elementor/class-ggm-elementor-workshop-bottom-image-tag.php';
		}
		require_once GGM_PLUGIN_DIR . 'modules/integration/elementor/class-ggm-elementor-workshop-heading-tags.php';
		require_once GGM_PLUGIN_DIR . 'modules/integration/elementor/class-ggm-elementor-form-popup-tag.php';
		require_once GGM_PLUGIN_DIR . 'modules/integration/elementor/class-ggm-elementor-blog-video-tag.php';

		if ( class_exists( 'GGM_Elementor_Workshop_Video_Tag' ) ) {
			$dynamic_tags_manager->register( new GGM_Elementor_Workshop_Video_Tag() );
		}
		if ( class_exists( 'GGM_Elementor_Workshop_Bottom_Image_Tag' ) ) {
			$dynamic_tags_manager->register( new GGM_Elementor_Workshop_Bottom_Image_Tag() );
		}
		if ( class_exists( 'GGM_Elementor_Blog_Video_Tag' ) ) {
			$dynamic_tags_manager->register( new GGM_Elementor_Blog_Video_Tag() );
		}
		if ( class_exists( 'GGM_Elementor_Form_Popup_Tag' ) ) {
			$dynamic_tags_manager->register( new GGM_Elementor_Form_Popup_Tag() );
		}

		$heading_tag_classes = array(
			'GGM_Elementor_Workshop_Discover_Heading_Tag',
			'GGM_Elementor_Workshop_Why_Different_Heading_Tag',
			'GGM_Elementor_Workshop_Perfect_For_Heading_Tag',
			'GGM_Elementor_Workshop_FAQ_Heading_Tag',
		);
		foreach ( $heading_tag_classes as $heading_tag_class ) {
			if ( class_exists( $heading_tag_class ) ) {
				$dynamic_tags_manager->register( new $heading_tag_class() );
			}
		}
	}
}
