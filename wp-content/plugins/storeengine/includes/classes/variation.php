<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Data\AttributeData;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Variation {

	protected int $id;
	protected string $table;
	protected string $name = '';

	protected array $data     = [
		'sku'                 => '',
		'barcode'             => null,
		'product_id'          => '',
		'price'               => null,
		'cost_price'          => null,
		'manage_stock'        => 0,
		'stock_quantity'      => null,
		'stock_status'        => 'instock',
		'backorders'          => 'no',
		'low_stock_threshold' => null,
	];
	protected array $new_data = [];

	protected array $data_format = [
		'sku'                 => '%s',
		'barcode'             => '%s',
		'product_id'          => '%d',
		'price'               => '%f',
		'cost_price'          => '%f',
		'manage_stock'        => '%d',
		'stock_quantity'      => '%d',
		'stock_status'        => '%s',
		'backorders'          => '%s',
		'low_stock_threshold' => '%d',
	];

	protected array $meta_data        = [];
	protected array $json_meta        = [];
	protected array $add_meta_data    = [];
	protected array $update_meta_data = [];
	protected array $delete_meta_data = [];
	/**
	 * @var AttributeData[]
	 */
	protected array $attributes              = [];
	protected array $new_attributes_term_ids = [];

	public const CACHE_KEY   = 'storeengine_variation_';
	public const CACHE_GROUP = 'storeengine_variations';

	public function __construct( int $id = 0 ) {
		global $wpdb;
		$this->id    = $id;
		$this->table = $wpdb->prefix . Helper::DB_PREFIX . 'product_variations';
	}

	public function get() {
		global $wpdb;

		$has_cache = wp_cache_get( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
		if ( $has_cache ) {
			return $this->set_data( $has_cache );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}storeengine_product_variations WHERE id = %d;",
				$this->get_id()
			)
		);

		if ( ! $result ) {
			return false;
		}

		$variation_attributes = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					t.term_id AS term_id,
					tt.term_taxonomy_id AS term_taxonomy_id,
					pl.variation_id AS variation_id,
					t.name AS name,
					t.slug AS slug,
					tt.description AS description,
					tt.taxonomy AS taxonomy,
					tt.`count` AS count,
					pl.term_order AS term_order
				FROM
					{$wpdb->prefix}storeengine_variation_term_relations pl
					LEFT JOIN {$wpdb->terms} t ON t.term_id = pl.term_id
					LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = pl.term_id
				WHERE
					pl.variation_id = %d
				ORDER BY
					pl.term_order DESC
				",
				$this->get_id()
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		$result->attributes = is_array( $variation_attributes ) ? $variation_attributes : [];

		wp_cache_set( self::CACHE_KEY . $this->get_id(), $result, self::CACHE_GROUP );

		$this->get_metadata_from_db();

		$this->set_data( $result );

		return $this;
	}

	public function get_metadata_from_db() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}storeengine_product_variation_meta WHERE variation_id = %d", $this->get_id()
		) );

		if ( empty( $results ) ) {
			return;
		}

		foreach ( $results as $result ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$this->meta_data[ $result->meta_key ] = $result->meta_value;
		}
	}

	public function save() {
		$had_stock_change = array_key_exists( 'stock_quantity', $this->new_data )
			|| array_key_exists( 'manage_stock', $this->new_data );

		$this->save_core();
		$this->save_add_metadata();
		$this->save_update_metadata();
		$this->save_attributes();
		$this->save_delete_metadata();

		// Fire after persistence so listeners (e.g. inventory-pro) can mirror
		// the new aggregate qty into per-location stock at the default
		// location. Only fires when the editor actually changed stock.
		if ( $had_stock_change && $this->get_id() && $this->manages_stock() ) {
			$qty = $this->get_stock_quantity();
			/**
			 * Variation aggregate stock just changed.
			 *
			 * @param int      $product_id
			 * @param int      $variation_id
			 * @param int|null $stock_quantity   Current aggregate qty.
			 * @param string   $context          'editor' (product editor save) for now.
			 */
			do_action(
				'storeengine/inventory/stock_quantity_set',
				(int) $this->get_product_id(),
				(int) $this->get_id(),
				null === $qty ? null : (int) $qty,
				'editor'
			);
		}
	}

	protected function save_core() {
		if ( empty( $this->new_data ) ) {
			return;
		}

		global $wpdb;
		$data      = array_merge( $this->data, $this->new_data );
		$formatter = array_values( array_map(
			fn( $key ) => $this->data_format[ $key ] ?? '%s',
			array_keys( $data )
		) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( 0 === $this->get_id() ) {
			$wpdb->insert(
				$this->table,
				$data,
				$formatter
			);
			$this->id = $wpdb->insert_id;
		} else {
			$wpdb->update( $this->table, $data, [ 'id' => $this->get_id() ], $formatter, [ '%d' ] );
			wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
			wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
		wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
	}

	protected function save_add_metadata() {
		if ( empty( $this->add_meta_data ) ) {
			return;
		}

		global $wpdb;
		$values = [];
		foreach ( $this->add_meta_data as $key => $value ) {
			if ( in_array( $key, $this->json_meta, true ) ) {
				$value = wp_json_encode( $value );
			}

			$type = in_array( gettype( $value ), [
				'integer',
				'boolean',
			], true ) ? '%d' : ( in_array( gettype( $value ), [ 'double', 'float' ], true ) ? '%f' : '%s' );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$values[] = $wpdb->prepare( "( %d, %s, $type )", $this->get_id(), $key, $value );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		}
		$values = implode( ', ', $values );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "INSERT INTO {$wpdb->prefix}storeengine_product_variation_meta (variation_id, meta_key, meta_value) VALUES $values" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	protected function save_update_metadata() {
		if ( empty( $this->update_meta_data ) ) {
			return;
		}

		global $wpdb;
		$values = [];
		foreach ( $this->update_meta_data as $key => $value ) {
			if ( in_array( $key, $this->json_meta, true ) ) {
				$value = wp_json_encode( $value );
			}

			$type = in_array( gettype( $value ), [
				'integer',
				'boolean',
			], true ) ? '%d' : ( in_array( gettype( $value ), [ 'double', 'float' ], true ) ? '%f' : '%s' );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$values[] = $wpdb->prepare( "WHEN variation_id = %d AND meta_key = %s THEN $type", $this->get_id(), $key, $value );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		}
		$values = implode( ' ', $values );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}storeengine_product_variation_meta
							SET meta_value = CASE
								$values
							END
							WHERE variation_id = %d", $this->get_id() ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	protected function save_attributes() {
		if ( empty( $this->attributes ) && empty( $this->new_attributes_term_ids ) ) {
			return;
		}

		$term_ids = array_map( fn( $attribute ) => $attribute->term_id, $this->attributes );

		$removed_items = array_values( array_diff( $term_ids, $this->new_attributes_term_ids ) );
		$new_items     = array_values( array_diff( $this->new_attributes_term_ids, $term_ids ) );

		global $wpdb;
		if ( ! empty( $removed_items ) ) {
			$removed_placeholders = array_fill( 0, count( $removed_items ), '%d' );
			$removed_placeholders = implode( ',', $removed_placeholders );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}storeengine_variation_term_relations WHERE variation_id = %d AND term_id IN (" . $removed_placeholders . ')', $this->get_id(), ...$removed_items
			) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
			wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
		}

		if ( ! empty( $new_items ) ) {
			$values = [];
			foreach ( $new_items as $item ) {
				$values[] = $wpdb->prepare( '(%d, %d)', $this->get_id(), $item );
			}
			$values = implode( ', ', $values );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- We've prepared values above.
			$wpdb->query( "INSERT INTO {$wpdb->prefix}storeengine_variation_term_relations (variation_id, term_id) VALUES {$values}" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			wp_cache_flush_group( self::CACHE_GROUP );
		}

		// update order.
		$update_query = "UPDATE {$wpdb->prefix}storeengine_variation_term_relations SET term_order = CASE ";
		$values       = [];

		foreach ( $this->new_attributes_term_ids as $index => $new_attribute_term_id ) {
			$update_query .= 'WHEN variation_id = %d AND term_id = %d THEN %d ';
			$values[]      = $this->get_id();
			$values[]      = $new_attribute_term_id;
			$values[]      = $index;
		}
		$update_query .= 'END WHERE variation_id = %d';
		$values[]      = $this->get_id();
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( $update_query, ...$values ) );
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		$this->get();
	}

	protected function save_delete_metadata() {
		if ( empty( $this->delete_meta_data ) ) {
			return;
		}

		$placeholders = array_fill( 0, count( $this->delete_meta_data ), '%s' );
		$placeholders = implode( ',', $placeholders );

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamically prepared.
		$values = $wpdb->prepare( $placeholders, $this->delete_meta_data );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}storeengine_product_variation_meta WHERE variation_id = %d AND meta_key IN $values", $this->get_id()
		) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
	}

	public function delete(): bool {
		if ( 0 === $this->get_id() ) {
			return false;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res = (bool) $wpdb->delete( $this->table, [ 'id' => $this->get_id() ], [ '%d' ] );
		if ( $res ) {
			$wpdb->delete( $wpdb->prefix . 'storeengine_product_variation_meta', [ 'variation_id' => $this->get_id() ], [ '%d' ] );
			$wpdb->delete( $wpdb->prefix . 'storeengine_variation_term_relations', [ 'variation_id' => $this->get_id() ], [ '%d' ] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_cache_delete( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
		wp_cache_flush_group( AbstractProduct::CACHE_GROUP );

		return $res;
	}

	public function set_data( $data ): Variation {
		$this->id                          = (int) $data->id;
		$this->data['sku']                 = $data->sku;
		$this->data['barcode']             = isset( $data->barcode ) && '' !== $data->barcode ? (string) $data->barcode : null;
		$this->data['product_id']          = (int) $data->product_id;
		$this->data['price']               = $data->price ? (float) $data->price : null;
		$this->data['cost_price']          = isset( $data->cost_price ) && '' !== $data->cost_price && null !== $data->cost_price ? (float) $data->cost_price : null;
		$this->data['manage_stock']        = isset( $data->manage_stock ) ? (int) $data->manage_stock : 0;
		$this->data['stock_quantity']      = isset( $data->stock_quantity ) && '' !== $data->stock_quantity && null !== $data->stock_quantity ? (int) $data->stock_quantity : null;
		$this->data['stock_status']        = $data->stock_status ?? 'instock';
		$this->data['backorders']          = $data->backorders ?? 'no';
		$this->data['low_stock_threshold'] = isset( $data->low_stock_threshold ) && '' !== $data->low_stock_threshold && null !== $data->low_stock_threshold ? (int) $data->low_stock_threshold : null;
		$attributes                        = $data->attributes;

		if ( ! is_array( $attributes ) ) {
			return $this;
		}

		usort( $attributes, fn( $a, $b ) => $a->term_order <=> $b->term_order );
		$this->attributes = [];
		foreach ( $attributes as $attribute ) {
			$this->attributes[] = ( new AttributeData() )->set_data( $attribute );
		}
		$this->name = implode( ' / ', wp_list_pluck( $this->attributes, 'name' ) );

		return $this;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_sku() {
		return $this->get_prop( 'sku' );
	}

	public function get_product_id() {
		return $this->get_prop( 'product_id', 0 );
	}

	public function get_price() {
		return $this->get_prop( 'price', null );
	}

	public function get_metadata( string $name ) {
		return $this->get_prop_metadata( $name );
	}

	public function get_pricing_id() {
		return $this->get_prop_metadata( '_pricing_id', null );
	}

	public function get_featured_image() {
		return $this->get_prop_metadata( '_featured_image_id', null );
	}

	public function get_attributes(): array {
		return $this->attributes;
	}

	public function set_product_id( int $value ) {
		$this->new_data['product_id'] = $value;
	}

	public function set_sku( string $value ) {
		$this->new_data['sku'] = $value;
	}

	public function get_barcode(): ?string {
		$value = $this->get_prop( 'barcode', null );
		return ( null === $value || '' === $value ) ? null : (string) $value;
	}

	public function set_barcode( ?string $value ): void {
		$this->new_data['barcode'] = ( null === $value || '' === $value ) ? null : $value;
	}

	public function get_cost_price(): ?float {
		$value = $this->get_prop( 'cost_price', null );
		return ( null === $value || '' === $value ) ? null : (float) $value;
	}

	public function set_cost_price( ?float $value ): void {
		$this->new_data['cost_price'] = $value;
	}

	/**
	 * @param float|null $value
	 *
	 * @return void
	 */
	public function set_price( ?float $value ) {
		$this->new_data['price'] = $value;
	}

	public function set_price_id( $value ) {
		$this->set_prop_metadata( '_pricing_id', $value );
	}

	public function set_featured_image( ?int $attachment_id ) {
		$this->set_prop_metadata( '_featured_image_id', $attachment_id );
	}

	public function add_metadata( string $name, $value ) {
		$this->add_meta_data[ $name ] = $value;
	}

	public function update_metadata( string $name, $value ) {
		$this->update_meta_data[ $name ] = $value;
	}

	public function remove_metadata( string $name ) {
		$this->delete_meta_data[ $name ] = $name;
	}

	public function set_id( int $id ) {
		$this->id = $id;
	}

	public function set_attributes( array $term_ids ) {
		$this->new_attributes_term_ids = $term_ids;
	}

	public function set_metadata( array $data ) {
		$this->meta_data = $data;
	}

	protected function get_prop_metadata( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->update_meta_data ) ) {
			return $this->update_meta_data[ $name ];
		}

		if ( array_key_exists( $name, $this->add_meta_data ) ) {
			return $this->add_meta_data[ $name ];
		}

		return $this->meta_data[ $name ] ?? $default;
	}

	protected function get_prop( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->new_data ) ) {
			return $this->new_data[ $name ];
		}

		return $this->data[ $name ] ?? $default;
	}

	protected function set_prop_metadata( string $name, $value ) {
		if ( array_key_exists( $name, $this->meta_data ) ) {
			$this->update_meta_data[ $name ] = $value;
		} else {
			$this->add_meta_data[ $name ] = $value;
		}
	}

	public function manages_stock(): bool {
		return (bool) (int) $this->get_prop( 'manage_stock', 0 );
	}

	public function set_manage_stock( bool $value ): void {
		$this->new_data['manage_stock'] = $value ? 1 : 0;
	}

	public function get_stock_quantity( string $context = 'view' ): ?int {
		$value = $this->get_prop( 'stock_quantity', null );

		if ( null === $value || '' === $value ) {
			return null;
		}

		return (int) $value;
	}

	public function set_stock_quantity( ?int $qty ): void {
		$this->new_data['stock_quantity'] = $qty;
	}

	public function get_stock_status(): string {
		// When stock is tracked, derive the status from the on-hand quantity so
		// it can never drift out of sync (a tracked variant at 0 must read
		// "outofstock" even if a stale status meta still says "instock").
		if ( $this->manages_stock() ) {
			$qty = $this->get_stock_quantity();
			if ( null !== $qty ) {
				if ( $qty > 0 ) {
					return 'instock';
				}
				return $this->backorders_allowed() ? 'onbackorder' : 'outofstock';
			}
		}

		$status = $this->get_prop( 'stock_status', 'instock' );

		if ( ! in_array( $status, [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
			$status = 'instock';
		}

		return $status;
	}

	public function set_stock_status( string $status ): void {
		if ( ! in_array( $status, [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
			$status = 'instock';
		}

		$from = $this->get_stock_status();

		$this->new_data['stock_status'] = $status;

		if ( $from !== $status && $this->get_id() ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update( $this->table, [ 'stock_status' => $status ], [ 'id' => $this->get_id() ], [ '%s' ], [ '%d' ] );
			// phpcs:enable
			$this->data['stock_status'] = $status;
			wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );

			do_action( 'storeengine/stock_status_changed', (int) $this->get_product_id(), $this->get_id(), $from, $status );
		}
	}

	/**
	 * Stage a stock-status change to be flushed by save() — used during bulk
	 * variation persistence where we want a single DB write and a single hook
	 * fire from the caller.
	 */
	public function new_data_set_stock_status( string $status ): void {
		if ( ! in_array( $status, [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
			$status = 'instock';
		}
		$this->new_data['stock_status'] = $status;
	}

	public function get_backorders(): string {
		$value = $this->get_prop( 'backorders', 'no' );

		if ( ! in_array( $value, [ 'no', 'notify', 'yes' ], true ) ) {
			$value = 'no';
		}

		return $value;
	}

	public function set_backorders( string $value ): void {
		if ( ! in_array( $value, [ 'no', 'notify', 'yes' ], true ) ) {
			$value = 'no';
		}
		$this->new_data['backorders'] = $value;
	}

	public function backorders_allowed(): bool {
		return in_array( $this->get_backorders(), [ 'yes', 'notify' ], true );
	}

	public function backorders_require_notification(): bool {
		return 'notify' === $this->get_backorders();
	}

	public function get_low_stock_threshold(): ?int {
		$value = $this->get_prop( 'low_stock_threshold', null );

		if ( null === $value || '' === $value ) {
			$global = (int) get_option( 'storeengine_low_stock_threshold', 0 );
			return $global > 0 ? $global : null;
		}

		return (int) $value;
	}

	public function set_low_stock_threshold( ?int $value ): void {
		$this->new_data['low_stock_threshold'] = $value;
	}

	public function get_reserved_stock(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'storeengine_reserved_stock';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifier bound via %i and values via %d/%s in prepare(); reserved-stock sum on a custom StoreEngine table, per-request.
		$qty = $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(stock_quantity),0) FROM %i WHERE variation_id = %d AND expires > %s',
			$table,
			$this->get_id(),
			current_time( 'mysql', true )
		) );

		return (int) $qty;
	}

	public function get_available_stock_quantity(): ?int {
		$qty = $this->get_stock_quantity();

		if ( null === $qty ) {
			return null;
		}

		return max( 0, $qty - $this->get_reserved_stock() );
	}

	public function has_enough_stock( int $qty ): bool {
		if ( ! $this->manages_stock() ) {
			return $this->is_in_stock();
		}

		if ( $this->backorders_allowed() ) {
			return true;
		}

		$available = $this->get_available_stock_quantity();

		return null === $available || $available >= $qty;
	}

	public function is_low_stock(): bool {
		if ( ! $this->manages_stock() ) {
			return false;
		}

		$threshold = $this->get_low_stock_threshold();
		$qty       = $this->get_stock_quantity();

		if ( null === $threshold || null === $qty ) {
			return false;
		}

		return $qty > 0 && $qty <= $threshold;
	}

	public function is_in_stock(): bool {
		if ( $this->manages_stock() ) {
			// Mirror AbstractProduct::is_in_stock() — subtract live
			// reservations so the cart's has_enough_stock() agrees with
			// the storefront badge for variable products too.
			$qty = $this->get_available_stock_quantity();

			if ( null === $qty ) {
				return $this->backorders_allowed();
			}

			return $qty > 0 || $this->backorders_allowed();
		}

		$status = $this->get_stock_status();

		return 'instock' === $status || ( 'onbackorder' === $status && $this->backorders_allowed() );
	}

	public function is_sold_individually(): bool {
		// Variations defer to the parent product for sold-individually behavior.
		$product = $this->get_product_id() ? Helper::get_product( (int) $this->get_product_id() ) : null;

		return $product && method_exists( $product, 'is_sold_individually' ) ? $product->is_sold_individually() : false;
	}

	public function reduce_stock( int $qty ): int {
		if ( ! $this->manages_stock() ) {
			return 0;
		}

		$current = (int) $this->get_stock_quantity();
		$new_qty = $current - $qty;

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( $this->table, [ 'stock_quantity' => $new_qty ], [ 'id' => $this->get_id() ], [ '%d' ], [ '%d' ] );
		// phpcs:enable
		$this->data['stock_quantity'] = $new_qty;
		wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );

		$this->maybe_sync_stock_status_from_quantity( $new_qty );

		return $new_qty;
	}

	public function increase_stock( int $qty ): int {
		if ( ! $this->manages_stock() ) {
			return 0;
		}

		$current = (int) $this->get_stock_quantity();
		$new_qty = $current + $qty;

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( $this->table, [ 'stock_quantity' => $new_qty ], [ 'id' => $this->get_id() ], [ '%d' ], [ '%d' ] );
		// phpcs:enable
		$this->data['stock_quantity'] = $new_qty;
		wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );

		$this->maybe_sync_stock_status_from_quantity( $new_qty );

		return $new_qty;
	}

	protected function maybe_sync_stock_status_from_quantity( int $new_qty ): void {
		$old_status = $this->get_stock_status();

		if ( $new_qty <= 0 && ! $this->backorders_allowed() ) {
			$new_status = 'outofstock';
		} elseif ( $new_qty <= 0 && $this->backorders_allowed() ) {
			$new_status = 'onbackorder';
		} else {
			$new_status = 'instock';
		}

		if ( $old_status !== $new_status ) {
			$this->set_stock_status( $new_status );
		}
	}
}
