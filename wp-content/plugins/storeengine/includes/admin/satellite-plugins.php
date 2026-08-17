<?php
/**
 * Satellite (free companion) plugin teasers.
 *
 * StoreEngine keeps the frameworks (payment-gateway registry, courier/dropship
 * frameworks) in core while the concrete providers ship in free companion
 * plugins — StoreEngine Payments and StoreEngine Connectors. When a companion
 * plugin is NOT active, its provider filters never run, so the admin has no way
 * to discover what those plugins unlock. This class supplies a small, filterable
 * catalog of "what you'd get" plus the plugin's install/download state, which
 * the React admin renders as an install-only teaser card. Once the companion
 * plugin is active, the teaser disappears and the real provider UI takes over.
 *
 * @package StoreEngine\Admin
 */

namespace StoreEngine\Admin;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SatellitePlugins {

	/**
	 * StoreEngine Payments — plugin basename + store product id + free download URL.
	 */
	const PAYMENTS_BASENAME     = 'storeengine-payments/storeengine-payments.php';
	const PAYMENTS_PRODUCT_ID   = 369;
	const PAYMENTS_DOWNLOAD_URL  = 'https://store.kodezen.com/se-download/free/storeengine-payments/latest/';

	/**
	 * StoreEngine Connectors — plugin basename + store product id + free download URL.
	 */
	const CONNECTORS_BASENAME     = 'storeengine-connectors/storeengine-connectors.php';
	const CONNECTORS_PRODUCT_ID   = 386;
	const CONNECTORS_DOWNLOAD_URL = 'https://store.kodezen.com/se-download/free/storeengine-connectors/latest/';

	/**
	 * StoreEngine Bricks Addons — page-builder companion (no provider catalog).
	 */
	const BRICKS_BASENAME     = 'storeengine-bricks-addons/storeengine-bricks-addons.php';
	const BRICKS_PRODUCT_ID   = 171;
	const BRICKS_DOWNLOAD_URL = 'https://store.kodezen.com/se-download/free/storeengine-bricks-addons/latest/';

	/**
	 * StoreEngine Elementor Addons — page-builder companion (no provider catalog).
	 */
	const ELEMENTOR_BASENAME     = 'storeengine-elementor-addons/storeengine-elementor-addons.php';
	const ELEMENTOR_PRODUCT_ID   = 160;
	const ELEMENTOR_DOWNLOAD_URL = 'https://store.kodezen.com/se-download/free/storeengine-elementor-addons/latest/';

	/**
	 * Static metadata for every external ("satellite") companion plugin that the
	 * Add-ons screen can offer to download / one-click install. Live install
	 * state (active/installed) is layered on in get_teaser_data().
	 *
	 * `kind`:
	 *   'satellite' — registers providers into StoreEngine core (Payments,
	 *                 Connectors); ships an `items` catalog of what it unlocks
	 *                 and a `settings` route to manage it once active.
	 *   'plugin'    — self-contained page-builder companion (Bricks, Elementor);
	 *                 no in-core catalog, links out to its docs instead.
	 *
	 * @return array<string, array>
	 */
	protected static function definitions(): array {
		return [
			'payments'         => [
				'kind'         => 'satellite',
				'basename'     => self::PAYMENTS_BASENAME,
				'name'         => __( 'StoreEngine Payments', 'storeengine' ),
				'product_id'   => self::PAYMENTS_PRODUCT_ID,
				'download_url' => self::payments_download_url(),
				'details'      => __( 'Extra payment gateways — Paystack, Square and more — behind one lightweight companion plugin.', 'storeengine' ),
				'icon'         => 'money-receive',
				'color'        => '#5a6ff0',
				'category'     => 'payments',
				'docs_url'     => '',
				'requires'     => '',
				// Where the "Manage" action jumps once the plugin is active.
				'settings'     => [ 'page' => 'storeengine-settings', 'path' => 'payment-method' ],
				'items'        => [
					[
						'group' => __( 'Payment gateways', 'storeengine' ),
						'list'  => self::payment_methods_catalog(),
					],
				],
			],
			'connectors'       => [
				'kind'         => 'satellite',
				'basename'     => self::CONNECTORS_BASENAME,
				'name'         => __( 'StoreEngine Connectors', 'storeengine' ),
				'product_id'   => self::CONNECTORS_PRODUCT_ID,
				'download_url' => self::connectors_download_url(),
				'details'      => __( 'Courier / shipping partners and dropshipping suppliers, ready to connect.', 'storeengine' ),
				'icon'         => 'shipping',
				'color'        => '#0ea5a3',
				'category'     => 'shipping',
				'docs_url'     => '',
				'requires'     => '',
				'settings'     => [ 'page' => 'storeengine-settings', 'path' => 'couriers' ],
				'items'        => [
					[
						'group' => __( 'Courier & shipping partners', 'storeengine' ),
						'list'  => self::courier_catalog(),
					],
					[
						'group' => __( 'Dropshipping suppliers', 'storeengine' ),
						'list'  => self::dropship_catalog(),
					],
				],
			],
			'bricks-addons'    => [
				'kind'         => 'plugin',
				'basename'     => self::BRICKS_BASENAME,
				'name'         => __( 'StoreEngine Bricks Addons', 'storeengine' ),
				'product_id'   => self::BRICKS_PRODUCT_ID,
				'download_url' => self::bricks_download_url(),
				'details'      => __( 'StoreEngine elements for the Bricks site builder.', 'storeengine' ),
				'icon'         => 'store',
				'color'        => '#f0663a',
				'category'     => 'tools',
				'docs_url'     => 'https://store.kodezen.com/product/storeengine-bricks-addons/',
				'requires'     => __( 'Bricks Builder', 'storeengine' ),
				'settings'     => null,
				'items'        => [],
			],
			'elementor-addons' => [
				'kind'         => 'plugin',
				'basename'     => self::ELEMENTOR_BASENAME,
				'name'         => __( 'StoreEngine Elementor Addons', 'storeengine' ),
				'product_id'   => self::ELEMENTOR_PRODUCT_ID,
				'download_url' => self::elementor_download_url(),
				'details'      => __( 'StoreEngine widgets for the Elementor page builder.', 'storeengine' ),
				'icon'         => 'store',
				'color'        => '#e0295a',
				'category'     => 'tools',
				'docs_url'     => 'https://store.kodezen.com/product/storeengine-elementor-addons/',
				'requires'     => __( 'Elementor', 'storeengine' ),
				'settings'     => null,
				'items'        => [],
			],
		];
	}

