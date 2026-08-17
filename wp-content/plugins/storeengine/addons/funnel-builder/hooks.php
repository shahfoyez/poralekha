<?php
/**
 * Funnel Builder admin wiring: menu item, add-on card, asset enqueue, and
 * purchase-event tracking.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder;

use StoreEngine\Addons\FunnelBuilder\Classes\Funnel;
use StoreEngine\Addons\FunnelBuilder\Classes\FunnelStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	const MENU_SLUG = 'storeengine-funnels';

	public static function init() {
		$self = new self();

		add_filter( 'storeengine/admin_menu_list', [ $self, 'register_menu' ] );
		add_filter( 'storeengine/admin/extra_addons', [ $self, 'register_addon_card' ] );

		// The admin editor UI (list + flow modes) ships inside the core
		// StoreEngine admin bundle (dev_storeengine/.../Pages/Funnels), registered
		// via the `storeengine.admin.pages` JS filter — nothing to enqueue here.

		// Attribute completed purchases back to the funnel that produced them.
		add_action( 'storeengine/checkout/order_processed', [ $self, 'track_purchase' ], 20, 2 );
	}

	/**
	 * Adds the "Funnels" item to the StoreEngine admin sidebar. Core registers
	 * the matching submenu page and the SPA renders whatever the
	 * `storeengine.admin.pages` JS filter maps the slug to (see assets/admin.js).
	 */
	public function register_menu( array $menu ): array {
		$menu[ self::MENU_SLUG ] = [
			'title'      => __( 'Funnels', 'storeengine' ),
			'capability' => 'manage_options',
			'priority'   => 35,
			'sub_items'  => [
				[
					'slug'  => '',
					'title' => __( 'Funnels', 'storeengine' ),
				],
				[
					'slug'  => 'store-checkout',
					'title' => __( 'Store Checkout', 'storeengine' ),
				],
				[
					'slug'  => 'analytics',
					'title' => __( 'Analytics', 'storeengine' ),
				],
			],
		];

		return $menu;
	}

	/**
	 * Surfaces the single Funnel Builder card on the Add-ons screen. Toggling it
	 * writes `storeengine_addons['funnel-builder']`, which activates BOTH the
	 * free and the (same-slug) Pro addon at once.
	 */
	public function register_addon_card( array $cards ): array {
		$cards[] = [
			'label'           => __( 'Funnel Builder', 'storeengine' ),
			'name'            => 'funnel-builder',
			'beta'            => true,
			'required_plugin' => false,
			'details'         => __( 'Build sales funnels — landing, checkout, one-click upsells and thank-you steps — designed with your block builder.', 'storeengine' ),
			'icon'            => 'funnel',
			'url'             => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
			'docsUrl'         => 'https://storeengine.pro/docs/funnel-builder/',
			'is_pro'          => false,
			'color'           => '#7c3aed',
			'category'        => 'checkout',
			'tags'            => [ 'funnel', 'sales funnel', 'upsell', 'downsell', 'checkout', 'one click', 'conversion', 'landing page' ],
		];

		return $cards;
	}

	/**
	 * When an order is placed through a funnel, log the purchase against its
	 * checkout step. The funnel id is stashed on the session/order by the
	 * frontend router (Frontend::class).
	 */
	public function track_purchase( $order, $payload = null ) {
		$funnel_id = 0;
		$step_id   = null;

		if ( is_object( $order ) && method_exists( $order, 'get_meta' ) ) {
			$funnel_id = (int) $order->get_meta( '_storeengine_funnel_id' );
			$step_id   = (int) $order->get_meta( '_storeengine_funnel_step_id' ) ?: null;
		}

		// Order placed through a funnel checkout — the funnel/step were stashed on
		// a cookie by Frontend::remember_context. Persist them onto the order so
		// attribution, the Pro upsell router and abandonment recovery can use them.
		if ( ! $funnel_id && ! empty( $_COOKIE['storeengine_funnel_id'] ) ) {
			$funnel_id = (int) $_COOKIE['storeengine_funnel_id'];
			$step_id   = ! empty( $_COOKIE['storeengine_funnel_step_id'] ) ? (int) $_COOKIE['storeengine_funnel_step_id'] : null;

			if ( $funnel_id && is_object( $order ) && method_exists( $order, 'update_meta_data' ) && method_exists( $order, 'save_meta_data' ) ) {
				$order->update_meta_data( '_storeengine_funnel_id', $funnel_id );
				if ( $step_id ) {
					$order->update_meta_data( '_storeengine_funnel_step_id', $step_id );
				}
				$order->save_meta_data();
			}
		}

		if ( ! $funnel_id ) {
			return;
		}

		$total = 0;
		if ( is_object( $order ) && method_exists( $order, 'get_total' ) ) {
			$total = (float) $order->get_total();
		}

		$order_id = ( is_object( $order ) && method_exists( $order, 'get_id' ) ) ? (int) $order->get_id() : null;

		FunnelStats::record( $funnel_id, $step_id, 'purchase', [
			'order_id' => $order_id,
			'revenue'  => $total,
		] );
	}
}
