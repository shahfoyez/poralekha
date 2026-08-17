<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;
use StoreEngine\Addons\MultiVendor\Classes\Vendors;
use StoreEngine\Utils\Helper;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public vendor store page at /vendor/{slug}/.
 *
 * Registers a query var, rewrite rule, and a template_include hook that swaps
 * the standard product archive query for one scoped to the vendor's user_id
 * and renders a vendor-store.php template.
 */
class StorePage {

	const QUERY_VAR  = 'storeengine_vendor';
	const URL_PREFIX = 'vendor';

	public static function init() {
		$self = new self();
		add_filter( 'query_vars', [ $self, 'register_query_var' ] );
		add_action( 'init', [ $self, 'register_rewrite' ] );
		add_action( 'pre_get_posts', [ $self, 'scope_main_query' ] );
		add_filter( 'template_include', [ $self, 'load_template' ], 99 );
	}

	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function register_rewrite() {
		add_rewrite_rule(
			'^' . self::URL_PREFIX . '/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . self::URL_PREFIX . '/([^/]+)/page/([0-9]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]&paged=$matches[2]',
			'top'
		);
	}

	/**
	 * Returns the active store-page vendor or null when this is not a store URL.
	 */
	public static function current_vendor(): ?Vendor {
		$slug = get_query_var( self::QUERY_VAR );
		if ( ! $slug ) {
			return null;
		}
		$vendor = Vendors::get_by_slug( (string) $slug );
		if ( ! $vendor || ! $vendor->is_approved() ) {
			return null;
		}
		return $vendor;
	}

	/**
	 * Scope the main query to the vendor's published products.
	 */
	public function scope_main_query( WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$slug = $query->get( self::QUERY_VAR );
		if ( ! $slug ) {
			return;
		}
		$vendor = Vendors::get_by_slug( (string) $slug );
		if ( ! $vendor ) {
			return;
		}

		$query->set( 'post_type', Helper::PRODUCT_POST_TYPE );
		$query->set( 'post_status', 'publish' );
		$query->set( 'author', $vendor->get_user_id() );
		$query->set( 'posts_per_page', (int) get_option( 'posts_per_page', 12 ) );
		$query->is_home    = false;
		$query->is_archive = true;
	}

	/**
	 * Load the vendor store template when the URL matches.
	 */
	public function load_template( $template ) {
		$slug = get_query_var( self::QUERY_VAR );
		if ( ! $slug ) {
			return $template;
		}

		$vendor = Vendors::get_by_slug( (string) $slug );
		if ( ! $vendor || ! $vendor->is_approved() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return get_404_template() ?: $template;
		}

		// Theme override: themes can drop `storeengine/multi-vendor/vendor-store.php`.
		$theme = locate_template( [ 'storeengine/multi-vendor/vendor-store.php' ] );
		if ( $theme ) {
			return $theme;
		}

		// Core default: templates/multi-vendor/vendor-store.php.
		return STOREENGINE_ROOT_DIR_PATH . 'templates/multi-vendor/vendor-store.php';
	}

	/**
	 * Public URL for a given vendor.
	 */
	public static function url_for( Vendor $vendor ): string {
		if ( ! $vendor->get_store_slug() ) {
			return home_url( '/' );
		}
		return home_url( '/' . self::URL_PREFIX . '/' . $vendor->get_store_slug() . '/' );
	}
}
