<?php
/**
 * GeoLocation MaxMind integration Helper.
 *
 * @version 1.0.0
 * @since 1.5.6
 *
 * @package StoreEngine/Utils/traits
 */

namespace StoreEngine\Utils\traits;

use Exception;
use PharData;
use StoreEngine\MaxMind\Db\Reader;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Fs;
use StoreEngine\Utils\Helper;
use WP_Error;

trait Maxmind {
	/**
	 * The name of the MaxMind database to utilize.
	 */
	public static string $maxmind_db = 'GeoLite2-Country';

	/**
	 * The extension for the MaxMind database.
	 */
	public static string $maxmind_db_ext = '.mmdb';

	public static function maxmind_locate_ip( array $data, string $ip_address = null ): array {
		// Check if country already found from header.
		if ( ! empty( $data['country'] ) ) {
			return $data;
		}

		if ( empty( $ip_address ) ) {
			return $data;
		}

		$country_code = self::country_code_for_ip( $ip_address );

		return [
			'country'  => $country_code,
			'state'    => '',
			'city'     => '',
			'postcode' => '',
		];
	}

	/**
	 * Fetches the ISO country code associated with an IP address.
	 *
	 * @param string $ip_address The IP address to find the country code for.
	 * @return string The country code for the IP address, or empty if not found.
	 */
	public static function country_code_for_ip( string $ip_address ): string {
		$country_code = '';


		$database_path = self::get_maxmind_db_path();

		if ( ! file_exists( $database_path ) ) {
			return $country_code;
		}

		try {
			$reader = new Reader( $database_path );
			$data   = $reader->get( $ip_address );
			if ( isset( $data['country']['iso_code'] ) ) {
				$country_code = $data['country']['iso_code'];
			}

			$reader->close();
		} catch ( Exception $e ) {
			Helper::log_error( $e );
		}

		return $country_code;
	}

	public static function validate_maxmind_license_key( string $license_key ) {
		$cache_key = 'maxmind_license_key_' . md5( $license_key ) . 'validation_status';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			'https://secret-scanning.maxmind.com/secrets/validate-license-key',
			[
				'body'    => [ 'license_key' => $license_key ],
				'timeout' => 15,
			]
		);

		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );

			$response = 204 === $code ? true : new WP_Error( 'invalid_maxmind_license_key', __( 'The MaxMind license key is invalid. Newly created keys may take a few minutes to activate.', 'storeengine' ) );
		}

		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

		return $response;
	}

	/**
	 * Fetches the database from the MaxMind service.
	 *
	 * @return string|WP_Error The path to the database file or an error if invalid.
	 */
	public static function download_maxmind_db( string $license_key ) {
		$download_uri = add_query_arg(
			[
				'edition_id'  => self::$maxmind_db,
				'license_key' => urlencode( Formatting::clean( $license_key ) ),
				'suffix'      => 'tar.gz',
			],
			'https://download.maxmind.com/app/geoip_download'
		);

		$tmp_file = Fs::download_url( $download_uri );

		if ( is_wp_error( $tmp_file ) ) {
			$error_data = [
				'data'  => $tmp_file->get_error_data(),
				'error' => $tmp_file->get_error_message(),
			];

			if ( isset( $error_data['code'] ) && 401 === (int) $error_data['code'] ) {
				return new WP_Error(
					'invalid_maxmind_license_key',
					__( 'The MaxMind license key is invalid. Newly created keys may take a few minutes to activate.', 'storeengine' ),
					$error_data
				);
			}

			return new WP_Error( 'maxmind_db_download_failed', __( 'Failed to download the MaxMind GeoLite2-Country database.', 'storeengine' ), $error_data );
		}

		// Extract the database from the archive.
		try {
			$file = new PharData( $tmp_file );

			$tmp_database_path = trailingslashit( dirname( $tmp_file ) ) . trailingslashit( $file->current()->getFilename() ) . self::$maxmind_db . self::$maxmind_db_ext;

			$file->extractTo( dirname( $tmp_file ), trailingslashit( $file->current()->getFilename() ) . self::$maxmind_db . self::$maxmind_db_ext, true );
		} catch ( Exception $exception ) {
			return new WP_Error( 'maxmind_db_archive_extract_failed', $exception->getMessage() );
		} finally {
			// Remove the archive since we only care about a single file in it.
			wp_delete_file( $tmp_file );
		}

		return $tmp_database_path;
	}

	/**
	 * Updates the database used for geolocation queries.
	 *
	 * @param ?string $new_db The path to the new database file. Null will fetch a new archive.
	 */
	public static function update_maxmind_db( string $new_db = null ) {
		// Allow us to easily interact with the filesystem.
		$fs = Fs::load();

		if ( is_wp_error( $fs ) ) {
			return;
		}

		// Remove any existing archives to comply with the MaxMind TOS.
		$target_database_path = self::get_maxmind_db_path();

		// If there's no database path, we can't store the database.
		if ( empty( $target_database_path ) ) {
			return;
		}

		if ( $fs->exists( $target_database_path ) ) {
			$fs->delete( $target_database_path );
		}

		if ( isset( $new_db ) ) {
			$tmp_file = $new_db;
		} else {
			// We can't download a database if there's no license key configured.
			$license_key = Helper::get_settings( 'maxmind_license' );

			if ( empty( $license_key ) ) {
				return;
			}

			$tmp_file = self::download_maxmind_db( $license_key );

			if ( is_wp_error( $tmp_file ) ) {
				return;
			}
		}

		// Move the new database into position.
		$fs->move( $tmp_file, $target_database_path, true );
		$fs->delete( dirname( $tmp_file ) );
	}

	public static function get_maxmind_db_path(): string {
		return apply_filters(
			'storeengine/geolocation/maxmind/db-path',
			STOREENGINE_SECURE_UPLOADS_DIR . '/maxmind/' . self::$maxmind_db . self::$maxmind_db_ext
		);
	}
}

// End of file maxmind.php.