	/**
	 * Companion plugin keys accepted by install_and_activate() → their install
	 * metadata (basename, download URL, display name). Central so the ajax
	 * endpoint never trusts a client-supplied URL — the key is the only input.
	 *
	 * @return array<string, array{basename:string, url:string, name:string}>
	 */
	protected static function registry(): array {
		$registry = [];
		foreach ( self::definitions() as $key => $def ) {
			$registry[ $key ] = [
				'basename' => $def['basename'],
				'url'      => $def['download_url'],
				'name'     => $def['name'],
			];
		}

		return $registry;
	}

	/**
	 * Valid companion keys — the only accepted input to the install endpoint.
	 *
	 * @return string[]
	 */
	public static function get_keys(): array {
		return array_keys( self::definitions() );
	}

	/**
	 * Where the "Install StoreEngine Payments" teaser downloads from. Filterable
	 * so a site can repoint it (e.g. to a future WordPress.org listing).
	 */
	public static function payments_download_url(): string {
		return apply_filters( 'storeengine/satellite/payments_download_url', self::PAYMENTS_DOWNLOAD_URL );
	}

	/**
	 * Where the "Install StoreEngine Connectors" teaser downloads from. Filterable.
	 */
	public static function connectors_download_url(): string {
		return apply_filters( 'storeengine/satellite/connectors_download_url', self::CONNECTORS_DOWNLOAD_URL );
	}

	/**
	 * Where the "Install StoreEngine Bricks Addons" teaser downloads from. Filterable.
	 */
	public static function bricks_download_url(): string {
		return apply_filters( 'storeengine/satellite/bricks_download_url', self::BRICKS_DOWNLOAD_URL );
	}

	/**
	 * Where the "Install StoreEngine Elementor Addons" teaser downloads from. Filterable.
	 */
	public static function elementor_download_url(): string {
		return apply_filters( 'storeengine/satellite/elementor_download_url', self::ELEMENTOR_DOWNLOAD_URL );
	}

	/**
	 * Teaser payload for every companion plugin, localized into
	 * `StoreEngineGlobal.satellite_plugins` for the React admin. Each entry is
	 * the static definition plus live `active` / `installed` state and its `key`.
	 *
	 * @return array<string, array>
	 */
	public static function get_teaser_data(): array {
		$data = [];
		foreach ( self::definitions() as $key => $def ) {
			$data[ $key ] = array_merge(
				$def,
				[
					'key'       => $key,
					'active'    => self::is_plugin_active( $def['basename'] ),
					'installed' => self::is_plugin_installed( $def['basename'] ),
				]
			);
		}

		/**
		 * Filter the full satellite-plugin teaser payload.
		 *
		 * @param array $data Teaser data keyed by plugin.
		 */
		return apply_filters( 'storeengine/admin/satellite_plugins', $data );
	}

	/**
	 * Download, install, and activate a companion plugin from the StoreEngine
	 * store — the one-click path behind the teaser button.
	 *
	 * The plugin is resolved from $key against the server-side registry(), so the
	 * package URL is never taken from the client. Idempotent: a plugin already
	 * active short-circuits; installed-but-inactive is just activated; otherwise
	 * it is downloaded from the free store URL and activated.
	 *
	 * @param string $key 'payments' | 'connectors'.
	 *
	 * @return array{status:string, message:string, plugin:string}|\WP_Error
	 */
	public static function install_and_activate( string $key ) {
		$registry = self::registry();
		if ( ! isset( $registry[ $key ] ) ) {
			return new \WP_Error( 'storeengine_unknown_satellite', __( 'Unknown plugin.', 'storeengine' ), [ 'status' => 400 ] );
		}

		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			return new \WP_Error( 'storeengine_cannot_install', __( 'You do not have permission to install plugins.', 'storeengine' ), [ 'status' => 403 ] );
		}

