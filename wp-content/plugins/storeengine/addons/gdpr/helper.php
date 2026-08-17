<?php
/**
 * Shared helpers for the GDPR exporters/erasers.
 *
 * The single most important piece here is querying orders by *email* (not just
 * customer_id), so guest orders — which have no user account — are matched too.
 */

namespace StoreEngine\Addons\Gdpr;

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper as CoreHelper;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helper {

	/**
	 * Rows handled per export/erase batch. WordPress re-invokes the callback
	 * with an incremented $page until it returns `done => true`.
	 */
	const PER_PAGE = 20;

	/**
	 * Resolve a WP user id from an email address.
	 *
	 * @param string $email
	 * @return int 0 when no account matches (i.e. a guest).
	 */
	public static function user_id_for_email( string $email ): int {
		$user = get_user_by( 'email', $email );

		return $user ? (int) $user->ID : 0;
	}

	/**
	 * Order ids belonging to an email — matched by billing_email OR, when the
	 * email maps to an account, by customer_id. Covers guests and registered
	 * customers alike. Paginated, newest first.
	 *
	 * @param string $email
	 * @param int    $page     1-based.
	 * @param int    $per_page
	 * @return int[]
	 */
	public static function order_ids_for_email( string $email, int $page = 1, int $per_page = self::PER_PAGE ): array {
		global $wpdb;

		$offset  = max( 0, ( $page - 1 ) * $per_page );
		$user_id = self::user_id_for_email( $email );

		$table = $wpdb->prefix . 'storeengine_orders';

		if ( $user_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%s/%d) query on a custom StoreEngine table; $table is a plugin-internal name.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE billing_email = %s OR customer_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore
					$email,
					$user_id,
					$per_page,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%s/%d) query on a custom StoreEngine table; $table is a plugin-internal name.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE billing_email = %s ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore
					$email,
					$per_page,
					$offset
				)
			);
		}

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Hydrate an order id into the correct order object (orders, subscriptions, …).
	 *
	 * @param int $order_id
	 * @return Order|null
	 */
	public static function get_order( int $order_id ): ?Order {
		try {
			$class = CoreHelper::get_class_name_for_order_id( $order_id );
			if ( ! $class || ! class_exists( $class ) ) {
				$class = Order::class;
			}
			$order = new $class( $order_id );

			return $order->get_id() ? $order : null;
		} catch ( Throwable $e ) {
			unset( $e );

			return null;
		}
	}

	/**
	 * Placeholder email used when anonymizing an order's email fields.
	 *
	 * @param int $order_id
	 * @return string
	 */
	public static function anonymized_email( int $order_id ): string {
		return 'deleted-' . $order_id . '@site.invalid';
	}

	/**
	 * Whether a batch is the last one (fewer rows than the page size).
	 *
	 * @param int $count    Rows returned this batch.
	 * @param int $per_page
	 * @return bool
	 */
	public static function is_done( int $count, int $per_page = self::PER_PAGE ): bool {
		return $count < $per_page;
	}
}
