<?php

namespace StoreEngine\Admin;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu {

	public static function init() {
		$self = new self();
		add_action( 'admin_menu', [ $self, 'admin_menu' ] );
		add_filter( 'storeengine/admin_menu_list', [ __CLASS__, 'inject_retail_menu_items' ] );
		add_filter( 'storeengine/admin_menu_list', [ __CLASS__, 'inject_withdrawals_menu_item' ] );
		// Runs last so it overrides whatever priority each addon set for itself —
		// the canonical submenu order lives in one place (see canonical_order()).
		add_filter( 'storeengine/admin_menu_list', [ __CLASS__, 'apply_canonical_order' ], 999 );
	}

	/**
	 * Single source of truth for the StoreEngine submenu order.
	 *
	 * Slug (without the `storeengine` prefix collision) => sort priority. Grouped
	 * into three bands so the menu reads top-to-bottom as: everyday selling →
	 * add-ons/operations → system. Addons register their own menu items with
	 * their own priorities; apply_canonical_order() normalises them against this
	 * map so the order never drifts as addons come and go.
	 *
	 * @return array<string,int>
	 */
	protected static function canonical_order(): array {
		$s = STOREENGINE_PLUGIN_SLUG;

		return [
			// --- Selling (core commerce, right after Orders) ---
			$s                       => 0,   // Dashboard
			"$s-products"            => 10,
			"$s-reviews"             => 14,  // Reviews — folded under Products
			"$s-faqs"                => 15,  // FAQs — folded under Products
			"$s-size-charts"         => 16,  // Size Charts — folded under Products
			"$s-orders"              => 20,
			"$s-coupons"             => 30,
			"$s-funnel-builder"      => 31,  // Funnels — standalone menu (was the Marketing group's slot)
			"$s-funnels"             => 31,  // alt slug
			"$s-customers"           => 40,
			"$s-roles"               => 41,  // Roles — folded under Customers
			"$s-payments"            => 50,
			"$s-subscriptions"       => 60,
			"$s-installment-plans"   => 70,
			"$s-membership-rules"    => 80,  // Access Rules
			"$s-membership-members"  => 81,  // Members
			"$s-membership-analytics" => 82, // Analytics
			"$s-affiliate"           => 90,
			"$s-affiliates"          => 90,  // alt slug
			"$s-withdrawals"         => 100, // right after Affiliates
			// --- Add-ons / operations ---
			"$s-inventory"           => 200,
			"$s-returns"             => 210,
			"$s-fraud-shield"        => 220,
			"$s-pos"                 => 230,
			"$s-order-bumps"         => 240,
			"$s-vendors"             => 260,
			"$s-deployments"         => 270,
			"$s-deployment-analytics" => 271,
			"$s-manage-licenses"     => 280,
			"$s-ai"                  => 290,
			// --- System ---
			"$s-addons"              => 910,
			"$s-logs"                => 915,  // directly above Tools
			"$s-tools"               => 920,
			"$s-webhooks"            => 930,
			"$s-settings"            => 990,
			"$s-get-pro"             => 1000,
		];
	}

	/**
	 * Normalise every menu item's priority against canonical_order(). Items not
	 * in the map keep their own priority but are floored into the add-ons band so
	 * a stray addon can never jump above the core selling menus.
	 *
	 * @param array $menu
	 *
	 * @return array
	 */
	public static function apply_canonical_order( array $menu ): array {
		$order = self::canonical_order();

		foreach ( $menu as $slug => &$item ) {
			if ( isset( $order[ $slug ] ) ) {
				$item['priority'] = $order[ $slug ];
			} elseif ( ( $item['priority'] ?? 0 ) < 200 ) {
				// Unknown addon: drop it into the add-ons band (after selling,
				// before system) instead of letting a low priority hoist it up.
				$item['priority'] = 300 + (int) ( $item['priority'] ?? 0 );
			}
		}
		unset( $item );

		return $menu;
	}

