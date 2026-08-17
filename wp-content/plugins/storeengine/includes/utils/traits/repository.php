<?php

namespace StoreEngine\Utils\Traits;

use Plugin_Upgrader;
use Theme_Upgrader;
use WP_Ajax_Upgrader_Skin;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Repository {

	/**
	 * Install/Activate Free Themes.
	 *
	 * @param string $theme_slug Slug that match on wp.org repo.
	 *
	 * @return true|WP_Error
	 */
	public static function install_and_active_theme( string $theme_slug ) {
		// Include required WordPress files for theme management.
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		self::require_dependencies();

		// Check if the theme is already installed.
		$installed_themes = wp_get_themes();
		if ( isset( $installed_themes[ $theme_slug ] ) ) {
			switch_theme( $theme_slug );
			return true;
		}

		// Fetch theme information from wp.org repo.
		$theme_info = themes_api('theme_information', array(
			'slug' => $theme_slug,
			'fields' => array(
				'sections' => false,
				'tested' => false,
			),
		));

		if ( is_wp_error( $theme_info ) ) {
			return $theme_info;
		}

		// Set up the installer.
		$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result = $upgrader->install( $theme_info->download_link ); // @phpstan-ignore-line

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Activate the theme
		if ( wp_get_theme( $theme_slug )->exists() ) {
			switch_theme( $theme_slug );
			return true;
		}

		return new WP_Error( 400, 'Theme installed but could not be activated' );
	}

	/**
	 * Install/Activate Free Plugins.
	 *
	 * @param string $plugin_slug Slug that match on wp.org repo.
	 *
	 * @return true|WP_Error
	 */
	public static function install_and_active_plugin( string $plugin_slug ) {
		// Include required WordPress files for plugin management.
		self::require_dependencies();
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		// Check if the plugin is already installed.
		$installed_plugins = get_plugins();
		foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
			if ( strpos( $plugin_file, $plugin_slug . '/' ) === 0 ) {
				$activation = activate_plugin( $plugin_file, '', false, true );
				if ( is_wp_error( $activation ) ) {
					return $activation;
				}
				return true;
			}
		}

		// Get plugin information from WordPress.org
		$plugin_info = plugins_api('plugin_information', array(
			'slug' => $plugin_slug,
			'fields' => array(
				'sections' => false,
				'tested' => false,
			),
		));

		if ( is_wp_error( $plugin_info ) ) {
			return $plugin_info;
		}

		// Set up the installer
		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result = $upgrader->install( $plugin_info->download_link ); // @phpstan-ignore-line

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Activate the plugin.
		$plugin_name = self::get_plugin_name( $plugin_slug );
		if ( is_wp_error( $plugin_name ) ) {
			return $plugin_name;
		}

		if ( ! is_plugin_active( $plugin_name ) ) {
			$activation = activate_plugin( $plugin_name, '', false, true );

			if ( is_wp_error( $activation ) ) {
				return $activation;
			}
		}

		return true;
	}

	/**
	 * Get plugin name from directory name.
	 *
	 * @param string $directory Directory/slug.
	 *
	 * @return WP_Error|string
	 */
	public static function get_plugin_name( string $directory ) {
		$plugin_data = get_plugins( '/' . $directory );
		$plugin_root_file = count( $plugin_data ) > 0 ? array_key_first( $plugin_data ) : false;
		if ( ! $plugin_root_file ) {
			return new WP_Error( 400, 'Invalid plugin folder!' );
		}

		return $directory . '/' . $plugin_root_file;
	}

	/**
	 * Commonly used dependencies here.
	 *
	 * @return void
	 */
	private static function require_dependencies() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}
}
