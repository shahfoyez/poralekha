<?php

namespace StoreEngine\Addons\Webhooks\Incoming;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Webhooks\Incoming\Handlers\CustomerUpsert;
use StoreEngine\Addons\Webhooks\Incoming\Handlers\OrderPaid;
use StoreEngine\Addons\Webhooks\Incoming\Handlers\OrderStatus;
use StoreEngine\Addons\Webhooks\Incoming\Handlers\StockAdjust;
use StoreEngine\Addons\Webhooks\Incoming\Handlers\StockSet;
use StoreEngine\Addons\Webhooks\Interfaces\IncomingHandlerInterface;

/**
 * Maps an action slug to the handler that performs it, and describes the
 * available actions for the admin picker. The `automation` pseudo-action skips
 * structured handling and only fires the internal `.../received` hook — the
 * no-code / FluentCRM path.
 */
class Registry {

	const AUTOMATION = 'automation';

	/**
	 * Action slug => handler class. Pro addons (multi-vendor, subscription, …)
	 * slot their own handlers in here.
	 *
	 * @return array<string,class-string<IncomingHandlerInterface>>
	 */
	public static function get_handlers(): array {
		return (array) apply_filters( 'storeengine/incoming_webhook/handlers', [
			'order_update_status' => OrderStatus::class,
			'order_mark_paid'     => OrderPaid::class,
			'stock_set'           => StockSet::class,
			'stock_adjust'        => StockAdjust::class,
			'customer_upsert'     => CustomerUpsert::class,
		] );
	}

	/**
	 * @return IncomingHandlerInterface|null
	 */
	public static function get_handler( string $action ) {
		$handlers = self::get_handlers();

		if ( ! isset( $handlers[ $action ] ) ) {
			return null;
		}

		$class = $handlers[ $action ];

		if ( ! class_exists( $class ) ) {
			return null;
		}

		$instance = new $class();

		return $instance instanceof IncomingHandlerInterface ? $instance : null;
	}

	/**
	 * Action descriptors for the admin UI (value + label + help text), exposed on
	 * the backend script data global.
	 *
	 * @return array<int,array{value:string,label:string,description:string}>
	 */
	public static function get_actions(): array {
		return (array) apply_filters( 'storeengine/incoming_webhook/action_labels', [
			[
				'value'       => self::AUTOMATION,
				'label'       => __( 'Automation / Raw (fire internal hook only)', 'storeengine' ),
				'description' => __( 'Verify & log the request, then fire the storeengine/incoming_webhook/received action for no-code tools and custom code to handle.', 'storeengine' ),
			],
			[
				'value'       => 'order_update_status',
				'label'       => __( 'Order → Update status', 'storeengine' ),
				'description' => __( 'Set an order status. Payload: { order_id, status, note?, tracking_number? }.', 'storeengine' ),
			],
			[
				'value'       => 'order_mark_paid',
				'label'       => __( 'Order → Mark as paid', 'storeengine' ),
				'description' => __( 'Force an order into a paid state. Payload: { order_id, transaction_id?, note? }.', 'storeengine' ),
			],
			[
				'value'       => 'stock_set',
				'label'       => __( 'Stock → Set quantity', 'storeengine' ),
				'description' => __( 'Set a product\'s stock to an absolute quantity. Payload: { product_id, quantity }.', 'storeengine' ),
			],
			[
				'value'       => 'stock_adjust',
				'label'       => __( 'Stock → Adjust by delta', 'storeengine' ),
				'description' => __( 'Increment/decrement a product\'s stock. Payload: { product_id, delta }.', 'storeengine' ),
			],
			[
				'value'       => 'customer_upsert',
				'label'       => __( 'Customer → Create or update', 'storeengine' ),
				'description' => __( 'Create a customer (or update the one matching the email). Payload: { email, first_name?, last_name?, phone? }.', 'storeengine' ),
			],
		] );
	}
}
