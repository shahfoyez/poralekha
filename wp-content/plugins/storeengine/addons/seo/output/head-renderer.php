<?php
/**
 * HeadRenderer — echoes meta/OG/Twitter/JSON-LD into wp_head (standalone mode).
 *
 * Only attached when no 3rd-party SEO plugin is active, so there is nothing to
 * conflict with — StoreEngine emits no other head SEO output today.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Output;

use StoreEngine\Addons\Seo\Engine\SeoData;
use StoreEngine\Addons\Seo\Engine\SeoEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HeadRenderer {

	public static function render(): void {
		$data = SeoEngine::init()->for_request();
		if ( ! $data ) {
			return;
		}

		echo "\n<!-- StoreEngine SEO -->\n";

		self::meta_description( $data );
		self::canonical( $data );
		self::open_graph( $data );
		self::twitter( $data );
		self::json_ld( $data );

		echo "<!-- /StoreEngine SEO -->\n";
	}

	protected static function meta_description( SeoData $data ): void {
		if ( '' === $data->description ) {
			return;
		}
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $data->description ) );
	}

	protected static function canonical( SeoData $data ): void {
		if ( '' === $data->canonical ) {
			return;
		}
		printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $data->canonical ) );
	}

	protected static function open_graph( SeoData $data ): void {
		foreach ( $data->og as $property => $content ) {
			printf( '<meta property="%s" content="%s" />' . "\n", esc_attr( $property ), esc_attr( $content ) );
		}
	}

	protected static function twitter( SeoData $data ): void {
		foreach ( $data->twitter as $name => $content ) {
			printf( '<meta name="%s" content="%s" />' . "\n", esc_attr( $name ), esc_attr( $content ) );
		}
	}

	protected static function json_ld( SeoData $data ): void {
		foreach ( $data->schema as $node ) {
			// JSON_HEX_TAG | JSON_HEX_AMP escape <, > and & as \u00XX so a schema
			// value containing "</script>" can never break out of this raw-text
			// script element (slashes stay unescaped only for readable URLs).
			echo '<script type="application/ld+json">' . wp_json_encode( $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

// End of file head-renderer.php.
