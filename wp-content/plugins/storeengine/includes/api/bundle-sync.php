<?php
/**
 * Bundle sync REST controller.
 *
 * Retroactively grants every product currently in a bundle to everyone who has
 * already purchased that bundle. A bundle's contents are snapshotted into the
 * order line-item `_bundles` meta at checkout, so products added to a bundle
 * later never reach past buyers. This endpoint walks the paid orders containing
 * the bundle (in batches), merges the missing items into each order's snapshot,
 * grants download permissions for them, and fires an action add-ons listen to
 * (e.g. license management) to backfill their own grants. Every step is
 * idempotent, so re-running grants nothing new.
 *
 * @package StoreEngine\API
 */

namespace StoreEngine\API;

use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Hooks\DownloadPermissionHooks;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BundleSync extends AbstractRestApiController {

	protected $rest_base = 'bundle';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/sync-purchasers', [
			'args' => [
				'id' => [
					'description' => __( 'Bundle product ID.', 'storeengine' ),
					'type'        => 'integer',
					'required'    => true,
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_purchasers' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'page'     => [
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					],
					'per_page' => [
						'type'    => 'integer',
						'default' => 25,
						'minimum' => 1,
						'maximum' => 100,
					],
				],
			],
		] );
	}

	public function permissions_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	/**
	 * Process one page of paid orders containing the bundle.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function sync_purchasers( WP_REST_Request $request ) {
		global $wpdb;

		$bundle_id = absint( $request->get_param( 'id' ) );
		$page      = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page  = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );

		$product = Helper::get_product( $bundle_id );
		if ( ! $product || 'bundled' !== $product->get_type() ) {
			return new WP_Error( 'invalid-bundle', __( 'Bundle product not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$bundle_items = $product->get_bundles();
		if ( empty( $bundle_items ) ) {
			return new WP_Error( 'empty-bundle', __( 'This bundle has no items to sync.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$paid_statuses = Helper::get_order_paid_statuses();
		if ( empty( $paid_statuses ) ) {
			return new WP_Error( 'no-paid-statuses', __( 'No paid order statuses are configured.', 'storeengine' ), [ 'status' => 400 ] );
		}
		$placeholders = implode( ',', array_fill( 0, count( $paid_statuses ), '%s' ) );

		$items_table  = $wpdb->prefix . 'storeengine_order_items';
		$meta_table   = $wpdb->prefix . 'storeengine_order_item_meta';
		$orders_table = $wpdb->prefix . 'storeengine_orders';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted $wpdb->prefix table identifiers interpolated; user values bound via prepare() with a dynamic %s IN() list (count is correct at runtime); custom StoreEngine tables.
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT( DISTINCT oi.order_id )
			FROM {$items_table} oi
			INNER JOIN {$meta_table} oim ON oim.order_item_id = oi.order_item_id
			INNER JOIN {$orders_table} o ON o.id = oi.order_id
			WHERE oi.order_item_type = 'line_item'
				AND oim.meta_key = '_product_id'
				AND oim.meta_value = %d
				AND o.status IN ( {$placeholders} )",
			array_merge( [ $bundle_id ], $paid_statuses )
		) );

		$offset    = ( $page - 1 ) * $per_page;
		$order_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT oi.order_id
			FROM {$items_table} oi
			INNER JOIN {$meta_table} oim ON oim.order_item_id = oi.order_item_id
			INNER JOIN {$orders_table} o ON o.id = oi.order_id
			WHERE oi.order_item_type = 'line_item'
				AND oim.meta_key = '_product_id'
				AND oim.meta_value = %d
				AND o.status IN ( {$placeholders} )
			ORDER BY oi.order_id ASC
			LIMIT %d OFFSET %d",
			array_merge( [ $bundle_id ], $paid_statuses, [ $per_page, $offset ] )
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$processed         = 0;
		$granted_downloads = 0;
		$synced_orders     = 0;

		foreach ( $order_ids as $order_id ) {
			$order = Helper::get_order( (int) $order_id );
			if ( is_wp_error( $order ) || ! $order ) {
				continue;
			}

			$result = $this->sync_order( $order, $bundle_id, $bundle_items );

			$processed++;
			$granted_downloads += $result['downloads'];
			if ( ! empty( $result['new_products'] ) ) {
				$synced_orders++;
			}
		}

		$done = ( $offset + $per_page ) >= $total;

		return rest_ensure_response( [
			'total'             => $total,
			'page'              => $page,
			'per_page'          => $per_page,
			'processed'         => $processed,
			'granted_downloads' => $granted_downloads,
			'synced_orders'     => $synced_orders,
			'done'              => $done,
			'next_page'         => $done ? null : ( $page + 1 ),
		] );
	}

	/**
	 * Ensure one order carries every current bundle item.
	 *
	 * Merges items missing from the order line's `_bundles` snapshot, grants
	 * download permissions for the newly-added products, and fires an action so
	 * add-ons can backfill their own grants (e.g. licenses).
	 *
	 * @param AbstractOrder $order
	 * @param int           $bundle_id
	 * @param array         $bundle_items Current bundle contents from BundledProduct::get_bundles().
	 *
	 * @return array{downloads:int,new_products:int[]}
	 */
	protected function sync_order( AbstractOrder $order, int $bundle_id, array $bundle_items ): array {
		$downloads    = 0;
		$new_products = [];

		foreach ( $order->get_line_product_items() as $item ) {
			if ( 'bundled' !== $item->get_product_type() || $bundle_id !== $item->get_product_id() ) {
				continue;
			}

			$snapshot     = (array) $item->get_meta( '_bundles' );
			$existing_ids = array_map( 'intval', array_filter( wp_list_pluck( $snapshot, 'product_id' ) ) );

			$added = false;
			foreach ( $bundle_items as $bundle_item ) {
				$pid = (int) ( $bundle_item['product_id'] ?? 0 );
				if ( ! $pid || in_array( $pid, $existing_ids, true ) ) {
					continue;
				}

				$snapshot[]     = $bundle_item;
				$existing_ids[] = $pid;
				$new_products[] = $pid;
				$added          = true;
			}

			if ( $added ) {
				$item->update_meta_data( '_bundles', $snapshot );
				$item->save_meta_data();
			}
		}

		$new_products = array_values( array_unique( $new_products ) );

		foreach ( $new_products as $pid ) {
			$downloads += DownloadPermissionHooks::init()->grant_product_to_order( $order, $pid );
		}

		if ( $new_products ) {
			/**
			 * Fires after newly-added bundle items are granted to an existing purchaser's order.
			 *
			 * @param AbstractOrder $order        The buyer's order.
			 * @param int[]         $new_products Product IDs newly granted on this order.
			 * @param int           $bundle_id    The bundle product being synced.
			 */
			do_action( 'storeengine/bundle/order_synced', $order, $new_products, $bundle_id );
		}

		return [
			'downloads'    => $downloads,
			'new_products' => $new_products,
		];
	}
}
