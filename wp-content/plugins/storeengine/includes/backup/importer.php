<?php
/**
 * StoreEngine full backup — importer / restore (Replace mode).
 *
 * Faithful, ID-preserving restore: custom tables are TRUNCATEd then re-inserted
 * verbatim; options/posts/postmeta replaced; terms/users upserted by PK (never
 * truncated, to protect data shared with other content). A pre-restore safety
 * backup is taken first.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Backup;

use StoreEngine\Database;
use StoreEngine\Classes\Exceptions\StoreEngineException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Importer {

	/** @var callable|null function(float $percent, string $message): void */
	protected $progress;

	protected int $batch = 500;

	public function __construct( ?callable $progress = null ) {
		$this->progress = $progress;
		$this->batch    = (int) apply_filters( 'storeengine/backup/import_batch_size', 500 );
	}

	protected function report( float $percent, string $message ): void {
		if ( $this->progress ) {
			call_user_func( $this->progress, min( 99.0, round( $percent, 1 ) ), $message );
		}
	}

	/**
	 * Read the manifest + a compatibility preview WITHOUT writing anything.
	 *
	 * @throws StoreEngineException
	 */
	public function inspect( string $zip_path ): array {
		$raw = ArchiveWriter::read_entry( $zip_path, 'manifest.json' );
		if ( null === $raw ) {
			throw new StoreEngineException( 'Not a valid StoreEngine backup (manifest.json missing).', 'backup-bad-manifest' );
		}
		$manifest = json_decode( $raw, true );
		if ( ! is_array( $manifest ) || empty( $manifest['format_version'] ) ) {
			throw new StoreEngineException( 'Corrupt or unsupported backup manifest.', 'backup-bad-manifest' );
		}
		if ( (int) $manifest['format_version'] > BackupManager::FORMAT_VERSION ) {
			throw new StoreEngineException( 'This backup was made by a newer version of StoreEngine. Update first.', 'backup-version' );
		}

		global $wpdb;
		$missing = [];
		foreach ( (array) ( $manifest['tables'] ?? [] ) as $t ) {
			$local = $wpdb->prefix . 'storeengine_' . $t['name'];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $local ) );
			if ( ! $exists ) {
				$missing[] = $t['name'];
			}
		}

		return [
			'manifest'        => $manifest,
			'missing_tables'  => $missing, // addons not installed locally → will be skipped.
			'prefix_mismatch' => ( $manifest['wp_prefix'] ?? $wpdb->prefix ) !== $wpdb->prefix,
		];
	}

	/**
	 * Restore. $opts: safety(bool,true), users(bool,true if present), files(bool,true if present).
	 *
	 * @throws StoreEngineException
	 */
	public function run( string $zip_path, array $opts = [] ): array {
		global $wpdb;

		$take_safety = $opts['safety'] ?? true;

		// 1) Pre-restore safety backup (DB-only) so a botched restore is recoverable.
		if ( $take_safety ) {
			$this->report( 1, __( 'Creating pre-restore safety backup…', 'storeengine' ) );
			$safety = ( new Exporter( [ 'licensing' => true, 'logs' => true, 'users' => true, 'files' => false ] ) )->run();
			// Re-label so it is recognisable as a pre-restore safety copy in the list.
			$renamed = dirname( $safety ) . '/storeengine-prerestore-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.zip';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			if ( @rename( $safety, $renamed ) ) {
				$safety = $renamed;
			}
			$safety_name = basename( $safety );
		} else {
			$safety_name = null;
		}

		// 2) Unzip.
		$this->report( 4, __( 'Reading archive…', 'storeengine' ) );
		$work = trailingslashit( BackupManager::ensure_backups_dir() ) . 'restore-' . wp_generate_password( 8, false );
		ArchiveWriter::unzip( $zip_path, $work );

		try {
			$manifest = json_decode( (string) file_get_contents( $work . '/manifest.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			if ( ! is_array( $manifest ) ) {
				throw new StoreEngineException( 'Corrupt backup manifest.', 'backup-bad-manifest' );
			}

			// 3) Ensure schema exists (core + addons) before inserting.
			$this->report( 6, __( 'Ensuring database schema…', 'storeengine' ) );
			Database::create_initial_custom_table();
			do_action( 'storeengine/schema_synced' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );

			$result = [ 'restored' => [], 'skipped' => [], 'safety_backup' => $safety_name ];

			$tables = (array) ( $manifest['tables'] ?? [] );
			$total  = max( 1, count( $tables ) );
			$i      = 0;

			// 4) Custom tables — TRUNCATE + faithful re-insert.
			foreach ( $tables as $t ) {
				$i++;
				$base  = $t['name'];
				$local = $wpdb->prefix . 'storeengine_' . $base;
				$file  = $work . '/' . $t['file'];

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $local ) );
				if ( ! $exists || ! file_exists( $file ) ) {
					$result['skipped'][] = $base;
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "TRUNCATE TABLE `{$local}`" );
				$this->restore_jsonl_into_table( $file, $local );
				$result['restored'][] = $base;

				$this->report( 6 + ( $i / $total ) * 78, sprintf( /* translators: %s table */ __( 'Restoring %s…', 'storeengine' ), $base ) );
			}

			// 5) Options.
			$this->report( 86, __( 'Restoring settings…', 'storeengine' ) );
			$this->restore_options( $work . '/options.json' );

			// 6) Posts + postmeta (replace) and terms (upsert).
			$this->report( 90, __( 'Restoring catalog…', 'storeengine' ) );
			$this->restore_posts( $manifest, $work );
			$this->restore_terms( $work );

			// 7) Users (upsert) — only if present in the archive.
			if ( ! empty( $manifest['includes_users'] ) && file_exists( $work . '/users.jsonl' ) ) {
				$this->report( 94, __( 'Restoring users…', 'storeengine' ) );
				$this->restore_users( $work );
			}

			// 8) Files.
			if ( ! empty( $manifest['includes_files'] ) && is_dir( $work . '/files' ) && defined( 'STOREENGINE_SECURE_UPLOADS_DIR' ) ) {
				$this->report( 96, __( 'Restoring uploaded files…', 'storeengine' ) );
				$this->copy_tree( $work . '/files', STOREENGINE_SECURE_UPLOADS_DIR );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );

			// 9) Resync + flush.
			Database::maybe_sync_schema();
			update_option( 'storeengine_required_rewrite_flush', 'yes' );
			wp_cache_flush();

			return $result;
		} finally {
			ArchiveWriter::rrmdir( $work );
		}
	}

	/* -------------------------------------------------------------------- */

	/**
	 * Stream a jsonl file into a table in batches, preserving NULLs and PKs.
	 * Only inserts columns that exist in the (current) target table.
	 */
	protected function restore_jsonl_into_table( string $file, string $table ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DESCRIBE on a prepared %i identifier; schema introspection, not cacheable.
		$valid_cols = array_flip( (array) $wpdb->get_col( $wpdb->prepare( 'DESCRIBE %i', $table ), 0 ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$h = fopen( $file, 'rb' );
		if ( ! $h ) {
			return;
		}
		$buffer = [];
		while ( ( $line = fgets( $h ) ) !== false ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$row = json_decode( $line, true );
			if ( ! is_array( $row ) ) {
				continue;
			}
			// Keep only columns present in the current table (tolerate drift).
			$buffer[] = array_intersect_key( $row, $valid_cols );
			if ( count( $buffer ) >= $this->batch ) {
				$this->insert_rows( $table, $buffer );
				$buffer = [];
			}
		}
		if ( $buffer ) {
			$this->insert_rows( $table, $buffer );
		}
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Multi-row INSERT preserving NULLs. Columns derived from the first row.
	 */
	protected function insert_rows( string $table, array $rows ): void {
		global $wpdb;
		if ( empty( $rows ) ) {
			return;
		}
		$columns  = array_keys( $rows[0] );
		$cols_sql = '`' . implode( '`,`', array_map( 'esc_sql', $columns ) ) . '`';

		$tuples = [];
		$args   = [ $table ];
		foreach ( $rows as $row ) {
			$cells = [];
			foreach ( $columns as $c ) {
				$v = $row[ $c ] ?? null;
				if ( null === $v ) {
					$cells[] = 'NULL';
				} else {
					$cells[] = '%s';
					$args[]  = is_scalar( $v ) ? $v : wp_json_encode( $v );
				}
			}
			$tuples[] = '(' . implode( ',', $cells ) . ')';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table via %i; column list is esc_sql()'d identifiers; values via %s placeholders.
		$sql = "INSERT INTO %i ({$cols_sql}) VALUES " . implode( ',', $tuples );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared bulk insert into a restore target table; per-batch import write, not cacheable.
		$wpdb->query( $wpdb->prepare( $sql, $args ) );
	}

	protected function restore_options( string $file ): void {
		global $wpdb;
		if ( ! file_exists( $file ) ) {
			return;
		}
		$options = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		if ( ! is_array( $options ) ) {
			return;
		}

		// Replace: clear existing storeengine options first.
		foreach ( BackupManager::option_like_patterns() as $pattern ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
		}

		foreach ( $options as $name => $value ) {
			// Raw (already-serialized) value — insert directly, never via
			// update_option (which would double-serialize).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", $name, $value, 'yes' ) );
		}
	}

	protected function restore_posts( array $manifest, string $work ): void {
		global $wpdb;

		$post_types = (array) ( $manifest['post_types'] ?? [] );
		if ( ! empty( $post_types ) ) {
			$in = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			// Delete existing rows of these types (+ their meta) for a faithful replace.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- %s placeholders built into $in and passed to prepare(); one placeholder per post type.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($in)", $post_types ) );
			if ( $ids ) {
				$id_in = implode( ',', array_map( 'intval', $ids ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($id_in)" );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($id_in)" );
			}
		}

		$this->restore_jsonl_into_table( $work . '/posts.jsonl', $wpdb->posts );
		$this->restore_jsonl_into_table( $work . '/postmeta.jsonl', $wpdb->postmeta );
	}

	protected function restore_terms( string $work ): void {
		global $wpdb;
		// Upsert (replace by PK) so terms shared with non-StoreEngine content survive.
		$this->upsert_jsonl( $work . '/terms.jsonl', $wpdb->terms );
		$this->upsert_jsonl( $work . '/term_taxonomy.jsonl', $wpdb->term_taxonomy );
		$this->upsert_jsonl( $work . '/term_relationships.jsonl', $wpdb->term_relationships );
	}

	protected function restore_users( string $work ): void {
		global $wpdb;
		$this->upsert_jsonl( $work . '/users.jsonl', $wpdb->users );
		$this->upsert_jsonl( $work . '/usermeta.jsonl', $wpdb->usermeta );
	}

	/**
	 * REPLACE INTO from a jsonl file (upsert by PK) — used for shared WP tables
	 * we must not truncate (terms, users).
	 */
	protected function upsert_jsonl( string $file, string $table ): void {
		global $wpdb;
		if ( ! file_exists( $file ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DESCRIBE on a prepared %i identifier; schema introspection, not cacheable.
		$valid = array_flip( (array) $wpdb->get_col( $wpdb->prepare( 'DESCRIBE %i', $table ), 0 ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$h = fopen( $file, 'rb' );
		if ( ! $h ) {
			return;
		}
		$buffer = [];
		while ( ( $line = fgets( $h ) ) !== false ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$row = json_decode( $line, true );
			if ( is_array( $row ) ) {
				$buffer[] = array_intersect_key( $row, $valid );
			}
			if ( count( $buffer ) >= $this->batch ) {
				$this->replace_rows( $table, $buffer );
				$buffer = [];
			}
		}
		if ( $buffer ) {
			$this->replace_rows( $table, $buffer );
		}
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	protected function replace_rows( string $table, array $rows ): void {
		global $wpdb;
		if ( empty( $rows ) ) {
			return;
		}
		$columns  = array_keys( $rows[0] );
		$cols_sql = '`' . implode( '`,`', array_map( 'esc_sql', $columns ) ) . '`';
		$tuples   = [];
		$args     = [ $table ];
		foreach ( $rows as $row ) {
			$cells = [];
			foreach ( $columns as $c ) {
				$v = $row[ $c ] ?? null;
				if ( null === $v ) {
					$cells[] = 'NULL';
				} else {
					$cells[] = '%s';
					$args[]  = is_scalar( $v ) ? $v : wp_json_encode( $v );
				}
			}
			$tuples[] = '(' . implode( ',', $cells ) . ')';
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table via %i; column list is esc_sql()'d identifiers; values via %s placeholders.
		$sql = "REPLACE INTO %i ({$cols_sql}) VALUES " . implode( ',', $tuples );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared bulk upsert into a shared WP table; per-batch import write, not cacheable.
		$wpdb->query( $wpdb->prepare( $sql, $args ) );
	}

	protected function copy_tree( string $src, string $dest ): void {
		$src = rtrim( $src, '/\\' );
		if ( ! is_dir( $dest ) ) {
			wp_mkdir_p( $dest );
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $items as $item ) {
			$target = trailingslashit( $dest ) . ltrim( str_replace( '\\', '/', substr( $item->getPathname(), strlen( $src ) ) ), '/' );
			if ( $item->isDir() ) {
				if ( ! is_dir( $target ) ) {
					wp_mkdir_p( $target );
				}
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
				@copy( $item->getPathname(), $target );
			}
		}
	}
}

// End of file importer.php.