	/**
	 * Register the shared "Withdrawals" management menu.
	 *
	 * Both the multi-vendor and affiliate addons feed payout/withdrawal
	 * requests into this screen, so it must appear whenever EITHER addon is
	 * active. Previously the multi-vendor addon owned the menu outright (see
	 * MultiVendor\Admin::register_menu), so turning multi-vendor off hid the
	 * affiliate withdrawals too. Registering it centrally — gated by both
	 * addon statuses — keeps it reachable for either addon independently.
	 *
	 * The sub-items and the gating capability adapt to whichever addons are
	 * active. The React BackendDashboard route falls back to the affiliate
	 * Payouts screen when multi-vendor is inactive.
	 */
	public static function inject_withdrawals_menu_item( array $menu ): array {
		$has_multi_vendor = Helper::get_addon_active_status( 'multi-vendor' );
		$has_affiliate    = Helper::get_addon_active_status( 'affiliate' );

		if ( ! $has_multi_vendor && ! $has_affiliate ) {
			return $menu;
		}

		$sub_items = [];
		if ( $has_multi_vendor ) {
			$sub_items[] = [ 'slug' => '', 'title' => __( 'Vendor Payouts', 'storeengine' ) ];
		}
		if ( $has_affiliate ) {
			$sub_items[] = [ 'slug' => 'affiliate', 'title' => __( 'Affiliate Payouts', 'storeengine' ) ];
		}

		$menu['storeengine-withdrawals'] = [
			'title'      => __( 'Payouts', 'storeengine' ),
			// Vendor managers use the vendor cap; an affiliate-only store falls
			// back to the standard admin cap so the screen stays reachable.
			'capability' => $has_multi_vendor ? 'manage_storeengine_vendor' : 'manage_options',
			'priority'   => 36,
			'sub_items'  => $sub_items,
		];

		return $menu;
	}

