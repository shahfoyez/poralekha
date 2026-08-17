<?php

namespace StoreEngine\Addons\MultiVendor\Api;

use StoreEngine\Addons\MultiVendor\Settings as SettingsStore;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const NS = 'storeengine/v1';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( self::NS, '/multi-vendor/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'permission' ],
			],
		] );
	}

	public function permission(): bool {
		return current_user_can( 'manage_storeengine_vendor' );
	}

	public function get_settings() {
		return new WP_REST_Response( SettingsStore::all() );
	}

	public function save_settings( WP_REST_Request $request ) {
		// These are GLOBAL multi-vendor settings (commission rate, payout config).
		// permission() allows the vendor-held `manage_storeengine_vendor` cap so
		// vendors can READ them, but writing must require a real administrator —
		// otherwise a vendor could set their own commission rate.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You are not allowed to change these settings.', 'storeengine' ), [ 'status' => 403 ] );
		}

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}

		$clean = [];
		if ( isset( $payload['commission_rate'] ) ) {
			$clean['commission_rate'] = (float) $payload['commission_rate'];
		}
		if ( isset( $payload['commission_type'] ) && in_array( $payload['commission_type'], [ 'percent', 'flat' ], true ) ) {
			$clean['commission_type'] = $payload['commission_type'];
		}
		if ( isset( $payload['auto_create_owner'] ) ) {
			$clean['auto_create_owner'] = (bool) $payload['auto_create_owner'];
		}
		if ( isset( $payload['owner_visible'] ) ) {
			$clean['owner_visible'] = (bool) $payload['owner_visible'];
		}
		if ( isset( $payload['min_withdraw_amount'] ) ) {
			$clean['min_withdraw_amount'] = (float) $payload['min_withdraw_amount'];
		}
		if ( isset( $payload['allow_vendor_signup'] ) ) {
			$clean['allow_vendor_signup'] = (bool) $payload['allow_vendor_signup'];
		}
		if ( isset( $payload['enabled_payment_methods'] ) && is_array( $payload['enabled_payment_methods'] ) ) {
			$clean['enabled_payment_methods'] = array_values( array_filter( array_map( 'sanitize_key', $payload['enabled_payment_methods'] ) ) );
		}
		if ( isset( $payload['badges'] ) && is_array( $payload['badges'] ) ) {
			$badges = [];
			foreach ( $payload['badges'] as $b ) {
				if ( ! is_array( $b ) || empty( $b['slug'] ) ) {
					continue;
				}
				$badges[] = [
					'slug'  => sanitize_key( $b['slug'] ),
					'label' => sanitize_text_field( $b['label'] ?? $b['slug'] ),
					'color' => sanitize_hex_color( $b['color'] ?? '#64748b' ) ?: '#64748b',
				];
			}
			$clean['badges'] = $badges;
		}

		SettingsStore::save( $clean );

		return new WP_REST_Response( SettingsStore::all() );
	}
}
