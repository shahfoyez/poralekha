<?php
/**
 * StandaloneProvider — owns wp_head when no 3rd-party SEO plugin is active.
 *
 * Emits the title, meta description, canonical, robots, OG/Twitter and JSON-LD
 * for StoreEngine product / category / shop pages, and applies noindex to the
 * transactional pages.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Providers;

use StoreEngine\Addons\Seo\Output\HeadRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StandaloneProvider extends AbstractProvider {

	public static function is_active(): bool {
		// Fallback provider — always available.
		return true;
	}

	public function mode(): string {
		return 'standalone';
	}

	public function register(): void {
		// Override <title> for StoreEngine contexts.
		add_filter( 'pre_get_document_title', [ $this, 'filter_title' ], 20 );

		// Everything else into wp_head, early.
		add_action( 'wp_head', [ HeadRenderer::class, 'render' ], 1 );

		// Portable robots fallback (also covers themes that read wp_robots).
		$this->apply_core_robots();
	}

	public function filter_title( $title ) {
		$data = $this->current_data();

		return ( $data && '' !== $data->title ) ? $data->title : $title;
	}
}

// End of file standalone-provider.php.
