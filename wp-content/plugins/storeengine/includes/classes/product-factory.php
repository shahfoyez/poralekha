<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Product\BundledProduct;
use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Classes\Product\VariableProduct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductFactory {

	/**
	 * @param int $product_id
	 *
	 * @return false|SimpleProduct|VariableProduct|BundledProduct
	 */
	public function get_product( int $product_id = 0 ) {
		try {
			$product_type = get_post_meta( $product_id, '_storeengine_product_type', true ) ?? 'simple';
			$classname    = self::get_product_classname( $product_id, $product_type );
			$product      = new $classname( $product_id );

			return $product->get_id() ? $product : false;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * @param int $product_id
	 * @param string|null $product_type
	 *
	 * @return AbstractProduct
	 */
	public static function getProduct( int $product_id = 0, string $product_type = null ) {
		if ( ! $product_type ) {
			$product_type = get_post_meta( $product_id, '_storeengine_product_type', true ) ?? 'simple';
		}

		$classname = self::get_product_classname( $product_id, $product_type );

		return new $classname( $product_id );
	}

	public function get_product_by_price_id( int $price_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM {$wpdb->prefix}storeengine_product_price WHERE id = %d", $price_id ) );
		if ( ! $product_id ) {
			return false;
		}

		return $this->get_product( $product_id );
	}

	public static function get_product_classname( $product_id, $product_type ) {
		$class_names = apply_filters( 'storeengine/product_classes', [
			'simple'   => SimpleProduct::class,
			'variable' => VariableProduct::class,
			'bundled'  => BundledProduct::class,
		] );

		$classname = apply_filters( 'storeengine/product/get_classname', $class_names[ $product_type ] ?? null, $product_id, $product_type );

		if ( ! $classname || ! class_exists( $classname ) ) {
			$classname = SimpleProduct::class;
		}

		return $classname;
	}
}
