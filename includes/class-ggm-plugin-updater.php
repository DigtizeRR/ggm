<?php
/**
 * Retrieves private-plugin update metadata and registers it with WordPress.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Plugin_Updater {
	const CACHE_KEY = 'ggm_plugin_update_manifest';

	/**
	 * Register update hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
	}

	/**
	 * Add an available update to WordPress' normal update response.
	 *
	 * @param object $transient Plugin update transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$manifest = $this->get_manifest();
		if ( ! $manifest || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
			return $transient;
		}

		$current_version = isset( $transient->checked[ GGM_PLUGIN_BASENAME ] )
			? $transient->checked[ GGM_PLUGIN_BASENAME ]
			: GGM_VERSION;

		if ( version_compare( $manifest['version'], $current_version, '<=' ) ) {
			return $transient;
		}

		$update = (object) array(
			'id'          => 'ggm-member-dashboard',
			'slug'        => 'ggm-member-dashboard',
			'plugin'      => GGM_PLUGIN_BASENAME,
			'new_version' => $manifest['version'],
			'url'         => isset( $manifest['homepage'] ) ? esc_url_raw( $manifest['homepage'] ) : '',
			'package'     => esc_url_raw( $manifest['download_url'] ),
			'tested'      => isset( $manifest['tested'] ) ? sanitize_text_field( $manifest['tested'] ) : '',
			'requires'    => isset( $manifest['requires'] ) ? sanitize_text_field( $manifest['requires'] ) : '',
			'requires_php'=> isset( $manifest['requires_php'] ) ? sanitize_text_field( $manifest['requires_php'] ) : '',
		);

		$transient->response[ GGM_PLUGIN_BASENAME ] = $update;
		return $transient;
	}

	/**
	 * Provide the changelog/details modal in Plugins > Installed Plugins.
	 *
	 * @param false|object|array $result Result object.
	 * @param string             $action Requested API action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'ggm-member-dashboard' !== $args->slug ) {
			return $result;
		}

		$manifest = $this->get_manifest();
		if ( ! $manifest ) {
			return $result;
		}

		return (object) array(
			'name'          => isset( $manifest['name'] ) ? sanitize_text_field( $manifest['name'] ) : 'Digtize LMS System',
			'slug'          => 'ggm-member-dashboard',
			'version'       => isset( $manifest['version'] ) ? $manifest['version'] : GGM_VERSION,
			'requires'      => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'requires_php'  => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
			'tested'        => isset( $manifest['tested'] ) ? $manifest['tested'] : '',
			'homepage'      => isset( $manifest['homepage'] ) ? esc_url_raw( $manifest['homepage'] ) : '',
			'download_link' => isset( $manifest['download_url'] ) ? esc_url_raw( $manifest['download_url'] ) : '',
			'sections'      => array(
				'description' => isset( $manifest['sections']['description'] ) ? wp_kses_post( $manifest['sections']['description'] ) : '',
				'changelog'   => isset( $manifest['sections']['changelog'] ) ? wp_kses_post( $manifest['sections']['changelog'] ) : '',
			),
		);
	}

	/**
	 * Fetch and cache a validated update manifest.
	 *
	 * @return array|null
	 */
	private function get_manifest() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = apply_filters( 'ggm_plugin_update_manifest_url', GGM_UPDATE_MANIFEST_URL );
		$response = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) || ! preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $manifest['version'] ) ) {
			return null;
		}

		set_site_transient( self::CACHE_KEY, $manifest, 12 * HOUR_IN_SECONDS );
		return $manifest;
	}
}
