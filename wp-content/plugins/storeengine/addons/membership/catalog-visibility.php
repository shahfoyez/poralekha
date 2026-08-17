<?php
/**
 * Keep membership's auto-created products out of the shop catalog.
 *
 * When an access group is given a "custom" price, StoreEngine auto-spawns a
 * hidden digital product to carry it (IntegrationTrait::create_product). Those
 * products are an implementation detail of the membership — they shouldn't show
 * up as buyable items in the shop/archive alongside real products. We identify
 * them by the `_storeengine_integration_product` stamp (set only on
 * auto-created products, never on a merchant's predefined product) and exclude
 * them from front-end product-archive queries. Their own single page still
 * works — the membership restriction/pricing UI drives purchases from there.
 *
 * @package StoreEngine\Addons\Membership
 */

namespace StoreEngine\Addons\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CatalogVisibility {

	const STAMP_META = '_storeengine_integration_product';

	public static function init() {
		if ( is_admin() ) {
			return;
		}

		$self = new self();
		add_action( 'pre_get_posts', [ $self, 'hide_from_catalog' ] );
	}

	public function hide_from_catalog( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		// Only the shop / product post-type archive — never a single product
		// page (its own query is also post_type=storeengine_product, and
		// filtering there would 404 the membership product's page).
		if ( $query->is_singular() || ! $query->is_post_type_archive( 'storeengine_product' ) ) {
			return;
		}

		/**
		 * Allow a site to keep membership products visible in the shop.
		 *
		 * @param bool $hide Default true.
		 */
		if ( ! apply_filters( 'storeengine/membership/hide_products_from_catalog', true ) ) {
			return;
		}

		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = [
			'relation' => 'OR',
			[
				'key'     => self::STAMP_META,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => self::STAMP_META,
				'value'   => 'storeengine/membership-addon',
				'compare' => '!=',
			],
		];
		$query->set( 'meta_query', $meta_query );
	}
}
