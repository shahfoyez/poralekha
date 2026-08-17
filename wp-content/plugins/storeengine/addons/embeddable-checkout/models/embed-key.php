<?php

namespace StoreEngine\Addons\EmbeddableCheckout\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embed Key model.
 *
 * Manages publishable keys used by the Quick Checkout embed SDK to authorise
 * cross-origin checkout sessions. Keys are publishable (safe to ship in
 * client-side JS); the trust boundary is the per-key allowed-origin list.
 */
class EmbedKey {

	const SCOPE_ALL = 'all';
	const SCOPE_IDS = 'ids';

	const TABLE = 'storeengine_embed_keys';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	public static function create_table(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
		$table_name      = self::table_name();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`key_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`label` varchar(191) NOT NULL DEFAULT '',
			`embed_key` char(64) NOT NULL,
			`embed_key_hash` char(64) NOT NULL,
			`allowed_origins` longtext NOT NULL,
			`product_scope` varchar(20) NOT NULL DEFAULT 'all',
			`product_ids` longtext NULL,
			`is_system` tinyint(1) NOT NULL DEFAULT '0',
			`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			`last_used_at` TIMESTAMP NULL DEFAULT NULL,
			`revoked_at` TIMESTAMP NULL DEFAULT NULL,
			PRIMARY KEY (`key_id`),
			UNIQUE KEY `embed_key_hash` (`embed_key_hash`),
			KEY `embed_key` (`embed_key`)
		) $charset_collate;";

