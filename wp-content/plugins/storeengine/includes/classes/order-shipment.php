<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records a shipment against a single order line item.
 *
 * Single source of truth for the per-item fulfilment flow shared by the admin
 * order screen and the multi-vendor vendor dashboard, so the two can't drift:
 * validates the status (enum + forward-only + digital), writes the status to
 * the lookup row (keyed by order_item_id — never product_id, which would touch
 * duplicate-product lines), stores courier/tracking as order meta, logs an
 * order note, and fires the shipment + core delivery hooks.
 *
 * Callers layer their own authorisation on top (vendors check ownership; the
 * admin path is gated by capability at the AJAX layer).
 */
class OrderShipment {

	/**
	 * @param int    $order_item_id Lookup PRIMARY KEY of the line being shipped.
	 * @param string $new_status    One of Constants::get_shipping_statuses().
	 * @param array  $tracking      [courier, tracking_number, tracking_url].
	 * @param string $actor_label   Who performed it (vendor store / "Store admin").
	 *
	 * @return array|WP_Error Shipment result on success.
	 */
	public static function record( int $order_item_id, string $new_status, array $tracking = [], string $actor_label = '' ) {
		global $wpdb;

		$statuses = Constants::get_shipping_statuses();
		if ( ! in_array( $new_status, $statuses, true ) ) {
			return new WP_Error( 'bad_status', __( 'Invalid shipping status.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$lookup = $wpdb->prefix . 'storeengine_order_product_lookup';

		// Positive rows only — the commission calculator stores refund markers on
		// synthetic negative order_item_id rows that reuse this column.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct read of a custom StoreEngine lookup table; not cacheable per request.
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM %i WHERE order_item_id = %d AND order_item_id > 0',
			$lookup,
			$order_item_id
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Order item not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$order_id   = (int) $row->order_id;
		$product_id = (int) $row->product_id;

		if ( 'digital' === get_post_meta( $product_id, '_storeengine_product_shipping_type', true ) ) {
			return new WP_Error( 'not_shippable', __( 'Shipping is not applicable for digital products.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$current_status = (string) $row->shipping_status;
		$cur_idx        = array_search( $current_status, $statuses, true );
		$next_idx       = array_search( $new_status, $statuses, true );
		if ( false !== $cur_idx && false !== $next_idx && $next_idx < $cur_idx ) {
			return new WP_Error( 'backward_status', __( 'You cannot move back to a previous shipping status.', 'storeengine' ), [ 'status' => 409 ] );
		}

		// 1) Status on the lookup row, keyed by order_item_id.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$lookup,
			[ 'shipping_status' => $new_status ],
			[ 'order_item_id' => $order_item_id ],
			[ '%s' ],
			[ '%d' ]
		);
		// phpcs:enable

		// 2) Courier/tracking on the order entity meta (per line item).
		$order = Helper::get_order( $order_id );
		if ( is_wp_error( $order ) ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$meta_key = '_storeengine_shipment_' . $order_item_id;
		$existing = $order->get_meta( $meta_key );
		$existing = is_array( $existing ) ? $existing : [];

		$courier     = sanitize_text_field( (string) ( $tracking['courier'] ?? '' ) );
		$tracking_no = sanitize_text_field( (string) ( $tracking['tracking_number'] ?? '' ) );
		$track_url   = esc_url_raw( (string) ( $tracking['tracking_url'] ?? '' ) );

		$shipped_idx = array_search( Constants::SHIPPED, $statuses, true );
		$is_shipped  = ( false !== $next_idx && $next_idx >= $shipped_idx );

		$shipped_at = (string) ( $existing['shipped_at'] ?? '' );
		if ( ! $shipped_at && $is_shipped ) {
			$shipped_at = current_time( 'mysql', 1 );
		}

		$tracking_changed = ( $tracking_no !== (string) ( $existing['tracking_number'] ?? '' ) );

		$shipment = [
			'courier'         => $courier,
			'tracking_number' => $tracking_no,
			'tracking_url'    => $track_url,
			'shipped_at'      => $shipped_at,
			'vendor_id'       => (int) $row->vendor_id,
			'status'          => $new_status,
		];

		$order->update_meta_data( $meta_key, $shipment );
		$order->save();

		// 3) Order note (always).
		$item_name    = get_the_title( $product_id ) ?: ( '#' . $product_id );
		$status_label = Constants::get_shipping_status_label( $new_status );
		$actor        = $actor_label ?: __( 'Store admin', 'storeengine' );

		$note_parts = [
			sprintf(
				/* translators: 1: who shipped (vendor/admin), 2: item name, 3: status label */
				__( '%1$s updated “%2$s” to %3$s.', 'storeengine' ),
				$actor,
				$item_name,
				$status_label
			),
		];
		if ( $courier ) {
			/* translators: %s: courier name */
			$note_parts[] = sprintf( __( 'Courier: %s', 'storeengine' ), $courier );
		}
		if ( $tracking_no ) {
			/* translators: %s: tracking number */
			$note_parts[] = sprintf( __( 'Tracking: %s', 'storeengine' ), $tracking_no );
		}
		$order->add_order_note( implode( ' ', $note_parts ), 0, true );

		// 4) Customer notification — only on shipped-or-later AND either it just
		// entered shipped-territory or the tracking changed (no micro-spam).
		$entered_shipped = $is_shipped && ( false === $cur_idx || $cur_idx < $shipped_idx );
		if ( $is_shipped && ( $entered_shipped || $tracking_changed ) ) {
			do_action( 'storeengine/order/item_shipped', $order_id, $order_item_id, $product_id, $shipment, $new_status );
		}

		// 5) Core delivery hooks (same as the legacy admin shipping AJAX).
		do_action( 'storeengine/before_single_product_delivered', $product_id, $order_id, $current_status, $new_status );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct read of a custom StoreEngine lookup table; not cacheable per request.
		$not_delivered = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE order_id = %d AND order_item_id > 0 AND shipping_status <> %s',
			$lookup,
			$order_id,
			Constants::DELIVERED
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( 0 === $not_delivered ) {
			do_action( 'storeengine/all_product_delivered', $order_id );
		}

		do_action( 'storeengine/after_single_product_delivered', $product_id, $order_id, $current_status, $new_status );

		return [
			'order_item_id'   => $order_item_id,
			'order_id'        => $order_id,
			'shipping_status' => $new_status,
			'status_label'    => $status_label,
			'shipment'        => $shipment,
			'all_delivered'   => ( 0 === $not_delivered ),
		];
	}
}
