<?php
/**
 * Persistent OAuth token store for courier providers.
 *
 * Holds one token record per provider id in a single, non-autoloaded option.
 * Used by the 3-legged (authorization_code) flow, where the refresh token must
 * survive across requests so access tokens can be silently renewed. The 2-legged
 * (client_credentials / password) flow keeps its short-lived tokens in a
 * transient instead — it can always re-mint from stored credentials, so nothing
 * needs persisting here.
 *
 * @package StoreEngine\Addons\Couriers
 */

namespace StoreEngine\Addons\Couriers\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OAuthTokenStore {

	const OPTION = 'storeengine_courier_oauth_tokens';

	/**
	 * Token record for a provider.
	 *
	 * @return array{access_token?:string,refresh_token?:string,expires_at?:int,token_type?:string,scope?:string,obtained_at?:int}
	 */
	public static function get( string $provider_id ): array {
		$all = (array) get_option( self::OPTION, [] );
		$row = $all[ $provider_id ] ?? [];
		return is_array( $row ) ? $row : [];
	}

	/**
	 * Merge-save a token record for a provider. Only the keys passed are
	 * updated, so a refresh that returns no new refresh_token keeps the old one.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function save( string $provider_id, array $data ): void {
		$all = (array) get_option( self::OPTION, [] );
		$row = is_array( $all[ $provider_id ] ?? null ) ? $all[ $provider_id ] : [];

		$all[ $provider_id ] = array_merge( $row, $data );

		// Non-autoloaded — these are secrets and only read on courier requests.
		update_option( self::OPTION, $all, false );
	}

	public static function clear( string $provider_id ): void {
		$all = (array) get_option( self::OPTION, [] );
		if ( isset( $all[ $provider_id ] ) ) {
			unset( $all[ $provider_id ] );
			update_option( self::OPTION, $all, false );
		}
	}

	public static function has_access_token( string $provider_id ): bool {
		$row = self::get( $provider_id );
		return ! empty( $row['access_token'] );
	}
}
