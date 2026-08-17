<?php
/**
 * EU VAT Ajax
 *
 * Endpoints
 * ─────────
 * POST storeengine_action / eu_vat/validate
 *   Validates a VAT number against VIES (or HMRC for GB), and stores the
 *   validated result on the customer session + recent draft order so that
 *   subsequent cart/tax recalculations apply the exemption.
 *   Public (no login required).
 *
 * POST storeengine_action / eu_vat/save_settings
 *   Saves admin settings.
 *   Requires manage_options capability.
 *
 * POST storeengine_action / eu_vat/get_order_vat
 *   Returns the VAT number stored on an order. Admin order editor only.
 *   Requires manage_options capability.
 *
 * POST storeengine_action / eu_vat/save_order_vat
 *   Writes the VAT number onto an order (covers guest orders that had no VAT
 *   at checkout). Admin order editor only. Requires manage_options capability.
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat\Classes;

use StoreEngine;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\AbstractRequestHandler;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [

			'eu_vat/validate' => [
				'callback'             => [ $this, 'validate' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'vat_number'      => AbstractRequestHandler::STRING,
					'billing_country' => AbstractRequestHandler::STRING,
					'billing_company' => AbstractRequestHandler::STRING,
				],
			],

			'eu_vat/save_settings' => [
				'callback'   => [ $this, 'save_settings' ],
				'capability' => 'manage_options',
				'fields'     => [
					'field_label'              => AbstractRequestHandler::STRING,
					'field_placeholder'        => AbstractRequestHandler::STRING,
					'field_description'        => AbstractRequestHandler::STRING,
					'field_required'           => AbstractRequestHandler::STRING,
					'preserve_in_base_country' => AbstractRequestHandler::STRING,
					'preserve_countries'       => [ AbstractRequestHandler::STRING ],
					'messages'                 => [
						'validating'        => AbstractRequestHandler::STRING,
						'valid'             => AbstractRequestHandler::STRING,
						'invalid'           => AbstractRequestHandler::STRING,
						'validation_failed' => AbstractRequestHandler::STRING,
					],
					'debug_logging'            => AbstractRequestHandler::STRING,
				],
			],

			'eu_vat/get_order_vat' => [
				'callback'   => [ $this, 'get_order_vat' ],
				'capability' => 'manage_options',
				'fields'     => [
					'order_id' => AbstractRequestHandler::STRING,
				],
			],

			'eu_vat/save_order_vat' => [
				'callback'   => [ $this, 'save_order_vat' ],
				'capability' => 'manage_options',
				'fields'     => [
					'order_id'   => AbstractRequestHandler::STRING,
					'vat_number' => AbstractRequestHandler::STRING,
				],
			],
		];
	}

	/**
	 * Return the VAT number stored on an order, for the admin order editor.
	 */
	public function get_order_vat( array $payload ): void {
		$order = Helper::get_order( (int) ( $payload['order_id'] ?? 0 ) );
		if ( ! $order || is_wp_error( $order ) ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'storeengine' ) ] );
		}

		wp_send_json_success( [
			'vat_number' => (string) $order->get_meta( '_billing_eu_vat_number' ),
		] );
	}

	/**
	 * Write a VAT number onto an order. Covers guest orders that had no VAT
	 * captured at checkout, so it can appear on the (regenerated) invoice.
	 */
	public function save_order_vat( array $payload ): void {
		$order = Helper::get_order( (int) ( $payload['order_id'] ?? 0 ) );
		if ( ! $order || is_wp_error( $order ) ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'storeengine' ) ] );
		}

		$raw = (string) ( $payload['vat_number'] ?? '' );
		$vat = '' === trim( $raw ) ? '' : \StoreEngine\Addons\EuVat\normalize_vat_input( $raw );

		$order->update_meta_data( '_billing_eu_vat_number', $vat );
		$order->save();

		wp_send_json_success( [ 'vat_number' => $vat ] );
	}

	public function validate( array $payload ): void {
		$raw     = (string) ( $payload['vat_number'] ?? '' );
		$country = strtoupper( (string) ( $payload['billing_country'] ?? '' ) );

		$vat = VatNumber::parse( $raw, $country );
		if ( ! $vat ) {
			wp_send_json_success( [
				'valid'   => false,
				'state'   => 'invalid_format',
				'message' => Settings::get( 'messages' )['invalid'] ?? '',
			] );
		}

		$result = ( new ViesValidator() )->validate( $vat );

		$messages = (array) Settings::get( 'messages', [] );

		if ( null !== $result['error'] ) {
			$this->log( 'validation_failed', $result );
			wp_send_json_success( [
				'valid'   => false,
				'state'   => 'validation_failed',
				'message' => $messages['validation_failed'] ?? '',
				'detail'  => $result['error'],
			] );
		}

		if ( ! $result['valid'] ) {
			$this->apply_to_session( null, false );
			$this->apply_to_draft_order( null, false, null );
			wp_send_json_success( [
				'valid'   => false,
				'state'   => 'invalid',
				'message' => $messages['invalid'] ?? '',
			] );
		}

		$exempt = $this->should_exempt( $vat->country );

		$this->apply_to_session( $vat->full(), $exempt );
		$this->apply_to_draft_order( $vat->full(), $exempt, $result );

		wp_send_json_success( [
			'valid'    => true,
			'state'    => 'valid',
			'message'  => $messages['valid'] ?? '',
			'exempt'   => $exempt,
			'company'  => $result['name'],
		] );
	}

	public function save_settings( array $payload ): void {
		Settings::update( [
			'field_label'              => sanitize_text_field( $payload['field_label'] ?? '' ),
			'field_placeholder'        => sanitize_text_field( $payload['field_placeholder'] ?? '' ),
			'field_description'        => wp_kses_post( $payload['field_description'] ?? '' ),
			'field_required'           => $this->sanitize_required( $payload['field_required'] ?? 'optional' ),
			'preserve_in_base_country' => 'yes' === ( $payload['preserve_in_base_country'] ?? 'yes' ) ? 'yes' : 'no',
			'preserve_countries'       => array_map( 'sanitize_text_field', (array) ( $payload['preserve_countries'] ?? [] ) ),
			'messages'                 => array_map( 'sanitize_text_field', (array) ( $payload['messages'] ?? [] ) ),
			'debug_logging'            => 'yes' === ( $payload['debug_logging'] ?? 'no' ) ? 'yes' : 'no',
		] );

		wp_send_json_success( [ 'settings' => Settings::all() ] );
	}

	private function sanitize_required( string $value ): string {
		return in_array( $value, [ 'optional', 'required', 'required_if_company' ], true ) ? $value : 'optional';
	}

	/**
	 * Decide whether to exempt VAT for a given VAT country, given store settings.
	 *
	 * Rules:
	 *   - Always exempt unless the VAT country matches the store base country
	 *     and "preserve_in_base_country" is "yes".
	 *   - Or the VAT country is in the explicit preserve list.
	 */
	private function should_exempt( string $vat_country ): bool {
		global $storeengine_settings;
		$base = isset( $storeengine_settings->store_country ) ? strtoupper( (string) $storeengine_settings->store_country ) : '';

		if ( 'yes' === Settings::get( 'preserve_in_base_country', 'yes' ) ) {
			$base_vat = \StoreEngine\Addons\EuVat\vat_country_code( $base );
			if ( $base_vat === $vat_country ) {
				return false;
			}
		}

		$preserve = (array) Settings::get( 'preserve_countries', [] );
		$preserve = array_map( fn( $c ) => \StoreEngine\Addons\EuVat\vat_country_code( strtoupper( (string) $c ) ), $preserve );
		if ( in_array( $vat_country, $preserve, true ) ) {
			return false;
		}

		return true;
	}

	private function apply_to_session( ?string $vat_full, bool $exempt ): void {
		$cart = StoreEngine::init()->get_cart();
		if ( ! $cart ) {
			return;
		}

		$customer = $cart->get_customer();
		if ( ! $customer ) {
			return;
		}

		$customer->set_is_vat_exempt( $exempt );
	}

	private function apply_to_draft_order( ?string $vat_full, bool $exempt, ?array $result ): void {
		$order = Helper::get_recent_draft_order();
		if ( ! $order ) {
			return;
		}

		if ( null === $vat_full ) {
			$order->update_meta_data( '_billing_eu_vat_number', '' );
			$order->update_meta_data( 'is_vat_exempt', 'no' );
		} else {
			$order->update_meta_data( '_billing_eu_vat_number', $vat_full );
			$order->update_meta_data( 'is_vat_exempt', $exempt ? 'yes' : 'no' );
			if ( $result ) {
				$order->update_meta_data( '_eu_vat_validation_response', wp_json_encode( $result ) );
			}
		}

		$order->save();
	}

	private function log( string $event, array $context ): void {
		if ( 'yes' !== Settings::get( 'debug_logging', 'no' ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Opt-in diagnostic, only runs when the EU-VAT debug_logging setting is enabled.
		error_log( sprintf( '[storeengine eu-vat] %s %s', $event, wp_json_encode( $context ) ) );
	}
}
