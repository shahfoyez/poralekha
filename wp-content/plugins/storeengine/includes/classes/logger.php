<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Exceptions\StoreEngineRuntimeException;
use StoreEngine\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	const SUCCESS = 'success';
	const WARNING = 'warning';
	const ERROR = 'error';
	const INFO = 'info';
	const DEBUG = 'debug';
	const EMERGENCY = 'emergency';
	const CRITICAL = 'critical';
	const ALERT = 'alert';

	public static function log( string $title, $content, string $status = 'success', string $module = 'system' ) {
		try {
			self::log_to_db( $title, $content, $status, $module );
		} catch ( \Throwable $t ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Unable to write StoreEngine log data to database. Error: ' . $t->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Last-resort diagnostic when DB logging fails; gated behind WP_DEBUG.
			}

			// Fallback.
			try {
				self::log_to_file( $title, $content, $status, $module );
			} catch ( \Throwable $t ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Unable to write StoreEngine log data to log file. Error: ' . $t->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Last-resort diagnostic when both DB and file logging fail; gated behind WP_DEBUG.
				}
			}
		}
	}

	public static function log_to_db( string $title, $content, string $status = 'success', string $module = 'system' ) {
		$log = new LogItem();
		$log->set_props( [
			'date'    => current_time( 'mysql' ),
			'module'  => $module,
			'title'   => $title,
			'status'  => $status,
			'content' => is_array( $content ) || is_object( $content ) ? json_encode( $content ) : $content,
		] );

		$log->save();
	}

	public static function log_to_file( string $title, $content, string $status = 'success', string $module = 'system' ) {
		if ( ! defined( 'STOREENGINE_LOGS_DIR' ) ) {
			throw new StoreEngineRuntimeException( 'Logs directory is not defined.' );
		}

		if ( ! file_exists( STOREENGINE_LOGS_DIR ) ) {
			wp_mkdir_p( STOREENGINE_LOGS_DIR );
		}

		$message = is_string( $content ) ? $title . $content : $title;

		if ( ! is_scalar( $content ) ) {
			$message .= PHP_EOL . json_encode( $content );
		}

		$log_file  = STOREENGINE_LOGS_DIR . 'storeengine-' . $module . '-' . gmdate( 'Y-m-d' ) . '.log';
		$timestamp = gmdate( 'c' );
		$module    = strtoupper( $module );
		$status    = strtoupper( $status );
		$message   = "[$timestamp] – [$module] [$status] $message";

		// Append via WP_Filesystem — Plugin Check disallows direct fopen/fwrite.
		// Log files rotate daily by name, so this read-modify-write stays cheap.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) && ! WP_Filesystem() ) {
			return;
		}

		$existing = $wp_filesystem->exists( $log_file ) ? (string) $wp_filesystem->get_contents( $log_file ) : '';
		$chmod    = Constants::is_defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : false;
		$wp_filesystem->put_contents( $log_file, $existing . $message . PHP_EOL, $chmod );
	}
}
