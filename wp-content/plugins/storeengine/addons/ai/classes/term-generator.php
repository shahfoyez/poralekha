<?php
/**
 * TermGenerator — AI-written description for a taxonomy term.
 *
 * Accepts the term name and taxonomy (no saved term id required, so this
 * works while the Create form is still open) and delegates to the shared
 * {@see \StoreEngine\AI\AiService}. Only `description` is supported.
 *
 * @since StoreEngine 1.10.0
 */

namespace StoreEngine\Addons\Ai\Classes;

use StoreEngine\AI\AiService;
use StoreEngine\Addons\Ai\Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TermGenerator {

	/**
	 * Generate a description for a taxonomy term.
	 *
	 * @param string $taxonomy  The WP taxonomy slug (e.g. storeengine_product_category).
	 * @param string $name      The term name/title to write copy for.
	 * @param array  $opts      { tone?:string, language?:string }
	 *
	 * @return string|WP_Error
	 */
	public static function generate( string $taxonomy, string $name, array $opts = [] ) {
		$name = trim( $name );
		if ( '' === $name ) {
			return new WP_Error( 'ai_term_no_name', __( 'Term name is required for generation.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$settings = Settings::init();
		$tone     = (string) ( $opts['tone'] ?? $settings->get_settings( 'default_tone', 'professional' ) );
		$language = (string) ( $opts['language'] ?? $settings->get_settings( 'default_language', '' ) );

		$taxonomy_label = self::taxonomy_label( $taxonomy );
		$tone_phrase    = self::tone_phrase( $tone );
		$lang           = $language ? sprintf( ' Write the response in %s.', $language ) : '';

		$system = sprintf(
			/* translators: 1: taxonomy label, 2: tone phrase. */
			__( 'Write a concise, informative description for a %1$s %2$s. Use 1–2 short paragraphs of plain text (no markdown, no headings, no quotes). Focus on what the %1$s covers and why it is useful to shoppers. Return only the description.', 'storeengine' ),
			$taxonomy_label,
			$tone_phrase
		) . $lang;

		$context = sprintf( '%s: %s', $taxonomy_label, $name );

		$model_preference = $settings->get_settings( 'model_preference', '' );

		return AiService::generate( $context, [
			'system'           => $system,
			'max_chars'        => 500,
			...( $model_preference ? [ 'temperature' => (float) $settings->get_settings( 'temperature', 0.7 ) ] : [] ),
			'model_preference' => $model_preference,
		] );
	}

	protected static function taxonomy_label( string $taxonomy ): string {
		$tax = get_taxonomy( $taxonomy );
		if ( $tax && ! empty( $tax->labels->singular_name ) ) {
			return strtolower( (string) $tax->labels->singular_name );
		}

		return __( 'category', 'storeengine' );
	}

	protected static function tone_phrase( string $tone ): string {
		switch ( $tone ) {
			case 'friendly':
				return __( 'in a warm, friendly tone', 'storeengine' );
			case 'persuasive':
				return __( 'in a persuasive, benefit-driven tone', 'storeengine' );
			case 'minimal':
				return __( 'in a clean, minimal tone', 'storeengine' );
			case 'professional':
			default:
				return __( 'in a professional, trustworthy tone', 'storeengine' );
		}
	}
}

// End of file TermGenerator.php.
