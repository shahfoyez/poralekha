<?php
/**
 * Seeder addon (free).
 *
 * Dummy-data generator for quick local testing — toggleable so it loads ZERO
 * code when disabled (the intended state on production). When active it exposes
 * the `wp storeengine seed` CLI command and a Tools → StoreEngine Seeder admin
 * page, and registers the core + free-addon data providers.
 *
 * Pro data sets ship in the separate `seeder-pro` addon, which hangs its
 * providers off the same `storeengine/seeder/providers` filter.
 *
 * @package StoreEngine\Addons\Seeder
 */

namespace StoreEngine\Addons\Seeder;

use StoreEngine\Addons\Seeder\Classes\Ajax;
use StoreEngine\Addons\Seeder\Classes\Cli;
use StoreEngine\Admin\Notices;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Seeder extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'seeder';

	/**
	 * Runs for every addon at load (active or not), so the Add-ons card is
	 * surfaced even while the seeder is disabled — that's how you toggle it on
	 * from StoreEngine → Add-ons. The framework itself still only loads in
	 * init_addon() when active. Registering one filter is negligible.
	 */
	const ENABLE_ACTION = 'storeengine_seeder_enable';
	const NAG_NOTICE    = 'storeengine_seeder_nag';

	/**
	 * StoreEngine → Tools "Dummy Data" tab, where the seeder UI lives.
	 */
	private function tools_tab_url(): string {
		return admin_url( 'admin.php?page=storeengine-tools&path=dummy-data' );
	}

	protected function __construct() {
		add_filter( 'storeengine/admin/extra_addons', [ $this, 'register_addon_card' ] );

		// One-click "Insert dummy data" invitation + its handler. Both live here
		// (not in init_addon) because this constructor runs even while the addon
		// is disabled — that's exactly when we want to invite turning it on.
		add_action( 'admin_init', [ $this, 'handle_enable_request' ], 5 );
		add_action( 'admin_init', [ $this, 'register_nag' ], 10 );
	}

	/**
	 * Handle a click on the "Insert dummy data" notice button: enable the seeder
	 * addon and drop the user straight onto the StoreEngine → Tools → Dummy Data
	 * tab, where they pick data sets and click Seed (and can Reset later).
	 */
	public function handle_enable_request(): void {
		if ( empty( $_GET[ self::ENABLE_ACTION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'storeengine' ) );
		}

		check_admin_referer( self::ENABLE_ACTION );

		if ( ! Helper::get_addon_active_status( 'seeder' ) ) {
			\StoreEngine\Addons::activate( 'seeder' );
		}

		// Don't invite again for this user now that they've opted in.
		update_user_meta( get_current_user_id(), 'dismissed_' . self::NAG_NOTICE . '_notice', 'yes' );

		wp_safe_redirect( $this->tools_tab_url() );
		exit;
	}

	/**
	 * Show a dismissible "try it with sample data" notice while the seeder is off.
	 * Once enabled (or dismissed) it stops — the Add-ons card + Tools page take
	 * over from there.
	 */
	public function register_nag(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Nothing to invite once it's already on. Otherwise show it once —
		// the user can dismiss it if they're not interested.
		if ( Helper::get_addon_active_status( 'seeder' ) ) {
			return;
		}

		$enable_url = wp_nonce_url(
			add_query_arg( self::ENABLE_ACTION, '1', admin_url( 'admin.php?page=storeengine' ) ),
			self::ENABLE_ACTION
		);

		Notices::add_notice( self::NAG_NOTICE, [
			'type'          => Notices::TYPE_INFO,
			'dismissible'   => true,
			'message'       => __( '<h3>Populate your store with sample data 🧪</h3><p>Generate realistic demo customers, products, orders and a sales funnel in one click — perfect for testing and demos. Development use only; remove it anytime.</p>', 'storeengine' ),
			'button_text'   => __( 'Insert dummy data', 'storeengine' ),
			'button_action' => $enable_url,
		] );
	}

	/**
	 * The single Add-ons card. Its `name` is the `seeder` slug, and because the
	 * Pro half shares that slug, toggling this one card enables/disables both.
	 *
	 * @param array $cards
	 *
	 * @return array
	 */
	public function register_addon_card( array $cards ): array {
		$cards[] = [
			'name'    => 'seeder',
			'label'   => __( 'Dummy Data Seeder', 'storeengine' ),
			'details' => __( 'Generate realistic sample data — customers, orders, products — for quick testing. Dev only.', 'storeengine' ),
			'svgIcon' => Helper::get_assets_url( 'images/addons/dummy_data_seeder.svg' ),
			// Settings link on the card → the Dummy Data tab under StoreEngine → Tools.
			'url'     => $this->tools_tab_url(),
			'color'   => '#7C3AED',
			'tags'    => [ 'developer', 'testing', 'dummy data', 'demo', 'sample' ],
		];

		return $cards;
	}

	public function define_constants() {
		define( 'STOREENGINE_SEEDER_VERSION', '1.0.0' );
		define( 'STOREENGINE_SEEDER_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'seeder/' );
	}

	public function init_addon() {
		// Register the data providers (core + free addons) on the shared filter.
		add_filter( 'storeengine/seeder/providers', [ $this, 'register_providers' ] );
		add_filter( 'storeengine/seeder/delete_object', [ $this, 'delete_object' ], 10, 3 );

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			add_action( 'cli_init', [ $this, 'register_cli' ] );
		}

		// AJAX endpoints backing the StoreEngine → Tools → Dummy Data tab.
		if ( is_admin() ) {
			( new Ajax() )->dispatch_actions();
		}
	}

	public function register_cli() {
		\WP_CLI::add_command( 'storeengine seed', Cli::class );
	}

	/**
	 * Add the core providers, plus free-addon providers gated on their addon.
	 *
	 * @param \StoreEngine\Addons\Seeder\Classes\AbstractSeederProvider[] $providers
	 *
	 * @return \StoreEngine\Addons\Seeder\Classes\AbstractSeederProvider[]
	 */
	public function register_providers( array $providers ): array {
		$providers[] = new Providers\CustomerProvider();
		$providers[] = new Providers\ProductProvider();
		$providers[] = new Providers\CouponProvider();
		$providers[] = new Providers\OrderProvider();

		if ( Helper::get_addon_active_status( 'subscription' ) ) {
			$providers[] = new Providers\SubscriptionProvider();
		}

		if ( Helper::get_addon_active_status( 'inventory' ) ) {
			$providers[] = new Providers\InventoryProvider();
		}

		if ( Helper::get_addon_active_status( 'funnel-builder' ) ) {
			$providers[] = new Providers\FunnelProvider();
		}

		return $providers;
	}

	/**
	 * Teach the manager how to delete the addon-specific objects this addon's
	 * providers record. (Core types are handled by the manager itself.)
	 *
	 * @param bool   $handled
	 * @param string $type
	 * @param int    $id
	 */
	public function delete_object( $handled, $type, $id ) {
		global $wpdb;

		switch ( $type ) {
			case 'stock_movement':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return (bool) $wpdb->delete( $wpdb->prefix . 'storeengine_stock_movements', [ 'id' => (int) $id ], [ '%d' ] );

			case 'page':
				// Funnel step pages seeded by the funnel provider.
				return (bool) wp_delete_post( (int) $id, true );

			case 'funnel':
				// Funnel::delete() cascades its steps + stats. Fall back to a direct
				// table delete when the addon class isn't loaded.
				if ( class_exists( '\StoreEngine\Addons\FunnelBuilder\Classes\Funnel' ) ) {
					$funnel = \StoreEngine\Addons\FunnelBuilder\Classes\Funnel::find( (int) $id );

					return $funnel ? $funnel->delete() : false;
				}
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $wpdb->prefix . 'storeengine_funnel_steps', [ 'funnel_id' => (int) $id ], [ '%d' ] );

				return (bool) $wpdb->delete( $wpdb->prefix . 'storeengine_funnels', [ 'id' => (int) $id ], [ '%d' ] );
				// phpcs:enable

			default:
				return $handled;
		}
	}
}
