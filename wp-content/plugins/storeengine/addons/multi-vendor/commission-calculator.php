<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;
use StoreEngine\Addons\MultiVendor\Classes\Vendors;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommissionCalculator {

	public static function init() {
		$self = new self();

		// Commission needs TWO things to be true: the order is paid, AND the
		// product-lookup rows (which carry vendor_id + product_net_revenue)
		// exist. Those rows are written by an async scheduled job
		// (`storeengine/store_product_lookup`, ~1s after the order is saved), so
		// the payment hook alone fires too early — the rows aren't there yet.
		//
		// So compute on BOTH events (compute_for_order() is idempotent — it
		// skips rows that already have a commission and rows without a vendor):
		//   1. when the order becomes paid (covers lookup-already-written case),
		//   2. after the lookup is (re)written for a paid order (covers the
		//      common async case + admin-created / mark-paid / mark-complete).
		//
		// NOTE: the previous `storeengine/order_payment_status_changed`
		// (underscore) hook only fired on a pending_payment→paid transition, so
		// it missed admin-created, force-paid, and mark-complete-from-processing
		// orders entirely. The slash variant below is the one every other
		// consumer (affiliate, subscriptions, …) uses.
		add_action( 'storeengine/order/payment_status_changed', [ $self, 'on_payment_status_changed' ], 20, 3 );
		add_action( 'storeengine/store_product_lookup', [ $self, 'on_lookup_stored' ], 20, 1 );

		// Reverse on refund.
		add_action( 'storeengine/order/status_changed', [ $self, 'on_status_changed' ], 20, 4 );
	}

	/**
	 * Fires whenever the order's paid_status changes. Compute on the transition
	 * into `paid`.
	 *
	 * @param mixed  $order      Order object.
	 * @param string $new_status New paid status.
	 * @param string $old_status Previous paid status.
	 */
	public function on_payment_status_changed( $order, $new_status, $old_status ) {
		unset( $old_status );
		if ( 'paid' !== $new_status ) {
			return;
		}
		$order_id = is_object( $order ) ? (int) $order->get_id() : (int) $order;
		$this->compute_for_order( $order_id );
	}

	/**
	 * Fires after the async lookup rows are written for an order. Compute only
	 * for paid orders (don't accrue commission on unpaid carts).
	 *
	 * @param int $order_id
	 */
	public function on_lookup_stored( $order_id ) {
		$order = Helper::get_order( (int) $order_id );
		if ( is_wp_error( $order ) || ! $order ) {
			return;
		}
		$is_paid = 'paid' === $order->get_paid_status()
			|| in_array( $order->get_status(), OrderStatus::get_is_paid_statuses(), true );
		if ( ! $is_paid ) {
			return;
		}
		$this->compute_for_order( (int) $order_id );
	}

	public function on_status_changed( $order_id, $old_status, $new_status, $order ) {
		unset( $order );
		if ( OrderStatus::REFUNDED !== $new_status ) {
			return;
		}
		// Skip if old status was already non-paid (no commission to reverse).
		if ( ! in_array( $old_status, OrderStatus::get_is_paid_statuses(), true ) ) {
			return;
		}
		$this->reverse_for_order( (int) $order_id );
	}

	protected function lookup_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storeengine_order_product_lookup';
	}

	/**
	 * Walk all lookup rows for the order and compute commission_amount.
	 * Idempotent: skips rows that already have a non-zero commission for the same vendor.
	 */
	public function compute_for_order( int $order_id ) {
		global $wpdb;

		$table = $this->lookup_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- %i identifier + %d value bound via prepare() on a custom StoreEngine lookup table; per-order read, not cacheable.
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT order_item_id, vendor_id, product_id, product_net_revenue, commission_amount FROM %i WHERE order_id = %d',
			$table,
			$order_id
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		foreach ( (array) $rows as $row ) {
			if ( (float) $row->commission_amount > 0 ) {
				continue; // Already computed.
			}
			$vendor_id = (int) $row->vendor_id;
			if ( $vendor_id <= 0 ) {
				continue; // No vendor.
			}

			$amount = $this->resolve_commission_for_vendor(
				$vendor_id,
				(float) $row->product_net_revenue
			);

			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				[ 'commission_amount' => $amount ],
				[ 'order_item_id' => (int) $row->order_item_id ]
			);
			// phpcs:enable
		}

		do_action( 'storeengine/multi_vendor/commission_computed', $order_id );
	}

	/**
	 * On refund, write a separate negative-amount lookup row per vendor line.
	 * Originals are not mutated, so SUM(commission_amount) gives the net balance.
	 */
	public function reverse_for_order( int $order_id ) {
		global $wpdb;

		$table = $this->lookup_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- %i identifier + %d value bound via prepare() on a custom StoreEngine lookup table; per-order read, not cacheable.
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM %i WHERE order_id = %d AND commission_amount > 0',
			$table,
			$order_id
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		foreach ( (array) $rows as $row ) {
			// Skip if a reversal row already exists.
			$ref_marker = 'refund:' . (int) $row->order_item_id;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- %i identifier + %d/%f/%s values bound via prepare() on a custom StoreEngine lookup table; per-order dedupe check, not cacheable.
			$exists = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE order_id = %d AND product_id = %d AND vendor_id = %d AND commission_amount = %f AND shipping_status = %s',
				$table,
				$order_id,
				(int) $row->product_id,
				(int) $row->vendor_id,
				-1 * (float) $row->commission_amount,
				$ref_marker
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

			if ( $exists ) {
				continue;
			}

			// Synthesize a unique negative order_item_id by negating the original.
			// Original positive ids never collide with negative ones.
			$negative_item_id = -1 * (int) $row->order_item_id;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, [
				'order_item_id'         => $negative_item_id,
				'order_id'              => $order_id,
				'product_id'            => (int) $row->product_id,
				'variation_id'          => (int) $row->variation_id,
				'price_id'              => (int) $row->price_id,
				'price'                 => -1 * (float) $row->price,
				'customer_id'           => (int) $row->customer_id,
				'vendor_id'             => (int) $row->vendor_id,
				'date_created'          => current_time( 'mysql', 1 ),
				'product_qty'           => -1 * (int) $row->product_qty,
				'product_net_revenue'   => -1 * (float) $row->product_net_revenue,
				'product_gross_revenue' => -1 * (float) $row->product_gross_revenue,
				'coupon_amount'         => -1 * (float) $row->coupon_amount,
				'tax_amount'            => -1 * (float) $row->tax_amount,
				'shipping_amount'       => -1 * (float) $row->shipping_amount,
				'shipping_tax_amount'   => -1 * (float) $row->shipping_tax_amount,
				'shipping_status'       => $ref_marker,
				'commission_amount'     => -1 * (float) $row->commission_amount,
			] );
			// phpcs:enable
		}

		do_action( 'storeengine/multi_vendor/commission_reversed', $order_id );
	}

	protected function resolve_commission_for_vendor( int $vendor_id, float $net_revenue ): float {
		$vendor = new Vendor( $vendor_id );

		$rate = $vendor->exists() && null !== $vendor->get_commission_rate()
			? (float) $vendor->get_commission_rate()
			: 0.0;
		$type = $vendor->exists() ? $vendor->get_commission_type() : 'percent';

		// Fall back to global default when the vendor has no override.
		if ( ! $vendor->exists() || null === $vendor->get_commission_rate() ) {
			$global = Vendors::get_global_commission();
			$rate   = (float) $global['rate'];
			$type   = (string) $global['type'];
		}

		if ( 'flat' === $type ) {
			return round( max( 0.0, $rate ), 2 );
		}

		// Percentage of net revenue.
		return round( max( 0.0, ( $rate / 100 ) * $net_revenue ), 2 );
	}
}
