<?php
/**
 * StoreEngine full backup — AJAX/SSE handler.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Backup;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\EventStreamServer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BackupAjaxHandler extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_backup';

	public function __construct() {
		$this->actions = [
			'export_stream' => [
				'callback'   => [ $this, 'export_stream' ],
				'capability' => 'manage_options',
				'fields'     => [
					'licensing'   => 'string',
					'deployments' => 'string',
					'logs'        => 'string',
					'users'       => 'string',
					'files'       => 'string',
				],
			],
			'download'      => [
				'callback'   => [ $this, 'download' ],
				'capability' => 'manage_options',
				'fields'     => [ 'file' => 'string', 'token' => 'string' ],
			],
			'list'          => [
				'callback'   => [ $this, 'list_backups' ],
				'capability' => 'manage_options',
			],
			'delete'        => [
				'callback'   => [ $this, 'delete_backup' ],
				'capability' => 'manage_options',
				'fields'     => [ 'file' => 'string' ],
			],
			'upload'        => [
				'callback'   => [ $this, 'upload' ],
				'capability' => 'manage_options',
			],
			'start_import'  => [
				'callback'   => [ $this, 'start_import' ],
				'capability' => 'manage_options',
				'fields'     => [ 'upload_id' => 'string' ],
			],
			'import_stream' => [
				'callback'   => [ $this, 'import_stream' ],
				'capability' => 'manage_options',
				'fields'     => [
					'upload_id' => 'string',
					'safety'    => 'string',
				],
			],
			'settings'      => [
				'callback'   => [ $this, 'get_schedule_settings' ],
				'capability' => 'manage_options',
			],
			'save_settings' => [
				'callback'   => [ $this, 'save_schedule_settings' ],
				'capability' => 'manage_options',
				'fields'     => [ 'settings' => 'string' ],
			],
		];
	}

	/**
	 * Return the current auto-backup schedule + retention settings.
	 */
	public function get_schedule_settings() {
		wp_send_json_success( [ 'settings' => BackupManager::get_settings() ] );
	}

	/**
	 * Persist the auto-backup schedule + retention settings (JSON payload).
	 */
	public function save_schedule_settings( array $payload ) {
		$raw  = is_string( $payload['settings'] ?? null ) ? wp_unslash( $payload['settings'] ) : '';
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid settings payload.', 'storeengine' ) ] );
		}

		wp_send_json_success( [ 'settings' => BackupManager::save_settings( $data ) ] );
	}

	protected function truthy( $v ): bool {
		return in_array( (string) $v, [ '1', 'true', 'yes', 'on' ], true );
	}

	/* ----------------------------------------------------------------- Export */

	public function export_stream( array $payload ) {
		$opts = [
			'licensing'   => isset( $payload['licensing'] ) ? $this->truthy( $payload['licensing'] ) : true,
			'deployments' => $this->truthy( $payload['deployments'] ?? '' ),
			'logs'        => $this->truthy( $payload['logs'] ?? '' ),
			'users'       => $this->truthy( $payload['users'] ?? '' ),
			'files'       => $this->truthy( $payload['files'] ?? '' ),
		];

		$sse = new EventStreamServer();
		$sse->listen( function () use ( $sse, $opts ) {
			try {
				$exporter = new Exporter(
					$opts,
					function ( $percent, $message ) use ( $sse ) {
						$sse->emitEvent( [ 'event' => 'message', 'type' => 'progress', 'percent' => $percent, 'message' => $message ] );
					}
				);
				$path = $exporter->run();

				// Archives are KEPT (manual backups) — listed in the UI and removed
				// only when the user deletes them, EXCEPT when the retention policy
				// is on, in which case old backups beyond the keep-count are pruned.
				$sched = BackupManager::get_settings();
				if ( ! empty( $sched['retention_enabled'] ) ) {
					BackupManager::apply_retention( (int) $sched['retention_keep'] );
				}

				$sse->emitEvent( [
					'event'    => 'message',
					'type'     => 'complete',
					'percent'  => 100,
					'message'  => __( 'Backup ready.', 'storeengine' ),
					'filename' => basename( $path ),
					'size'     => size_format( (int) filesize( $path ) ),
				], true );
			} catch ( \Throwable $e ) {
				\StoreEngine\Utils\Helper::log_error( $e );
				$sse->emitEvent( [ 'event' => 'message', 'type' => 'error', 'message' => $e->getMessage() ], true );
			}
		} );
	}

	public function download( array $payload ) {
		// Prefer a stable filename (validated to live inside the private backups
		// dir); fall back to a legacy one-time token if present.
		$real = ! empty( $payload['file'] )
			? BackupManager::resolve_backup_path( (string) $payload['file'] )
			: null;

		if ( ! $real && ! empty( $payload['token'] ) ) {
			$path = BackupManager::resolve_download_token( (string) $payload['token'] );
			$real = $path ? realpath( $path ) : false;
			$dir  = realpath( BackupManager::backups_dir() );
			if ( ! $real || ! $dir || ! str_starts_with( $real, $dir ) || ! is_file( $real ) ) {
				$real = null;
			}
		}

		if ( ! $real ) {
			wp_send_json_error( __( 'Backup file not found.', 'storeengine' ), 404 );
		}

		if ( ob_get_length() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $real ) . '"' );
		header( 'Content-Length: ' . filesize( $real ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$h = fopen( $real, 'rb' );
		if ( $h ) {
			while ( ! feof( $h ) ) {
				echo fread( $h, 1048576 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
				flush();
			}
			fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		exit;
	}

	public function list_backups() {
		return [ 'backups' => BackupManager::list_backups() ];
	}

	public function delete_backup( array $payload ) {
		$real = BackupManager::resolve_backup_path( (string) ( $payload['file'] ?? '' ) );
		if ( ! $real ) {
			wp_send_json_error( __( 'Backup not found.', 'storeengine' ), 404 );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $real );

		return [ 'deleted' => true, 'backups' => BackupManager::list_backups() ];
	}

	/* ----------------------------------------------------------------- Import */

	public function upload() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by AbstractRequestHandler before callback.
		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'storeengine' ), 400 );
		}

		$tmp  = sanitize_text_field( $_FILES['file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce verified by AbstractRequestHandler before callback.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by AbstractRequestHandler before callback.
		$name = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : 'backup.zip';

		if ( ! is_uploaded_file( $tmp ) ) {
			wp_send_json_error( __( 'Invalid upload.', 'storeengine' ), 400 );
		}
		if ( 'zip' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			wp_send_json_error( __( 'Backup must be a .zip archive.', 'storeengine' ), 400 );
		}

		$dir       = trailingslashit( BackupManager::ensure_backups_dir() ) . 'uploads';
		wp_mkdir_p( $dir );
		$upload_id = 'up-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false );
		$dest      = trailingslashit( $dir ) . $upload_id . '.zip';

		// Admin-only, nonce-checked backup restore. move_uploaded_file() is the
		// correct secure primitive for a raw .zip archive (wp_handle_upload() is
		// media-oriented); the is_uploaded_file() guard blocks path forgery.
		// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- see note above; guarded by is_uploaded_file().
		if ( ! is_uploaded_file( $tmp ) || ! move_uploaded_file( $tmp, $dest ) ) {
			wp_send_json_error( __( 'Could not store the uploaded file.', 'storeengine' ), 500 );
		}

		return [ 'upload_id' => $upload_id ];
	}

	protected function upload_path( string $upload_id ): string {
		$upload_id = preg_replace( '/[^a-zA-Z0-9\-]/', '', $upload_id );

		return trailingslashit( BackupManager::backups_dir() ) . 'uploads/' . $upload_id . '.zip';
	}

	public function start_import( array $payload ) {
		$path = $this->upload_path( (string) ( $payload['upload_id'] ?? '' ) );
		if ( ! is_file( $path ) ) {
			wp_send_json_error( __( 'Uploaded backup not found.', 'storeengine' ), 404 );
		}

		$preview  = ( new Importer() )->inspect( $path );
		$manifest = $preview['manifest'];

		return [
			'site_url'        => $manifest['site_url'] ?? '',
			'generated_at'    => $manifest['generated_at'] ?? '',
			'plugin_version'  => $manifest['plugin_version'] ?? '',
			'includes_users'  => ! empty( $manifest['includes_users'] ),
			'includes_files'  => ! empty( $manifest['includes_files'] ),
			'prefix_mismatch' => $preview['prefix_mismatch'],
			'missing_tables'  => $preview['missing_tables'],
			'tables'          => array_map(
				static function ( $t ) {
					return [ 'name' => $t['name'], 'row_count' => $t['row_count'] ];
				},
				(array) ( $manifest['tables'] ?? [] )
			),
		];
	}

	public function import_stream( array $payload ) {
		$path   = $this->upload_path( (string) ( $payload['upload_id'] ?? '' ) );
		$safety = isset( $payload['safety'] ) ? $this->truthy( $payload['safety'] ) : true;

		$sse = new EventStreamServer();
		$sse->listen( function () use ( $sse, $path, $safety ) {
			try {
				if ( ! is_file( $path ) ) {
					throw new \StoreEngine\Classes\Exceptions\StoreEngineException( 'Uploaded backup not found.', 'backup-missing-upload' );
				}
				$importer = new Importer( function ( $percent, $message ) use ( $sse ) {
					$sse->emitEvent( [ 'event' => 'message', 'type' => 'progress', 'percent' => $percent, 'message' => $message ] );
				} );
				$result = $importer->run( $path, [ 'safety' => $safety ] );

				// Drop the uploaded archive once consumed.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $path );

				$sse->emitEvent( [
					'event'   => 'message',
					'type'    => 'complete',
					'percent' => 100,
					'message' => __( 'Restore complete.', 'storeengine' ),
					'result'  => $result,
				], true );
			} catch ( \Throwable $e ) {
				\StoreEngine\Utils\Helper::log_error( $e );
				$sse->emitEvent( [ 'event' => 'message', 'type' => 'error', 'message' => $e->getMessage() ], true );
			}
		} );
	}
}

// End of file backup-ajax-handler.php.
