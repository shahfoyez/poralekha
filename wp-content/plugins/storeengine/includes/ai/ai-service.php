<?php
/**
 * AiService — the single StoreEngine wrapper around WordPress's native AI Client.
 *
 * StoreEngine NEVER stores its own provider key. AI credentials are configured
 * ONCE site-wide under Settings → Connectors (WP 7.0 Connectors API) and shared
 * by every plugin; this wrapper just talks to `WordPress\AiClient\AiClient`.
 *
 * Everything that generates content — the `ai` addon (free product copy), the
 * `seo` addon (meta), and the pro `ai` augmentation (bulk + variants) — routes
 * through here, so the AI surface lives in exactly one file. When WordPress 7.0
 * isn't present, or no provider is connected, every call returns a uniform
 * `WP_Error` and the UI degrades to a "Connect WordPress AI" prompt.
 *
 * The builder's advanced setters (system instruction, temperature, model
 * preference, JSON response) are applied defensively via method_exists() so a
 * naming drift in the (new) WP 7.0 AI Client can never fatal — text generation,
 * proven working in the seo addon, always succeeds; advanced knobs light up when
 * the corresponding builder methods are available.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\AI;

use Throwable;
use WordPress\AiClient\AiClient;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AiService {

	const CLIENT_CLASS = '\\WordPress\\AiClient\\AiClient';

	/* ---------------------------------------------------------- availability */

	/**
	 * The WP 7.0 AI Client class is present (i.e. running WordPress 7.0+).
	 */
	public static function is_supported(): bool {
		return class_exists( self::CLIENT_CLASS );
	}

	/**
	 * A provider connector capable of text generation is configured.
	 */
	public static function has_connector(): bool {
		if ( ! self::is_supported() ) {
			return false;
		}

		try {
			return (bool) AiClient::prompt( 'ping' )->isSupportedForTextGeneration();
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Supported AND connected — the only gate the UI/REST should check.
	 */
	public static function is_available(): bool {
		return self::has_connector();
	}

	/**
	 * Whether image generation is available (separate provider capability).
	 */
	public static function image_available(): bool {
		if ( ! self::is_supported() ) {
			return false;
		}

		try {
			$builder = AiClient::prompt( 'ping' );
			if ( method_exists( $builder, 'isSupportedForImageGeneration' ) ) {
				return (bool) $builder->isSupportedForImageGeneration();
			}
		} catch ( Throwable $e ) {
			return false;
		}

		return false;
	}

	/**
	 * Admin URL where the site connects an AI provider (WP 7.0 Connectors).
	 */
	public static function connectors_url(): string {
		return admin_url( 'options-connectors.php' );
	}

	/**
	 * Availability snapshot for the admin React surfaces (one REST call drives
	 * every "Generate" button's visibility).
	 */
	public static function status(): array {
		$supported = self::is_supported();
		$connected = $supported && self::has_connector();

		return [
			'supported'      => $supported,
			'has_connector'  => $connected,
			'available'      => $connected,
			'image_available' => $connected && self::image_available(),
			'connectors_url' => self::connectors_url(),
			'min_wp'         => '7.0',
		];
	}

	/* ------------------------------------------------------------- generation */

	/**
	 * Generate plain text.
	 *
	 * @param string $prompt
	 * @param array  $opts {
	 *     @type string       $system           System instruction.
	 *     @type float        $temperature      0.0–1.0.
	 *     @type string|array $model_preference Preferred model id(s).
	 *     @type int          $max_chars        Soft length hint folded into the prompt.
	 * }
	 *
	 * @return string|WP_Error
	 */
	public static function generate( string $prompt, array $opts = [] ) {
		if ( ! self::is_available() ) {
			return self::unavailable_error();
		}

		try {
			$builder = self::build( $prompt, $opts );
			$text    = trim( wp_strip_all_tags( (string) $builder->generateText() ) );

			if ( '' === $text ) {
				return new WP_Error( 'ai_empty', __( 'WordPress AI returned an empty result.', 'storeengine' ), [ 'status' => 502 ] );
			}

			return $text;
		} catch ( Throwable $e ) {
			return new WP_Error( 'ai_provider_error', $e->getMessage(), [ 'status' => 502 ] );
		}
	}

	/**
	 * Generate a structured (JSON) response validated against $schema.
	 *
	 * Uses the builder's native JSON response when available, otherwise instructs
	 * the model to reply with JSON and decodes the text — so structured output
	 * works regardless of the exact WP 7.0 builder surface.
	 *
	 * @param string $prompt
	 * @param array  $schema JSON schema (object).
	 * @param array  $opts   See {@see generate()}.
	 *
	 * @return array|WP_Error
	 */
	public static function generate_json( string $prompt, array $schema, array $opts = [] ) {
		if ( ! self::is_available() ) {
			return self::unavailable_error();
		}

		$opts['json_schema'] = $schema;

		try {
			$builder = self::build( $prompt, $opts );
			$raw     = (string) $builder->generateText();
			$decoded = self::decode_json( $raw );

			if ( null === $decoded ) {
				return new WP_Error( 'ai_bad_json', __( 'WordPress AI returned malformed JSON.', 'storeengine' ), [ 'status' => 502 ] );
			}

			return $decoded;
		} catch ( Throwable $e ) {
			return new WP_Error( 'ai_provider_error', $e->getMessage(), [ 'status' => 502 ] );
		}
	}

	/**
	 * Generate an image; returns the provider URL (or data) the model produced.
	 *
	 * @param string $prompt
	 * @param array  $opts
	 *
	 * @return string|WP_Error
	 */
	public static function generate_image( string $prompt, array $opts = [] ) {
		if ( ! self::image_available() ) {
			return self::unavailable_error();
		}

		try {
			$builder = self::build( $prompt, $opts );
			if ( ! method_exists( $builder, 'generateImage' ) ) {
				return self::unavailable_error();
			}

			$image = $builder->generateImage();

			return is_string( $image ) ? $image : (string) wp_json_encode( $image );
		} catch ( Throwable $e ) {
			return new WP_Error( 'ai_provider_error', $e->getMessage(), [ 'status' => 502 ] );
		}
	}

	/* ----------------------------------------------------------------- internals */

	/**
	 * Build a configured prompt builder. Length + JSON hints are folded into the
	 * prompt text (always honoured); temperature/model/system are applied through
	 * the fluent builder when those methods exist.
	 *
	 * @return object WordPress\AiClient prompt builder.
	 */
	private static function build( string $prompt, array $opts ) {
		$text = '';

		if ( ! empty( $opts['system'] ) ) {
			$text .= (string) $opts['system'] . "\n\n";
		}

		if ( ! empty( $opts['max_chars'] ) ) {
			$text .= sprintf(
				/* translators: %d: maximum character count. */
				__( 'Keep the response under %d characters. ', 'storeengine' ),
				(int) $opts['max_chars']
			);
		}

		if ( ! empty( $opts['json_schema'] ) ) {
			$text .= __( 'Respond with valid JSON only, matching this schema (no markdown, no commentary): ', 'storeengine' )
				. wp_json_encode( $opts['json_schema'] ) . "\n\n";
		}

		$text   .= $prompt;
		$builder = AiClient::prompt( $text );

		// Advanced knobs — applied only when the builder exposes them so a method
		// rename in the new WP 7.0 client cannot fatal core generation.
		if ( isset( $opts['system'] ) && '' !== $opts['system'] && method_exists( $builder, 'usingSystemInstruction' ) ) {
			$builder = $builder->usingSystemInstruction( (string) $opts['system'] );
		}
		if ( isset( $opts['temperature'] ) && method_exists( $builder, 'usingTemperature' ) ) {
			$builder = $builder->usingTemperature( (float) $opts['temperature'] );
		}
		if ( ! empty( $opts['model_preference'] ) && method_exists( $builder, 'usingModelPreference' ) ) {
			$builder = $builder->usingModelPreference( $opts['model_preference'] );
		}
		if ( ! empty( $opts['json_schema'] ) && method_exists( $builder, 'asJsonResponse' ) ) {
			$builder = $builder->asJsonResponse( $opts['json_schema'] );
		}

		return $builder;
	}

	/**
	 * Tolerant JSON decode — strips ```json fences the model may wrap around the
	 * payload, then decodes to an associative array.
	 *
	 * @return array|null
	 */
	private static function decode_json( string $raw ): ?array {
		$raw = trim( $raw );
		$raw = (string) preg_replace( '/^```(?:json)?|```$/m', '', $raw );
		$raw = trim( $raw );

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	private static function unavailable_error(): WP_Error {
		return new WP_Error(
			'ai_unavailable',
			__( 'Connect an AI provider under Settings → Connectors to use AI generation.', 'storeengine' ),
			[ 'status' => 400 ]
		);
	}
}

// End of file ai-service.php.
