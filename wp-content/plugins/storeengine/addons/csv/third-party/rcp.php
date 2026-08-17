<?php

namespace StoreEngine\Addons\Csv\ThirdParty;

use RCP\Membership_Level;
use StoreEngine\Addons\Csv\Ajax\Export;
use StoreEngine\Addons\Paypal\GatewayPaypal;
use StoreEngine\Addons\Stripe\GatewayStripe;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\EventStreamServer;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;

class Rcp extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_csv';

	protected static EventStreamServer $sse;

	public function __construct() {
		$this->actions = [
			'rcp_export' => [
				'callback'             => [ $this, 'rcp_export' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'type' => 'string',
				],
			],
		];
	}

	/**
	 * @param array $payload
	 *
	 * @return void
	 */
	public function rcp_export( array $payload ) {
		if ( ! isset( $payload['type'] ) ) {
			wp_send_json_error( __( 'Type is required!', 'storeengine' ) );
		}

		$type      = $payload['type'];
		self::$sse = new EventStreamServer();
		self::$sse->listen( function () use ( $type ) {
			/**
			 * @see export_membership_levels()
			 * @see export_payments()
			 * @see export_memberships()
			 * @see export_customers()
			 */
			$this->{"export_$type"}();
		} );
	}

	private function export_membership_levels() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$membership_levels_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}restrict_content_pro WHERE status = 'active';" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		$membership_levels_pages = ceil( $membership_levels_count / 20 );
		$upload_dir              = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time            = time();
		$filename                = "rcp_membership_levels_{$membership_levels_count}_$current_time.csv";
		$filepath                = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = fopen( $filepath, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		Export::run_clean_hook( $filepath );

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

		$rcp_settings = get_option( 'rcp_settings' );

		$index = 0;
		foreach ( range( 1, $membership_levels_pages ) as $page ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$membership_levels = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}restrict_content_pro WHERE status = 'active' LIMIT %d OFFSET %d",
				20,
				max( ( $page - 1 ) * 20, 0 )
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

			foreach ( $membership_levels as $membership_level ) {
				if ( $index > 0 ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [ '# Access Group Entity' ] );
				}
				$index ++;

				$post_args       = [
					'post_status'    => 'publish',
					'posts_per_page' => - 1,
					'post_type'      => 'any',
					'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						[
							'key'   => '_is_paid',
							'value' => 1,
						],
						[
							'relation' => 'OR',
							[
								'key'     => 'rcp_subscription_level',
								'compare' => 'NOT EXISTS',
							],
							[
								'key'   => 'rcp_subscription_level',
								'value' => 'any',
							],
							[
								'key'   => 'rcp_subscription_level',
								'value' => 'any-paid',
							],
							[
								'key'     => 'rcp_subscription_level',
								'compare' => 'LIKE',
								'value'   => $membership_level->id,
							],
						],
						[
							'relation' => 'OR',
							[
								'key'     => 'rcp_access_level',
								'compare' => 'NOT EXISTS',
							],
							[
								'key'   => 'rcp_access_level',
								'value' => $membership_level->level,
							],
						],
						[
							'relation' => 'OR',
							[
								'key'     => 'rcp_user_level',
								'compare' => 'LIKE',
								'value'   => 'all',
							],
							[
								'key'     => 'rcp_user_level',
								'compare' => 'LIKE',
								'value'   => $membership_level->role,
							],
						],
					],
				];
				$protected_posts = get_posts( $post_args );
				$protected_posts = array_map( fn( $post ) => $post->post_name, $protected_posts );

				$course_args       = [
					'post_status'    => 'publish',
					'posts_per_page' => - 1,
					'post_type'      => 'academy_courses',
					'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						[
							'key'   => 'academy_course_type',
							'value' => 'rcp_membership',
						],
						[
							'key'     => 'academy_rcp_membership_levels',
							'compare' => 'LIKE',
							'value'   => $membership_level->id,
						],
					],
				];
				$protected_courses = get_posts( $course_args );
				$protected_courses = array_map( fn( $post ) => $post->post_name, $protected_courses );
				$protected_posts   = array_merge( $protected_posts, $protected_courses );

				fputcsv( $fp, [
					esc_html( $membership_level->name ),
					esc_html( $rcp_settings['restriction_message'] ?? '' ),
					'Specific Posts/ Pages/ Taxonomies',
					esc_html( implode( ',', $protected_posts ) ),
					'',
					'',
					false,
					'',
					false,
					null,
					null,
					null,
					esc_html( $membership_level->role ),
					'',
				] );

				if ( $membership_level->price ) {
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

					$trial_days = null;
					if ( $membership_level->trial_duration_unit ) {
						if ( 'month' === $membership_level->trial_duration_unit ) {
							$trial_days = $membership_level->trial_duration * 30;
						} elseif ( 'year' === $membership_level->trial_duration_unit ) {
							$trial_days = $membership_level->trial_duration * 365;
						} else {
							$trial_days = $membership_level->trial_duration;
						}
					}

					$duration = absint( $membership_level->duration );
					fputcsv( $fp, [
						esc_html( $membership_level->name ),
						sanitize_title( $membership_level->name ),
						0 === $duration ? 'Onetime' : 'Subscription',
						0 === $duration ? 'onetime' : 'subscription',
						esc_html( $membership_level->price ),
						null,
						! empty( $membership_level->fee ) && 0 !== (int) $membership_level->fee,
						'Signup Fee',
						esc_html( $membership_level->fee ),
						'fee',
						! empty( $membership_level->trial_duration ) && 0 !== (int) $membership_level->trial_duration,
						esc_html( $trial_days ),
						false,
						null,
						esc_html( $duration ),
						esc_html( $membership_level->duration_unit ),
						false,
						'taxable',
					] );
				}

				self::$sse->emitEvent( [
					'type'       => 'progress',
					'percentage' => round( ( $index / $membership_levels_count ) * 100 ),
				] );
			}
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		self::$sse->emitEvent( [
			'type'    => 'filename',
			'message' => $filename,
		] );

		self::$sse->emitEvent( [
			'type'    => 'complete',
			'message' => esc_html__( 'RCP membership levels export file has been ready.', 'storeengine' ),
		], true );
	}

	private function export_payments() {
		global $wpdb;
		$payment_table      = rcp_get_payments_db_name();
		$payment_meta_table = rcp_get_payment_meta_db_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$payments_count = $wpdb->get_var( "SELECT COUNT(*) FROM $payment_table" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$payments_pages = ceil( $payments_count / 20 );
		$upload_dir     = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time   = time();
		$filename       = "rcp_payments_{$payments_count}_$current_time.csv";
		$filepath       = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = fopen( $filepath, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		Export::run_clean_hook( $filepath );

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
		$order_status_mapping = [
			'pending'  => 'pending_payment',
			'complete' => 'completed',
			'failed'   => 'payment_failed',
			'refunded' => 'refunded',
		];
		$gateway_mapping      = [
			'free'   => 'Free',
			'stripe' => ( new GatewayStripe() )->get_title(),
			'paypal' => ( new GatewayPaypal() )->get_title(),
		];
		$order_meta_mapping   = [
			'stripe_payment_intent_id' => '_stripe_intent_id',
		];

		$index = 0;
		foreach ( range( 1, $payments_pages ) as $page ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$payments = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $payment_table LIMIT %d OFFSET %d",
				20, max( ( $page - 1 ) * 20, 0 )
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $payments as $payment ) {
				$membership_level = rcp_get_membership_level( $payment->object_id );
				if ( ! $membership_level ) {
					continue;
				}

				if ( $index > 0 ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [ '# Order Entity' ] );
				}
				$index ++;

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_data = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM $payment_meta_table WHERE rcp_payment_id = %d",
					$payment->id
				) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				$order_meta_data = [];
				if ( ! is_array( $meta_data ) ) {
					$meta_data = [];
				}
				foreach ( $meta_data as $meta ) {
					$order_meta_data[] = ( $order_meta_mapping[ $meta->meta_key ] ?? $meta->meta_key ) . ':' . $meta->meta_value;
				}
				$user = get_userdata( $payment->user_id );
				fputcsv( $fp, [
					esc_html( $this->get_order_key( $payment->id, $payment->subscription_key ) ),
					esc_html( $order_status_mapping[ $payment->status ] ),
					esc_html( $user->user_email ),
					esc_html( rcp_get_currency() ),
					esc_html( $payment->subtotal ),
					esc_html( $payment->amount ),
					0,
					esc_html( $payment->date ),
					null,
					esc_html( $payment->date ),
					esc_html( $payment->date ),
					esc_html( $payment->gateway ),
					esc_html( $gateway_mapping[ $payment->gateway ] ),
					esc_html( $payment->transaction_id ),
					null,
					null,
					null,
					null,
					esc_html( implode( '|', $order_meta_data ) ),
				] );

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

				$trial_days = $this->get_days( $membership_level );

				fputcsv( $fp, [
					esc_html( $membership_level->get_name() ),
					sanitize_title( $membership_level->get_name() ),
					'simple',
					esc_html( $membership_level->get_price() ),
					1,
					esc_html( $membership_level->get_price() ),
					0.00,
					esc_html( $payment->amount ),
					0.00,
					'digital',
					true,
					0 === $membership_level->get_duration() ? 'Onetime' : 'Subscription',
					0 === $membership_level->get_duration() ? 'onetime' : 'subscription',
					! empty( $membership_level->get_fee() ) && 0 !== (int) $membership_level->get_fee(),
					'Signup Fee',
					esc_html( $membership_level->get_fee() ),
					'fee',
					! empty( $membership_level->get_trial_duration() ) && 0 !== $membership_level->get_trial_duration(),
					esc_html( $trial_days ),
					false,
					null,
					esc_html( $membership_level->get_duration() ),
					esc_html( $membership_level->get_duration_unit() ),
					null,
				] );

				self::$sse->emitEvent( [
					'type'       => 'progress',
					'percentage' => round( ( $index / $payments_count ) * 100 ),
				] );
			}
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		self::$sse->emitEvent( [
			'type'    => 'filename',
			'message' => $filename,
		] );

		self::$sse->emitEvent( [
			'type'    => 'completed',
			'message' => __( 'RCP payments export file has been ready.', 'storeengine' ),
		], true );
	}

	private function export_memberships() {
		global $wpdb;
		$payment_table    = rcp_get_payments_db_name();
		$membership_table = rcp_get_memberships_db_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$memberships_count = $wpdb->get_var( "SELECT COUNT(*) FROM $membership_table WHERE expiration_date IS NOT NULL" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$memberships_pages = ceil( $memberships_count / 20 );
		$upload_dir        = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time      = time();
		$filename          = "rcp_memberships_{$memberships_count}_{$current_time}.csv";
		$filepath          = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = fopen( $filepath, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		Export::run_clean_hook( $filepath );

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

		$gateway_mapping = [
			'free'   => 'Free',
			'stripe' => ( new GatewayStripe() )->get_title(),
			'paypal' => ( new GatewayPaypal() )->get_title(),
		];

		$index = 0;
		foreach ( range( 1, $memberships_pages ) as $page ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$memberships = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $membership_table WHERE expiration_date IS NOT NULL LIMIT %d OFFSET %d", 20,
				max( ( $page - 1 ) * 20, 0 )
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			foreach ( $memberships as $membership ) {
				$user = get_userdata( $membership->user_id );
				if ( ! $user ) {
					continue;
				}

				if ( $membership->object_id ) {
					$membership_level = rcp_get_membership_level( $membership->object_id );
					if ( ! $membership_level ) {
						continue;
					}
				} else {
					continue;
				}

				if ( $index > 0 ) {
					fputcsv( $fp, [] );
					fputcsv( $fp, [ '# Subscription Entity' ] );
				}
				$index ++;

				$parent_order_key = null;

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$parent_order = $wpdb->get_row( $wpdb->prepare( "SELECT id, subscription_key FROM $payment_table WHERE membership_id = %d AND transaction_type='new' ORDER BY id DESC", $membership->id ) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				if ( $parent_order ) {
					$parent_order_key = $this->get_order_key( $parent_order->id, $parent_order->subscription_key );
				}

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$payments = $wpdb->get_results( $wpdb->prepare( "SELECT id, subscription_key FROM $payment_table WHERE membership_id = %d AND transaction_type='renewal'", $membership->id ) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				$child_order_keys = null;
				if ( is_array( $payments ) && ! empty( $payments ) ) {
					$child_order_keys = implode( ',', array_map( fn( $payment ) => $this->get_order_key( $payment->id, $payment->subscription_key ), $payments ) );
				}

				fputcsv( $fp, [
					esc_html( Order::generate_order_key() ),
					esc_html( $membership->status ),
					esc_html( $user->user_email ),
					esc_html( $membership->currency ),
					esc_html( $membership->recurring_amount ),
					esc_html( $membership->recurring_amount ),
					0.00,
					esc_html( ! empty( $membership->created_date ) ? gmdate( 'Y-m-d h:i:s A', strtotime( $membership->created_date ) ) : '' ),
					null,
					esc_html( ! empty( $membership->created_date ) ? gmdate( 'Y-m-d h:i:s A', strtotime( $membership->created_date ) ) : '' ),
					esc_html( $membership->gateway ),
					esc_html( $gateway_mapping[ $membership->gateway ] ?? $membership->gateway ),
					null,
					null,
					esc_html( $membership->notes ),
					null,
					esc_html( $parent_order_key ),
					esc_html( $child_order_keys ),
					esc_html( ! empty( $membership->activated_date ) ? gmdate( 'Y-m-d h:i:s A', strtotime( $membership->activated_date ) ) : '' ),
					! empty( $membership->trial_end_date ) && strtotime( $membership->trial_end_date ) > time(),
					null,
					esc_html( ! empty( $membership->trial_end_date ) ? gmdate( 'Y-m-d h:i:s A', strtotime( $membership->trial_end_date ) ) : '' ),
					esc_html( ! empty( $membership->expiration_date ) ? gmdate( 'Y-m-d h:i:s A', strtotime( $membership->expiration_date ) ) : '' ),
					null,
				] );

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

				fputcsv( $fp, [
					esc_html( $membership_level->get_name() ),
					sanitize_title( $membership_level->get_name() ),
					'simple',
					esc_html( $membership_level->get_price() ),
					1,
					esc_html( $membership_level->get_price() ),
					0.00,
					esc_html( $membership_level->get_price() ),
					0.00,
					'digital',
					true,
					0 === $membership_level->get_duration() ? 'Onetime' : 'Subscription',
					0 === $membership_level->get_duration() ? 'onetime' : 'subscription',
					! empty( $membership_level->get_fee() ) && 0 !== (int) $membership_level->get_fee(),
					'Signup Fee',
					esc_html( $membership_level->get_fee() ),
					'fee',
					esc_html( $membership_level->has_trial() ),
					esc_html( $this->get_days( $membership_level ) ),
					false,
					null,
					esc_html( $membership_level->get_duration() ),
					esc_html( $membership_level->get_duration_unit() ),
					null,
				] );
				self::$sse->emitEvent( [
					'type'       => 'progress',
					'percentage' => round( ( $index / $memberships_count ) * 100 ),
				] );
			}
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

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
		$customer_table = rcp_get_customers_db_name();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$customers_count = $wpdb->get_var( "SELECT COUNT(*) FROM $customer_table" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$customer_pages = ceil( $customers_count / 20 );
		$upload_dir     = trailingslashit( Helper::get_upload_dir() . '/csv/' );
		$current_time   = time();
		$filename       = "rcp_customers_{$customers_count}_$current_time.csv";
		$filepath       = $upload_dir . $filename;

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$fp = fopen( $filepath, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fp ) {
			self::$sse->emitEvent( [
				'type'    => 'error',
				'message' => __( 'Unable to open file for writing.', 'storeengine' ),
			], true );
		}
		Export::run_clean_hook( $filepath );

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
			/** @var \RCP_Customer[] $customers */
			$customers = rcp_get_customers( [
				'offset' => max( ( $page - 1 ) * 20, 0 ),
			] );
			foreach ( $customers as $customer ) {
				$user = get_userdata( $customer->get_user_id() );
				fputcsv( $fp, [
					esc_html( $user->user_login ),
					esc_html( $user->first_name ),
					esc_html( $user->last_name ),
					esc_html( $user->user_nicename ),
					esc_html( $user->display_name ),
					esc_html( $user->user_email ),
					esc_html( $user->user_url ),
					esc_html( $user->user_description ),
					esc_html( ! empty( $user->user_registered ) ? gmdate( 'Y-m-d h:i:s A', strtotime( $user->user_registered ) ) : '' ),
					esc_html( implode( ',', $user->roles ) ),
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					null,
					'no',
				] );

				self::$sse->emitEvent( [
					'type'       => 'progress',
					'percentage' => round( ( $index / $customers_count ) * 100 ),
				] );
				$index ++;
			}
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		self::$sse->emitEvent( [
			'type'    => 'filename',
			'message' => $filename,
		] );


		self::$sse->emitEvent( [
			'type'    => 'complete',
			'message' => esc_html__( 'Customer export file has been ready.', 'storeengine' ),
		], true );
	}

	/**
	 * @param Membership_Level $membership_level
	 *
	 * @return float|int|null
	 */
	private function get_days( Membership_Level $membership_level ) {
		$trial_days = null;
		if ( $membership_level->get_trial_duration_unit() ) {
			if ( 'month' === $membership_level->get_trial_duration_unit() ) {
				$trial_days = $membership_level->get_trial_duration() * 30;
			} elseif ( 'year' === $membership_level->get_trial_duration_unit() ) {
				$trial_days = $membership_level->get_trial_duration() * 365;
			} else {
				$trial_days = $membership_level->get_trial_duration();
			}
		}

		return $trial_days;
	}

	private function get_order_key( int $payment_id, string $subscription_key ) {
		$input = "rcp_payment_{$payment_id}_{$subscription_key}";

		return 'se_order_' . substr( wp_hash( $input ), 0, 13 );
	}
}