		$basename = $registry[ $key ]['basename'];
		$url      = $registry[ $key ]['url'];
		$name     = $registry[ $key ]['name'];

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		// Already active — nothing to do.
		if ( self::is_plugin_active( $basename ) ) {
			return [ 'status' => 'active', 'plugin' => $key, 'message' => sprintf( /* translators: %s: plugin name. */ __( '%s is already active.', 'storeengine' ), $name ) ];
		}

		// Not installed yet → download + install from the free store URL.
		if ( ! self::is_plugin_installed( $basename ) ) {
			if ( ! WP_Filesystem() ) {
				return new \WP_Error( 'storeengine_fs_unavailable', __( 'WordPress could not access the filesystem to install the plugin. Please download and install it manually.', 'storeengine' ), [ 'status' => 500 ] );
			}

			$skin     = new \Automatic_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $url );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( true !== $result ) {
				$messages = method_exists( $skin, 'get_errors' ) && is_wp_error( $skin->get_errors() ) ? $skin->get_errors()->get_error_message() : '';
				return new \WP_Error(
					'storeengine_install_failed',
					$messages ? $messages : __( 'The plugin could not be installed. Please download and install it manually.', 'storeengine' ),
					[ 'status' => 500 ]
				);
			}

			// The upgrader wrote new files — refresh the plugin list before activating.
			wp_clean_plugins_cache();
		}

		if ( ! self::is_plugin_installed( $basename ) ) {
			return new \WP_Error( 'storeengine_install_missing', __( 'The plugin was downloaded but its main file was not found. Please install it manually.', 'storeengine' ), [ 'status' => 500 ] );
		}

		$activated = activate_plugin( $basename );
		if ( is_wp_error( $activated ) ) {
			return $activated;
		}

		return [
			'status'  => 'installed',
			'plugin'  => $key,
			'message' => sprintf( /* translators: %s: plugin name. */ __( '%s installed and activated.', 'storeengine' ), $name ),
		];
	}

	/**
	 * Providers unlocked by StoreEngine Payments. Kept in sync with the plugin's
	 * `src/providers.php`; filterable so the plugin (or a site) can extend it.
	 *
	 * @return array<int, array{label:string, details:string}>
	 */
	protected static function payment_methods_catalog(): array {
		return apply_filters( 'storeengine/admin/satellite_payments_catalog', [
			[
				'label'   => 'Paystack',
				'details' => __( 'Card, bank transfer, USSD and mobile-money payments across Africa.', 'storeengine' ),
			],
			[
				'label'   => 'Square',
				'details' => __( 'Card payments via the Square Web Payments SDK — card data never touches your server.', 'storeengine' ),
			],
		] );
	}

	/**
	 * Courier / shipping partners unlocked by StoreEngine Connectors.
	 *
	 * @return array<int, array{label:string, details:string}>
	 */
	protected static function courier_catalog(): array {
		return apply_filters( 'storeengine/admin/satellite_courier_catalog', [
			[
				'label'   => 'Pathao',
				'details' => __( 'Push orders and auto-poll delivery status.', 'storeengine' ),
			],
			[
				'label'   => 'Steadfast',
				'details' => __( 'One-click consignment creation and tracking sync.', 'storeengine' ),
			],
			[
				'label'   => 'Shiprocket',
				'details' => __( 'Multi-carrier shipping and tracking.', 'storeengine' ),
			],
		] );
	}

	/**
	 * Dropshipping suppliers unlocked by StoreEngine Connectors.
	 *
	 * @return array<int, array{label:string, details:string}>
	 */
	protected static function dropship_catalog(): array {
		return apply_filters( 'storeengine/admin/satellite_dropship_catalog', [
			[
				'label'   => 'AliExpress',
				'details' => __( 'Import products and sync inventory from the marketplace.', 'storeengine' ),
			],
			[
				'label'   => 'CJ Dropshipping',
				'details' => __( 'Source and fulfil from the CJ supplier network.', 'storeengine' ),
			],
			[
				'label'   => 'Spocket',
				'details' => __( 'Curated US/EU suppliers with fast shipping.', 'storeengine' ),
			],
			[
				'label'   => 'Zendrop',
				'details' => __( 'Curated US suppliers and auto-fulfilment.', 'storeengine' ),
			],
			[
				'label'   => 'Printful',
				'details' => __( 'Print-on-demand products and fulfilment.', 'storeengine' ),
			],
			[
				'label'   => 'Printify',
				'details' => __( 'Print-on-demand catalog across multiple providers.', 'storeengine' ),
			],
		] );
	}

	protected static function is_plugin_active( string $basename ): bool {
		if ( method_exists( Helper::class, 'is_plugin_active' ) ) {
			return Helper::is_plugin_active( $basename );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $basename );
	}

	protected static function is_plugin_installed( string $basename ): bool {
		if ( method_exists( Helper::class, 'is_plugin_installed' ) ) {
			return (bool) Helper::is_plugin_installed( $basename );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array_key_exists( $basename, get_plugins() );
	}
}
