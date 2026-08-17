<?php
namespace ABlocks\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hold "unused" images aside instead of deleting them.
 *
 * {@see UnusedScanner} cannot prove an image is unused — it can only report
 * that it found no evidence of use. Acting on that with wp_delete_attachment()
 * would make every false positive permanent and unrecoverable, and the failure
 * is silent: a missing image on one page nobody looks at, discovered months
 * later with no way back.
 *
 * So removal is staged. The attachment's files move into a quarantine folder
 * and the post is soft-deleted, both reversible for a retention window. Only
 * after that window passes is anything actually destroyed, and even then only
 * by an explicit sweep the site owner controls.
 *
 * The record of what moved where is kept in an option rather than post meta,
 * because the post itself is part of what gets removed.
 */
class Quarantine {

	const DIR_NAME = 'ablocks-quarantine';
	const OPTION   = 'ablocks_quarantined_images';

	/**
	 * Days a quarantined image is kept before it can be swept.
	 */
	const RETENTION_DAYS = 30;

	/**
	 * Move an attachment's files into quarantine and trash the post.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{ok:bool, bytes:int, message:string}
	 */
	public static function hold( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$out           = [
			'ok'      => false,
			'bytes'   => 0,
			'message' => '',
		];

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			$out['message'] = __( 'Not an attachment.', 'ablocks' );
			return $out;
		}

		// Re-check at the moment of removal rather than trusting the scan that
		// produced the list. Lists get reviewed slowly, and a page published in
		// between would otherwise lose its image.
		$evidence = UnusedScanner::find_usage( $attachment_id );
		if ( ! empty( $evidence ) ) {
			/* translators: %s: reason the image appears to be in use. */
			$out['message'] = sprintf( __( 'Now in use (%s) — skipped.', 'ablocks' ), (string) reset( $evidence ) );
			return $out;
		}

		$files = Compressor::files_for( $attachment_id );
		if ( empty( $files ) ) {
			$out['message'] = __( 'No files found.', 'ablocks' );
			return $out;
		}

		$dir = self::dir_for( $attachment_id );
		if ( ! wp_mkdir_p( $dir ) ) {
			$out['message'] = __( 'Could not create the quarantine folder.', 'ablocks' );
			return $out;
		}

		$moved = [];
		foreach ( $files as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$target = $dir . '/' . basename( $path );

			$out['bytes'] += (int) filesize( $path );

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value checked.
			if ( @rename( $path, $target ) ) {
				$moved[] = [
					'from' => $path,
					'to'   => $target,
				];
			}
		}

		if ( empty( $moved ) ) {
			$out['message'] = __( 'Nothing could be moved.', 'ablocks' );
			return $out;
		}

		$records                   = self::records();
		$records[ $attachment_id ] = [
			'id'    => $attachment_id,
			'title' => get_the_title( $attachment_id ),
			'files' => $moved,
			'bytes' => (int) $out['bytes'],
			'time'  => time(),
		];
		self::save_records( $records );

		// Trashed, not deleted: the row has to survive for a restore to put the
		// attachment back with the same ID, which is what every reference to it
		// depends on.
		wp_trash_post( $attachment_id );

		$out['ok'] = true;

		return $out;
	}

	/**
	 * Put a quarantined attachment back.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function restore( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$records       = self::records();

		if ( empty( $records[ $attachment_id ]['files'] ) ) {
			return false;
		}

		$restored = 0;
		foreach ( $records[ $attachment_id ]['files'] as $pair ) {
			if ( empty( $pair['from'] ) || empty( $pair['to'] ) || ! file_exists( $pair['to'] ) ) {
				continue;
			}
			$dir = dirname( $pair['from'] );
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				continue;
			}
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value checked.
			if ( @rename( $pair['to'], $pair['from'] ) ) {
				$restored++;
			}
		}

		if ( ! $restored ) {
			return false;
		}

		wp_untrash_post( $attachment_id );
		// Untrashing restores the previous status, which for an attachment must
		// be 'inherit'; WordPress can leave it as 'draft' otherwise, and a draft
		// attachment is invisible to the media library.
		wp_update_post(
			[
				'ID'          => $attachment_id,
				'post_status' => 'inherit',
			]
		);

		unset( $records[ $attachment_id ] );
		self::save_records( $records );

		self::cleanup_dir( self::dir_for( $attachment_id ) );

		return true;
	}

	/**
	 * Permanently destroy quarantined items past the retention window.
	 *
	 * @param int|null $days Override the retention window.
	 * @return array{deleted:int, bytes:int}
	 */
	public static function sweep( $days = null ) {
		$days = null === $days
			? (int) apply_filters( 'ablocks/images/quarantine_retention_days', self::RETENTION_DAYS )
			: (int) $days;

		$cutoff  = time() - ( max( 1, $days ) * DAY_IN_SECONDS );
		$records = self::records();
		$out     = [
			'deleted' => 0,
			'bytes'   => 0,
		];

		foreach ( $records as $id => $record ) {
			if ( empty( $record['time'] ) || $record['time'] > $cutoff ) {
				continue;
			}

			foreach ( (array) $record['files'] as $pair ) {
				if ( ! empty( $pair['to'] ) && file_exists( $pair['to'] ) ) {
					$out['bytes'] += (int) filesize( $pair['to'] );
					wp_delete_file( $pair['to'] );
				}
			}

			self::cleanup_dir( self::dir_for( $id ) );
			wp_delete_post( (int) $id, true );

			unset( $records[ $id ] );
			$out['deleted']++;
		}

		self::save_records( $records );

		return $out;
	}

	/**
	 * Everything currently held.
	 *
	 * @return array
	 */
	public static function records() {
		$records = get_option( self::OPTION, [] );
		return is_array( $records ) ? $records : [];
	}

	/**
	 * Summary for display.
	 *
	 * @return array{count:int, bytes:int, oldest:int}
	 */
	public static function summary() {
		$records = self::records();
		$bytes   = 0;
		$oldest  = 0;

		foreach ( $records as $record ) {
			$bytes += isset( $record['bytes'] ) ? (int) $record['bytes'] : 0;
			$time   = isset( $record['time'] ) ? (int) $record['time'] : 0;
			if ( $time && ( ! $oldest || $time < $oldest ) ) {
				$oldest = $time;
			}
		}

		return [
			'count'  => count( $records ),
			'bytes'  => $bytes,
			'oldest' => $oldest,
		];
	}

	/**
	 * Persist the record set.
	 *
	 * @param array $records Records.
	 */
	private static function save_records( $records ) {
		update_option( self::OPTION, $records, false );
	}

	/**
	 * Quarantine folder for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function dir_for( $attachment_id ) {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['basedir'] ) . self::DIR_NAME . '/' . (int) $attachment_id;
	}

	/**
	 * Remove a quarantine folder once it is empty.
	 *
	 * @param string $dir Directory.
	 */
	private static function cleanup_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value checked.
		$entries = @scandir( $dir );
		if ( false === $entries ) {
			return;
		}
		if ( 2 === count( $entries ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best effort.
			@rmdir( $dir );
		}
	}
}
