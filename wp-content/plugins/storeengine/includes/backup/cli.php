<?php
/**
 * StoreEngine full backup — WP-CLI commands.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Backup;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export / import a complete StoreEngine backup.
 */
class Cli {

	/**
	 * Export all StoreEngine data into a single .zip archive.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Destination .zip path. Defaults to the private backups dir (path printed).
	 *
	 * [--groups=<list>]
	 * : Comma list of optional groups to include: licensing,logs,users,files.
	 *   (Core store data + settings + catalog are always included.)
	 *   Default: licensing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine backup export --file=/tmp/se.zip --groups=licensing,users
	 *
	 * @when after_wp_load
	 */
	public function export( $args, $assoc_args ) {
		$groups = array_filter( array_map( 'trim', explode( ',', (string) ( $assoc_args['groups'] ?? 'licensing' ) ) ) );
		$opts   = [
			'licensing'   => in_array( 'licensing', $groups, true ),
			'deployments' => in_array( 'deployments', $groups, true ),
			'logs'        => in_array( 'logs', $groups, true ),
			'users'       => in_array( 'users', $groups, true ),
			'files'       => in_array( 'files', $groups, true ),
		];

		$progress = WP_CLI\Utils\make_progress_bar( 'Exporting', 100 );
		$last     = 0;
		$exporter = new Exporter( $opts, function ( $percent ) use ( $progress, &$last ) {
			$tick = (int) $percent - $last;
			if ( $tick > 0 ) {
				$progress->tick( $tick );
				$last = (int) $percent;
			}
		} );

		$path = $exporter->run();
		$progress->finish();

		if ( ! empty( $assoc_args['file'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			rename( $path, $assoc_args['file'] );
			$path = $assoc_args['file'];
		}

		WP_CLI::success( sprintf( 'Backup written to %s (%s)', $path, size_format( (int) filesize( $path ) ) ) );
	}

	/**
	 * Restore a StoreEngine backup (REPLACES existing StoreEngine data).
	 *
	 * ## OPTIONS
	 *
	 * --file=<path>
	 * : Path to the backup .zip.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--no-safety]
	 * : Skip the automatic pre-restore safety backup (not recommended).
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine backup import --file=/tmp/se.zip --yes
	 *
	 * @when after_wp_load
	 */
	public function import( $args, $assoc_args ) {
		$file = (string) ( $assoc_args['file'] ?? '' );
		if ( ! is_file( $file ) ) {
			WP_CLI::error( 'File not found: ' . $file );
		}

		$preview = ( new Importer() )->inspect( $file );
		$manifest = $preview['manifest'];
		WP_CLI::log( sprintf( 'Backup from %s (%s), generated %s.', $manifest['site_url'] ?? '?', $manifest['plugin_version'] ?? '?', $manifest['generated_at'] ?? '?' ) );
		WP_CLI::log( sprintf( '%d tables; %d will be skipped (addon not installed).', count( (array) ( $manifest['tables'] ?? [] ) ), count( $preview['missing_tables'] ) ) );

		WP_CLI::confirm( 'This will REPLACE all StoreEngine data on this site. Continue?', $assoc_args );

		$progress = WP_CLI\Utils\make_progress_bar( 'Restoring', 100 );
		$last     = 0;
		$importer = new Importer( function ( $percent ) use ( $progress, &$last ) {
			$tick = (int) $percent - $last;
			if ( $tick > 0 ) {
				$progress->tick( $tick );
				$last = (int) $percent;
			}
		} );

		$result = $importer->run( $file, [ 'safety' => ! isset( $assoc_args['no-safety'] ) ] );
		$progress->finish();

		WP_CLI::log( sprintf( 'Restored %d tables, skipped %d.', count( $result['restored'] ), count( $result['skipped'] ) ) );
		if ( ! empty( $result['safety_backup'] ) ) {
			WP_CLI::log( 'Pre-restore safety backup: ' . $result['safety_backup'] );
		}
		WP_CLI::success( 'Restore complete.' );
	}

	/**
	 * Compare a backup's manifest row counts against the current database.
	 *
	 * ## OPTIONS
	 *
	 * --file=<path>
	 * : Path to the backup .zip.
	 *
	 * @when after_wp_load
	 */
	public function verify( $args, $assoc_args ) {
		global $wpdb;
		$file = (string) ( $assoc_args['file'] ?? '' );
		if ( ! is_file( $file ) ) {
			WP_CLI::error( 'File not found: ' . $file );
		}
		$manifest = ( new Importer() )->inspect( $file )['manifest'];
		$rows     = [];
		foreach ( (array) ( $manifest['tables'] ?? [] ) as $t ) {
			$local = $wpdb->prefix . 'storeengine_' . $t['name'];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%i) row count on a custom StoreEngine table for CLI backup verification; no cache layer applies.
			$live = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $local ) );
			$rows[] = [
				'table'    => $t['name'],
				'in_backup' => (int) $t['row_count'],
				'in_db'    => $live,
				'match'    => ( (int) $t['row_count'] === $live ) ? 'yes' : 'NO',
			];
		}
		WP_CLI\Utils\format_items( 'table', $rows, [ 'table', 'in_backup', 'in_db', 'match' ] );
	}
}

// End of file cli.php.
