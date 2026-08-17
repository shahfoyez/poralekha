<?php

namespace StoreEngine\Hooks;

use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Classes\DownloadPermission;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DownloadPermissionHooks {

	protected static ?DownloadPermissionHooks $instance = null;

	public static function init(): DownloadPermissionHooks {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		// storeengine/checkout/after_place_order -> $self -> check_if_paid_after_checkout
		// storeengine/order/status_changed -> $self -> check_if_paid_after_status_change
		add_action( 'storeengine/order/payment_status_changed', [ $this, 'order_payment_status_changed' ], 10, 2 );
	}

	/**
	 * Internal hook callback.
	 *
	 * @param AbstractOrder $order
	 * @param $status
	 *
	 * @return void
	 */
	public function order_payment_status_changed( AbstractOrder $order, $status ) {
		$this->set_download_permissions( $order, 'paid' === $status );
	}

	public function set_download_permissions( AbstractOrder $order, bool $allow ) {
		if ( $allow ) {
			$price_type = $order->is_type( 'order' ) ? 'onetime' : $order->get_type();
			$this->give_permissions( $order, $price_type );
			return;
		}

		$this->delete_permissions( $order );
	}

	/**
	 * @param AbstractOrder $order
	 *
	 * @return void
	 * @deprecated v1.7.5
	 * @see self::order_payment_status_changed()
	 */
	public function check_if_paid_after_checkout( AbstractOrder $order ) {
		if ( in_array( $order->get_status(), Helper::get_order_paid_statuses(), true ) ) {
			$this->give_permissions( $order );
		}
	}

	/**
	 * @param $order_id
	 * @param $old_status
	 * @param $new_status
	 * @param $order
	 *
	 * @return void
	 * @deprecated 1.7.5
	 * @see self::order_payment_status_changed()
	 */
	public function check_if_paid_after_status_change( $order_id, $old_status, $new_status, $order ) {
		if ( ( in_array( $new_status, Helper::get_order_paid_statuses(), true ) && in_array( $old_status, Helper::get_order_paid_statuses(), true ) ) || ( ! in_array( $new_status, Helper::get_order_paid_statuses(), true ) && ! in_array( $old_status, Helper::get_order_paid_statuses(), true ) ) ) {
			return;
		}

		if ( ! in_array( $new_status, Helper::get_order_paid_statuses(), true ) && in_array( $old_status, Helper::get_order_paid_statuses(), true ) ) {
			$this->delete_permissions( $order );

			return;
		}

		if ( in_array( $new_status, Helper::get_order_paid_statuses(), true ) && ! in_array( $old_status, Helper::get_order_paid_statuses(), true ) ) {
			$this->give_permissions( $order );
		}
	}

	protected function delete_permissions( AbstractOrder $order ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $wpdb->prefix . 'storeengine_downloadable_product_permissions', [ 'order_id' => $order->get_id() ], [ '%d' ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		wp_cache_flush_group( DownloadPermission::CACHE_GROUP );
	}

	/**
	 * @param AbstractOrder $order
	 * @param string $price_type
	 *
	 * @return void
	 */
	protected function give_permissions( AbstractOrder $order, string $price_type = 'onetime' ) {
		global $wpdb;

		$product_data = [];
		foreach ( $order->get_line_product_items() as $order_item ) {
			if ( $price_type !== $order_item->get_price_type() ) {
				continue;
			}

			if ( 'bundled' === $order_item->get_product_type() ) {

				$bundles = $order_item->get_meta( '_bundles' );

				foreach ( $bundles as [ 'product_id' => $product_id ] ) {
					if ( ! empty( $product_data[ $product_id ] ) ) {
						continue;
					}

					$data = $this->get_product_download_data( $product_id );

					if ( ! $data ) {
						continue;
					}

					$product_data[ $product_id ] = $data;
				}

				continue;
			}

			$product_id = $order_item->get_product_id();

			if ( ! empty( $product_data[ $product_id ] ) || 'digital' !== $order_item->get_shipping_type() ) {
				continue;
			}

			$data = $this->get_product_download_data( $product_id );

			if ( ! $data ) {
				continue;
			}

			$product_data[ $product_id ] = $data;
		}

		if ( empty( $product_data ) ) {
			return;
		}

		$rows = [];

		foreach ( $product_data as $product_id => $downloadable_files ) {
			foreach ( $downloadable_files as $download_id ) {
				$rows[] = $wpdb->prepare(
					'( %d, %d, %s, %d, %s )',
					$order->get_customer_id(),
					$order->get_id(),
					$download_id,
					$product_id,
					current_time( 'mysql', 1 )
				);
			}
		}

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( count( $rows ) > 60 ) {
			foreach ( array_chunk( $rows, 50 ) as $chunk ) {
				$wpdb->query( "INSERT INTO {$wpdb->prefix}storeengine_downloadable_product_permissions ( user_id, order_id, download_id, product_id, access_granted  ) VALUES " . implode( ',', $chunk ) );
			}
		} else {
			$wpdb->query( "INSERT INTO {$wpdb->prefix}storeengine_downloadable_product_permissions ( user_id, order_id, download_id, product_id, access_granted  ) VALUES " . implode( ',', $rows ) );
		}

		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_cache_flush_group( DownloadPermission::CACHE_GROUP );
	}

	/**
	 * Grant download permissions for a single product on an already-paid order,
	 * skipping any download files already granted. Idempotent.
	 *
	 * Used by the bundle "sync to existing buyers" feature to retroactively give
	 * past purchasers access to a product newly added to a bundle. Unlike
	 * {@see self::give_permissions()} this never deletes existing rows, so the
	 * download counts/limits on already-granted files are preserved.
	 *
	 * @param AbstractOrder $order
	 * @param int           $product_id
	 *
	 * @return int Number of permission rows inserted.
	 */
	public function grant_product_to_order( AbstractOrder $order, int $product_id ): int {
		global $wpdb;

		$download_ids = $this->get_product_download_data( $product_id );
		if ( ! $download_ids ) {
			return 0;
		}

		$table = $wpdb->prefix . 'storeengine_downloadable_product_permissions';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%d) query on a custom StoreEngine table; $table is a plugin-internal name, not user input.
		$existing = $wpdb->get_col( $wpdb->prepare(
			"SELECT download_id FROM {$table} WHERE order_id = %d AND product_id = %d",
			$order->get_id(),
			$product_id
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$missing = array_diff( array_map( 'strval', $download_ids ), array_map( 'strval', $existing ) );
		if ( ! $missing ) {
			return 0;
		}

		$rows = [];
		foreach ( $missing as $download_id ) {
			$rows[] = $wpdb->prepare(
				'( %d, %d, %s, %d, %s )',
				$order->get_customer_id(),
				$order->get_id(),
				$download_id,
				$product_id,
				current_time( 'mysql', 1 )
			);
		}

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "INSERT INTO {$table} ( user_id, order_id, download_id, product_id, access_granted ) VALUES " . implode( ',', $rows ) );
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching

		wp_cache_flush_group( DownloadPermission::CACHE_GROUP );

		return count( $rows );
	}

	protected function get_product_download_data( int $product_id ): ?array {
		// @TODO Check product meta if product is digital/downloadable.
		// @TODO If admin try to change shipping type to physical show a confirmation popup
		//       that it would delete/remove the downloadable files.
		//       And, let them back-it-up or use different or create new product.
		$downloadable_files = get_post_meta( $product_id, '_storeengine_product_downloadable_files', true );
		if ( empty( $downloadable_files ) ) {
			return null;
		}
		$downloadable_files = maybe_unserialize( $downloadable_files );
		if ( ! is_array( $downloadable_files ) ) {
			return null;
		}

		return wp_list_pluck( $downloadable_files, 'id' );
	}
}
