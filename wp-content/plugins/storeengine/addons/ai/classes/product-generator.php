<?php
/**
 * ProductGenerator — turn a product into AI-written copy.
 *
 * Assembles product context (name, content, categories — the same shape the SEO
 * generator uses), picks the field instruction from {@see PromptLibrary}, and
 * defers to the shared {@see \StoreEngine\AI\AiService}. Used by the free REST
 * endpoint and reused verbatim by the pro bulk generator and write abilities.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai\Classes;

use StoreEngine\AI\AiService;
use StoreEngine\Addons\Ai\Settings;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductGenerator {

	/**
	 * Generate a single field for a product.
	 *
	 * @param int    $product_id
	 * @param string $field      title | description | short_description | image_alt
	 * @param array  $opts       { tone?:string, language?:string }
	 *
	 * @return string|WP_Error
	 */
	public static function generate( int $product_id, string $field, array $opts = [] ) {
		if ( ! in_array( $field, PromptLibrary::fields(), true ) ) {
			return new WP_Error( 'ai_bad_field', __( 'Unsupported field.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$product = Helper::get_product( $product_id );
		if ( ! $product || is_wp_error( $product ) ) {
			return new WP_Error( 'ai_no_product', __( 'Product not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$settings = Settings::init();
		$tone     = (string) ( $opts['tone'] ?? $settings->get_settings( 'default_tone', 'professional' ) );
		$language = (string) ( $opts['language'] ?? $settings->get_settings( 'default_language', '' ) );

		$instruction = PromptLibrary::for_field( $field, $tone, $language );
		$context     = self::build_context( $product_id, $product );

		return AiService::generate( $context, [
			'system'           => $instruction['system'],
			'max_chars'        => $instruction['max_chars'],
			...( $settings->get_settings( 'model_preference', '' ) ? ['temperature'      => (float) $settings->get_settings( 'temperature', 0.7 )] : [] ),
			'model_preference' => $settings->get_settings( 'model_preference', '' ),
		] );
	}

	/**
	 * Build the product context block the model reasons over.
	 *
	 * @param int   $product_id
	 * @param mixed $product    StoreEngine product object.
	 */
	protected static function build_context( int $product_id, $product ): string {
		$context = wp_strip_all_tags( (string) $product->get_name() );

		$content = wp_trim_words( wp_strip_all_tags( (string) $product->get_content() ), 120 );
		if ( $content ) {
			$context .= "\n\n" . $content;
		}

		$terms = get_the_terms( $product_id, Helper::PRODUCT_CATEGORY_TAXONOMY );
		if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
			$context .= "\n\nCategories: " . implode( ', ', wp_list_pluck( $terms, 'name' ) );
		}

		return "Product:\n" . $context;
	}
}

// End of file product-generator.php.
