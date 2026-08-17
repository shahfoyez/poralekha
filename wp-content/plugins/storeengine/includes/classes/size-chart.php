<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Size chart resolution.
 *
 * Structurally this mirrors {@see Faq}: an admin-managed library of CPT entries,
 * each carrying display rules in post meta, resolved per product and cached
 * under a monotonic version key.
 *
 * It differs from FAQ in one deliberate way: FAQ groups *accumulate* (a product
 * shows every group that matches), while size charts resolve to exactly **one**
 * winner. Showing a shopper three conflicting measurement tables is worse than
 * showing one, so matches are scored by specificity and the highest wins.
 */
class SizeChart {

	const BRAND_TAXONOMY = 'storeengine_product_brand';

	/**
	 * Specificity scores. Highest match wins; ties break on menu_order then ID
	 * (get_all_charts() is already ordered that way, and we only replace the
	 * incumbent on a strictly greater score).
	 */
	const SCORE_PRODUCT        = 100;
	const SCORE_BRAND_CATEGORY = 40;
	const SCORE_BRAND          = 30;
	const SCORE_CATEGORY       = 20;
	const SCORE_ALL            = 10;

	/**
	 * Resolve the single size chart that applies to a product.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array{id:int,title:string,note:string,tables:array}|array Empty when nothing applies.
	 */
	public static function get_product_chart( int $product_id ): array {
		$cache_key = 'storeengine_size_chart_' . self::cache_version() . '_' . $product_id;
		$cached    = wp_cache_get( $cache_key, 'storeengine_size_charts' );

		if ( false !== $cached ) {
			return $cached;
		}

		$chart = self::resolve( $product_id );

		/**
		 * Filter a product's resolved size chart.
		 *
		 * @param array $chart      Resolved chart payload, or [] when none applies.
		 * @param int   $product_id Product id.
		 */
		$chart = apply_filters( 'storeengine/product_size_chart', $chart, $product_id );

		wp_cache_set( $cache_key, $chart, 'storeengine_size_charts', HOUR_IN_SECONDS );

		return $chart;
	}

	/**
	 * Score every published chart against the product and return the winner.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array
	 */
	protected static function resolve( int $product_id ): array {
		$category_ids = wp_get_post_terms( $product_id, Helper::PRODUCT_CATEGORY_TAXONOMY, [ 'fields' => 'ids' ] );
		$category_ids = is_wp_error( $category_ids ) ? [] : array_map( 'absint', $category_ids );

		// The brand taxonomy ships in an addon, so it may not exist.
		$brand_ids = [];
		if ( taxonomy_exists( self::BRAND_TAXONOMY ) ) {
			$brand_ids = wp_get_post_terms( $product_id, self::BRAND_TAXONOMY, [ 'fields' => 'ids' ] );
			$brand_ids = is_wp_error( $brand_ids ) ? [] : array_map( 'absint', $brand_ids );
		}

		$winner = null;
		$best   = 0;

		foreach ( self::get_all_charts() as $chart ) {
			$score = self::score( $chart->ID, $product_id, $category_ids, $brand_ids );

			if ( $score > $best ) {
				$best   = $score;
				$winner = $chart;
			}
		}

		if ( ! $winner ) {
			return [];
		}

		$tables = self::clean_tables( get_post_meta( $winner->ID, '_storeengine_size_tables', true ) );

		// A chart with no usable table is not worth a trigger button.
		if ( empty( $tables ) ) {
			return [];
		}

		return [
			'id'     => (int) $winner->ID,
			'title'  => $winner->post_title,
			'note'   => self::render_note( $winner ),
			'tables' => $tables,
		];
	}

	/**
	 * Render a chart's "how to measure" body (the post content).
	 *
	 * Runs the standard content pipeline so blocks, shortcodes and uploaded
	 * images all resolve. `the_content` is applied against a post that is never
	 * queried on its own, so there is no loop to disturb — but we skip the work
	 * entirely for an empty body, which is the common case.
	 *
	 * @param \WP_Post $chart Chart post.
	 *
	 * @return string
	 */
	protected static function render_note( \WP_Post $chart ): string {
		if ( '' === trim( (string) $chart->post_content ) ) {
			return '';
		}

		return (string) apply_filters( 'the_content', $chart->post_content );
	}

