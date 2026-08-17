<?php
/**
 * GDPR personal-data exporters.
 *
 * Each callback receives ( string $email_address, int $page ) and returns
 * `[ 'data' => array, 'done' => bool ]` per the WordPress Privacy API. All
 * translated labels are built here (inside the callback), not at registration
 * time, to avoid the WP 6.7+ early-translation notice.
 */

namespace StoreEngine\Addons\Gdpr;

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper as CoreHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Exporters {

	/**
	 * Orders placed by the email (registered + guest), one export item each.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function orders( string $email, int $page = 1 ): array {
		$ids  = Helper::order_ids_for_email( $email, $page );
		$data = [];

		foreach ( $ids as $order_id ) {
			$order = Helper::get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$data[] = [
				'group_id'          => 'storeengine_orders',
				'group_label'       => __( 'Orders', 'storeengine' ),
				'group_description' => __( 'Order data stored by StoreEngine.', 'storeengine' ),
				'item_id'           => 'order-' . $order_id,
				'data'              => self::order_rows( $order ),
			];
		}

		return [
			'data' => $data,
			'done' => Helper::is_done( count( $ids ) ),
		];
	}

	/**
	 * Customer-lookup record, saved usermeta addresses and consent preferences.
	 * Only meaningful for a registered account; guests have none.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function customer_data( string $email, int $page = 1 ): array {
		// All of a customer's profile data fits in a single (page 1) item.
		if ( $page > 1 ) {
			return [ 'data' => [], 'done' => true ];
		}

		global $wpdb;
		$data    = [];
		$user_id = Helper::user_id_for_email( $email );

		// Customer-lookup row (matched by email or user id).
		$table = $wpdb->prefix . 'storeengine_customer_lookup';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%i/%s/%d) query on a custom StoreEngine table; GDPR export, not cacheable.
		$lookup = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT first_name, last_name, email, phone, country, postcode, city, state FROM %i WHERE email = %s OR ( user_id IS NOT NULL AND user_id = %d ) LIMIT 1",
				$table,
				$email,
				$user_id
			)
		);

		if ( $lookup ) {
			$data[] = [
				'group_id'          => 'storeengine_customer',
				'group_label'       => __( 'Customer Data', 'storeengine' ),
				'group_description' => __( 'Customer profile data stored by StoreEngine.', 'storeengine' ),
				'item_id'           => 'customer-' . $email,
				'data'              => array_values( array_filter( [
					self::row( __( 'First Name', 'storeengine' ), $lookup->first_name ),
					self::row( __( 'Last Name', 'storeengine' ), $lookup->last_name ),
					self::row( __( 'Email', 'storeengine' ), $lookup->email ),
					self::row( __( 'Phone', 'storeengine' ), $lookup->phone ),
					self::row( __( 'City', 'storeengine' ), $lookup->city ),
					self::row( __( 'State', 'storeengine' ), $lookup->state ),
					self::row( __( 'Postcode', 'storeengine' ), $lookup->postcode ),
					self::row( __( 'Country', 'storeengine' ), $lookup->country ),
				] ) ),
			];
		}

		if ( $user_id ) {
			$prefix = CoreHelper::DB_PREFIX;

			// Saved billing / shipping addresses (usermeta).
			foreach ( [ 'billing' => __( 'Saved Billing Address', 'storeengine' ), 'shipping' => __( 'Saved Shipping Address', 'storeengine' ) ] as $type => $label ) {
				$address = get_user_meta( $user_id, $prefix . $type . '_address', true );
				$address = maybe_unserialize( $address );
				if ( is_string( $address ) ) {
					$decoded = json_decode( $address, true );
					$address = is_array( $decoded ) ? $decoded : [];
				}
				if ( ! empty( $address ) && is_array( $address ) ) {
					$rows = [];
					foreach ( $address as $key => $value ) {
						if ( '' !== $value && null !== $value ) {
							$rows[] = self::row( ucwords( str_replace( '_', ' ', (string) $key ) ), $value );
						}
					}
					if ( $rows ) {
						$data[] = [
							'group_id'    => 'storeengine_saved_addresses',
							'group_label' => __( 'Saved Addresses', 'storeengine' ),
							'item_id'     => 'address-' . $type . '-' . $user_id,
							'data'        => $rows,
						];
					}
				}
			}

			// Consent / privacy preferences.
			$consent_rows = array_values( array_filter( [
				self::bool_row( __( 'Allow data sharing', 'storeengine' ), get_user_meta( $user_id, 'storeengine_privacy_data_sharing', true ) ),
				self::bool_row( __( 'Allow profiling', 'storeengine' ), get_user_meta( $user_id, 'storeengine_privacy_profiling', true ) ),
			] ) );
			if ( $consent_rows ) {
				$data[] = [
					'group_id'    => 'storeengine_consent',
					'group_label' => __( 'Consent Preferences', 'storeengine' ),
					'item_id'     => 'consent-' . $user_id,
					'data'        => $consent_rows,
				];
			}
		}

		return [ 'data' => $data, 'done' => true ];
	}

	/**
	 * Download log (which files were downloaded, when, from which IP).
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function downloads( string $email, int $page = 1 ): array {
		$user_id = Helper::user_id_for_email( $email );
		if ( ! $user_id ) {
			return [ 'data' => [], 'done' => true ];
		}

		global $wpdb;
		$per_page = Helper::PER_PAGE;
		$offset   = max( 0, ( $page - 1 ) * $per_page );
		$table    = $wpdb->prefix . 'storeengine_download_log';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%i/%d) query on a custom StoreEngine table; GDPR export, not cacheable.
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, permission_id, user_ip_address, `timestamp` FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
				$table,
				$user_id,
				$per_page,
				$offset
			)
		);

		$data = [];
		foreach ( (array) $logs as $log ) {
			$data[] = [
				'group_id'    => 'storeengine_downloads',
				'group_label' => __( 'Download History', 'storeengine' ),
				'item_id'     => 'download-' . $log->id,
				'data'        => array_values( array_filter( [
					self::row( __( 'Permission ID', 'storeengine' ), $log->permission_id ),
					self::row( __( 'IP Address', 'storeengine' ), $log->user_ip_address ),
					self::row( __( 'Downloaded At', 'storeengine' ), $log->timestamp ),
				] ) ),
			];
		}

		return [ 'data' => $data, 'done' => Helper::is_done( count( (array) $logs ) ) ];
	}

	/**
	 * Saved payment methods. The raw token/secret is never exported — only the
	 * gateway and type, so the customer can see what is stored without exposing
	 * reusable credentials.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function payment_tokens( string $email, int $page = 1 ): array {
		$user_id = Helper::user_id_for_email( $email );
		if ( ! $user_id || $page > 1 ) {
			return [ 'data' => [], 'done' => true ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'storeengine_payment_tokens';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%i/%d) query on a custom StoreEngine table; GDPR export, not cacheable.
		$tokens = $wpdb->get_results(
			$wpdb->prepare( "SELECT token_id, gateway_id, type, is_default FROM %i WHERE user_id = %d ORDER BY token_id DESC", $table, $user_id )
		);

		$data = [];
		foreach ( (array) $tokens as $token ) {
			$data[] = [
				'group_id'    => 'storeengine_payment_tokens',
				'group_label' => __( 'Saved Payment Methods', 'storeengine' ),
				'item_id'     => 'token-' . $token->token_id,
				'data'        => array_values( array_filter( [
					self::row( __( 'Gateway', 'storeengine' ), $token->gateway_id ),
					self::row( __( 'Type', 'storeengine' ), $token->type ),
					self::bool_row( __( 'Default method', 'storeengine' ), $token->is_default ),
				] ) ),
			];
		}

		return [ 'data' => $data, 'done' => true ];
	}

	/**
	 * REST API keys. The consumer secret is never exported.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function api_keys( string $email, int $page = 1 ): array {
		$user_id = Helper::user_id_for_email( $email );
		if ( ! $user_id || $page > 1 ) {
			return [ 'data' => [], 'done' => true ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'storeengine_api_keys';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%i/%d) query on a custom StoreEngine table; GDPR export, not cacheable.
		$keys = $wpdb->get_results(
			$wpdb->prepare( "SELECT key_id, description, permissions, truncated_key, last_access FROM %i WHERE user_id = %d ORDER BY key_id DESC", $table, $user_id )
		);

		$data = [];
		foreach ( (array) $keys as $key ) {
			$data[] = [
				'group_id'    => 'storeengine_api_keys',
				'group_label' => __( 'API Keys', 'storeengine' ),
				'item_id'     => 'api-key-' . $key->key_id,
				'data'        => array_values( array_filter( [
					self::row( __( 'Description', 'storeengine' ), $key->description ),
					self::row( __( 'Permissions', 'storeengine' ), $key->permissions ),
					self::row( __( 'Key', 'storeengine' ), $key->truncated_key ? '…' . $key->truncated_key : '' ),
					self::row( __( 'Last Access', 'storeengine' ), $key->last_access ),
				] ) ),
			];
		}

		return [ 'data' => $data, 'done' => true ];
	}

	/* ---------------------------------------------------------------------
	 * Internal
	 * ------------------------------------------------------------------- */

	/**
	 * Flatten an order into export rows using its public getters.
	 *
	 * @param Order $order
	 * @return array
	 */
	private static function order_rows( Order $order ): array {
		$rows = [
			self::row( __( 'Order Number', 'storeengine' ), $order->get_id() ),
			self::row( __( 'Order Email', 'storeengine' ), $order->get_order_email() ?: $order->get_billing_email() ),
			self::row( __( 'Billing Name', 'storeengine' ), trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ),
			self::row( __( 'Billing Company', 'storeengine' ), $order->get_billing_company() ),
			self::row( __( 'Billing Address', 'storeengine' ), self::format_address( [
				$order->get_billing_address_1(),
				$order->get_billing_address_2(),
				$order->get_billing_city(),
				$order->get_billing_state(),
				$order->get_billing_postcode(),
				$order->get_billing_country(),
			] ) ),
			self::row( __( 'Billing Phone', 'storeengine' ), $order->get_billing_phone() ),
			self::row( __( 'Shipping Name', 'storeengine' ), trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) ),
			self::row( __( 'Shipping Address', 'storeengine' ), self::format_address( [
				$order->get_shipping_address_1(),
				$order->get_shipping_address_2(),
				$order->get_shipping_city(),
				$order->get_shipping_state(),
				$order->get_shipping_postcode(),
				$order->get_shipping_country(),
			] ) ),
			self::row( __( 'IP Address', 'storeengine' ), $order->get_ip_address() ),
			self::row( __( 'Customer Note', 'storeengine' ), $order->get_customer_note() ),
		];

		return array_values( array_filter( $rows ) );
	}

	/**
	 * Build a single `name => value` export row, or null when the value is empty.
	 *
	 * @param string $name
	 * @param mixed  $value
	 * @return array|null
	 */
	private static function row( string $name, $value ): ?array {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return null;
		}

		return [ 'name' => $name, 'value' => $value ];
	}

	/**
	 * Boolean preference row (always rendered, Yes/No).
	 *
	 * @param string $name
	 * @param mixed  $value
	 * @return array
	 */
	private static function bool_row( string $name, $value ): array {
		return [
			'name'  => $name,
			'value' => $value ? __( 'Yes', 'storeengine' ) : __( 'No', 'storeengine' ),
		];
	}

	/**
	 * Join non-empty address parts with commas.
	 *
	 * @param array $parts
	 * @return string
	 */
	private static function format_address( array $parts ): string {
		return implode( ', ', array_filter( array_map( 'trim', array_map( 'strval', $parts ) ) ) );
	}
}