	/**
	 * Inject Inventory, POS, Returns, and Reports entries so the React admin
	 * shell shows them in the side menu and the route switcher mounts the
	 * React pages. Each addon-owned menu is gated by its own active status —
	 * disabling the addon removes the entry. Reports remains visible
	 * unconditionally; tabs that depend on specific addons (e.g. profit
	 * from cost-profit) are gated at the REST / React level.
	 */
	public static function inject_retail_menu_items( array $menu ): array {
		$has_inventory_pro = Helper::get_addon_active_status( 'inventory-pro' );

		if ( Helper::get_addon_active_status( 'inventory' ) ) {
			$inventory_sub_items = [
				[ 'slug' => '', 'title' => __( 'Stock', 'storeengine' ) ],
			];

			if ( $has_inventory_pro ) {
				$inventory_sub_items[] = [ 'slug' => 'locations', 'title' => __( 'Locations', 'storeengine' ) ];
			}

			$inventory_sub_items[] = [ 'slug' => 'movements', 'title' => __( 'Movements', 'storeengine' ) ];

			// Barcode label generator is free — only needs the (free) Inventory
			// addon, not inventory-pro.
			$inventory_sub_items[] = [ 'slug' => 'barcodes', 'title' => __( 'Barcode Labels', 'storeengine' ) ];

			if ( $has_inventory_pro ) {
				$inventory_sub_items[] = [ 'slug' => 'transfer', 'title' => __( 'Transfer', 'storeengine' ) ];
				$inventory_sub_items[] = [ 'slug' => 'stock-subscribers', 'title' => __( 'Restock sub', 'storeengine' ) ];
			}

			$menu[ STOREENGINE_PLUGIN_SLUG . '-inventory' ] = [
				'title'      => __( 'Inventory', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 25,
				'sub_items'  => $inventory_sub_items,
			];
		}

		// POS and Returns menus now ship with their respective Pro addons
		// (see StoreEnginePro\Addons\Pos\Hooks::register_menu_items() and
		// StoreEnginePro\Addons\Returns\Hooks::register_menu_items()), so the
		// menu entry and its SPA route disappear together when the addon — or
		// the whole Pro plugin — is absent or disabled.

		// (Cost-profit reporting is now folded into the main Dashboard
		// `OverviewCards` row via the `storeengine/analytics/stats` filter
		// — see cost-profit/admin.php::append_profit_stats().)

		return $menu;
	}

	/**
	 * Presentational grouping for the React sidebar.
	 *
	 * Each group becomes a single expandable top-level item whose children are
	 * the listed page slugs. Only children that actually exist in the (already
	 * filtered/ordered) flat menu are pulled in, so a group shrinks — or
	 * disappears entirely — as its addons are toggled off. Children keep their
	 * own `?page=` route untouched: grouping is a pure menu-tree transform (see
	 * get_menu_tree()), the WP submenu-page registration and every React route
	 * stay exactly as before.
	 *
	 * `priority` positions the group where its members used to sit in the
	 * canonical order (Marketing ≈ Coupons, Finance ≈ Payments).
	 *
	 * @return array<string,array>
	 */
	protected static function menu_groups(): array {
		$s = STOREENGINE_PLUGIN_SLUG;

		return [
			// Products with its taxonomy/attribute screens as raw-route children.
			"$s-products"  => [
				'title'         => __( 'Products', 'storeengine' ),
				'priority'      => 10,
				'primary'       => "$s-products",
				'primary_label' => __( 'All Products', 'storeengine' ),
				'children'      => [
					"$s-reviews", // Reviews — folded under Products
					"$s-faqs",    // FAQs — folded under Products
					"$s-size-charts", // Size Charts — folded under Products
					"$s-coupons", // Coupons — folded under Products
					"$s-category",
					"$s-tags",
					"$s-attributes",
					"$s-brands",
				],
			],
			// Orders is a group *backed by a real page* (`primary`): its own
			// list view leads (as "All Orders") followed by its existing
			// sub-items (e.g. Abandoned Carts), then the folded-in pages. A
			// folded page that carries its own sub-items (e.g. Returns) keeps
			// them as a second nesting level, which get_menu_tree() preserves
			// so their sub-pages stay reachable from the sidebar.
			"$s-orders"    => [
				'title'         => __( 'Orders', 'storeengine' ),
				'priority'      => 20,
				'primary'       => "$s-orders",
				'primary_label' => __( 'All Orders', 'storeengine' ),
				'children'      => [
					"$s-abandoned-cart",
					"$s-returns",
					"$s-couriers", // Shipments — folded under Orders
					"$s-order-bumps", // Order Bumps — folded under Orders
				],
			],
			"$s-customers" => [
				'title'         => __( 'Customers', 'storeengine' ),
				'priority'      => 40,
				'primary'       => "$s-customers",
				'primary_label' => __( 'All Customers', 'storeengine' ),
				'children'      => [
					"$s-roles", // Roles — every registered role across all add-ons
				],
			],
			// Membership: a pure container (no page of its own) whose children are
			// the Access Rules builder and the Members list. Both slugs are
			// registered by the membership addon (see Membership\Hooks::admin_menu_items),
			// so the whole group appears only while the addon is active and lands
			// on the first child (Access Rules).
			"$s-membership" => [
				'title'    => __( 'Membership', 'storeengine' ),
				'priority' => 80,
				'children' => [
					"$s-membership-rules",     // Access Rules
					"$s-membership-members",   // Members
					"$s-membership-analytics", // Analytics
				],
			],
			// Fraud Shield (pro): its own top-level menu — many screens fold in
			// as raw-route children.
			"$s-fraud-shield" => [
				'title'         => __( 'Fraud Shield', 'storeengine' ),
				'priority'      => 24,
				'primary'       => "$s-fraud-shield",
				'primary_label' => __( 'Dashboard', 'storeengine' ),
				'children'      => [
					"$s-fraud-shield-queue",
					"$s-fraud-shield-rules",
					"$s-fraud-shield-blocklist",
					"$s-fraud-shield-activity",
					"$s-fraud-shield-settings",
				],
			],
			// POS (pro): its management screens fold in as raw-route children.
			"$s-pos"       => [
				'title'         => __( 'POS', 'storeengine' ),
				'priority'      => 27,
				'primary'       => "$s-pos",
				'primary_label' => __( 'Sessions', 'storeengine' ),
				'children'      => [
					"$s-pos-registers",
					"$s-pos-staff",
					"$s-pos-card-readers",
					"$s-pos-reports",
				],
			],
			// Dropshipping (pro): its screens fold in as raw-route children.
			"$s-dropship"  => [
				'title'         => __( 'Dropshipping', 'storeengine' ),
				'priority'      => 28,
				'primary'       => "$s-dropship",
				'primary_label' => __( 'Dispatch queue', 'storeengine' ),
				'children'      => [
					"$s-dropship-routes",
					"$s-dropship-supplier-rules",
					"$s-dropship-connectors",
					"$s-dropship-settings",
				],
			],
			// Suppliers (pro): Purchase Orders folds in as a raw-route child.
			"$s-suppliers" => [
				'title'         => __( 'Suppliers', 'storeengine' ),
				'priority'      => 26,
				'primary'       => "$s-suppliers",
				'primary_label' => __( 'Suppliers', 'storeengine' ),
				'children'      => [
					"$s-purchase-orders",
				],
			],
			// Licenses is a page with its own sub-items (All Licenses, Sites);
			// Deployments (with its Analytics sub-page) folds in beneath it as
			// a second level.
			"$s-manage-licenses" => [
				'title'         => __( 'Licenses', 'storeengine' ),
				'priority'      => 35,
				'primary'       => "$s-manage-licenses",
				'primary_label' => __( 'All Licenses', 'storeengine' ),
				'children'      => [
					"$s-installation-events",
					"$s-deployments",
					"$s-deployment-analytics",
				],
			],
			// Funnel Builder is its own top-level menu (no "Marketing" wrapper).
			// It's a flat, addon-registered item; canonical_order() positions it
			// where Marketing used to sit. It surfaces top-level simply by not
			// being folded into any group here.
			// Payments is a page-backed group: the payments list leads
			// ("One-time"), and Subscriptions / Installment Plans fold in as
			// their own raw-route pages (page=storeengine-subscriptions,
			// page=storeengine-installment-plans) — consistent with every other
			// group's children (SureCart-style raw routes, not ?path= params).
			"$s-payments"  => [
				'title'         => __( 'Payments', 'storeengine' ),
				'priority'      => 50,
				'primary'       => "$s-payments",
				'primary_label' => __( 'One-time', 'storeengine' ),
				'children'      => [
					"$s-subscriptions",
					"$s-installment-plans",
				],
			],
			// Affiliates, Vendors and Payouts are their own top-level menus (no
			// "Partners" wrapper). Each is a flat item registered by its addon
			// (affiliate/multi-vendor) or centrally (Payouts, see
			// inject_withdrawals_menu_item); canonical_order() positions them
			// (Affiliates 90, Payouts 100). They surface top-level simply by not
			// being folded into any group here.
		];
	}

	/**
	 * The grouped menu tree the React sidebar renders.
	 *
	 * Starts from the flat get_menu_lists() (fully filtered + ordered), then
	 * folds each group's children into a single expandable parent. The child's
	 * standalone top-level row is removed and re-emitted as a `sub_items` entry
	 * carrying its own `page` slug, so the React `MenuItem` links straight to
	 * the child's existing route — no addon or route switcher needs touching.
	 *
	 * WP page registration still iterates the flat get_menu_lists(), so every
	 * child page URL and capability check remains valid.
	 *
	 * @return array
	 */
	public static function get_menu_tree(): array {
		$menu = self::get_menu_lists();

		// Grouping is a presentational nicety for the full admin. For a
		// restricted (non-admin) user — e.g. a Pro role-permission staff member —
		// the flat, per-permission menu is filtered down to only the pages they
		// may reach. Folding those into groups whose *parent* page they can't
		// access would bury or drop a permitted child (e.g. Coupons lives under
		// the Products group, so a staff user with coupon access but no product
		// access would lose the Coupons menu entirely). Render the menu flat for
		// them so every permitted page stays a reachable top-level item.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $menu;
		}

		foreach ( self::menu_groups() as $group_slug => $group ) {
			$sub_items = [];
			$primary   = $group['primary'] ?? null;

			// A group backed by a real page leads with that page's own list
			// view + its existing sub-items (both routed under the page).
			if ( $primary && isset( $menu[ $primary ] ) ) {
				$primary_subs = $menu[ $primary ]['sub_items'] ?? [];

				// Only synthesize an "All …" default when the page doesn't
				// already ship its own default (slug '') sub-item — pages like
				// Licenses do, so reusing theirs avoids a duplicate row.
				$has_default = false;
				foreach ( $primary_subs as $existing ) {
					if ( ! isset( $existing['page'] ) && '' === ( $existing['slug'] ?? '' ) ) {
						$has_default = true;
						break;
					}
				}

				if ( ! $has_default ) {
					$sub_items[] = [
						'slug'  => '',
						'title' => $group['primary_label'] ?? $menu[ $primary ]['title'],
					];
				}

				foreach ( $primary_subs as $existing ) {
					$sub_items[] = $existing;
				}
			}

			// Fold in each child page. A child that has its own sub-items keeps
			// them as a second nesting level (grandchildren), so pages like
			// Fraud Shield don't lose access to their sub-screens.
			foreach ( $group['children'] ?? [] as $child_slug ) {
				if ( ! isset( $menu[ $child_slug ] ) ) {
					continue;
				}

				$entry = [
					'page'  => $child_slug,
					'title' => $menu[ $child_slug ]['title'],
				];

				if ( ! empty( $menu[ $child_slug ]['sub_items'] ) ) {
					$entry['sub_items'] = $menu[ $child_slug ]['sub_items'];
				}

				$sub_items[] = $entry;

				unset( $menu[ $child_slug ] );
			}

			// Path-based sub-tabs: rendered under the primary page via ?path=,
			// not as separate pages. The standalone page each replaces
			// (`requires`) is absorbed into this group.
			foreach ( $group['path_children'] ?? [] as $path_child ) {
				$requires = $path_child['requires'] ?? null;
				if ( $requires && ! isset( $menu[ $requires ] ) ) {
					continue;
				}

				$sub_items[] = [
					'slug'  => $path_child['slug'],
					'title' => $path_child['title'],
				];

				if ( $requires ) {
					unset( $menu[ $requires ] );
				}
			}

			// A primary-backed group with only its own page (no folded children)
			// gains nothing from grouping — leave the page as-is.
			if ( empty( $sub_items ) || ( $primary && count( $sub_items ) <= 1 ) ) {
				continue;
			}

			// Where the parent row navigates: a primary-backed group (Orders)
			// lands on its own page; a pure container (Marketing) lands on its
			// first page-based child. Used by both the React menu and the native
			// WP submenu registration.
			$landing = $primary;
			if ( ! $landing ) {
				foreach ( $sub_items as $si ) {
					if ( ! empty( $si['page'] ) ) {
						$landing = $si['page'];
						break;
					}
				}
			}

			// Reuse the primary page's slug so its route/registration is intact;
			// otherwise mint the group's own (page-less) slug.
			$menu[ $group_slug ] = [
				'title'      => $group['title'],
				'capability' => $menu[ $primary ]['capability'] ?? 'manage_options',
				'priority'   => $group['priority'],
				'sub_items'  => $sub_items,
				'is_group'   => true,
				'landing'    => $landing ?: '',
			];
		}

		uasort( $menu, function ( $a, $b ) {
			return ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 );
		} );

