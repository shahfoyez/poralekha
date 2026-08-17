<?php
namespace ABlocks\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image compression — recompress attachment files in place.
 *
 * Three strength levels, following the shape users know from Smush and similar:
 *
 *   1x  gentle    ~ quality 90, visually indistinguishable
 *   2x  balanced  ~ quality 80, the sensible default
 *   5x  aggressive~ quality 65, noticeably smaller, some loss on detailed photos
 *
 * Everything runs locally through WP_Image_Editor (Imagick or GD), so no image
 * ever leaves the server and there is no API key, quota or third-party
 * dependency. That is a deliberate difference from the hosted optimizers: the
 * ceiling is lower than a dedicated service, but it works offline, on staging,
 * and for free.
 *
 * ## Originals are always kept
 *
 * Compression is lossy and cannot be undone by recompressing. Before the first
 * write, the untouched file is copied aside, so any level can be re-applied
 * from the original rather than stacking loss on loss — running 2x and then 5x
 * would otherwise compress an already-compressed image and look far worse than
 * 5x alone. It is also what makes "restore originals" possible at all.
 *
 * ## Never grows a file
 *
 * Recompression can produce a *larger* file than it started with, particularly
 * on flat graphics and on images already optimised elsewhere. Results are only
 * kept when they are actually smaller.
 */
class Compressor {

	const BACKUP_DIR = 'ablocks-originals';
	const META_KEY   = '_ablocks_image_optimized';

	/**
	 * Quality per level.
	 */
	const LEVELS = [
		'1x' => 90,
		'2x' => 80,
		'5x' => 65,
	];

	/**
	 * Mime types worth recompressing.
	 *
	 * PNG is included but treated carefully: for photographic PNGs quality has
	 * little meaning, and the real win is WebP alongside.
	 */
	const SUPPORTED = [ 'image/jpeg', 'image/png', 'image/webp' ];

	/**
	 * Quality for a level name.
	 *
	 * @param string $level Level key.
	 * @return int
	 */
	public static function quality_for( $level ) {
		$level = (string) $level;
		$map   = (array) apply_filters( 'ablocks/images/levels', self::LEVELS );

		if ( isset( $map[ $level ] ) ) {
			return (int) $map[ $level ];
		}

		return (int) $map['2x'];
	}

	/**
	 * Optimize an attachment and every generated size.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $level         Level key (1x|2x|5x).
	 * @param bool   $make_webp     Also write a .webp sibling.
	 * @return array{ok:bool, before:int, after:int, files:int, webp:int, message:string}
	 */
	public static function optimize_attachment( $attachment_id, $level = '2x', $make_webp = true ) {
		$attachment_id = (int) $attachment_id;
		$result        = [
			'ok'      => false,
			'before'  => 0,
			'after'   => 0,
			'files'   => 0,
			'webp'    => 0,
			'message' => '',
		];

		// Once the originals are gone there is no pristine source left, and
		// compressing again would work from the already-compressed file:
		// ensure_backup() would quietly adopt *that* as the new "original", so
		// every future saving would be measured against it and a restore would
		// hand back a compressed image. Refusing is the only honest option.
		$record = self::record( $attachment_id );
		if ( ! empty( $record['originals_deleted'] ) ) {
			$result['message'] = __( 'Originals were deleted, so this image cannot be re-compressed.', 'ablocks' );
			return $result;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			$result['message'] = __( 'File missing.', 'ablocks' );
			return $result;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, self::SUPPORTED, true ) ) {
			$result['message'] = __( 'Unsupported file type.', 'ablocks' );
			return $result;
		}

		foreach ( self::files_for( $attachment_id, $file ) as $path ) {
			$one = self::optimize_file( $path, $level, $make_webp, $attachment_id );

			$result['before'] += $one['before'];
			$result['after']  += $one['after'];
			$result['files']  += $one['changed'] ? 1 : 0;
			$result['webp']   += $one['webp'] ? 1 : 0;
		}

