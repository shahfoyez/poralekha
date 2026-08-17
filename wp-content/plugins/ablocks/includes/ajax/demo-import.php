<?php

namespace ABlocks\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AbstractAjaxHandler;
use ABlocks\Classes\Sanitizer;
use ABlocks\Helper;
use ABlocks\import\CustomizerImporter;
use ABlocks\import\ThemeOptions\CodeStar;
use ABlocks\import\ThemeOptions\Redux;
use ABlocks\import\WidgetImporter;
use ABlocksThemeBuilder\Database;

class DemoImport extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = array(
			'get_demo_list'       => array(
				'callback'   => array( $this, 'get_demo_list' ),
				'capability' => 'manage_options',
				'fields'     => array(
					'type'      => 'string',
					'cost_type' => 'string',
					'page'      => 'integer',
					'per_page'  => 'integer',
					'category'  => 'string',
					'dev'       => 'string',
					'q'         => 'string',
				)
			),
			'get_single_demo'     => array(
				'callback'   => array( $this, 'get_single_demo' ),
				'capability' => 'manage_options',
				'fields'     => array(
					'id'          => 'integer',
					'with_images' => 'string',
				)
			),
			'get_demo_categories' => array(
				'callback'   => array( $this, 'get_demo_categories' ),
				'capability' => 'manage_options',
				'fields'     => array(
					'type' => 'string',
				)
			),
			'get_theme_demos'     => array(
				'callback'   => array( $this, 'get_theme_demos' ),
				'capability' => 'manage_options',
			),
			'check_dependencies'  => array(
				'callback'   => array( $this, 'check_dependencies' ),
				'capability' => 'manage_options',
				'fields'     => array(
					'dependencies' => 'array|string',
				)
			),
			'install_and_active'  => array(
				'callback'   => array( $this, 'install_and_active' ),
				'capability' => 'manage_options',
				'fields'     => array(
					'dependency' => 'array|string',
				)
			),
			'import_template'     => array(
				'callback'   => array( $this, 'import_template' ),
				'capability' => 'manage_options',
				'fields'     => array(
					'file_url'            => 'string',
					'customizer_file_url' => 'string',
					'widget_file_url'     => 'string',
					'codestar_file_url'   => 'string',
					'redux_options'       => 'json',
					'with_images'         => 'boolean',
				)
			),
		);
	}

	/**
	 * Get demo list data from database, if not exists, then get & store data from API.
	 *
	 * @param array $data Query Data for API.
	 *
	 * @return void
	 */
	public function get_demo_list( array $data ) {
		$args = [
			'type'      => $data['type'] ?? 'pattern',
			'cost_type' => $data['cost_type'] ?? 'all',
			'page'      => $data['page'] ?? 1,
		];

		if ( isset( $data['category'] ) ) {
			$args['category'] = $data['category'];
		}

		if ( isset( $data['dev'] ) ) {
			$args['dev'] = $data['dev'];
		}

		if ( isset( $data['q'] ) ) {
			$args['q'] = $data['q'];
		}

		if ( isset( $data['per_page'] ) ) {
			$args['per_page'] = $data['per_page'];
		}

		$hash         = md5( wp_json_encode( $args ) );
		$key          = "ablocks_demo_$hash";
		$has_response = get_transient( $key );
		if ( $has_response ) {
			wp_send_json_success( $has_response );
		}

		$url = 'https://' . ABLOCKS_TEMPLATE_LIB_HOST . '/wp-json/ablocks_server/v1/ablocks';
		$url = add_query_arg( $args, $url );
		$this->handle_list_response( $url, $key );
	}

	/**
	 * Get single demo data from database, if not exists, then get & store data from API.
	 *
	 * @param array $data inside data `id` is required.
	 *
	 * @return void
	 */
	public function get_single_demo( array $data ) {
		if ( ! $data['id'] ) {
			wp_send_json_error( __( 'id is required.', 'ablocks' ) );
		}
		$id          = absint( $data['id'] );
		$with_images = isset( $data['with_images'] ) && true === (bool) $data['with_images'];

		$key          = 'ablocks_demo_item_' . $id;
		$has_response = get_transient( $key );
		if ( $has_response ) {
			if ( $with_images && ! isset( $has_response['remote_images_parsed'] ) ) {
				$has_response['content']              = Helper::parse_remote_images( $has_response['content'] );
				$has_response['remote_images_parsed'] = true;
				set_transient( $key, $has_response, WEEK_IN_SECONDS );
			}
			unset( $has_response['remote_images_parsed'] );
			wp_send_json_success( $has_response );
		}

		$url      = 'https://' . ABLOCKS_TEMPLATE_LIB_HOST . '/wp-json/ablocks_server/v1/ablocks/' . $id;
		$response = wp_safe_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response      = wp_remote_retrieve_body( $response );
		$response      = json_decode( $response, true );
		if ( $response_code !== 200 ) {
			wp_send_json_error( $response );
		}

		$response = $response['data'];
		if ( $with_images ) {
			$content = $response['content'] ?? null;
			if ( $content ) {
				$response['content']              = Helper::parse_remote_images( wp_unslash( $content ) );
				$response['remote_images_parsed'] = true;
			}
		}
		set_transient( $key, $response, WEEK_IN_SECONDS );
		unset( $response['remote_images_parsed'] );
		wp_send_json_success( $response );
	}

	/**
	 * Get demo category list data from database, if not exists, then get & store data from API.
	 *
	 * @param array $data Query Data for Category list API.
	 *
	 * @return void
	 */
	public function get_demo_categories( array $data ) {
		$type         = $data['type'] ?? 'pattern';
		$key          = "ablocks_demo_categories_$type";
		$has_response = get_transient( $key );
		if ( $has_response ) {
			wp_send_json_success( $has_response );
		}

		$url = 'https://' . ABLOCKS_TEMPLATE_LIB_HOST . '/wp-json/ablocks_server/v1/categories';
		$url = add_query_arg( 'type', $type, $url );
		$this->handle_list_response( $url, $key );
	}

	/**
	 * Get theme demos.
	 *
	 * @return void
	 */
	public function get_theme_demos() {
		$demo_config = Helper::get_theme_demo_config();
		if ( ! $demo_config ) {
			wp_send_json_error( [
				'message' => 'Current theme demo isn\'t configured.',
			] );
		}

		$demos      = $demo_config['demos'];
		$categories = [];
		foreach ( $demos as &$demo ) {
			$demo_categories = [];
			foreach ( $demo['categories'] as $category ) {
				if ( is_array( $category ) ) {
					$categories[]      = $category;
					$demo_categories[] = $category;
				} else {
					$demo_category     = [
						'label' => ucfirst( $category ),
						'slug'  => str_replace( ' ', '-', trim( $category ) ),
					];
					$categories[]      = $demo_category;
					$demo_categories[] = $demo_category;
				}
			}
			$demo['categories'] = $demo_categories;
		}

		$preloaded_categories = array_map( fn( $category ) => is_array( $category ) ? $category : [
			'label' => ucfirst( $category ),
			'slug'  => str_replace( ' ', '-', trim( $category ) ),
		], $demo_config['preloaded_demo_category'] );

		$categories_slugs = array_map( fn( $category ) => $category['slug'], $categories );
		foreach ( $preloaded_categories as $preloaded_category ) {
			if ( ! in_array( $preloaded_category['slug'], $categories_slugs, true ) ) {
				$categories[] = $preloaded_category;
			}
		}

		wp_send_json_success( array(
			'categories'                => $categories,
			'preloaded_demo'            => $demo_config['preloaded_demo'],
			'preloaded_demo_categories' => $preloaded_categories,
			'demos'                     => $demos,
		) );
	}

	/**
	 * Check dependencies before importing pattern/page/template.
	 *
	 * @param array $data Dependency Data.
	 *
	 * @return void
	 */
	public function check_dependencies( array $data ) {
		if ( ! isset( $data['dependencies'] ) ) {
			return;
		}

		$dependencies = Sanitizer::sanitize_array_field( $data['dependencies'] );
		foreach ( $dependencies as $index => $dependency ) {
			$type                             = 'check_' . ( $dependency['type'] ?? 'plugin' );
			$status                           = Helper::$type( $dependency['slug'] );
			$dependencies[ $index ]['status'] = $status;
			if ( isset( $dependency['id'] ) ) {
				$dependencies[ $index ]['id'] = (int) $dependency['id'];
			}
		}

		wp_send_json_success( $dependencies );
	}

	/**
	 * Install and active theme/plugin from ajax request.
	 *
	 * @param array $data post-data.
	 *
	 * @return void
	 */
	public function install_and_active( array $data ) {
		if ( ! isset( $data['dependency'] ) ) {
			return;
		}
		$dependency = Sanitizer::sanitize_array_field( $data['dependency'] );
		$method     = 'install_and_active_' . ( $dependency['type'] ?? 'plugin' );
		$activated  = Helper::$method( $dependency['slug'] );
		if ( is_wp_error( $activated ) ) {
			wp_send_json_error( array(
				'message' => $activated->get_error_message(),
			) );
		}

		wp_send_json_success();
	}

	/**
	 * Import template from file url.
	 *
	 * @param array $data Import data.
	 *
	 * @return void
	 */
	public function import_template( array $data ) {
		if ( ! isset( $data['file_url'] ) ) {
			return;
		}

		$file_url          = $data['file_url'];
		$content_file_path = Helper::remote_to_tmp_file( $file_url );

		$customizer_file_url  = $data['customizer_file_url'] ?? '';
		$customizer_file_path = Helper::remote_to_tmp_file( $customizer_file_url );
		$widget_file_url      = $data['widget_file_url'] ?? '';
		$widget_file_path     = Helper::remote_to_tmp_file( $widget_file_url );
		$codestar_file_url    = $data['codestar_file_url'] ?? '';
		$codestar_file_path   = Helper::remote_to_tmp_file( $codestar_file_url );
		$with_images          = isset( $data['with_images'] ) && true === $data['with_images'];

		// Start the event stream.
		header( 'Content-Type: text/event-stream, charset=UTF-8' );
		header( 'Cache-Control: no-store' );
		header( 'Connection: keep-alive' );

		// Turn off PHP output compression.
		// phpcs:disable WordPress.PHP.IniSet.Risky
		ini_set( 'output_buffering', 'off' );
		ini_set( 'zlib.output_compression', false );
		// phpcs:enable WordPress.PHP.IniSet.Risky

		if ( $GLOBALS['is_nginx'] ) {
			// Setting this header instructs Nginx to disable fast-cgi buffering
			// and disable gzip for this request.
			header( 'X-Accel-Buffering: no' );
			header( 'Content-Encoding: none' );
		}

		echo esc_html( ':' . str_repeat( ' ', 2048 ) . "\n\n" ); // 2KB padding for IE.

		set_time_limit( 0 );

		// Ensure we're not buffered.
		wp_ob_end_flush_all();
		flush();

		if ( is_wp_error( $content_file_path ) ) {
			Helper::emit_sse_message( [
				'action' => 'complete',
				'error'  => is_string( $codestar_file_path->get_error_message() ) && ! empty( $codestar_file_path->get_error_message() ) ? $codestar_file_path->get_error_code() : __( 'Invalid content path.', 'ablocks' ),
			] );
			exit;
		}

		do_action( 'ablocks/import/before_template_import_start', $data );

		if ( ! Helper::get_addon_active_status( 'theme-builder' ) && ! Helper::is_fse_theme() ) {
			$saved_addons = json_decode( get_option( ABLOCKS_ADDONS_SETTINGS_NAME, '{}' ), true );
			if ( ! is_array( $saved_addons ) ) {
				$saved_addons = [];
			}

			$addon_slug                  = 'theme-builder';
			$saved_addons[ $addon_slug ] = true;
			do_action( "ablocks/addons/activated_$addon_slug" );

			update_option( ABLOCKS_ADDONS_SETTINGS_NAME, wp_json_encode( $saved_addons ) );
			( new Database() )->create_ablocks_tb_post_type();
			( new Database() )->register_ablocks_tb_meta();
		}

		$template_import = Helper::import_template( $content_file_path, $with_images );
		if ( ! is_wp_error( $template_import ) ) {
			if ( ! empty( $customizer_file_path ) && ! is_wp_error( $customizer_file_path ) ) {
				CustomizerImporter::import( $customizer_file_path );
			}
			if ( ! empty( $widget_file_path ) && ! is_wp_error( $widget_file_path ) ) {
				WidgetImporter::import( $widget_file_path );
			}
			if ( ! empty( $codestar_file_path ) ) {
				CodeStar::import( $codestar_file_path );
			}
			if ( isset( $data['redux_options'] ) && is_array( $data['redux_options'] ) && count( $data['redux_options'] ) > 0 ) {
				Redux::import( $data['redux_options'] );
			}
		}
		do_action( 'ablocks/import/template_import_finished', $data );

		// Let the browser know we're done.
		$complete = [
			'action' => 'complete',
			'error'  => false,
		];
		if ( is_wp_error( $template_import ) ) {
			$complete['error'] = $template_import->get_error_message();
		}
		Helper::emit_sse_message( $complete );
		exit;
	}

	/**
	 * Handle common list response.
	 *
	 * @param string $url URL.
	 * @param string $key Transient key.
	 *
	 * @return void
	 */
	private function handle_list_response( string $url, string $key ): void {
		global $wp_version;
		$response = wp_safe_remote_get( $url, array(
			'headers' => array(
				'Accept'     => 'application/json',
				'user-agent' => sprintf(
					'aBlocks/%1$s (%2$s; WordPress/%3$s) %4$s',
					ABLOCKS_VERSION,
					get_option( 'blogname' ),
					$wp_version,
					home_url()
				),
			),
		) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message' => $response->get_error_message()
			] );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response      = wp_remote_retrieve_body( $response );
		$response      = json_decode( $response, true );
		if ( $response_code !== 200 || isset( $response['message'] ) ) {
			wp_send_json_error( [
				'message' => $response['message']
			] );
		}

		set_transient( $key, $response, WEEK_IN_SECONDS );
		wp_send_json_success( $response );
	}
}
