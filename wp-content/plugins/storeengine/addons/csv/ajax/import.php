<?php

namespace StoreEngine\Addons\Csv\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Attribute;
use StoreEngine\Classes\EventStreamServer;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Classes\Product\VariableProduct;
use StoreEngine\Classes\ProductFactory;
use StoreEngine\Classes\Variation;
use StoreEngine\Database;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Import extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_csv';

	protected static EventStreamServer $sse;

	protected array $orders_key_mapping = [
		'Order Key'              => 'order_key',
		'Order Status'           => 'order_status',
		'Customer Email'         => 'customer_email',
		'Order Currency'         => 'order_currency',
		'Order Subtotal'         => 'order_subtotal',
		'Order Total'            => 'order_total',
		'Order Tax'              => 'order_tax',
		'Date Created (GMT)'     => 'date_created_gmt',
		'Date Updated (GMT)'     => 'date_updated_gmt',
		'Date Placed (GMT)'      => 'date_placed_gmt',
		'Date Paid (GMT)'        => 'order_paid_date_gmt',
		'Payment Method (name)'  => 'payment_method',
		'Payment Method (title)' => 'payment_method_title',
		'Transaction ID'         => 'transaction_id',
		'IP Address'             => 'ip_address',
		'User Agent'             => 'user_agent',
		'Customer Note'          => 'customer_note',
		'Cart Hash'              => 'cart_hash',
		'Meta Data'              => 'meta_data',
	];

	protected array $customers_key_mapping = [
		'Username'              => 'username',
		'First Name'            => 'first_name',
		'Last Name'             => 'last_name',
		'Nick Name'             => 'nickname',
		'Display Name'          => 'display_name',
		'Email'                 => 'email',
		'Website'               => 'url',
		'Biographical Info'     => 'description',
		'Registered Date (GMT)' => 'user_registered_date',
		'Roles'                 => 'roles',
		'Billing First Name'    => 'billing_first_name',
		'Billing Last Name'     => 'billing_last_name',
		'Billing Company'       => 'billing_company',
		'Billing Address 1'     => 'billing_address_1',
		'Billing Address 2'     => 'billing_address_2',
		'Billing City'          => 'billing_city',
		'Billing State'         => 'billing_state',
		'Billing Postcode'      => 'billing_postcode',
		'Billing Country'       => 'billing_country',
		'Billing Email'         => 'billing_email',
		'Billing Phone'         => 'billing_phone',
		'Shipping First Name'   => 'shipping_first_name',
		'Shipping Last Name'    => 'shipping_last_name',
		'Shipping Company'      => 'shipping_company',
		'Shipping Address 1'    => 'shipping_address_1',
		'Shipping Address 2'    => 'shipping_address_2',
		'Shipping City'         => 'shipping_city',
		'Shipping State'        => 'shipping_state',
		'Shipping Postcode'     => 'shipping_postcode',
		'Shipping Country'      => 'shipping_country',
		'Shipping Email'        => 'shipping_email',
		'Shipping Phone'        => 'shipping_phone',
		'Email Subscribe'       => 'subscribe_to_email',
	];

	protected array $products_key_mapping = [
		'ID'                 => 'id',
		'Type'               => 'type',
		'Name'               => 'name',
		'Slug'               => 'slug',
		'Published'          => 'published_date_gmt',
		'Description'        => 'content',
		'Is Hide'            => 'hide',
		'Shipping Type'      => 'shipping_type',
		'Weight'             => 'weight',
		'Weight Unit'        => 'weight_unit',
		'Product Categories' => 'product_categories',
		'Product Tags'       => 'product_tags',
		'Images'             => 'product_gallery',
	];

	protected array $access_groups_key_mapping = [
		'Access Group Name'              => 'name',
		'Unauthorized Access Message'    => 'message',
		'Protected Content Type'         => 'content_type',
		'Specific Content Items'         => 'specific_content_items',
		'Excluded Content Items'         => 'excluded_content_items',
		'Downloadable files'             => 'downloadable_files',
		'Enable Redirect URL'            => 'enable_redirect_url',
		'Redirect URL'                   => 'redirect_url',
		'Enable Access Group Expiration' => 'enable_expiration',
		'Expiration Date type'           => 'expiration_date_type',
		'Expiration Relative Date'       => 'expiration_relative_date',
		'Expiration Specific Date'       => 'expiration_specific_date',
		'User Role Sync'                 => 'user_role_sync',
		'Priority'                       => 'priority',
	];

	protected array $subscriptions_key_mapping = [
		'Subscription Key'        => 'subscription_key',
		'Subscription Status'     => 'subscription_status',
		'Customer Email'          => 'customer_email',
		'Subscription Currency'   => 'subscription_currency',
		'Subscription Subtotal'   => 'subscription_subtotal',
		'Subscription Total'      => 'subscription_total',
		'Subscription Tax'        => 'subscription_tax',
		'Date Created (GMT)'      => 'date_created_gmt',
		'Date Updated (GMT)'      => 'date_updated_gmt',
		'Date Placed (GMT)'       => 'date_placed_gmt',
		'Date Paid (GMT)'         => 'subscription_paid_date_gmt',
		'Payment Method (name)'   => 'payment_method',
		'Payment Method (title)'  => 'payment_method_title',
		'IP Address'              => 'ip_address',
		'User Agent'              => 'user_agent',
		'Customer Note'           => 'customer_note',
		'Cart Hash'               => 'cart_hash',
		'Parent Order Key'        => 'parent_order_key',
		'Child Order Keys'        => 'child_order_keys',
		'Start Date (GMT)'        => 'start_date',
		'Has Trial'               => 'has_trial',
		'Trial Days'              => 'trial_days',
		'Trial End Date (GMT)'    => 'trial_end_date',
		'Next Payment Date (GMT)' => 'next_payment_date',
		'Meta Data'               => 'meta_data',
	];

	public function __construct() {
		$this->actions = [
			'import'               => [
				'callback'   => [ $this, 'import' ],
				'capability' => 'manage_options',
				'fields'     => [
					'type' => 'string',
				],
			],
			'import_process'       => [
				'callback'   => [ $this, 'import_process' ],
				'capability' => 'manage_options',
				'fields'     => [
					'filename' => 'string',
					'type'     => 'string',
					'index'    => 'integer',
				],
			],
			'download_sample_file' => [
				'callback'   => [ $this, 'download_sample_file' ],
				'capability' => 'manage_options',
				'fields'     => [
					'type' => 'string',
				],
			],
		];
	}

	public function import( array $payload ) {
		if ( ! isset( $_FILES['file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( __( 'No file was uploaded.', 'storeengine' ) );
		}

		$type = $payload['type'];
		if ( ! in_array( $type, [ 'products', 'orders', 'customers', 'access_groups', 'subscriptions' ], true ) ) {
			wp_send_json_error( __( 'Invalid type!', 'storeengine' ) );
		}

		$file   = $_FILES['file']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$folder = Helper::get_upload_dir() . '/csv/';

		if ( ! file_exists( $folder ) ) {
			wp_mkdir_p( $folder );
		}

		$max_size = 20 * 1024 * 1024; // 20 MB
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( __( 'File too large. Maximum size is 20MB.', 'storeengine' ) );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			wp_send_json_error( __( 'Only CSV files are allowed.', 'storeengine' ) );
		}

		$filename = sanitize_file_name( wp_basename( $file['name'] ) );
		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $filename, [ 'csv' => 'text/csv' ] );
		if ( ! isset( $filetype['ext'] ) || $filetype['ext'] !== 'csv' ) {
			wp_send_json_error( 'Invalid file type. Only CSV files are allowed.' );
		}

		$valid_csv = \StoreEngine\Addons\Csv\Utils\Import::validate_csv_structure( $file['tmp_name'], array_keys( $this->{"{$type}_key_mapping"} ) );
		if ( is_wp_error( $valid_csv ) ) {
			wp_send_json_error( $valid_csv->get_error_message() );
		}

		$target = trailingslashit( $folder ) . $filename;

		// @TODO Use wp_handle_upload()..
		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			wp_send_json_error( __( 'Failed to move uploaded file.', 'storeengine' ) );
		}

		wp_send_json_success( [
			'filename' => $filename,
		] );
	}

	public function import_process( array $payload ) {
		if ( ! isset( $payload['type'] ) ) {
			wp_send_json_error( __( 'Type is required.', 'storeengine' ) );
		}

		$type = $payload['type'];
		if ( ! in_array( $type, [ 'products', 'orders', 'customers', 'access_groups', 'subscriptions' ], true ) ) {
			wp_send_json_error( __( 'Invalid type!', 'storeengine' ) );
		}

		if ( 'access_groups' === $type && ! Helper::get_addon_active_status( 'membership' ) ) {
			wp_send_json_error( __( 'Please active Membership addon to import Access groups.', 'storeengine' ) );
		}

		if ( 'subscriptions' === $type && ! Helper::get_addon_active_status( 'subscription' ) ) {
			wp_send_json_error( __( 'Please active Subscription addon to import Subscriptions.', 'storeengine' ) );
		}

		if ( ! isset( $payload['filename'] ) ) {
			wp_send_json_error( __( 'Filename is required.', 'storeengine' ) );
		}
		$filename = $payload['filename'];

		self::$sse = new EventStreamServer();
		self::$sse->listen( function () use ( $type, $filename, $payload ) {
			/**
			 * @see import_products()
			 * @see import_orders()
			 * @see import_customers()
			 * @see import_access_groups()
			 * @see import_subscriptions()
			 */
			$this->{"import_$type"}( $filename, $payload['index'] ?? null );
		} );
	}

	private function import_products( string $filename, ?int $start_index = null ) {
		if ( null === $start_index ) {
			$this->chunk_products( $filename );

			return;
		}

		$this->import_chunk_products( $filename, $start_index );
	}

	private function chunk_products( string $filename ) {
		$filepath = Helper::get_upload_dir() . '/csv/' . $filename;
		$fp       = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for reading.', 'storeengine' ),
			], true );
		}

		$products_key_mapping   = $this->products_key_mapping;
		$prices_key_mapping     = [
			'Price name'            => 'name',
			'Price type'            => 'type',
			'Price'                 => 'price',
			'Compare price'         => 'compare_price',
			'Setup Fee'             => 'setup_fee',
			'Setup Fee name'        => 'setup_fee_name',
			'Setup Fee price'       => 'setup_fee_price',
			'Setup Fee type'        => 'setup_fee_type',
			'Trial'                 => 'trial',
			'Trial days'            => 'trial_days',
			'Expire'                => 'expire',
			'Expire days'           => 'expire_days',
			'Payment duration'      => 'payment_duration',
			'Payment duration type' => 'payment_duration_type',
			'Upgradeable'           => 'upgradeable',
			'Order'                 => 'order',
		];
		$downloads_key_mapping  = [
			'Download ID'     => 'id',
			'Download Name'   => 'name',
			'Download URL'    => 'file',
			'Download Status' => 'enabled',
		];
		$variations_key_mapping = [
			'Variation Price'      => 'price',
			'Variation Image'      => 'featured_image',
			'Variation Attributes' => 'attributes',
			'Price Index'          => 'price_index',
			'Variation SKU'        => 'sku',
			'Variation Barcode'    => 'barcode',
			'Variation Cost'       => 'cost_price',
			'Variation Stock'      => 'stock_quantity',
		];

		$products               = [];
		$current_section_header = 'product';
		$header_row             = [];
		$recent_product         = null;
		while ( ( $row = fgetcsv( $fp ) ) !== false ) {
			if ( empty( $row ) || ( 1 === count( array_unique( $row ) ) && empty( $row[0] ) ) ) {
				continue;
			}

			if ( ( in_array( 'Name', $row, true ) && in_array( 'Slug', $row, true ) ) || in_array( '# Product Entity', $row, true ) ) {
				$current_section_header = 'product';
				$header_row             = [
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
				];
				continue;
			} elseif ( in_array( 'Price name', $row, true ) && in_array( 'Price type', $row, true ) ) {
				$current_section_header = 'price';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 16 ) );
				continue;
			} elseif ( in_array( 'Download ID', $row, true ) && in_array( 'Download Name', $row, true ) ) {
				$current_section_header = 'download';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 4 ) );
				continue;
			} elseif ( in_array( 'Variation Price', $row, true ) && in_array( 'Variation Image', $row, true ) ) {
				$current_section_header = 'variation';
				// Header may include the new optional Variation SKU/Barcode/Cost/Stock columns.
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 8 ) );
				continue;
			}

			if ( $current_section_header === 'product' ) {
				$recent_product              = sanitize_text_field( $row[ array_search( 'Slug', $header_row, true ) ] );
				$data                        = array_slice( $row, 0, 13 );
				$data                        = array_combine( $header_row, array_map( function ( $value, $key ) {
					return 5 === $key ? wp_kses_post( $value ) : sanitize_text_field( $value );
				}, $data, array_keys( $data ) ) );
				$products[ $recent_product ] = Helper::rename_array_keys( $data, $products_key_mapping );
			} elseif ( $current_section_header === 'price' ) {
				$products[ $recent_product ]['prices'][] = Helper::rename_array_keys(
					array_combine( $header_row, array_map( 'sanitize_text_field', array_slice( $row, 0, 16 ) ) ),
					$prices_key_mapping
				);
			} elseif ( $current_section_header === 'download' ) {
				$products[ $recent_product ]['downloads'][] = Helper::rename_array_keys(
					array_combine( $header_row, array_map( 'sanitize_text_field', array_slice( $row, 0, 4 ) ) ),
					$downloads_key_mapping
				);
			} elseif ( $current_section_header === 'variation' ) {
				$slice = array_slice( $row, 0, count( $header_row ) );
				// Normalize length to header — pad missing optional cells with empty strings.
				while ( count( $slice ) < count( $header_row ) ) {
					$slice[] = '';
				}
				$products[ $recent_product ]['variations'][] = Helper::rename_array_keys(
					array_combine( $header_row, array_map( 'sanitize_text_field', $slice ) ),
					$variations_key_mapping
				);
			}
		}
		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		wp_delete_file( $filepath );

		$expire           = 60 * 60 * 4;
		$chunked_products = array_chunk( $products, 30, true );
		$filename         = $this->get_filename_without_extension( $filename );
		set_transient( 'storeengine_csv_products_' . $filename . '_count', count( $products ), $expire );
		foreach ( $chunked_products as $index => $chunk ) {
			set_transient( 'storeengine_csv_products_' . $filename . '_' . $index, $chunk, $expire );
		}

		self::$sse->emitEvent( [
			'type'  => 'chunk-completed',
			'total' => count( $chunked_products ),
		], true );
	}

	private function import_chunk_products( string $filename, $start_index ) {
		$filename = $this->get_filename_without_extension( $filename );
		$products = get_transient( 'storeengine_csv_products_' . $filename . '_' . $start_index );
		if ( ! $products ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to retrieve chunked products.', 'storeengine' ),
			], true );
		}

		$index          = ( $start_index * 30 ) + 1;
		$products_count = get_transient( 'storeengine_csv_products_' . $filename . '_count' );
		foreach ( $products as $product_slug => $product_data ) {
			$posts       = get_posts( [
				'name'           => $product_slug,
				'post_type'      => 'storeengine_product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			] );
			$has_product = ! empty( $posts );
			if ( $has_product ) {
				continue;
			}

			$product = ProductFactory::getProduct( 0, $product_data['type'] );

			$setters = $product_data;
			unset( $setters['product_categories'] );
			unset( $setters['product_tags'] );
			unset( $setters['product_gallery'] );
			unset( $setters['id'] );
			foreach ( $setters as $key => $value ) {
				$method = "set_$key";
				if ( method_exists( $product, $method ) ) {
					$product->$method( $value );
				}
			}
			$product->save();

			if ( ! empty( $product_data['product_gallery'] ) ) {
				$images      = explode( ',', $product_data['product_gallery'] );
				$gallery_ids = [];
				foreach ( $images as $image ) {
					$attachment_id = $this->remote_to_local_image( trim( $image ) );
					if ( is_wp_error( $attachment_id ) ) {
						self::$sse->emitEvent( [
							'type'    => 'error',
							'message' => $attachment_id->get_error_messages(),
						] );
						continue;
					}
					$gallery_ids[] = $attachment_id;
				}
				$product->update_metadata( 'product_gallery_ids', $gallery_ids );
			}

			if ( ! empty( $product_data['product_categories'] ) ) {
				$categories = explode( ',', $product_data['product_categories'] );
				$terms      = [];
				foreach ( $categories as $category ) {
					$category       = trim( $category );
					$sub_categories = explode( '>', $category );
					if ( is_array( $sub_categories ) && ! empty( $sub_categories ) ) {
						$parent_category = 0;
						$j               = 0;
						while ( $j < count( $sub_categories ) ) {
							$term = wp_insert_term( $sub_categories[ $j ], 'storeengine_product_category', [
								'parent' => $parent_category,
							] );
							$j ++;
							if ( is_wp_error( $term ) ) {
								self::$sse->emitEvent( [
									'type'    => 'error',
									'message' => $term->get_error_message(),
								] );
								continue;
							}
							$terms[]         = $term['term_id'];
							$parent_category = $term['term_id'];
						}
						continue;
					}

					$term = get_term_by( 'name', $category, 'storeengine_product_category' );
					if ( $term ) {
						$terms[] = $term->term_id;
					} else {
						$term = wp_insert_term( $category, 'storeengine_product_category' );
						if ( is_wp_error( $term ) ) {
							self::$sse->emitEvent( [
								'type'    => 'error',
								'message' => $term->get_error_message(),
							] );
							continue;
						}
						$terms[] = $term['term_id'];
					}
				}

				wp_set_object_terms( $product->get_id(), $terms, 'storeengine_product_category' );
			}

			if ( ! empty( $product_data['product_tags'] ) ) {
				$tags  = explode( ',', $product_data['product_tags'] );
				$terms = [];
				foreach ( $tags as $tag ) {
					$tag  = trim( $tag );
					$term = get_term_by( 'name', $tag, 'storeengine_product_tag' );
					if ( $term ) {
						$terms[] = $term->term_id;
					} else {
						$term = wp_insert_term( $tag, 'storeengine_product_tag' );
						if ( is_wp_error( $term ) ) {
							self::$sse->emitEvent( [
								'type'    => 'error',
								'message' => $term->get_error_message(),
							] );
							continue;
						}
						$terms[] = $term['term_id'];
					}
				}

				wp_set_object_terms( $product->get_id(), $terms, 'storeengine_product_tag' );
			}

			$prices = [];
			if ( isset( $product_data['prices'] ) && is_array( $product_data['prices'] ) ) {
				foreach ( $product_data['prices'] as $price_data ) {
					$price = new Price();
					$price->set_product_id( $product->get_id() );
					foreach ( $price_data as $price_key => $value ) {
						if ( ! empty( $value ) ) {
							$price->{"set_$price_key"}( $value );
						}
					}

					$price->save();
					$prices[] = $price->get_id();
				}
			}

			if ( isset( $product_data['downloads'] ) && is_array( $product_data['downloads'] ) ) {
				$product->set_downloadable_files( $product_data['downloads'] );
				$product->save();
			}

			if ( isset( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
				$taxonomy_labels = [];
				$variation_data  = [];
				foreach ( $product_data['variations'] as $v_data ) {
					$variation_attributes = explode( ',', $v_data['attributes'] );
					$variant_data         = [];
					foreach ( $variation_attributes as $variation_attribute ) {
						$variation_attribute = trim( $variation_attribute );
						$variant             = explode( ':', $variation_attribute );
						if ( count( $variant ) !== 2 ) {
							continue;
						}
						$taxonomy_labels[] = strtolower( trim( $variant[0] ) );
						$variant_data[]    = [
							'taxonomy' => strtolower( trim( $variant[0] ) ),
							'value'    => trim( $variant[1] ),
						];
					}
					$variation_data[] = [
						'price'          => $v_data['price'],
						'price_index'    => $v_data['price_index'],
						'featured_image' => $v_data['featured_image'],
						'attributes'     => $variant_data,
					];
				}

				$taxonomy_labels             = array_unique( $taxonomy_labels );
				$taxonomy_labels_formatter   = implode( ',', array_fill( 0, count( $taxonomy_labels ), '%s' ) );
				$attribute_taxonomies_exists = [];

				global $wpdb;

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$results = $wpdb->get_results( $wpdb->prepare(
					"SELECT attribute_id, attribute_label FROM {$wpdb->prefix}storeengine_attribute_taxonomies
                    		WHERE attribute_label IN ($taxonomy_labels_formatter)",
					...$taxonomy_labels
				) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

				foreach ( $results as $result ) {
					$attribute_taxonomies_exists[ $result->attribute_label ] = $result->attribute_id;
				}

				$taxonomy_names = [];
				foreach ( $taxonomy_labels as $taxonomy_label ) {
					if ( isset( $attribute_taxonomies_exists[ $taxonomy_label ] ) ) {
						continue;
					}
					$name          = sanitize_title( $taxonomy_label );
					$prefixed_name = Helper::get_attribute_taxonomy_name( $name );
					while ( strlen( $prefixed_name ) > 30 ) {
						$previous = $name;

						// Match and remove the last "word" including its delimiter
						$name = preg_replace( '/[\s\-_]+[^-\s_]+$/u', '', trim( $name ) );

						// If nothing changed, break to avoid infinite loop
						if ( $name === $previous ) {
							break;
						}
						$prefixed_name = Helper::get_attribute_taxonomy_name( $name );
					}

					$i = 1;
					while ( taxonomy_exists( $prefixed_name ) ) {
						$name          .= $i;
						$prefixed_name = Helper::get_attribute_taxonomy_name( $name );
						$i ++;
					}
					$taxonomy_names[ $taxonomy_label ] = $name;
				}

				foreach ( $taxonomy_names as $taxonomy_label => $taxonomy_name ) {
					$attribute = new Attribute();
					$attribute->set_label( $taxonomy_label );
					$attribute->set_name( $taxonomy_name );
					$attribute->set_orderby( 'custom-ordering' );
					$attribute->save();
				}

				$all_product_taxonomies = [];
				foreach ( $variation_data as $variation_datum ) {
					$variation_terms = [];
					foreach ( $variation_datum['attributes'] as $variant_datum ) {
						Database::register_attribute_taxonomy( $variant_datum['taxonomy'], $variant_datum['taxonomy'], true );
						$term    = get_term_by( 'name', $variant_datum['value'], Helper::get_attribute_taxonomy_name( $variant_datum['taxonomy'] ) );
						$term_id = $term ? $term->term_id : null;
						if ( ! $term ) {
							$term = wp_insert_term( $variant_datum['value'], Helper::get_attribute_taxonomy_name( $variant_datum['taxonomy'] ) );
							if ( is_wp_error( $term ) ) {
								self::$sse->emitEvent( [
									'type'    => 'error',
									'message' => $term->get_error_message(),
								] );
								continue;
							}
							$term_id = $term['term_id'];
						}
						$variation_terms[]        = $term_id;
						$all_product_taxonomies[] = Helper::get_attribute_taxonomy_name( $variant_datum['taxonomy'] );
						wp_set_object_terms( $product->get_id(), $term_id, Helper::get_attribute_taxonomy_name( $variant_datum['taxonomy'] ) );
					}

					$variation = new Variation();
					$variation->set_product_id( $product->get_id() );
					$variation->set_attributes( $variation_terms );
					if ( ! empty( $variation_datum['price'] ) ) {
						$variation->set_price( (float) $variation_datum['price'] );
					}
					if ( ! empty( $variation_datum['featured_image'] ) ) {
						$attachment_id = $this->remote_to_local_image( trim( $variation_datum['featured_image'] ) );
						if ( is_wp_error( $attachment_id ) ) {
							self::$sse->emitEvent( [
								'type'    => 'error',
								'message' => $attachment_id->get_error_messages(),
							] );
						} else {
							$variation->set_featured_image( $attachment_id );
						}
					}
					if ( ! empty( $variation_datum['price_index'] ) ) {
						$variation->set_price_id( $prices[ max( absint( $variation_datum['price_index'] ) - 1, 0 ) ] );
					}

					if ( ! empty( $variation_datum['sku'] ) && method_exists( $variation, 'set_sku' ) ) {
						$variation->set_sku( sanitize_text_field( (string) $variation_datum['sku'] ) );
					}
					if ( ! empty( $variation_datum['barcode'] ) && method_exists( $variation, 'set_barcode' ) ) {
						$variation->set_barcode( sanitize_text_field( (string) $variation_datum['barcode'] ) );
					}
					if ( isset( $variation_datum['cost_price'] ) && '' !== $variation_datum['cost_price'] && method_exists( $variation, 'set_cost_price' ) ) {
						$variation->set_cost_price( (float) $variation_datum['cost_price'] );
					}
					if ( isset( $variation_datum['stock_quantity'] ) && '' !== $variation_datum['stock_quantity'] && method_exists( $variation, 'set_stock_quantity' ) ) {
						$qty = (int) $variation_datum['stock_quantity'];
						$variation->set_manage_stock( true );
						$variation->set_stock_quantity( $qty );
					}

					$variation->save();

					// Let multi-location addons (e.g. inventory-pro) mirror this
					// import into per-location stock. CSV addon stays free-only.
					if ( isset( $variation_datum['stock_quantity'] ) && '' !== $variation_datum['stock_quantity'] && $variation->get_id() ) {
						/**
						 * Fires after CSV import sets stock on a variation.
						 *
						 * @param int $product_id
						 * @param int $variation_id
						 * @param int $qty
						 */
						do_action(
							'storeengine/inventory/import_stock_set',
							$product->get_id(),
							$variation->get_id(),
							(int) $variation_datum['stock_quantity']
						);
					}
				}

				$product->set_attributes_order( array_unique( $all_product_taxonomies ) );
				$product->save();
			}

			self::$sse->emitEvent( [
				'type'       => 'progress',
				'percentage' => round( ( $index / $products_count ) * 100 ),
			] );
			$index ++;
		}

		delete_transient( 'storeengine_csv_products_' . $filename . '_' . $start_index );
		wp_cache_flush();

		self::$sse->emitEvent( [
			'type' => 'completed',
		], true );
	}

	private function remote_to_local_image( string $url ) {
		if ( empty( $url ) || ! wp_http_validate_url( $url ) ) {
			return new Wp_Error( 'invalid_url', __( 'Invalid URL', 'storeengine' ) );
		}

		// Download image
		$temp_file = download_url( $url );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$file_array = [
			'name'     => basename( $url ),
			'tmp_name' => $temp_file,
		];

		// Upload to WordPress media library
		$attachment_id = media_handle_sideload( $file_array );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $temp_file );
		}

		return $attachment_id;
	}

	private function import_orders( string $filename, ?int $start_index = null ) {
		if ( null === $start_index ) {
			$this->chunk_orders( $filename );

			return;
		}

		\StoreEngine\Addons\Csv\Utils\Import::import_chunk_orders( $filename, $start_index, self::$sse );
	}

	private function chunk_orders( string $filename ) {
		$dir      = Helper::get_upload_dir() . '/csv/';
		$filepath = $dir . $filename;
		$fp       = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for reading.', 'storeengine' ),
			], true );
		}

		$order_key_mapping            = $this->orders_key_mapping;
		$order_item_key_mapping       = [
			'Order Item Name'       => 'name',
			'Product Slug'          => 'product_slug',
			'Product Type'          => 'product_type',
			'Price'                 => 'price',
			'Quantity'              => 'quantity',
			'Subtotal'              => 'subtotal',
			'Subtotal (Tax)'        => 'subtotal_tax',
			'Total'                 => 'total',
			'Total (Tax)'           => 'total_tax',
			'Product Shipping Type' => 'shipping_type',
			'Is Autocomplete'       => 'digital_auto_complete',
			'Price name'            => 'price_name',
			'Price type'            => 'price_type',
			'Setup Fee'             => 'setup_fee',
			'Setup Fee name'        => 'setup_fee_name',
			'Setup Fee price'       => 'setup_fee_price',
			'Setup Fee type'        => 'setup_fee_type',
			'Trial'                 => 'trial',
			'Trial days'            => 'trial_days',
			'Expire'                => 'expire',
			'Expire days'           => 'expire_days',
			'Payment duration'      => 'payment_duration',
			'Payment duration type' => 'payment_duration_type',
			'Meta Data'             => 'meta_data',
		];
		$billing_address_key_mapping  = [
			'Billing First Name' => 'billing_first_name',
			'Billing Last Name'  => 'billing_last_name',
			'Billing Company'    => 'billing_company',
			'Billing Address 1'  => 'billing_address_1',
			'Billing Address 2'  => 'billing_address_2',
			'Billing City'       => 'billing_city',
			'Billing State'      => 'billing_state',
			'Billing Postcode'   => 'billing_postcode',
			'Billing Country'    => 'billing_country',
			'Billing Email'      => 'billing_email',
			'Billing Phone'      => 'billing_phone',
		];
		$shipping_address_key_mapping = [
			'Shipping First Name' => 'shipping_first_name',
			'Shipping Last Name'  => 'shipping_last_name',
			'Shipping Company'    => 'shipping_company',
			'Shipping Address 1'  => 'shipping_address_1',
			'Shipping Address 2'  => 'shipping_address_2',
			'Shipping City'       => 'shipping_city',
			'Shipping State'      => 'shipping_state',
			'Shipping Postcode'   => 'shipping_postcode',
			'Shipping Country'    => 'shipping_country',
			'Shipping Email'      => 'shipping_email',
			'Shipping Phone'      => 'shipping_phone',
		];
		$coupon_key_mapping           = [
			'Coupon Code'         => 'code',
			'Coupon Amount'       => 'discount',
			'Coupon Amount (Tax)' => 'discount_tax',
		];
		$tax_key_mapping              = [
			'Tax Name'            => 'name',
			'Tax Label'           => 'label',
			'Tax Amount'          => 'tax_total',
			'Tax Rate(%)'         => 'rate_percent',
			'Shipping Tax Amount' => 'shipping_tax_total',
		];
		$shipping_key_mapping         = [
			'Shipping Method'       => 'name',
			'Shipping Method Title' => 'method_title',
			'Amount'                => 'total',
			'Amount (Tax)'          => 'total_tax',
			'Tax Status'            => 'tax_status',
		];
		$fee_key_mapping              = [
			'Fee Name'         => 'name',
			'Fee Amount'       => 'amount',
			'Fee Amount (Tax)' => 'total_tax',
			'Tax Status'       => 'tax_status',
		];
		$download_key_mapping         = [
			'Download ID'               => 'download_id',
			'Product Slug'              => 'product_slug',
			'Date Access Granted (GMT)' => 'access_granted',
		];
		$refund_key_mapping           = [
			'Refund Amount' => 'amount',
			'Refund Reason' => 'reason',
			'Refund By'     => 'refund_by',
		];

		$orders                 = [];
		$current_section_header = 'order';
		$header_row             = [];
		$recent_order           = null;
		while ( ( $row = fgetcsv( $fp ) ) !== false ) {
			if ( empty( $row ) || ( 1 === count( array_unique( $row ) ) && empty( $row[0] ) ) ) {
				continue;
			}

			if ( ( in_array( 'Order Key', $row, true ) && in_array( 'Order Status', $row, true ) ) || in_array( '# Order Entity', $row, true ) ) {
				$current_section_header = 'order';
				$header_row             = array_keys( $order_key_mapping );
				continue;
			} elseif ( in_array( 'Order Item Name', $row, true ) ) {
				$current_section_header = 'order_item';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 24 ) );
				continue;
			} elseif ( in_array( 'Billing First Name', $row, true ) && in_array( 'Billing Last Name', $row, true ) ) {
				$current_section_header = 'billing_address';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 11 ) );
				continue;
			} elseif ( in_array( 'Shipping First Name', $row, true ) && in_array( 'Shipping Last Name', $row, true ) ) {
				$current_section_header = 'shipping_address';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 11 ) );
				continue;
			} elseif ( in_array( 'Coupon Code', $row, true ) && in_array( 'Coupon Amount', $row, true ) ) {
				$current_section_header = 'coupon';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 3 ) );
				continue;
			} elseif ( in_array( 'Tax Name', $row, true ) && in_array( 'Tax Label', $row, true ) ) {
				$current_section_header = 'tax';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 5 ) );
				continue;
			} elseif ( in_array( 'Shipping Method', $row, true ) && in_array( 'Shipping Method Title', $row, true ) ) {
				$current_section_header = 'shipping';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 5 ) );
				continue;
			} elseif ( in_array( 'Fee Name', $row, true ) && in_array( 'Fee Amount', $row, true ) ) {
				$current_section_header = 'fee';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 4 ) );
				continue;
			} elseif ( in_array( 'Download ID', $row, true ) ) {
				$current_section_header = 'download';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 3 ) );
				continue;
			} elseif ( in_array( 'Refund Amount', $row, true ) ) {
				$current_section_header = 'refund';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 3 ) );
				continue;
			}

			if ( 'order' === $current_section_header ) {
				$recent_order            = $row[ array_search( 'Order Key', $header_row, true ) ];
				$order_data              = array_slice( $row, 0, count( $order_key_mapping ) );
				$orders[ $recent_order ] = Helper::rename_array_keys(
					array_combine( $header_row, array_map( function ( $value, $key ) {
						return 2 === $key ? sanitize_email( $value ) : sanitize_text_field( $value );
					}, $order_data, array_keys( $order_data ) ) ),
					$order_key_mapping
				);
			} elseif ( 'order_item' === $current_section_header ) {
				$orders[ $recent_order ]['order_items'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $order_item_key_mapping ) ) )
					),
					$order_item_key_mapping
				);
			} elseif ( 'billing_address' === $current_section_header ) {
				$billing_data                                 = array_slice( $row, 0, count( $billing_address_key_mapping ) );
				$billing_data                                 = array_map( function ( $value, $key ) {
					return 9 === $key ? sanitize_email( $value ) : sanitize_text_field( $value );
				}, $billing_data, array_keys( $billing_data ) );
				$orders[ $recent_order ]['billing_address'][] = Helper::rename_array_keys(
					array_combine( $header_row, $billing_data ),
					$billing_address_key_mapping
				);
			} elseif ( 'shipping_address' === $current_section_header ) {
				$shipping_data                                 = array_slice( $row, 0, count( $shipping_address_key_mapping ) );
				$shipping_data                                 = array_map( function ( $value, $key ) {
					return 9 === $key ? sanitize_email( $value ) : sanitize_text_field( $value );
				}, $shipping_data, array_keys( $shipping_data ) );
				$orders[ $recent_order ]['shipping_address'][] = Helper::rename_array_keys(
					array_combine( $header_row, $shipping_data ),
					$shipping_address_key_mapping
				);
			} elseif ( 'coupon' === $current_section_header ) {
				$orders[ $recent_order ]['coupons'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $coupon_key_mapping ) ) )
					),
					$coupon_key_mapping
				);
			} elseif ( 'tax' === $current_section_header ) {
				$orders[ $recent_order ]['taxes'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $tax_key_mapping ) ) )
					),
					$tax_key_mapping
				);
			} elseif ( 'shipping' === $current_section_header ) {
				$orders[ $recent_order ]['shipping'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $shipping_key_mapping ) ) )
					),
					$shipping_key_mapping
				);
			} elseif ( 'fee' === $current_section_header ) {
				$orders[ $recent_order ]['fees'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $fee_key_mapping ) ) )
					),
					$fee_key_mapping
				);
			} elseif ( 'download' === $current_section_header ) {
				$orders[ $recent_order ]['downloads'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $download_key_mapping ) ) )
					),
					$download_key_mapping
				);
			} elseif ( 'refund' === $current_section_header ) {
				$orders[ $recent_order ]['refunds'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $refund_key_mapping ) ) )
					),
					$refund_key_mapping
				);
			}
		}
		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		wp_delete_file( $filepath );

		$chunked_orders = \StoreEngine\Addons\Csv\Utils\Import::store_chunk_data( 'orders', $filename, $orders );

		self::$sse->emitEvent( [
			'type'  => 'chunk-completed',
			'total' => count( $chunked_orders ),
		], true );
	}

	private function get_filename_without_extension( string $filename ) {
		return pathinfo( $filename, PATHINFO_FILENAME );
	}

	private function import_customers( string $filename, ?int $start_index = null ) {
		if ( null === $start_index ) {
			$this->chunk_customers( $filename );

			return;
		}

		\StoreEngine\Addons\Csv\Utils\Import::import_chunk_customers( $filename, $start_index, self::$sse );
	}

	private function chunk_customers( string $filename ) {
		$filepath = Helper::get_upload_dir() . '/csv/' . $filename;
		$fp       = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for reading.', 'storeengine' ),
			], true );
		}

		$key_mapping = $this->customers_key_mapping;

		$customers = [];
		while ( ( $row = fgetcsv( $fp ) ) !== false ) {
			if ( empty( $row ) || ( 1 === count( array_unique( $row ) ) && empty( $row[0] ) ) || ( in_array( 'Username', $row, true ) && in_array( 'First Name', $row, true ) ) ) {
				continue;
			}

			$sliced_row = array_slice( $row, 0, count( $key_mapping ) );
			$sliced_row = array_map( function ( $value, $key ) {
				return in_array( $key, [ 5, 19, 30 ], true ) ? sanitize_email( $value ) : sanitize_text_field( $value );
			}, $sliced_row, array_keys( $sliced_row ) );
			if ( count( $sliced_row ) !== count( $key_mapping ) ) {
				$sliced_row = array_pad( $sliced_row, count( $key_mapping ), '' );
			}
			$customers[] = Helper::rename_array_keys( array_combine( array_keys( $key_mapping ), $sliced_row ), $key_mapping );
		}
		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		wp_delete_file( $filepath );

		$chunked_customers = \StoreEngine\Addons\Csv\Utils\Import::store_chunk_data( 'customers', $filename, $customers );

		self::$sse->emitEvent( [
			'type'  => 'chunk-completed',
			'total' => count( $chunked_customers ),
		], true );
	}

	private function import_access_groups( string $filename, ?int $start_index = null ) {
		if ( null === $start_index ) {
			$this->chunk_access_groups( $filename );

			return;
		}

		$this->import_chunk_access_groups( $filename, $start_index );
	}

	private function chunk_access_groups( string $filename ) {
		$filepath = Helper::get_upload_dir() . '/csv/' . $filename;
		$fp       = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for reading.', 'storeengine' ),
			], true );
		}

		$access_group_key_mapping = $this->access_groups_key_mapping;
		$prices_key_mapping       = [
			'Product name'          => 'product_name',
			'Product slug'          => 'product_slug',
			'Price name'            => 'name',
			'Price type'            => 'type',
			'Price'                 => 'price',
			'Compare price'         => 'compare_price',
			'Setup Fee'             => 'setup_fee',
			'Setup Fee name'        => 'setup_fee_name',
			'Setup Fee price'       => 'setup_fee_price',
			'Setup Fee type'        => 'setup_fee_type',
			'Trial'                 => 'trial',
			'Trial days'            => 'trial_days',
			'Expire'                => 'expire',
			'Expire days'           => 'expire_days',
			'Payment duration'      => 'payment_duration',
			'Payment duration type' => 'payment_duration_type',
			'Upgradeable'           => 'upgradeable',
			'Order'                 => 'order',
		];
		$features_key_mapping     = [
			'Feature Icon'  => 'icon',
			'Feature Label' => 'label',
		];

		$access_groups          = [];
		$current_section_header = 'access_group';
		$header_row             = [];
		$recent_access_group    = null;
		while ( ( $row = fgetcsv( $fp ) ) !== false ) {
			if ( empty( $row ) || ( 1 === count( array_unique( $row ) ) && empty( $row[0] ) ) ) {
				continue;
			}

			if ( ( in_array( 'Access Group Name', $row, true ) && in_array( 'Unauthorized Access Message', $row, true ) ) || in_array( '# Access Group Entity', $row, true ) ) {
				$current_section_header = 'access_group';
				$header_row             = array_keys( $access_group_key_mapping );
				continue;
			} elseif ( in_array( 'Price name', $row, true ) && in_array( 'Price type', $row, true ) ) {
				$current_section_header = 'price';
				$header_row             = array_slice( $row, 0, count( $prices_key_mapping ) );
				continue;
			} elseif ( in_array( 'Feature Icon', $row, true ) && in_array( 'Feature Label', $row, true ) ) {
				$current_section_header = 'feature';
				$header_row             = array_slice( $row, 0, count( $features_key_mapping ) );
				continue;
			}

			if ( 'access_group' === $current_section_header ) {
				$last_index                            = array_key_last( $access_groups );
				$recent_access_group                   = null !== $last_index ? $last_index + 1 : 0;
				$access_groups_data                    = array_map( 'sanitize_text_field', array_slice( $row, 0, count( $access_group_key_mapping ) ) );
				$access_groups[ $recent_access_group ] = Helper::rename_array_keys( array_combine( $header_row, $access_groups_data ), $access_group_key_mapping );
			} elseif ( 'price' === $current_section_header ) {
				$prices_data                                       = array_slice( $row, 0, count( $prices_key_mapping ) );
				$prices_data                                       = array_map( 'sanitize_text_field', $prices_data );
				$access_groups[ $recent_access_group ]['prices'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						$prices_data
					),
					$prices_key_mapping
				);
			} elseif ( 'feature' === $current_section_header ) {
				$features_data                                       = array_slice( $row, 0, count( $features_key_mapping ) );
				$features_data                                       = array_map( 'sanitize_text_field', $features_data );
				$access_groups[ $recent_access_group ]['features'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						$features_data
					),
					$features_key_mapping
				);
			}
		}
		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		wp_delete_file( $filepath );

		$expire                = 60 * 60 * 4;
		$chunked_access_groups = array_chunk( $access_groups, 30, true );
		$filename              = $this->get_filename_without_extension( $filename );
		set_transient( 'storeengine_csv_access_groups_' . $filename . '_count', count( $access_groups ), $expire );
		foreach ( $chunked_access_groups as $index => $chunk ) {
			set_transient( 'storeengine_csv_access_groups_' . $filename . '_' . $index, $chunk, $expire );
		}

		self::$sse->emitEvent( [
			'type'  => 'chunk-completed',
			'total' => count( $chunked_access_groups ),
		], true );
	}

	private function import_chunk_access_groups( string $filename, int $start_index ) {
		$filename      = $this->get_filename_without_extension( $filename );
		$access_groups = get_transient( 'storeengine_csv_access_groups_' . $filename . '_' . $start_index );
		if ( ! $access_groups ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to retrieve chunked access groups.', 'storeengine' ),
			], true );
		}

		$rules               = [
			'Entire Website'                    => 'basic-global',
			'All Posts'                         => 'post|all',
			'All Categories Archive'            => 'post|all|taxarchive|category',
			'All Tags Archive'                  => 'post|all|taxarchive|post_tag',
			'All Pages'                         => 'page|all',
			'Specific Posts/ Pages/ Taxonomies' => 'specifics',
		];
		$access_groups_count = get_transient( 'storeengine_csv_access_groups_' . $filename . '_count' );
		$index               = ( $start_index * 30 ) + 1;
		foreach ( $access_groups as $access_group_data ) {
			$post_id = wp_insert_post( [
				'post_title'  => $access_group_data['name'],
				'post_type'   => 'storeengine_groups',
				'post_status' => 'publish',
			] );
			if ( ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, '_storeengine_membership_authorization', [
				'type'         => Formatting::string_to_bool( $access_group_data['enable_redirect_url'] ) ? 'redirect' : 'message',
				'redirect_url' => $access_group_data['redirect_url'],
				'message'      => $access_group_data['message'],
			] );
			update_post_meta( $post_id, '_storeengine_membership_expiration', [
				'is_enable_expiration' => Formatting::string_to_bool( $access_group_data['enable_expiration'] ),
				'date_type'            => $access_group_data['expiration_date_type'],
				'fixed_date_duration'  => $access_group_data['expiration_relative_date'],
				'specific_date'        => $access_group_data['expiration_specific_date'],
			] );

			$specific_items = [];
			if ( ! empty( $access_group_data['specific_content_items'] ) ) {
				foreach ( ( explode( ',', $access_group_data['specific_content_items'] ) ?? [] ) as $specific_content_item ) {
					$label_value = $this->get_label_value( $specific_content_item );
					if ( is_array( $label_value ) ) {
						$specific_items[] = $label_value;
					}
				}
			}

			update_post_meta( $post_id, '_storeengine_membership_content_protect_types', [
				'rules'     => array_map( fn( $type ) => $rules[ trim( $type ) ], explode( ',', $access_group_data['content_type'] ) ),
				'specifics' => $specific_items,
			] );

			$excluded_items = [];
			if ( ! empty( $access_group_data['excluded_content_items'] ) ) {
				foreach ( ( explode( ',', $access_group_data['excluded_content_items'] ) ?? [] ) as $excluded_content_item ) {
					$label_value = $this->get_label_value( $excluded_content_item );
					if ( is_array( $label_value ) ) {
						$excluded_items[] = $label_value;
					}
				}
			}
			update_post_meta( $post_id, '_storeengine_membership_content_protect_excluded_items', $excluded_items );

			$roles = [];
			foreach ( ( explode( ',', $access_group_data['user_role_sync'] ) ?? [] ) as $user_role ) {
				if ( isset( wp_roles()->role_names[ trim( $user_role ) ] ) ) {
					$roles[] = [
						'value' => trim( $user_role ),
						'label' => wp_roles()->role_names[ trim( $user_role ) ],
					];
				}
			}
			update_post_meta( $post_id, '_storeengine_membership_user_roles', $roles );
			update_post_meta( $post_id, '_storeengine_membership_priority', $access_group_data['priority'] );

			$downloadable_items = [];
			if ( ! empty( $access_group_data['downloadable_files'] ) ) {
				foreach ( ( explode( ',', $access_group_data['downloadable_files'] ) ?? [] ) as $downloadable_file_url ) {
					$downloadable_file_url = trim( $downloadable_file_url );
					$attachment_id         = $this->remote_to_local_image( $downloadable_file_url );

					if ( ! is_wp_error( $attachment_id ) ) {
						$downloadable_items[] = $attachment_id;
					}
				}
			}
			update_post_meta( $post_id, '_storeengine_membership_attachments', $downloadable_items );

			update_post_meta( $post_id, '_storeengine_membership_features', $access_group_data['features'] ?? [] );

			foreach ( $access_group_data['prices'] as $price_data ) {
				$posts       = get_posts( [
					'name'           => $price_data['product_slug'],
					'post_type'      => 'storeengine_product',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				] );
				$has_product = ! empty( $posts );

				$price_obj = null;
				if ( $has_product ) {
					$product = Helper::get_product( $posts[0]->ID );
					foreach ( $product->get_prices() as $price ) {
						if ( (float) $price_data['price'] === $price->get_price() && $price_data['name'] === $price->get_name() && $price_data['type'] === $price->get_type() ) {
							$price_obj = $price;
							break;
						}
					}
				} else {
					$product = new SimpleProduct();
					$product->set_name( $price_data['product_name'] );
					$product->set_slug( $price_data['product_slug'] );
					$product->set_status( 'publish' );
					$product->set_shipping_type( 'digital' );
					$product->set_digital_auto_complete( true );
					$product->save();
					$price = new Price();
					$price->set_product_id( $product->get_id() );
					foreach ( $price_data as $key => $value ) {
						if ( method_exists( $price, "set_$key" ) ) {
							$price->{"set_$key"}( $value );
						}
					}
					$price->save();
					$price_obj = $price;
				}

				if ( $price_obj ) {
					$price_obj->add_integration( 'storeengine/membership-addon', $post_id );
				}
			}

			self::$sse->emitEvent( [
				'type'       => 'progress',
				'percentage' => round( ( $index / $access_groups_count ) * 100 ),
			] );
			$index ++;
		}

		delete_transient( 'storeengine_csv_access_groups_' . $filename . '_' . $start_index );
		wp_cache_flush();

		self::$sse->emitEvent( [
			'type' => 'completed',
		], true );
	}

	private function import_subscriptions( string $filename, ?int $start_index = null ) {
		if ( null === $start_index ) {
			$this->chunk_subscriptions( $filename );

			return;
		}

		\StoreEngine\Addons\Csv\Utils\Import::import_chunk_subscriptions( $filename, $start_index, self::$sse );
	}

	private function chunk_subscriptions( string $filename ) {
		$filepath = Helper::get_upload_dir() . '/csv/' . $filename;
		$fp       = fopen( $filepath, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for reading.', 'storeengine' ),
			], true );
		}

		$subscription_key_mapping     = $this->subscriptions_key_mapping;
		$order_item_key_mapping       = [
			'Item Name'             => 'name',
			'Product Slug'          => 'product_slug',
			'Product Type'          => 'product_type',
			'Price'                 => 'price',
			'Quantity'              => 'quantity',
			'Subtotal'              => 'subtotal',
			'Subtotal (Tax)'        => 'subtotal_tax',
			'Total'                 => 'total',
			'Total (Tax)'           => 'total_tax',
			'Product Shipping Type' => 'shipping_type',
			'Is Autocomplete'       => 'digital_auto_complete',
			'Price name'            => 'price_name',
			'Price type'            => 'price_type',
			'Setup Fee'             => 'setup_fee',
			'Setup Fee name'        => 'setup_fee_name',
			'Setup Fee price'       => 'setup_fee_price',
			'Setup Fee type'        => 'setup_fee_type',
			'Trial'                 => 'trial',
			'Trial days'            => 'trial_days',
			'Expire'                => 'expire',
			'Expire days'           => 'expire_days',
			'Payment duration'      => 'payment_duration',
			'Payment duration type' => 'payment_duration_type',
			'Meta Data'             => 'meta_data',
		];
		$billing_address_key_mapping  = [
			'Billing First Name' => 'billing_first_name',
			'Billing Last Name'  => 'billing_last_name',
			'Billing Company'    => 'billing_company',
			'Billing Address 1'  => 'billing_address_1',
			'Billing Address 2'  => 'billing_address_2',
			'Billing City'       => 'billing_city',
			'Billing State'      => 'billing_state',
			'Billing Postcode'   => 'billing_postcode',
			'Billing Country'    => 'billing_country',
			'Billing Email'      => 'billing_email',
			'Billing Phone'      => 'billing_phone',
		];
		$shipping_address_key_mapping = [
			'Shipping First Name' => 'shipping_first_name',
			'Shipping Last Name'  => 'shipping_last_name',
			'Shipping Company'    => 'shipping_company',
			'Shipping Address 1'  => 'shipping_address_1',
			'Shipping Address 2'  => 'shipping_address_2',
			'Shipping City'       => 'shipping_city',
			'Shipping State'      => 'shipping_state',
			'Shipping Postcode'   => 'shipping_postcode',
			'Shipping Country'    => 'shipping_country',
			'Shipping Email'      => 'shipping_email',
			'Shipping Phone'      => 'shipping_phone',
		];
		$coupon_key_mapping           = [
			'Coupon Code'         => 'code',
			'Coupon Amount'       => 'discount',
			'Coupon Amount (Tax)' => 'discount_tax',
		];
		$tax_key_mapping              = [
			'Tax Name'            => 'name',
			'Tax Label'           => 'label',
			'Tax Amount'          => 'tax_total',
			'Tax Rate(%)'         => 'rate_percent',
			'Shipping Tax Amount' => 'shipping_tax_total',
		];
		$shipping_key_mapping         = [
			'Shipping Method'       => 'name',
			'Shipping Method Title' => 'method_title',
			'Amount'                => 'total',
			'Amount (Tax)'          => 'total_tax',
			'Tax Status'            => 'tax_status',
		];
		$fee_key_mapping              = [
			'Fee Name'         => 'name',
			'Fee Amount'       => 'amount',
			'Fee Amount (Tax)' => 'total_tax',
			'Tax Status'       => 'tax_status',
		];

		$subscriptions          = [];
		$current_section_header = 'subscription';
		$header_row             = [];
		$recent_subscription    = null;
		while ( ( $row = fgetcsv( $fp ) ) !== false ) {
			if ( empty( $row ) || ( 1 === count( array_unique( $row ) ) && empty( $row[0] ) ) ) {
				continue;
			}

			if ( ( in_array( 'Subscription Key', $row, true ) && in_array( 'Subscription Status', $row, true ) ) || in_array( '# Subscription Entity', $row, true ) ) {
				$current_section_header = 'subscription';
				$header_row             = array_keys( $subscription_key_mapping );
				continue;
			} elseif ( in_array( 'Item Name', $row, true ) ) {
				$current_section_header = 'order_item';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 24 ) );
				continue;
			} elseif ( in_array( 'Billing First Name', $row, true ) && in_array( 'Billing Last Name', $row, true ) ) {
				$current_section_header = 'billing_address';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 11 ) );
				continue;
			} elseif ( in_array( 'Shipping First Name', $row, true ) && in_array( 'Shipping Last Name', $row, true ) ) {
				$current_section_header = 'shipping_address';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 11 ) );
				continue;
			} elseif ( in_array( 'Coupon Code', $row, true ) && in_array( 'Coupon Amount', $row, true ) ) {
				$current_section_header = 'coupon';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 3 ) );
				continue;
			} elseif ( in_array( 'Tax Name', $row, true ) && in_array( 'Tax Label', $row, true ) ) {
				$current_section_header = 'tax';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 5 ) );
				continue;
			} elseif ( in_array( 'Shipping Method', $row, true ) && in_array( 'Shipping Method Title', $row, true ) ) {
				$current_section_header = 'shipping';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 5 ) );
				continue;
			} elseif ( in_array( 'Fee Name', $row, true ) && in_array( 'Fee Amount', $row, true ) ) {
				$current_section_header = 'fee';
				$header_row             = array_map( 'sanitize_text_field', array_slice( $row, 0, 4 ) );
				continue;
			}

			if ( 'subscription' === $current_section_header ) {
				$recent_subscription = $row[ array_search( 'Subscription Key', $header_row, true ) ];
				$subscription_data   = array_slice( $row, 0, count( $subscription_key_mapping ) );
				$subscription_data   = array_map( function ( $value, $key ) {
					return 2 === $key ? sanitize_email( $value ) : sanitize_text_field( $value );
				}, $subscription_data, array_keys( $subscription_data ) );

				$subscriptions[ $recent_subscription ] = Helper::rename_array_keys(
					array_combine( $header_row, $subscription_data ),
					$subscription_key_mapping
				);
			} elseif ( 'order_item' === $current_section_header ) {
				$subscriptions[ $recent_subscription ]['order_items'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $order_item_key_mapping ) ) )
					),
					$order_item_key_mapping
				);
			} elseif ( 'billing_address' === $current_section_header ) {
				$billing_data                                               = array_slice( $row, 0, count( $billing_address_key_mapping ) );
				$billing_data                                               = array_map( function ( $value, $key ) {
					return 10 === $key ? sanitize_email( $value ) : sanitize_text_field( $value );
				}, $billing_data, array_keys( $billing_data ) );
				$subscriptions[ $recent_subscription ]['billing_address'][] = Helper::rename_array_keys(
					array_combine( $header_row, $billing_data ),
					$billing_address_key_mapping
				);
			} elseif ( 'shipping_address' === $current_section_header ) {
				$shipping_data                                               = array_slice( $row, 0, count( $shipping_address_key_mapping ) );
				$shipping_data                                               = array_map( function ( $value, $key ) {
					return 10 === $key ? sanitize_email( $value ) : sanitize_text_field( $value );
				}, $shipping_data, array_keys( $shipping_data ) );
				$subscriptions[ $recent_subscription ]['shipping_address'][] = Helper::rename_array_keys(
					array_combine( $header_row, $shipping_data ),
					$shipping_address_key_mapping
				);
			} elseif ( 'coupon' === $current_section_header ) {
				$subscriptions[ $recent_subscription ]['coupons'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $coupon_key_mapping ) ) )
					),
					$coupon_key_mapping
				);
			} elseif ( 'tax' === $current_section_header ) {
				$subscriptions[ $recent_subscription ]['taxes'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $tax_key_mapping ) ) )
					),
					$tax_key_mapping
				);
			} elseif ( 'shipping' === $current_section_header ) {
				$subscriptions[ $recent_subscription ]['shipping'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $shipping_key_mapping ) ) )
					),
					$shipping_key_mapping
				);
			} elseif ( 'fee' === $current_section_header ) {
				$subscriptions[ $recent_subscription ]['fees'][] = Helper::rename_array_keys(
					array_combine(
						$header_row,
						array_map( 'sanitize_text_field', array_slice( $row, 0, count( $fee_key_mapping ) ) )
					),
					$fee_key_mapping
				);
			}
		}
		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		wp_delete_file( $filepath );

		$expire                = 60 * 60 * 4;
		$chunked_subscriptions = array_chunk( $subscriptions, 30, true );
		$filename              = $this->get_filename_without_extension( $filename );
		set_transient( 'storeengine_csv_subscriptions_' . $filename . '_count', count( $subscriptions ), $expire );
		foreach ( $chunked_subscriptions as $index => $chunk ) {
			set_transient( 'storeengine_csv_subscriptions_' . $filename . '_' . $index, $chunk, $expire );
		}

		self::$sse->emitEvent( [
			'type'  => 'chunk-completed',
			'total' => count( $chunked_subscriptions ),
		], true );
	}

	private function get_label_value( string $slug ): ?array {
		$data = explode( '::', $slug );
		if ( 2 === count( $data ) ) {
			$taxonomy = $data[0];
			$term     = $data[1];
			$term_id  = term_exists( $term, $taxonomy );
			if ( ! $term_id ) {
				return null;
			}

			return [
				'label' => "$term - $taxonomy",
				'value' => "tax-$term_id--single-$taxonomy",
			];
		}

		$posts = get_posts( [
			'name'        => $slug,
			'post_type'   => 'any',
			'numberposts' => 1,
		] );
		$post  = empty( $posts ) ? null : $posts[0];
		if ( ! $post ) {
			return null;
		}

		return [
			'label' => "$post->post_title - $post->post_type",
			'value' => "post-$post->ID-|",
		];
	}

	public function download_sample_file( array $payload ) {
		$valid = \StoreEngine\Addons\Csv\Utils\Import::validate_data_type( $payload['type'] );

		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( $valid->get_error_message() );
		}

		$filename = 'sample_' . $payload['type'] . '.csv';

		$filepath = STOREENGINE_ROOT_DIR_PATH . 'sample-data/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			wp_send_json_error( __( 'Sample file not found!', 'storeengine' ) );
		}

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		readfile( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
