<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Affiliate\Affiliate;
use StoreEngine\Addons\Membership\Membership;
use StoreEngine\Admin\ScheduledActionsTool;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Utils\Helper;

class Tools extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'fetch_storeengine_status'              => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'fetch_storeengine_status' ],
			],
			'fetch_storeengine_pages'               => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'fetch_storeengine_pages' ],
			],
			'regenerate_storeengine_pages'          => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'regenerate_storeengine_pages' ],
			],
			'fetch_storeengine_scheduled_actions'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'fetch_storeengine_scheduled_actions' ],
			],
			'reset_storeengine_scheduled_actions'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'reset_storeengine_scheduled_actions' ],
				'fields'     => [
					'hooks'         => self::STR_ARR,
					'include_logs'  => self::BOOLEAN,
					'cleanup_tools' => self::STR_ARR,
				],
			],
		];
	}

	/**
	 * Registry of "data cleanup" tools surfaced under the Action Scheduler tab.
	 * Addons can attach entries via the `storeengine/tools/data_cleanup_tools`
	 * filter — each entry pairs UI metadata with a server-side callback that
	 * performs the cleanup. Used when cancelling pending actions alone is not
	 * enough (e.g. also truncating addon-owned tables).
	 *
	 * @return array<int, array{id:string, label:string, description:string, confirm?:string, callback:callable}>
	 */
	public static function get_data_cleanup_tools(): array {
		return array_values( array_filter(
			(array) apply_filters( 'storeengine/tools/data_cleanup_tools', [] ),
			static fn( $t ) => is_array( $t )
				&& ! empty( $t['id'] )
				&& ! empty( $t['label'] )
				&& ! empty( $t['callback'] )
				&& is_callable( $t['callback'] )
		) );
	}

	protected function fetch_storeengine_status() {
		$tools = new \StoreEngine\Classes\Tools();

		wp_send_json_success( [
			'wordpress' => $tools->get_wordpress_environment_status(),
			'server'    => $tools->get_server_environment_status(),
		] );
	}

	protected function fetch_storeengine_pages() {
		global $storeengine_settings;
		$pages = apply_filters( 'storeengine/settings/tools/pages', [
			'shop_page'      => __( 'Store Shop', 'storeengine' ),
			'cart_page'      => __( 'Store Cart', 'storeengine' ),
			'checkout_page'  => __( 'Store Checkout', 'storeengine' ),
			'thankyou_page'  => __( 'Store Thank You', 'storeengine' ),
			'dashboard_page' => __( 'Store Dashboard', 'storeengine' ),
		] );

		$response = [];
		$idx      = 0;
		foreach ( $pages as $key => $label ) {
			$page       = Helper::get_settings( $key );
			$page       = $page ? get_post( $page ) : false;
			$response[] = [
				'key'         => $key,
				'index'       => ++$idx,
				'ID'          => $page ? $page->ID : null,
				'post_title'  => $page ? $page->post_title : $label,
				'post_name'   => $page ? $page->post_name : null,
				'post_status' => $page ? $page->post_status : null,
				'permalink'   => $page ? get_permalink( $page ) : null,
				'edit_link'   => $page && current_user_can( 'manage_options' ) ? get_edit_post_link( $page ) : null,
			];
		}

		wp_send_json_success( $response );
	}

	protected function regenerate_storeengine_pages() {
		Helper::create_initial_pages();
		wp_send_json_success();
	}

	/**
	 * Return per-hook pending/failed counts for the StoreEngine Action
	 * Scheduler queue. Used by the "Action Scheduler" tab in Tools.
	 */
	protected function fetch_storeengine_scheduled_actions(): array {
		$rows  = ScheduledActionsTool::get_hook_counts();
		$total = 0;
		foreach ( $rows as $row ) {
			$total += $row['pending'];
		}

		// Strip callbacks before sending to the client — closures and array
		// callbacks aren't safe to JSON-serialise and the UI doesn't need them.
		$cleanup_tools = array_map( static function ( array $tool ): array {
			return [
				'id'          => (string) $tool['id'],
				'label'       => (string) $tool['label'],
				'description' => (string) ( $tool['description'] ?? '' ),
				'confirm'     => (string) ( $tool['confirm'] ?? '' ),
			];
		}, self::get_data_cleanup_tools() );

		return [
			'rows'               => $rows,
			'total'              => $total,
			'logs_table'         => $GLOBALS['wpdb']->prefix . 'actionscheduler_logs',
			'known_hooks'        => ScheduledActionsTool::get_known_hooks(),
			'data_cleanup_tools' => $cleanup_tools,
		];
	}

	/**
	 * Cancel pending/failed Action Scheduler events for the requested hooks,
	 * optionally truncate AS logs, and optionally run one or more addon-supplied
	 * data cleanup callbacks (e.g. truncating an addon-owned records table).
	 * All three actions are handled in a single request so the UI can update
	 * in one round-trip.
	 *
	 * @param array{hooks?: string[], include_logs?: bool, cleanup_tools?: string[]} $payload
	 */
	protected function reset_storeengine_scheduled_actions( array $payload ): array {
		$requested       = isset( $payload['hooks'] ) && is_array( $payload['hooks'] ) ? $payload['hooks'] : [];
		$tools_requested = isset( $payload['cleanup_tools'] ) && is_array( $payload['cleanup_tools'] ) ? $payload['cleanup_tools'] : [];
		$known           = array_flip( ScheduledActionsTool::get_known_hooks() );
		$cancelled       = 0;
		$cleared         = [];

		foreach ( $requested as $hook ) {
			if ( ! isset( $known[ $hook ] ) ) {
				continue;
			}
			$n           = ScheduledActionsTool::cancel_hook( $hook );
			$cancelled  += $n;
			$cleared[]   = [ 'hook' => $hook, 'count' => $n ];
		}

		if ( ! empty( $payload['include_logs'] ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}actionscheduler_logs" );
		}

		// Run requested data-cleanup tool callbacks (e.g. addon table purges).
		// Tools are looked up by id against the filter-registered registry so
		// the client can't invoke arbitrary callables.
		$tool_results = [];
		if ( $tools_requested ) {
			$available = [];
			foreach ( self::get_data_cleanup_tools() as $tool ) {
				$available[ $tool['id'] ] = $tool;
			}
			foreach ( $tools_requested as $tool_id ) {
				if ( ! isset( $available[ $tool_id ] ) ) {
					continue;
				}
				$tool_results[ $tool_id ] = (array) call_user_func( $available[ $tool_id ]['callback'] );
			}
		}

		return [
			'cancelled'    => $cancelled,
			'cleared'      => $cleared,
			'tool_results' => $tool_results,
			'rows'         => ScheduledActionsTool::get_hook_counts(),
		];
	}
}