		$result['ok'] = true;

		update_post_meta(
			$attachment_id,
			self::META_KEY,
			[
				'level'  => (string) $level,
				'before' => (int) $result['before'],
				'after'  => (int) $result['after'],
				'files'  => (int) $result['files'],
				'webp'   => (int) $result['webp'],
				'time'   => time(),
			]
		);

		return $result;
	}

	/**
	 * Every file belonging to an attachment: the original plus each thumbnail.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file          Absolute path to the full-size file.
	 * @return string[]
	 */
	public static function files_for( $attachment_id, $file = '' ) {
		$file = $file ? $file : get_attached_file( $attachment_id );
		if ( ! $file ) {
			return [];
		}

		$paths = [ $file ];
		$dir   = dirname( $file );
		$meta  = wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$candidate = $dir . '/' . basename( $size['file'] );
				if ( file_exists( $candidate ) ) {
					$paths[] = $candidate;
				}
			}
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Recompress one file.
	 *
	 * @param string $path          Absolute file path.
	 * @param string $level         Level key.
	 * @param bool   $make_webp     Also write a .webp sibling.
	 * @param int    $attachment_id Owning attachment, for the backup path.
	 * @return array{before:int, after:int, changed:bool, webp:bool}
	 */
	private static function optimize_file( $path, $level, $make_webp, $attachment_id ) {
		$out = [
			'before'  => 0,
			'after'   => 0,
			'changed' => false,
			'webp'    => false,
		];

		$current = (int) filesize( $path );
		if ( $current <= 0 ) {
			return $out;
		}

		// Always compress from the pristine copy, never from the current file,
		// so levels are re-applicable instead of compounding.
		$source = self::ensure_backup( $path, $attachment_id );
		$source = $source ? $source : $path;

		// Savings are reported against the *original*, not against whatever the
		// last run left behind. Measuring from the current file makes every
		// re-run look like it saved another 25% and makes levels impossible to
		// compare — 5x has to be judged against the untouched image, not against
		// the output of 2x.
		$before        = (int) filesize( $source );
		$before        = $before > 0 ? $before : $current;
		$out['before'] = $before;
		$out['after']  = $current;

		$quality = self::quality_for( $level );

		$editor = wp_get_image_editor( $source );
		if ( is_wp_error( $editor ) ) {
			return $out;
		}
		$editor->set_quality( $quality );

		// Written to a temporary name and swapped in only if smaller, so a failed
		// or counter-productive pass can never damage the live file.
		$tmp  = $path . '.ablocks-tmp';
		$save = $editor->save( $tmp );

		if ( is_wp_error( $save ) || empty( $save['path'] ) || ! file_exists( $save['path'] ) ) {
			return $out;
		}

		$after = (int) filesize( $save['path'] );

		// Compared against the original, so a weaker level applied after a
		// stronger one restores the larger-but-better file rather than being
		// rejected for being bigger than the current one.
		if ( $after > 0 && $after < $before ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value checked; a racing writer must not warn into output.
			if ( @rename( $save['path'], $path ) ) {
				$out['after']   = $after;
				$out['changed'] = true;
			} else {
				wp_delete_file( $save['path'] );
			}
		} else {
			// Recompression came out no smaller than the original — common on
			// flat graphics and on images already optimised elsewhere.
			wp_delete_file( $save['path'] );

			// Fall back to the original rather than leaving whatever a previous
			// pass left behind, so a level always produces the same result no
			// matter what ran before it. Without this, applying 5x and then 1x
			// leaves thumbnails at 5x quality — because a 1x re-encode is bigger
			// than the current file but smaller than the original — and the
			// picture the user gets depends on the order they clicked, which is
			// impossible to reason about or support.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_copy,WordPress.PHP.NoSilencedErrors.Discouraged -- Restoring inside uploads; see ensure_backup().
			if ( $source !== $path && $current !== $before && @copy( $source, $path ) ) {
				$out['after']   = $before;
				$out['changed'] = true;
			}
		}//end if

		if ( $make_webp ) {
			$out['webp'] = self::write_webp( $source, $path, $quality );
		}

		return $out;
	}

	/**
	 * Write a .webp sibling next to a file.
	 *
	 * Kept as a sibling rather than replacing the original so nothing that
	 * references the existing URL breaks; delivery picks it up separately.
	 *
	 * @param string $source  Pristine source path.
	 * @param string $path    Live file path (names the sibling).
	 * @param int    $quality Encoder quality.
	 * @return bool
	 */
	private static function write_webp( $source, $path, $quality ) {
		if ( ! function_exists( 'imagewebp' ) && ! class_exists( 'Imagick' ) ) {
			return false;
		}

		$target = preg_replace( '/\.(jpe?g|png)$/i', '', $path ) . '.webp';
		if ( $target === $path ) {
			return false; // Already a webp.
		}

		$editor = wp_get_image_editor( $source );
		if ( is_wp_error( $editor ) ) {
			self::drop_stale_webp( $target );
			return false;
		}
		$editor->set_quality( $quality );

		// Written beside the target first, so a rejected result never replaces a
		// good sibling that is already in place.
		$tmp   = $target . '.ablocks-tmp.webp';
		$saved = $editor->save( $tmp, 'image/webp' );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
			self::drop_stale_webp( $target );
			return false;
		}

		// A WebP larger than the file it is meant to replace is worse than none.
		// This matters most on a *re-run at a stronger level*: the JPEG shrinks,
		// so a WebP that won last time can now lose. Leaving the old one on disk
		// would keep serving a file bigger than the JPEG beside it, so the stale
		// sibling is removed rather than merely not replaced.
		if ( file_exists( $path ) && filesize( $saved['path'] ) >= filesize( $path ) ) {
			wp_delete_file( $saved['path'] );
			self::drop_stale_webp( $target );
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value checked.
		if ( ! @rename( $saved['path'], $target ) ) {
			wp_delete_file( $saved['path'] );
			return false;
		}

		return true;
	}

	/**
	 * Remove a WebP sibling left by an earlier, weaker pass.
	 *
	 * @param string $target Sibling path.
	 */
	private static function drop_stale_webp( $target ) {
		if ( $target && file_exists( $target ) ) {
			wp_delete_file( $target );
		}
	}

	/**
	 * Copy a file into the originals store the first time it is touched.
	 *
	 * @param string $path          Live file path.
	 * @param int    $attachment_id Owning attachment.
	 * @return string|null Path to the pristine copy.
	 */
	public static function ensure_backup( $path, $attachment_id ) {
		$backup = self::backup_path( $path, $attachment_id );
		if ( ! $backup ) {
			return null;
		}

		if ( file_exists( $backup ) ) {
			return $backup;
		}

		$dir = dirname( $backup );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_copy,WordPress.PHP.NoSilencedErrors.Discouraged -- Copying inside uploads; WP_Filesystem adds no safety and may need FTP credentials on a frontend request.
		return @copy( $path, $backup ) ? $backup : null;
	}

	/**
	 * Where a file's pristine copy lives.
	 *
	 * @param string $path          Live file path.
	 * @param int    $attachment_id Owning attachment.
	 * @return string|null
	 */
	public static function backup_path( $path, $attachment_id ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return null;
		}

		$base = trailingslashit( $upload['basedir'] ) . self::BACKUP_DIR;

		return $base . '/' . (int) $attachment_id . '/' . basename( $path );
	}

	/**
	 * Put every original back, discarding optimized versions.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int Files restored.
	 */
	public static function restore_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$restored      = 0;

		foreach ( self::files_for( $attachment_id ) as $path ) {
			$backup = self::backup_path( $path, $attachment_id );
			if ( ! $backup || ! file_exists( $backup ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_copy,WordPress.PHP.NoSilencedErrors.Discouraged -- See ensure_backup().
			if ( @copy( $backup, $path ) ) {
				$restored++;
			}

			$webp = preg_replace( '/\.(jpe?g|png)$/i', '', $path ) . '.webp';
			if ( $webp !== $path && file_exists( $webp ) ) {
				wp_delete_file( $webp );
			}
		}

		if ( $restored ) {
			self::delete_backups( $attachment_id );
			delete_post_meta( $attachment_id, self::META_KEY );
		}

		return $restored;
	}

	/**
	 * Remove an attachment's stored originals.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function delete_backups( $attachment_id ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return;
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::BACKUP_DIR . '/' . (int) $attachment_id;
		if ( ! is_dir( $dir ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value checked.
		$entries = @scandir( $dir );
		if ( false === $entries ) {
			return;
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			wp_delete_file( $dir . '/' . $entry );
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best effort.
		@rmdir( $dir );
	}

	/**
	 * Delete an attachment's stored originals and mark it as no longer reversible.
	 *
	 * Reclaims the disk the pristine copies occupy, at the cost of everything
	 * they make possible: restoring, and re-applying a different strength from a
	 * clean source. The flag is what stops a later run silently treating the
	 * compressed file as the original — see optimize_attachment().
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int Bytes reclaimed.
	 */
	public static function discard_originals( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$freed         = self::originals_size( $attachment_id );

		self::delete_backups( $attachment_id );

		$record = self::record( $attachment_id );
		if ( is_array( $record ) ) {
			$record['originals_deleted'] = true;
			update_post_meta( $attachment_id, self::META_KEY, $record );
		}

		return $freed;
	}

	/**
	 * Bytes held by stored originals.
	 *
	 * @param int $attachment_id Attachment ID, or 0 for every attachment.
	 * @return int
	 */
	public static function originals_size( $attachment_id = 0 ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$base = trailingslashit( $upload['basedir'] ) . self::BACKUP_DIR;
		$dirs = $attachment_id
			? [ $base . '/' . (int) $attachment_id ]
			: glob( $base . '/*', GLOB_ONLYDIR );

		$total = 0;
		foreach ( (array) $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( (array) glob( $dir . '/*' ) as $file ) {
				if ( is_file( $file ) ) {
					$total += (int) filesize( $file );
				}
			}
		}

		return $total;
	}

	/**
	 * Attachment IDs whose originals are still stored.
	 *
	 * @param int $limit Maximum to return.
	 * @return int[]
	 */
	public static function ids_with_originals( $limit = 10000 ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return [];
		}

		$base = trailingslashit( $upload['basedir'] ) . self::BACKUP_DIR;
		$dirs = glob( $base . '/*', GLOB_ONLYDIR );

		$ids = [];
		foreach ( (array) $dirs as $dir ) {
			$id = (int) basename( $dir );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
			if ( count( $ids ) >= $limit ) {
				break;
			}
		}

		return $ids;
	}

	/**
	 * Has this attachment been optimized?
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null Stored record, or null.
	 */
	public static function record( $attachment_id ) {
		$meta = get_post_meta( (int) $attachment_id, self::META_KEY, true );
		return is_array( $meta ) ? $meta : null;
	}

	/**
	 * Attachment IDs that are candidates for optimization.
	 *
	 * @param int  $limit          Maximum to return.
	 * @param bool $include_done   Include already-optimized attachments.
	 * @return int[]
	 */
	public static function pending_ids( $limit = 50, $include_done = false ) {
		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => self::SUPPORTED,
			'posts_per_page' => max( 1, (int) $limit ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		if ( ! $include_done ) {
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded by posts_per_page; this is an admin-triggered batch, not a page render.
				[
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				],
			];
		}

		$query = new \WP_Query( $args );

		return array_map( 'intval', $query->posts );
	}
}
