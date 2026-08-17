<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Performance Suite — preload the likely LCP image.
 *
 * PageSpeed's "LCP request discovery" audit wants the largest above-the-fold
 * image to be discoverable as early as possible. This scans a singular post's
 * content for the first aBlocks image and emits a high-priority
 * `<link rel="preload" as="image">` (with a responsive imagesrcset) in the
 * <head>, so the browser starts fetching it before it parses the body.
 *
 * Opt-in via `perf_preload_lcp`. Scoped to singular views for v1.
 */
class LcpPreload {

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		$enabled = (bool) apply_filters(
			'ablocks/perf/perf_preload_lcp',
			(bool) Helper::get_settings( 'perf_preload_lcp', true )
		);
		if ( ! $enabled ) {
			return;
		}
		$self = new self();
		add_action( 'wp_head', [ $self, 'print_preload' ], 2 );
	}

	public function print_preload() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || false === strpos( (string) $post->post_content, 'wp:ablocks/image' ) ) {
			return;
		}

		$image = $this->first_image_block( parse_blocks( $post->post_content ) );
		if ( ! $image ) {
			return;
		}

		$href = esc_url( $image['url'] );
		$attrs = 'rel="preload" as="image" fetchpriority="high" href="' . $href . '"';

		if ( ! empty( $image['id'] ) ) {
			$srcset = wp_get_attachment_image_srcset( (int) $image['id'], 'full' );
			if ( $srcset ) {
				$sizes = wp_get_attachment_image_sizes( (int) $image['id'], 'full' );
				$attrs .= ' imagesrcset="' . esc_attr( $srcset ) . '"';
				if ( $sizes ) {
					$attrs .= ' imagesizes="' . esc_attr( $sizes ) . '"';
				}
			}
		}

		echo "<link {$attrs}>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Depth-first search for the first aBlocks image block that carries a URL.
	 * Returns [ 'url' => …, 'id' => … ] or null.
	 */
	private function first_image_block( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) && 'ablocks/image' === $block['blockName'] ) {
				$url = $block['attrs']['imgUrl'] ?? '';
				if ( $url && 0 !== strpos( $url, 'ablocks_dc:' ) ) {
					return [
						'url' => $url,
						'id'  => $block['attrs']['imgId'] ?? 0,
					];
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->first_image_block( $block['innerBlocks'] );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}
}
