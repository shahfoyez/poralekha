<?php
/**
 * YoastProvider — bridge into Yoast SEO.
 *
 * Yoast already owns <title>/canonical/OG and emits a generic WebPage graph
 * for the `storeengine_product` CPT, but it has no idea about StoreEngine's
 * price / stock / review data, so it never produces a Product rich result.
 * We graft our Product + BreadcrumbList nodes into Yoast's @graph and let
 * Yoast keep owning everything else.
 *
 * Confirmed-stable filter: `wpseo_schema_graph`.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YoastProvider extends AbstractProvider {

	public static function is_active(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	public function register(): void {
		add_filter( 'wpseo_schema_graph', [ $this, 'graft_schema' ], 11, 2 );

		// Yoast owns robots output; force noindex on transactional pages.
		add_filter( 'wpseo_robots_array', [ $this, 'filter_robots' ], 20 );
	}

	/**
	 * Append our commerce schema nodes to Yoast's graph.
	 *
	 * @param array $graph   Array of graph pieces.
	 * @param mixed $context Yoast meta-tags context (unused).
	 *
	 * @return array
	 */
	public function graft_schema( $graph, $context = null ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		$nodes = $this->schema_nodes( true );
		foreach ( $nodes as $node ) {
			$graph[] = $node;
		}

		return $graph;
	}

	/**
	 * @param array $robots Yoast robots directives (e.g. ['index'=>'index']).
	 *
	 * @return array
	 */
	public function filter_robots( $robots ) {
		$data = $this->current_data();
		if ( is_array( $robots ) && $data && ! $data->robots['index'] ) {
			$robots['index'] = 'noindex';
		}

		return $robots;
	}
}

// End of file yoast-provider.php.
