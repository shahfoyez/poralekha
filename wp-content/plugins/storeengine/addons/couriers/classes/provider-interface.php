<?php

namespace StoreEngine\Addons\Couriers\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Courier provider contract.
 *
 * Implementations live under addons/couriers/providers/. Each implementation
 * is a thin wrapper around the courier's HTTP API: sandbox by default,
 * configurable via the storeengine_courier_settings option.
 */
interface ProviderInterface {

	public function id(): string;

	public function label(): string;

	/**
	 * Per-provider config schema for the React settings page.
	 *
	 * @return array<int,array{key:string,label:string,type:string,help?:string,required?:bool}>
	 */
	public function settings_schema(): array;

	/**
	 * Push a shipment to the courier API. Returns the provider's
	 * tracking_id, label_url, etc.
	 *
	 * @param array $payload  order, customer, items, weight_kg, cod_amount
	 * @return array{ok:bool,tracking_id?:string,consignment_id?:string,label_url?:string,tracking_url?:string,raw?:array,errors?:array<string>}
	 */
	public function create_shipment( array $payload ): array;

	/**
	 * Poll the courier for current status of a shipment.
	 *
	 * @return array{ok:bool,status?:string,internal_status?:string,delivered?:bool,raw?:array,errors?:array<string>}
	 */
	public function check_status( string $tracking_id ): array;

	/**
	 * Cancel an outstanding shipment.
	 *
	 * @return array{ok:bool,errors?:array<string>}
	 */
	public function cancel( string $tracking_id ): array;
}
