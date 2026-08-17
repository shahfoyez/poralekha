<?php

namespace StoreEngine\Addons\Csv\Utils;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\DownloadPermission;
use StoreEngine\Classes\EventStreamServer;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Refund;
use StoreEngine\Integrations\AbstractIntegration;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

class Import {

	public static function validate_csv_structure( string $file, array $expected_header ) {
		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'invalid_csv', __( 'Unable to open file.', 'storeengine' ) );
		}

		$header = fgetcsv( $handle, 0, ',' );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( false === $header ) {
			return new WP_Error( 'invalid_csv', __( 'CSV is empty.', 'storeengine' ) );
		}

		$header = array_map( 'trim', $header );
		if ( $header !== $expected_header ) {
			return new WP_Error( 'invalid_csv', __( 'CSV header does not match the expected format.', 'storeengine' ) );
		}

		return true;
	}

	public static function import_chunk_customers( string $filename, int $start_index, EventStreamServer $stream ) {
		$filename  = Helper::get_filename_without_extension( $filename );
		$customers = get_transient( 'storeengine_csv_customers_' . $filename . '_' . $start_index );
		if ( ! $customers ) {
			$stream->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to retrieve chunked customers.', 'storeengine' ),
			], true );
		}

		$index           = ( $start_index * 30 ) + 1;
		$customers_count = get_transient( 'storeengine_csv_customers_' . $filename . '_count' );
		foreach ( $customers as $customer_data ) {
			$customer = new Customer();
			$username = $customer_data['username'] ?? null;
			if ( ! $username && isset( $customer_data['first_name'], $customer_data['last_name'] ) ) {
				$username = self::generate_unique_username( $customer_data['first_name'], $customer_data['last_name'] );
			}

			$email = $customer_data['email'];
			if ( empty( $username ) || empty( $email ) || username_exists( $username ) || email_exists( $email ) ) {
				continue;
			}

			foreach ( $customer_data as $key => $value ) {
				if ( method_exists( $customer, "set_$key" ) ) {
					$customer->{"set_$key"}( $value );
				}
			}
			$customer->set_password( '' );
			$customer->save();

			$roles = explode( ',', $customer_data['roles'] );
			if ( is_array( $roles ) && ! empty( $roles ) ) {
				foreach ( $roles as $role ) {
					$customer->get_wp_user()->add_role( trim( $role ) );
				}
			}

			do_action( 'storeengine/csv/customer_imported', $customer, $customer_data );

			$stream->emitEvent( [
				'type'       => 'progress',
				'percentage' => round( ( $index / $customers_count ) * 100 ),
			] );
			$index ++;
		}

		delete_transient( 'storeengine_csv_customers_' . $filename . '_' . $start_index );
		wp_cache_flush();

		$stream->emitEvent( [
			'type' => 'completed',
		], true );
	}

	public static function import_chunk_orders( string $filename, int $start_index, EventStreamServer $stream ) {
		$filename = Helper::get_filename_without_extension( $filename );
		$orders   = get_transient( 'storeengine_csv_orders_' . $filename . '_' . $start_index );
		if ( ! $orders ) {
			$stream->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to retrieve chunked orders.', 'storeengine' ),
			], true );
		}

		remove_all_actions( 'storeengine/order/status_changed' );
		remove_all_actions( 'storeengine/order/payment_status_changed' );
		$index        = ( $start_index * 30 ) + 1;
		$orders_count = get_transient( 'storeengine_csv_orders_' . $filename . '_count' );

		foreach ( $orders as $order_data ) {
			if ( ! isset( $order_data['order_key'] ) ) {
				continue;
			}
			$order_key = $order_data['order_key'];
			$has_order = Helper::get_order_by_key( $order_key );
			if ( ! is_wp_error( $has_order ) ) {
				continue;
			}
			$order = new Order();
			$order->set_order_key( $order_key );
			$order->set_status( $order_data['order_status'] );
			$order->set_currency( $order_data['order_currency'] );
			$order->set_total( $order_data['order_total'] );
			$order->set_cart_tax( $order_data['order_tax'] );
			$order->set_order_email( $order_data['customer_email'] );
			$order->set_created_via( 'store-csv-import' );

			$user = get_user_by( 'email', $order_data['customer_email'] );
			if ( $user ) {
				$order->set_customer_id( $user->ID );
			}

			$order->set_date_created_gmt( empty( $order_data['date_created_gmt'] ) ? current_time( 'timestamp' ) : $order_data['date_created_gmt'] );
			if ( ! empty( $order_data['date_updated_gmt'] ) ) {
				$order->set_date_updated_gmt( $order_data['date_updated_gmt'] );
			}
			if ( ! empty( $order_data['date_placed_gmt'] ) ) {
				$order->set_order_placed_date_gmt( $order_data['date_placed_gmt'] );
				$order->set_order_placed_date( get_date_from_gmt( $order_data['date_placed_gmt'] ) );
			}
			if ( ! empty( $order_data['order_paid_date_gmt'] ) ) {
				$order->set_date_paid_gmt( $order_data['order_paid_date_gmt'] );
			}

			$order->set_payment_method( $order_data['payment_method'] );
			$order->set_payment_method_title( $order_data['payment_method_title'] );
			if ( isset( $order_data['transaction_id'] ) ) {
				$order->set_transaction_id( $order_data['transaction_id'] );
			}
			if ( isset( $order_data['ip_address'] ) ) {
				$order->set_ip_address( $order_data['ip_address'] );
			}
			if ( isset( $order_data['user_agent'] ) ) {
				$order->set_user_agent( $order_data['user_agent'] );
			}
			if ( isset( $order_data['customer_note'] ) ) {
				$order->set_customer_note( $order_data['customer_note'] );
			}
			if ( isset( $order_data['cart_hash'] ) ) {
				$order->set_cart_hash( $order_data['cart_hash'] );
			}

			if ( ! empty( $order_data['meta_data'] ) ) {
				$meta_data = explode( '|', $order_data['meta_data'] );
				foreach ( $meta_data as $meta_datum ) {
					$meta_datum = explode( ':', $meta_datum );
					if ( 2 !== count( $meta_datum ) ) {
						continue;
					}
					$order->update_meta_data( $meta_datum[0], $meta_datum[1] );
				}
			}

			$product_ids = [];
			if ( ! empty( $order_data['order_items'] ) && is_array( $order_data['order_items'] ) ) {
				foreach ( $order_data['order_items'] as $order_item_data ) {
					$product_slug = trim( $order_item_data['product_slug'] );
					$posts        = get_posts( [
						'name'           => $product_slug,
						'post_type'      => 'storeengine_product',
						'fields'         => 'ids',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
					] );
					$has_product  = ! empty( $posts );
					$product_obj  = false;
					if ( $has_product ) {
						$product_obj = Helper::get_product( $posts[0] );
					}

					$order_product_item = new Order\OrderItemProduct();
					if ( $product_obj ) {
						$product_ids[ $product_slug ] = $product_obj->get_id();
						$order_product_item->set_product_id( $product_obj->get_id() );
						$price_id  = false;
						$price_obj = null;
						foreach ( $product_obj->get_prices() as $price ) {
							if ( (float) $order_item_data['price'] === $price->get_price() && $order_item_data['price_name'] === $price->get_name() && $order_item_data['price_type'] === $price->get_type() && (
									'onetime' === $order_item_data['price_type'] ||
									( 'subscription' === $order_item_data['price_type']
									  && $order_item_data['payment_duration'] === $price->get_payment_duration() && $order_item_data['payment_duration_type'] === $price->get_payment_duration_type() )
								) ) {
								$price_id  = $price->get_id();
								$price_obj = $price;
								break;
							}
						}
						if ( $price_id ) {
							$order_product_item->set_price_id( $price_id );
							if ( 'subscription' !== $price_obj->get_type() && 'completed' === $order_data['order_status'] ) {
								try {
									$integrations = Helper::get_integrations_by_price_id( $price_obj->get_id() );
									foreach ( $integrations as $integration ) {
										AbstractIntegration::get_integration( $integration->get_provider() )->run_integration( $order_product_item, $order );
									}
								} catch ( StoreEngineException $e ) {
									Helper::log_error( $e );
								}
							}
						}
					}

					$meta_data = explode( '|', $order_item_data['meta_data'] );
					unset( $order_item_data['meta_data'] );
					foreach ( $meta_data as $meta_datum ) {
						$meta_datum = explode( ':', $meta_datum );
						if ( 2 !== count( $meta_datum ) ) {
							continue;
						}
						$order_product_item->update_meta_data( $meta_datum[0], $meta_datum[1] );
					}

					foreach ( $order_item_data as $key => $value ) {
						if ( method_exists( $order_product_item, "set_$key" ) ) {
							$order_product_item->{"set_$key"}( $value );
						}
					}

					$order->add_item( $order_product_item );
				}
			}

			if ( ! empty( $order_data['billing_address'] ) && is_array( $order_data['billing_address'] ) ) {
				foreach ( $order_data['billing_address'] as $billing_address_data ) {
					foreach ( $billing_address_data as $key => $value ) {
						if ( method_exists( $order, "set_$key" ) ) {
							$order->{"set_$key"}( $value );
						}
					}
				}
			}

			if ( ! empty( $order_data['shipping_address'] ) && is_array( $order_data['shipping_address'] ) ) {
				foreach ( $order_data['shipping_address'] as $shipping_address_data ) {
					foreach ( $shipping_address_data as $key => $value ) {
						if ( method_exists( $order, "set_$key" ) ) {
							$order->{"set_$key"}( $value );
						}
					}
				}
			}

			if ( ! empty( $order_data['coupons'] ) && is_array( $order_data['coupons'] ) ) {
				foreach ( $order_data['coupons'] as $coupon_data ) {
					if ( ! isset( $coupon_data['discount'], $coupon_data['code'], $coupon_data['discount_tax'] ) ) {
						continue;
					}
					$coupon = new Order\OrderItemCoupon();
					$coupon->set_code( $coupon_data['code'] );
					$coupon->set_discount( $coupon_data['discount'] );
					$coupon->set_discount_tax( $coupon_data['discount_tax'] );
					$order->add_item( $coupon );
				}
			}

			if ( ! empty( $order_data['taxes'] ) && is_array( $order_data['taxes'] ) ) {
				foreach ( $order_data['taxes'] as $tax_data ) {
					if ( ! isset( $tax_data['name'], $tax_data['label'], $tax_data['tax_total'], $tax_data['rate_percent'], $tax_data['shipping_tax_total'] ) ) {
						continue;
					}

					$tax = new Order\OrderItemTax();
					foreach ( $tax_data as $key => $value ) {
						if ( method_exists( $tax, "set_$key" ) ) {
							$tax->{"set_$key"}( $value );
						}
					}
					$order->add_item( $tax );
				}
			}

			if ( ! empty( $order_data['shipping'] ) && is_array( $order_data['shipping'] ) ) {
				foreach ( $order_data['shipping'] as $shipping_data ) {
					$shipping = new Order\OrderItemShipping();
					foreach ( $shipping_data as $key => $value ) {
						if ( method_exists( $shipping, "set_$key" ) ) {
							$shipping->{"set_$key"}( $value );
						}
					}
					$order->add_item( $shipping );
				}
			}

			if ( ! empty( $order_data['fees'] ) && is_array( $order_data['fees'] ) ) {
				foreach ( $order_data['fees'] as $fee_data ) {
					$fee = new Order\OrderItemFee();
					foreach ( $fee_data as $key => $value ) {
						if ( method_exists( $fee, "set_$key" ) ) {
							$fee->{"set_$key"}( $value );
						}
					}
					$order->add_item( $fee );
				}
			}

			$order->save();
			\StoreEngine\Schedules\Order::set_product_lookup_schedule( $order );

			if ( ! empty( $order_data['refunds'] ) && is_array( $order_data['refunds'] ) ) {
				foreach ( $order_data['refunds'] as $refund_data ) {
					$refund = new Refund();
					$refund->set_parent_order_id( $order->get_id() );
					foreach ( $refund_data as $key => $value ) {
						if ( method_exists( $refund, "set_$key" ) ) {
							$refund->{"set_$key"}( $value );
						}
					}
					$refund->save();
				}
			}

			if ( ! empty( $order_data['downloads'] ) && is_array( $order_data['downloads'] ) && $order->get_customer_id() ) {
				foreach ( $order_data['downloads'] as $download_data ) {
					$product_slug = trim( $download_data['product_slug'] );
					if ( ! isset( $product_ids[ $product_slug ] ) ) {
						continue;
					}

					$download = new DownloadPermission();
					$download->set_user_id( $order->get_customer_id() );
					$download->set_order_id( $order->get_id() );
					$download->set_product_id( $product_ids[ $product_slug ] );
					$download->set_download_id( $download_data['download_id'] );
					$download->set_access_granted( empty( $download_data['access_granted'] ) ? current_time( 'mysql' ) : $download_data['access_granted'] );
					$download->save();
				}
			}

			$stream->emitEvent( [
				'type'       => 'progress',
				'percentage' => round( ( $index / $orders_count ) * 100 ),
			] );
			$index ++;
		}

		delete_transient( 'storeengine_csv_orders_' . $filename . '_' . $start_index );
		wp_cache_flush();

		$stream->emitEvent( [
			'type' => 'completed',
		], true );
	}

	public static function import_chunk_subscriptions( string $filename, int $start_index, EventStreamServer $stream ) {
		$filename      = Helper::get_filename_without_extension( $filename );
		$subscriptions = get_transient( 'storeengine_csv_subscriptions_' . $filename . '_' . $start_index );
		if ( ! $subscriptions ) {
			$stream->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to retrieve chunked subscriptions.', 'storeengine' ),
			], true );
		}

		$subscriptions_count = get_transient( 'storeengine_csv_subscriptions_' . $filename . '_count' );
		$index               = ( $start_index * 30 ) + 1;
		foreach ( $subscriptions as $subscription_key => $subscription_data ) {
			try {
				$has_subscription = ( new Subscription() )->get_by_key( $subscription_key );
			} catch ( StoreEngineException $e ) {
				$has_subscription = false;
			}
			if ( $has_subscription ) {
				continue;
			}
			$subscription = new Subscription();
			$subscription->set_order_key( $subscription_key );
			$subscription->set_status( $subscription_data['subscription_status'] );
			$subscription->set_currency( $subscription_data['subscription_currency'] );
			$subscription->set_total( $subscription_data['subscription_total'] );
			$subscription->set_cart_tax( $subscription_data['subscription_tax'] );
			$subscription->set_order_email( $subscription_data['customer_email'] );
			$subscription->set_created_via( 'store-csv-import' );

			$user = get_user_by( 'email', $subscription_data['customer_email'] );
			if ( $user ) {
				$subscription->set_customer_id( $user->ID );
			}

			$subscription->set_date_created_gmt( empty( $subscription_data['date_created_gmt'] ) ? current_time( 'timestamp' ) : $subscription_data['date_created_gmt'] );
			if ( ! empty( $subscription_data['date_updated_gmt'] ) ) {
				$subscription->set_date_updated_gmt( $subscription_data['date_updated_gmt'] );
			}
			if ( ! empty( $subscription_data['order_paid_date_gmt'] ) ) {
				$subscription->set_date_paid_gmt( $subscription_data['order_paid_date_gmt'] );
			}

			$subscription->set_payment_method( $subscription_data['payment_method'] );
			$subscription->set_payment_method_title( $subscription_data['payment_method_title'] );
			if ( isset( $subscription_data['ip_address'] ) ) {
				$subscription->set_ip_address( $subscription_data['ip_address'] );
			}
			if ( isset( $subscription_data['user_agent'] ) ) {
				$subscription->set_user_agent( $subscription_data['user_agent'] );
			}
			if ( isset( $subscription_data['customer_note'] ) ) {
				$subscription->set_customer_note( $subscription_data['customer_note'] );
			}
			if ( isset( $subscription_data['cart_hash'] ) ) {
				$subscription->set_cart_hash( $subscription_data['cart_hash'] );
			}
			$subscription->set_start_date( $subscription_data['start_date'] );
			$subscription->set_trial( Formatting::string_to_bool( $subscription_data['has_trial'] ) );
			$subscription->set_trial_days( $subscription_data['trial_days'] );
			$subscription->set_trial_end_date( $subscription_data['trial_end_date'] );
			$subscription->set_next_payment_date( $subscription_data['next_payment_date'] );

			if ( ! empty( $subscription_data['meta_data'] ) ) {
				$meta_data = explode( '|', $subscription_data['meta_data'] );
				foreach ( $meta_data as $meta_datum ) {
					$meta_datum = explode( ':', $meta_datum );
					if ( 2 !== count( $meta_datum ) ) {
						continue;
					}
					$subscription->update_meta_data( $meta_datum[0], $meta_datum[1] );
				}
			}

			$parent_order = Helper::get_order_by_key( $subscription_data['parent_order_key'] );
			if ( ! is_wp_error( $parent_order ) ) {
				$subscription->set_parent_order_id( $parent_order->get_id() );
			}

			$product_ids = [];
			if ( ! empty( $subscription_data['order_items'] ) && is_array( $subscription_data['order_items'] ) ) {
				foreach ( $subscription_data['order_items'] as $order_item_data ) {
					$product_slug = trim( $order_item_data['product_slug'] );
					$posts        = get_posts( [
						'name'           => $product_slug,
						'post_type'      => 'storeengine_product',
						'fields'         => 'ids',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
					] );
					$has_product  = ! empty( $posts );
					$product_obj  = false;
					if ( $has_product ) {
						$product_obj = Helper::get_product( $posts[0] );
					}

					$order_product_item = new Order\OrderItemProduct();
					if ( $product_obj ) {
						$product_ids[ $product_slug ] = $product_obj->get_id();
						$order_product_item->set_product_id( $product_obj->get_id() );
						$price_id = false;
						foreach ( $product_obj->get_prices() as $price ) {
							if ( (float) $order_item_data['price'] === $price->get_price() && $order_item_data['price_name'] === $price->get_name() && $order_item_data['price_type'] === $price->get_type() && $order_item_data['payment_duration'] === $price->get_payment_duration() && $order_item_data['payment_duration_type'] === $price->get_payment_duration_type() ) {
								$price_id = $price->get_id();
								break;
							}
						}
						if ( $price_id ) {
							$order_product_item->set_price_id( $price_id );
						}
					}

					$meta_data = explode( '|', $order_item_data['meta_data'] );
					unset( $order_item_data['meta_data'] );
					foreach ( $meta_data as $meta_datum ) {
						$meta_datum = explode( ':', $meta_datum );
						if ( 2 !== count( $meta_datum ) ) {
							continue;
						}
						$order_product_item->update_meta_data( $meta_datum[0], $meta_datum[1] );
					}

					foreach ( $order_item_data as $key => $value ) {
						if ( method_exists( $order_product_item, "set_$key" ) ) {
							$order_product_item->{"set_$key"}( $value );
						}
					}

					$subscription->add_item( $order_product_item );
				}
			}

			if ( ! empty( $subscription_data['billing_address'] ) && is_array( $subscription_data['billing_address'] ) ) {
				foreach ( $subscription_data['billing_address'] as $billing_address_data ) {
					foreach ( $billing_address_data as $key => $value ) {
						if ( method_exists( $subscription, "set_$key" ) ) {
							$subscription->{"set_$key"}( $value );
						}
					}
				}
			}

			if ( ! empty( $subscription_data['shipping_address'] ) && is_array( $subscription_data['shipping_address'] ) ) {
				foreach ( $subscription_data['shipping_address'] as $shipping_address_data ) {
					foreach ( $shipping_address_data as $key => $value ) {
						if ( method_exists( $subscription, "set_$key" ) ) {
							$subscription->{"set_$key"}( $value );
						}
					}
				}
			}

			if ( ! empty( $subscription_data['coupons'] ) && is_array( $subscription_data['coupons'] ) ) {
				foreach ( $subscription_data['coupons'] as $coupon_data ) {
					if ( ! isset( $coupon_data['discount'], $coupon_data['code'], $coupon_data['discount_tax'] ) ) {
						continue;
					}
					$coupon = new Order\OrderItemCoupon();
					$coupon->set_code( $coupon_data['code'] );
					$coupon->set_discount( $coupon_data['discount'] );
					$coupon->set_discount_tax( $coupon_data['discount_tax'] );
					$subscription->add_item( $coupon );
				}
			}

			if ( ! empty( $subscription_data['taxes'] ) && is_array( $subscription_data['taxes'] ) ) {
				foreach ( $subscription_data['taxes'] as $tax_data ) {
					if ( ! isset( $tax_data['name'], $tax_data['label'], $tax_data['tax_total'], $tax_data['rate_percent'], $tax_data['shipping_tax_total'] ) ) {
						continue;
					}

					$tax = new Order\OrderItemTax();
					foreach ( $tax_data as $key => $value ) {
						if ( method_exists( $tax, "set_$key" ) ) {
							$tax->{"set_$key"}( $value );
						}
					}
					$subscription->add_item( $tax );
				}
			}

			if ( ! empty( $subscription_data['shipping'] ) && is_array( $subscription_data['shipping'] ) ) {
				foreach ( $subscription_data['shipping'] as $shipping_data ) {
					$shipping = new Order\OrderItemShipping();
					foreach ( $shipping_data as $key => $value ) {
						if ( method_exists( $shipping, "set_$key" ) ) {
							$shipping->{"set_$key"}( $value );
						}
					}
					$subscription->add_item( $shipping );
				}
			}

			if ( ! empty( $subscription_data['fees'] ) && is_array( $subscription_data['fees'] ) ) {
				foreach ( $subscription_data['fees'] as $fee_data ) {
					$fee = new Order\OrderItemFee();
					foreach ( $fee_data as $key => $value ) {
						if ( method_exists( $fee, "set_$key" ) ) {
							$fee->{"set_$key"}( $value );
						}
					}
					$subscription->add_item( $fee );
				}
			}

			$subscription->save();

			if ( ! empty( $subscription_data['child_order_keys'] ) ) {
				$child_order_keys = explode( ',', $subscription_data['child_order_keys'] );
				foreach ( $child_order_keys as $child_order_key ) {
					$order = Helper::get_order_by_key( $child_order_key );
					if ( ! is_wp_error( $order ) ) {
						$order->add_meta_data( '_subscription_id', $subscription->get_id() );
						$order->add_meta_data( '_subscription_renewal', $subscription->get_id() );
						$order->save();
					}
				}
			}

			foreach ( $subscription->get_items() as $subscription_item ) {
				/** @var Order\OrderItemProduct $subscription_item */
				try {
					$integrations = Helper::get_integrations_by_price_id( $subscription_item->get_price_id() );
					foreach ( $integrations as $integration ) {
						AbstractIntegration::get_integration( $integration->get_provider() )->handle_subscription_status_changed( $subscription, $subscription->get_status() );
					}
				} catch ( StoreEngineException $e ) {
					Helper::log_error( $e );
				}
			}

			$stream->emitEvent( [
				'type'       => 'progress',
				'percentage' => round( ( $index / $subscriptions_count ) * 100 ),
			] );
			$index ++;
		}

		delete_transient( 'storeengine_csv_subscriptions_' . $filename . '_' . $start_index );
		wp_cache_flush();

		$stream->emitEvent( [
			'type' => 'completed',
		], true );
	}

	public static function store_chunk_data( string $type, string $filename, array $data ): array {
		$expire       = 60 * 60 * 4;
		$chunked_data = array_chunk( $data, 30, true );
		$filename     = Helper::get_filename_without_extension( $filename );
		set_transient( "storeengine_csv_{$type}_" . $filename . '_count', count( $data ), $expire );
		foreach ( $chunked_data as $index => $chunk ) {
			set_transient( "storeengine_csv_{$type}_" . $filename . '_' . $index, $chunk, $expire );
		}

		return $chunked_data;
	}

	public static function generate_unique_username( $first_name, $last_name ): string {
		// Build base username (first + last, lowercase, sanitized)
		$base   = sanitize_user( strtolower( $first_name . '.' . $last_name ), true );
		$user   = $base;
		$suffix = 1;

		// Ensure uniqueness
		while ( username_exists( $user ) ) {
			$user = $base . $suffix;
			$suffix ++;
		}

		return $user;
	}

	public static function validate_data_type( string $type = null ) {
		if ( ! $type ) {
			return new \WP_Error( 'invalid_type', esc_html__( 'Type is required.', 'storeengine' ) );
		}

		if ( ! in_array( $type, [ 'products', 'orders', 'customers', 'access_groups', 'subscriptions' ], true ) ) {
			return new \WP_Error( 'type-not-supported', esc_html__( 'Invalid type!', 'storeengine' ) );
		}

		if ( 'access_groups' === $type && ! Helper::get_addon_active_status( 'membership' ) ) {
			return new \WP_Error( 'membership-addon-not-active', esc_html__( 'Please active Membership addon to export Access groups.', 'storeengine' ) );
		}

		if ( 'subscriptions' === $type && ! Helper::get_addon_active_status( 'subscription' ) ) {
			return new \WP_Error( 'subscription-addon-not-active', esc_html__( 'Please active Subscription addon to export Subscriptions.', 'storeengine' ) );
		}

		return true;
	}
}
