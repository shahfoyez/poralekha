<?php
namespace ABlocks\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Find the same image uploaded more than once.
 *
 * Media libraries accumulate copies: the same photo dragged in twice, a logo
 * re-uploaded because nobody could find the first one, a demo import bringing
 * its own version of files that already existed. WordPress does not notice — it
 * appends `-1` to the filename and stores another full set of thumbnails.
 *
 * ## Identified by content, not by name
 *
 * Two files with the same bytes are the same image whatever they are called;
 * two files called `logo.png` and `logo-1.png` may be entirely different. So
 * grouping is by a hash of the file itself.
 *
 * The hash is taken from the **stored original** where one exists, not the file
 * currently on disk. Compression rewrites bytes, so hashing the live file would
 * make two copies of one photo stop matching the moment one of them was
 * optimized — the tool would quietly go blind exactly on the libraries that
 * have been tidied up.
 *
 * Hashes are cached in post meta, so a rescan only reads files it has not seen.
 */
class DuplicateScanner {

	const HASH_META = '_ablocks_file_hash';

	/**
	 * Attachments examined per batch.
	 */
	const BATCH = 40;

	/**
	 * Group images by content.
	 *
	 * @param int $limit  Attachments to examine.
	 * @param int $offset Where to resume.
	 * @return array{groups:array, scanned:int, total:int, done:bool}
	 */
	public static function scan( $limit = self::BATCH, $offset = 0 ) {
		$limit  = max( 1, min( 200, (int) $limit ) );
		$offset = max( 0, (int) $offset );

		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]
		);

		foreach ( $query->posts as $id ) {
			self::hash_for( (int) $id );
		}

		return [
			'groups'  => [],
			'scanned' => $offset + count( $query->posts ),
			'total'   => (int) $query->found_posts,
			'done'    => count( $query->posts ) < $limit,
		];
	}

	/**
	 * Every set of attachments sharing identical content.
	 *
	 * Read from the stored hashes, so this is a database query rather than a
	 * pass over the filesystem — the reading happens during {@see self::scan()}.
	 *
	 * @return array Groups, largest first.
	 */
	public static function groups() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value AS hash
				 FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value <> ''",
				self::HASH_META
			),
			ARRAY_A
		);

		$by_hash = [];
		foreach ( (array) $rows as $row ) {
			$by_hash[ $row['hash'] ][] = (int) $row['post_id'];
		}

		$groups = [];
		foreach ( $by_hash as $hash => $ids ) {
			if ( count( $ids ) < 2 ) {
				continue;
			}

			sort( $ids );
			$members = [];
			$wasted  = 0;

			foreach ( $ids as $index => $id ) {
				if ( 'attachment' !== get_post_type( $id ) ) {
					continue;
				}

				$used = UnusedScanner::find_usage( $id );
				$size = UnusedScanner::size_of( $id );

				// Everything after the first copy is wasted space, but only the
				// unreferenced ones can actually be removed.
				if ( $index > 0 && empty( $used ) ) {
					$wasted += $size;
				}

				$members[] = [
					'id'    => $id,
					'title' => get_the_title( $id ),
					'thumb' => wp_get_attachment_image_url( $id, 'thumbnail' ),
					'url'   => wp_get_attachment_url( $id ),
					'date'  => get_the_date( 'Y-m-d', $id ),
					'bytes' => $size,
					'size'  => size_format( $size, 1 ),
					'used'  => ! empty( $used ),
					'why'   => ! empty( $used ) ? (string) reset( $used ) : '',
				];
			}

			if ( count( $members ) < 2 ) {
				continue;
			}

			// The oldest copy that is actually referenced is the one to keep;
			// failing that, simply the oldest. Suggesting deletion of the copy a
			// page is pointing at would be exactly wrong.
			$keep = $members[0]['id'];
			foreach ( $members as $member ) {
				if ( $member['used'] ) {
					$keep = $member['id'];
					break;
				}
			}

			$groups[] = [
				'hash'    => (string) $hash,
				'members' => $members,
				'keep'    => $keep,
				'wasted'  => $wasted,
				'saving'  => size_format( $wasted, 1 ),
			];
		}//end foreach

		usort(
			$groups,
			function ( $a, $b ) {
				return $b['wasted'] <=> $a['wasted'];
			}
		);

		return $groups;
	}

	/**
	 * Content hash for an attachment, computed once and remembered.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force         Recompute even if a hash is stored.
	 * @return string
	 */
	public static function hash_for( $attachment_id, $force = false ) {
		$attachment_id = (int) $attachment_id;

		if ( ! $force ) {
			$stored = get_post_meta( $attachment_id, self::HASH_META, true );
			if ( is_string( $stored ) && '' !== $stored ) {
				return $stored;
			}
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return '';
		}

		// Prefer the untouched copy: compression changes the bytes, and identity
		// has to survive that or two copies of one photo stop matching as soon
		// as either is optimized.
		$backup = Compressor::backup_path( $file, $attachment_id );
		$source = ( $backup && file_exists( $backup ) ) ? $backup : $file;

		if ( ! file_exists( $source ) ) {
			return '';
		}

		$hash = sha1_file( $source );
		if ( false === $hash ) {
			return '';
		}

		update_post_meta( $attachment_id, self::HASH_META, $hash );

		return $hash;
	}

	/**
	 * Summary for the panel header.
	 *
	 * @return array{groups:int, extra:int, bytes:int, size:string}
	 */
	public static function summary() {
		$groups = self::groups();
		$extra  = 0;
		$bytes  = 0;

		foreach ( $groups as $group ) {
			$extra += count( $group['members'] ) - 1;
			$bytes += (int) $group['wasted'];
		}

		return [
			'groups' => count( $groups ),
			'extra'  => $extra,
			'bytes'  => $bytes,
			'size'   => size_format( $bytes, 1 ),
		];
	}

	/**
	 * Drop a stored hash when the file changes, so it is recomputed.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function forget( $attachment_id ) {
		delete_post_meta( (int) $attachment_id, self::HASH_META );
	}
}
