<?php
/**
 * ContextResolver — classify the current request for the SEO engine.
 *
 * Uses StoreEngine's own conditional tags (Helper::is_*) so cart / checkout /
 * account / order-received pages are correctly identified even though they are
 * ordinary pages chosen in settings, not a CPT.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Engine;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ContextResolver {

	const TYPE_PRODUCT  = 'product';
	const TYPE_CATEGORY = 'category';
	const TYPE_TAG      = 'tag';
	const TYPE_SHOP     = 'shop';
	const TYPE_CART     = 'cart';
	const TYPE_CHECKOUT = 'checkout';
	const TYPE_ACCOUNT  = 'account';
	const TYPE_THANKYOU = 'thankyou';
	const TYPE_NONE     = 'none';

	/**
	 * @return array{type:string,object:mixed}
	 */
	public static function resolve(): array {
		// Transactional pages first — these decide noindex.
		if ( Helper::is_thank_you() ) {
			return [ 'type' => self::TYPE_THANKYOU, 'object' => null ];
		}
		if ( Helper::is_checkout() ) {
			return [ 'type' => self::TYPE_CHECKOUT, 'object' => null ];
		}
		if ( Helper::is_cart() ) {
			return [ 'type' => self::TYPE_CART, 'object' => null ];
		}
		if ( Helper::is_account_page() ) {
			return [ 'type' => self::TYPE_ACCOUNT, 'object' => null ];
		}

		if ( Helper::is_product() ) {
			return [
				'type'   => self::TYPE_PRODUCT,
				'object' => Helper::get_product( get_queried_object_id() ),
			];
		}
		if ( Helper::is_product_category() ) {
			return [ 'type' => self::TYPE_CATEGORY, 'object' => get_queried_object() ];
		}
		if ( Helper::is_product_tag() ) {
			return [ 'type' => self::TYPE_TAG, 'object' => get_queried_object() ];
		}
		if ( Helper::is_shop() ) {
			return [ 'type' => self::TYPE_SHOP, 'object' => null ];
		}

		return [ 'type' => self::TYPE_NONE, 'object' => null ];
	}
}

// End of file context-resolver.php.
