<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Performance Suite — image loading optimizations for aBlocks blocks.
 *
 * Two independent, opt-in transforms applied to rendered aBlocks block HTML:
 *  - `perf_lazy_images`: `loading="lazy"` + `decoding="async"`, keeping the first
 *    N images eager with `fetchpriority="high"` so the LCP image isn't deferred.
 *  - `perf_image_dimensions`: inject intrinsic `width`/`height` on images that
 *    have neither, so the browser reserves space and Cumulative Layout Shift
 *    (CLS) drops. Dimensions are resolved cheaply — from the `wp-image-{id}`
 *    class (attachment metadata) or the WordPress `-WIDTHxHEIGHT` filename
 *    suffix — with no per-request filesystem reads.
 *
 * Operates via the `render_block` filter (scoped to aBlocks blocks) so no
 * per-block markup changes are needed.
 */
class ImageOptimizer {

	private $image_index = 0;
	private $eager_count = 1;
	private $do_lazy = false;
	private $do_dimensions = false;
	private $do_responsive = false;

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		$self = new self();
		$self->do_lazy = (bool) apply_filters(
			'ablocks/perf/perf_lazy_images',
			(bool) Helper::get_settings( 'perf_lazy_images', true )
		);
		$self->do_dimensions = (bool) apply_filters(
			'ablocks/perf/perf_image_dimensions',
			(bool) Helper::get_settings( 'perf_image_dimensions', true )
		);
		$self->do_responsive = (bool) apply_filters(
			'ablocks/perf/perf_responsive_images',
			(bool) Helper::get_settings( 'perf_responsive_images', true )
		);
		if ( ! $self->do_lazy && ! $self->do_dimensions && ! $self->do_responsive ) {
			return;
		}
		$self->eager_count = (int) apply_filters(
			'ablocks/perf/lcp_eager_count',
			(int) Helper::get_settings( 'perf_lcp_eager_count', 1 )
		);
		add_filter( 'render_block', [ $self, 'process' ], 20, 2 );
	}

	public function process( $content, $block ) {
		if ( empty( $block['blockName'] ) || false === strpos( $block['blockName'], 'ablocks' ) ) {
			return $content;
		}
		if ( false === strpos( $content, '<img' ) ) {
			return $content;
		}

		// aBlocks image blocks store the attachment id in their attributes but
		// don't emit a wp-image-{id} class, and URL→id lookups fail for
		// intermediate sizes of -scaled images. Use the block's own id as the
		// authoritative source for its image.
		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : [];
		$this->block_image_id = 0;
		foreach ( [ 'imgId', 'imgIdMobile', 'imgIdTablet' ] as $key ) {
			if ( ! empty( $attrs[ $key ] ) ) {
				$this->block_image_id = (int) $attrs[ $key ];
				break;
			}
		}

		return preg_replace_callback(
			'/<img\b[^>]*>/i',
			[ $this, 'rewrite_img' ],
			$content
		);
	}

	private function rewrite_img( $matches ) {
		$tag = $matches[0];
		$this->image_index++;
		$attrs = '';

		// Lazy-loading / priority hints.
		if ( $this->do_lazy ) {
			$has_loading = false !== stripos( $tag, 'loading=' );
			$is_eager    = $this->image_index <= $this->eager_count;

			if ( ! $has_loading ) {
				// No loading hint yet — add one (idempotent, never overrides markup
				// that set its own).
				$attrs .= $is_eager ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"';
				if ( false === stripos( $tag, 'decoding=' ) ) {
					$attrs .= ' decoding="async"';
				}
			} elseif ( $is_eager && false === stripos( $tag, 'fetchpriority=' ) ) {
				// Above-the-fold image that markup hardcoded as loading="lazy" (e.g.
				// aBlocks image save output) — upgrade it to eager + high priority so
				// the likely-LCP image isn't deferred.
				$tag = preg_replace(
					'/\bloading=(["\'])(?:lazy|auto)\1/i',
					'loading="eager" fetchpriority="high"',
					$tag,
					1
				);
			}
		}

		// CLS fix — reserve space by giving images that have neither width nor
		// height their intrinsic dimensions.
		if ( $this->do_dimensions
			&& false === stripos( $tag, 'width=' )
			&& false === stripos( $tag, 'height=' ) ) {
			$dim = $this->resolve_dimensions( $tag );
			if ( $dim ) {
				$attrs .= ' width="' . (int) $dim[0] . '" height="' . (int) $dim[1] . '"';
			}
		}

		// Responsive delivery — add a width-descriptor srcset + sizes so the
		// browser downloads a right-sized file instead of the full image (the
		// single biggest mobile payload win). Only when the image maps to an
		// attachment and doesn't already declare srcset.
		if ( $this->do_responsive && false === stripos( $tag, 'srcset=' ) ) {
			$attrs .= $this->responsive_attrs( $tag );
		}

		if ( '' === $attrs ) {
			return $tag;
		}
		return preg_replace( '/^<img\b/', '<img' . $attrs, $tag, 1 );
	}

	/**
	 * Build ` srcset="…" sizes="…"` for an image that maps to an attachment,
	 * using WordPress core's generators (which read the already-stored metadata,
	 * no filesystem work). Returns '' when the image can't be mapped or has no
	 * alternate sizes.
	 */
	private function responsive_attrs( $tag ) {
		$id = $this->resolve_attachment_id( $tag );
		if ( ! $id ) {
			return '';
		}
		$size = $this->resolve_dimensions( $tag );
		$size = $size ? $size : 'full';

		$srcset = wp_get_attachment_image_srcset( $id, $size );
		if ( ! $srcset ) {
			return '';
		}
		$sizes = wp_get_attachment_image_sizes( $id, $size );
		$out   = ' srcset="' . esc_attr( $srcset ) . '"';
		if ( $sizes && false === stripos( $tag, 'sizes=' ) ) {
			$out .= ' sizes="' . esc_attr( $sizes ) . '"';
		}
		return $out;
	}

	/**
	 * Resolve an image's intrinsic [width, height] without touching the
	 * filesystem: first from the attachment metadata (via the wp-image-{id}
	 * class, matching the exact rendered size), then from WordPress's
	 * `-WIDTHxHEIGHT` resized-filename convention. Returns null when unknown.
	 */
	private function resolve_dimensions( $tag ) {
		$src = $this->get_attr( $tag, 'src' );

		$id = $this->resolve_attachment_id( $tag );
		if ( $id ) {
			$dim = $this->dimensions_from_attachment( $id, $src );
			if ( $dim ) {
				return $dim;
			}
		}

		if ( $src && preg_match( '/-(\d+)x(\d+)\.(?:jpe?g|png|gif|webp|avif|bmp)(?:\?.*)?$/i', $src, $m ) ) {
			return [ (int) $m[1], (int) $m[2] ];
		}

		return null;
	}

	/**
	 * Resolve the attachment id for an <img>: prefer the WordPress `wp-image-{id}`
	 * class, otherwise map the src URL back to an attachment (aBlocks image blocks
	 * don't emit the class). URL lookups are cached per request — and only run
	 * when the class is absent — so the DB is hit at most once per unique URL.
	 */
	private $id_cache = [];
	private $block_image_id = 0;
	private function resolve_attachment_id( $tag ) {
		if ( preg_match( '/wp-image-(\d+)/', $tag, $m ) ) {
			return (int) $m[1];
		}
		// The current block's stored attachment id (authoritative for aBlocks
		// image blocks, which don't emit the class).
		if ( $this->block_image_id ) {
			return $this->block_image_id;
		}
		$src = $this->get_attr( $tag, 'src' );
		if ( ! $src ) {
			return 0;
		}
		if ( ! array_key_exists( $src, $this->id_cache ) ) {
			$this->id_cache[ $src ] = (int) attachment_url_to_postid( $src );
		}
		return $this->id_cache[ $src ];
	}

	/**
	 * Pull width/height from an attachment's stored metadata, preferring the
	 * registered sub-size whose file matches the rendered src, falling back to
	 * the full-size dimensions.
	 */
	private function dimensions_from_attachment( $id, $src ) {
		if ( ! $id ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $id );
		if ( ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			return null;
		}
		if ( $src && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$base = wp_basename( strtok( $src, '?' ) );
			foreach ( $meta['sizes'] as $size ) {
				if ( isset( $size['file'], $size['width'], $size['height'] ) && $size['file'] === $base ) {
					return [ (int) $size['width'], (int) $size['height'] ];
				}
			}
		}
		return [ (int) $meta['width'], (int) $meta['height'] ];
	}

	private function get_attr( $tag, $name ) {
		if ( preg_match( '/\b' . preg_quote( $name, '/' ) . '=(["\'])(.*?)\1/i', $tag, $m ) ) {
			return $m[2];
		}
		return '';
	}
}
