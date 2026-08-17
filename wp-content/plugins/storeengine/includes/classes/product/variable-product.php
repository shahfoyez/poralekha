<?php

namespace StoreEngine\Classes\Product;

use StoreEngine\classes\AbstractProduct;
use StoreEngine\Classes\Variation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VariableProduct extends AbstractProduct {

	/**
	 * @return Variation[]
	 */
	public function get_variants(): array {
		$cache_key = self::CACHE_KEY . $this->get_id() . '_variants';
		$has_cache = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT
					*
				FROM
					{$wpdb->prefix}storeengine_product_variations
				WHERE
					product_id = %d", $this->get_id()
		) );

		if ( ! $results ) {
			return [];
		}

		$variation_ids = wp_list_pluck( $results, 'id' );
		$placeholders  = array_fill( 0, count( $variation_ids ), '%d' );
		$placeholders  = implode( ',', $placeholders );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$variation_ids = $wpdb->prepare( "($placeholders)", $variation_ids );

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery -- prepared above.
		$variation_metadata_db = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}storeengine_product_variation_meta WHERE variation_id IN $variation_ids" );
		// phpcs:enabled PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery -- prepared above.

		$variation_metadata = [];
		foreach ( $variation_metadata_db as $variation_meta ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$variation_metadata[ (int) $variation_meta->variation_id ][ $variation_meta->meta_key ] = $variation_meta->meta_value;
		}

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$variation_attributes = $wpdb->get_results(
			"SELECT
						t.term_id AS term_id,
						tt.term_taxonomy_id AS term_taxonomy_id,
						pl.variation_id AS variation_id,
						t.name AS name,
						t.slug AS slug,
						tt.description AS description,
						tt.taxonomy AS taxonomy,
						tt.count AS count,
						pl.term_order AS term_order
					FROM
						{$wpdb->prefix}storeengine_variation_term_relations pl
						LEFT JOIN {$wpdb->terms} t ON t.term_id = pl.term_id
						LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = pl.term_id
					WHERE
						pl.variation_id IN $variation_ids
						AND t.term_id <> ''
					ORDER BY
						pl.term_order DESC"
		);
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( is_array( $variation_attributes ) ) {
			$variation_attributes_list = [];
			foreach ( $variation_attributes as $variation_attribute ) {
				$variation_attributes_list[ $variation_attribute->variation_id ][] = $variation_attribute;
			}

			foreach ( $results as $result ) {
				$result->attributes = $variation_attributes_list[ $result->id ] ?? [];
			}
		}

		$variants = [];

		foreach ( $results as $result ) {
			$variation = ( new Variation() )->set_data( $result );
			$variation->set_metadata( $variation_metadata[ (int) $result->id ] ?? [] );
			$variants[] = $variation;
		}

		wp_cache_set( $cache_key, $variants, self::CACHE_GROUP );

		return $variants;
	}

	/**
	 * Variants that should appear in the storefront picker + JS data feed.
	 *
	 * A variant counts as "available" when EITHER:
	 *   - its own `price` column is set (the per-variant increment), OR
	 *   - it carries a `_pricing_id` meta pointing at a price tier on
	 *     the parent product.
	 *
	 * Either is enough for the variant to be sellable — the cart resolves
	 * the final price downstream. Rows with neither are considered
	 * misconfigured and stay hidden.
	 *
	 * Stock status is NOT checked here; out-of-stock variants still need
	 * to be rendered so the customer can see them and (with the
	 * back-in-stock addon active) subscribe for restock alerts. The
	 * frontend gates Add-to-Cart based on the per-variant `is_in_stock`
	 * value published in the JS feed.
	 *
	 * @return Variation[]
	 */
	public function get_available_variants(): array {
		return array_values( array_filter(
			$this->get_variants(),
			function ( $v ) {
				$has_price    = null !== $v->get_price();
				$has_price_id = method_exists( $v, 'get_pricing_id' ) && (int) $v->get_pricing_id() > 0;
				return $has_price || $has_price_id;
			}
		) );
	}

	/**
	 * Returns true if any variation manages stock — in which case parent-level
	 * stock fields are ignored and stock is sourced from the variations.
	 */
	public function any_variant_manages_stock(): bool {
		foreach ( $this->get_variants() as $variant ) {
			if ( $variant->manages_stock() ) {
				return true;
			}
		}

		return false;
	}

	public function manages_stock(): bool {
		if ( $this->any_variant_manages_stock() ) {
			return true;
		}

		return parent::manages_stock();
	}

	public function is_in_stock(): bool {
		if ( $this->any_variant_manages_stock() ) {
			foreach ( $this->get_variants() as $variant ) {
				if ( $variant->is_in_stock() ) {
					return true;
				}
			}

			return false;
		}

		return parent::is_in_stock();
	}

	public function is_low_stock(): bool {
		if ( $this->any_variant_manages_stock() ) {
			foreach ( $this->get_variants() as $variant ) {
				if ( $variant->is_low_stock() ) {
					return true;
				}
			}

			return false;
		}

		return parent::is_low_stock();
	}

	public function get_stock_status(): string {
		if ( $this->any_variant_manages_stock() ) {
			$any_in_stock      = false;
			$any_on_backorder  = false;

			foreach ( $this->get_variants() as $variant ) {
				$status = $variant->get_stock_status();
				if ( 'instock' === $status ) {
					$any_in_stock = true;
				} elseif ( 'onbackorder' === $status ) {
					$any_on_backorder = true;
				}
			}

			if ( $any_in_stock ) {
				return 'instock';
			}
			if ( $any_on_backorder ) {
				return 'onbackorder';
			}

			return 'outofstock';
		}

		return parent::get_stock_status();
	}

	public function get_aggregate_stock_quantity(): ?int {
		if ( ! $this->any_variant_manages_stock() ) {
			return parent::get_stock_quantity();
		}

		$total = 0;
		$has   = false;

		foreach ( $this->get_variants() as $variant ) {
			if ( $variant->manages_stock() ) {
				$qty = $variant->get_stock_quantity();
				if ( null !== $qty ) {
					$total += $qty;
					$has    = true;
				}
			}
		}

		return $has ? $total : null;
	}
}
