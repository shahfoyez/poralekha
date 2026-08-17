<?php
/**
 * StoreEngine full backup — exporter.
 *
 * Streams all StoreEngine data (custom tables + options + CPT posts/meta/terms,
 * optionally WP users and uploaded files) into a single ZIP archive. Memory-safe
 * (batched reads, per-row fwrite) and progress-reporting (pluggable callback for
 * SSE or CLI).
 *
 * @version 1.0.0
 */

namespace StoreEngine\Backup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Exporter {

	/** @var array{licensing:bool,logs:bool,users:bool,files:bool} */
	protected array $opts;

	/** @var callable|null function(float $percent, string $message): void */
	protected $progress;

	protected int $batch_size;

	protected int $rows_total = 0;
	protected int $rows_done  = 0;

	/**
	 * @param array         $opts     licensing(bool,true), deployments(bool,false), logs(bool,false), users(bool,false), files(bool,false)
	 * @param callable|null $progress function(float $percent, string $message): void
	 */
	public function __construct( array $opts = [], ?callable $progress = null ) {
		$this->opts = [
			'licensing'   => (bool) ( $opts['licensing'] ?? true ),
			'deployments' => (bool) ( $opts['deployments'] ?? false ),
			'logs'        => (bool) ( $opts['logs'] ?? false ),
			'users'       => (bool) ( $opts['users'] ?? false ),
			'files'       => (bool) ( $opts['files'] ?? false ),
		];
		$this->progress   = $progress;
		$this->batch_size = (int) apply_filters( 'storeengine/backup/batch_size', 2000 );
	}

	protected function report( float $percent, string $message ): void {
		if ( $this->progress ) {
			call_user_func( $this->progress, min( 99.0, round( $percent, 1 ) ), $message );
		}
	}

