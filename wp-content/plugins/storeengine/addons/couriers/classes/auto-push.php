<?php

namespace StoreEngine\Addons\Couriers\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional: auto-push paid orders to a configured courier provider.
 *
 * Listens for `storeengine/payment_complete` and, if `auto_push.enabled`
 * is true in the courier-settings option, calls the chosen provider's
 * `create_shipment()` with the order's billing address. POS sales are
 * skipped (cashier already handed the goods over). Online order numbers
 * are tagged with `_storeengine_courier_auto_pushed` to prevent duplicates
 * if the action fires twice (e.g. due to gateway retry).
 */
final class AutoPush {

	const META_KEY = '_storeengine_courier_auto_pushed';
	const OPT_KEY  = 'storeengine_courier_settings';

	const ASYNC_HOOK = 'storeengine/couriers/auto_push';

	public static function init(): void {
		add_action( 'storeengine/payment_complete', [ __CLASS__, 'maybe_push' ], 40 );
		add_action( self::ASYNC_HOOK, [ __CLASS__, 'run_push' ], 10, 1 );
	}

	/**
	 * Runs inside the user-facing payment-complete request. Does only cheap local
	 * checks, then hands the courier API calls (which are external HTTP with a 30s
	 * timeout, one per vendor) to Action Scheduler so they never block checkout or
	 * a gateway callback. Falls back to inline execution only if Action Scheduler
	 * is unavailable.
	 */
	public static function maybe_push( int $order_id ): void {
		if ( ! $order_id ) {
			return;
		}

		$cfg = self::get_auto_push_config();
		if ( empty( $cfg['enabled'] ) || empty( $cfg['provider'] ) ) {
			return;
		}

		// Skip POS sales — cashier hands goods to the customer in person.
		if ( 'pos' === get_post_meta( $order_id, '_storeengine_pos_source', true ) ) {
			return;
		}

		// Idempotency guard — already pushed (or queued and completed).
		if ( get_post_meta( $order_id, self::META_KEY, true ) ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$args = [ 'order_id' => $order_id ];
			if ( ! function_exists( 'as_next_scheduled_action' ) || false === as_next_scheduled_action( self::ASYNC_HOOK, $args, 'storeengine-couriers' ) ) {
				as_enqueue_async_action( self::ASYNC_HOOK, $args, 'storeengine-couriers' );
			}
			return;
		}

		// Action Scheduler not loaded — do it inline as a last resort.
		self::run_push( $order_id );
	}

	public static function get_auto_push_config(): array {
		$all = (array) get_option( self::OPT_KEY, [] );
		return is_array( $all['auto_push'] ?? null ) ? $all['auto_push'] : [];
	}

	public static function set_auto_push_config( array $cfg ): void {
		$all = (array) get_option( self::OPT_KEY, [] );
		$all['auto_push'] = [
			'enabled'           => (bool) ( $cfg['enabled'] ?? false ),
			'provider'          => isset( $cfg['provider'] ) ? sanitize_key( (string) $cfg['provider'] ) : '',
			'default_weight_kg' => isset( $cfg['default_weight_kg'] ) ? (float) $cfg['default_weight_kg'] : 0.5,
			'use_cod_when'      => isset( $cfg['use_cod_when'] ) ? sanitize_key( (string) $cfg['use_cod_when'] ) : 'cod_payment_method',
			'cod_methods'       => array_values( array_filter(
				array_map( 'sanitize_text_field', (array) ( $cfg['cod_methods'] ?? [] ) )
			) ),
		];
		update_option( self::OPT_KEY, $all, false );
	}

	/**
	 * The actual courier push (external HTTP). Runs via Action Scheduler, off the
	 * user-facing request. Re-checks config and the idempotency guard because the
	 * order/settings may have changed between enqueue and execution.
	 */
	public static function run_push( int $order_id ): void {
		if ( ! $order_id ) return;

		$cfg = self::get_auto_push_config();
		if ( empty( $cfg['enabled'] ) || empty( $cfg['provider'] ) ) {
			return;
		}

		// Skip POS sales — cashier hands goods to the customer in person.
		$source = get_post_meta( $order_id, '_storeengine_pos_source', true );
		if ( 'pos' === $source ) return;

		// Idempotency guard.
		if ( get_post_meta( $order_id, self::META_KEY, true ) ) return;

		$order = Helper::get_order( $order_id );
		if ( ! $order ) return;

		$payload = OrderPayloadMapper::build_payload( $order, $cfg );
		if ( empty( $payload['customer_phone'] ) ) {
			// No phone → courier APIs reject; record a note and bail.
			update_post_meta( $order_id, self::META_KEY, 'skipped_no_phone' );
			return;
		}

		$payload = array_merge(
			$payload,
			OrderPayloadMapper::build_provider_extras( $order, (string) $cfg['provider'] )
		);

		// Multi-vendor: split into one shipment per distinct vendor so each
		// courier dispatch goes to the correct warehouse. Single-vendor orders
		// still produce exactly one shipment.
		$vendor_groups = self::group_items_by_vendor( $order );
		$created_ids   = [];

		if ( count( $vendor_groups ) <= 1 ) {
			$vendor_id = array_key_first( $vendor_groups );
			$payload['vendor_id'] = $vendor_id ?: null;

			$result = ShipmentsService::create_for_order(
				$order_id,
				(string) $cfg['provider'],
				$payload
			);
			if ( ! empty( $result['ok'] ) ) {
				$created_ids[] = (int) $result['shipment_id'];
			}
		} else {
			foreach ( $vendor_groups as $vendor_id => $_lines ) {
				$vendor_payload              = $payload;
				$vendor_payload['vendor_id'] = $vendor_id;
				$result = ShipmentsService::create_for_order(
					$order_id,
					(string) $cfg['provider'],
					$vendor_payload
				);
				if ( ! empty( $result['ok'] ) ) {
					$created_ids[] = (int) $result['shipment_id'];
				}
			}
		}

		if ( $created_ids ) {
			update_post_meta( $order_id, self::META_KEY, count( $created_ids ) > 1 ? implode( ',', $created_ids ) : (int) $created_ids[0] );

			foreach ( $created_ids as $shipment_id ) {
				do_action( 'storeengine/courier/auto_pushed', $order_id, $shipment_id, (string) $cfg['provider'] );
			}
		} else {
			update_post_meta(
				$order_id,
				self::META_KEY,
				'failed:no_shipments_created'
			);
		}
	}

	/**
	 * Group an order's line items by vendor (product post_author).
	 * @return array<int, array<int, mixed>>  vendor_id => list of line items
	 */
	protected static function group_items_by_vendor( $order ): array {
		$groups = [];
		if ( ! method_exists( $order, 'get_items' ) ) {
			return $groups;
		}

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$pid = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
			if ( ! $pid ) continue;
			$author = (int) get_post_field( 'post_author', $pid );
			if ( $author > 0 ) {
				$groups[ $author ][] = $item;
			} else {
				$groups[0][] = $item;
			}
		}
		return $groups;
	}
}
