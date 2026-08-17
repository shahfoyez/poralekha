<?php

namespace StoreEngine\Cli;

use StoreEngine\Classes\OrderCollection;
use StoreEngine\Utils\Helper;
use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order extends WP_CLI_Command {

	/**
	 * List orders.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Order status to filter by (e.g. pending, processing, completed, on_hold, cancelled, refunded, failed).
	 *
	 * [--limit=<limit>]
	 * : Maximum number of orders to list. Defaults to 10. Set to -1 for all.
	 * ---
	 * default: 10
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine order list --status=completed --limit=50
	 *
	 * @subcommand list
	 */
	public function list_orders( $args, $assoc_args ) {
		$status = $assoc_args['status'] ?? '';
		$limit  = $assoc_args['limit'] ?? 10;

		$query_args = [
			'per_page' => $limit,
			'orderby'  => 'id',
			'order'    => 'DESC',
		];

		if ( $status ) {
			$query_args['where'][] = [
				'key'   => 'status',
				'value' => $status,
			];
		}

		$orders_collection = new OrderCollection( $query_args );
		$orders            = $orders_collection->get_results();

		if ( empty( $orders ) ) {
			WP_CLI::warning( 'No orders found.' );

			return;
		}

		$items = [];
		foreach ( $orders as $order ) {
			$items[] = [
				'ID'          => $order->get_id(),
				'Status'      => $order->get_status(),
				'Type'        => $order->get_type(),
				'Total'       => $order->get_total(),
				'Currency'    => $order->get_currency(),
				'Customer ID' => $order->get_customer_id(),
				'Date'        => $order->get_date_created_gmt() ? $order->get_date_created_gmt()->format( 'Y-m-d H:i:s' ) : '',
			];
		}

		WP_CLI\Utils\format_items( 'table', $items, [
			'ID',
			'Status',
			'Type',
			'Total',
			'Currency',
			'Customer ID',
			'Date'
		] );
	}

	/**
	 * Delete orders.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 * : Specific order ID to delete. Set to "all" to delete all orders.
	 *
	 * [--status=<status>]
	 * : Delete all orders matching a specific status.
	 *
	 * [--force]
	 * : Skip confirmation prompt.
	 *
	 * [--skip-trash]
	 * : Deletes directly from database without setting status to trash.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine order delete --id=123
	 *     wp storeengine order delete --status=cancelled --force
	 *
	 * @subcommand delete
	 */
	public function delete_orders( $args, $assoc_args ) {
		$id         = $assoc_args['id'] ?? null;
		$status     = $assoc_args['status'] ?? null;
		$force      = $assoc_args['force'] ?? false;
		$skip_trash = $assoc_args['skip-trash'] ?? false;

		if ( ! $id && ! $status ) {
			WP_CLI::error( 'Please provide either --id=<id>, --id=all, or --status=<status>' );
		}

		$orders_to_delete = [];

		if ( $id && 'all' !== $id ) {
			$order = Helper::get_order( $id );
			if ( ! $order || is_wp_error( $order ) ) {
				WP_CLI::error( "Order $id not found." );
			}
			$orders_to_delete[] = $order;
		} else {
			$query_args = [
				'per_page' => - 1,
			];

			if ( $status ) {
				$query_args['where'][] = [
					'key'   => 'status',
					'value' => $status,
				];
			}

			$orders_collection = new OrderCollection( $query_args );
			$orders_to_delete  = $orders_collection->get_results();

			if ( empty( $orders_to_delete ) ) {
				$msg = $status ? "No orders found with status '$status'." : "No orders found.";
				WP_CLI::success( $msg );

				return;
			}
		}

		$count = count( $orders_to_delete );

		if ( ! $force ) {
			WP_CLI::confirm( "Are you sure you want to delete $count order(s)?" );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting orders', $count );

		$deleted_count = 0;
		foreach ( $orders_to_delete as $order ) {
			try {
				if ( $order->delete( $skip_trash ) ) {
					$deleted_count ++;
				} else {
					WP_CLI::warning( "Failed to delete order {$order->get_id()}." );
				}
			} catch ( \Throwable $e ) {
				WP_CLI::warning( "Failed to delete order {$order->get_id()}. Error: {$e->getMessage()}" );
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( "Successfully deleted $deleted_count order(s)." );
	}
}