	/**
	 * Specificity score for one chart against one product. 0 means no match.
	 *
	 * Rule types combine with AND, values within a rule type with OR — so a
	 * chart set to brand "Nike" + category "Shoes" applies to Nike shoes only,
	 * not to every Nike product. An explicit product pick overrides everything.
	 *
	 * @param int   $chart_id     Chart post id.
	 * @param int   $product_id   Product id.
	 * @param int[] $category_ids Product's category term ids.
	 * @param int[] $brand_ids    Product's brand term ids.
	 *
	 * @return int
	 */
	protected static function score( int $chart_id, int $product_id, array $category_ids, array $brand_ids ): int {
		$chart_products = self::id_meta( $chart_id, '_storeengine_size_product_ids' );

		if ( in_array( $product_id, $chart_products, true ) ) {
			return self::SCORE_PRODUCT;
		}

		if ( get_post_meta( $chart_id, '_storeengine_size_apply_all', true ) ) {
			return self::SCORE_ALL;
		}

		$chart_categories = self::id_meta( $chart_id, '_storeengine_size_category_ids' );
		$chart_brands     = self::id_meta( $chart_id, '_storeengine_size_brand_ids' );

		$has_category_rule = ! empty( $chart_categories );
		$has_brand_rule    = ! empty( $chart_brands );

		// Not assigned to anything.
		if ( ! $has_category_rule && ! $has_brand_rule ) {
			return 0;
		}

		$category_ok = ! $has_category_rule || array_intersect( $category_ids, $chart_categories );
		$brand_ok    = ! $has_brand_rule || array_intersect( $brand_ids, $chart_brands );

		if ( ! $category_ok || ! $brand_ok ) {
			return 0;
		}

		if ( $has_category_rule && $has_brand_rule ) {
			return self::SCORE_BRAND_CATEGORY;
		}

		return $has_brand_rule ? self::SCORE_BRAND : self::SCORE_CATEGORY;
	}

	/**
	 * Read an id-list meta key as a clean array of positive ints.
	 *
	 * get_post_meta() returns '' for an unset key, and (array) '' is [''] —
	 * which absint()s to [0], an array that is *not* empty. Without filtering,
	 * a chart with no category rule would look like it had one, and the
	 * AND-across-rule-types check in score() would then reject every product.
	 *
	 * @param int    $chart_id Chart post id.
	 * @param string $key      Meta key.
	 *
	 * @return int[]
	 */
	protected static function id_meta( int $chart_id, string $key ): array {
		return array_values( array_filter( array_map( 'absint', (array) get_post_meta( $chart_id, $key, true ) ) ) );
	}

	/**
	 * All published charts, ordered by menu order then title.
	 *
	 * @return \WP_Post[]
	 */
	protected static function get_all_charts(): array {
		static $charts = null;

		if ( null === $charts ) {
			$charts = get_posts( [
				'post_type'        => Helper::SIZE_CHART_POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => 500,
				'orderby'          => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
				'suppress_filters' => false,
			] );
		}

		return $charts;
	}

	/**
	 * Normalise stored tables — drop empty rows/tables, coerce shape, and pad
	 * every row to the column count so the rendered <table> stays rectangular.
	 *
	 * @param mixed $tables Raw meta value.
	 *
	 * @return array<int,array{title:string,unit:string,columns:string[],rows:array}>
	 */
	protected static function clean_tables( $tables ): array {
		if ( ! is_array( $tables ) ) {
			return [];
		}

		$clean = [];

		foreach ( $tables as $table ) {
			if ( ! is_array( $table ) ) {
				continue;
			}

			$columns = array_values( array_filter(
				array_map( 'strval', (array) ( $table['columns'] ?? [] ) ),
				static fn ( $column ) => '' !== trim( $column )
			) );

			if ( empty( $columns ) ) {
				continue;
			}

			$rows = [];
			foreach ( (array) ( $table['rows'] ?? [] ) as $row ) {
				$row = array_map( 'strval', (array) $row );

				// Skip rows that are entirely blank.
				if ( ! array_filter( $row, static fn ( $cell ) => '' !== trim( $cell ) ) ) {
					continue;
				}

				$rows[] = array_slice( array_pad( $row, count( $columns ), '' ), 0, count( $columns ) );
			}

			if ( empty( $rows ) ) {
				continue;
			}

			$clean[] = [
				'title'   => (string) ( $table['title'] ?? '' ),
				'unit'    => (string) ( $table['unit'] ?? '' ),
				'columns' => $columns,
				'rows'    => $rows,
			];
		}

		return $clean;
	}

	/**
	 * Monotonic cache version — folded into every cache key so a single option
	 * bump invalidates all resolved charts at once, even under a persistent
	 * object cache.
	 */
	protected static function cache_version(): int {
		return (int) get_option( 'storeengine_size_chart_cache_ver', 1 );
	}

	/**
	 * Bust the resolved-chart cache. Called on any chart/product save.
	 */
	public static function flush_cache() {
		update_option( 'storeengine_size_chart_cache_ver', self::cache_version() + 1, false );
	}
}
