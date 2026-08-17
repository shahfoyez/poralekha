<?php
/**
 * RankMathProvider — bridge into Rank Math.
 *
 * Grafts our Product + BreadcrumbList nodes into Rank Math's JSON-LD output
 * and forces noindex on transactional pages. Rank Math keeps owning
 * <title>/canonical/OG.
 *
 * Confirmed-stable filter: `rank_math/json_ld`.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RankMathProvider extends AbstractProvider {

	public static function is_active(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath' );
	}

	public function register(): void {
		add_filter( 'rank_math/json_ld', [ $this, 'graft_schema' ], 99, 2 );

		// Rank Math owns robots; force noindex on transactional pages.
		add_filter( 'rank_math/frontend/robots', [ $this, 'filter_robots' ], 20 );
	}

	/**
	 * @param array $data   Rank Math JSON-LD pieces, keyed.
	 * @param mixed $jsonld Rank Math JsonLD instance (unused).
	 *
	 * @return array
	 */
	public function graft_schema( $data, $jsonld = null ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$nodes = $this->schema_nodes( true );
		$i     = 0;
		foreach ( $nodes as $node ) {
			$key          = 'storeengine_' . ( $node['@type'] ?? 'node' ) . '_' . $i ++;
			$data[ $key ] = $node;
		}

		return $data;
	}

	/**
	 * @param array $robots Rank Math robots directives.
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

// End of file rank-math-provider.php.
