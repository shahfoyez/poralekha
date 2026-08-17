<?php
namespace ABlocks\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image tools — WP-CLI commands.
 *
 * Bulk work belongs on the command line as much as in the browser: an admin
 * page optimising ten thousand images depends on a tab staying open, and the
 * same job from WP-CLI does not.
 */
class Cli {

	/**
	 * Register the command with WP-CLI.
	 */
	public static function register() {
		\WP_CLI::add_command( 'ablocks image', __CLASS__ );
	}

	/**
	 * Recompress images.
	 *
	 * ## OPTIONS
	 *
	 * [--level=<level>]
	 * : Compression strength.
	 * ---
	 * default: 2x
	 * options:
	 *   - 1x
	 *   - 2x
	 *   - 5x
	 * ---
	 *
	 * [--limit=<count>]
	 * : How many attachments to process.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--all]
	 * : Include attachments that were already optimized.
	 *
	 * [--webp]
	 * : Also write .webp siblings.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ablocks image optimize --level=2x --limit=200 --webp
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function optimize( $args, $assoc_args ) {
		$level = isset( $assoc_args['level'] ) ? (string) $assoc_args['level'] : '2x';
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50;
		$webp  = isset( $assoc_args['webp'] );
		$all   = isset( $assoc_args['all'] );

		$ids = Compressor::pending_ids( $limit, $all );
		if ( empty( $ids ) ) {
			\WP_CLI::success( 'Nothing to optimize.' );
			return;
		}

		$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Optimizing (%s)', $level ), count( $ids ) );
		$before   = 0;
		$after    = 0;

		foreach ( $ids as $id ) {
			$result  = Compressor::optimize_attachment( $id, $level, $webp );
			$before += (int) $result['before'];
			$after  += (int) $result['after'];
			$progress->tick();
		}
		$progress->finish();

		$saved = $before - $after;

		\WP_CLI::success(
			sprintf(
				'%d image(s): %s -> %s, saved %s (%.1f%%).',
				count( $ids ),
				size_format( $before, 1 ),
				size_format( $after, 1 ),
				size_format( max( 0, $saved ), 1 ),
				$before > 0 ? ( 100 * $saved / $before ) : 0
			)
		);
	}

	/**
	 * Put optimized images back to their originals.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Restore a single attachment instead of all of them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ablocks image restore
	 *     wp ablocks image restore 543
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function restore( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$count = Compressor::restore_attachment( (int) $args[0] );
			\WP_CLI::success( sprintf( '%d file(s) restored.', $count ) );
			return;
		}

		$ids   = Compressor::pending_ids( 10000, true );
		$total = 0;
		foreach ( $ids as $id ) {
			$total += Compressor::restore_attachment( $id );
		}

		\WP_CLI::success( sprintf( '%d file(s) restored across %d attachment(s).', $total, count( $ids ) ) );
	}

	/**
	 * List images with no evidence of being used.
	 *
	 * Absence of evidence is not proof: an image can be referenced from places a
	 * scan cannot see. Review the list before acting on it.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<count>]
	 * : How many attachments to examine.
	 * ---
	 * default: 200
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function unused( $args, $assoc_args ) {
		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 200;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$found   = [];
		$offset  = 0;
		$bytes   = 0;
		$scanned = 0;

		do {
			$batch    = UnusedScanner::scan( 50, $offset );
			$offset   = $batch['scanned'];
			$scanned  = $batch['scanned'];
			foreach ( $batch['items'] as $item ) {
				$found[] = [
					'id'    => $item['id'],
					'title' => $item['title'],
					'size'  => size_format( $item['bytes'], 1 ),
					'date'  => $item['date'],
				];
				$bytes  += (int) $item['bytes'];
			}
		} while ( ! $batch['done'] && $scanned < $limit );

		if ( empty( $found ) ) {
			\WP_CLI::success( sprintf( 'Scanned %d image(s); none look unused.', $scanned ) );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $found, [ 'id', 'title', 'size', 'date' ] );
		\WP_CLI::log( sprintf( '%d candidate(s), %s total. Verify before removing.', count( $found ), size_format( $bytes, 1 ) ) );
	}

	/**
	 * Move unused images into quarantine, or manage what is already there.
	 *
	 * Nothing is destroyed: files move aside and the attachment is trashed, both
	 * reversible until the retention window passes.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Attachment IDs to quarantine. Omit to act on the whole scan.
	 *
	 * [--list]
	 * : Show what is currently quarantined.
	 *
	 * [--restore=<id>]
	 * : Bring one attachment back.
	 *
	 * [--sweep]
	 * : Permanently delete items past the retention window.
	 *
	 * [--days=<days>]
	 * : Override the retention window for --sweep.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ablocks image quarantine --list
	 *     wp ablocks image quarantine 543 544
	 *     wp ablocks image quarantine --restore=543
	 *     wp ablocks image quarantine --sweep --days=30
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function quarantine( $args, $assoc_args ) {
		if ( isset( $assoc_args['list'] ) ) {
			$records = Quarantine::records();
			if ( empty( $records ) ) {
				\WP_CLI::success( 'Quarantine is empty.' );
				return;
			}
			$rows = [];
			foreach ( $records as $record ) {
				$rows[] = [
					'id'    => $record['id'],
					'title' => $record['title'],
					'size'  => size_format( $record['bytes'], 1 ),
					'held'  => gmdate( 'Y-m-d H:i', $record['time'] ) . ' UTC',
				];
			}
			\WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'title', 'size', 'held' ] );
			return;
		}

		if ( ! empty( $assoc_args['restore'] ) ) {
			$ok = Quarantine::restore( (int) $assoc_args['restore'] );
			if ( $ok ) {
				\WP_CLI::success( sprintf( 'Attachment %d restored.', (int) $assoc_args['restore'] ) );
			} else {
				\WP_CLI::warning( 'Nothing to restore for that ID.' );
			}
			return;
		}

		if ( isset( $assoc_args['sweep'] ) ) {
			$days = isset( $assoc_args['days'] ) ? (int) $assoc_args['days'] : null;
			\WP_CLI::confirm( 'Permanently delete quarantined images past the retention window?', $assoc_args );
			$result = Quarantine::sweep( $days );
			\WP_CLI::success( sprintf( '%d image(s) deleted, %s reclaimed.', $result['deleted'], size_format( $result['bytes'], 1 ) ) );
			return;
		}

		$ids = array_map( 'intval', $args );
		if ( empty( $ids ) ) {
			\WP_CLI::warning( 'Pass attachment IDs, or use --list / --restore / --sweep. Run `wp ablocks image unused` first.' );
			return;
		}

		$held  = 0;
		$bytes = 0;
		foreach ( $ids as $id ) {
			$result = Quarantine::hold( $id );
			if ( $result['ok'] ) {
				$held++;
				$bytes += (int) $result['bytes'];
			} else {
				\WP_CLI::warning( sprintf( '%d: %s', $id, $result['message'] ) );
			}
		}

		\WP_CLI::success( sprintf( '%d image(s) quarantined, %s freed. Restore with --restore=<id>.', $held, size_format( $bytes, 1 ) ) );
	}
}
