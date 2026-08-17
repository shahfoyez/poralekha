<?php
/**
 * StoreEngine full backup — orchestration, table registry & helpers.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Backup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry + helpers shared by the Exporter, Importer, CLI and AJAX
 * handler. Knows which `{$wpdb->prefix}storeengine_*` tables exist (discovered
 * dynamically, never hardcoded), how to classify them into selectable groups,
 * which tables are transient/ephemeral (never backed up), and where archives
 * live.
 */
class BackupManager {

	/**
	 * Bump when the archive layout changes in a backward-incompatible way.
	 */
	const FORMAT_VERSION = 1;

	const CLEANUP_HOOK = 'storeengine/backup/cleanup_archive';

	/** Recurring auto-backup cron hook. */
	const SCHEDULE_HOOK = 'storeengine/backup/scheduled_run';

	/** Option storing the auto-backup schedule + retention config. */
	const OPTION = 'storeengine_backup_schedule';

	/**
	 * Table basenames (without the `{$prefix}storeengine_` part) that are
	 * transient/ephemeral and must NEVER be exported or truncated on restore.
	 */
	const DENYLIST = [
		'cart',
		'reserved_stock',
		'sessions',
		'otp_verifications',
		'abandoned_cart',
	];

	/**
	 * Log tables — included only when the "logs" group is selected.
	 */
	const LOG_TABLES = [
		'logs',
		'email_log',
		'download_log',
	];

	/**
	 * License-management tables — included with the "licensing" group. License
	 * keys, site activations and usage analytics: small, important data.
	 */
	const LICENSE_TABLES = [
		'licenses',
		'installation_events',
		'installation_eventmeta',
		'usage_analytics',
	];

	/**
	 * Deployment tables — included with the "deployments" group. Paired with the
	 * (potentially very large) versioned deployment package files, so this group
	 * is kept separate from licensing and defaults off.
	 */
	const DEPLOYMENT_TABLES = [
		'deployment_versions',
	];

	/** Subdir (under the secure uploads dir) holding deployment package files. */
	const VERSIONED_FILES_SUBDIR = 'versioned-files';

	public static function init(): void {
		add_action( self::CLEANUP_HOOK, [ __CLASS__, 'cleanup_archive' ] );

		// Scheduled auto-backups.
		add_filter( 'cron_schedules', [ __CLASS__, 'register_cron_schedules' ] );
		add_action( self::SCHEDULE_HOOK, [ __CLASS__, 'run_scheduled' ] );
		// Self-heal the cron event if it drifts from the saved setting (e.g. after
		// a reactivation that cleared scheduled hooks).
		add_action( 'init', [ __CLASS__, 'maybe_reschedule' ] );
	}

	/* -------------------------------------------------------------------------
	 * Schedule + retention settings
	 * ---------------------------------------------------------------------- */

	public static function default_settings(): array {
		return [
			'enabled'           => false,
			'frequency'         => 'weekly',
			'opts'              => [
				'licensing'   => true,
				'deployments' => false,
				'users'       => false,
				'logs'        => false,
				'files'       => false,
			],
			'retention_enabled' => false,
			'retention_keep'    => 5,
		];
	}

