<?php

namespace StoreEngine\Utils\traits;

use Exception;
use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\Exceptions\StoreEngineNotFoundException;
use StoreEngine\Classes\Order as OrderClass;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Classes\Orders;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\Refund;
use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\StringUtil;
use WP_Error;

trait Order {

	public static function order_type_classes() {
		return apply_filters( 'storeengine/order/classes', [
			'order'        => OrderClass::class,
			'refund_order' => Refund::class,
		] );
	}

	public static function get_order_type_class( string $type ): ?string {
		return self::order_type_classes()[ $type ] ?? null;
	}

	public static function get_order_paid_statuses(): array {
		return apply_filters( 'storeengine/order_paid_statuses', [ 'completed', 'payment_confirmed' ] );
	}

	/**
	 * @param false|int|AbstractOrder $order_id
	 *
	 * @return AbstractOrder|WP_Error
	 */
	public static function get_order( $order_id ) {
		try {
			$order_id = self::get_order_id( $order_id );

			if ( ! $order_id ) {
				return new WP_Error( 'order-not-found', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
			}

			$order = wp_cache_get( 'orders_' . $order_id, 'storeengine_orders' );

			if ( $order ) {
				if ( 0 === $order->get_id() ) {
					wp_cache_delete( 'orders_' . $order_id, 'storeengine_orders' );

					return new WP_Error( 'order-not-found', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
				}

				return $order;
			}

			$classname = self::get_class_name_for_order_id( $order_id );

			if ( ! $classname ) {
				return new WP_Error( 'order-class-not-found', __( 'Invalid order type.', 'storeengine' ), [ 'status' => 404 ] );
			}

			$order = new $classname( $order_id );

			if ( $order instanceof AbstractOrder ) {
				wp_cache_set( 'orders_' . $order_id, $order, 'storeengine_orders', HOUR_IN_SECONDS );
			}

			return $order;
		} catch ( StoreEngineException $e ) {
			Helper::log_error( $e );

			return $e->toWpError();
		} catch ( Exception $e ) {
			Helper::log_error( $e );

			return new WP_Error( 'unknown-error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * @param mixed $order
	 *
	 * @return false|int
	 */
	public static function get_order_id( $order ) {
		if ( false === $order ) {
			return self::get_global_order_id();
		} elseif ( is_numeric( $order ) ) {
			return absint( $order );
		} elseif ( $order instanceof AbstractOrder ) {
			return $order->get_id();
		} elseif ( ! empty( $order->ID ) ) {
			return (int) $order->ID;
		} else {
			return false;
		}
	}

	private static function get_global_order_id() {
		global $order;
		global $refund;
		global $subscription;

		if ( $order instanceof AbstractOrder ) {
			return $order->get_id();
		}
		if ( $refund instanceof AbstractOrder ) {
			return $refund->get_id();
		}
		if ( $subscription instanceof AbstractOrder ) {
			return $subscription->get_id();
		}

		return false;
	}

	/**
	 * Gets the class name an order instance should have based on its ID.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return string|false The class name or FALSE if the class does not exist.
	 */
	public static function get_class_name_for_order_id( int $order_id ) {
		$classnames = self::get_class_names_for_order_ids( [ $order_id ] );

		return $classnames[ $order_id ] ?? false;
	}

	/**
	 * Gets the class name bunch of order instances should have based on their IDs.
	 *
	 * @param array $order_ids Order IDs to get the class name for.
	 *
	 * @return array Array of order_id => class_name.
	 * @throws StoreEngineNotFoundException
	 */
	public static function get_class_names_for_order_ids( array $order_ids ): array {
		$order_types = self::get_orders_type( $order_ids );

		if ( empty( $order_types ) && 1 === count( $order_ids ) ) {
			throw new StoreEngineNotFoundException( esc_html__( 'Entry not found!', 'storeengine' ) );
		}

		return array_map( function ( $order_type ) {
			return self::get_order_type_class( $order_type );
		}, $order_types );
	}

	public static function get_order_type( $order_id ): string {
		return self::get_orders_type( [ $order_id ] )[ $order_id ] ?? '';
	}
	public static function get_orders_type( array $order_ids ): array {
		global $wpdb;

		if ( empty( $order_ids ) ) {
			return [];
		}

		$order_types   = [];
		$key_map       = array_combine( $order_ids, array_map( fn( $key ) => 'oder_type_' . $key, $order_ids ) );
		$cached_values = wp_cache_get_multiple( array_values( $key_map ), 'storeengine_orders' );

		foreach ( $key_map as $key => $prefixed_key ) {
			if ( isset( $cached_values[ $prefixed_key ] ) && false !== $cached_values[ $prefixed_key ] ) {
				$order_types[ $key ] = $cached_values[ $prefixed_key ];
			}
		}

		// Remaining order ids.
		$order_ids = array_diff( $order_ids, array_keys( $order_types ) );

		if ( $order_ids ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Data cached.
			$order_ids_placeholder = implode( ', ', array_fill( 0, count( $order_ids ), '%d' ) );
			$results               = $wpdb->get_results( $wpdb->prepare( "SELECT id, type FROM {$wpdb->prefix}storeengine_orders WHERE id IN ( $order_ids_placeholder );", $order_ids ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Data cached.

			foreach ( $results as $row ) {
				$order_types[ $row->id ] = $row->type;
			}

			$objects = array_combine( array_map( fn( $key ) => 'oder_type_' . $key, array_keys( $order_types ) ), $order_types );

			wp_cache_set_multiple( $objects, 'storeengine_orders', HOUR_IN_SECONDS );
		}

		return $order_types;
	}

	/**
	 * Finds an Order ID based on an order key.
	 *
	 * @param string $order_key An order key has generated by.
	 *
	 * @return int The ID of an order, or 0 if the order could not be found
	 */
	public static function get_order_id_by_order_key( string $order_key ): int {
		global $wpdb;
		if ( empty( $order_key ) ) {
			return 0;
		}

		$id = wp_cache_get( 'order:key:' . $order_key, 'storeengine_orders' );

		if ( false !== $id ) {
			return (int) $id;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Data cached.
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}storeengine_order_operational_data WHERE order_key = %s LIMIT 1;", $order_key ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Data cached.

		wp_cache_set( 'order:key:' . $order_key, $id, 'storeengine_orders', DAY_IN_SECONDS );

		return $id;
	}

	public static function get_order_by_key( string $key ) {
		return self::get_order( self::get_order_id_by_order_key( $key ) );
	}

	public static function get_order_id_by_meta( string $key, $value = null ): int {
		global $wpdb;

		if ( ! is_scalar( $value ) ) {
			StoreEngineInvalidArgumentException::throw(
				sprintf(
				/* translators: %s: Argument type. */
					__( 'Invalid argument provided. Value (meta_value) must be string, int or float, %s provided.', 'storeengine' ),
					gettype( $value )
				)
			);
		}

		if ( is_bool( $value ) ) {
			$value = (int) $value;
		}

		$cache_key = 'order:meta' . $key . '_' . $value;
		$id        = wp_cache_get( $cache_key, 'storeengine_orders' );

		if ( false !== $id ) {
			return (int) $id;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared for value type and cached the result.

		$format = is_float( $value ) ? '%s' : ( is_numeric( $value ) ? '%d' : '%s' );
		$id     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}storeengine_orders_meta WHERE meta_key = %s AND meta_value = {$format} ORDER BY order_id DESC LIMIT 1;", $key, $value ) );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_cache_set( $cache_key, $id, 'storeengine_orders', DAY_IN_SECONDS );

		return $id;
	}

	public static function get_order_by_meta( string $key, $value ) {
		return self::get_order( self::get_order_id_by_meta( $key, $value ) );
	}

	/**
	 * Get orders of customer.
	 *
	 * @param int $customer_id [Optional] Customer id. Default to zero (current user).
	 *
	 * @return OrderClass[]
	 * @see OrderCollection
	 *
	 * @deprecated Use Order collection class to query and count results together.
	 */
	public static function get_customer_orders( int $customer_id = 0, int $page = 1, int $per_page = 10 ): array {
		return ( new Orders( $page, $per_page ) )->get( array(
			'customer_id' => array(
				'condition' => '=',
				'formatter' => '%d',
				'value'     => 0 !== $customer_id ? $customer_id : get_current_user_id(),
			),
		) );
	}

	public static function get_payment_data( OrderClass $order ): array {
		$data = [
			'id'                       => $order->get_id(),
			'status'                   => $order->get_status(),
			'customer_id'              => $order->get_customer_id(),
			'total_amount'             => $order->get_total(),
			'date_created_gmt'         => $order->get_date_created_gmt() ? $order->get_date_created_gmt()->format( 'Y-m-d H:i:s' ) : null,
			'date_updated_gmt'         => $order->get_date_updated_gmt() ? $order->get_date_updated_gmt()->format( 'Y-m-d H:i:s' ) : null,
			'payment_method'           => $order->get_payment_method(),
			'payment_method_title'     => $order->get_payment_method_title(),
			'refunds_total'            => $order->get_total_refunded(),
			'refunded_amount'          => $order->get_total_refunded(),
			'can_refund'               => false,
			'gateway_can_refund_order' => false,
			'currency'                 => $order->get_currency(),
			'is_paid'                  => $order->is_paid(),
		];

		if ( $order->is_paid() && 'refunded' !== $order->get_status() ) {
			$data['can_refund'] = (bool) apply_filters(
				'storeengine/refund/can_admin_refund_order',
				(
					0 < $order->get_total() - $order->get_total_refunded() ||
					0 < absint( $order->get_item_count() - $order->get_item_count_refunded() )
				),
				$order->get_id(),
				$order
			);

			$payment_gateway = Helper::get_payment_gateway_by_order( $order );

			if ( false !== $payment_gateway ) {
				$data['gateway_name'] = ( ! empty( $payment_gateway->method_title ) ? $payment_gateway->method_title : $payment_gateway->get_title() );

				if ( $payment_gateway->can_refund_order( $order ) ) {
					$data['gateway_can_refund_order'] = true;
				}
			} else {
				$data['gateway_name'] = __( 'Payment gateway', 'storeengine' );
			}
		}

		return $data;
	}

	/**
	 * Get orders by page and conditions.
	 *
	 * @param array $args Conditional Args.
	 * @param array $pagination Pagination array.
	 *
	 * @return OrderClass[]
	 * @see OrderCollection
	 *
	 * @deprecated Use Order collection class to query and count results together.
	 */
	public static function get_orders( array $args = [], array $pagination = [] ): array {
		$pagination = wp_parse_args( $pagination, [
			'page'     => 1,
			'per_page' => 10,
		] );

		return ( new Orders( $pagination['page'], $pagination['per_page'] ) )->get( $args );
	}

	/**
	 * @param array $conditions
	 *
	 * @return int
	 * @see OrderCollection
	 *
	 * @deprecated Use Order collection class to query and count results together.
	 */
	public static function get_total_orders_count( array $conditions = [] ): int {
		return ( new Orders() )->get_total_orders_count( $conditions );
	}

	public static function get_recent_draft_order( int $customer_id = 0, ?string $cart_hash = null, bool $create = true ) {
		return ( new OrderClass() )->get_recent_draft_order( $customer_id, null, $create );
	}

	public static function create_refund( $args = [] ) {
		$default_args = [
			'amount'         => 0,
			'reason'         => null,
			'order_id'       => 0,
			'refund_id'      => 0,
			'line_items'     => [],
			'refund_payment' => false,
			'restock_items'  => false,
		];

		try {
			$args  = wp_parse_args( $args, $default_args );
			$order = self::get_order( absint( $args['order_id'] ) );

			if ( is_wp_error( $order ) ) {
				throw new StoreEngineException( esc_html__( 'Invalid order ID.', 'storeengine' ), 'invalid-order-id' );
			}

			$remaining_refund_amount     = $order->get_remaining_refund_amount();
			$remaining_refund_items      = $order->get_remaining_refund_items();
			$refund_item_count           = 0;
			$refund                      = new Refund( $args['refund_id'] ); // @TODO should use self::get_order( $args['refund_id'] );
			$refunded_order_and_products = [];

			if ( 0 > $args['amount'] || $args['amount'] > $remaining_refund_amount ) {
				throw new StoreEngineException( esc_html__( 'Invalid refund amount.', 'storeengine' ), 'invalid-refund-amount' );
			}

			$refund->set_currency( $order->get_currency() );
			$refund->set_amount( $args['amount'] );
			$refund->set_status( OrderStatus::COMPLETED );
			$refund->set_parent_order_id( $order->get_id() );
			$refund->set_customer_id( $order->get_customer_id() );
			$refund->set_refunded_by( get_current_user_id() );
			$refund->set_prices_include_tax( $order->get_prices_include_tax() );

			if ( ! StringUtil::is_null_or_whitespace( $args['reason'] ) ) {
				$refund->set_reason( (string) $args['reason'] );
			}

			// Negative line items.
			if ( is_array( $args['line_items'] ) && count( $args['line_items'] ) > 0 ) {
				$items = $order->get_items( [ 'line_item', 'fee', 'shipping' ] );

				foreach ( $items as $item_id => $item ) {
					if ( ! isset( $args['line_items'][ $item_id ] ) ) {
						continue;
					}

					$qty          = $args['line_items'][ $item_id ]['qty'] ?? 0;
					$refund_total = $args['line_items'][ $item_id ]['refund_total'];
					$refund_tax   = isset( $args['line_items'][ $item_id ]['refund_tax'] ) ? array_filter( (array) $args['line_items'][ $item_id ]['refund_tax'] ) : [];

					if ( empty( $qty ) && empty( $refund_total ) && empty( $args['line_items'][ $item_id ]['refund_tax'] ) ) {
						continue;
					}

					// array of order id and product id which were refunded.
					// later to be used for revoking download permission.
					// checking if the item is a product, as we only need to revoke download permission for products.
					if ( $item->is_type( 'line_item' ) ) {
						$refunded_order_and_products[ $item_id ] = [
							'order_id'   => $order->get_id(),
							'product_id' => $item->get_product_id(),
						];
					}

					$class         = get_class( $item );
					$refunded_item = new $class( $item );
					$refunded_item->set_id( 0 );
					$refunded_item->add_meta_data( '_refunded_item_id', $item_id, true );
					$refunded_item->set_total( Formatting::format_refund_total( $refund_total ) );
					$refunded_item->set_taxes( [
						'total'    => array_map( [ Formatting::class, 'format_refund_total' ], $refund_tax ),
						'subtotal' => array_map( [ Formatting::class, 'format_refund_total' ], $refund_tax ),
					] );

					if ( is_callable( [ $refunded_item, 'set_subtotal' ] ) ) {
						$refunded_item->set_subtotal( Formatting::format_refund_total( $refund_total ) );
					}

					if ( is_callable( [ $refunded_item, 'set_quantity' ] ) ) {
						$refunded_item->set_quantity( $qty * - 1 );
					}

					$refund->add_item( $refunded_item );
					$refund_item_count += $qty;
				}
			}

			$refund->update_taxes();
			$refund->calculate_totals( false );
			$refund->set_total( $args['amount'] * - 1 );

			// this should remain after update_taxes(), as this will save the order, and write the current date to the db
			// so we must wait until the order is persisted to set the date.
			if ( isset( $args['date_created'] ) ) {
				$refund->set_date_created( $args['date_created'] );
			}

			/**
			 * Action hook to adjust refund before save.
			 */
			do_action( 'storeengine/create_refund', $refund, $args );

			if ( ! $refund->save() ) {
				return new WP_Error( 'unknown-error', __( 'Something went wrong. Please try again after sometime.', 'storeengine' ) );
			}

			if ( $args['refund_payment'] ) {
				$result = self::refund_payment( $order, $refund->get_amount(), $refund->get_reason() );

				if ( is_wp_error( $result ) ) {
					$refund->delete();

					return $result;
				}

				$refund->set_refunded_payment( true );
				$refund->save();
			}

			$cache_key = Caching::get_cache_prefix( 'orders' ) . 'refunds' . $order->get_id();
			wp_cache_delete( $cache_key, 'storeengine_orders' );
			wp_cache_delete( Caching::get_cache_prefix( 'orders' ) . 'total_refunded' . $order->get_id(), 'storeengine_orders' );

			if ( $args['restock_items'] ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
				// @TODO restock items.
			}

			// delete downloads that were refunded using order and product id, if present.
			// @TODO remove download permission.

			/**
			 * Trigger notification emails.
			 *
			 * Filter hook to modify the partially-refunded status conditions.
			 *
			 * @param bool $is_partially_refunded Whether the order is partially refunded.
			 * @param int $order_id The order id.
			 * @param int $refund_id The refund id.
			 */
			if ( apply_filters( 'storeengine/order_is_partially_refunded', ( $remaining_refund_amount - $args['amount'] ) > 0 || ( $order->has_free_item() && ( $remaining_refund_items - $refund_item_count ) > 0 ), $order->get_id(), $refund->get_id() ) ) {
				do_action( 'storeengine/order/partially_refunded', $order->get_id(), $refund->get_id(), $remaining_refund_amount - $args['amount'] );
			} else {
				do_action( 'storeengine/order/fully_refunded', $order->get_id(), $refund->get_id() );

				/**
				 * Filter the status to set the order to when fully refunded.
				 *
				 * @param string $parent_status The status to set the order to when fully refunded.
				 * @param int $order_id The order ID.
				 * @param int $refund_id The refund ID.
				 */
				$parent_status = apply_filters( 'storeengine/order/fully_refunded_status', OrderStatus::REFUNDED, $order->get_id(), $refund->get_id() );

				if ( $parent_status ) {
					$order->update_status( $parent_status );
				}
			}

			if ( ! $order->get_remaining_refund_amount() ) {
				$order->set_paid_status( 'refunded' );
			}

			$order->set_date_modified( time() );
			$order->save();

			do_action( 'storeengine/order/refund_created', $refund, $args );
			do_action( 'storeengine/order/order_refunded', $order->get_id(), $refund->get_id() );
		} catch ( StoreEngineException $e ) {
			Helper::log_error( $e );

			try {
				if ( isset( $refund ) && is_a( $refund, Refund::class ) ) {
					$refund->delete( true );
				}
			} catch ( StoreEngineException $ex ) {
				Helper::log_error( $ex );
			}

			return $e->toWpError();
		}

		return $refund;
	}

	/**
	 * Try to refund the payment for an order via the gateway.
	 *
	 * @param OrderClass $order Order instance.
	 * @param string|float $amount Amount to refund.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|WP_Error
	 */
	public static function refund_payment( OrderClass $order, $amount, string $reason = '' ) {
		try {
			$gateway = Payment_Gateways::get_instance()->get_gateway( $order->get_payment_method() ) ?? false;

			if ( ! $gateway ) {
				throw new StoreEngineException( esc_html__( 'The payment gateway for this order does not exist.', 'storeengine' ), 'gateway_not_found_for_order' );
			}

			if ( ! $gateway->supports( 'refunds' ) ) {
				throw new StoreEngineException( esc_html__( 'The payment gateway for this order does not support automatic refunds.', 'storeengine' ), 'gateway_does_not_support_refund' );
			}

			$result = $gateway->process_refund( $order->get_id(), $amount, $reason );

			if ( ! $result ) {
				throw new StoreEngineException( esc_html__( 'An error occurred while attempting to create the refund using the payment gateway API.', 'storeengine' ) );
			}

			if ( is_wp_error( $result ) ) {
				throw StoreEngineException::from_wp_error( $result );
			}

			return true;
		} catch ( StoreEngineException $e ) {
			Helper::log_error( $e );

			return $e->toWpError();
		}
	}

	public static function get_order_item( int $id ) {
		return ( new OrderItemProduct( $id ) );
	}

	public static function get_first_order() {
		static $order;

		if ( null === $order ) {
			$query = new OrderCollection( [
				'per_page' => 1,
				'orderby'  => 'id',
				'order'    => 'ASC',
				'where'    => [
					'key'   => 'type',
					'value' => 'order',
				]
			] );
			$order = $query->next_result();
		}

		return $order;
	}

	public static function get_first_order_date( $format = 'Y-m-d H:i:s' ): string {
		$firstOrder = Helper::get_first_order();

		return $firstOrder ? $firstOrder->get_date_created_gmt()->format( $format ) : gmdate( 'Y-m-d H:i:s' );
	}
}
