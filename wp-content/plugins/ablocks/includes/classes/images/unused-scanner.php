<?php
namespace ABlocks\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Find attachments that appear to be unreferenced.
 *
 * ## Read this before trusting the result
 *
 * "Unused" cannot be determined reliably in WordPress. An image can be
 * referenced from post content, post meta, options, theme mods, widgets, menus,
 * customizer settings, page-builder JSON, a CSS file, another plugin's custom
 * table, or an external site hot-linking it. A scanner sees some of those and
 * cannot see the rest.
 *
 * So this deliberately answers a narrower question — "did we find any evidence
 * this is used?" — and treats absence of evidence as a *suspicion*, never as
 * proof. Everything it produces is a candidate for human review, which is why
 * removal quarantines rather than deletes. See {@see Quarantine}.
 *
 * Guards that keep false positives survivable:
 *
 * - Attachments newer than a grace period are never listed. A freshly uploaded
 *   image is routinely unreferenced for the minutes between uploading it and
 *   using it, and that window is exactly when someone runs a cleanup.
 * - Featured images, site icon, custom logo and header are checked explicitly.
 * - Both the full URL and the bare filename are searched, so a resized variant
 *   or a relative reference still counts as a use.
 */
class UnusedScanner {

	/**
	 * Attachments younger than this are never considered unused.
	 */
	const GRACE_DAYS = 30;

	/**
	 * Scan a batch of attachments.
	 *
	 * @param int $limit  Attachments to examine.
	 * @param int $offset Where to resume from.
	 * @return array{items:array, scanned:int, done:bool}
	 */
	public static function scan( $limit = 50, $offset = 0 ) {
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
				'no_found_rows'  => false,
			]
		);

		$grace = time() - ( (int) apply_filters( 'ablocks/images/unused_grace_days', self::GRACE_DAYS ) * DAY_IN_SECONDS );
		$items = [];

		foreach ( $query->posts as $id ) {
			$id = (int) $id;

			if ( get_post_time( 'U', true, $id ) > $grace ) {
				continue;
			}

			$evidence = self::find_usage( $id );
			if ( ! empty( $evidence ) ) {
				continue;
			}

			$items[] = [
				'id'    => $id,
				'title' => get_the_title( $id ),
				'url'   => wp_get_attachment_url( $id ),
				'thumb' => wp_get_attachment_image_url( $id, 'thumbnail' ),
				'bytes' => self::size_of( $id ),
				'date'  => get_the_date( 'Y-m-d', $id ),
			];
		}//end foreach

		return [
			'items'   => $items,
			'scanned' => $offset + count( $query->posts ),
			'total'   => (int) $query->found_posts,
			'done'    => count( $query->posts ) < $limit,
		];
	}

	/**
	 * Look for any evidence that an attachment is in use.
	 *
	 * Returns as soon as it finds something: the caller only needs to know
	 * whether evidence exists, and stopping early keeps the scan cheap.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string[] Reasons it appears used; empty when none were found.
	 */
	public static function find_usage( $attachment_id ) {
		global $wpdb;

		$attachment_id = (int) $attachment_id;
		$found         = [];

		// Attached to a post. Cheap, and the most common real use.
		$parent = (int) get_post_field( 'post_parent', $attachment_id );
		if ( $parent && 'attachment' !== get_post_type( $parent ) ) {
			$found[] = 'attached';
			return $found;
		}

		// Featured image.
		$thumb_for = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
				$attachment_id
			)
		);
		if ( $thumb_for ) {
			$found[] = 'featured-image';
			return $found;
		}

		// Site identity: logo, icon, header, background.
		foreach ( [ 'site_icon', 'site_logo' ] as $option ) {
			if ( (int) get_option( $option ) === $attachment_id ) {
				$found[] = $option;
				return $found;
			}
		}
		foreach ( [ 'custom_logo', 'header_image_data' ] as $mod ) {
			$value = get_theme_mod( $mod );
			if ( is_object( $value ) && isset( $value->attachment_id ) && (int) $value->attachment_id === $attachment_id ) {
				$found[] = 'theme-' . $mod;
				return $found;
			}
			if ( is_numeric( $value ) && (int) $value === $attachment_id ) {
				$found[] = 'theme-' . $mod;
				return $found;
			}
		}

		$url  = wp_get_attachment_url( $attachment_id );
		$file = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$needles = array_values(
			array_unique(
				array_filter(
					[
						$url,
						$file ? basename( $file ) : '',
						'wp-image-' . $attachment_id,
					]
				)
			)
		);

		foreach ( $needles as $needle ) {
			$like = '%' . $wpdb->esc_like( $needle ) . '%';

			// Post content and excerpts, including page-builder markup, which is
			// stored as post content or as JSON in meta.
			$in_posts = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_status NOT IN ('trash','auto-draft')
					   AND ( post_content LIKE %s OR post_excerpt LIKE %s )
					 LIMIT 1",
					$like,
					$like
				)
			);
			if ( $in_posts ) {
				$found[] = 'in-content:' . (int) $in_posts;
				return $found;
			}

			// Meta covers ACF fields, builder payloads and most plugin storage.
			$in_meta = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta}
					 WHERE meta_value LIKE %s AND post_id <> %d
					 LIMIT 1",
					$like,
					$attachment_id
				)
			);
			if ( $in_meta ) {
				$found[] = 'in-meta:' . (int) $in_meta;
				return $found;
			}

			// Options catch widgets, theme mods, and plugin settings.
			$in_options = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s LIMIT 1",
					$like
				)
			);
			if ( $in_options ) {
				$found[] = 'in-option:' . sanitize_key( $in_options );
				return $found;
			}
		}//end foreach

		// A last chance for anything with its own storage: a plugin that keeps
		// media references in a custom table can answer here rather than having
		// its images quarantined.
		return (array) apply_filters( 'ablocks/images/usage_evidence', $found, $attachment_id );
	}

	/**
	 * Total bytes an attachment occupies, including generated sizes.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int
	 */
	public static function size_of( $attachment_id ) {
		$total = 0;
		foreach ( Compressor::files_for( $attachment_id ) as $path ) {
			if ( file_exists( $path ) ) {
				$total += (int) filesize( $path );
			}
		}
		return $total;
	}
}
