<?php
namespace ABlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PermalinkRewrite {
	public static function init() {
		$self = new self();
		add_filter( 'query_vars', array( $self, 'register_query_vars' ) );
		add_action( 'generate_rewrite_rules', array( $self, 'add_rewrite_rules' ) );
	}
	public function register_query_vars( $query_vars ) {
		$query_vars[] = 'ablocks_dashboard_page';
		$query_vars[] = 'ablocks_dashboard_sub_page';
		return $query_vars;
	}
	public function add_rewrite_rules( $wp_rewrite ) {
		// Frontend Dashboard
		$dashboard_page_id = (int) Helper::get_settings( 'frontend_dashboard_page' );
		if ( ! $dashboard_page_id ) {
			return;
		}
		$dashboard_page_slug = get_post_field( 'post_name', $dashboard_page_id );
		$dashboard_pages = json_decode( get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ), true );
		foreach ( $dashboard_pages as $dashboard_page ) {
			$dashboard_key = $dashboard_page['slug'];
			$new_rules[ "({$dashboard_page_slug})/{$dashboard_key}/?$" ] = 'index.php?pagename=' . $wp_rewrite->preg_index( 1 ) . '&ablocks_dashboard_page=' . $dashboard_key;
			$new_rules[ "({$dashboard_page_slug})/{$dashboard_key}/(.+?)/?$" ] = 'index.php?pagename=' . $wp_rewrite->preg_index( 1 ) . '&ablocks_dashboard_page=' . $dashboard_key . '&ablocks_dashboard_sub_page=' . $wp_rewrite->preg_index( 2 );
			// Child Items
			if ( isset( $dashboard_page['child_items'] ) && is_array( $dashboard_page['child_items'] ) ) {
				foreach ( $dashboard_page['child_items'] as $child_key => $child_page ) {
					$new_rules[ "({$dashboard_page_slug})/{$dashboard_key}/{$child_key}/?$" ] = 'index.php?pagename=' . $wp_rewrite->preg_index( 1 ) . '&ablocks_dashboard_page=' . $dashboard_key;
					$new_rules[ "({$dashboard_page_slug})/{$dashboard_key}/{$child_key}/(.+?)/?$" ] = 'index.php?pagename=' . $wp_rewrite->preg_index( 1 ) . '&ablocks_dashboard_page=' . $dashboard_key . '&ablocks_dashboard_sub_page=' . $child_key;
				}
			}
		}

		$wp_rewrite->rules = ( $new_rules ?? [] ) + $wp_rewrite->rules;
	}
}
