<?php
/**
 * SchemaBuilder — Product + BreadcrumbList JSON-LD from a StoreEngine product.
 *
 * This is the single highest-value piece of the SEO addon: it grafts the
 * commerce data (offers/price/availability, aggregateRating, sku, variants)
 * that generic SEO plugins never produce for `storeengine_product`, which is
 * what earns the price + star rich result in Google.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Engine;

use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Classes\Price;
use StoreEngine\Models\Product as ProductModel;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SchemaBuilder {

	/**
	 * Map a StoreEngine stock status to a schema.org availability URL.
	 */
	protected static function availability( string $status ): string {
		switch ( $status ) {
			case 'onbackorder':
				return 'https://schema.org/BackOrder';
			case 'outofstock':
				return 'https://schema.org/OutOfStock';
			case 'instock':
			default:
				return 'https://schema.org/InStock';
		}
	}

	/**
	 * Collect product image URLs (featured first, then gallery).
	 *
	 * @return string[]
	 */
	public static function get_images( AbstractProduct $product ): array {
		$images = [];

		$thumb_id = get_post_thumbnail_id( $product->get_id() );
		if ( $thumb_id ) {
			$url = wp_get_attachment_image_url( $thumb_id, 'full' );
			if ( $url ) {
				$images[] = $url;
			}
		}

		foreach ( $product->get_product_gallery() as $gallery_id ) {
			$url = wp_get_attachment_image_url( absint( $gallery_id ), 'full' );
			if ( $url ) {
				$images[] = $url;
			}
		}

		return array_values( array_unique( array_filter( $images ) ) );
	}

	/**
	 * Build the `offers` node from a product's price rows.
	 *
	 * Returns a single Offer when there's one price, an AggregateOffer when
	 * there are several, or null when the product has no purchasable price.
	 *
	 * @return array|null
	 */
	protected static function build_offers( AbstractProduct $product ): ?array {
		$prices = $product->get_prices();
		if ( empty( $prices ) ) {
			return null;
		}

		$currency     = Formatting::get_currency();
		$url          = $product->get_permalink() ?: '';
		$availability = self::availability( $product->get_stock_status() );

		$amounts = [];
		foreach ( $prices as $price ) {
			if ( $price instanceof Price ) {
				$amounts[] = (float) $price->get_price();
			}
		}
		$amounts = array_values( array_filter( $amounts, fn( $a ) => $a > 0 ) );

		if ( empty( $amounts ) ) {
			return null;
		}

		if ( 1 === count( $amounts ) ) {
			return [
				'@type'         => 'Offer',
				'priceCurrency' => $currency,
				'price'         => self::format_number( $amounts[0] ),
				'availability'  => $availability,
				'url'           => $url,
			];
		}

		return [
			'@type'         => 'AggregateOffer',
			'priceCurrency' => $currency,
			'lowPrice'      => self::format_number( min( $amounts ) ),
			'highPrice'     => self::format_number( max( $amounts ) ),
			'offerCount'    => count( $amounts ),
			'availability'  => $availability,
			'url'           => $url,
		];
	}

	protected static function format_number( float $amount ): string {
		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * AggregateRating node, or null when the product has no approved reviews.
	 * Google rejects an AggregateRating with a zero count, so the node is
	 * omitted entirely rather than emitted with ratingValue:0.
	 */
	protected static function build_rating( AbstractProduct $product ): ?array {
		$rating = ProductModel::get_product_rating( $product->get_id() );

		if ( empty( $rating->rating_count ) ) {
			return null;
		}

		return [
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) $rating->rating_avg,
			'reviewCount' => (int) $rating->rating_count,
			'bestRating'  => '5',
			'worstRating' => '1',
		];
	}

	/**
	 * Per-variant nodes for a variable product (schema.org hasVariant).
	 *
	 * @return array<int,array>
	 */
	protected static function build_variants( AbstractProduct $product, string $currency ): array {
		if ( ! method_exists( $product, 'get_variants' ) ) {
			return [];
		}

		$nodes = [];
		foreach ( (array) $product->get_variants() as $variant ) {
			$node = [
				'@type' => 'Product',
				'name'  => $variant->get_name(),
			];

			$sku = $variant->get_sku();
			if ( $sku ) {
				$node['sku'] = (string) $sku;
			}

			$price = $variant->get_price();
			if ( null !== $price && (float) $price > 0 ) {
				$node['offers'] = [
					'@type'         => 'Offer',
					'priceCurrency' => $currency,
					'price'         => self::format_number( (float) $price ),
					'availability'  => self::availability( $variant->get_stock_status() ),
				];
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * The full Product schema node.
	 */
	public static function build_product( AbstractProduct $product, string $description = '' ): array {
		$currency = Formatting::get_currency();

		$node = [
			'@context' => 'https://schema.org/',
			'@type'    => 'Product',
			'name'     => wp_strip_all_tags( $product->get_name() ),
			'url'      => $product->get_permalink() ?: '',
		];

		if ( $description ) {
			$node['description'] = $description;
		}

		$images = self::get_images( $product );
		if ( $images ) {
			$node['image'] = $images;
		}

		// SKU: simple products store it in postmeta; variable products carry
		// it per-variant under hasVariant.
		if ( 'simple' === $product->get_type() ) {
			$sku = (string) get_post_meta( $product->get_id(), '_storeengine_sku', true );
			if ( $sku ) {
				$node['sku'] = $sku;
			}
		}

		// Brand: no brand taxonomy exists yet, so fall back to the configured
		// organization name when set.
		$brand = apply_filters( 'storeengine/seo/product_brand', '', $product );
		if ( $brand ) {
			$node['brand'] = [
				'@type' => 'Brand',
				'name'  => $brand,
			];
		}

		$offers = self::build_offers( $product );
		if ( $offers ) {
			$node['offers'] = $offers;
		}

		$rating = self::build_rating( $product );
		if ( $rating ) {
			$node['aggregateRating'] = $rating;
		}

		$variants = self::build_variants( $product, $currency );
		if ( $variants ) {
			$node['hasVariant'] = $variants;
		}

		return apply_filters( 'storeengine/seo/product_schema', $node, $product );
	}

	/**
	 * BreadcrumbList: Home → Shop → primary category → product.
	 */
	public static function build_breadcrumb( AbstractProduct $product ): array {
		$items     = [];
		$position  = 1;

		$items[] = self::crumb( $position ++, __( 'Home', 'storeengine' ), home_url( '/' ) );

		$shop_url = get_post_type_archive_link( Helper::PRODUCT_POST_TYPE );
		if ( $shop_url ) {
			$items[] = self::crumb( $position ++, __( 'Shop', 'storeengine' ), $shop_url );
		}

		$terms = get_the_terms( $product->get_id(), Helper::PRODUCT_CATEGORY_TAXONOMY );
		if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
			$primary = reset( $terms );
			$link    = get_term_link( $primary );
			if ( ! is_wp_error( $link ) ) {
				$items[] = self::crumb( $position ++, $primary->name, $link );
			}
		}

		$items[] = self::crumb( $position, wp_strip_all_tags( $product->get_name() ), $product->get_permalink() ?: '' );

		return [
			'@context'        => 'https://schema.org/',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		];
	}

	/**
	 * CollectionPage node for a product category / tag archive.
	 */
	public static function build_collection_page( \WP_Term $term ): array {
		$link = get_term_link( $term );

		$node = [
			'@context' => 'https://schema.org/',
			'@type'    => 'CollectionPage',
			'name'     => $term->name,
			'url'      => is_wp_error( $link ) ? '' : $link,
		];

		if ( $term->description ) {
			$node['description'] = wp_strip_all_tags( $term->description );
		}
		if ( $term->count ) {
			$node['numberOfItems'] = (int) $term->count;
		}

		return apply_filters( 'storeengine/seo/term_schema', $node, $term );
	}

	/**
	 * CollectionPage node for the shop archive itself.
	 */
	public static function build_shop_page( string $name, string $url, string $description = '' ): array {
		$node = [
			'@context' => 'https://schema.org/',
			'@type'    => 'CollectionPage',
			'name'     => $name,
			'url'      => $url,
		];

		if ( $description ) {
			$node['description'] = $description;
		}

		$count = wp_count_posts( Helper::PRODUCT_POST_TYPE )->publish ?? 0;
		if ( $count ) {
			$node['numberOfItems'] = (int) $count;
		}

		return apply_filters( 'storeengine/seo/shop_schema', $node );
	}

	/**
	 * BreadcrumbList for the shop archive: Home → Shop.
	 */
	public static function build_shop_breadcrumb( string $name, string $url ): array {
		return [
			'@context'        => 'https://schema.org/',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => [
				self::crumb( 1, __( 'Home', 'storeengine' ), home_url( '/' ) ),
				self::crumb( 2, $name, $url ),
			],
		];
	}

	/**
	 * BreadcrumbList for a category / tag archive: Home → Shop → term.
	 */
	public static function build_term_breadcrumb( \WP_Term $term ): array {
		$items    = [];
		$position = 1;

		$items[] = self::crumb( $position ++, __( 'Home', 'storeengine' ), home_url( '/' ) );

		$shop_url = get_post_type_archive_link( Helper::PRODUCT_POST_TYPE );
		if ( $shop_url ) {
			$items[] = self::crumb( $position ++, __( 'Shop', 'storeengine' ), $shop_url );
		}

		// Ancestor terms (hierarchical categories) in order.
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			$link     = $ancestor ? get_term_link( $ancestor ) : '';
			if ( $ancestor && ! is_wp_error( $link ) ) {
				$items[] = self::crumb( $position ++, $ancestor->name, $link );
			}
		}

		$link    = get_term_link( $term );
		$items[] = self::crumb( $position, $term->name, is_wp_error( $link ) ? '' : $link );

		return [
			'@context'        => 'https://schema.org/',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		];
	}

	protected static function crumb( int $position, string $name, string $url ): array {
		return [
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $name,
			'item'     => $url,
		];
	}
}

// End of file schema-builder.php.
