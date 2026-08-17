<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	public static function init() {
		$self = new self();

		// Resolve vendor_id from product post_author at lookup INSERT time.
		add_filter( 'storeengine/order/product_lookup_vendor_id', [ $self, 'resolve_vendor_id' ], 10, 2 );

		// Force vendor product list in wp-admin to scope to own posts.
		add_action( 'pre_get_posts', [ $self, 'scope_admin_product_query' ] );

		// Add vendor menu items to frontend dashboard for vendor users + hide customer items.
		add_filter( 'storeengine/frontend_dashboard_menu_items', [ $self, 'inject_vendor_menu_items' ] );

		// Shared page-header: vendor page descriptions + primary actions render in
		// the topbar so vendor pages no longer hand-roll their own titles/actions.
		add_filter( 'storeengine/frontend-dashboard/page_description', [ $self, 'vendor_page_description' ], 10, 3 );
		add_action( 'storeengine/templates/frontend-dashboard/topbar/right_content', [ $self, 'vendor_topbar_actions' ], 10, 2 );

		// Show a status banner at the top of every dashboard page for pending /
		// suspended vendors. Priority 9 so it renders above the topbar.
		add_action( 'storeengine/frontend/dashboard_content', [ $self, 'render_vendor_status_banner' ], 9 );

		// Vendors must NOT access wp-admin. Redirect them to the frontend dashboard on every
		// admin pageload (with carve-outs for AJAX, REST, async-upload — required for media
		// uploads from the frontend product form to work).
		add_action( 'admin_init', [ $self, 'block_admin_for_vendors' ] );
		add_filter( 'show_admin_bar', [ $self, 'hide_admin_bar_for_vendors' ] );

		// Backfill listener (registered by migration but ensure it's hooked when addon loads too).
		add_action( Database\MigrateLookupTable::BACKFILL_HOOK, [ Database\MigrateLookupTable::class, 'run_backfill' ] );

		// Opt-in: auto-complete an order once every line item (all vendors) is
		// delivered. Fired by both the vendor fulfilment endpoint and the admin
		// shipping AJAX, so the payload may be an order id or the model data.
		add_action( 'storeengine/all_product_delivered', [ $self, 'maybe_auto_complete_order' ] );
	}

	/**
	 * Description shown under the shared page-header title for vendor endpoints.
	 *
	 * @param string $description
	 * @param string $path
	 * @param string $sub_path
	 *
	 * @return string
	 */
	public function vendor_page_description( $description, $path, $sub_path ) {
		$map = [
			'vendor-orders' => __( 'Mark items as shipped and add courier & tracking details — the customer is notified on each shipment.', 'storeengine' ),
		];

		return $map[ $path ] ?? $description;
	}

	/**
	 * Vendor primary page actions rendered on the right of the shared page-header.
	 *
	 * @param string $path
	 * @param string $sub_path
	 */
	public function vendor_topbar_actions( $path, $sub_path ) {
		if ( 'vendor-profile' !== $path ) {
			return;
		}

		$user = wp_get_current_user();
		if ( empty( $user->ID ) ) {
			return;
		}

		$vendor = new Vendor( (int) $user->ID );
		if ( ! $vendor->is_approved() ) {
			return;
		}

		$store_url = StorePage::url_for( $vendor );
		if ( ! $store_url ) {
			return;
		}

		printf(
			'<a href="%1$s" target="_blank" rel="noopener" class="storeengine-btn storeengine-btn--md storeengine-btn--preset-blue storeengine-dashboard-header-action">%2$s <i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i></a>',
			esc_url( $store_url ),
			esc_html__( 'View public store', 'storeengine' )
		);
	}

	/**
	 * Advance an order processing → completed when all items are delivered, if
	 * the `auto_complete_on_all_delivered` setting is enabled. Only advances from
	 * `processing` so we never resurrect a cancelled/refunded order or re-complete
	 * one. Vendors never set order status directly — this is the only automated
	 * path, and it's off by default.
	 *
	 * @param int|array|object $payload Order id, or the legacy model data shape.
	 */
	public function maybe_auto_complete_order( $payload ): void {
		if ( ! Settings::get( 'auto_complete_on_all_delivered', false ) ) {
			return;
		}

		$order_id = 0;
		if ( is_numeric( $payload ) ) {
			$order_id = (int) $payload;
		} elseif ( is_array( $payload ) ) {
			$order_id = (int) ( $payload['id'] ?? ( $payload[0]['id'] ?? 0 ) );
		} elseif ( is_object( $payload ) && isset( $payload->id ) ) {
			$order_id = (int) $payload->id;
		}
		if ( ! $order_id ) {
			return;
		}

		$order = Helper::get_order( $order_id );
		if ( is_wp_error( $order ) ) {
			return;
		}
		if ( Constants::ORDER_STATUS_PROCESSING !== $order->get_status() ) {
			return;
		}

		$order->set_status( Constants::ORDER_STATUS_COMPLETED, __( 'All items delivered across all vendors.', 'storeengine' ) );
		$order->save();
	}

	/**
	 * Vendors must use the frontend dashboard exclusively. If they hit any wp-admin URL,
	 * redirect to the dashboard. Allow AJAX, REST, async-upload (media library uploads
	 * from the frontend product form rely on async-upload.php), and the logout flow.
	 */
	public function block_admin_for_vendors() {
		// Carve-outs that the frontend product form depends on.
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		// async-upload.php (media library) and admin-post.php must remain reachable.
		$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( in_array( $pagenow, [ 'async-upload.php', 'admin-post.php' ], true ) ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}
		if ( ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return;
		}
		// Users wearing both vendor and a higher role (admin/shop_manager) should pass.
		if ( user_can( $user, 'edit_others_storeengine_products' ) || user_can( $user, 'manage_options' ) ) {
			return;
		}
		// StoreEngine staff members (Pro role-permission addon) manage the store
		// from wp-admin, so they must not be redirected to the vendor storefront
		// dashboard even if they also carry the vendor role. Detected via the
		// staff flag meta so this free addon needs no dependency on the Pro class.
		if ( get_user_meta( $user->ID, 'storeengine_staff_is_set', true ) ) {
			return;
		}

		$dashboard = (int) \StoreEngine\Utils\Helper::get_settings( 'dashboard_page' );
		$target    = $dashboard ? get_permalink( $dashboard ) : home_url( '/' );

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Hide the wp-admin bar on the front end for vendors — they should live in the dashboard UI.
	 */
	public function hide_admin_bar_for_vendors( $show ) {
		$user = wp_get_current_user();
		if ( ! $user || ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return $show;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return $show;
		}
		return false;
	}

	/**
	 * Resolve vendor_id from a product's post_author for lookup-table writes.
	 *
	 * @param int|null $current
	 * @param int      $product_id
	 * @return int|null
	 */
	public function resolve_vendor_id( $current, $product_id ) {
		if ( null !== $current && $current > 0 ) {
			return $current;
		}

		$author = (int) get_post_field( 'post_author', (int) $product_id );
		return $author > 0 ? $author : null;
	}

	/**
	 * Force the wp-admin product list to only show the vendor's own products.
	 */
	public function scope_admin_product_query( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		// Only the products list table.
		$post_type = $query->get( 'post_type' );
		if ( Helper::PRODUCT_POST_TYPE !== $post_type ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return;
		}

		// Admins/shop managers viewing the same screen pass through.
		if ( current_user_can( 'edit_others_storeengine_products' ) ) {
			return;
		}

		$query->set( 'author', $user->ID );
	}

	public function render_vendor_status_banner(): void {
		$user = wp_get_current_user();
		if ( ! $user || ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return;
		}

		$vendor = new Vendor( (int) $user->ID );
		if ( ! $vendor->exists() ) {
			return;
		}

		$status = $vendor->get_status();
		if ( Vendor::STATUS_APPROVED === $status ) {
			return;
		}

		if ( Vendor::STATUS_SUSPENDED === $status ) {
			$class   = 'storeengine-notice--error';
			$title   = __( 'Your vendor account is suspended', 'storeengine' );
			$message = sprintf(
				/* translators: %s: store name */
				__( 'Access to %s has been suspended. Please contact support.', 'storeengine' ),
				$vendor->get_store_name()
			);
		} else {
			$class   = 'storeengine-notice--info';
			$title   = __( 'Application pending review', 'storeengine' );
			$message = sprintf(
				/* translators: %s: store name */
				__( 'Thanks for applying with %s. Your account is being reviewed and you will receive an email update soon. You can still edit your store profile while you wait.', 'storeengine' ),
				$vendor->get_store_name()
			);
		}

		printf(
			'<div class="storeengine-notice %s" style="margin:0 0 16px;"><strong>%s</strong><div>%s</div></div>',
			esc_attr( $class ),
			esc_html( $title ),
			esc_html( $message )
		);
	}

	// Always inject vendor routes so PermalinkRewrite::add_rewrite_rules can
	// build URL rules (rewrite generation runs in admin context with no
	// vendor as current user). Menu visibility is per-item via `public`.
	public function inject_vendor_menu_items( array $items ): array {
		$user        = wp_get_current_user();
		$is_vendor   = $user && $user->ID && in_array( Role::ROLE, (array) $user->roles, true );
		$vendor      = $is_vendor ? new Vendor( (int) $user->ID ) : null;
		$is_approved = $vendor && $vendor->is_approved();
		$is_pending  = $is_vendor && ! $is_approved;

		// Hide customer commerce items from approved vendors. `downloads` stays
		// visible — vendors can also be buyers and need access to what they've
		// purchased. Unset is safe at rewrite-generation time because customer
		// routes are also re-added elsewhere; we only need this to keep them out
		// of the vendor menu UI.
		if ( $is_approved ) {
			foreach ( [ 'orders', 'payment-methods', 'edit-address' ] as $key ) {
				if ( isset( $items[ $key ] ) ) {
					$items[ $key ]['public'] = false;
				}
			}
		}

		// For pending vendors, hide all customer items except the basics so
		// they don't see options that aren't relevant to a pending state.
		if ( $is_pending ) {
			foreach ( $items as $key => $item ) {
				if ( in_array( $key, [ 'index', 'edit-account', 'customer-logout' ], true ) ) {
					continue;
				}
				$items[ $key ]['public'] = false;
			}
		}

		$vendor_items = [
			'__vendor_separator'    => [
				'label'        => __( 'Vendor', 'storeengine' ),
				'public'       => $is_approved,
				'priority'     => 4,
				'is_separator' => true,
			],
			// Store overview is no longer a standalone page — its analytics are
			// rendered on the dashboard root (index) for approved vendors. See
			// TemplateLoader::render_root_overview().
			'vendor-products'       => [
				'label'    => __( 'My products', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--box',
				'public'   => $is_approved,
				'priority' => 10,
			],
			'vendor-product-edit'   => [
				'label'    => __( 'Add / edit product', 'storeengine' ),
				'public'   => false,
				'priority' => 11,
			],
			'vendor-orders'         => [
				'label'    => __( 'Store orders', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--store_orders',
				'public'   => $is_approved,
				'priority' => 20,
			],
			'vendor-inventory'      => [
				'label'    => __( 'Inventory', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--inventory',
				'public'   => $is_approved,
				'priority' => 22,
			],
			'vendor-returns'        => [
				'label'    => __( 'Returns received', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--restore',
				'public'   => $is_approved,
				'priority' => 24,
			],
			'vendor-payment-method' => [
				'label'    => __( 'Payment method', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--payment',
				'public'   => $is_approved,
				'priority' => 25,
			],
			'vendor-withdraw'       => [
				'label'    => __( 'Withdraw', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--money-send',
				'public'   => $is_approved,
				'priority' => 28,
			],
			'vendor-profile'        => [
				'label'    => __( 'Store profile', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--build',
				// Visible to pending too, so they can fix typos before approval.
				'public'   => $is_vendor,
				'priority' => 30,
			],
		];

		return array_merge( $items, $vendor_items );
	}
}
