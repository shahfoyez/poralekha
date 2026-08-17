<?php

namespace StoreEngine\Ajax;

use StoreEngine\Admin\Settings\Base as BaseSettings;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Utils\Helper;
use WP_Error;

class Setup extends AbstractAjaxHandler {

	/**
	 * Request namespace.
	 *
	 * @var string
	 */
	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '/setup';

	public function __construct() {
		$this->actions = [
			'generate_store_pages' => [
				'callback'   => [ $this, 'generate_store_pages' ],
				'capability' => 'manage_options',
				'fields'     => [
					'use_prefix'    => 'boolean',
					'use_woo_pages' => 'boolean',
				]
			],
			'activate_plugin'      => [
				'callback'   => [ $this, 'activate_plugin' ],
				'capability' => 'manage_options',
				'fields'     => [
					'slug'   => 'string',
					'name'   => 'string',
					'plugin' => 'string',
				]
			],
			// One-click download + install + activate for the free companion
			// plugins (Payments, Connectors, Bricks/Elementor addons). The
			// package URL is resolved server-side from the plugin key — never
			// client-supplied.
			'install_satellite'    => [
				'callback'   => [ $this, 'install_satellite' ],
				'capability' => 'install_plugins',
				'fields'     => [
					'plugin' => 'string',
				]
			]
		];
	}

	public static function install_satellite( array $payload ) {
		$key = $payload['plugin'] ?? '';

		if ( ! in_array( $key, \StoreEngine\Admin\SatellitePlugins::get_keys(), true ) ) {
			return new WP_Error( 'storeengine_unknown_satellite', __( 'Unknown plugin.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$result = \StoreEngine\Admin\SatellitePlugins::install_and_activate( $key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Return the refreshed teaser state so the UI can react without a reload.
		$result['satellite_plugins'] = \StoreEngine\Admin\SatellitePlugins::get_teaser_data();

		return $result;
	}

	public static function activate_plugin( array $payload ) {
		if ( empty( $payload['name'] ) || empty( $payload['slug'] ) || empty( $payload['plugin'] ) ) {
			wp_send_json_error( __( 'No plugin specified.', 'storeengine' ) );
		}

		if ( ! current_user_can( 'activate_plugin', $payload['plugin'] ) ) {
			wp_send_json_error( __( 'Sorry, you are not allowed to activate plugin.', 'storeengine' ) );
		}

		if ( Helper::is_plugin_active( $payload['plugin'] ) ) {
			wp_send_json_error( sprintf(
			/* translators: %s: Plugin name. */
				__( '%s is already active.', 'storeengine' ),
				$payload['name']
			) );
		}

		$activated = Helper::activate_plugin( $payload['plugin'], '', false, true );

		if ( is_wp_error( $activated ) ) {
			wp_send_json_error( $activated->get_error_message() );
		}

		wp_send_json_success( sprintf(
		/* translators: %s: Plugin name. */
			__( '%s has been activated.', 'storeengine' ),
			$payload['name']
		) );
	}

	/**
	 * @param array $payload
	 *
	 * @return void|WP_Error
	 */
	public function generate_store_pages( array $payload ) {
		global $storeengine_settings;

		$use_woo_pages = $payload['use_woo_pages'] ?? false;
		$use_prefix    = $payload['use_prefix'] ?? false;

		if ( $use_woo_pages && Helper::is_plugin_installed( 'woocommerce/woocommerce.php' ) ) {
			$wc_pages = self::get_woo_store_pages();

			// Prepare settings data for update.
			$settings = (array) $storeengine_settings;

			foreach ( Helper::get_store_page_contents() as $key => $page ) {
				if ( $wc_pages[ $key ] ) {
					$updated = wp_update_post( [
						'ID'             => $wc_pages[ $key ],
						'post_status'    => 'publish',
						'post_content'   => $page[ $key ]['content'] ?? '',
						'comment_status' => 'closed',
					], true );

					if ( is_wp_error( $updated ) ) {
						return $updated;
					}

					// Set template for handling block-theme compatibility.
					update_post_meta( $wc_pages[ $key ], '_wp_page_template', 'storeengine-canvas.php' );

					$settings[ $key ] = $wc_pages[ $key ];
				} else {
					$parent  = ! empty( $page['parent'] ) ? Helper::get_settings( $page['parent'], 0 ) : 0;
					$status  = ! empty( $page['post_status'] ) ? $page['post_status'] : 'publish';
					$post_id = Helper::create_page(
						esc_sql( $page['slug'] ),
						$key,
						$page['title'],
						$page['content'],
						$parent,
						$status
					);

					if ( ! $post_id ) {
						return new WP_Error(
							'failed-to-create-' . $key,
							sprintf(
								// translators: %s. Core page name.
								__( 'Failed to create %s page', 'storeengine' ),
								$page['title']
							)
						);
					}

					$settings[ $key ] = $post_id;
				}
			}

			BaseSettings::save_settings( $settings );

			wp_send_json_success( self::get_page_settings() );
		}

		if ( $use_prefix ) {
			Helper::create_initial_pages( true );

			wp_send_json_success( self::get_page_settings() );
		}

		if ( Helper::is_plugin_installed( 'woocommerce/woocommerce.php' ) ) {
			if ( ! empty( self::get_woo_store_pages() ) ) {
				wp_send_json_success( [ 'should_use_woo_pages' => true ] );
			}
		}

		// Create pages without prefix if no wc pages are found.
		Helper::create_initial_pages();
		wp_send_json_success( self::get_page_settings() );
	}

	/**
	 * Get WooCommere store pages.
	 * WC store pages mapped with StoreEngine store page setting keys.
	 * WC doesn't have any separate thankyou page.
	 *
	 * @return array
	 * @see AssetDataRegistry::get_store_pages()
	 * @see WCAdminHelper::is_current_page_store_page()
	 */
	protected static function get_woo_store_pages(): array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return [];
		}

		return array_filter(
			[
				'shop_page'      => wc_get_page_id( 'shop' ),
				'cart_page'      => wc_get_page_id( 'cart' ),
				'checkout_page'  => wc_get_page_id( 'checkout' ),
				'dashboard_page' => wc_get_page_id( 'myaccount' ),
			],
			[ __CLASS__, 'filter_valid_page' ]
		);
	}

	protected static function get_woo_other_pages(): array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return [];
		}

		return array_filter(
			[
				'coming_soon_page' => wc_get_page_id( 'coming_soon' ),
				'privacy_page'     => get_option( 'wp_page_for_privacy_policy', 0 ),
				'terms_page'       => wc_get_page_id( 'terms' ),
			],
			[ __CLASS__, 'filter_valid_page' ]
		);
	}

	public static function filter_valid_page( $page_id ): bool {
		return (bool) get_post( $page_id );
	}

	protected static function get_page_settings(): array {
		// Refresh settings.
		\StoreEngine\Admin\Settings::load_settings();

		$pages = [
			'shop_page',
			'cart_page',
			'checkout_page',
			'thankyou_page',
			'dashboard_page',
			'affiliate_registration_page',
			'membership_pricing_page',
		];

		$settings = [];

		foreach ( $pages as $page ) {
			$settings[ $page ] = Helper::get_settings( $page );
		}

		return array_filter( $settings, [ __CLASS__, 'filter_valid_page' ] );
	}
}
