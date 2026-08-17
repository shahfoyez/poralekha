<?php

namespace StoreEngine\Addons\Couriers\Classes;

use StoreEngine\Addons\Couriers\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ShipmentsService {

	public static function all( array $filters = [] ): array {
		global $wpdb;
		$where  = [];
		$values = [];

		if ( ! empty( $filters['order_id'] ) ) {
			$where[]  = 'order_id = %d';
			$values[] = (int) $filters['order_id'];
		}
		if ( ! empty( $filters['vendor_id'] ) ) {
			$where[]  = 'vendor_id = %d';
			$values[] = (int) $filters['vendor_id'];
		}
		if ( ! empty( $filters['provider'] ) ) {
			$where[]  = 'provider = %s';
			$values[] = (string) $filters['provider'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = (string) $filters['status'];
		}

		$where_sql = $where ? ( ' WHERE ' . implode( ' AND ', $where ) ) : '';
		$limit     = min( 100, max( 1, (int) ( $filters['per_page'] ?? 50 ) ) );
		$page      = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$offset    = ( $page - 1 ) * $limit;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is a safe internal constant; values use %s/%d placeholders.
		$sql = "SELECT * FROM " . Database::shipments_table() . "{$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows = $values
			? $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( $values, [ $limit, $offset ] ) ) )
			: $wpdb->get_results( $wpdb->prepare( $sql, $limit, $offset ) );
		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	public static function get( int $id ): ?object {
		if ( ! $id ) return null;
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is a safe internal constant; value uses %d placeholder.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . Database::shipments_table() . " WHERE id = %d", $id ) );
		// phpcs:enable
		return $row ?: null;
	}

	public static function create_for_order( int $order_id, string $provider_id, array $payload ): array {
		$provider = Registry::get( $provider_id );
		if ( ! $provider ) {
			return [ 'ok' => false, 'errors' => [ 'unknown_provider' ] ];
		}

		$payload = array_merge( $payload, [ 'order_id' => $order_id ] );
		$result  = $provider->create_shipment( $payload );

		if ( empty( $result['ok'] ) ) {
			return $result;
		}

		global $wpdb;
		$now = current_time( 'mysql', 1 );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$vendor_id = isset( $payload['vendor_id'] ) && (int) $payload['vendor_id'] > 0
			? (int) $payload['vendor_id']
			: null;

		$wpdb->insert( Database::shipments_table(), [
			'order_id'        => $order_id,
			'vendor_id'       => $vendor_id,
			'provider'        => $provider_id,
			'tracking_id'     => $result['tracking_id'] ?? null,
			'consignment_id'  => $result['consignment_id'] ?? null,
			'status'          => 'created',
			'internal_status' => ShipmentStatus::CREATED,
			'label_url'       => $result['label_url'] ?? null,
			'tracking_url'   => $result['tracking_url'] ?? null,
			'cost'           => isset( $payload['cost'] ) ? (float) $payload['cost'] : null,
			'cod_amount'     => isset( $payload['cod_amount'] ) ? (float) $payload['cod_amount'] : null,
			'weight_kg'      => isset( $payload['weight_kg'] ) ? (float) $payload['weight_kg'] : null,
			'payload'        => wp_json_encode( $payload ),
			'response'       => isset( $result['raw'] ) ? wp_json_encode( $result['raw'] ) : null,
			'date_created'   => $now,
			'date_modified'  => $now,
		] );
		// phpcs:enable

		$id = (int) $wpdb->insert_id;

		do_action( 'storeengine/courier/shipment_created', $id, $order_id, $provider_id );

		return [ 'ok' => true, 'shipment_id' => $id ];
	}

	public static function refresh_status( int $id ): array {
		$row = self::get( $id );
		if ( ! $row ) return [ 'ok' => false, 'errors' => [ 'not_found' ] ];
		if ( ! $row->tracking_id ) return [ 'ok' => false, 'errors' => [ 'no_tracking_id' ] ];

		$provider = Registry::get( (string) $row->provider );
		if ( ! $provider ) return [ 'ok' => false, 'errors' => [ 'unknown_provider' ] ];

		$result = $provider->check_status( (string) $row->tracking_id );
		if ( empty( $result['ok'] ) ) return $result;

		$status          = (string) ( $result['status'] ?? $row->status );
		$internal_status = (string) ( $result['internal_status'] ?? $status );
		if ( ! ShipmentStatus::is_valid( $internal_status ) ) {
			$internal_status = ShipmentStatus::CREATED;
		}
		$delivered  = (bool) ( $result['delivered'] ?? false );
		$now        = current_time( 'mysql', 1 );

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( Database::shipments_table(), [
			'status'            => $status,
			'internal_status'   => $internal_status,
			'last_status_check' => $now,
			'delivered_at'      => $delivered ? $now : ( $row->delivered_at ?: null ),
			'response'          => isset( $result['raw'] ) ? wp_json_encode( $result['raw'] ) : $row->response,
			'date_modified'     => $now,
		], [ 'id' => $id ] );
		// phpcs:enable

		do_action( 'storeengine/courier/shipment_status_updated', $id, $status, $internal_status, $delivered );

		return [ 'ok' => true, 'status' => $status, 'internal_status' => $internal_status, 'delivered' => $delivered ];
	}

	public static function cancel( int $id ): array {
		$row = self::get( $id );
		if ( ! $row ) return [ 'ok' => false, 'errors' => [ 'not_found' ] ];

		$provider = Registry::get( (string) $row->provider );
		if ( ! $provider ) return [ 'ok' => false, 'errors' => [ 'unknown_provider' ] ];

		if ( $row->tracking_id ) {
			$provider->cancel( (string) $row->tracking_id );
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( Database::shipments_table(), [
			'status'          => 'cancelled',
			'internal_status' => ShipmentStatus::CANCELLED,
			'date_modified'   => current_time( 'mysql', 1 ),
		], [ 'id' => $id ] );
		// phpcs:enable

		do_action(
			'storeengine/courier/shipment_status_updated',
			$id,
			'cancelled',
			ShipmentStatus::CANCELLED,
			false
		);

		return [ 'ok' => true ];
	}

	public static function in_flight_ids( int $limit = 50 ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is a safe internal constant; value uses %d placeholder.
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM " . Database::shipments_table() . "
			  WHERE internal_status NOT IN ('delivered','cancelled','returned')
				AND tracking_id IS NOT NULL
			  ORDER BY (last_status_check IS NULL) DESC, last_status_check ASC
			  LIMIT %d",
			$limit
		) );
		// phpcs:enable
		return array_map( 'intval', (array) $rows );
	}
}
