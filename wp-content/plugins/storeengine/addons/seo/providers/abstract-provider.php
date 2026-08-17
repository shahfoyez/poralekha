<?php
/**
 * AbstractProvider — shared helpers for every SEO provider.
 *
 * Notably provides the additive-JSON-LD fallback: any bridge adapter whose
 * 3rd-party plugin lacks a clean schema-merge filter can still ship commerce
 * schema by emitting its own <script type="application/ld+json"> on wp_head,
 * while leaving <title>/canonical/OG to the host plugin (never duplicated).
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Providers;

use StoreEngine\Addons\Seo\Engine\ContextResolver;
use StoreEngine\Addons\Seo\Engine\SeoData;
use StoreEngine\Addons\Seo\Engine\SeoEngine;
use StoreEngine\Classes\AbstractProduct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractProvider implements SeoProvider {

	public function mode(): string {
		return 'bridge';
	}

	protected function engine(): SeoEngine {
		return SeoEngine::init();
	}

	/**
	 * The computed SEO data for the current request, or null when not a
	 * StoreEngine context.
	 */
	protected function current_data(): ?SeoData {
		return $this->engine()->for_request();
	}

	/**
	 * The product being viewed, or null.
	 */
	protected function current_product(): ?AbstractProduct {
		$context = ContextResolver::resolve();

		return $context['object'] instanceof AbstractProduct ? $context['object'] : null;
	}

	/**
	 * Robots applied through WordPress core's portable `wp_robots` filter so
	 * transactional pages get noindex regardless of the host SEO plugin.
	 */
	public function apply_core_robots(): void {
		add_filter( 'wp_robots', [ $this, 'filter_core_robots' ], 20 );
	}

	public function filter_core_robots( array $robots ): array {
		$data = $this->current_data();
		if ( $data && ! $data->robots['index'] ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
		}

		return $robots;
	}

	/**
	 * Schema nodes for the current request, ready to merge into a host
	 * plugin's @graph. When $strip_context is true the redundant top-level
	 * `@context` is removed (a graph declares context once at the root).
	 *
	 * @return array<int,array>
	 */
	protected function schema_nodes( bool $strip_context = false ): array {
		$data = $this->current_data();
		if ( ! $data || empty( $data->schema ) ) {
			return [];
		}

		if ( ! $strip_context ) {
			return $data->schema;
		}

		return array_map( static function ( array $node ) {
			unset( $node['@context'] );
			return $node;
		}, $data->schema );
	}

	/**
	 * Additive-JSON-LD fallback: echo schema nodes directly. Used by the
	 * standalone provider and by bridge adapters that can't merge into the
	 * host plugin's graph.
	 */
	public function emit_jsonld(): void {
		$data = $this->current_data();
		if ( ! $data || empty( $data->schema ) ) {
			return;
		}

		foreach ( $data->schema as $node ) {
			// JSON_HEX_TAG | JSON_HEX_AMP escape <, > and & as \u00XX so a schema
			// value containing "</script>" can never break out of this raw-text
			// script element (slashes stay unescaped only for readable URLs).
			echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

// End of file abstract-provider.php.
