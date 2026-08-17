<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;
use StoreEngine\Addons\MultiVendor\Classes\Vendors;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shop archive: filter products by vendor via ?vendor_slug=foo.
 *
 * Hooks pre_get_posts on the product archive (and storefront product loop) to
 * limit results to a vendor's published products. Renders a filter UI in the
 * archive sidebar/header listing every approved & visible vendor with a count.
 */
class ShopFilter {

	const QUERY_VAR = 'vendor_slug';

	public static function init() {
		$self = new self();
		add_filter( 'query_vars', [ $self, 'register_query_var' ] );
		add_action( 'pre_get_posts', [ $self, 'scope_archive_query' ] );
		// Render the vendor dropdown at the top of the archive sidebar (above the
		// search/category filter widgets, which run at the default priority 10).
		add_action( 'storeengine/templates/archive_product_sidebar_content', [ $self, 'render_sidebar_filter' ], 5 );
	}

	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function scope_archive_query( WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		// Only on the product archive.
		if ( ! $query->is_post_type_archive( Helper::PRODUCT_POST_TYPE ) && ! is_tax( [
			'storeengine_product_category',
			'storeengine_product_tag',
		] ) ) {
			return;
		}

		$slug = (string) get_query_var( self::QUERY_VAR );
		if ( ! $slug ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public shop archive filter; sanitized GET value, no state change.
			$slug = isset( $_GET['vendor'] ) ? sanitize_title( wp_unslash( $_GET['vendor'] ) ) : '';
		}
		if ( ! $slug ) {
			return;
		}
		$vendor = Vendors::get_by_slug( $slug );
		if ( ! $vendor || ! $vendor->is_approved() ) {
			return;
		}
		$query->set( 'author', $vendor->get_user_id() );
	}

	/**
	 * Render the vendor dropdown inside the archive sidebar. Visible vendors
	 * only — default/owner vendor with `is_visible=0` is hidden from the list.
	 */
	public function render_sidebar_filter() {
		$current = (string) get_query_var( self::QUERY_VAR );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only public shop archive filter; sanitized GET value, no state change.
		if ( ! $current && isset( $_GET['vendor'] ) ) {
			$current = sanitize_title( wp_unslash( $_GET['vendor'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$vendors = Vendors::query( [ 'status' => 'approved', 'limit' => 100 ] );
		$vendors = array_filter( $vendors, fn( Vendor $v ) => $v->is_visible() );
		if ( empty( $vendors ) ) {
			return;
		}

		echo '<div class="storeengine-archive-product-widget storeengine-archive-product-widget--vendor">';
		echo '<div class="storeengine-vendor-filter">';
		echo '<form method="get" class="storeengine-vendor-filter__form">';

		// Preserve other query params except `vendor`.
		foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'vendor' === $k ) {
				continue;
			}
			echo '<input type="hidden" name="' . esc_attr( (string) $k ) . '" value="' . esc_attr( wp_unslash( (string) $v ) ) . '">';
		}

		echo '<span class="storeengine-vendor-filter__control">';
		echo '<select id="storeengine-vendor-filter-select" class="storeengine-vendor-filter__select" name="vendor" onchange="this.form.submit()" aria-label="' . esc_attr__( 'Filter by vendor', 'storeengine' ) . '">';
		echo '<option value="">' . esc_html__( 'All vendors', 'storeengine' ) . '</option>';
		foreach ( $vendors as $vendor ) {
			$slug = $vendor->get_store_slug();
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $current, $slug, false ) . '>'
				. esc_html( $vendor->get_store_name() )
				. '</option>';
		}
		echo '</select>';
		echo '</span>';

		if ( $current ) {
			echo '<a href="' . esc_url( remove_query_arg( 'vendor' ) ) . '" class="storeengine-vendor-filter__clear">'
				. esc_html__( 'Clear', 'storeengine' ) . '</a>';
		}

		echo '</form></div></div>';
	}
}
