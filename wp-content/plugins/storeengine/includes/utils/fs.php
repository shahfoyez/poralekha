<?php

namespace StoreEngine\Utils;

use WP_Error;
use WP_Filesystem_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fs {
	protected static $fs_loaded = null;

	public static function load_fs() {
		if ( null !== self::$fs_loaded ) {
			return self::$fs_loaded;
		}

		// Allow us to easily interact with the filesystem.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		ob_start();
		$credentials = request_filesystem_credentials( self_admin_url() );
		ob_end_clean();

		if ( false === $credentials || ! WP_Filesystem( $credentials ) ) {
			global $wp_filesystem;
			$errorCode    = 'fs_unavailable';
			$errorMessage = esc_html__( 'Unable to connect to the filesystem. Please confirm your credentials.', 'storeengine' );

			if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) { // @phpstan-ignore-line
				$errorMessage = esc_html( $wp_filesystem->errors->get_error_message() );
			}

			self::$fs_loaded = new WP_Error( $errorCode, $errorMessage, [ 'status' => 500 ] );
		} else {
			self::$fs_loaded = true;
		}

		return self::$fs_loaded;
	}

	/**
	 * Initializes and connects the WordPress Filesystem.
	 *
	 * @return WP_Error|WP_Filesystem_Base
	 */
	public static function load() {
		$loaded = self::load_fs();

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			return new WP_Error( 'fs_unavailable', esc_html__( 'Could not access filesystem.', 'storeengine' ), [ 'status' => 500 ] );
		}

		if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
			return new WP_Error( 'fs_error', esc_html__( 'Filesystem error.', 'storeengine' ), $wp_filesystem->errors );
		}

		return $wp_filesystem;
	}

	/**
	 * Downloads a URL to a local temporary file using the WordPress HTTP API.
	 * Please note that the calling function must delete or move the file.
	 *
	 *
	 * @param string $url The URL of the file to download.
	 * @param int $timeout The timeout for the request to download the file.
	 *                     Default 300 seconds.
	 *
	 * @return string|WP_Error Temp filename (path) on success, WP_Error on failure.
	 */
	public static function download_url( string $url, int $timeout = 300 ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return download_url( esc_url_raw( $url ), $timeout );
	}

	/**
	 * Reads entire file into a string.
	 *
	 * @param string $file Name of the file to read.
	 *
	 * @return string|false Read data on success, false on failure.
	 * @since 1.6.4
	 */
	public static function get_contents( string $file ) {
		$fs = self::load();

		// load() is documented to return WP_Error on failure (line 49 above).
		// Honour the get_contents() contract — return false on FS unavailable
		// — instead of dispatching a method on a WP_Error and fataling.
		if ( $fs instanceof WP_Error ) {
			return false;
		}

		if ( ! $fs->exists( $file ) || ! $fs->is_readable( $file ) ) {
			return false;
		}

		return $fs->get_contents( $file );
	}

	/**
	 * Render a PHP template file and return its output.
	 *
	 * Used for the block-markup page-content templates: they carry a
	 * `defined( 'ABSPATH' ) || exit;` direct-access guard (required by Plugin
	 * Check), so they must be executed via include — not read raw — otherwise
	 * the guard line would leak into the generated page content.
	 *
	 * @param string $file Absolute path to the template file.
	 * @return string Rendered markup, or '' if the file is missing.
	 */
	public static function render_template( string $file ): string {
		if ( ! is_readable( $file ) ) {
			return '';
		}
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}
}

// End of file filesystem.php.
