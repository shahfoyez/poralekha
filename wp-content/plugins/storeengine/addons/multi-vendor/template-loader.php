<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vendor dashboard template loader.
 *
 * Templates live under the core plugin's `templates/multi-vendor/` directory
 * (same convention the email addon follows). `Helper::get_template` gives us
 * the standard theme override flow:
 *
 *   1. Theme: `<theme>/storeengine/multi-vendor/<relative>`
 *   2. Plugin default: `<storeengine>/templates/multi-vendor/<relative>`
 *
 * Endpoints fire one method per dashboard route; each just builds the
 * `$args` array and delegates to `Helper::get_template`.
 */
class TemplateLoader {

	const TEMPLATE_BASE = 'multi-vendor/';

	public static function init() {
		$self = new self();

		add_action( 'storeengine/frontend/dashboard_vendor_endpoint', [ $self, 'render_overview' ] );

		// Render the store analytics on the dashboard root (index) for approved
		// vendors, replacing the default customer order list. Priority 5 so the
		// default content (storeengine_dashboard_orders @30) can be removed
		// before it fires. The "Store overview" menu item has been retired.
		add_action( 'storeengine/dashboard/dashboard_content', [ $self, 'render_root_overview' ], 5 );
		add_action( 'storeengine/frontend/dashboard_vendor-products_endpoint', [ $self, 'render_products' ] );
		add_action( 'storeengine/frontend/dashboard_vendor-product-edit_endpoint', [ $self, 'render_product_edit' ] );
		add_action( 'storeengine/frontend/dashboard_vendor-orders_endpoint', [ $self, 'render_orders' ] );
		add_action( 'storeengine/frontend/dashboard_vendor-profile_endpoint', [ $self, 'render_profile' ] );
		add_action( 'storeengine/frontend/dashboard_vendor-payment-method_endpoint', [ $self, 'render_payment_method' ] );
		add_action( 'storeengine/frontend/dashboard_vendor-withdraw_endpoint', [ $self, 'render_withdraw' ] );
		add_action( 'storeengine/frontend/dashboard_vendor-inventory_endpoint', [ $self, 'render_inventory' ] );
		add_action( 'storeengine/frontend/dashboard_vendor-returns_endpoint', [ $self, 'render_returns' ] );
	}

	protected function require_approved_vendor(): ?Vendor {
		$user = wp_get_current_user();
		if ( ! $user || ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			echo '<p>' . esc_html__( 'You do not have access to this page.', 'storeengine' ) . '</p>';
			return null;
		}

		$vendor = new Vendor( (int) $user->ID );
		if ( ! $vendor->is_approved() ) {
			echo '<p>' . esc_html__( 'Your vendor application is awaiting review.', 'storeengine' ) . '</p>';
			return null;
		}
		return $vendor;
	}

	protected function render( string $relative, array $args = [] ): void {
		Helper::get_template( self::TEMPLATE_BASE . $relative, $args );
	}

	public function render_overview() {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor.php', [ 'vendor' => $vendor ] );
	}

	/**
	 * Dashboard root (index) content for approved vendors: show the store
	 * analytics instead of the customer order list. Non-vendors and unapproved
	 * vendors fall through untouched so they keep the default dashboard.
	 */
	public function render_root_overview() {
		$user = wp_get_current_user();
		if ( ! $user || ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return;
		}
		$vendor = new Vendor( (int) $user->ID );
		if ( ! $vendor->is_approved() ) {
			return;
		}

		// Suppress the default customer order list so the root shows only the
		// store overview (mirrors what the standalone page used to render).
		remove_action( 'storeengine/dashboard/dashboard_content', 'storeengine_dashboard_orders', 30 );

		$this->render( 'frontend-dashboard/pages/vendor.php', [ 'vendor' => $vendor ] );
	}

	public function render_products() {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor-products.php', [ 'vendor' => $vendor ] );
	}

	public function render_product_edit( $sub_page = '' ) {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor-product-edit.php', [
			'vendor'     => $vendor,
			'product_id' => absint( $sub_page ),
		] );
	}

	public function render_orders() {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor-orders.php', [ 'vendor' => $vendor ] );
	}

	public function render_profile() {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor-profile.php', [ 'vendor' => $vendor ] );
	}

	public function render_payment_method() {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor-payment-method.php', [ 'vendor' => $vendor ] );
	}

	public function render_withdraw() {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor-withdraw.php', [ 'vendor' => $vendor ] );
	}

	public function render_inventory() {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor-inventory.php', [ 'vendor' => $vendor ] );
	}

	public function render_returns() {
		$vendor = $this->require_approved_vendor();
		if ( ! $vendor ) {
			return;
		}
		$this->render( 'frontend-dashboard/pages/vendor-returns.php', [ 'vendor' => $vendor ] );
	}
}
