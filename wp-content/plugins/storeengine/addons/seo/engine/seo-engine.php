<?php
/**
 * SeoEngine — computes the SEO "truth" once per request/object.
 *
 * All three output channels (standalone head, bridge adapters, REST/headless)
 * read from the SeoData this produces, so the title/description/canonical/
 * schema can never disagree across channels.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Engine;

use StoreEngine\Addons\Seo\Settings;
use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeoEngine {
	use Singleton;

	/**
	 * Per-request memo keyed by context signature.
	 *
	 * @var array<string,SeoData|null>
	 */
	protected array $cache = [];

	protected function settings(): Settings {
		return Settings::init();
	}

	/**
	 * Resolve the current request and compute its SEO data (standalone/bridge).
	 */
	public function for_request(): ?SeoData {
		$context = ContextResolver::resolve();

		switch ( $context['type'] ) {
			case ContextResolver::TYPE_PRODUCT:
				return $context['object'] instanceof AbstractProduct ? $this->for_product( $context['object'] ) : null;

			case ContextResolver::TYPE_CATEGORY:
			case ContextResolver::TYPE_TAG:
				return $context['object'] instanceof WP_Term ? $this->for_term( $context['object'] ) : null;

			case ContextResolver::TYPE_SHOP:
				return $this->for_shop();

			case ContextResolver::TYPE_CART:
			case ContextResolver::TYPE_CHECKOUT:
			case ContextResolver::TYPE_ACCOUNT:
			case ContextResolver::TYPE_THANKYOU:
				return $this->for_noindex_context( $context['type'] );

			default:
				return null;
		}
	}

	/**
	 * SEO data for a single product.
	 */
	public function for_product( AbstractProduct $product ): SeoData {
		$key = 'product:' . $product->get_id();
		if ( array_key_exists( $key, $this->cache ) ) {
			return $this->cache[ $key ];
		}

		$id   = $product->get_id();
		$data = new SeoData();

		$data->title       = $this->resolve_title( $id, $product->get_name() );
		$data->description = $this->resolve_description( $id, $product );
		$data->canonical   = $product->get_permalink() ?: '';

		$override_robots = get_post_meta( $id, '_storeengine_seo_robots', true );
		if ( 'noindex' === $override_robots ) {
			$data->robots = [ 'index' => false, 'follow' => true ];
		}

		$images       = SchemaBuilder::get_images( $product );
		$data->image  = $images[0] ?? $this->default_share_image();

		$data->og      = $this->build_og( $data, 'product' );
		$data->twitter = $this->build_twitter( $data );

		if ( $this->settings()->get_settings( 'schema_product_enabled', true ) ) {
			$data->schema[] = SchemaBuilder::build_product( $product, $data->description );
		}
		if ( $this->settings()->get_settings( 'schema_breadcrumb_enabled', true ) ) {
			$data->schema[] = SchemaBuilder::build_breadcrumb( $product );
		}

		/**
		 * Lets pro override per-product SEO (meta box values, AI titles,
		 * OG image generation) without the free engine depending on pro.
		 */
		$data = apply_filters( 'storeengine/seo/product_data', $data, $product );

		$this->cache[ $key ] = $data;

		return $data;
	}

	/**
	 * SEO data for a product category / tag archive.
	 */
	public function for_term( WP_Term $term ): SeoData {
		$key = 'term:' . $term->term_id;
		if ( array_key_exists( $key, $this->cache ) ) {
			return $this->cache[ $key ];
		}

		$data = new SeoData();

		$override_title = get_term_meta( $term->term_id, '_storeengine_seo_title', true );
		$data->title    = $override_title ?: ( $term->name . ' – ' . get_bloginfo( 'name' ) );

		$override_desc     = get_term_meta( $term->term_id, '_storeengine_seo_desc', true );
		$data->description = $override_desc ?: wp_trim_words( wp_strip_all_tags( $term->description ), 30 );

		$link              = get_term_link( $term );
		$data->canonical   = is_wp_error( $link ) ? '' : $link;
		$data->image       = $this->default_share_image();

		$data->og      = $this->build_og( $data, 'website' );
		$data->twitter = $this->build_twitter( $data );

		if ( $this->settings()->get_settings( 'schema_product_enabled', true ) ) {
			$data->schema[] = SchemaBuilder::build_collection_page( $term );
		}
		if ( $this->settings()->get_settings( 'schema_breadcrumb_enabled', true ) ) {
			$data->schema[] = SchemaBuilder::build_term_breadcrumb( $term );
		}

		$data = apply_filters( 'storeengine/seo/term_data', $data, $term );

		$this->cache[ $key ] = $data;

		return $data;
	}

	/**
	 * SEO data for the shop archive (post type archive, or a static page
	 * assigned as the shop page).
	 */
	public function for_shop(): SeoData {
		$key = 'shop';
		if ( array_key_exists( $key, $this->cache ) ) {
			return $this->cache[ $key ];
		}

		$data = new SeoData();

		$shop_page_id = (int) Helper::get_settings( 'shop_page' );
		$name         = $shop_page_id ? get_the_title( $shop_page_id ) : post_type_archive_title( '', false );
		$name         = $name ?: __( 'Shop', 'storeengine' );

		$data->title       = $name . ' – ' . get_bloginfo( 'name' );
		$data->description = $shop_page_id ? wp_trim_words( wp_strip_all_tags( get_the_excerpt( $shop_page_id ) ), 30 ) : '';
		$data->canonical   = get_post_type_archive_link( Helper::PRODUCT_POST_TYPE ) ?: '';
		$data->image       = $this->default_share_image();

		$data->og      = $this->build_og( $data, 'website' );
		$data->twitter = $this->build_twitter( $data );

		if ( $this->settings()->get_settings( 'schema_product_enabled', true ) ) {
			$data->schema[] = SchemaBuilder::build_shop_page( $name, $data->canonical, $data->description );
		}
		if ( $this->settings()->get_settings( 'schema_breadcrumb_enabled', true ) ) {
			$data->schema[] = SchemaBuilder::build_shop_breadcrumb( $name, $data->canonical );
		}

		$data = apply_filters( 'storeengine/seo/shop_data', $data );

		$this->cache[ $key ] = $data;

		return $data;
	}

	/**
	 * SEO data for a transactional page that should be kept out of the index.
	 */
	protected function for_noindex_context( string $type ): ?SeoData {
		$setting_map = [
			ContextResolver::TYPE_CART     => 'noindex_cart',
			ContextResolver::TYPE_CHECKOUT => 'noindex_checkout',
			ContextResolver::TYPE_ACCOUNT  => 'noindex_account',
			ContextResolver::TYPE_THANKYOU => 'noindex_thankyou',
		];

		$setting = $setting_map[ $type ] ?? '';
		if ( ! $setting || ! $this->settings()->get_settings( $setting, true ) ) {
			return null;
		}

		$data         = new SeoData();
		$data->robots = [ 'index' => false, 'follow' => true ];

		return $data;
	}

	protected function resolve_title( int $id, string $fallback ): string {
		$override = get_post_meta( $id, '_storeengine_seo_title', true );
		if ( $override ) {
			return $override;
		}

		return wp_strip_all_tags( $fallback ) . ' – ' . get_bloginfo( 'name' );
	}

	protected function resolve_description( int $id, AbstractProduct $product ): string {
		$override = get_post_meta( $id, '_storeengine_seo_desc', true );
		if ( $override ) {
			return $override;
		}

		$excerpt = get_the_excerpt( $id );
		if ( $excerpt ) {
			return wp_strip_all_tags( $excerpt );
		}

		return wp_trim_words( wp_strip_all_tags( $product->get_content() ), 30 );
	}

	protected function build_og( SeoData $data, string $type ): array {
		if ( ! $this->settings()->get_settings( 'og_enabled', true ) ) {
			return [];
		}

		$og = [
			'og:type'        => $type,
			'og:title'       => $data->title,
			'og:description' => $data->description,
			'og:url'         => $data->canonical,
			'og:site_name'   => get_bloginfo( 'name' ),
		];

		if ( $data->image ) {
			$og['og:image'] = $data->image;
		}

		return array_filter( $og );
	}

	protected function build_twitter( SeoData $data ): array {
		if ( ! $this->settings()->get_settings( 'og_enabled', true ) ) {
			return [];
		}

		$twitter = [
			'twitter:card'        => $this->settings()->get_settings( 'twitter_card_type', 'summary_large_image' ),
			'twitter:title'       => $data->title,
			'twitter:description' => $data->description,
		];

		$site = $this->settings()->get_settings( 'twitter_site', '' );
		if ( $site ) {
			$twitter['twitter:site'] = $site;
		}
		if ( $data->image ) {
			$twitter['twitter:image'] = $data->image;
		}

		return array_filter( $twitter );
	}

	protected function default_share_image(): string {
		$id = (int) $this->settings()->get_settings( 'default_share_image_id', 0 );
		if ( ! $id ) {
			return '';
		}

		return (string) ( wp_get_attachment_image_url( $id, 'full' ) ?: '' );
	}
}

// End of file seo-engine.php.
