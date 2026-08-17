<?php

namespace StoreEngine\Utils\traits;

use WP_Error;

trait ThemePlugin {
	/**
	 * Installed plugin list.
	 */
	protected static ?array $installed_plugins = null;

	/**
	 * @param $basename
	 *
	 * @return false|array{Name:string,PluginURI:string,Version:string,Description:string,Author:string,AuthorURI:string,TextDomain:string,DomainPath:string,Network:string,RequiresWP:string,RequiresPHP:string,UpdateURI:string,RequiresPlugins:string}
	 */
	public static function is_plugin_installed( $basename ) {
		if ( null === static::$installed_plugins ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				include_once ABSPATH . '/wp-admin/includes/plugin.php';
			}

			static::$installed_plugins = get_plugins();
		}

		return static::$installed_plugins[ $basename ] ?? false;
	}

	public static function is_active_storeengine_pro(): bool {
		return self::is_plugin_active( 'storeengine-pro/storeengine-pro.php' );
	}

	public static function is_plugin_active( $basename ): bool {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $basename );
	}

	/**
	 * Attempts activation of plugin in a "sandbox" and redirects on success.
	 *
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 * @param string $redirect Optional. URL to redirect to.
	 * @param bool $network_wide Optional. Whether to enable the plugin for all sites in the network
	 *                             or just the current site. Multisite only. Default false.
	 * @param bool $silent Optional. Whether to prevent calling activation hooks. Default false.
	 *
	 * @return null|WP_Error Null on success, WP_Error on invalid file.
	 * @since 1.6.4
	 *
	 */
	public static function activate_plugin( string $plugin, string $redirect = '', bool $network_wide = false, bool $silent = false ): ?WP_Error {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		return activate_plugin( $plugin, $redirect, $network_wide, $silent );
	}

	public static function get_plugin_install_url( string $slug ): string {
		return wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=' . $slug ), 'install-plugin_' . $slug );
	}

	public static function get_plugin_activation_url( string $basename ): string {
		return wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $basename ), 'activate-plugin_' . $basename );
	}

	public static function get_plugin_deactivation_url( string $basename ): string {
		return wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=' . $basename ), 'deactivate-plugin_' . $basename );
	}
}

// End of file theme-plugin.php.
