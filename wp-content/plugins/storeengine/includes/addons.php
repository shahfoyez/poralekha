<?php

namespace StoreEngine;

use StoreEngine\Admin\Settings;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Addons {

	/**
	 * Single option holding every addon's installed DB schema version, as a
	 * map of `addon-slug => version`. The central manager compares each active
	 * addon's get_db_version() against this and reruns install_tables() on a
	 * mismatch — no per-addon option or per-addon `init` hook needed.
	 */
	const DB_VERSION_OPTION = 'storeengine_addons_db_version';

	/**
	 * Active addon instances collected during loading, keyed by slug.
	 *
	 * @var AbstractAddon[]
	 */
	private array $loaded_addons = [];

	public static function init() {
		$self = new self();
		// Load all addons
		$self->addons_loader();
	}

	private function addons_loader() {
		$addons = apply_filters( 'storeengine/addons/loader_args', [
			'paypal'         => 'Paypal',
			'stripe'         => 'Stripe',
			'affiliate'      => 'Affiliate',
			'membership'     => 'Membership',
			'subscription'   => 'Subscription',
			'invoice'        => 'Invoice',
			'email'          => 'Email',
			'webhooks'       => 'Webhooks',
			'migration-tool' => 'MigrationTool',
			'csv'            => 'Csv',
			'catalog-mode'   => 'CatalogMode',
			'razorpay'       => 'Razorpay',
			// 'paystack' moved to the storeengine-payments plugin.
			'paddle'         => 'Paddle',
			'multi-currency'         => 'MultiCurrency',
			'eu-vat'         		=> 'EuVat',
			'multi-currency'      => 'MultiCurrency',
			'instant-checkout'    => 'InstantCheckout',
			'embeddable-checkout' => 'EmbeddableCheckout',
			'inventory'           => 'Inventory',
			// Courier framework (shipment model, status sync, Shipments admin).
			// Third-party provider integrations ship in the storeengine-courier
			// satellite and register via the `storeengine/couriers/providers`
			// filter — same split as payment gateways / storeengine-payments.
			'couriers'            => 'Couriers',
			'multi-vendor'        => 'MultiVendor',
			'eu-compliance'       => 'EuCompliance',
			'seo'                 => 'Seo',
			'ai'                  => 'Ai',
			'gdpr'                => 'Gdpr',
			'seeder'              => 'Seeder',
			'brand'               => 'Brand',
			'funnel-builder'      => 'FunnelBuilder',
		] );

		foreach ( $addons as $addon_name => $addon_class_name ) {
			$addon_root_path = STOREENGINE_ADDONS_DIR_PATH . $addon_name . '/';
			$addon_namespace = 'StoreEngine\\Addons\\' . $addon_class_name;

			// Register the addon's root namespace and path.
			Autoload::get_instance()->add_namespace_directory( $addon_namespace, $addon_root_path );

			/**
			 * Initialize the addon's main class.
			 *
			 * @var AbstractAddon $class Addon's main class.
			 */
			$class = $addon_namespace . '\\' . $addon_class_name;
			$class = $class::init();
			$class->run();

			// Only active addons participate in schema syncing.
			if ( Helper::get_addon_active_status( $addon_name ) ) {
				$this->loaded_addons[ $addon_name ] = $class;
			}
		}

		// Single hook drives table creation/migration for every active addon.
		add_action( 'init', [ $this, 'sync_addon_schemas' ], 4 );

		do_action( 'storeengine/addons/loaded' );
	}

	/**
	 * Sync the schema of every active core addon loaded this request.
	 */
	public function sync_addon_schemas(): void {
		self::sync_schema_for( $this->loaded_addons );
	}

	/**
	 * Compare each addon's declared DB version against the stored map and
	 * rerun install_tables() where they differ. Writes the map once at the end.
	 *
	 * Reusable so other loaders (e.g. storeengine-pro) can sync their own
	 * addon instances into the same `storeengine_addons_db_version` option.
	 *
	 * @param AbstractAddon[] $addons Addon instances keyed by slug.
	 */
	public static function sync_schema_for( array $addons ): void {
		$stored  = (array) get_option( self::DB_VERSION_OPTION, [] );
		$changed = false;

		foreach ( $addons as $slug => $addon ) {
			if ( ! $addon instanceof AbstractAddon ) {
				continue;
			}

			$declared = $addon->get_db_version();
			if ( '' === $declared ) {
				// Addon has no tables of its own.
				continue;
			}

			if ( isset( $stored[ $slug ] ) && $stored[ $slug ] === $declared ) {
				// Already up to date.
				continue;
			}

			$addon->install_tables();
			$stored[ $slug ] = $declared;
			$changed         = true;
		}

		if ( $changed ) {
			update_option( self::DB_VERSION_OPTION, $stored, true );
		}
	}

	/**
	 * Hard inter-addon dependency map.
	 *
	 * Activating an addon requires every slug in its list to already be active.
	 * Deactivating an addon is blocked while anything that lists it here is
	 * still active. Filter `storeengine/addons/dependencies` lets pro plugins
	 * extend the map without editing core.
	 */
	public static function get_dependencies(): array {
		return apply_filters( 'storeengine/addons/dependencies', [
			'inventory-pro' => [ 'inventory' ],
			// POS only hard-requires inventory. inventory-pro is a soft
			// dependency — when active, registers can be bound to locations
			// and stock is decremented per location; when inactive, POS
			// falls back to core's global StockManager.
			'pos'           => [ 'inventory' ],
			'returns'       => [ 'inventory' ],
		] );
	}

	/**
	 * Slugs that currently depend on $addon_slug AND are themselves active.
	 */
	public static function get_active_dependents( string $addon_slug ): array {
		global $storeengine_addons;
		$active     = (array) $storeengine_addons;
		$dependents = [];
		foreach ( self::get_dependencies() as $consumer => $requires ) {
			if ( ! in_array( $addon_slug, $requires, true ) ) {
				continue;
			}
			if ( ! empty( $active[ $consumer ] ) ) {
				$dependents[] = $consumer;
			}
		}
		return $dependents;
	}

	/**
	 * Slugs that $addon_slug requires but are NOT currently active.
	 */
	public static function get_missing_requirements( string $addon_slug ): array {
		global $storeengine_addons;
		$active   = (array) $storeengine_addons;
		$required = self::get_dependencies()[ $addon_slug ] ?? [];
		$missing  = [];
		foreach ( $required as $req ) {
			if ( empty( $active[ $req ] ) ) {
				$missing[] = $req;
			}
		}
		return $missing;
	}

	public static function activate( string $addon_slug ) {
		global $storeengine_addons;
		if ( ! isset( $storeengine_addons ) ) {
			Settings::load_settings();
		}

		$data = (array) $storeengine_addons;

		if ( array_key_exists( $addon_slug, $data ) ) {
			if ( $data[$addon_slug] ) {
				// translators: %s: Addon slug.
				return new WP_Error( 'addon_already_activated', sprintf( __( 'Addon %s already activated', 'storeengine' ), $addon_slug ) );
			}
		}

		$storeengine_addons->{$addon_slug} = true;

		update_option( STOREENGINE_ADDONS_SETTINGS_NAME, wp_json_encode( $storeengine_addons ) );

		do_action( "storeengine/addons/activated_{$addon_slug}" );

		return true;
	}

	public static function deactivate( string $addon_slug ) {
		global $storeengine_addons;
		if ( ! isset( $storeengine_addons ) ) {
			Settings::load_settings();
		}

		$data = (array) $storeengine_addons;

		if ( array_key_exists( $addon_slug, $data ) ) {
			if ( !$data[$addon_slug] ) {
				// translators: %s: Addon slug.
				return new WP_Error( 'addon_already_deactivated', sprintf( __( 'Addon %s already deactivated', 'storeengine' ), $addon_slug ) );
			}
		}

		$storeengine_addons->{$addon_slug} = false;

		update_option( STOREENGINE_ADDONS_SETTINGS_NAME, wp_json_encode( $storeengine_addons ) );

		do_action( "storeengine/addons/deactivated_{$addon_slug}" );

		return true;
	}
}
