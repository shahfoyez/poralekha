<?php

namespace StoreEngine\Addons\Csv\Ajax;

use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\EventStreamServer;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\Order\OrderItemCoupon;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Classes\Product\VariableProduct;
use StoreEngine\Classes\StoreengineDatetime;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Export extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_csv';

	protected static EventStreamServer $sse;

	public function __construct() {
		// Vendors hold edit_storeengine_products too; results are scoped to
		// their own data inside export_*() so they only see their own rows.
		$this->actions = [
			'file_download' => [
				'callback'   => [ $this, 'file_download' ],
				'capability' => 'edit_storeengine_products',
				'fields'     => [
					'filename' => 'string',
				],
			],
			'export'        => [
				'callback'   => [ $this, 'export' ],
				'capability' => 'edit_storeengine_products',
				'fields'     => [
					'type' => 'string',
				],
			],
		];
	}

	/**
	 * Returns the user_id to scope export queries by, or 0 for full visibility.
	 * Vendors get scoped to their own data; admin/shop_manager get everything.
	 */
	protected function vendor_scope_user_id(): int {
		if ( current_user_can( 'manage_options' )
			|| current_user_can( 'manage_storeengine_settings' )
			|| current_user_can( 'edit_others_storeengine_products' ) ) {
			return 0;
		}
		if ( current_user_can( 'manage_storeengine_vendor' ) ) {
			return (int) get_current_user_id();
		}
		return 0;
	}

	public function file_download( array $payload ) {
		if ( ! isset( $payload['filename'] ) ) {
			wp_send_json_error( __( 'Filename is required.', 'storeengine' ) );
		}

		$filename   = sanitize_file_name( wp_basename( $payload['filename'] ) );
		$upload_dir = trailingslashit( Helper::get_upload_dir() ) . 'csv/';
		$filepath   = $upload_dir . $filename;

		$real_upload_dir = realpath( $upload_dir );
		$real_filepath   = realpath( $filepath );


		if ( ! $real_filepath || strpos( $real_filepath, $real_upload_dir ) !== 0 ) {
			wp_send_json_error( __( 'Invalid file path.', 'storeengine' ) );
		}

		if ( ! file_exists( $real_filepath ) ) {
			wp_send_json_error( __( 'File not found.', 'storeengine' ) );
		}

		if ( ob_get_length() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $real_filepath ) . '"' );
		header( 'Content-Length: ' . filesize( $real_filepath ) );

		$handle = self::open_file( $real_filepath, 'rb' );
		if ( $handle ) {
			while ( ! feof( $handle ) ) {
				echo fread( $handle, 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
				flush();
			}
			self::close_file( $handle );
		}
		exit;
	}

	public function export( array $payload ) {
		$valid = \StoreEngine\Addons\Csv\Utils\Import::validate_data_type( $payload['type'] );

		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( $valid->get_error_message() );
		}

		// Vendors can only export data they own (currently products + orders).
		// Customer / subscription / access-group exports remain admin-only.
		if ( $this->vendor_scope_user_id() > 0
			&& ! in_array( (string) $payload['type'], [ 'products', 'orders' ], true ) ) {
			wp_send_json_error( __( 'Vendors can only export their own products and orders.', 'storeengine' ) );
		}

		self::$sse = new EventStreamServer();
		self::$sse->listen( function () use ( $payload ) {
			/**
			 * @see export_products()
			 * @see export_orders()
			 * @see export_customers()
			 * @see export_access_groups()
			 * @see export_subscriptions()
			 */
			$this->{'export_' . $payload['type']}();
		} );
	}

	private function export_products() {
		global $wpdb;
		$scope_user_id = $this->vendor_scope_user_id();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( $scope_user_id > 0 ) {
			$products_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_author = %d",
				'storeengine_product',
				$scope_user_id
			) );
		} else {
			$products_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s", 'storeengine_product' ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		$product_pages = ceil( $products_count / 20 );
		$upload_dir    = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time  = time();
		$filename      = "products_{$products_count}_{$current_time}.csv";
		$filepath      = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = self::open_file( $filepath, 'w' );
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		self::run_clean_hook( $filepath );

		fputcsv( $fp, [
			'ID',
			'Type',
			'Name',
			'Slug',
			'Published',
			'Description',
			'Is Hide',
			'Shipping Type',
			'Weight',
			'Weight Unit',
			'Product Categories',
			'Product Tags',
			'Images',
		] );

		$index = 0;
		foreach ( range( 1, $product_pages ) as $page ) {
			$query_args = [
				'posts_per_page' => 20,
				'paged'          => $page,
			];
			if ( $scope_user_id > 0 ) {
				$query_args['author'] = $scope_user_id;
			}
			$products = Helper::get_products( $query_args );
			foreach ( $products as $product ) {
				if ( $index > 0 ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [ '# Product Entity' ] );
				}
				$index ++;

				fputcsv( $fp, [
					esc_html( $product->get_id() ),
					esc_html( $product->get_type() ),
					esc_html( $product->get_name() ),
					esc_html( $product->get_slug() ),
					esc_html( $product->get_published_date_gmt() ),
					wp_kses_post( $product->get_content() ),
					absint( $product->is_hide() ),
					esc_html( $product->get_shipping_type() ),
					esc_html( $product->get_weight() ),
					esc_html( $product->get_weight_unit() ),
					esc_html( $this->get_product_categories( $product ) ),
					esc_html( $this->get_product_tags( $product ) ),
					esc_html( $this->get_product_images( $product ) ),
				] );

				if ( ! empty( $product->get_prices() ) ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [
						'Price name',
						'Price type',
						'Price',
						'Compare price',
						'Setup Fee',
						'Setup Fee name',
						'Setup Fee price',
						'Setup Fee type',
						'Trial',
						'Trial days',
						'Expire',
						'Expire days',
						'Payment duration',
						'Payment duration type',
						'Upgradeable',
						'Order',
					] );
					foreach ( $product->get_prices() as $price ) {
						$price_data = $price->get_data();
						unset( $price_data['order'] );
						unset( $price_data['tax_status'] );
						unset( $price_data['product_id'] );
						$price_data = array_merge( array_values( $price_data ), [ $price->get_order() ] );
						$price_data = array_map( 'esc_html', $price_data );

						fputcsv( $fp, $price_data );
					}
				}

				if ( ! empty( $product->get_downloadable_files() ) ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [
						'Download ID',
						'Download Name',
						'Download URL',
						'Download Status',
					] );
					foreach ( $product->get_downloadable_files() as $file ) {
						fputcsv( $fp, array_map( 'esc_html', array_values( $file ) ) );
					}
				}

				if ( $product->is_type( 'variable' ) && $product instanceof VariableProduct ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [
						'Variation Price',
						'Variation Image',
						'Variation Attributes',
						'Price Index',
						'Variation SKU',
						'Variation Barcode',
						'Variation Cost',
						'Variation Stock',
					] );
					$variations = array_map( function ( $variant ) use ( $product ) {
						$attributes = [];
						foreach ( $variant->get_attributes() as $attribute ) {
							$taxonomy     = get_taxonomy( $attribute->taxonomy );
							$attributes[] = ( $taxonomy ? $taxonomy->labels->singular_name : str_replace( 'se_pa_', '', $attribute->taxonomy ) ) . ':' . $attribute->name;
						}

						return [
							$variant->get_price(),
							wp_get_attachment_image_url( $variant->get_featured_image() ),
							implode( ',', $attributes ),
							empty( $variant->get_pricing_id() ) ? null : $this->get_pricing_index( $product, $variant->get_pricing_id() ),
							method_exists( $variant, 'get_sku' ) ? (string) $variant->get_sku() : '',
							method_exists( $variant, 'get_barcode' ) ? (string) $variant->get_barcode() : '',
							method_exists( $variant, 'get_cost_price' ) ? ( null === $variant->get_cost_price() ? '' : (string) $variant->get_cost_price() ) : '',
							method_exists( $variant, 'get_stock_quantity' ) ? ( null === $variant->get_stock_quantity() ? '' : (string) $variant->get_stock_quantity() ) : '',
						];
					}, $product->get_variants() );
					foreach ( $variations as $variation ) {
						fputcsv( $fp, array_map( 'esc_html', $variation ) );
					}
				}

				self::$sse->emitEvent( [
					'type'       => 'progress',
					'percentage' => round( ( $index / $products_count ) * 100 ),
				] );
			}
		}

		self::close_file( $fp );

		self::$sse->emitEvent( [
			'type'    => 'filename',
			'message' => $filename,
		] );

		self::$sse->emitEvent( [
			'type'    => 'complete',
			'message' => esc_html__( 'Product export file has been ready.', 'storeengine' ),
		], true );
	}

	private function get_product_categories( AbstractProduct $product ): string {
		$terms = wp_get_object_terms( $product->get_id(), 'storeengine_product_category', [ 'fields' => 'all' ] );

		$paths = [];

		foreach ( $terms as $term ) {
			$path      = [ $term->name ];
			$parent_id = $term->parent;

			while ( $parent_id ) {
				$parent = get_term( $parent_id, 'storeengine_product_category' );
				if ( is_wp_error( $parent ) || ! $parent ) {
					break;
				}
				array_unshift( $path, $parent->name );
				$parent_id = $parent->parent;
			}

			$paths[] = implode( ' > ', $path );
		}

		return implode( ', ', $paths );
	}

	private function get_product_tags( AbstractProduct $product ): string {
		return implode( ', ', wp_get_object_terms( $product->get_id(), 'storeengine_product_tag', [ 'fields' => 'names' ] ) );
	}

	private function get_product_images( AbstractProduct $product ): string {
		return implode( ', ',
			array_unique(
				array_filter(
					array_merge(
						[ get_the_post_thumbnail_url( $product->get_id() ) ],
						array_map(
							fn( $image_id ) => wp_get_attachment_image_url( $image_id ),
							$product->get_product_gallery()
						)
					)
				)
			)
		);
	}

	private function get_pricing_index( AbstractProduct $product, int $pricing_id ): ?int {
		$pricing_index = 1;
		foreach ( $product->get_prices() as $price ) {
			if ( $price->get_id() === $pricing_id ) {
				return $pricing_index;
			}
			$pricing_index ++;
		}

		return null;
	}

	private function export_orders() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$orders_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_orders WHERE type = %s", 'order' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		$orders_pages = ceil( $orders_count / 20 );
		$upload_dir   = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time = time();
		$filename     = "orders_{$orders_count}_{$current_time}.csv";
		$filepath     = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = self::open_file( $filepath, 'w' );
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		self::run_clean_hook( $filepath );

		fputcsv( $fp, [
			'Order Key',
			'Order Status',
			'Customer Email',
			'Order Currency',
			'Order Subtotal',
			'Order Total',
			'Order Tax',
			'Date Created (GMT)',
			'Date Updated (GMT)',
			'Date Placed (GMT)',
			'Date Paid (GMT)',
			'Payment Method (name)',
			'Payment Method (title)',
			'Transaction ID',
			'IP Address',
			'User Agent',
			'Customer Note',
			'Cart Hash',
			'Meta Data',
		] );

		$index = 0;
		foreach ( range( 1, $orders_pages ) as $page ) {
			try {
				$query = new OrderCollection( [
					'per_page' => 20,
					'paged'    => $page,
					'where'    => [
						'relation' => 'AND',
						[
							'key'   => 'type',
							'value' => 'order',
						],
						[
							'key'     => 'status',
							'value'   => [ 'draft', 'auto-draft', 'trash' ],
							'compare' => 'NOT IN',
						],
					],
				] );
				if ( $query->have_results() ) {
					foreach ( $query->get_results() as $order ) {
						if ( $index > 0 ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [ '# Order Entity' ] );
						}
						$index ++;

						$order_meta_data = [];
						foreach ( $order->get_meta_data() as $meta ) {
							$order_meta_data[] = $meta->key . ':' . $meta->value;
						}
						$user = get_userdata( $order->get_customer_id() );
						fputcsv( $fp, [
							esc_html( $order->get_order_key() ),
							esc_html( $order->get_status() ),
							esc_html( $user ? $user->user_email : $order->get_order_email() ),
							esc_html( $order->get_currency() ),
							esc_html( $order->get_subtotal() ),
							esc_html( $order->get_total() ),
							esc_html( $order->get_total_tax() ),
							esc_html( $order->get_date_created_gmt()->format( 'Y-m-d h:i:s A' ) ),
							esc_html( $order->get_date_updated_gmt() ? $order->get_date_updated_gmt()->format( 'Y-m-d h:i:s A' ) : '' ),
							esc_html( $order->get_order_placed_date_gmt() ? $order->get_order_placed_date_gmt()->format( 'Y-m-d h:i:s A' ) : '' ),
							esc_html( $order->get_date_paid_gmt() ? $order->get_date_paid_gmt()->format( 'Y-m-d h:i:s A' ) : '' ),
							esc_html( $order->get_payment_method() ),
							esc_html( $order->get_payment_method_title() ),
							esc_html( $order->get_transaction_id() ),
							esc_html( $order->get_ip_address() ),
							esc_html( $order->get_user_agent() ),
							esc_html( $order->get_customer_note() ),
							esc_html( $order->get_cart_hash() ),
							esc_html( implode( '|', $order_meta_data ) ),
						] );

						if ( ! empty( $order->get_items() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Order Item Name',
								'Product Slug',
								'Product Type',
								'Price',
								'Quantity',
								'Subtotal',
								'Subtotal (Tax)',
								'Total',
								'Total (Tax)',
								'Product Shipping Type',
								'Is Autocomplete',
								'Price name',
								'Price type',
								'Setup Fee',
								'Setup Fee name',
								'Setup Fee price',
								'Setup Fee type',
								'Trial',
								'Trial days',
								'Expire',
								'Expire days',
								'Payment duration',
								'Payment duration type',
								'Meta Data',
							] );
							/** @var OrderItemProduct $item */
							foreach ( $order->get_items() as $item ) {
								$metadata = [];
								foreach ( $item->get_formatted_metadata() as $meta ) {
									$metadata[] = "{$meta['key']}:{$meta['value']}";
								}
								fputcsv( $fp, [
									esc_html( $item->get_name() ),
									esc_html( $item->get_product() ? $item->get_product()->get_slug() : '' ),
									esc_html( $item->get_product_type() ),
									esc_html( $item->get_price() ),
									absint( $item->get_quantity() ),
									esc_html( $item->get_subtotal() ),
									esc_html( $item->get_subtotal_tax() ),
									esc_html( $item->get_total() ),
									esc_html( $item->get_total_tax() ),
									esc_html( $item->get_shipping_type() ),
									esc_html( $item->get_digital_auto_complete() ),
									esc_html( $item->get_price_name() ),
									esc_html( $item->get_price_type() ),
									esc_html( $item->get_setup_fee() ),
									esc_html( $item->get_setup_fee_name() ),
									esc_html( $item->get_setup_fee_price() ),
									esc_html( $item->get_setup_fee_type() ),
									esc_html( $item->get_trial() ),
									esc_html( $item->get_trial_days() ),
									esc_html( $item->get_expire() ),
									esc_html( $item->get_expire_days() ),
									esc_html( $item->get_payment_duration() ),
									esc_html( $item->get_payment_duration_type() ),
									esc_html( implode( '|', $metadata ) ),
								] );
							}
						}

						if ( ! empty( $order->get_billing_country() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Billing First Name',
								'Billing Last Name',
								'Billing Company',
								'Billing Address 1',
								'Billing Address 2',
								'Billing City',
								'Billing State',
								'Billing Postcode',
								'Billing Country',
								'Billing Email',
								'Billing Phone',
							] );
							fputcsv( $fp, [
								esc_html( $order->get_billing_first_name() ),
								esc_html( $order->get_billing_last_name() ),
								esc_html( $order->get_billing_company() ),
								esc_html( $order->get_billing_address_1() ),
								esc_html( $order->get_billing_address_2() ),
								esc_html( $order->get_billing_city() ),
								esc_html( $order->get_billing_state() ),
								esc_html( $order->get_billing_postcode() ),
								esc_html( $order->get_billing_country() ),
								esc_html( $order->get_billing_email() ),
								esc_html( $order->get_billing_phone() ),
							] );
						}

						if ( ! empty( $order->get_shipping_country() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Shipping First Name',
								'Shipping Last Name',
								'Shipping Company',
								'Shipping Address 1',
								'Shipping Address 2',
								'Shipping City',
								'Shipping State',
								'Shipping Postcode',
								'Shipping Country',
								'Shipping Email',
								'Shipping Phone',
							] );
							fputcsv( $fp, [
								esc_html( $order->get_shipping_first_name() ),
								esc_html( $order->get_shipping_last_name() ),
								esc_html( $order->get_shipping_company() ),
								esc_html( $order->get_shipping_address_1() ),
								esc_html( $order->get_shipping_address_2() ),
								esc_html( $order->get_shipping_city() ),
								esc_html( $order->get_shipping_state() ),
								esc_html( $order->get_shipping_postcode() ),
								esc_html( $order->get_shipping_country() ),
								esc_html( $order->get_shipping_email() ),
								esc_html( $order->get_shipping_phone() ),
							] );
						}

						if ( ! empty( $order->get_coupons() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Coupon Code',
								'Coupon Amount',
								'Coupon Amount (Tax)',
							] );
							/** @var OrderItemCoupon $coupon */
							foreach ( $order->get_coupons() as $coupon ) {
								fputcsv( $fp, [
									esc_html( $coupon->get_code() ),
									esc_html( $coupon->get_discount() ),
									esc_html( $coupon->get_discount_tax() ),
								] );
							}
						}

						if ( ! empty( $order->get_taxes() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Tax Name',
								'Tax Label',
								'Tax Amount',
								'Tax Rate(%)',
								'Shipping Tax Amount',
							] );

							foreach ( $order->get_taxes() as $tax ) {
								fputcsv( $fp, [
									esc_html( $tax->get_name() ),
									esc_html( $tax->get_label() ),
									esc_html( $tax->get_tax_total() ),
									esc_html( $tax->get_rate_percent() ),
									esc_html( $tax->get_shipping_tax_total() ),
								] );
							}
						}

						if ( ! empty( $order->get_shipping_methods() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Shipping Method',
								'Shipping Method Title',
								'Amount',
								'Amount (Tax)',
								'Tax Status',
							] );
							foreach ( $order->get_shipping_methods() as $shipping_method ) {
								fputcsv( $fp, [
									esc_html( $shipping_method->get_method_id() ),
									esc_html( $shipping_method->get_method_title() ),
									esc_html( $shipping_method->get_total() ),
									esc_html( $shipping_method->get_total_tax() ),
									esc_html( $shipping_method->get_tax_status() ),
								] );
							}
						}

						if ( ! empty( $order->get_fees() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Fee Name',
								'Fee Amount',
								'Fee Amount (Tax)',
								'Tax Status',
							] );
							foreach ( $order->get_fees() as $fee ) {
								fputcsv( $fp, [
									esc_html( $fee->get_name() ),
									esc_html( $fee->get_amount() ),
									esc_html( $fee->get_total_tax() ),
									esc_html( $fee->get_tax_status() ),
								] );
							}
						}

						if ( ! empty( $order->get_downloadable_permissions() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Download ID',
								'Product Slug',
								'Date Access Granted (GMT)',
							] );
							foreach ( $order->get_downloadable_permissions() as $downloadable_item ) {
								$product_post = get_post( $downloadable_item->get_product_id() );
								if ( ! $product_post ) {
									continue;
								}
								fputcsv( $fp, [
									esc_html( $downloadable_item->get_download_id() ),
									esc_html( $product_post->post_name ),
									esc_html( $downloadable_item->get_access_granted_date() instanceof StoreengineDatetime ? $downloadable_item->get_access_granted_date()->format( 'Y-m-d h:i:s A' ) : $downloadable_item->get_access_granted_date() ),
								] );
							}
						}

						if ( ! empty( $order->get_refunds() ) ) {
							fputcsv( $fp, [] );
							fputcsv( $fp, [
								'Refund Amount',
								'Refund Reason',
								'Refund By',
							] );
							foreach ( $order->get_refunds() as $refund ) {
								$user = get_userdata( $refund->get_refunded_by() );
								fputcsv( $fp, [
									esc_html( $refund->get_amount() ),
									esc_html( $refund->get_reason() ),
									esc_html( $user ? $user->user_email : '' ),
								] );
							}
						}

						self::$sse->emitEvent( [
							'type'       => 'progress',
							'percentage' => round( ( $index / $orders_count ) * 100 ),
						] );
					}
				}
			} catch ( StoreEngineInvalidArgumentException $e ) {
				self::close_file( $fp );
				self::$sse->emitEvent( [
					'type'    => 'error',
					'message' => $e->getMessage(),
				], true );
			}
		}

		self::close_file( $fp );

		self::$sse->emitEvent( [
			'type'    => 'filename',
			'message' => $filename,
		] );

		self::$sse->emitEvent( [
			'type'    => 'complete',
			'message' => esc_html__( 'Orders export file has been ready.', 'storeengine' ),
		], true );
	}

	private function export_customers() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$customers_count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		$customer_pages = ceil( $customers_count / 20 );
		$upload_dir     = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time   = time();
		$filename       = "customers_{$customers_count}_$current_time.csv";
		$filepath       = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = self::open_file( $filepath, 'w' );
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		self::run_clean_hook( $filepath );

		fputcsv( $fp, [
			'Username',
			'First Name',
			'Last Name',
			'Nick Name',
			'Display Name',
			'Email',
			'Website',
			'Biographical Info',
			'Registered Date (GMT)',
			'Roles',
			'Billing First Name',
			'Billing Last Name',
			'Billing Company',
			'Billing Address 1',
			'Billing Address 2',
			'Billing City',
			'Billing State',
			'Billing Postcode',
			'Billing Country',
			'Billing Email',
			'Billing Phone',
			'Shipping First Name',
			'Shipping Last Name',
			'Shipping Company',
			'Shipping Address 1',
			'Shipping Address 2',
			'Shipping City',
			'Shipping State',
			'Shipping Postcode',
			'Shipping Country',
			'Shipping Email',
			'Shipping Phone',
			'Email Subscribe',
		] );

		$index = 0;
		foreach ( range( 1, $customer_pages ) as $page ) {
			/** @var Customer[] $customers */
			$customers = Helper::get_customers( [
				'number' => 20,
				'paged'  => $page,
			] );
			foreach ( $customers as $customer ) {
				fputcsv( $fp, [
					esc_html( $customer->get_username() ),
					esc_html( $customer->get_first_name() ),
					esc_html( $customer->get_last_name() ),
					esc_html( $customer->get_wp_user()->user_nicename ),
					esc_html( $customer->get_display_name() ),
					esc_html( $customer->get_email() ),
					esc_html( $customer->get_url() ),
					esc_html( $customer->get_wp_user()->user_description ),
					esc_html( $customer->get_user_registered() ? $customer->get_user_registered()->format( 'Y-m-d h:i:s A' ) : '' ),
					esc_html( implode( ',', $customer->get_wp_user()->roles ) ),
					esc_html( $customer->get_billing_first_name() ),
					esc_html( $customer->get_billing_last_name() ),
					esc_html( $customer->get_billing_company() ),
					esc_html( $customer->get_billing_address_1() ),
					esc_html( $customer->get_billing_address_2() ),
					esc_html( $customer->get_billing_city() ),
					esc_html( $customer->get_billing_state() ),
					esc_html( $customer->get_billing_postcode() ),
					esc_html( $customer->get_billing_country() ),
					esc_html( $customer->get_billing_email() ),
					esc_html( $customer->get_billing_phone() ),
					esc_html( $customer->get_shipping_first_name() ),
					esc_html( $customer->get_shipping_last_name() ),
					esc_html( $customer->get_shipping_company() ),
					esc_html( $customer->get_shipping_address_1() ),
					esc_html( $customer->get_shipping_address_2() ),
					esc_html( $customer->get_shipping_city() ),
					esc_html( $customer->get_shipping_state() ),
					esc_html( $customer->get_shipping_postcode() ),
					esc_html( $customer->get_shipping_country() ),
					esc_html( $customer->get_shipping_email() ),
					esc_html( $customer->get_shipping_phone() ),
					Formatting::bool_to_string( $customer->get_subscribe_to_email() ),
				] );

				self::$sse->emitEvent( [
					'type'       => 'progress',
					'percentage' => round( ( $index / $customers_count ) * 100 ),
				] );
				$index ++;
			}
		}

		self::close_file( $fp );

		self::$sse->emitEvent( [
			'type'    => 'filename',
			'message' => $filename,
		] );

		self::$sse->emitEvent( [
			'type'    => 'complete',
			'message' => esc_html__( 'Customer export file has been ready.', 'storeengine' ),
		], true );
	}

	private function export_access_groups() {
		$access_groups_count = wp_count_posts( 'storeengine_groups' )->publish;

		$access_groups_pages = ceil( $access_groups_count / 20 );
		$upload_dir          = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time        = time();
		$filename            = "access_groups_{$access_groups_count}_$current_time.csv";
		$filepath            = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = self::open_file( $filepath, 'w' );
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		self::run_clean_hook( $filepath );

		fputcsv( $fp, [
			'Access Group Name',
			'Unauthorized Access Message',
			'Protected Content Type',
			'Specific Content Items',
			'Excluded Content Items',
			'Downloadable files',
			'Enable Redirect URL',
			'Redirect URL',
			'Enable Access Group Expiration',
			'Expiration Date type',
			'Expiration Relative Date',
			'Expiration Specific Date',
			'User Role Sync',
			'Priority',
		] );

		$rules = [
			'basic-global'                 => 'Entire Website',
			'post|all'                     => 'All Posts',
			'post|all|taxarchive|category' => 'All Categories Archive',
			'post|all|taxarchive|post_tag' => 'All Tags Archive',
			'page|all'                     => 'All Pages',
			'specifics'                    => 'Specific Posts/ Pages/ Taxonomies',
		];

		$index = 0;
		foreach ( range( 1, $access_groups_pages ) as $page ) {
			$access_group_posts = get_posts( [
				'post_type'   => 'storeengine_groups',
				'numberposts' => 20,
				'offset'      => max( ( $page - 1 ) * 20, 0 ),
			] );
			foreach ( $access_group_posts as $access_group_post ) {
				if ( $index > 0 ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [ '# Access Group Entity' ] );
				}
				$index ++;

				$authorization_meta     = get_post_meta( $access_group_post->ID, '_storeengine_membership_authorization', true );
				$content_protect_data   = get_post_meta( $access_group_post->ID, '_storeengine_membership_content_protect_types', true );
				$content_excluded_items = get_post_meta( $access_group_post->ID, '_storeengine_membership_content_protect_excluded_items', true );
				$attachments            = get_post_meta( $access_group_post->ID, '_storeengine_membership_attachments', true );
				$expiration_meta        = get_post_meta( $access_group_post->ID, '_storeengine_membership_expiration', true );
				$user_roles             = get_post_meta( $access_group_post->ID, '_storeengine_membership_user_roles', true );

				$content_types = [];
				if ( ! empty( $content_protect_data ) && ! empty( $content_protect_data['rules'] ) ) {
					$content_types = array_map( fn( $type ) => $rules[ $type ], $content_protect_data['rules'] );
				}
				$specifics = [];
				if ( ! empty( $content_protect_data ) && ! empty( $content_protect_data['specifics'] ) ) {
					foreach ( $content_protect_data['specifics'] as $specific ) {
						$slug = $this->get_slug_from_item( $specific['value'] );
						if ( $slug ) {
							$specifics[] = $slug;
						}
					}
				}

				$excluded_items = [];
				if ( ! empty( $content_excluded_items ) ) {
					foreach ( $content_excluded_items as $excluded_item ) {
						$slug = $this->get_slug_from_item( $excluded_item['value'] );
						if ( $slug ) {
							$excluded_items[] = $slug;
						}
					}
				}

				if ( is_array( $attachments ) ) {
					$attachments = array_map( fn( $attachment_id ) => wp_get_attachment_image_url( $attachment_id, 'full' ), $attachments );
				}

				if ( is_array( $user_roles ) ) {
					$user_roles = array_map( fn( $user_role ) => $user_role['value'], $user_roles );
				}

				fputcsv( $fp, [
					esc_html( $access_group_post->post_title ),
					esc_html( $authorization_meta['message'] ),
					esc_html( implode( ',', $content_types ) ),
					esc_html( implode( ',', $specifics ) ),
					esc_html( implode( ',', $excluded_items ) ),
					esc_html( implode( ',', $attachments ) ),
					'redirect' === $authorization_meta['type'],
					esc_html( $authorization_meta['redirect_url'] ),
					Formatting::string_to_bool( $expiration_meta['is_enable_expiration'] ),
					esc_html( $expiration_meta['date_type'] ?? '' ),
					esc_html( $expiration_meta['fixed_date_duration'] ?? '' ),
					esc_html( $expiration_meta['specific_date'] ?? '' ),
					esc_html( implode( ',', $user_roles ) ),
					esc_html( get_post_meta( $access_group_post->ID, '_storeengine_membership_priority', true ) ),
				] );

				$integrations = Helper::get_integration_repository_by_id( 'storeengine/membership-addon', $access_group_post->ID );
				if ( ! empty( $integrations ) ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [
						'Product name',
						'Product slug',
						'Price name',
						'Price type',
						'Price',
						'Compare price',
						'Setup Fee',
						'Setup Fee name',
						'Setup Fee price',
						'Setup Fee type',
						'Trial',
						'Trial days',
						'Expire',
						'Expire days',
						'Payment duration',
						'Payment duration type',
						'Upgradeable',
						'Order',
					] );
					foreach ( $integrations as $integration ) {
						$price      = $integration->price;
						$price_data = $price->get_data();
						unset( $price_data['order'] );
						unset( $price_data['tax_status'] );
						unset( $price_data['product_id'] );
						$product = $price->get_product();

						$data = array_merge( [
							$product ? $product->get_name() : '',
							$product ? $product->get_slug() : '',
						], array_values( $price_data ), [ $price->get_order() ] );
						$data = array_map( 'esc_html', $data );
						fputcsv( $fp, $data );
					}
				}

				$features = get_post_meta( $access_group_post->ID, '_storeengine_membership_features', true );
				if ( ! empty( $features ) ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [
						'Feature Icon',
						'Feature Label',
					] );
					foreach ( $features as $feature ) {
						fputcsv( $fp, [
							esc_html( $feature['icon'] ),
							esc_html( $feature['label'] ),
						] );
					}
				}

				self::$sse->emitEvent( [
					'type'       => 'progress',
					'percentage' => round( ( $index / $access_groups_count ) * 100 ),
				] );
			}
		}

		self::close_file( $fp );

		self::$sse->emitEvent( [
			'type'    => 'filename',
			'message' => $filename,
		] );

		self::$sse->emitEvent( [
			'type'    => 'complete',
			'message' => esc_html__( 'Access groups export file has been ready.', 'storeengine' ),
		], true );
	}

	private function get_slug_from_item( string $title ): ?string {
		if ( str_starts_with( $title, 'post-' ) ) {
			preg_match( '/post-(\d+)-\|/', $title, $matches );
			$post_id = $matches[1] ?? null;
			if ( ! $post_id ) {
				return null;
			}
			$post = get_post( $post_id );
			if ( ! $post ) {
				return null;
			}

			return $post->post_name;
		}

		if ( str_starts_with( $title, 'tax-' ) ) {
			$data = explode( '--single-', $title );
			if ( count( $data ) !== 2 ) {
				return null;
			}
			[ $term_id ] = $data;
			$term_id = (int) substr( $term_id, 4 );
			if ( ! $term_id ) {
				return null;
			}
			$term = get_term( $term_id );
			if ( ! $term ) {
				return null;
			}

			return "$term->taxonomy::$term->slug";
		}

		return null;
	}

	private function export_subscriptions() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$subscriptions_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_orders WHERE type = %s", 'subscription' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		$subscriptions_pages = ceil( $subscriptions_count / 20 );
		$upload_dir          = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time        = time();
		$filename            = "subscriptions_{$subscriptions_count}_{$current_time}.csv";
		$filepath            = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = self::open_file( $filepath, 'w' );
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		self::run_clean_hook( $filepath );

		fputcsv( $fp, [
			'Subscription Key',
			'Subscription Status',
			'Customer Email',
			'Subscription Currency',
			'Subscription Subtotal',
			'Subscription Total',
			'Subscription Tax',
			'Date Created (GMT)',
			'Date Updated (GMT)',
			'Date Placed (GMT)',
			'Date Paid (GMT)',
			'Payment Method (name)',
			'Payment Method (title)',
			'IP Address',
			'User Agent',
			'Customer Note',
			'Cart Hash',
			'Parent Order Key',
			'Child Order Keys',
			'Start Date (GMT)',
			'Has Trial',
			'Trial Days',
			'Trial End Date (GMT)',
			'Next Payment Date (GMT)',
			'Meta Data',
		] );

		$index = 0;
		foreach ( range( 1, $subscriptions_pages ) as $page ) {
			try {
				$query = new SubscriptionCollection( [
					'per_page' => 20,
					'paged'    => $page,
					'where'    => [
						[
							'key'     => 'status',
							'value'   => [ 'draft', 'auto-draft', 'trash' ],
							'compare' => 'NOT IN',
						],
					],
				] );
				foreach ( $query->get_results() as $subscription ) {
					if ( $index > 0 ) {
						fputcsv( $fp, [] );
						fputcsv( $fp, [ '# Subscription Entity' ] );
					}
					$index ++;

					$subscription_meta_data = [];
					foreach ( $subscription->get_meta_data() as $meta ) {
						$subscription_meta_data[] = $meta->key . ':' . $meta->value;
					}
					$user         = get_userdata( $subscription->get_customer_id() );
					$parent_order = Helper::get_order( $subscription->get_parent_order_id() );
					$parent_id    = $subscription->get_parent_order_id();

					$related_orders = array_filter( $subscription->get_related_orders(), fn( $order_id ) => $order_id != $parent_id );
					$formatter      = implode( ',', array_fill( 0, count( $related_orders ), '%d' ) );
					global $wpdb;
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- query prepared.
					$child_order_keys = $wpdb->get_results( $wpdb->prepare( "SELECT order_key FROM {$wpdb->prefix}storeengine_order_operational_data WHERE order_id in ($formatter)", ...$related_orders ) );
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- query prepared.
					$child_order_keys = array_map( fn( $obj ) => $obj->order_key, $child_order_keys );

					fputcsv( $fp, [
						esc_html( $subscription->get_order_key() ),
						esc_html( $subscription->get_status() ),
						esc_html( $user ? $user->user_email : $subscription->get_order_email() ),
						esc_html( $subscription->get_currency() ),
						esc_html( $subscription->get_subtotal() ),
						esc_html( $subscription->get_total() ),
						esc_html( $subscription->get_total_tax() ),
						esc_html( $subscription->get_date_created_gmt()->format( 'Y-m-d h:i:s A' ) ),
						esc_html( $subscription->get_date_updated_gmt() ? $subscription->get_date_updated_gmt()->format( 'Y-m-d h:i:s A' ) : '' ),
						esc_html( $subscription->get_order_placed_date_gmt() ? $subscription->get_order_placed_date_gmt()->format( 'Y-m-d h:i:s A' ) : '' ),
						esc_html( $subscription->get_date_paid_gmt() ? $subscription->get_date_paid_gmt()->format( 'Y-m-d h:i:s A' ) : '' ),
						esc_html( $subscription->get_payment_method() ),
						esc_html( $subscription->get_payment_method_title() ),
						esc_html( $subscription->get_ip_address() ),
						esc_html( $subscription->get_user_agent() ),
						esc_html( $subscription->get_customer_note() ),
						esc_html( $subscription->get_cart_hash() ),
						esc_html( $parent_order && ! is_wp_error( $parent_order ) ? $parent_order->get_order_key() : '' ),
						esc_html( implode( ',', $child_order_keys ) ),
						esc_html( $subscription->get_start_date() ? $subscription->get_start_date()->format( 'Y-m-d h:i:s A' ) : '' ),
						esc_html( $subscription->get_trial() ),
						esc_html( $subscription->get_trial_days() ),
						esc_html( $subscription->get_trial_end_date() ? $subscription->get_trial_end_date()->format( 'Y-m-d h:i:s A' ) : '' ),
						esc_html( $subscription->get_next_payment_date() ? $subscription->get_next_payment_date()->format( 'Y-m-d h:i:s A' ) : '' ),
						esc_html( implode( '|', $subscription_meta_data ) ),
					] );

					if ( ! empty( $subscription->get_items() ) ) {
						fputcsv( $fp, [] );
						fputcsv( $fp, [
							'Item Name',
							'Product Slug',
							'Product Type',
							'Price',
							'Quantity',
							'Subtotal',
							'Subtotal (Tax)',
							'Total',
							'Total (Tax)',
							'Product Shipping Type',
							'Is Autocomplete',
							'Price name',
							'Price type',
							'Setup Fee',
							'Setup Fee name',
							'Setup Fee price',
							'Setup Fee type',
							'Trial',
							'Trial days',
							'Expire',
							'Expire days',
							'Payment duration',
							'Payment duration type',
							'Meta Data',
						] );
						/** @var OrderItemProduct $item */
						foreach ( $subscription->get_items() as $item ) {
							$metadata = [];
							foreach ( $item->get_formatted_metadata() as $meta ) {
								$metadata[] = "{$meta['key']}:{$meta['value']}";
							}
							fputcsv( $fp, [
								esc_html( $item->get_name() ),
								esc_html( $item->get_product() ? $item->get_product()->get_slug() : '' ),
								esc_html( $item->get_product_type() ),
								esc_html( $item->get_price() ),
								esc_html( $item->get_quantity() ),
								esc_html( $item->get_subtotal() ),
								esc_html( $item->get_subtotal_tax() ),
								esc_html( $item->get_total() ),
								esc_html( $item->get_total_tax() ),
								esc_html( $item->get_shipping_type() ),
								$item->get_digital_auto_complete(),
								esc_html( $item->get_price_name() ),
								esc_html( $item->get_price_type() ),
								esc_html( $item->get_setup_fee() ),
								esc_html( $item->get_setup_fee_name() ),
								esc_html( $item->get_setup_fee_price() ),
								esc_html( $item->get_setup_fee_type() ),
								esc_html( $item->get_trial() ),
								esc_html( $item->get_trial_days() ),
								esc_html( $item->get_expire() ),
								esc_html( $item->get_expire_days() ),
								esc_html( $item->get_payment_duration() ),
								esc_html( $item->get_payment_duration_type() ),
								esc_html( implode( '|', $metadata ) ),
							] );
						}
					}

					if ( ! empty( $subscription->get_billing_country() ) ) {
						fputcsv( $fp, [] );
						fputcsv( $fp, [
							'Billing First Name',
							'Billing Last Name',
							'Billing Company',
							'Billing Address 1',
							'Billing Address 2',
							'Billing City',
							'Billing State',
							'Billing Postcode',
							'Billing Country',
							'Billing Email',
							'Billing Phone',
						] );
						fputcsv( $fp, [
							esc_html( $subscription->get_billing_first_name() ),
							esc_html( $subscription->get_billing_last_name() ),
							esc_html( $subscription->get_billing_company() ),
							esc_html( $subscription->get_billing_address_1() ),
							esc_html( $subscription->get_billing_address_2() ),
							esc_html( $subscription->get_billing_city() ),
							esc_html( $subscription->get_billing_state() ),
							esc_html( $subscription->get_billing_postcode() ),
							esc_html( $subscription->get_billing_country() ),
							esc_html( $subscription->get_billing_email() ),
							esc_html( $subscription->get_billing_phone() ),
						] );
					}

					if ( ! empty( $subscription->get_shipping_country() ) ) {
						fputcsv( $fp, [] );
						fputcsv( $fp, [
							'Shipping First Name',
							'Shipping Last Name',
							'Shipping Company',
							'Shipping Address 1',
							'Shipping Address 2',
							'Shipping City',
							'Shipping State',
							'Shipping Postcode',
							'Shipping Country',
							'Shipping Email',
							'Shipping Phone',
						] );
						fputcsv( $fp, [
							esc_html( $subscription->get_shipping_first_name() ),
							esc_html( $subscription->get_shipping_last_name() ),
							esc_html( $subscription->get_shipping_company() ),
							esc_html( $subscription->get_shipping_address_1() ),
							esc_html( $subscription->get_shipping_address_2() ),
							esc_html( $subscription->get_shipping_city() ),
							esc_html( $subscription->get_shipping_state() ),
							esc_html( $subscription->get_shipping_postcode() ),
							esc_html( $subscription->get_shipping_country() ),
							esc_html( $subscription->get_shipping_email() ),
							esc_html( $subscription->get_shipping_phone() ),
						] );
					}

					if ( ! empty( $subscription->get_coupons() ) ) {
						fputcsv( $fp, [] );
						fputcsv( $fp, [
							'Coupon Code',
							'Coupon Amount',
							'Coupon Amount (Tax)',
						] );
						/** @var OrderItemCoupon $coupon */
						foreach ( $subscription->get_coupons() as $coupon ) {
							fputcsv( $fp, [
								esc_html( $coupon->get_code() ),
								esc_html( $coupon->get_discount() ),
								esc_html( $coupon->get_discount_tax() ),
							] );
						}
					}

					if ( ! empty( $subscription->get_taxes() ) ) {
						fputcsv( $fp, [] );
						fputcsv( $fp, [
							'Tax Name',
							'Tax Label',
							'Tax Amount',
							'Tax Rate(%)',
							'Shipping Tax Amount',
						] );

						foreach ( $subscription->get_taxes() as $tax ) {
							fputcsv( $fp, [
								esc_html( $tax->get_name() ),
								esc_html( $tax->get_label() ),
								esc_html( $tax->get_tax_total() ),
								esc_html( $tax->get_rate_percent() ),
								esc_html( $tax->get_shipping_tax_total() ),
							] );
						}
					}

					if ( ! empty( $subscription->get_shipping_methods() ) ) {
						fputcsv( $fp, [] );
						fputcsv( $fp, [
							'Shipping Method',
							'Shipping Method Title',
							'Amount',
							'Amount (Tax)',
							'Tax Status',
						] );
						foreach ( $subscription->get_shipping_methods() as $shipping_method ) {
							fputcsv( $fp, [
								esc_html( $shipping_method->get_method_id() ),
								esc_html( $shipping_method->get_method_title() ),
								esc_html( $shipping_method->get_total() ),
								esc_html( $shipping_method->get_total_tax() ),
								esc_html( $shipping_method->get_tax_status() ),
							] );
						}
					}

					if ( ! empty( $subscription->get_fees() ) ) {
						fputcsv( $fp, [] );
						fputcsv( $fp, [
							'Fee Name',
							'Fee Amount',
							'Fee Amount (Tax)',
							'Tax Status',
						] );
						foreach ( $subscription->get_fees() as $fee ) {
							fputcsv( $fp, [
								esc_html( $fee->get_name() ),
								esc_html( $fee->get_amount() ),
								esc_html( $fee->get_total_tax() ),
								esc_html( $fee->get_tax_status() ),
							] );
						}
					}

					self::$sse->emitEvent( [
						'type'       => 'progress',
						'percentage' => round( ( $index / $subscriptions_count ) * 100 ),
					] );
				}
			} catch ( StoreEngineInvalidArgumentException $e ) {
				self::close_file( $fp );
				self::$sse->emitEvent( [
					'type'    => 'error',
					'message' => $e->getMessage(),
				], true );
			}
		}

		self::close_file( $fp );

		self::$sse->emitEvent( [
			'type'    => 'filename',
			'message' => $filename,
		] );

		self::$sse->emitEvent( [
			'type'    => 'complete',
			'message' => esc_html__( 'Orders export file has been ready.', 'storeengine' ),
		], true );
	}

	public static function run_clean_hook( string $filepath ) {
		$hook = 'storeengine/csv/clean_tmp_csv';
		$args = [ $filepath ];

		// Skip if a cleanup is already scheduled for this exact file.
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args ) ) {
			return;
		}

		as_schedule_single_action( time() + 3600, $hook, $args );
	}

	private static function open_file( string $filename, string $mode, bool $use_include_path = false, $context = null ) {
		// @TODO use FS utils.
		return fopen( $filename, $mode, $use_include_path, $context ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	}

	private static function close_file( $stream ): bool {
		return fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}
}
