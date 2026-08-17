<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;
use ABlocks\Classes\Images\Compressor;
use ABlocks\Classes\Images\Quarantine;

/**
 * Performance Suite — image compression and media cleanup.
 *
 * Wires the pieces together: optimize-on-upload, the scheduled quarantine
 * sweep, and cleanup of an attachment's stored originals when it is deleted.
 *
 * Distinct from {@see ImageOptimizer}, which only changes how images are
 * *delivered* (lazy-loading, dimensions) and never touches a file. This one
 * rewrites files on disk, which is why every part of it is opt-in.
 */
class ImageTools {

	const SWEEP_HOOK = 'ablocks/images/quarantine_sweep';

	public static function init() {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\ABlocks\Classes\Images\Cli::register();
		}

		add_action( self::SWEEP_HOOK, [ __CLASS__, 'run_sweep' ] );
		add_action( 'init', [ __CLASS__, 'ensure_events' ] );

		// An attachment's stored originals are useless once the attachment is
		// gone, and would otherwise sit in uploads forever.
		add_action( 'delete_attachment', [ Compressor::class, 'delete_backups' ] );
		// A deleted file must not keep answering to its old content hash.
		add_action( 'delete_attachment', [ \ABlocks\Classes\Images\DuplicateScanner::class, 'forget' ] );

		if ( (bool) Helper::get_settings( 'perf_image_optimize_on_upload', false ) ) {
			// Priority 20: after WordPress has generated the thumbnail sizes, so
			// every derivative is compressed rather than just the original.
			add_filter( 'wp_generate_attachment_metadata', [ __CLASS__, 'optimize_new_upload' ], 20, 2 );
		}
	}

	/**
	 * Keep the quarantine sweep scheduled only while something is held.
	 */
	public static function ensure_events() {
		$summary = Quarantine::summary();
		$next    = wp_next_scheduled( self::SWEEP_HOOK );

		if ( $summary['count'] > 0 && ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::SWEEP_HOOK );
		} elseif ( 0 === $summary['count'] && $next ) {
			wp_unschedule_event( $next, self::SWEEP_HOOK );
		}
	}

	/**
	 * Delete quarantined images that have outlived the retention window.
	 *
	 * Only runs when the site has opted into automatic deletion. Otherwise items
	 * are held indefinitely and removed by an explicit action, because silently
	 * destroying media that a scan merely *suspected* was unused is not a
	 * default anyone should get by accident.
	 *
	 * @return array
	 */
	public static function run_sweep() {
		if ( ! (bool) Helper::get_settings( 'perf_image_quarantine_autodelete', false ) ) {
			return [
				'deleted' => 0,
				'bytes'   => 0,
			];
		}

		$days = (int) Helper::get_settings( 'perf_image_quarantine_days', Quarantine::RETENTION_DAYS );

		return Quarantine::sweep( $days );
	}

	/**
	 * Compress an image as it is uploaded.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public static function optimize_new_upload( $metadata, $attachment_id ) {
		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, Compressor::SUPPORTED, true ) ) {
			return $metadata;
		}

		$level = (string) Helper::get_settings( 'perf_image_level', '2x' );
		$webp  = (bool) Helper::get_settings( 'perf_image_webp', true );

		// Never let an optimization failure break an upload: the attachment and
		// its metadata matter far more than the saving.
		try {
			Compressor::optimize_attachment( $attachment_id, $level, $webp );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'aBlocks image optimize: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		return $metadata;
	}

	/**
	 * Totals for the settings screen.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 5000",
				Compressor::META_KEY
			)
		);

		$before = 0;
		$after  = 0;
		foreach ( (array) $rows as $row ) {
			$record = maybe_unserialize( $row );
			if ( ! is_array( $record ) ) {
				continue;
			}
			$before += isset( $record['before'] ) ? (int) $record['before'] : 0;
			$after  += isset( $record['after'] ) ? (int) $record['after'] : 0;
		}

		$quarantine = Quarantine::summary();
		$originals  = Compressor::originals_size();

		return [
			// The configured strength travels with the stats because the bulk
			// run lives on the Scanner, outside the settings form. Without it
			// the Scanner would fall back to a default and quietly compress a
			// 5x site at 2x — the run would look like it worked.
			'level'           => (string) Helper::get_settings( 'perf_image_level', '2x' ),
			'webp'            => (bool) Helper::get_settings( 'perf_image_webp', true ),
			'optimized'       => count( (array) $rows ),
			'saved_bytes'     => max( 0, $before - $after ),
			'saved'           => size_format( max( 0, $before - $after ), 1 ),
			'pending'         => count( Compressor::pending_ids( 500, false ) ),
			'quarantined'     => (int) $quarantine['count'],
			'quarantine_size' => size_format( (int) $quarantine['bytes'], 1 ),
			// What deleting the stored originals would reclaim, so the choice
			// can be presented with a number rather than as a leap of faith.
			'originals_bytes' => (int) $originals,
			'originals'       => size_format( (int) $originals, 1 ),
			'originals_count' => count( Compressor::ids_with_originals() ),
		];
	}
}