		return $menu;
	}

	public static function get_menu_lists() {
		$menu_items = [
			STOREENGINE_PLUGIN_SLUG                => [
				'title'      => __( 'Dashboard', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 0,
			],
			STOREENGINE_PLUGIN_SLUG . '-products'  => [
				'title'      => __( 'Products', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 10,
			],
			// Product taxonomy / attribute screens are their own raw-route pages
			// (?page=storeengine-category etc.); the Products group folds them in
			// as children — see menu_groups(). The parent Products route keeps a
			// `?path=` fallback so any un-migrated internal link still resolves.
			STOREENGINE_PLUGIN_SLUG . '-reviews'    => [
				'title'      => __( 'Reviews', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 14,
			],
			STOREENGINE_PLUGIN_SLUG . '-category'   => [
				'title'      => __( 'Category', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 11,
			],
			STOREENGINE_PLUGIN_SLUG . '-tags'       => [
				'title'      => __( 'Tags', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 12,
			],
			STOREENGINE_PLUGIN_SLUG . '-attributes' => [
				'title'      => __( 'Attributes', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 13,
			],
			STOREENGINE_PLUGIN_SLUG . '-orders'    => [
				'title'      => __( 'Orders', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 20,
			],
			STOREENGINE_PLUGIN_SLUG . '-coupons'   => [
				'title'      => __( 'Coupons', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 30,
			],
			STOREENGINE_PLUGIN_SLUG . '-customers' => [
				'title'      => __( 'Customers', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 40,
			],
			STOREENGINE_PLUGIN_SLUG . '-roles'     => [
				'title'      => __( 'Roles', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 41,
			],
			STOREENGINE_PLUGIN_SLUG . '-payments'  => [
				'title'      => __( 'Payments', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 50,
			],
			STOREENGINE_PLUGIN_SLUG . '-addons'    => [
				'title'      => __( 'Add-ons', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 90,
			],
			STOREENGINE_PLUGIN_SLUG . '-tools'     => [
				'title'      => __( 'Tools', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 90,
			],
			STOREENGINE_PLUGIN_SLUG . '-logs' => [
				'title'      => __( 'Logs', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 80,
			],
			STOREENGINE_PLUGIN_SLUG . '-settings'  => [
				'title'      => __( 'Settings', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 99,
			],
		];

		// The FAQ Groups library is only surfaced in the "global" FAQ mode; in
		// "product_only" mode products keep an inline FAQ editor and no library.
		if ( \StoreEngine\Utils\Helper::get_settings( 'enable_faqs', true )
			&& 'product_only' !== \StoreEngine\Utils\Helper::get_settings( 'faq_mode', 'global' ) ) {
			$menu_items[ STOREENGINE_PLUGIN_SLUG . '-faqs' ] = [
				'title'      => __( 'FAQs', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 15,
			];
		}

		// Size chart library — only surfaced while the size guide is enabled,
		// since the charts have nowhere to render otherwise.
		if ( \StoreEngine\Utils\Helper::get_settings( 'enable_size_guide', false ) ) {
			$menu_items[ STOREENGINE_PLUGIN_SLUG . '-size-charts' ] = [
				'title'      => __( 'Size Charts', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 16,
			];
		}

		$menu = apply_filters( 'storeengine/admin_menu_list', $menu_items );

		if ( ! defined( 'STOREENGINE_PRO_VERSION' ) ) {
			// Injected AFTER the apply_canonical_order filter, so it must carry its
			// own canonical priority — pull it straight from canonical_order() so
			// "Get Pro" lands in the System band (bottom) instead of the selling
			// band. A stray `100` here dropped it between Payments and Add-ons.
			$get_pro_slug = STOREENGINE_PLUGIN_SLUG . '-get-pro';
			$menu[ $get_pro_slug ] = [
				'title'       => '<span class="dashicons dashicons-awards storeengine-blue-color"></span> ' . __( 'Get Pro', 'storeengine' ),
				'capability'  => 'manage_options',
				'priority'    => self::canonical_order()[ $get_pro_slug ] ?? 1000,
			];
		}

		uasort( $menu, function ( $a, $b ) {
			return ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 );
		} );

		return $menu;
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_menu_page(
			__( 'StoreEngine', 'storeengine' ),
			__( 'StoreEngine', 'storeengine' ),
			'manage_options',
			STOREENGINE_PLUGIN_SLUG,
			[ $this, 'load_main_template' ],
			$this->get_menu_icon(),
			55
		);

		$registered = [];

		// SureCart-style native menu: register one visible entry per top-level
		// group (titled with the group name, pointing at its navigable page —
		// own page for page-backed groups, first child for containers). This is
		// all the native hover fly-out shows.
		foreach ( self::get_menu_tree() as $slug => $item ) {
			$target = ! empty( $item['landing'] ) ? $item['landing'] : $slug;

			if ( isset( $registered[ $target ] ) ) {
				continue;
			}

			add_submenu_page(
				STOREENGINE_PLUGIN_SLUG,
				$item['title'],
				$item['title'],
				$item['capability'] ?? 'manage_options',
				$target,
				[ $this, 'load_main_template' ]
			);
			$registered[ $target ] = true;
		}

		// Child / sub-pages are NOT permanently registered — that is what kept
		// the fly-out long. Instead, register only the page currently being
		// viewed (SureCart's conditional-registration trick), so a direct URL or
		// reload of a child route still resolves and loads the SPA. In-app
		// navigation is client-side React and needs no registration, and from
		// other screens no child is registered, so the fly-out stays to the
		// group names only.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $current_page && ! isset( $registered[ $current_page ] ) ) {
			$flat = self::get_menu_lists();
			if ( isset( $flat[ $current_page ] ) ) {
				$item = $flat[ $current_page ];
				add_submenu_page(
					STOREENGINE_PLUGIN_SLUG,
					$item['title'],
					$item['title'],
					$item['capability'],
					$current_page,
					[ $this, 'load_main_template' ]
				);
				$registered[ $current_page ] = true;
			}
		}
	}

	protected function get_menu_icon() {
		return apply_filters( 'storeengine/admin/toplevel_inactive_menu_icon', STOREENGINE_ASSETS_URI . 'images/logo.svg' );
	}

	public function load_main_template() {
		echo '<div id="storeengine-admin" class="storeengine-admin se-admin-tw"></div>';
	}
}
