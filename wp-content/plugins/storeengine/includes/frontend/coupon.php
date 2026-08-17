<?php

namespace StoreEngine\Frontend;

use StoreEngine\classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Coupon as CouponObject;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Coupon {

	public static function init() {
		add_action( 'storeengine/validate_coupon', [ __CLASS__, 'check_cart_minimum_requirement' ] );
		add_action( 'storeengine/validate_coupon', [ __CLASS__, 'check_prices_requirement' ] );
		// Coupons passed via URL (?coupon=CODE / ?storeengine_coupon=CODE).
		add_action( 'template_redirect', [ __CLASS__, 'apply_url_coupon' ] );
		// Coupons flagged "auto apply" are added to every eligible cart.
		add_action( 'storeengine/cart/before_calculate_totals', [ __CLASS__, 'apply_auto_coupons' ] );
	}

	/**
	 * Apply a coupon code supplied via the URL to the current cart.
	 */
	public static function apply_url_coupon(): void {
		if ( is_admin() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- shared/email link, read-only intent.
		$code = sanitize_text_field( wp_unslash( $_GET['storeengine_coupon'] ?? $_GET['coupon'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $code ) {
			return;
		}

		$cart = Helper::cart();
		if ( ! $cart || $cart->is_coupon_applied( $code ) ) {
			return;
		}

		$applied = $cart->apply_coupon( $code );
		if ( ! is_wp_error( $applied ) ) {
			$cart->store_on_database();
		}
	}

	/**
	 * Add every valid auto-apply coupon to the cart before totals are computed.
	 *
	 * @param \StoreEngine\Classes\Cart $cart
	 */
	public static function apply_auto_coupons( $cart ): void {
		// Auto-apply is a Pro ("Advanced Coupons") feature.
		if ( ! apply_filters( 'storeengine/advanced_coupon/enabled', false ) ) {
			return;
		}

		// Defensive: this fires from calculate_cart_totals(), so a cart is expected,
		// but never fatal if it is missing/invalid.
		if ( ! $cart instanceof \StoreEngine\Classes\Cart ) {
			return;
		}

		try {
			foreach ( self::get_auto_apply_codes() as $code ) {
				if ( $cart->is_coupon_applied( $code ) ) {
					continue;
				}

				$coupon = new CouponObject( $code );
				if ( ! $coupon->get_id() || ! $coupon->get_auto_apply() ) {
					continue;
				}

				// apply_coupon() validates; invalid coupons are silently skipped.
				$cart->apply_coupon( $code );
			}
		} catch ( \Throwable $e ) {
			// Auto-apply must never take the site down. If something unexpected
			// blows up here, log it and self-heal by clearing this user's cart data
			// so subsequent requests start clean instead of re-triggering the error.
			Helper::log_error( $e );

			if ( method_exists( $cart, 'clear_cart' ) ) {
				$cart->clear_cart();
				if ( method_exists( $cart, 'store_on_database' ) ) {
					$cart->store_on_database();
				}
			}
		}
	}

	/**
	 * Codes of all published coupons flagged for auto-apply.
	 *
	 * @return string[]
	 */
	protected static function get_auto_apply_codes(): array {
		$codes = wp_cache_get( 'storeengine_auto_apply_coupon_codes', 'coupons' );
		if ( is_array( $codes ) ) {
			return $codes;
		}

		$ids = get_posts( [
			'post_type'      => Helper::COUPON_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_storeengine_coupon_auto_apply',
					'value' => '1',
				],
			],
		] );

		$codes = array_values( array_filter( array_map( static function ( $id ) {
			return get_post_meta( $id, '_storeengine_coupon_name', true );
		}, $ids ) ) );

		wp_cache_set( 'storeengine_auto_apply_coupon_codes', $codes, 'coupons' );

		return $codes;
	}

	/**
	 * @throws StoreEngineException
	 */
	public static function check_cart_minimum_requirement( \StoreEngine\classes\Coupon $coupon ) {
		if ( 'none' !== $coupon->settings['coupon_type_of_min_requirement'] ) {
			$cart = Helper::cart();
			if ( ! $cart ) {
				// No cart to validate against (should not happen once the cart is
				// ready). Skip rather than fatal on a null cart.
				return;
			}
			$cart_subtotal = (float) $cart->get_subtotal();

			self::check_minimum_requirements( $coupon, $cart->get_count(), $cart_subtotal );
		}
	}


	/**
	 * @param \StoreEngine\Classes\Coupon $coupon
	 * @param $total_quantity
	 * @param $subtotal
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	public static function check_minimum_requirements( \StoreEngine\Classes\Coupon $coupon, $total_quantity, $subtotal ) {
		$minimum_purchase_quantity = $coupon->settings['coupon_min_purchase_quantity'];
		if ( 'quantity' === $coupon->settings['coupon_type_of_min_requirement'] && $total_quantity < $minimum_purchase_quantity ) {
			throw new StoreEngineException( esc_html(
				sprintf(
					// translators: %d: minimum purchase quantity.
					__( 'Sorry, Coupon has minimum purchase quantity of %d', 'storeengine' ),
					$minimum_purchase_quantity
				)
			), 'min-purchase-qty', null, 400 );
		}

		$minimum_purchase_amount = $coupon->settings['coupon_min_purchase_amount'] ?? 0;
		if ( 'amount' === $coupon->settings['coupon_type_of_min_requirement'] && $subtotal < (float) $minimum_purchase_amount ) {
			throw new StoreEngineException( esc_html(
				sprintf(
				// translators: %s: minimum purchase amount.
					__( 'Sorry, Coupon has minimum purchase amount of %s', 'storeengine' ),
					Formatting::price( $minimum_purchase_amount, [
						'in_span' => false
					] )
				)
			), 'min-purchase-amount', null, 400 );
		}
	}

	/**
	 * @throws StoreEngineException
	 */
	public static function check_prices_requirement( \StoreEngine\classes\Coupon $coupon ) {
		if ( empty( $coupon->get_valid_price_data() ) ) {
			return;
		}

		$cart = Helper::cart();
		if ( ! $cart ) {
			// No cart to validate against (should not happen once the cart is
			// ready). Skip rather than fatal on a null cart.
			return;
		}
		$is_valid = false;
		foreach ( $coupon->get_valid_price_data() as $valid_price_data ) {
			if ( $cart->is_product_in_cart( $valid_price_data['product_id'] ) ) {
				if ( empty( $valid_price_data['price_ids'] ) ) {
					$is_valid = true;
					break;
				}

				foreach ( $valid_price_data['price_ids'] as $price_id ) {
					if ( $cart->is_price_in_cart( $price_id ) ) {
						$is_valid = true;
						break;
					}
				}

				if ( $is_valid ) {
					break;
				}
			}
		}

		if ( ! $is_valid ) {
			throw new StoreEngineException( esc_html__( "Sorry, Coupon isn't valid for your cart.", 'storeengine' ), 'min-purchase-amount', null, 400 );
		}
	}

}
