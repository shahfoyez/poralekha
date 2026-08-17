<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;

class DownloadPermission {

	protected string $table;

	protected int $id;

	protected array $data       = [
		'user_id'             => 0,
		'order_id'            => 0,
		'download_id'         => '',
		'product_id'          => 0,
		'price_id'            => null,
		'variation_id'        => null,
		'downloads_remaining' => null,
		'access_granted'      => null,
		'access_expires'      => null,
		'download_count'      => 0,
	];
	protected array $new_data   = [];
	protected array $extra_data = [
		'order_key'     => '',
		'billing_email' => '',
	];

	public const CACHE_KEY   = 'storeengine_download_permission_';
	public const CACHE_GROUP = 'storeengine_download_permissions';

	public function __construct( int $id = 0 ) {
		global $wpdb;
		$this->table = $wpdb->prefix . Helper::DB_PREFIX . 'downloadable_product_permissions';
		$this->id    = $id;
	}

	public function get() {
		$has_cache = wp_cache_get( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
		if ( $has_cache ) {
			return $this->set_data( $has_cache );
		}

		global $wpdb;
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT
					dp.*,
					oa.email AS billing_email,
					op.order_key AS order_key
				FROM
					$this->table dp
					INNER JOIN {$wpdb->prefix}storeengine_orders o ON o.id = dp.order_id
					INNER JOIN {$wpdb->prefix}storeengine_order_operational_data op ON op.order_id = o.id
					INNER JOIN {$wpdb->prefix}storeengine_order_addresses oa ON oa.order_id = o.id
					AND oa.address_type = 'billing'
				WHERE
					dp.id = %d", $this->id )
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $result ) {
			return false;
		}

		return $this->set_data( $result );
	}

	public function set_data( $data ): self {
		wp_cache_set( self::CACHE_KEY . $this->id, $data, self::CACHE_GROUP );

		$this->id   = (int) $data->id;
		$this->data = [
			'user_id'             => (int) $data->user_id,
			'order_id'            => (int) $data->order_id,
			'download_id'         => $data->download_id,
			'product_id'          => (int) $data->product_id,
			'price_id'            => $data->price_id ? (int) $data->price_id : null,
			'variation_id'        => $data->variation_id ? (int) $data->variation_id : null,
			'downloads_remaining' => $data->downloads_remaining,
			'access_granted'      => $data->access_granted,
			'access_expires'      => $data->access_expires,
			'download_count'      => (int) $data->download_count,
		];

		if ( $data->order_key ) {
			$this->extra_data['order_key'] = $data->order_key;
		}

		if ( $data->billing_email ) {
			$this->extra_data['billing_email'] = $data->billing_email;
		}

		return $this;
	}

	public function save() {
		if ( empty( $this->new_data ) ) {
			return;
		}

		global $wpdb;
		$data        = array_merge( $this->data, $this->new_data );
		$placeholder = [ '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d' ];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( 0 === $this->id ) {
			$wpdb->insert( $this->table, $data, $placeholder );
			$this->id = $wpdb->insert_id;
		} else {
			$wpdb->update( $this->table, $data, [ 'id' => $this->get_id() ], $placeholder, [ '%d' ] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_cache_delete( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
	}

	public function delete(): bool {
		if ( 0 === $this->get_id() ) {
			return false;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->table, [ 'id' => $this->get_id() ], [ '%d' ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_cache_delete( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
		wp_cache_flush_group( self::CACHE_GROUP );

		return true;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_user_id(): int {
		return $this->get_prop( 'product_id', 0 );
	}

	public function get_order_id(): int {
		return $this->get_prop( 'order_id', 0 );
	}

	public function get_download_id(): string {
		return $this->get_prop( 'download_id' );
	}

	public function get_product_id() {
		return $this->get_prop( 'product_id', null );
	}

	public function get_product_title(): string {
		return get_the_title( $this->get_product_id() );
	}

	public function get_price_id() {
		return $this->get_prop( 'price_id', null );
	}

	public function get_variation_id() {
		return $this->get_prop( 'variation_id', null );
	}

	public function get_downloads_remaining() {
		return $this->get_prop( 'downloads_remaining', null );
	}

	public function get_file_name(): string {
		$downloadable_files = get_post_meta( $this->get_product_id(), '_storeengine_product_downloadable_files', true );
		if ( empty( $downloadable_files ) ) {
			return '';
		}
		$downloadable_files = maybe_unserialize( $downloadable_files );
		if ( ! is_array( $downloadable_files ) ) {
			return '';
		}

		$downloadable_files = array_filter( $downloadable_files, fn( $file ) => $file['id'] === $this->get_download_id() );

		return empty( $downloadable_files ) ? '' : reset( $downloadable_files )['name'];
	}

	public function get_order_key() {
		return $this->extra_data['order_key'] ?? '';
	}

	public function get_billing_email() {
		return $this->extra_data['billing_email'] ?? '';
	}

	public function get_download_url(): string {
		$args = [
			'download_file' => $this->get_product_id(),
			'order'         => $this->get_order_key(),
			'uid'           => hash( 'sha256', $this->get_billing_email() ),
			'key'           => $this->get_download_id(),
		];

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * @throws \Exception
	 */
	public function get_access_granted_date(): ?StoreengineDatetime {
		$access_granted = $this->get_prop( 'access_granted', null );

		return ! empty( $access_granted ) ? new StoreengineDatetime( $access_granted ) : null;
	}

	protected function get_prop( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->new_data ) ) {
			return $this->new_data[ $name ];
		}

		return $this->data[ $name ] ?? $default;
	}

	public function set_user_id( int $value ) {
		$this->new_data['user_id'] = $value;
	}

	public function set_order_id( int $value ) {
		$this->new_data['order_id'] = $value;
	}

	public function set_download_id( string $value ) {
		$this->new_data['download_id'] = $value;
	}

	public function set_product_id( int $value ) {
		$this->new_data['product_id'] = $value;
	}

	public function set_price_id( $value ) {
		$this->new_data['price_id'] = $value;
	}

	public function set_variation_id( $value ) {
		$this->new_data['variation_id'] = $value;
	}

	public function set_downloads_remaining( $value ) {
		$this->new_data['downloads_remaining'] = $value;
	}

	public function set_access_granted( $value ) {
		$this->new_data['access_granted'] = $value;
	}
}
