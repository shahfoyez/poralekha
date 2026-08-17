<?php
/**
 * GDPR personal-data erasers.
 *
 * Each callback receives ( string $email_address, int $page ) and returns
 * `[ 'items_removed' => bool, 'items_retained' => bool, 'messages' => string[], 'done' => bool ]`
 * per the WordPress Privacy API.
 *
 * Strategy: completed orders (and the customer-lookup/download rows tied to
 * accounting) are *anonymized in place* so financial totals stay accurate —
 * reported as retained. Pure-PII stores (saved addresses, consent prefs,
 * payment tokens, API keys) are *deleted* — reported as removed.
 *
 * Translated messages are built inside the callbacks (post-`init`) to avoid the
 * WP 6.7+ early-translation notice.
 */

namespace StoreEngine\Addons\Gdpr;

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper as CoreHelper;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Erasers {

	/**
	 * Anonymize orders placed by the email (registered + guest), in place.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function orders( string $email, int $page = 1 ): array {
		// Always page 1: each anonymized order no longer matches the email, so the
		// next batch naturally surfaces the following unprocessed orders.
		$ids      = Helper::order_ids_for_email( $email, 1 );
		$retained = false;
		$messages = [];

		foreach ( $ids as $order_id ) {
			$order = Helper::get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			self::anonymize_order( $order );
			$retained   = true;
			$messages[] = sprintf(
				/* translators: %s: order number. */
				__( 'Order %s has been anonymized.', 'storeengine' ),
				$order_id
			);
		}

		return [
			'items_removed'  => false,
			'items_retained' => $retained,
			'messages'       => $messages,
			// Done once a batch comes back empty (all matching orders anonymized).
			'done'           => empty( $ids ),
		];
	}

	/**
	 * Anonymize the customer-lookup row; delete saved addresses + consent prefs.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function customer_data( string $email, int $page = 1 ): array {
		if ( $page > 1 ) {
			return self::result( false, false, [], true );
		}

		global $wpdb;
		$removed  = false;
		$retained = false;
		$messages = [];
		$user_id  = Helper::user_id_for_email( $email );

		// Anonymize the customer-lookup row (kept for reporting integrity).
		$lookup_table = $wpdb->prefix . 'storeengine_customer_lookup';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from $wpdb->prefix + a literal; values are prepared (%s/%d); direct write on a custom StoreEngine table.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$lookup_table} SET first_name = '', last_name = '', email = %s, phone = '', country = '', postcode = '', city = '', state = '' WHERE email = %s OR ( user_id IS NOT NULL AND user_id = %d )", // phpcs:ignore
				self::anonymized_email_for( $email ),
				$email,
				$user_id
			)
		);
		if ( $updated ) {
			$retained   = true;
			$messages[] = __( 'StoreEngine customer record has been anonymized.', 'storeengine' );
		}

		if ( $user_id ) {
			$prefix = CoreHelper::DB_PREFIX;

			foreach ( [ 'billing', 'shipping' ] as $type ) {
				if ( delete_user_meta( $user_id, $prefix . $type . '_address' ) ) {
					$removed = true;
				}
			}

			$consent_deleted = false;
			foreach ( [ 'storeengine_privacy_data_sharing', 'storeengine_privacy_profiling' ] as $meta_key ) {
				if ( delete_user_meta( $user_id, $meta_key ) ) {
					$consent_deleted = true;
				}
			}

			if ( $removed ) {
				$messages[] = __( 'Saved addresses have been deleted.', 'storeengine' );
			}
			if ( $consent_deleted ) {
				$removed    = true;
				$messages[] = __( 'Consent preferences have been deleted.', 'storeengine' );
			}
		}

		return self::result( $removed, $retained, $messages, true );
	}

	/**
	 * Anonymize the IP address on the customer's download log rows.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function downloads( string $email, int $page = 1 ): array {
		if ( $page > 1 ) {
			return self::result( false, false, [], true );
		}

		$user_id = Helper::user_id_for_email( $email );
		if ( ! $user_id ) {
			return self::result( false, false, [], true );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'storeengine_download_log';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from $wpdb->prefix + a literal; value is prepared (%d); direct write on a custom StoreEngine table.
		$updated = $wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET user_ip_address = '0.0.0.0' WHERE user_id = %d AND user_ip_address <> '0.0.0.0'", $user_id ) // phpcs:ignore
		);

		$messages = $updated ? [ __( 'Download log IP addresses have been anonymized.', 'storeengine' ) ] : [];

		return self::result( false, (bool) $updated, $messages, true );
	}

	/**
	 * Delete the customer's saved payment tokens (and their meta).
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function payment_tokens( string $email, int $page = 1 ): array {
		if ( $page > 1 ) {
			return self::result( false, false, [], true );
		}

		$user_id = Helper::user_id_for_email( $email );
		if ( ! $user_id ) {
			return self::result( false, false, [], true );
		}

		global $wpdb;
		$tokens_table = $wpdb->prefix . 'storeengine_payment_tokens';
		$meta_table   = $wpdb->prefix . 'storeengine_payment_tokenmeta';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$token_ids = $wpdb->get_col( $wpdb->prepare( "SELECT token_id FROM {$tokens_table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore

		if ( empty( $token_ids ) ) {
			return self::result( false, false, [], true );
		}

		$token_ids   = array_map( 'absint', $token_ids );
		$placeholders = implode( ',', array_fill( 0, count( $token_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$meta_table} WHERE payment_token_id IN ({$placeholders})", $token_ids ) ); // phpcs:ignore
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tokens_table} WHERE token_id IN ({$placeholders})", $token_ids ) ); // phpcs:ignore

		return self::result( true, false, [ __( 'Saved payment methods have been deleted.', 'storeengine' ) ], true );
	}

	/**
	 * Delete the customer's REST API keys.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public static function api_keys( string $email, int $page = 1 ): array {
		if ( $page > 1 ) {
			return self::result( false, false, [], true );
		}

		$user_id = Helper::user_id_for_email( $email );
		if ( ! $user_id ) {
			return self::result( false, false, [], true );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'storeengine_api_keys';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore

		$messages = $deleted ? [ __( 'API keys have been deleted.', 'storeengine' ) ] : [];

		return self::result( (bool) $deleted, false, $messages, true );
	}

	/* ---------------------------------------------------------------------
	 * Internal
	 * ------------------------------------------------------------------- */

	/**
	 * Blank out every personal field on an order and persist it. Order rows are
	 * kept so totals/tax reporting stay intact.
	 *
	 * @param Order $order
	 */
	private static function anonymize_order( Order $order ) {
		$anon = self::anonymized_email_for( (string) $order->get_id() );

		$order->set_billing_first_name( '' );
		$order->set_billing_last_name( '' );
		$order->set_billing_company( '' );
		$order->set_billing_address_1( '' );
		$order->set_billing_address_2( '' );
		$order->set_billing_city( '' );
		$order->set_billing_state( '' );
		$order->set_billing_postcode( '' );
		$order->set_billing_country( '' );
		$order->set_billing_email( $anon );
		$order->set_billing_phone( '' );

		$order->set_shipping_first_name( '' );
		$order->set_shipping_last_name( '' );
		$order->set_shipping_company( '' );
		$order->set_shipping_address_1( '' );
		$order->set_shipping_address_2( '' );
		$order->set_shipping_city( '' );
		$order->set_shipping_state( '' );
		$order->set_shipping_postcode( '' );
		$order->set_shipping_country( '' );
		$order->set_shipping_phone( '' );

		$order->set_ip_address( '0.0.0.0' );
		$order->set_customer_note( '' );
		$order->set_order_email( $anon );
		$order->set_customer_id( 0 );

		/**
		 * Let other addons scrub their own order-attached personal data
		 * (e.g. invoice meta, eu-vat numbers) before the order is saved.
		 *
		 * @param Order $order
		 */
		do_action( 'storeengine/privacy/anonymize_order', $order );

		try {
			$order->save();
		} catch ( Throwable $e ) {
			unset( $e );
		}
	}

	/**
	 * Build the standard WP eraser return array.
	 */
	private static function result( bool $removed, bool $retained, array $messages, bool $done ): array {
		return [
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => $done,
		];
	}

	/**
	 * Deterministic placeholder email so anonymized rows stay unique.
	 *
	 * @param string $seed
	 * @return string
	 */
	private static function anonymized_email_for( string $seed ): string {
		return 'deleted-' . md5( $seed ) . '@site.invalid';
	}
}
