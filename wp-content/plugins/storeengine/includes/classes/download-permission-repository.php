<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DownloadPermissionRepository {

	public const CACHE_KEY = 'storeengine_download_permission_repository_';

	protected int $page;
	protected int $limit;

	public function __construct() {
	}

	/**
	 * @param int $order_id
	 *
	 * @return DownloadPermission[]
	 */
	public function get_by_order( int $order_id ): array {
		global $wpdb;

		$cache_key = self::CACHE_KEY . 'order_' . $order_id;
		$has_cache = wp_cache_get( $cache_key, DownloadPermission::CACHE_GROUP );

		if ( $has_cache ) {
			return $has_cache;
		}

		$objects = [];
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT
						dp.*,
						oa.email AS billing_email,
						op.order_key AS order_key
					FROM
						{$wpdb->prefix}storeengine_downloadable_product_permissions dp
						INNER JOIN {$wpdb->prefix}storeengine_orders o ON o.id = dp.order_id
						INNER JOIN {$wpdb->prefix}storeengine_order_operational_data op ON op.order_id = o.id
						INNER JOIN {$wpdb->prefix}storeengine_order_addresses oa ON oa.order_id = o.id
						AND oa.address_type = 'billing'
					WHERE
						dp.order_id = %d", $order_id )
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $results ) {
			return [];
		}

		foreach ( $results as $result ) {
			$objects[] = ( new DownloadPermission() )->set_data( $result );
		}

		wp_cache_set( $cache_key, $objects, DownloadPermission::CACHE_GROUP );

		return $objects;
	}

	/**
	 * @param array $order_ids
	 *
	 * @return DownloadPermission[]
	 */
	public function get_by_order_ids( array $order_ids ): array {
		global $wpdb;

		if ( empty( $order_ids ) ) {
			return [];
		}

		$cache_key = self::CACHE_KEY . 'order_ids_' . md5( json_encode( $order_ids ) );
		$has_cache = wp_cache_get( $cache_key, DownloadPermission::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		$objects   = [];
		$formatter = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT
						dp.*,
						oa.email AS billing_email,
						op.order_key AS order_key
					FROM
						{$wpdb->prefix}storeengine_downloadable_product_permissions dp
						INNER JOIN {$wpdb->prefix}storeengine_orders o ON o.id = dp.order_id
						INNER JOIN {$wpdb->prefix}storeengine_order_operational_data op ON op.order_id = o.id
						INNER JOIN {$wpdb->prefix}storeengine_order_addresses oa ON oa.order_id = o.id
						AND oa.address_type = 'billing'
					WHERE
						dp.order_id IN ($formatter)",
				...$order_ids
			)
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! $results ) {
			return [];
		}

		foreach ( $results as $result ) {
			$objects[] = ( new DownloadPermission() )->set_data( $result );
		}

		wp_cache_set( $cache_key, $objects, DownloadPermission::CACHE_GROUP );

		return $objects;
	}

	public function with_pagination( int $page = 1, int $per_page = 10 ): self {
		$this->page  = max( 1, $page );
		$this->limit = $per_page;

		return $this;
	}

	public function get_by_customer_id( int $customer_id ) {
		global $wpdb;

		$offset    = ( $this->page - 1 ) * $this->limit;
		$cache_key = self::CACHE_KEY . 'customer_' . $customer_id . '_' . $offset;
		$has_cache = wp_cache_get( $cache_key, DownloadPermission::CACHE_GROUP );

		if ( $has_cache ) {
			return $has_cache;
		}

		$objects = [];

		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT
						dp.*,
						oa.email AS billing_email,
						op.order_key AS order_key
					FROM
						{$wpdb->prefix}storeengine_downloadable_product_permissions dp
						INNER JOIN {$wpdb->prefix}storeengine_orders o ON o.id = dp.order_id
						INNER JOIN {$wpdb->prefix}storeengine_order_operational_data op ON op.order_id = o.id
						INNER JOIN {$wpdb->prefix}storeengine_order_addresses oa ON oa.order_id = o.id
						AND oa.address_type = 'billing'
					WHERE
						user_id = %d
					ORDER BY
						id DESC
					LIMIT
						%d
					OFFSET
						%d", $customer_id, $this->limit, $offset )
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $results ) {
			return [];
		}

		foreach ( $results as $result ) {
			$objects[] = ( new DownloadPermission() )->set_data( $result );
		}

		wp_cache_set( $cache_key, $objects, DownloadPermission::CACHE_GROUP );

		return $objects;
	}

	public function total_count_by_customer_id( int $customer_id ) {
		global $wpdb;

		$cache_key = self::CACHE_KEY . 'customer_count';
		$has_cache = wp_cache_get( $cache_key, DownloadPermission::CACHE_GROUP );

		if ( $has_cache ) {
			return $has_cache;
		}

		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_downloadable_product_permissions WHERE user_id = %d", $customer_id )
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_cache_set( $cache_key, $count, DownloadPermission::CACHE_GROUP );

		return $count;
	}
}
