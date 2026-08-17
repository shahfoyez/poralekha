<?php

namespace StoreEngine\Addons\Couriers\Classes;

use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Couriers\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges shipment-status events back into the parent order: writes
 * notes for every status transition, and on terminal statuses optionally
 * advances the order to `completed` or `on_hold` based on admin settings.
 *
 * Order stays in `processing` for the entire fulfilment journey unless
 * ALL shipments for the order reach `delivered`, which is when we flip to
 * `completed` (opt-out via auto_complete_on_delivered).
 */
final class OrderStatusSync {

	const OPT_KEY = 'storeengine_courier_settings';

	public static function init(): void {
		add_action( 'storeengine/courier/shipment_created', [ __CLASS__, 'on_created' ], 10, 3 );
		add_action( 'storeengine/courier/shipment_status_updated', [ __CLASS__, 'on_updated' ], 10, 4 );
	}

	public static function get_sync_config(): array {
		$all = (array) get_option( self::OPT_KEY, [] );
		$sync = is_array( $all['sync'] ?? null ) ? $all['sync'] : [];
		return [
			'auto_complete_on_delivered' => array_key_exists( 'auto_complete_on_delivered', $sync )
				? (bool) $sync['auto_complete_on_delivered']
				: true,
			'flip_on_return' => array_key_exists( 'flip_on_return', $sync )
				? (bool) $sync['flip_on_return']
				: false,
		];
	}

	public static function set_sync_config( array $cfg ): void {
		$all = (array) get_option( self::OPT_KEY, [] );
		$all['sync'] = [
			'auto_complete_on_delivered' => array_key_exists( 'auto_complete_on_delivered', $cfg )
				? (bool) $cfg['auto_complete_on_delivered']
				: true,
			'flip_on_return' => (bool) ( $cfg['flip_on_return'] ?? false ),
		];
		update_option( self::OPT_KEY, $all, false );
	}

	public static function on_created( int $shipment_id, int $order_id, string $provider ): void {
		$order = Helper::get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) || ! method_exists( $order, 'add_order_note' ) ) return;

		$shipment = ShipmentsService::get( $shipment_id );
		$tracking = $shipment && ! empty( $shipment->tracking_id ) ? (string) $shipment->tracking_id : '—';

		$order->add_order_note( sprintf(
			/* translators: 1: shipment id, 2: provider, 3: tracking id */
			__( 'Shipment #%1$d created via %2$s, tracking %3$s.', 'storeengine' ),
			$shipment_id,
			$provider,
			$tracking
		) );
	}

	public static function on_updated( int $shipment_id, string $raw_status, string $internal_status, bool $delivered ): void {
		$shipment = ShipmentsService::get( $shipment_id );
		if ( ! $shipment ) return;

		$order = Helper::get_order( (int) $shipment->order_id );
		if ( ! $order || is_wp_error( $order ) || ! method_exists( $order, 'add_order_note' ) ) return;

		$note = sprintf(
			/* translators: 1: shipment id, 2: internal status, 3: raw provider status */
			__( 'Shipment #%1$d status: %2$s (provider: %3$s).', 'storeengine' ),
			$shipment_id,
			$internal_status,
			$raw_status
		);
		$order->add_order_note( $note );

		$cfg = self::get_sync_config();

		if ( ShipmentStatus::DELIVERED === $internal_status ) {
			if ( $cfg['auto_complete_on_delivered'] && self::all_shipments_delivered( (int) $shipment->order_id ) ) {
				if ( method_exists( $order, 'update_status' ) ) {
					$order->update_status(
						'completed',
						__( 'All courier shipments delivered — auto-completed.', 'storeengine' )
					);
				}
			}
			return;
		}

		if ( ShipmentStatus::RETURNED === $internal_status ) {
			if ( $cfg['flip_on_return'] && method_exists( $order, 'update_status' ) ) {
				$order->update_status(
					'on_hold',
					__( 'Courier returned shipment — order placed on hold.', 'storeengine' )
				);
			}
		}
	}

	protected static function all_shipments_delivered( int $order_id ): bool {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is a safe internal constant; values use %d/%s placeholders.
		$pending = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . Database::shipments_table() . "
			  WHERE order_id = %d AND internal_status != %s",
			$order_id,
			ShipmentStatus::DELIVERED
		) );
		// phpcs:enable
		return 0 === $pending;
	}
}
