<?php

namespace StoreEngine\Addons\Couriers\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal shipment status enum.
 *
 *   created → picked_up → in_transit → out_for_delivery → delivered
 *                                                     ↘ cancelled
 *                                                     ↘ returned
 *
 * Each provider's check_status() normalizes its raw string into one of
 * these values via AbstractProvider::map_status().
 */
final class ShipmentStatus {

	const CREATED          = 'created';
	const PICKED_UP        = 'picked_up';
	const IN_TRANSIT       = 'in_transit';
	const OUT_FOR_DELIVERY = 'out_for_delivery';
	const DELIVERED        = 'delivered';
	const CANCELLED        = 'cancelled';
	const RETURNED         = 'returned';

	const TERMINAL = [ self::DELIVERED, self::CANCELLED, self::RETURNED ];

	public static function all(): array {
		return [
			self::CREATED,
			self::PICKED_UP,
			self::IN_TRANSIT,
			self::OUT_FOR_DELIVERY,
			self::DELIVERED,
			self::CANCELLED,
			self::RETURNED,
		];
	}

	public static function is_valid( string $status ): bool {
		return in_array( $status, self::all(), true );
	}

	public static function is_terminal( string $status ): bool {
		return in_array( $status, self::TERMINAL, true );
	}
}
