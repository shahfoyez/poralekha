<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StockManager {

	public const TYPE_SALE      = 'sale';
	public const TYPE_RESTOCK   = 'restock';
	public const TYPE_MANUAL    = 'manual';
	public const TYPE_RETURN    = 'return';
	public const TYPE_DAMAGED   = 'damaged';
	public const TYPE_RECEIVED  = 'received';
	public const TYPE_CORRECTION = 'correction';

	public static function init() {
		add_action( 'storeengine/payment_complete', [ __CLASS__, 'on_payment_complete' ], 20 );
		add_action( 'storeengine/order/status_changed', [ __CLASS__, 'on_status_changed' ], 20, 4 );
		add_action( 'storeengine/stock/release_expired_reservations', [ __CLASS__, 'release_expired_reservations' ] );
		add_action( 'storeengine/stock/low_stock_digest', [ __CLASS__, 'send_low_stock_digest' ] );

		if ( function_exists( 'as_next_scheduled_action' ) ) {
			add_action( 'init', [ __CLASS__, 'schedule_sweeper' ] );
		}
	}

	public static function schedule_sweeper() {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( false === as_next_scheduled_action( 'storeengine/stock/release_expired_reservations' ) ) {
			as_schedule_recurring_action( time() + 60, 15 * MINUTE_IN_SECONDS, 'storeengine/stock/release_expired_reservations' );
		}

		if ( false === as_next_scheduled_action( 'storeengine/stock/low_stock_digest' ) ) {
			as_schedule_recurring_action( strtotime( 'tomorrow 09:00' ), DAY_IN_SECONDS, 'storeengine/stock/low_stock_digest' );
		}
	}

	public static function send_low_stock_digest(): void {
		$recipient = get_option( 'storeengine_low_stock_recipient' );
		if ( ! $recipient ) {
			$recipient = get_option( 'admin_email' );
		}

		if ( ! $recipient || ! is_email( $recipient ) ) {
			return;
		}

		global $wpdb;

		$threshold = (int) get_option( 'storeengine_low_stock_threshold', 0 );

		$rows = [];

		// Variation-level low stock.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$variation_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT v.id, v.sku, v.product_id, v.stock_quantity, v.low_stock_threshold
			 FROM {$wpdb->prefix}storeengine_product_variations v
			 WHERE v.manage_stock = 1
			   AND v.stock_quantity IS NOT NULL
			   AND v.stock_quantity > 0
			   AND v.stock_quantity <= COALESCE(v.low_stock_threshold, %d)",
			$threshold
		) );
		// phpcs:enable

		foreach ( $variation_rows as $row ) {
			$rows[] = sprintf(
				'#%d %s — variation %d (qty %d)',
				(int) $row->product_id,
				get_the_title( (int) $row->product_id ),
				(int) $row->id,
				(int) $row->stock_quantity
			);
		}

		// Simple-product low stock via post meta. Cap to a reasonable result count.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$product_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, qty.meta_value AS stock_quantity, t.meta_value AS threshold
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = '_storeengine_manage_stock' AND ms.meta_value = '1'
			 INNER JOIN {$wpdb->postmeta} qty ON qty.post_id = p.ID AND qty.meta_key = '_storeengine_stock_quantity'
			 LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_storeengine_low_stock_threshold'
			 WHERE p.post_type = 'storeengine_product' AND p.post_status = 'publish'
			   AND CAST(qty.meta_value AS SIGNED) > 0
			   AND CAST(qty.meta_value AS SIGNED) <= COALESCE(NULLIF(CAST(t.meta_value AS SIGNED), 0), %d)
			 LIMIT 200",
			$threshold
		) );
		// phpcs:enable

		foreach ( $product_rows as $row ) {
			$rows[] = sprintf(
				'#%d %s (qty %d)',
				(int) $row->ID,
				get_the_title( (int) $row->ID ),
				(int) $row->stock_quantity
			);
		}

		if ( empty( $rows ) ) {
			return;
		}

		$body  = __( 'The following products are running low on stock:', 'storeengine' ) . "\n\n";
		$body .= implode( "\n", $rows );

		wp_mail(
			$recipient,
			__( '[StoreEngine] Daily low-stock digest', 'storeengine' ),
			$body
		);
	}

	public static function on_payment_complete( int $order_id ) {
		self::maybe_reduce_stock_levels( $order_id );
		self::release_reserved_stock( $order_id );
	}

	public static function on_status_changed( $order_id, $status_from, $status_to, $order = null ) {
		$restore_states = [ 'cancelled', 'refunded', 'failed' ];
		$reduce_states  = array_merge(
			OrderStatus::get_is_paid_statuses(),
			[ OrderStatus::PAYMENT_CONFIRMED, 'shipping' ]
		);

		if ( in_array( $status_to, $restore_states, true ) ) {
			self::maybe_restore_stock_levels( $order_id );
			self::release_reserved_stock( $order_id );
		} elseif ( in_array( $status_to, $reduce_states, true ) ) {
			self::maybe_reduce_stock_levels( $order_id );
			self::release_reserved_stock( $order_id );
		}
	}

	public static function maybe_reduce_stock_levels( int $order_id ): void {
		global $wpdb;

		$order = Helper::get_order( $order_id );

		if ( ! $order || is_wp_error( $order ) ) {
			return;
		}

		// Atomically claim the "reduce stock" step at the DB row level instead
		// of trusting a possibly cached/stale in-memory order object. Multiple
		// triggers can race close together — `storeengine/payment_complete` and
		// a `storeengine/order/status_changed` transition (e.g. processing then
		// later completed) both call into here — and a plain
		// read-flag-then-reduce-then-write-flag check has a gap where a second
		// caller can read "not reduced yet" before the first caller's save()
		// commits, causing stock to be deducted twice for one order. This
		// UPDATE only succeeds for whichever caller gets there first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}storeengine_order_operational_data
			 SET order_stock_reduced = 1
			 WHERE order_id = %d AND ( order_stock_reduced IS NULL OR order_stock_reduced = 0 )",
			$order_id
		) );

		if ( ! $claimed ) {
			return;
		}

		// Keep the in-memory (possibly cached) object in sync with the DB row
		// we just claimed, without a redundant save() — the flag is already
		// persisted above.
		$order->set_order_stock_reduced( true );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id   = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
			$variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
			$qty          = method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 0;

			if ( ! $product_id || $qty <= 0 ) {
				continue;
			}

			$target = null;

			if ( $variation_id ) {
				$variation = Helper::get_product_variation( $variation_id );

				if ( $variation && method_exists( $variation, 'manages_stock' ) && $variation->manages_stock() ) {
					$target = $variation;
				}
			}

			if ( ! $target ) {
				$product = Helper::get_product( $product_id );

				if ( $product && $product->manages_stock() ) {
					$target = $product;
				}
			}

			if ( ! $target ) {
				continue;
			}

			$qty_before = (int) $target->get_stock_quantity();
			$target instanceof \StoreEngine\Classes\Variation
				? $target->reduce_stock( $qty )
				: $target->reduce_stock( $qty, $variation_id ?: null );
			$qty_after = (int) $target->get_stock_quantity();

			self::record_movement( [
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'type'         => self::TYPE_SALE,
				'reason'       => 'order',
				'qty_change'   => -$qty,
				'qty_before'   => $qty_before,
				'qty_after'    => $qty_after,
				'note'         => sprintf( 'Order #%d', $order_id ),
				'order_id'     => $order_id,
			] );

			/**
			 * Fires after free-core stock-quantity has been reduced for an order
			 * line. Multi-location addons hook this to mirror the deduction at
			 * the default location.
			 *
			 * @param int $product_id
			 * @param int $variation_id
			 * @param int $qty
			 * @param int $order_id
			 */
			do_action( 'storeengine/order/stock_reduced', $product_id, $variation_id, $qty, $order_id );
		}
	}

	public static function maybe_restore_stock_levels( int $order_id ): void {
		global $wpdb;

		$order = Helper::get_order( $order_id );

		if ( ! $order || is_wp_error( $order ) ) {
			return;
		}

		// Same atomic-claim pattern as maybe_reduce_stock_levels(): only flip
		// the flag back to "not reduced" for whichever caller gets there first,
		// so a cancellation and a refund landing close together can't both
		// pass the check and restock the same order twice.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}storeengine_order_operational_data
			 SET order_stock_reduced = 0
			 WHERE order_id = %d AND order_stock_reduced = 1",
			$order_id
		) );

		if ( ! $claimed ) {
			return;
		}

		$order->set_order_stock_reduced( false );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id   = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
			$variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
			$qty          = method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 0;

			if ( ! $product_id || $qty <= 0 ) {
				continue;
			}

			$target = null;

			if ( $variation_id ) {
				$variation = Helper::get_product_variation( $variation_id );

				if ( $variation && method_exists( $variation, 'manages_stock' ) && $variation->manages_stock() ) {
					$target = $variation;
				}
			}

			if ( ! $target ) {
				$product = Helper::get_product( $product_id );

				if ( $product && $product->manages_stock() ) {
					$target = $product;
				}
			}

			if ( ! $target ) {
				continue;
			}

			$qty_before = (int) $target->get_stock_quantity();
			$target instanceof \StoreEngine\Classes\Variation
				? $target->increase_stock( $qty )
				: $target->increase_stock( $qty, $variation_id ?: null );
			$qty_after = (int) $target->get_stock_quantity();

			self::record_movement( [
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'type'         => self::TYPE_RESTOCK,
				'reason'       => 'order_cancelled',
				'qty_change'   => $qty,
				'qty_before'   => $qty_before,
				'qty_after'    => $qty_after,
				'note'         => sprintf( 'Restored from order #%d', $order_id ),
				'order_id'     => $order_id,
			] );

			/**
			 * Fires after free-core stock-quantity has been restored from a
			 * cancelled/refunded order. Multi-location addons mirror the
			 * restock at the default location.
			 *
			 * @param int $product_id
			 * @param int $variation_id
			 * @param int $qty
			 * @param int $order_id
			 */
			do_action( 'storeengine/order/stock_restored', $product_id, $variation_id, $qty, $order_id );
		}
	}

	/**
	 * Apply a manual stock adjustment + log a movement.
	 *
	 * @param int    $product_id
	 * @param int    $variation_id 0 if simple product
	 * @param string $action       add | remove | set
	 * @param int    $qty          positive integer
	 * @param string $reason       received | damaged | return | correction | manual | other
	 * @param string $note         optional free-text note
	 *
	 * @return array{ok:bool, qty_before:?int, qty_after:?int, message?:string}
	 */
	public static function adjust_stock( int $product_id, int $variation_id, string $action, int $qty, string $reason = 'manual', string $note = '' ): array {
		global $wpdb;

		$action = in_array( $action, [ 'add', 'remove', 'set' ], true ) ? $action : 'set';
		$qty    = max( 0, $qty );

		if ( $variation_id ) {
			$table = $wpdb->prefix . 'storeengine_product_variations';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct read on a custom StoreEngine variations table; prepared with %i/%d placeholders.
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, manage_stock, stock_quantity, backorders FROM %i WHERE id = %d', $table, $variation_id ) );

			if ( ! $row ) {
				return [ 'ok' => false, 'qty_before' => null, 'qty_after' => null, 'message' => 'variation_not_found' ];
			}

			$qty_before = null === $row->stock_quantity ? 0 : (int) $row->stock_quantity;
			$qty_after  = self::compute_new_qty( $action, $qty, $qty_before );
			$qty_change = $qty_after - $qty_before;
			$backorders = $row->backorders ?: 'no';
			$new_status = self::derive_stock_status( $qty_after, $backorders );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[
					'manage_stock'   => 1,
					'stock_quantity' => $qty_after,
					'stock_status'   => $new_status,
				],
				[ 'id' => $variation_id ],
				[ '%d', '%d', '%s' ],
				[ '%d' ]
			);
			// phpcs:enable
			wp_cache_delete( \StoreEngine\Classes\Variation::CACHE_KEY . $variation_id, \StoreEngine\Classes\Variation::CACHE_GROUP );
			wp_cache_flush_group( \StoreEngine\Classes\AbstractProduct::CACHE_GROUP );

			$old_status = $row->stock_quantity > 0 ? 'instock' : ( in_array( $backorders, [ 'yes', 'notify' ], true ) ? 'onbackorder' : 'outofstock' );
			if ( $old_status !== $new_status ) {
				do_action( 'storeengine/stock_status_changed', $product_id, $variation_id, $old_status, $new_status );
			}
		} else {
			// Simple product (or variable-product fallback) — operate on post meta.
			if ( ! $product_id ) {
				return [ 'ok' => false, 'qty_before' => null, 'qty_after' => null, 'message' => 'product_not_found' ];
			}

			$existing_qty   = get_post_meta( $product_id, '_storeengine_stock_quantity', true );
			$qty_before     = ( '' === $existing_qty || null === $existing_qty ) ? 0 : (int) $existing_qty;
			$qty_after      = self::compute_new_qty( $action, $qty, $qty_before );
			$qty_change     = $qty_after - $qty_before;
			$backorders     = get_post_meta( $product_id, '_storeengine_backorders', true ) ?: 'no';
			$new_status     = self::derive_stock_status( $qty_after, $backorders );
			$old_status_raw = get_post_meta( $product_id, '_storeengine_stock_status', true );

			update_post_meta( $product_id, '_storeengine_manage_stock', true );
			update_post_meta( $product_id, '_storeengine_stock_quantity', $qty_after );
			update_post_meta( $product_id, '_storeengine_stock_status', $new_status );

			wp_cache_flush_group( \StoreEngine\Classes\AbstractProduct::CACHE_GROUP );

			if ( $old_status_raw && $old_status_raw !== $new_status ) {
				do_action( 'storeengine/stock_status_changed', $product_id, 0, $old_status_raw, $new_status );
			}
		}

		$movement_type = self::TYPE_MANUAL;
		if ( in_array( $reason, [ 'received', 'damaged', 'return', 'correction' ], true ) ) {
			$movement_type = $reason;
		}

		self::record_movement( [
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'type'         => $movement_type,
			'reason'       => $reason,
			'qty_change'   => $qty_change,
			'qty_before'   => $qty_before,
			'qty_after'    => $qty_after,
			'note'         => $note,
			'user_id'      => get_current_user_id() ?: null,
		] );

		return [
			'ok'           => true,
			'qty_before'   => $qty_before,
			'qty_after'    => $qty_after,
			'qty_change'   => $qty_change,
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
		];
	}

	protected static function compute_new_qty( string $action, int $qty, int $current ): int {
		if ( 'add' === $action ) {
			return $current + $qty;
		}
		if ( 'remove' === $action ) {
			return $current - $qty;
		}
		return $qty; // set
	}

	protected static function derive_stock_status( int $qty, string $backorders ): string {
		$allows_backorder = in_array( $backorders, [ 'yes', 'notify' ], true );

		if ( $qty <= 0 && ! $allows_backorder ) {
			return 'outofstock';
		}
		if ( $qty <= 0 && $allows_backorder ) {
			return 'onbackorder';
		}
		return 'instock';
	}

	public static function record_movement( array $args ): int {
		global $wpdb;

		$defaults = [
			'product_id'   => 0,
			'variation_id' => 0,
			'type'         => self::TYPE_MANUAL,
			'reason'       => null,
			'qty_change'   => 0,
			'qty_before'   => null,
			'qty_after'    => null,
			'note'         => null,
			'user_id'      => null,
			'order_id'     => null,
			'vendor_id'    => null,
		];

		$args               = array_merge( $defaults, $args );
		$args['created_at'] = current_time( 'mysql' );

		// Backfill vendor_id from product post_author so the stock log carries
		// vendor identity at write-time. Avoids a brittle reverse-join later.
		if ( empty( $args['vendor_id'] ) && ! empty( $args['product_id'] ) ) {
			$author = (int) get_post_field( 'post_author', (int) $args['product_id'] );
			if ( $author > 0 ) {
				$args['vendor_id'] = $author;
			}
		}

		/**
		 * Filter movement-row args before insert. Multi-location addons hook
		 * this to inject `location_id` so the column (added by inventory-pro's
		 * installer) gets populated.
		 *
		 * @param array $args
		 */
		$args = apply_filters( 'storeengine/stock/record_movement_args', $args );

		$table = $wpdb->prefix . 'storeengine_stock_movements';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $table, $args );
		// phpcs:enable

		return (int) $wpdb->insert_id;
	}

	public static function get_movements( int $product_id, int $variation_id = 0, int $limit = 50, int $offset = 0 ): array {
		return self::query_movements( [
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'limit'        => $limit,
			'offset'       => $offset,
		] );
	}

	/**
	 * Filterable movement query used by the inventory-pro addon.
	 *
	 * @param array{product_id?:int,variation_id?:int,location_id?:int,type?:string,from?:string,to?:string,limit?:int,offset?:int} $args
	 * @return array<int,object>
	 */
	public static function query_movements( array $args ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'storeengine_stock_movements';

		$where  = [];
		$values = [];

		if ( ! empty( $args['product_id'] ) ) {
			$where[]  = 'product_id = %d';
			$values[] = (int) $args['product_id'];
		}

		if ( ! empty( $args['variation_id'] ) ) {
			$where[]  = 'variation_id = %d';
			$values[] = (int) $args['variation_id'];
		}

		if ( ! empty( $args['location_id'] ) ) {
			$where[]  = 'location_id = %d';
			$values[] = (int) $args['location_id'];
		}

		if ( ! empty( $args['vendor_id'] ) ) {
			$where[]  = 'vendor_id = %d';
			$values[] = (int) $args['vendor_id'];
		}

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$values[] = (string) $args['type'];
		}

		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = (string) $args['from'];
		}

		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = (string) $args['to'];
		}

		$where_sql = $where ? ( ' WHERE ' . implode( ' AND ', $where ) ) : '';
		$limit     = max( 1, (int) ( $args['limit'] ?? 50 ) );
		$offset    = max( 0, (int) ( $args['offset'] ?? 0 ) );

		// Table via %i, dynamic WHERE built from literal fragments + %d/%s value
		// placeholders (see $where above); the LIMIT/OFFSET are appended as %d.
		$sql = 'SELECT * FROM %i' . $where_sql . ' ORDER BY id DESC LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on a custom StoreEngine table; $sql is literal-only with %i/%d/%s placeholders passed to prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( [ $table ], $values, [ $limit, $offset ] ) ) );

		return is_array( $rows ) ? $rows : [];
	}

	public static function reserve_stock_for_order( int $order_id, array $items, int $minutes = 60 ): void {
		global $wpdb;

		$table   = $wpdb->prefix . 'storeengine_reserved_stock';
		$expires = gmdate( 'Y-m-d H:i:s', time() + ( $minutes * MINUTE_IN_SECONDS ) );

		// Clear any prior reservation rows for this order to avoid stale entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row delete on a custom StoreEngine reservations table; no cache layer applies.
		$wpdb->delete( $table, [ 'order_id' => $order_id ], [ '%d' ] );

		foreach ( $items as $item ) {
			$product_id   = (int) ( $item['product_id'] ?? 0 );
			$variation_id = (int) ( $item['variation_id'] ?? 0 );
			$qty          = (int) ( $item['quantity'] ?? 0 );

			if ( ! $product_id || $qty <= 0 ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row upsert on a custom StoreEngine reservations table; no cache layer applies.
			$wpdb->replace(
				$table,
				[
					'order_id'       => $order_id,
					'product_id'     => $product_id,
					'variation_id'   => $variation_id,
					'stock_quantity' => $qty,
					'expires'        => $expires,
				],
				[ '%d', '%d', '%d', '%d', '%s' ]
			);
		}
	}

	public static function release_reserved_stock( int $order_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'storeengine_reserved_stock';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row delete on a custom StoreEngine reservations table; no cache layer applies.
		$wpdb->delete( $table, [ 'order_id' => $order_id ], [ '%d' ] );
	}

	public static function release_expired_reservations(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'storeengine_reserved_stock';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup delete on a custom StoreEngine reservations table; prepared with %i/%s placeholders.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires <= %s', $table, current_time( 'mysql', true ) ) );
	}
}
