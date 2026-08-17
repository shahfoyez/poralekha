<?php

namespace StoreEngine;

use stdClass;
use StoreEngine\Classes\FrontendRequestHandler;
use StoreEngine\Compatibility\Elementor;
use StoreEngine\Hooks\Integration;
use StoreEngine\hooks\Kses;
use StoreEngine\hooks\Payment;
use StoreEngine\Hooks\PermissionsAndCapabilities;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Geolocation;
use StoreEngine\Utils\Helper;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	public static function init() {
		PermissionsAndCapabilities::init();
		Formatting::init_hooks();
		Kses::init();
		Payment::init();
		Hooks\Price::init();
		Integration::init();
		Hooks\AfterPurchase::init();

		// Compatibility with external plugins
		Elementor::init();

		/**
		 * Excludes order comments from generic comment queries and feeds.
		 */
		add_filter( 'comments_clauses', [ __CLASS__, 'exclude_order_comments' ] );
		add_filter( 'comment_feed_where', [ __CLASS__, 'exclude_order_comments_from_feed_where' ] );

		FrontendRequestHandler::init();

		add_filter( 'storeengine/template/get_content', [ __CLASS__, 'remove_empty_spaces' ], -10, 2 );
		add_filter( 'do_shortcode_tag', [ __CLASS__, 'remove_empty_spaces' ], -10, 2 );

		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 2 );
		add_action( 'delete_post', [ __CLASS__, 'delete_post' ], 10, 2 );

		add_filter( 'storeengine_settings', [ __CLASS__, 'set_maxmind_database_path' ] );
		add_filter( 'storeengine/api/settings', [ __CLASS__, 'set_maxmind_database_path' ] );
		add_filter( 'storeengine/geolocation/geo-locate-ip', [ Geolocation::class, 'maxmind_locate_ip' ], 10, 2 );

		self::handle_cache_last_changed();
	}

	/**
	 * @param stdClass $settings
	 *
	 * @return stdClass
	 */
	public static function set_maxmind_database_path( stdClass $settings ): stdClass {
		$settings->maxmind_db_path = Geolocation::get_maxmind_db_path();

		return $settings;
	}

	/**
	 * @return void
	 * @see update_metadata()
	 * @see delete_metadata()
	 * @see add_metadata()
	 */
	protected static function handle_cache_last_changed() {
		$cache_groups = [
			'payment_token' => 'storeengine_payment_tokenmeta',
			'order_item'    => 'storeengine_order_item_meta',
			'order'         => 'storeengine_orders_meta',
		];

		foreach ( $cache_groups as $meta_type => $cache_group ) {
			add_action( "added_{$meta_type}_meta", fn() => wp_cache_set_last_changed( $cache_group ) );
			add_action( "updated_{$meta_type}_meta", fn() => wp_cache_set_last_changed( $cache_group ) );
			add_action( "deleted_{$meta_type}_meta", fn() => wp_cache_set_last_changed( $cache_group ) );
		}
	}

	/**
	 * Exclude order comments from queries and RSS.
	 *
	 * This code should exclude shop_order comments from queries. Some queries (like the recent comments widget on the dashboard) are hardcoded.
	 * and are not filtered, however, the code current_user_can( 'read_post', $comment->comment_post_ID ) should keep them safe since only admin and.
	 * shop managers can view orders anyway.
	 *
	 * The frontend view order pages get around this filter by removing the order-comment exclusion on the comments_clauses filter.
	 *
	 * @param array $clauses A compacted array of comment query clauses.
	 *
	 * @return array
	 */
	public static function exclude_order_comments( array $clauses ): array {
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type != 'order_note' ";

		return $clauses;
	}

	public static function include_order_comments( array $clauses ): array {
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type = 'order_note' AND comment_agent = 'StoreEngine' ";

		return $clauses;
	}

	/**
	 * Exclude order comments from queries and RSS.
	 *
	 * @param string $where The WHERE clause of the query.
	 *
	 * @return string
	 */
	public static function exclude_order_comments_from_feed_where( string $where ): string {
		return $where . ( $where ? ' AND ' : '' ) . " comment_type != 'order_note' ";
	}

	/**
	 * Cleanup empty extra whitespaces.
	 *
	 * @param mixed|string $content
	 * @param string $tag
	 *
	 * @return mixed|string
	 */
	public static function remove_empty_spaces( $content, string $tag ) {
		if ( ! Helper::is_fse_theme() || ( ! str_starts_with( $tag, 'storeengine_' ) && ! str_starts_with( $tag, 'academy_' ) ) ) {
			return $content;
		}

		return Formatting::clean_html_whitespaces( (string) $content );
	}

	public static function save_post( $post_id, WP_Post $post ) {
		wp_cache_set( 'storeengine:get_page_by_title:' . sanitize_title( $post->post_title ), absint( $post_id ), $post->post_type );
	}

	public static function delete_post( $post_id, WP_Post $post ) {
		wp_cache_delete( 'storeengine:get_page_by_title:' . sanitize_title( $post->post_title ), $post->post_type );
	}
}