		dbDelta( $sql );
	}

	public static function generate_key(): string {
		$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$max      = strlen( $alphabet ) - 1;
		$out      = '';
		for ( $i = 0; $i < 32; $i++ ) {
			$out .= $alphabet[ random_int( 0, $max ) ];
		}

		return 'pk_live_' . $out;
	}

	public static function hash_key( string $key ): string {
		return hash( 'sha256', $key );
	}

	public static function find_by_key( string $key ): ?array {
		global $wpdb;

		$hash  = self::hash_key( $key );
		$table = self::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE embed_key_hash = %s LIMIT 1", $hash ), ARRAY_A ); // phpcs:ignore WordPress.DB

		return $row ? self::hydrate( $row ) : null;
	}

	public static function find( int $id ): ?array {
		global $wpdb;

		$table = self::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE key_id = %d LIMIT 1", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB

		return $row ? self::hydrate( $row ) : null;
	}

	public static function all(): array {
		global $wpdb;

		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY key_id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB

		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	public static function find_or_create_system_key(): array {
		global $wpdb;

		$table = self::table_name();
		$row   = $wpdb->get_row( "SELECT * FROM {$table} WHERE is_system = 1 AND revoked_at IS NULL LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB

		if ( $row ) {
			return self::hydrate( $row );
		}

		$id = self::create( [
			'label'           => __( 'System (same-site Quick Checkout)', 'storeengine' ),
			'allowed_origins' => self::same_site_origin_variants(),
			'product_scope'   => self::SCOPE_ALL,
			'product_ids'     => [],
			'is_system'       => 1,
		] );

		return self::find( $id );
	}

	public static function same_site_origin_variants(): array {
		$parts = wp_parse_url( home_url() );
		if ( empty( $parts['host'] ) ) {
			return [];
		}

		$host = strtolower( $parts['host'] );
		$port = ! empty( $parts['port'] ) ? ':' . (int) $parts['port'] : '';

		$bare = preg_replace( '/^www\./', '', $host );
		$www  = 'www.' . $bare;

		$out = [];
		foreach ( [ 'http', 'https' ] as $scheme ) {
			$out[] = $scheme . '://' . $bare . $port;
			if ( $www !== $bare ) {
				$out[] = $scheme . '://' . $www . $port;
			}
		}

		return array_values( array_unique( $out ) );
	}

	public static function create( array $data ): int {
		global $wpdb;

		$key     = $data['embed_key'] ?? self::generate_key();
		$origins = self::sanitize_origins( $data['allowed_origins'] ?? [] );
		$scope   = in_array( $data['product_scope'] ?? self::SCOPE_ALL, [ self::SCOPE_ALL, self::SCOPE_IDS ], true ) ? $data['product_scope'] : self::SCOPE_ALL;
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $data['product_ids'] ?? [] ) ) ) ) );

		$wpdb->insert( // phpcs:ignore WordPress.DB
			self::table_name(),
			[
				'label'           => sanitize_text_field( $data['label'] ?? '' ),
				'embed_key'       => $key,
				'embed_key_hash'  => self::hash_key( $key ),
				'allowed_origins' => wp_json_encode( $origins ),
				'product_scope'   => $scope,
				'product_ids'     => wp_json_encode( $ids ),
				'is_system'       => ! empty( $data['is_system'] ) ? 1 : 0,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
		);

		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$update = [];
		$format = [];
		if ( array_key_exists( 'label', $data ) ) {
			$update['label'] = sanitize_text_field( $data['label'] );
			$format[]        = '%s';
		}
		if ( array_key_exists( 'allowed_origins', $data ) ) {
			$update['allowed_origins'] = wp_json_encode( self::sanitize_origins( $data['allowed_origins'] ) );
			$format[]                  = '%s';
		}
		if ( array_key_exists( 'product_scope', $data ) ) {
			$scope                   = in_array( $data['product_scope'], [ self::SCOPE_ALL, self::SCOPE_IDS ], true ) ? $data['product_scope'] : self::SCOPE_ALL;
			$update['product_scope'] = $scope;
			$format[]                = '%s';
		}
		if ( array_key_exists( 'product_ids', $data ) ) {
			$update['product_ids'] = wp_json_encode( array_values( array_unique( array_filter( array_map( 'absint', (array) $data['product_ids'] ) ) ) ) );
			$format[]              = '%s';
		}

		if ( ! $update ) {
			return true;
		}

		return false !== $wpdb->update( // phpcs:ignore WordPress.DB
			self::table_name(),
			$update,
			[ 'key_id' => $id ],
			$format,
			[ '%d' ]
		);
	}

	public static function revoke( int $id ): bool {
		global $wpdb;

		if ( self::is_system_key( $id ) ) {
			return false;
		}

		return false !== $wpdb->update( // phpcs:ignore WordPress.DB
			self::table_name(),
			[ 'revoked_at' => current_time( 'mysql', true ) ],
			[ 'key_id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public static function delete( int $id ): bool {
		global $wpdb;

		if ( self::is_system_key( $id ) ) {
			return false;
		}

		return false !== $wpdb->delete( // phpcs:ignore WordPress.DB
			self::table_name(),
			[ 'key_id' => $id ],
			[ '%d' ]
		);
	}

	protected static function is_system_key( int $id ): bool {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_system FROM {$table} WHERE key_id = %d", $id ) ) === 1; // phpcs:ignore WordPress.DB
	}

	public static function touch_last_used( int $id ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB
			self::table_name(),
			[ 'last_used_at' => current_time( 'mysql', true ) ],
			[ 'key_id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public static function is_same_site_origin( string $origin ): bool {
		$origin = self::normalize_origin( $origin );
		if ( '' === $origin ) {
			return false;
		}

		return in_array( $origin, self::same_site_origin_variants(), true );
	}

	public static function origin_is_allowed( array $row, string $origin ): bool {
		$origin = self::normalize_origin( $origin );
		if ( ! $origin ) {
			return false;
		}

		foreach ( $row['allowed_origins'] as $allowed ) {
			if ( strcasecmp( $allowed, $origin ) === 0 ) {
				return true;
			}
		}

		// System-key auto-heal: if this is the same-site system key and the
		// inbound origin is a recognised variant of THIS site (different scheme
		// / www prefix / port), accept it AND persist it. Same-site Quick
		// Checkout buttons must never fail with "origin not allowed".
		if ( ! empty( $row['is_system'] ) && in_array( $origin, self::same_site_origin_variants(), true ) ) {
			self::append_allowed_origin( (int) $row['key_id'], $origin );
			return true;
		}

		return false;
	}

	public static function append_allowed_origin( int $key_id, string $origin ): void {
		global $wpdb;

		$origin = self::normalize_origin( $origin );
		if ( ! $origin || ! $key_id ) {
			return;
		}

		$row = self::find( $key_id );
		if ( ! $row ) {
			return;
		}

		$existing = $row['allowed_origins'];
		foreach ( $existing as $allowed ) {
			if ( strcasecmp( $allowed, $origin ) === 0 ) {
				return;
			}
		}
		$existing[] = $origin;

		$wpdb->update( // phpcs:ignore WordPress.DB
			self::table_name(),
			[ 'allowed_origins' => wp_json_encode( $existing ) ],
			[ 'key_id' => $key_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public static function product_is_in_scope( array $row, int $product_id ): bool {
		if ( self::SCOPE_ALL === $row['product_scope'] ) {
			return true;
		}

		return in_array( $product_id, $row['product_ids'], true );
	}

	private static function hydrate( array $row ): array {
		$row['key_id']          = (int) $row['key_id'];
		$row['is_system']       = (int) $row['is_system'] === 1;
		$row['allowed_origins'] = self::decode_origins( $row['allowed_origins'] ?? '' );
		$row['product_ids']     = array_values( array_filter( array_map( 'absint', (array) json_decode( (string) ( $row['product_ids'] ?? '[]' ), true ) ) ) );

		return $row;
	}

	private static function decode_origins( string $json ): array {
		$decoded = json_decode( $json, true );

		return is_array( $decoded ) ? array_values( array_filter( array_map( [ self::class, 'normalize_origin' ], $decoded ) ) ) : [];
	}

	public static function sanitize_origins( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value );
		}

		$out = [];
		foreach ( (array) $value as $candidate ) {
			$candidate = self::normalize_origin( (string) $candidate );
			if ( $candidate ) {
				$out[] = $candidate;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalise an origin string for comparison against the allow-list.
	 *
	 * Accepts http(s) origins (browsers + Next.js / Nuxt storefronts) AND
	 * native-app schemes like `app://com.example.myapp` or
	 * `capacitor://localhost` (React Native, Capacitor, Cordova, Tauri,
	 * Electron). For non-http schemes the embed key itself is the auth
	 * boundary; the "origin" string is just a stable identifier the admin
	 * adds to the allow-list.
	 *
	 * Bare strings (no scheme) are coerced to https:// for back-compat.
	 */
	public static function normalize_origin( string $origin ): string {
		$origin = trim( $origin );
		if ( '' === $origin ) {
			return '';
		}

		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $origin ) ) {
			$origin = 'https://' . ltrim( $origin, '/' );
		}

		$parts = wp_parse_url( $origin );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme     = strtolower( $parts['scheme'] );
		$normalized = $scheme . '://' . strtolower( $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$normalized .= ':' . (int) $parts['port'];
		}

		return $normalized;
	}
}