	public static function get_settings(): array {
		$defaults = self::default_settings();
		$saved    = get_option( self::OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		$settings          = wp_parse_args( $saved, $defaults );
		$settings['opts']  = wp_parse_args(
			isset( $saved['opts'] ) && is_array( $saved['opts'] ) ? $saved['opts'] : [],
			$defaults['opts']
		);
		$settings['opts']  = array_map( 'boolval', $settings['opts'] );
		$settings['enabled']           = (bool) $settings['enabled'];
		$settings['retention_enabled'] = (bool) $settings['retention_enabled'];
		$settings['retention_keep']    = max( 1, (int) $settings['retention_keep'] );
		if ( ! in_array( $settings['frequency'], [ 'daily', 'weekly', 'monthly' ], true ) ) {
			$settings['frequency'] = $defaults['frequency'];
		}

		return $settings;
	}

	/**
	 * Persist the schedule config (sanitised) and reconcile the cron event.
	 */
	public static function save_settings( array $data ): array {
		$defaults = self::default_settings();
		$opts_in  = isset( $data['opts'] ) && is_array( $data['opts'] ) ? $data['opts'] : [];

		$settings = [
			'enabled'           => ! empty( $data['enabled'] ),
			'frequency'         => in_array( $data['frequency'] ?? '', [ 'daily', 'weekly', 'monthly' ], true )
				? $data['frequency']
				: $defaults['frequency'],
			'opts'              => [
				'licensing'   => ! empty( $opts_in['licensing'] ),
				'deployments' => ! empty( $opts_in['deployments'] ),
				'users'       => ! empty( $opts_in['users'] ),
				'logs'        => ! empty( $opts_in['logs'] ),
				'files'       => ! empty( $opts_in['files'] ),
			],
			'retention_enabled' => ! empty( $data['retention_enabled'] ),
			'retention_keep'    => max( 1, (int) ( $data['retention_keep'] ?? $defaults['retention_keep'] ) ),
		];

		update_option( self::OPTION, $settings, false );
		self::reschedule( $settings );

		return $settings;
	}

	/* -------------------------------------------------------------------------
	 * Cron scheduling
	 * ---------------------------------------------------------------------- */

	public static function register_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'storeengine' ),
			];
		}
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = [
				'interval' => MONTH_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'storeengine' ),
			];
		}

		return $schedules;
	}

	/**
	 * Clear + (re)schedule the recurring event to match the given settings.
	 */
	public static function reschedule( array $settings ): void {
		wp_clear_scheduled_hook( self::SCHEDULE_HOOK );

		if ( ! empty( $settings['enabled'] ) ) {
			$freq = in_array( $settings['frequency'] ?? '', [ 'daily', 'weekly', 'monthly' ], true )
				? $settings['frequency']
				: 'weekly';
			wp_schedule_event( time() + HOUR_IN_SECONDS, $freq, self::SCHEDULE_HOOK );
		}
	}

	/**
	 * Cheap drift check on every load: schedule when enabled-but-missing, clear
	 * when disabled-but-present.
	 */
	public static function maybe_reschedule(): void {
		$settings = self::get_settings();
		$next     = wp_next_scheduled( self::SCHEDULE_HOOK );

		if ( ! empty( $settings['enabled'] ) && ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $settings['frequency'], self::SCHEDULE_HOOK );
		} elseif ( empty( $settings['enabled'] ) && $next ) {
			wp_clear_scheduled_hook( self::SCHEDULE_HOOK );
		}
	}

	/**
	 * Cron callback — produce a backup with the saved scope, then prune.
	 */
	public static function run_scheduled(): void {
		$settings = self::get_settings();

		try {
			( new Exporter( $settings['opts'] ) )->run();
		} catch ( \Throwable $e ) {
			\StoreEngine\Utils\Helper::log_error( $e );
		}

		if ( ! empty( $settings['retention_enabled'] ) ) {
			self::apply_retention( (int) $settings['retention_keep'] );
		}
	}

	/**
	 * Keep only the newest `$keep` non-safety archives; delete the rest. Safety
	 * (pre-restore) backups are never auto-deleted.
	 *
	 * @return int Number of archives removed.
	 */
	public static function apply_retention( int $keep ): int {
		$keep = max( 1, $keep );

		$deletable = array_values( array_filter(
			self::list_backups(), // newest first
			static fn( $b ) => empty( $b['is_safety'] )
		) );

		$deleted = 0;
		foreach ( array_slice( $deletable, $keep ) as $b ) {
			$path = self::resolve_backup_path( $b['filename'] );
			if ( $path && is_file( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $path );
				$deleted++;
			}
		}

		return $deleted;
	}

	/* -------------------------------------------------------------------------
	 * Paths
	 * ---------------------------------------------------------------------- */

	/**
	 * Private, web-inaccessible directory for archives. Lives inside
	 * STOREENGINE_SECURE_UPLOADS_DIR which already ships a `Require all denied`
	 * .htaccess (and we add belt-and-suspenders protection here too).
	 */
	public static function backups_dir(): string {
		return trailingslashit( STOREENGINE_SECURE_UPLOADS_DIR ) . 'backups';
	}

	public static function ensure_backups_dir(): string {
		$dir = self::backups_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Defence in depth — never serve these over HTTP regardless of server.
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index, '' );
		}

		return $dir;
	}

	/* -------------------------------------------------------------------------
	 * Table discovery + classification
	 * ---------------------------------------------------------------------- */

	/**
	 * Every `{$prefix}storeengine_*` table currently in the DB (core + whatever
	 * pro addons are active). Returns full table names.
	 *
	 * @return string[]
	 */
	public static function all_tables(): array {
		global $wpdb;

		$like = $wpdb->esc_like( $wpdb->prefix . 'storeengine_' ) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		return is_array( $tables ) ? $tables : [];
	}

	/**
	 * Strip the `{$prefix}storeengine_` prefix → bare basename used for grouping.
	 */
	public static function basename( string $table ): string {
		global $wpdb;

		return preg_replace( '/^' . preg_quote( $wpdb->prefix . 'storeengine_', '/' ) . '/', '', $table );
	}

	/**
	 * Which selectable group a table belongs to: 'logs' | 'licensing' |
	 * 'deployments' | 'store'. 'store' is the always-included core/addon data set.
	 */
	public static function table_group( string $table ): string {
		$base = self::basename( $table );

		if ( in_array( $base, self::LOG_TABLES, true ) ) {
			return 'logs';
		}
		if ( in_array( $base, self::DEPLOYMENT_TABLES, true ) ) {
			return 'deployments';
		}
		if ( in_array( $base, self::LICENSE_TABLES, true ) ) {
			return 'licensing';
		}

		return 'store';
	}

	/** Absolute path to the deployment package files dir (may not exist). */
	public static function versioned_files_dir(): ?string {
		if ( ! defined( 'STOREENGINE_SECURE_UPLOADS_DIR' ) ) {
			return null;
		}

		return trailingslashit( STOREENGINE_SECURE_UPLOADS_DIR ) . self::VERSIONED_FILES_SUBDIR;
	}

	/**
	 * Resolve the set of tables to back up for the given options.
	 *
	 * @param array $opts { licensing:bool(default true), logs:bool(default false) }
	 *
	 * @return string[] Full table names.
	 */
	public static function tables_for( array $opts ): array {
		$include_licensing   = $opts['licensing'] ?? true;
		$include_deployments = $opts['deployments'] ?? false;
		$include_logs        = $opts['logs'] ?? false;

		$tables = [];
		foreach ( self::all_tables() as $table ) {
			$base = self::basename( $table );

			if ( in_array( $base, self::DENYLIST, true ) ) {
				continue;
			}

			$group = self::table_group( $table );
			if ( 'logs' === $group && ! $include_logs ) {
				continue;
			}
			if ( 'licensing' === $group && ! $include_licensing ) {
				continue;
			}
			if ( 'deployments' === $group && ! $include_deployments ) {
				continue;
			}

			$tables[] = $table;
		}

		/**
		 * Filter the final list of tables to export.
		 *
		 * @param string[] $tables Full table names.
		 * @param array    $opts   Resolved export options.
		 */
		return apply_filters( 'storeengine/backup/tables', $tables, $opts );
	}

	/**
	 * StoreEngine-registered custom post types to back up. Enumerated
	 * dynamically (prefix `storeengine` / `se_`) so no addon CPT is missed;
	 * filterable for anything outside that convention.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = [];
		foreach ( get_post_types( [ '_builtin' => false ] ) as $type ) {
			if ( str_starts_with( $type, 'storeengine' ) || str_starts_with( $type, 'se_' ) ) {
				$types[] = $type;
			}
		}

		/**
		 * Filter the post types included in a backup.
		 *
		 * @param string[] $types
		 */
		return array_values( array_unique( apply_filters( 'storeengine/backup/post_types', $types ) ) );
	}

	/**
	 * The `option_name` LIKE patterns whose options are backed up. Single
	 * `storeengine%` already covers `storeengine_pro%`.
	 *
	 * @return string[]
	 */
	public static function option_like_patterns(): array {
		return apply_filters( 'storeengine/backup/option_patterns', [ 'storeengine%' ] );
	}

	/* -------------------------------------------------------------------------
	 * Listing existing backups + filename-based (stable) download
	 * ---------------------------------------------------------------------- */

	/**
	 * Only our own archive filenames are ever downloadable/deletable.
	 */
	public static function is_valid_backup_name( string $name ): bool {
		return (bool) preg_match( '/^storeengine-(backup|prerestore)[a-z0-9\-]*\.zip$/i', $name );
	}

	/**
	 * Resolve a backup filename to an absolute path inside the backups dir, or
	 * null if it's invalid / escapes the dir / doesn't exist. (Path-traversal safe.)
	 */
	public static function resolve_backup_path( string $name ): ?string {
		$name = wp_basename( $name );
		if ( ! self::is_valid_backup_name( $name ) ) {
			return null;
		}
		$real = realpath( trailingslashit( self::backups_dir() ) . $name );
		$dir  = realpath( self::backups_dir() );

		return ( $real && $dir && str_starts_with( $real, $dir ) && is_file( $real ) ) ? $real : null;
	}

	/**
	 * List the archives currently stored (newest first). Excludes the
	 * `uploads/` (incoming import) and `tmp-*`/`restore-*` working dirs.
	 *
	 * @return array<int,array{filename:string,size:string,bytes:int,created:string,timestamp:int,is_safety:bool}>
	 */
	public static function list_backups(): array {
		$dir = self::backups_dir();
		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$out = [];
		foreach ( (array) glob( trailingslashit( $dir ) . '*.zip' ) as $path ) {
			$name = basename( $path );
			if ( ! self::is_valid_backup_name( $name ) ) {
				continue;
			}
			$mtime = (int) filemtime( $path );
			$out[] = [
				'filename'  => $name,
				'size'      => size_format( (int) filesize( $path ) ),
				'bytes'     => (int) filesize( $path ),
				'created'   => gmdate( 'Y-m-d H:i:s', $mtime ),
				'timestamp' => $mtime,
				'is_safety' => str_starts_with( $name, 'storeengine-prerestore-' ),
			];
		}

		usort( $out, static fn( $a, $b ) => $b['timestamp'] <=> $a['timestamp'] );

		return $out;
	}

	/* -------------------------------------------------------------------------
	 * Download tokens (legacy, optional)
	 * ---------------------------------------------------------------------- */

	public static function issue_download_token( string $filepath ): string {
		$token = wp_generate_password( 32, false );
		set_transient( 'storeengine_backup_dl_' . $token, $filepath, HOUR_IN_SECONDS );

		return $token;
	}

	public static function resolve_download_token( string $token ): ?string {
		$path = get_transient( 'storeengine_backup_dl_' . $token );

		return $path && is_string( $path ) ? $path : null;
	}

	/* -------------------------------------------------------------------------
	 * Cleanup
	 * ---------------------------------------------------------------------- */

	/**
	 * Action Scheduler / cron callback — delete an expired archive.
	 */
	public static function cleanup_archive( string $filepath ): void {
		$dir = realpath( self::backups_dir() );
		$real = realpath( $filepath );
		// Only ever delete inside our own backups dir.
		if ( $dir && $real && str_starts_with( $real, $dir ) && is_file( $real ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $real );
		}
	}
}

// End of file backup-manager.php.
