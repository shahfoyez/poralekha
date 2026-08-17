<?php

namespace StoreEngine\Addons\Couriers\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Registry {

	/** @var array<string,ProviderInterface>|null */
	protected static ?array $cache = null;

	/**
	 * @return array<string,ProviderInterface>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) return self::$cache;

		// Providers are supplied by the storeengine-courier satellite (and any
		// third party) through this filter. The concrete integrations no longer
		// ship inside Pro — this addon owns only the courier framework.
		$providers = [];

		/**
		 * Allow third parties to register additional providers.
		 *
		 * @param array<string,ProviderInterface> $providers
		 */
		$providers = apply_filters( 'storeengine/couriers/providers', $providers );

		self::$cache = $providers;
		return $providers;
	}

	/**
	 * @return object|null A courier provider (framework or satellite-supplied).
	 *                     Duck-typed against ProviderInterface — the concrete
	 *                     class may live in the storeengine-courier satellite,
	 *                     which implements its own copy of the contract.
	 */
	public static function get( string $id ): ?object {
		$all = self::all();
		return $all[ $id ] ?? null;
	}
}
