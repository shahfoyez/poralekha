<?php

namespace StoreEngine\Addons\InstantCheckout;

use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instant Checkout shortcode.
 *
 *   Single product:
 *     [storeengine_instant_checkout product_id="123" price_id="5"]
 *
 *   Multi-product / optional add-ons (nested):
 *     [storeengine_instant_checkout]
 *       [se_checkout_item product_id="27" price_id="5"]
 *       [se_checkout_item product_id="28" optional="yes" label="Add gift wrap"]
 *       [se_checkout_item product_id="29" optional="yes" default="yes"]
 *     [/storeengine_instant_checkout]
 *
 *   Modes:
 *     mode=""        → embedded form (default)
 *     mode="inline"  → embedded form
 *     mode="modal"   → launcher button (opens Quick Checkout modal)
 */
class Shortcode {
	use Singleton;

	const TAG      = 'storeengine_instant_checkout';
	const ITEM_TAG = 'se_checkout_item';

	protected function __construct() {
		add_shortcode( self::TAG, [ $this, 'render' ] );
		add_shortcode( self::ITEM_TAG, '__return_empty_string' );
	}

	public function render( $atts, $content = '' ): string {
		$atts = shortcode_atts(
			[
				'product_id'  => 0,
				'price_id'    => 0,
				'quantity'    => 1,
				'mode'        => '',
				'label'       => '',
				'success_url' => '',
			],
			$atts,
			self::TAG
		);

		$items = self::parse_inner_items( (string) $content );
		if ( ! $items && (int) $atts['product_id'] ) {
			$items = [ self::normalize_item( [
				'product_id' => $atts['product_id'],
				'price_id'   => $atts['price_id'],
				'quantity'   => $atts['quantity'],
			] ) ];
		}

		if ( ! $items ) {
			return '';
		}

		if ( 'modal' === $atts['mode'] ) {
			$primary_id = (int) ( $items[0]['product_id'] ?? 0 );
			if ( $primary_id ) {
				ob_start();
				Hooks::init()->print_button_for_product( $primary_id );

				return (string) ob_get_clean();
			}
		}

		return self::render_inline_mount( $items, $atts );
	}

	protected static function parse_inner_items( string $content ): array {
		if ( '' === trim( $content ) ) {
			return [];
		}

		$pattern = get_shortcode_regex( [ self::ITEM_TAG ] );
		if ( ! preg_match_all( "/$pattern/", $content, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		$items = [];
		foreach ( $matches as $m ) {
			$attrs = shortcode_parse_atts( $m[3] );
			$item  = self::normalize_item( is_array( $attrs ) ? $attrs : [] );
			if ( $item['product_id'] ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	protected static function normalize_item( array $raw ): array {
		$product_id = (int) ( $raw['product_id'] ?? $raw['id'] ?? 0 );
		$price_id   = (int) ( $raw['price_id'] ?? 0 );
		$quantity   = max( 1, (int) ( $raw['quantity'] ?? $raw['qty'] ?? 1 ) );
		$optional   = self::truthy( $raw['optional'] ?? false );
		$default    = self::truthy( $raw['default'] ?? false );

		$label = isset( $raw['label'] ) ? (string) $raw['label'] : '';
		$image = '';
		if ( $product_id ) {
			if ( '' === $label ) {
				$post  = get_post( $product_id );
				$label = $post ? (string) $post->post_title : '';
			}
			$image = (string) ( get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ?: '' );

			if ( ! $price_id ) {
				$product = Helper::get_product( $product_id );
				if ( $product ) {
					$prices = $product->get_prices();
					if ( $prices ) {
						$price_id = (int) reset( $prices )->get_id();
					}
				}
			}
		}

		return [
			'product_id' => $product_id,
			'price_id'   => $price_id,
			'quantity'   => $quantity,
			'optional'   => $optional,
			'default'    => $default,
			'label'      => $label,
			'image'      => $image,
		];
	}

	protected static function truthy( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}
		return in_array( strtolower( (string) $v ), [ '1', 'yes', 'true', 'on' ], true );
	}

	protected static function render_inline_mount( array $items, array $atts ): string {
		wp_enqueue_script( 'storeengine-instant-checkout-app' );
		wp_enqueue_style( 'storeengine-instant-checkout-app' );

		$primary_id = (int) ( $items[0]['product_id'] ?? 0 );
		$primary_pr = (int) ( $items[0]['price_id'] ?? 0 );

		return sprintf(
			'<div data-storeengine-checkout data-store-url="%1$s" data-rest-url="%7$s" data-product-id="%2$d" data-price-id="%3$d" data-quantity="1" data-success-url="%4$s" data-items="%5$s" data-nonce="%6$s"></div>',
			esc_url( home_url() ),
			$primary_id,
			$primary_pr,
			esc_url( $atts['success_url'] ),
			esc_attr( wp_json_encode( $items ) ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_url( rest_url() )
		);
	}
}
