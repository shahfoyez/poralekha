<?php
/**
 * RestSeo — attach the computed `seo` object to the product REST response.
 *
 * This is the headless channel: the Next.js storefront reads `product.seo`
 * and feeds it straight into generateMetadata() + a JSON-LD <script>, so the
 * storefront does zero SEO logic of its own. Always on (free).
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Rest;

use StoreEngine\Addons\Seo\Engine\SeoEngine;
use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestSeo {

	public static function init() {
		$self = new self();

		// After core's extend_product_rest_response (priority 10).
		add_filter( 'rest_prepare_' . Helper::PRODUCT_POST_TYPE, [ $self, 'add_seo' ], 20, 3 );

		return $self;
	}

	/**
	 * @param \WP_REST_Response $response
	 * @param \WP_Post          $post
	 * @param \WP_REST_Request  $request
	 *
	 * @return \WP_REST_Response
	 */
	public function add_seo( $response, $post, $request ) {
		if ( empty( $response->data['id'] ) ) {
			return $response;
		}

		$product = Helper::get_product( (int) $response->data['id'] );
		if ( ! $product instanceof AbstractProduct ) {
			return $response;
		}

		$response->data['seo'] = SeoEngine::init()->for_product( $product )->to_array();

		return $response;
	}
}

// End of file rest-seo.php.
