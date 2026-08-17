<?php

namespace StoreEngine\Addons\Subscription\Events;

use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Coupon;
use StoreEngine\Classes\Discounts;
use StoreEngine\Classes\Order;
use StoreEngine\Addons\Subscription\Classes\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries a coupon's discount forward onto subscription renewals.
 *
 * When a subscription is created with a coupon flagged "recurring discount", we
 * remember the code and how many renewals it should keep discounting. At each
 * renewal we re-apply that coupon to the new order — bypassing expiry / usage
 * validation so an early subscriber keeps the deal they signed up for.
 *
 * @see Coupon::get_recurring_discount()
 * @see \StoreEngine\Addons\Subscription\Events\Renewal
 */
class RecurringCoupon {

	const META_KEY = '_storeengine_recurring_coupons';

	/**
	 * Codes currently being re-applied at renewal, so the validation-bypass
	 * filter only relaxes checks for those specific coupons.
	 *
	 * @var array<string, bool>
	 */
	protected static array $applying = [];

	public static function init(): void {
		$self = new self();
		add_action( 'storeengine/subscription/checkout_subscription_created', [ $self, 'remember_recurring_coupons' ], 10, 3 );
		// Re-apply on both renewal paths. The scheduled recurring payment fires
		// `renewal_order_created` (a filter — see Utils::create_renewal_order),
		// while the expiry-driven renewal fires `after_create_renewal_order` (an
		// action — see Renewal::create_new_order). apply_to_renewal returns the
		// order so the same callback is safe as either a filter or an action.
		add_filter( 'storeengine/subscription/renewal_order_created', [ $self, 'apply_to_renewal' ], 10, 2 );
		add_action( 'storeengine/api/after_create_renewal_order', [ $self, 'apply_to_renewal' ], 10, 1 );
		add_filter( 'storeengine/pre_is_coupon_valid', [ $self, 'bypass_validation_for_renewal' ], 10, 2 );
	}

	/**
	 * Record which applied coupons should recur, and for how many renewals.
	 *
	 * @param Subscription   $subscription Newly created subscription.
	 * @param AbstractOrder  $order        Parent order.
	 * @param Cart           $cart         Recurring cart.
	 */
	public function remember_recurring_coupons( Subscription $subscription, AbstractOrder $order, Cart $cart ): void {
		$map = [];

		// Read coupons from the parent order, not the subscription: recurring
		// carts have their coupons stripped before the subscription is built
		// (Hooks::create_subscriptions() -> $recurring_cart->remove_coupons()),
		// so the subscription itself stores no coupon codes. The signup discount
		// only survives on the parent order.
		foreach ( $order->get_coupon_codes() as $code ) {
			$coupon = new Coupon( $code );

			if ( ! $coupon->get_id() || ! $coupon->get_recurring_discount() ) {
				continue;
			}

			$limit = $coupon->get_recurring_discount_limit();
			// 0 limit means forever; track that as -1 so it never decrements to 0.
			$map[ $code ] = $limit > 0 ? $limit : -1;
		}

		if ( ! empty( $map ) ) {
			$subscription->update_meta_data( self::META_KEY, $map );
			$subscription->save_meta_data();
		}
	}

	/**
	 * Re-apply remembered coupons to a freshly created renewal order.
	 *
	 * Used as both a filter callback (`renewal_order_created`, which passes the
	 * source subscription) and an action callback (`after_create_renewal_order`,
	 * which passes only the order). It always returns the order so it is safe in
	 * the filter position.
	 *
	 * @param Order              $order        The renewal order (already saved).
	 * @param Subscription|null  $subscription Source subscription, when provided.
	 *
	 * @return Order
	 */
	public function apply_to_renewal( Order $order, ?Subscription $subscription = null ): Order {
		if ( ! $subscription ) {
			$subscription_id = (int) $order->get_meta( '_subscription_renewal' );
			if ( ! $subscription_id ) {
				return $order;
			}

			$subscription = Subscription::get_subscription( $subscription_id );
		}

		if ( ! $subscription ) {
			return $order;
		}

		$map = $subscription->get_meta( self::META_KEY );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return $order;
		}

		$changed = false;

		foreach ( $map as $code => $remaining ) {
			$remaining = (int) $remaining;

			// 0 = exhausted; -1 = forever; >0 = renewals left.
			if ( 0 === $remaining ) {
				continue;
			}

			$coupon = new Coupon( $code );
			if ( ! $coupon->get_id() || ! $coupon->get_recurring_discount() ) {
				continue;
			}

			self::$applying[ strtolower( $code ) ] = true;
			$applied                                = $order->apply_coupon( $coupon );
			unset( self::$applying[ strtolower( $code ) ] );

			if ( is_wp_error( $applied ) ) {
				continue;
			}

			if ( $remaining > 0 ) {
				$map[ $code ] = $remaining - 1;
				$changed      = true;
			}
		}

		if ( $changed ) {
			$subscription->update_meta_data( self::META_KEY, $map );
			$subscription->save_meta_data();
		}

		return $order;
	}

	/**
	 * While re-applying a recurring coupon at renewal, treat it as valid so an
	 * expired / usage-capped coupon still honours the locked-in discount.
	 *
	 * @param null|bool|\WP_Error $pre    Short-circuit value.
	 * @param Coupon              $coupon Coupon being validated.
	 *
	 * @return null|bool|\WP_Error
	 */
	public function bypass_validation_for_renewal( $pre, Coupon $coupon ) {
		if ( null !== $pre ) {
			return $pre;
		}

		if ( ! empty( self::$applying[ strtolower( $coupon->get_code() ) ] ) && $coupon->get_recurring_discount() ) {
			return true;
		}

		return $pre;
	}
}
