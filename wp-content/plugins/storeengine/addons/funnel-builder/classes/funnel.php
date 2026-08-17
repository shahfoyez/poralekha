<?php
/**
 * Funnel entity + repository.
 *
 * Thin active-record over the storeengine_funnels table. Kept self-contained
 * (direct $wpdb) to mirror the abandoned-cart / order-bump data classes rather
 * than coupling to the core AbstractEntity machinery.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder\Classes;

use StoreEngine\Addons\FunnelBuilder\Installer;
use StoreEngine\Addons\FunnelBuilder\StoreCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Funnel {

	public int $id = 0;
	public string $name = '';
	public string $slug = '';
	public string $status = 'draft';
	public string $type = 'sales';
	public string $trigger_type = 'manual';
	public array $trigger_data = [];
	public array $settings = [];
	public ?string $created_at = null;
	public ?string $updated_at = null;

	public function __construct( array $data = [] ) {
		if ( $data ) {
			$this->populate( $data );
		}
	}

	protected function populate( array $row ): void {
		$this->id           = (int) ( $row['id'] ?? 0 );
		$this->name         = (string) ( $row['name'] ?? '' );
		$this->slug         = (string) ( $row['slug'] ?? '' );
		$this->status       = (string) ( $row['status'] ?? 'draft' );
		$this->type         = (string) ( $row['type'] ?? 'sales' );
		$this->trigger_type = (string) ( $row['trigger_type'] ?? 'manual' );
		$this->trigger_data = self::decode( $row['trigger_data'] ?? '' );
		$this->settings     = self::decode( $row['settings'] ?? '' );
		$this->created_at    = $row['created_at'] ?? null;
		$this->updated_at    = $row['updated_at'] ?? null;
	}

	protected static function decode( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return [];
		}
		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	public static function find( int $id ): ?self {
		global $wpdb;
		$table = Installer::funnels_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%d) query on a custom StoreEngine table; $table is a plugin-internal name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return $row ? new self( $row ) : null;
	}

	public static function find_by_slug( string $slug ): ?self {
		global $wpdb;
		$table = Installer::funnels_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%s) query on a custom StoreEngine table; $table is a plugin-internal name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );

		return $row ? new self( $row ) : null;
	}

	/**
	 * @return self[]
	 */
	public static function all( array $args = [] ): array {
		global $wpdb;
		$table = Installer::funnels_table();
		$where = '1=1';
		$params = [];

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['trigger_type'] ) ) {
			$where   .= ' AND trigger_type = %s';
			$params[] = $args['trigger_type'];
		}

		// Bind the table via %i; $where is composed only of hardcoded clauses + placeholders.
		$sql = "SELECT * FROM %i WHERE {$where} ORDER BY id DESC";
		array_unshift( $params, $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared query (%i table + %s placeholders) on a custom StoreEngine table; $where is composed of literals only.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];

		return array_map( static fn( $row ) => new self( $row ), $rows );
	}

	/**
	 * The published funnel currently bound to the global checkout flow, if any.
	 */
	public static function get_global_checkout_funnel(): ?self {
		global $wpdb;
		$table = Installer::funnels_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query over a custom StoreEngine table; $table is a plugin-internal name, WHERE clause is all literals.
		$row = $wpdb->get_row( "SELECT * FROM {$table} WHERE trigger_type = 'global_checkout' AND status = 'publish' ORDER BY id DESC LIMIT 1", ARRAY_A );

		return $row ? new self( $row ) : null;
	}

	/**
	 * The published product-trigger funnel targeting any of the given product or
	 * price ids, if one exists. trigger_data carries { products: [], prices: [] }.
	 */
	public static function find_for_product( array $product_ids = [], array $price_ids = [] ): ?self {
		$product_ids = array_map( 'intval', $product_ids );
		$price_ids   = array_map( 'intval', $price_ids );

		foreach ( self::all( [ 'status' => 'publish', 'trigger_type' => 'product' ] ) as $funnel ) {
			$targets_products = array_map( 'intval', (array) ( $funnel->trigger_data['products'] ?? [] ) );
			$targets_prices   = array_map( 'intval', (array) ( $funnel->trigger_data['prices'] ?? [] ) );

			if ( array_intersect( $product_ids, $targets_products ) || array_intersect( $price_ids, $targets_prices ) ) {
				return $funnel;
			}
		}

		return null;
	}

	public function save(): int {
		global $wpdb;
		$table = Installer::funnels_table();
		$now   = current_time( 'mysql', true );

		if ( ! $this->slug && $this->name ) {
			$this->slug = self::unique_slug( sanitize_title( $this->name ) );
		}

		$data = [
			'name'         => $this->name,
			'slug'         => $this->slug,
			'status'       => $this->status,
			'type'         => $this->type,
			'trigger_type' => $this->trigger_type,
			'trigger_data' => wp_json_encode( $this->trigger_data ),
			'settings'     => wp_json_encode( $this->settings ),
			'updated_at'   => $now,
		];

		if ( $this->id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row write on a custom StoreEngine table; no cache layer applies.
			$wpdb->update( $table, $data, [ 'id' => $this->id ] );
		} else {
			$data['created_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row write on a custom StoreEngine table; no cache layer applies.
			$wpdb->insert( $table, $data );
			$this->id = (int) $wpdb->insert_id;
		}

		// A change to the (published) Store Checkout funnel changes what the site
		// checkout override serves — drop its cached resolution.
		if ( 'global_checkout' === $this->trigger_type && class_exists( StoreCheckout::class ) ) {
			StoreCheckout::flush();
		}

		return $this->id;
	}

	public function delete(): bool {
		global $wpdb;
		if ( ! $this->id ) {
			return false;
		}
		// Remove steps (and their stats) with the funnel.
		foreach ( FunnelStep::for_funnel( $this->id ) as $step ) {
			$step->delete();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row delete on a custom StoreEngine table; no cache layer applies.
		$wpdb->delete( Installer::stats_table(), [ 'funnel_id' => $this->id ] );

		if ( 'global_checkout' === $this->trigger_type && class_exists( StoreCheckout::class ) ) {
			StoreCheckout::flush();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row delete on a custom StoreEngine table; no cache layer applies.
		return (bool) $wpdb->delete( Installer::funnels_table(), [ 'id' => $this->id ] );
	}

	protected static function unique_slug( string $slug ): string {
		$slug = $slug ?: 'funnel';
		$base = $slug;
		$i    = 1;
		while ( self::find_by_slug( $slug ) ) {
			$slug = $base . '-' . ( ++$i );
		}

		return $slug;
	}

	/**
	 * @return FunnelStep[]
	 */
	public function steps(): array {
		return FunnelStep::for_funnel( $this->id );
	}

	public function to_array(): array {
		return [
			'id'           => $this->id,
			'name'         => $this->name,
			'slug'         => $this->slug,
			'status'       => $this->status,
			'type'         => $this->type,
			'trigger_type' => $this->trigger_type,
			'trigger_data' => $this->trigger_data,
			'settings'     => $this->settings,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		];
	}
}
