<?php
/**
 * PromptLibrary — per-field instructions for product content generation.
 *
 * Central place that maps a product field + tone to the system instruction and
 * length constraint handed to {@see \StoreEngine\AI\AiService}. Keeping every
 * prompt here means copy can be tuned without touching the generator or REST.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PromptLibrary {

	/**
	 * Supported product fields.
	 */
	public static function fields(): array {
		return [ 'title', 'description', 'short_description', 'image_alt' ];
	}

	/**
	 * Human phrase describing a tone, woven into the instruction.
	 */
	protected static function tone_phrase( string $tone ): string {
		switch ( $tone ) {
			case 'friendly':
				return __( 'in a warm, friendly, conversational tone', 'storeengine' );
			case 'persuasive':
				return __( 'in a persuasive, benefit-driven sales tone', 'storeengine' );
			case 'minimal':
				return __( 'in a clean, minimal, matter-of-fact tone', 'storeengine' );
			case 'professional':
			default:
				return __( 'in a professional, trustworthy tone', 'storeengine' );
		}
	}

	/**
	 * Instruction + soft length cap for a field.
	 *
	 * @return array{system:string, max_chars:int}
	 */
	public static function for_field( string $field, string $tone = 'professional', string $language = '' ): array {
		$tone_phrase = self::tone_phrase( $tone );
		$lang        = $language ? sprintf( ' Write the response in %s.', $language ) : '';

		switch ( $field ) {
			case 'description':
				return [
					'system'    => sprintf(
						/* translators: %s: tone phrase. */
						__( 'Write a compelling product description %s. Use 2–3 short paragraphs of plain text (no markdown, no headings, no quotes). Focus on benefits and key features. Return only the description.', 'storeengine' ),
						$tone_phrase
					) . $lang,
					'max_chars' => 900,
				];

			case 'short_description':
				return [
					'system'    => sprintf(
						/* translators: %s: tone phrase. */
						__( 'Write a single-sentence product summary %s (max 160 characters, plain text, no quotes). Return only the summary.', 'storeengine' ),
						$tone_phrase
					) . $lang,
					'max_chars' => 160,
				];

			case 'image_alt':
				return [
					'system'    => __( 'Write concise, descriptive alt text for the main image of this product for accessibility and image SEO (max 120 characters, plain text, no quotes, do not start with "image of"). Return only the alt text.', 'storeengine' ) . $lang,
					'max_chars' => 120,
				];

			case 'title':
			default:
				return [
					'system'    => sprintf(
						/* translators: %s: tone phrase. */
						__( 'Write a single concise, click-worthy product title %s (max 70 characters, plain text, no quotes). Return only the title.', 'storeengine' ),
						$tone_phrase
					) . $lang,
					'max_chars' => 70,
				];
		}
	}
}

// End of file prompt-library.php.
