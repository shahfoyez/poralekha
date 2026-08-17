<?php
/**
 * Funnel step entity + repository.
 *
 * Each step belongs to a funnel and renders a WP page (page_id) built with the
 * active page builder (aBlocks). Ordering is by step_order. Per-step config
 * lives in the JSON `settings` column (offer, discount, redirects, Pro
 * conditions / A/B variant).
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder\Classes;

use StoreEngine\Addons\FunnelBuilder\Installer;
use StoreEngine\Addons\FunnelBuilder\StoreCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FunnelStep {

	/**
	 * Base (free) step types. Pro reuses these — upsell/downsell behaviour is
	 * gated by the Pro addon, but the type lives in the shared schema.
	 */
	const TYPES = [ 'landing', 'optin', 'checkout', 'upsell', 'downsell', 'thankyou' ];

	public int $id = 0;
	public int $funnel_id = 0;
	public ?int $page_id = null;
	public string $type = 'landing';
	public string $name = '';
	public int $step_order = 0;
	public ?string $ab_variant_group = null;
	public array $settings = [];
	public ?string $created_at = null;
	public ?string $updated_at = null;

	public function __construct( array $data = [] ) {
		if ( $data ) {
			$this->populate( $data );
		}
	}

	protected function populate( array $row ): void {
		$this->id               = (int) ( $row['id'] ?? 0 );
		$this->funnel_id        = (int) ( $row['funnel_id'] ?? 0 );
		$this->page_id          = isset( $row['page_id'] ) ? (int) $row['page_id'] : null;
		$this->type             = (string) ( $row['type'] ?? 'landing' );
		$this->name             = (string) ( $row['name'] ?? '' );
		$this->step_order       = (int) ( $row['step_order'] ?? 0 );
		$this->ab_variant_group = $row['ab_variant_group'] ?? null;
		$this->settings         = self::decode( $row['settings'] ?? '' );
		$this->created_at       = $row['created_at'] ?? null;
		$this->updated_at       = $row['updated_at'] ?? null;
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
		$table = Installer::steps_table();
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- %i identifier + %d value via prepare() on a custom StoreEngine table; single-row read, not cacheable.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ), ARRAY_A );

		return $row ? new self( $row ) : null;
	}

	/**
	 * Per-request cache of a funnel's steps, keyed by funnel_id.
	 *
	 * `next()`, `Funnel::steps()` and the Store Checkout resolver all read a
	 * funnel's steps repeatedly within one request; without this, resolving a
	 * single next-step redirect ran O(n²) table queries. Busted on save/delete.
	 *
	 * @var array<int, self[]>
	 */
	protected static array $for_funnel_cache = [];

	/**
	 * @return self[]
	 */
	public static function for_funnel( int $funnel_id ): array {
		if ( isset( self::$for_funnel_cache[ $funnel_id ] ) ) {
			return self::$for_funnel_cache[ $funnel_id ];
		}

		global $wpdb;
		$table = Installer::steps_table();
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- %i identifier + %d value via prepare() on a custom StoreEngine table; result cached per request in $for_funnel_cache.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE funnel_id = %d ORDER BY step_order ASC, id ASC', $table, $funnel_id ), ARRAY_A ) ?: [];

		$steps = array_map( static fn( $row ) => new self( $row ), $rows );

		self::$for_funnel_cache[ $funnel_id ] = $steps;

		return $steps;
	}

	/**
	 * Drop the per-request step cache for a funnel (after a write).
	 */
	public static function flush_cache( int $funnel_id = 0 ): void {
		if ( $funnel_id ) {
			unset( self::$for_funnel_cache[ $funnel_id ] );
		} else {
			self::$for_funnel_cache = [];
		}
	}

	public static function find_by_page( int $page_id ): ?self {
		global $wpdb;
		$table = Installer::steps_table();
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- %i identifier + %d value via prepare() on a custom StoreEngine table; single-row read, not cacheable.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE page_id = %d LIMIT 1', $table, $page_id ), ARRAY_A );

		return $row ? new self( $row ) : null;
	}

	public static function next_order( int $funnel_id ): int {
		global $wpdb;
		$table = Installer::steps_table();
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- %i identifier + %d value via prepare() on a custom StoreEngine table; aggregate read, not cacheable.
		$max = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(step_order) FROM %i WHERE funnel_id = %d', $table, $funnel_id ) );

		return null === $max ? 0 : ( (int) $max + 1 );
	}

	public function save(): int {
		global $wpdb;
		$table = Installer::steps_table();
		$now   = current_time( 'mysql', true );

		$data = [
			'funnel_id'        => $this->funnel_id,
			'page_id'          => $this->page_id,
			'type'             => $this->type,
			'name'             => $this->name,
			'step_order'       => $this->step_order,
			'ab_variant_group' => $this->ab_variant_group,
			'settings'         => wp_json_encode( $this->settings ),
			'updated_at'       => $now,
		];

		if ( $this->id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to a custom StoreEngine table via $wpdb->update(); no cache layer applies.
			$wpdb->update( $table, $data, [ 'id' => $this->id ] );
		} else {
			$data['created_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to a custom StoreEngine table via $wpdb->insert(); no cache layer applies.
			$wpdb->insert( $table, $data );
			$this->id = (int) $wpdb->insert_id;
		}

		self::flush_cache( $this->funnel_id );

		// A checkout step change may affect the Store Checkout override resolution.
		if ( 'checkout' === $this->type && class_exists( StoreCheckout::class ) ) {
			StoreCheckout::flush();
		}

		return $this->id;
	}

	public function delete(): bool {
		global $wpdb;
		if ( ! $this->id ) {
			return false;
		}

		self::flush_cache( $this->funnel_id );

		if ( 'checkout' === $this->type && class_exists( StoreCheckout::class ) ) {
			StoreCheckout::flush();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete from a custom StoreEngine table via $wpdb->delete(); no cache layer applies.
		return (bool) $wpdb->delete( Installer::steps_table(), [ 'id' => $this->id ] );
	}

	/**
	 * The next step after this one in the funnel order (used for routing /
	 * upsell-skip). Returns null at the end of the funnel.
	 */
	public function next(): ?self {
		$steps = self::for_funnel( $this->funnel_id );
		$found = false;
		foreach ( $steps as $step ) {
			if ( $found ) {
				return $step;
			}
			if ( $step->id === $this->id ) {
				$found = true;
			}
		}

		return null;
	}

	public function get_url(): string {
		if ( $this->page_id ) {
			return (string) get_permalink( $this->page_id );
		}

		return '';
	}

	/**
	 * State of the WP page backing this step: publish | draft | trashed | missing.
	 * `missing` means no page_id, or the page was permanently deleted.
	 */
	public function page_status(): string {
		if ( ! $this->page_id ) {
			return 'missing';
		}
		$status = get_post_status( $this->page_id );
		if ( ! $status ) {
			return 'missing';
		}
		if ( 'trash' === $status ) {
			return 'trashed';
		}

		return 'publish' === $status ? 'publish' : 'draft';
	}

	/**
	 * A step is renderable only when its page exists and is published.
	 */
	public function is_page_live(): bool {
		return 'publish' === $this->page_status();
	}

	public function to_array(): array {
		return [
			'id'               => $this->id,
			'funnel_id'        => $this->funnel_id,
			'page_id'          => $this->page_id,
			'page_url'         => $this->get_url(),
			'page_edit_url'    => $this->page_id ? get_edit_post_link( $this->page_id, 'raw' ) : '',
			'page_status'      => $this->page_status(),
			'type'             => $this->type,
			'name'             => $this->name,
			'step_order'       => $this->step_order,
			'ab_variant_group' => $this->ab_variant_group,
			'settings'         => $this->settings,
			'created_at'       => $this->created_at,
			'updated_at'       => $this->updated_at,
		];
	}
}
