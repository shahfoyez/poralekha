<?php
/**
 * SeoGenerator — AI meta generation via the WordPress core AI client.
 *
 * Uses `WordPress\AiClient\AiClient` (WP 7.0+) exclusively, so AI credentials
 * are configured ONCE site-wide under Settings → Connectors and shared across
 * every plugin — StoreEngine never asks for its own key. When no provider is
 * connected, generation reports an error pointing the user to Connectors.
 *
 * @since StoreEngine Pro 1.0.0
 */

namespace StoreEngine\Addons\Seo\Classes;

use StoreEngine\AI\AiService;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeoGenerator {

	/**
	 * Whether the core AI client is present AND has a connected provider that
	 * can generate text. Delegates to the shared {@see AiService} so the AI
	 * surface lives in one place.
	 */
	public static function core_ai_available(): bool {
		return AiService::is_available();
	}

	public static function is_available(): bool {
		return AiService::is_available();
	}

	/**
	 * Admin URL where the site connects an AI provider (WP 7.0 Connectors).
	 */
	public static function settings_url(): string {
		return AiService::connectors_url();
	}

	/**
	 * Generate an SEO field for a product.
	 *
	 * @param int    $product_id
	 * @param string $type        title | description | keywords | og_title | og_description
	 *
	 * @return string|WP_Error
	 */
	public static function generate( int $product_id, string $type = 'title' ) {
		$product = Helper::get_product( $product_id );
		if ( ! $product || is_wp_error( $product ) ) {
			return new WP_Error( 'seo_ai_no_product', __( 'Product not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$context = wp_strip_all_tags( $product->get_name() ) . "\n\n" . wp_trim_words( wp_strip_all_tags( $product->get_content() ), 120 );
		$terms   = get_the_terms( $product_id, Helper::PRODUCT_CATEGORY_TAXONOMY );
		if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
			$context .= "\n\nCategories: " . implode( ', ', wp_list_pluck( $terms, 'name' ) );
		}

		[ $instruction, $max_chars ] = self::instruction_for( $type );

		return AiService::generate( "Product:\n" . $context, [
			'system'    => $instruction,
			'max_chars' => $max_chars,
		] );
	}

	/**
	 * Instruction + soft length cap per SEO field.
	 *
	 * @return array{0:string, 1:int}
	 */
	protected static function instruction_for( string $type ): array {
		switch ( $type ) {
			case 'description':
				return [ 'Write a single compelling SEO meta description (max 155 characters, plain text, no quotes) for this product. Return only the description.', 155 ];
			case 'keywords':
				return [ 'List 5-8 comma-separated SEO focus keywords for this product. Return only the comma-separated list.', 160 ];
			case 'og_title':
				return [ 'Write a single engaging Open Graph share title (max 60 characters, plain text, no quotes) for this product. Return only the title.', 60 ];
			case 'og_description':
				return [ 'Write a single engaging Open Graph share description (max 200 characters, plain text, no quotes) for this product. Return only the description.', 200 ];
			case 'title':
			default:
				return [ 'Write a single concise, click-worthy SEO title (max 60 characters, plain text, no quotes) for this product. Return only the title.', 60 ];
		}
	}
}
