<?php
/**
 * Pixel Utils.
 *
 * @version 1.0.0
 * @since StoreEngine 1.7.4
 */

namespace StoreEngine\Utils;

use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Price;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Pixel {
	public static function get_single_product_data(): array {
		global $product;
		$data = [];

		if ( ! Helper::is_product() ) {
			return $data;
		}

		$data = [
			'item_id'   => $product->get_id(),
			'item_name' => $product->get_name(),
			'price'     => 0,
		];

		if ( 'variable' !== $product->get_type() ) {
			$data['price'] = $product->get_prices() ? $product->get_prices()[0]->get_price() : 0;
		} elseif ( $product->get_variants() ) {
			try {
				$variant              = $product->get_variants()[0];
				$price                = $variant->get_pricing_id() ? new Price( $variant->get_pricing_id() ) : 0;
				$data['price']        = $variant->get_price() + $price->get_price();
				$data['item_variant'] = $variant->get_name();
			} catch ( \Exception $e ) {
				// NoOp.
			}
		}

		$data = [
			'product'  => $data,
			'products' => [],
		];

		// @XXX upsell ids are used as related.
		// @TODO make proper related product feature.
		$related_product_ids = get_post_meta( $product->get_id(), '_storeengine_upsell_ids', true );
		if ( ! empty( $related_product_ids ) ) {
			foreach ( $related_product_ids as $related_product_id ) {
				$related_product = storeengine_get_product( $related_product_id );
				if ( ! $related_product ) {
					continue;
				}

				$data['products'][] = [
					'item_id'        => $related_product->get_id(),
					'item_name'      => $related_product->get_name(),
					'item_list_id'   => 'related_products',
					'item_list_name' => __( 'Related Products', 'storeengine' ),
				];
			}
		}

		return $data;
	}

	public static function get_product_archive_data(): array {
		$products = [];

		if ( ! Helper::is_shop() ) {
			return $products;
		}

		if ( have_posts() ) {
			$doc_title = wp_get_document_title();
			while ( have_posts() ) {
				the_post();
				global $product;
				$products[] = [
					'item_id'        => $product->get_id(),
					'item_name'      => wp_strip_all_tags( $product->get_name() ),
					'item_list_id'   => 'shop',
					'item_list_name' => $doc_title,
				];
			}
			wp_reset_postdata();
		}

		return [ 'products' => $products ];
	}

	public static function get_cart_info(): array {
		$cart = Helper::cart();

		return [
			'is_empty'   => $cart->is_cart_empty(),
			'total'      => (float) $cart->get_total( 'edit' ),
			'cart_items' => array_map( function ( $item ) {
				return [
					'item_id'      => $item->product_id,
					'item_name'    => $item->name,
					'currency'     => Formatting::get_currency(),
					'discount'     => 0,
					'price'        => $item->price,
					'quantity'     => $item->quantity,
					'item_variant' => implode( ' / ', $item->variation ),
				];
			}, array_values( $cart->get_cart_items() ) ),
		];
	}
}
