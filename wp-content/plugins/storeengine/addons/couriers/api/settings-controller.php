<?php

namespace StoreEngine\Addons\Couriers\Api;

use StoreEngine\Addons\Couriers\Classes\AutoPush;
use StoreEngine\Addons\Couriers\Classes\OrderStatusSync;
use StoreEngine\Addons\Couriers\Classes\Registry;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsController {

	const NS  = 'storeengine/v1';
	const OPT = 'storeengine_courier_settings';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/couriers/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'fetch' ],
				'permission_callback' => [ __CLASS__, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'save' ],
				'permission_callback' => [ __CLASS__, 'permission' ],
			],
		] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_storeengine_settings' )
			|| current_user_can( 'manage_options' );
	}

	public static function fetch() {
		$saved = (array) get_option( self::OPT, [] );

		// Mask secret-typed fields when returning.
		$masked = [];
		foreach ( Registry::all() as $id => $provider ) {
			$cfg    = is_array( $saved[ $id ] ?? null ) ? $saved[ $id ] : [];
			$schema = $provider->settings_schema();
			$row    = [];
			foreach ( $schema as $field ) {
				$key   = $field['key'];
				$value = $cfg[ $key ] ?? '';
				if ( ( $field['type'] ?? '' ) === 'password' && $value ) {
					$value = '••••••••';
				}
				$row[ $key ] = $value;
			}
			$masked[ $id ] = $row;
		}

		return rest_ensure_response( [
			'providers' => $masked,
			'auto_push' => self::default_auto_push( AutoPush::get_auto_push_config() ),
			'sync'      => OrderStatusSync::get_sync_config(),
		] );
	}

	public static function save( WP_REST_Request $request ) {
		$body  = (array) $request->get_json_params();
		$saved = (array) get_option( self::OPT, [] );

		// Allow either flat (legacy) or { providers, auto_push } shape on input.
		$providers_in = isset( $body['providers'] ) && is_array( $body['providers'] )
			? $body['providers']
			: $body;

		foreach ( Registry::all() as $id => $provider ) {
			if ( ! isset( $providers_in[ $id ] ) || ! is_array( $providers_in[ $id ] ) ) continue;
			$incoming = $providers_in[ $id ];
			$existing = is_array( $saved[ $id ] ?? null ) ? $saved[ $id ] : [];
			$schema   = $provider->settings_schema();
			$next     = $existing;

			foreach ( $schema as $field ) {
				$key  = $field['key'];
				$type = $field['type'] ?? 'string';
				if ( ! array_key_exists( $key, $incoming ) ) continue;
				$value = $incoming[ $key ];
				// Skip masked-bullets so users can save without re-entering secrets.
				if ( 'password' === $type && '••••••••' === $value ) continue;

				if ( 'boolean' === $type ) {
					$next[ $key ] = (bool) $value;
				} else {
					$next[ $key ] = sanitize_text_field( (string) $value );
				}
			}

			$saved[ $id ] = $next;
		}

		update_option( self::OPT, $saved, false );

		// Auto-push config lives in the same option, separate top-level key.
		if ( isset( $body['auto_push'] ) && is_array( $body['auto_push'] ) ) {
			AutoPush::set_auto_push_config( $body['auto_push'] );
		}

		// Status-sync config (auto_complete_on_delivered, flip_on_return).
		if ( isset( $body['sync'] ) && is_array( $body['sync'] ) ) {
			OrderStatusSync::set_sync_config( $body['sync'] );
		}

		return self::fetch();
	}

	protected static function default_auto_push( array $cfg ): array {
		return [
			'enabled'           => (bool) ( $cfg['enabled'] ?? false ),
			'provider'          => (string) ( $cfg['provider'] ?? '' ),
			'default_weight_kg' => isset( $cfg['default_weight_kg'] ) ? (float) $cfg['default_weight_kg'] : 0.5,
			'use_cod_when'      => (string) ( $cfg['use_cod_when'] ?? 'cod_payment_method' ),
			'cod_methods'       => array_values( (array) ( $cfg['cod_methods'] ?? [] ) ),
		];
	}
}
