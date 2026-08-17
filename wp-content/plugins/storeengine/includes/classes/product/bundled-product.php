<?php

namespace StoreEngine\Classes\Product;

use StoreEngine\classes\AbstractProduct;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\Variation;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BundledProduct extends AbstractProduct {

	protected $bundles = null;

	public function __construct( int $id = 0 ) {
		$this->meta_data['product_bundles'] = [];
		$this->serialized_data[]            = 'product_bundles';

		parent::__construct( $id );
	}

	/**
	 * @return array<array{
	 *     product_id: int,
	 *     price_id: int,
	 *     product_name:string,
	 *     price:float,
	 *     price_name:string
	 * }>
	 */
	public function get_bundles(): array {

		if ( null === $this->bundles ) {
			$this->bundles = [];

			foreach ( $this->get_meta_prop( 'product_bundles', [] ) as $bundle ) {
				try {
					$bundle_price   = new Price( $bundle['price_id'] );
					$bundle_product = $bundle_price->get_product();

					// Sanity check: DB records aren't corrupted.
					if ( ! $bundle_product || $bundle_product->get_id() != $bundle_price->get_product_id() ) {
						continue;
					}

					if ( in_array( get_post_status( $bundle_product->get_id() ), [ 'trash', 'draft', 'auto-draft' ], true ) ) {
						continue;
					}

					// Don't include the link (product can be in trash or removed).
					$this->bundles[] = array_merge( $bundle, [
						'product_name' => $bundle_product->get_name(),
						'quantity'     => $bundle['quantity'],
						'price'        => $bundle_price->get_price(),
						'price_name'   => $bundle_price->get_name(),
					] );
				} catch ( Throwable $e ) {
					// No-Op.
				}
			}
		}


//		dd( $this->bundles );

		return $this->bundles;
	}
}
