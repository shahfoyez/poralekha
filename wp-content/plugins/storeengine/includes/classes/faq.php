<?php
/**
 * FAQ resolution for products.
 *
 * A product's rendered FAQ is the merge of:
 *   1. its own inline Q&A                       (product meta _storeengine_product_faqs)
 *   2. groups whose rules match its categories  (group meta _storeengine_faq_category_ids)
 *   3. groups whose rules match its brands       (_storeengine_faq_brand_ids)
 *   4. groups whose rules target it directly     (_storeengine_faq_product_ids)
 *   5. groups flagged store-wide                  (_storeengine_faq_apply_all)
 *
 * @package StoreEngine\Classes
 */

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Faq {

	const BRAND_TAXONOMY = 'storeengine_product_brand';

	/**
	 * Resolve the grouped FAQ list for a product.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array<int,array{id:int,title:string,items:array<int,array{question:string,answer:string}>}>
	 */
	public static function get_product_faqs( int $product_id ): array {
		$cache_key = 'storeengine_faq_' . self::cache_version() . '_' . $product_id;
		$cached    = wp_cache_get( $cache_key, 'storeengine_faqs' );

		if ( false !== $cached ) {
			return $cached;
		}

		$product = Helper::get_product( $product_id );

		if ( ! $product ) {
			return [];
		}

		$groups = [];

		// 1. Inline product Q&A -> a title-less leading group.
		$inline = self::clean_items( $product->get_faqs() );
		if ( ! empty( $inline ) ) {
			$groups[] = [ 'id' => 0, 'title' => '', 'items' => $inline ];
		}

		// In "product only" mode the group library is disabled, so groups never
		// render on the storefront even if a product still carries saved
		// attachments/rules. Only the product's own inline Q&A shows.
		if ( 'product_only' === Helper::get_settings( 'faq_mode', 'global' ) ) {
			$groups = apply_filters( 'storeengine/product_faq_groups', $groups, $product_id );
			wp_cache_set( $cache_key, $groups, 'storeengine_faqs', HOUR_IN_SECONDS );

			return $groups;
		}

		// Groups matched by their display rules (all / category / brand / product).
		$category_id = wp_get_post_terms( $product_id, Helper::PRODUCT_CATEGORY_TAXONOMY, [ 'fields' => 'ids' ] );
		$category_id = is_wp_error( $category_id ) ? [] : array_map( 'absint', $category_id );

		$brand_ids = [];
		if ( taxonomy_exists( self::BRAND_TAXONOMY ) ) {
			$brand_ids = wp_get_post_terms( $product_id, self::BRAND_TAXONOMY, [ 'fields' => 'ids' ] );
			$brand_ids = is_wp_error( $brand_ids ) ? [] : array_map( 'absint', $brand_ids );
		}

		foreach ( self::get_all_groups() as $group ) {
			$apply_all    = (bool) get_post_meta( $group->ID, '_storeengine_faq_apply_all', true );
			$group_cats   = array_map( 'absint', (array) get_post_meta( $group->ID, '_storeengine_faq_category_ids', true ) );
			$group_brands = array_map( 'absint', (array) get_post_meta( $group->ID, '_storeengine_faq_brand_ids', true ) );
			$group_prods  = array_map( 'absint', (array) get_post_meta( $group->ID, '_storeengine_faq_product_ids', true ) );

			$matches = $apply_all
				|| in_array( $product_id, $group_prods, true )
				|| array_intersect( $category_id, $group_cats )
				|| array_intersect( $brand_ids, $group_brands );

			if ( ! $matches ) {
				continue;
			}

			$items = self::clean_items( get_post_meta( $group->ID, '_storeengine_faq_items', true ) );
			if ( empty( $items ) ) {
				continue;
			}

			$groups[] = [
				'id'    => (int) $group->ID,
				'title' => $group->post_title,
				'items' => $items,
			];
		}

		/**
		 * Filter a product's resolved FAQ groups.
		 *
		 * @param array $groups     Resolved groups.
		 * @param int   $product_id Product id.
		 */
		$groups = apply_filters( 'storeengine/product_faq_groups', $groups, $product_id );

		wp_cache_set( $cache_key, $groups, 'storeengine_faqs', HOUR_IN_SECONDS );

		return $groups;
	}

	/**
	 * All published FAQ groups, ordered by menu order then title.
	 *
	 * @return \WP_Post[]
	 */
	protected static function get_all_groups(): array {
		static $groups = null;

		if ( null === $groups ) {
			$groups = get_posts( [
				'post_type'        => Helper::FAQ_POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => 500,
				'orderby'          => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
				'suppress_filters' => false,
			] );
		}

		return $groups;
	}

	/**
	 * Normalise a stored items array — drop empty rows, coerce shape.
	 *
	 * @param mixed $items Raw meta value.
	 *
	 * @return array<int,array{question:string,answer:string}>
	 */
	protected static function clean_items( $items ): array {
		if ( ! is_array( $items ) ) {
			return [];
		}

		$clean = [];
		foreach ( $items as $item ) {
			$question = trim( (string) ( $item['question'] ?? '' ) );
			$answer   = (string) ( $item['answer'] ?? '' );

			if ( '' === $question && '' === trim( $answer ) ) {
				continue;
			}

			$clean[] = [ 'question' => $question, 'answer' => $answer ];
		}

		return $clean;
	}

	/**
	 * Monotonic cache version — folded into every cache key so a single option
	 * bump invalidates all resolved FAQs at once, even under a persistent object
	 * cache (where flushing a single group can be a no-op).
	 */
	protected static function cache_version(): int {
		return (int) get_option( 'storeengine_faq_cache_ver', 1 );
	}

	/**
	 * Bust the resolved-FAQ cache. Called broadly on any group/product save.
	 */
	public static function flush_cache() {
		update_option( 'storeengine_faq_cache_ver', self::cache_version() + 1, false );
	}
}