	/**
	 * Run the export. Returns the absolute path to the created .zip.
	 *
	 * @throws \StoreEngine\Classes\Exceptions\StoreEngineException
	 */
	public function run(): string {
		global $wpdb;

		$dir     = BackupManager::ensure_backups_dir();
		$stamp   = gmdate( 'Ymd-His' );
		$rand    = wp_generate_password( 8, false );
		$work    = trailingslashit( $dir ) . 'tmp-' . $stamp . '-' . $rand;
		wp_mkdir_p( $work );
		wp_mkdir_p( $work . '/tables' );

		try {
			$tables = BackupManager::tables_for( $this->opts );

			// Pre-compute total work (rows) for weighted progress.
			$this->rows_total = 1; // avoid div-by-zero
			$table_counts     = [];
			foreach ( $tables as $table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier bound via %i in prepare(); row count for export progress over a custom StoreEngine table.
				$count                 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
				$table_counts[ $table ] = $count;
				$this->rows_total      += $count;
			}
			$post_types = BackupManager::post_types();
			$post_total = $this->count_posts( $post_types );
			$this->rows_total += $post_total;
			if ( $this->opts['users'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$this->rows_total += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
			}

			$manifest = [
				'format_version' => BackupManager::FORMAT_VERSION,
				'generated_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'site_url'       => site_url(),
				'wp_prefix'      => $wpdb->prefix,
				'plugin_version' => defined( 'STOREENGINE_VERSION' ) ? STOREENGINE_VERSION : '',
				'db_version'     => defined( 'STOREENGINE_DB_VERSION' ) ? STOREENGINE_DB_VERSION : '',
				'schema_hash'    => get_option( 'storeengine_schema_hash', '' ),
				'active_addons'  => (array) get_option( 'storeengine_addons', [] ),
				'options'        => $this->opts,
				'post_types'     => $post_types,
				'includes_users' => $this->opts['users'],
				'includes_files' => $this->opts['files'],
				'excluded'       => BackupManager::DENYLIST,
				'tables'         => [],
			];

			// 1) Tables.
			foreach ( $tables as $table ) {
				$base = BackupManager::basename( $table );
				$file = 'tables/' . $base . '.jsonl';
				$cols = $this->dump_table( $table, $work . '/' . $file );
				$manifest['tables'][] = [
					'name'      => $base,
					'full_name' => $table,
					'file'      => $file,
					'row_count' => $table_counts[ $table ],
					'columns'   => $cols,
					'group'     => BackupManager::table_group( $table ),
				];
			}

			// 2) Options (raw values; + user_roles when users included).
			$manifest['options_count'] = $this->dump_options( $work . '/options.json' );

			// 3) CPT posts / postmeta / terms.
			$manifest['post_count'] = $this->dump_posts( $post_types, $work );

			// 4) WP users (opt-in).
			if ( $this->opts['users'] ) {
				$manifest['user_count'] = $this->dump_users( $work );
			}

			// Write manifest.
			$this->write_file( $work . '/manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );

			// 5) Zip it.
			$this->report( 99, __( 'Compressing archive…', 'storeengine' ) );
			$archive = trailingslashit( $dir ) . 'storeengine-backup-' . $stamp . '-' . $rand . '.zip';
			ArchiveWriter::zip_dir( $work, $archive );

			// 6) Optional uploaded files appended (streamed, no temp copy).
			//    The deployment package files (versioned-files) are large and live
			//    under their own group, so they're excluded here and appended in
			//    (6b) only when the "deployments" group is selected. Both land at
			//    the same `files/...` archive path, so restore stays symmetric.
			$backups_real   = realpath( BackupManager::backups_dir() ) ?: null;
			$versioned_dir  = BackupManager::versioned_files_dir();
			$versioned_real = $versioned_dir ? ( realpath( $versioned_dir ) ?: null ) : null;

			if ( $this->opts['files'] && defined( 'STOREENGINE_SECURE_UPLOADS_DIR' ) ) {
				ArchiveWriter::append_dir(
					$archive,
					STOREENGINE_SECURE_UPLOADS_DIR,
					'files',
					array_filter( [ $backups_real, $versioned_real ] )
				);
			}

			// 6b) Deployment package files — only with the "deployments" group.
			if ( $this->opts['deployments'] && $versioned_dir && is_dir( $versioned_dir ) ) {
				ArchiveWriter::append_dir(
					$archive,
					$versioned_dir,
					'files/' . BackupManager::VERSIONED_FILES_SUBDIR,
					$backups_real
				);
			}

			return $archive;
		} finally {
			ArchiveWriter::rrmdir( $work );
		}
	}

	/* -------------------------------------------------------------------- */

	/**
	 * Stream one table to a jsonl file in batches. Returns its column list.
	 *
	 * @return string[]
	 */
	protected function dump_table( string $table, string $out_path ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier bound via %i in prepare(); reading column list of a custom StoreEngine table for export.
		$columns = $wpdb->get_col( $wpdb->prepare( 'DESCRIBE %i', $table ), 0 );
		$columns = is_array( $columns ) ? $columns : [];

		$handle = $this->open( $out_path );
		$offset = 0;
		$base   = BackupManager::basename( $table );

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier bound via %i and LIMIT/OFFSET via %d in prepare(); batched read of a custom StoreEngine table for streaming export.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY 1 LIMIT %d OFFSET %d', $table, $this->batch_size, $offset ), ARRAY_A );
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				fwrite( $handle, wp_json_encode( $row ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming batched export; WP_Filesystem has no append and would force whole-table buffering.
			}
			$this->rows_done += count( $rows );
			$offset          += $this->batch_size;
			$this->report(
				( $this->rows_done / $this->rows_total ) * 100,
				/* translators: %s table name */
				sprintf( __( 'Exporting %s…', 'storeengine' ), $base )
			);
		} while ( count( $rows ) === $this->batch_size );

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $columns;
	}

	protected function dump_options( string $out_path ): int {
		global $wpdb;

		$names = [];
		foreach ( BackupManager::option_like_patterns() as $pattern ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
			$names = array_merge( $names, (array) $found );
		}
		// Custom roles/caps live here; bundle with the users group.
		if ( $this->opts['users'] ) {
			$names[] = $wpdb->prefix . 'user_roles';
		}
		$names = array_values( array_unique( $names ) );

		$options = [];
		foreach ( $names as $name ) {
			// Raw value straight from the table — exact serialization round-trip.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			if ( null !== $value ) {
				$options[ $name ] = $value;
			}
		}

		$this->write_file( $out_path, wp_json_encode( $options ) );

		return count( $options );
	}

	protected function count_posts( array $post_types ): int {
		global $wpdb;
		if ( empty( $post_types ) ) {
			return 0;
		}
		$in = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic %s IN() list interpolated; post types bound via prepare() (placeholders present at runtime); core posts table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($in)", $post_types ) );
	}

	protected function dump_posts( array $post_types, string $work ): int {
		global $wpdb;
		if ( empty( $post_types ) ) {
			$this->write_file( $work . '/posts.jsonl', '' );
			$this->write_file( $work . '/postmeta.jsonl', '' );
			$this->write_file( $work . '/terms.jsonl', '' );
			$this->write_file( $work . '/term_taxonomy.jsonl', '' );
			$this->write_file( $work . '/term_relationships.jsonl', '' );

			return 0;
		}

		$in_types = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// posts
		$posts_h = $this->open( $work . '/posts.jsonl' );
		$offset  = 0;
		$count   = 0;
		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic %s IN() list interpolated; post types + LIMIT/OFFSET bound via prepare() (count correct at runtime); core posts table.
			$sql  = $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_type IN ($in_types) ORDER BY ID LIMIT %d OFFSET %d", array_merge( $post_types, [ $this->batch_size, $offset ] ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $sql is built via $wpdb->prepare() on the line above; direct read for streaming export.
			$rows = $wpdb->get_results( $sql, ARRAY_A );
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				fwrite( $posts_h, wp_json_encode( $row ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming batched export; WP_Filesystem has no append and would force whole-table buffering.
			}
			$count           += count( $rows );
			$this->rows_done += count( $rows );
			$offset          += $this->batch_size;
			$this->report( ( $this->rows_done / $this->rows_total ) * 100, __( 'Exporting posts…', 'storeengine' ) );
		} while ( count( $rows ) === $this->batch_size );
		fclose( $posts_h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// postmeta (joined to our post types)
		$meta_h = $this->open( $work . '/postmeta.jsonl' );
		$offset = 0;
		do {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic %s IN() list interpolated; post types + LIMIT/OFFSET bound via prepare() (count correct at runtime); core postmeta table.
			$sql  = $wpdb->prepare(
				"SELECT pm.* FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN ($in_types) ORDER BY pm.meta_id LIMIT %d OFFSET %d",
				array_merge( $post_types, [ $this->batch_size, $offset ] )
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $sql is built via $wpdb->prepare() on the line above; direct read for streaming export.
			$rows = $wpdb->get_results( $sql, ARRAY_A );
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				fwrite( $meta_h, wp_json_encode( $row ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming batched export; WP_Filesystem has no append and would force whole-table buffering.
			}
			$offset += $this->batch_size;
		} while ( count( $rows ) === $this->batch_size );
		fclose( $meta_h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// terms / term_taxonomy / term_relationships for our taxonomies + posts
		$taxonomies = [];
		foreach ( $post_types as $pt ) {
			$taxonomies = array_merge( $taxonomies, get_object_taxonomies( $pt ) );
		}
		$taxonomies = array_values( array_unique( $taxonomies ) );
		$this->dump_terms( $taxonomies, $post_types, $work );

		return $count;
	}

	protected function dump_terms( array $taxonomies, array $post_types, string $work ): void {
		global $wpdb;

		$tt_h  = $this->open( $work . '/term_taxonomy.jsonl' );
		$trm_h = $this->open( $work . '/terms.jsonl' );
		$rel_h = $this->open( $work . '/term_relationships.jsonl' );

		if ( ! empty( $taxonomies ) ) {
			$in_tax  = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic %s IN() list interpolated; taxonomies bound via prepare() (placeholders present at runtime); core term_taxonomy table.
			$tt_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($in_tax)", $taxonomies ), ARRAY_A );
			$term_ids = [];
			foreach ( (array) $tt_rows as $row ) {
				fwrite( $tt_h, wp_json_encode( $row ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming batched export; WP_Filesystem has no append and would force whole-table buffering.
				$term_ids[ (int) $row['term_id'] ] = true;
			}
			if ( $term_ids ) {
				$ids = implode( ',', array_map( 'intval', array_keys( $term_ids ) ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$term_rows = $wpdb->get_results( "SELECT * FROM {$wpdb->terms} WHERE term_id IN ($ids)", ARRAY_A );
				foreach ( (array) $term_rows as $row ) {
					fwrite( $trm_h, wp_json_encode( $row ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming batched export; WP_Filesystem has no append and would force whole-table buffering.
				}
			}
		}

		// term_relationships for objects of our post types.
		if ( ! empty( $post_types ) ) {
			$in_types = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic %s IN() list interpolated; post types bound via prepare() (placeholders present at runtime); core term_relationships table.
			$rel_rows = $wpdb->get_results( $wpdb->prepare( "SELECT tr.* FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_type IN ($in_types)", $post_types ), ARRAY_A );
			foreach ( (array) $rel_rows as $row ) {
				fwrite( $rel_h, wp_json_encode( $row ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming batched export; WP_Filesystem has no append and would force whole-table buffering.
			}
		}

		fclose( $tt_h );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $trm_h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $rel_h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	protected function dump_users( string $work ): int {
		global $wpdb;

		$users_h = $this->open( $work . '/users.jsonl' );
		$meta_h  = $this->open( $work . '/usermeta.jsonl' );
		$offset  = 0;
		$count   = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- LIMIT/OFFSET bound via %d in prepare(); batched read of the core users table for streaming export.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->users} ORDER BY ID LIMIT %d OFFSET %d", $this->batch_size, $offset ), ARRAY_A );
			if ( ! $rows ) {
				break;
			}
			$ids = [];
			foreach ( $rows as $row ) {
				fwrite( $users_h, wp_json_encode( $row ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming batched export; WP_Filesystem has no append and would force whole-table buffering.
				$ids[] = (int) $row['ID'];
			}
			$id_in = implode( ',', $ids );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$metas = $wpdb->get_results( "SELECT * FROM {$wpdb->usermeta} WHERE user_id IN ($id_in)", ARRAY_A );
			foreach ( (array) $metas as $m ) {
				fwrite( $meta_h, wp_json_encode( $m ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming batched export; WP_Filesystem has no append and would force whole-table buffering.
			}
			$count           += count( $rows );
			$this->rows_done += count( $rows );
			$offset          += $this->batch_size;
			$this->report( ( $this->rows_done / $this->rows_total ) * 100, __( 'Exporting users…', 'storeengine' ) );
		} while ( count( $rows ) === $this->batch_size );

		fclose( $users_h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $meta_h );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $count;
	}

	/* -------------------------------------------------------------------- */

	protected function open( string $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $path, 'wb' );
		if ( ! $handle ) {
			throw new \StoreEngine\Classes\Exceptions\StoreEngineException( 'Unable to open backup work file.', 'backup-open-fail' );
		}

		return $handle;
	}

	protected function write_file( string $path, string $contents ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, $contents );
	}
}

// End of file exporter.php.
