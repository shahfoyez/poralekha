<?php

namespace StoreEngine\Hooks;

use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Classes\enums\PurchaseRedirectType;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AfterPurchase {

	public static function init() {
		if ( ! Helper::get_settings( 'enable_after_purchase_redirect', false ) ) {
			return;
		}

		add_filter( 'storeengine/order/get_checkout_order_received_url', [ __CLASS__, 'get_checkout_order_received_url', ], 10, 2 );
	}

	/**
	 * @throws StoreEngineException
	 */
	public static function get_checkout_order_received_url( string $received_url, Order $order ): string {
		if ( 'paid' !== $order->get_paid_status() || 1 !== count( $order->get_items() ) ) {
			return $received_url;
		}

		$order_items = $order->get_items();
		/** @var Order\OrderItemProduct $order_item */
		$order_item   = reset( $order_items );
		$integrations = Helper::get_integrations_by_price_id( $order_item->get_price_id() );

		foreach ( $integrations as $integration ) {
			if ( 'storeengine/membership-addon' !== $integration->get_provider() ) {
				continue;
			}

			$redirect_type = get_post_meta( $integration->get_integration_id(), '_storeengine_membership_purchase_redirect_type', true );
			if ( PurchaseRedirectType::DEFAULT === $redirect_type ) {
				continue;
			}
			$membership_redirect_url = get_post_meta( $integration->get_integration_id(), '_storeengine_membership_purchase_redirect_url', true );
			if ( empty( $membership_redirect_url ) ) {
				continue;
			}
			if ( PurchaseRedirectType::PAGE === $redirect_type ) {
				return get_permalink( (int) $membership_redirect_url );
			}

			return $membership_redirect_url;
		}

		/** @var false|AbstractProduct $product */
		$product = $order_item->get_product();
		if ( ! $product || PurchaseRedirectType::DEFAULT === $product->get_purchase_redirect_type() || empty( $product->get_purchase_redirect_url() ) ) {
			return $received_url;
		}

		return $product->get_purchase_redirect_url();
	}

}
