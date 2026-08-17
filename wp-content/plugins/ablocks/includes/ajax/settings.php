<?php

namespace ABlocks\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AbstractAjaxHandler;
use ABlocks\Classes\Sanitizer;
use ABlocks\Helper;
use ABlocks\Admin\Settings\Base as BaseSettings;

class Settings extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = array(
			'get_blocks_visibility'      => array(
				'callback'      => array( $this, 'get_blocks_visibility' ),
				'capability'    => 'manage_options'
			),
			'save_block_visibility'      => array(
				'callback' => array( $this, 'save_block_visibility' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'block_name'        => 'string',
					'status'            => 'boolean',
				)
			),
			'save_bulk_block_visibility'      => array(
				'callback' => array( $this, 'save_bulk_block_visibility' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'blocks'        => 'json',
				)
			),
			'get_settings'      => array(
				'callback' => array( $this, 'get_settings' ),
				'capability'    => 'manage_options',
			),
			'save_settings'      => array(
				'callback' => array( $this, 'save_settings' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'default_container_width' => 'integer',
					'container_padding' => 'integer',
					'container_element_gap' => 'integer',
					'enabled_assets_file_generation' => 'boolean',
					'enabled_block_copy_paste_style' => 'boolean',
					'lock_global_typography' => 'boolean',
					'lock_global_typography_strict' => 'boolean',
					'lock_global_colors' => 'boolean',
					'enabled_load_google_font_locally' => 'boolean',
					'enabled_only_selected_fonts' => 'boolean',
					'font_metric_fallback' => 'boolean',
					'enabled_coming_soon_page' => 'boolean',
					'coming_soon_page' => 'integer',
					'enabled_maintenance_page' => 'boolean',
					'maintenance_page' => 'integer',
					'login_page' => 'integer',
					'registration_page' => 'integer',
					'forget_password_page' => 'integer',
					'frontend_dashboard_page' => 'string',
					'mailchimp_api_key' => 'string',
					'drip_api_key' => 'string',
					'getresponse_api_key' => 'string',
					'convertkit_api_key' => 'string',
					'mailerlite_api_key' => 'string',
					// Performance Suite
					'perf_lazy_images' => 'boolean',
					'perf_lcp_eager_count' => 'integer',
					'perf_image_dimensions' => 'boolean',
					'perf_responsive_images' => 'boolean',
					'perf_preload_lcp' => 'boolean',
					'perf_touch_targets' => 'boolean',
					'perf_inline_css' => 'boolean',
					'perf_async_css' => 'boolean',
					'perf_critical_css' => 'boolean',
					'perf_defer_js' => 'boolean',
					'perf_delay_js' => 'boolean',
					'perf_delay_js_timeout' => 'integer',
					'perf_disable_emojis' => 'boolean',
					'perf_disable_embeds' => 'boolean',
					'perf_disable_dashicons' => 'boolean',
					'perf_disable_jquery_migrate' => 'boolean',
					'perf_control_heartbeat' => 'boolean',
					'perf_heartbeat_frequency' => 'integer',
					// Performance Suite — full-page cache.
					'perf_page_cache' => 'boolean',
					'perf_page_cache_scope' => 'string',
					'perf_page_cache_ttl' => 'integer',
					'perf_page_cache_gzip' => 'boolean',
					'perf_page_cache_prewarm' => 'boolean',
					'perf_page_cache_mobile_variant' => 'boolean',
					'perf_page_cache_crawler' => 'boolean',
					'perf_page_cache_crawl_batch' => 'integer',
					'perf_consolidate_css' => 'boolean',
					'perf_consolidate_css_min' => 'integer',
					'perf_fragment_cache' => 'boolean',
					'perf_fragment_cache_ttl' => 'integer',
					'perf_template_cache' => 'boolean',
					'perf_template_cache_ttl' => 'integer',
					'perf_image_optimize_on_upload' => 'boolean',
					'perf_image_level' => 'string',
					'perf_image_webp' => 'boolean',
					'perf_image_quarantine_autodelete' => 'boolean',
					'perf_image_quarantine_days' => 'integer',
					'perf_image_require_filename' => 'boolean',
					'perf_image_require_alt' => 'boolean',
					// Marketing — query param passthrough
					'param_passthrough_enabled' => 'boolean',
					'param_passthrough_keys' => 'string',
					'param_passthrough_match' => 'string',
					'param_passthrough_class' => 'string',
					'param_passthrough_keyword' => 'string',
					'param_passthrough_persist' => 'boolean',
					'param_passthrough_cookie_days' => 'integer',
					// global color & typography
					'global_color' => 'json',
					'global_typography' => 'json',
					'global_font_family_fallback' => 'string',
					'global_body_text_color' => 'string',
					'global_body_typography' => 'json',
					'global_body_paragraph_space' => 'json',
					'global_link_color' => 'string',
					'global_link_hover_color' => 'string',
					'global_link_typography' => 'json',
					'global_link_hover_typography' => 'json',
					'global_h1_color' => 'string',
					'global_h1_typography' => 'json',
					'global_h2_color' => 'string',
					'global_h2_typography' => 'json',
					'global_h3_color' => 'string',
					'global_h3_typography' => 'json',
					'global_h4_color' => 'string',
					'global_h4_typography' => 'json',
					'global_h5_color' => 'string',
					'global_h5_typography' => 'json',
					'global_h6_color' => 'string',
					'global_h6_typography' => 'json',
				)
			),
			'fetch_posts'      => array(
				'callback' => array( $this, 'fetch_posts' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'postId'   => 'integer',
					'postType' => 'string',
					'keyword'  => 'string',
				)
			),
			'get_fronted_dashboard_pages'      => array(
				'callback' => array( $this, 'get_fronted_dashboard_pages' ),
				'capability'    => 'manage_options',
			),
			'create_fronted_dashboard_page'      => array(
				'callback' => array( $this, 'create_fronted_dashboard_page' ),
				'capability'    => 'manage_options',
				'fields' => [
					'label'     => 'string',
					'slug'      => 'string',
					'icon'      => 'string',
					'class_name' => 'string',
					'priority'  => 'integer',
					'parent_id'  => 'integer',
				]
			),
			'create_fronted_dashboard_link'      => array(
				'callback' => array( $this, 'create_fronted_dashboard_link' ),
				'capability'    => 'manage_options',
				'fields' => [
					'label'     => 'string',
					'link'      => 'string',
					'icon'      => 'string',
					'class_name' => 'string',
					'priority'  => 'integer',
					'parent_id'  => 'string',
				]
			),
			'edit_fronted_dashboard_link'      => array(
				'callback' => array( $this, 'edit_fronted_dashboard_link' ),
				'capability'    => 'manage_options',
				'fields' => [
					'label'     => 'string',
					'link'      => 'string',
					'icon'      => 'string',
					'class_name' => 'string',
					'priority'  => 'integer',
					'page_id'  => 'string',
				]
			),
			'delete_fronted_dashboard_link'      => array(
				'callback' => array( $this, 'delete_fronted_dashboard_link' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'page_id' => 'string',
				)
			),
			'edit_fronted_dashboard_page'      => array(
				'callback' => array( $this, 'edit_fronted_dashboard_page' ),
				'capability'    => 'manage_options',
				'fields' => [
					'label'     => 'string',
					'slug'      => 'string',
					'icon'      => 'string',
					'class_name' => 'string',
					'priority'  => 'integer',
					'parent_id'  => 'integer',
					'page_id'  => 'integer',
				]
			),
			'move_fronted_dashboard_page'      => array(
				'callback' => array( $this, 'move_fronted_dashboard_page' ),
				'capability'    => 'manage_options',
				'fields' => [
					'reordered_items'  => 'string',
				]
			),
			'delete_fronted_dashboard_page'      => array(
				'callback' => array( $this, 'delete_fronted_dashboard_page' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'slug'   => 'string',
					'page_id' => 'integer',
				)
			),
		);
	}

	public function get_blocks_visibility() {
		global $ablocks_blocks;
		wp_send_json_success( $ablocks_blocks );
	}

	public function save_block_visibility( $payload ) {
		$block_name = $payload['block_name'];
		$status = $payload['status'];

		$json_payload = Sanitizer::sanitize_payload([
			'required_plugin' => 'json',
		], $payload);

		$required_plugin = $json_payload['required_plugin'];

		if ( empty( $block_name ) ) {
			wp_send_json_error( __( 'Block Name missing', 'ablocks' ) );
		}

		if ( $status && $required_plugin ) {
			if ( $required_plugin && is_array( $required_plugin ) ) {
				foreach ( $required_plugin as $plugin ) {
					if ( ! Helper::is_plugin_active( sanitize_text_field( $plugin['plugin_dir_path'] ) ) ) {
						$error_message = sprintf( '%s Plugin is required to activate %s block.', $plugin['plugin_name'], $block_name );
						wp_send_json_error( $error_message );
					}
				}
			}
		}

		// Saved Data
		$saved_blocks = (array) json_decode( get_option( ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME ), true );
		$saved_blocks[ $block_name ] = $status;
		update_option( ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME, wp_json_encode( $saved_blocks ) );
		// Fire Addon Action
		if ( $status ) {
			do_action( "ablocks/block/activated_{$block_name}", $status );
		} else {
			do_action( "ablocks/block/deactivated_{$block_name}", $status );
		}
		wp_send_json_success( $saved_blocks );
	}
	public function save_bulk_block_visibility( $payload ) {
		$blocks = wp_list_pluck( \json_decode( $payload['blocks'], true ), 'status', 'block_name' );
		$saved_blocks = (array) json_decode( get_option( ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME ), true );
		$updated_settings = wp_parse_args( $blocks, $saved_blocks );
		update_option( ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME, wp_json_encode( $updated_settings ) );
		wp_send_json_success( $updated_settings );
	}
	public function get_settings() {
		$settings = BaseSettings::get_saved_data();
		wp_send_json_success( $settings );
	}

	public function save_settings( $payload ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		do_action( 'ablocks/before_save_settings', $payload, 'base' );
		$json_payload = Sanitizer::sanitize_payload([
			'selected_fonts' => 'json',
			'perf_page_cache_query_args' => 'json',
			'perf_page_cache_exclusions' => 'json',
			'global_color' => 'json',
			'global_typography' => 'json',
			'global_body_typography' => 'json',
			'global_body_paragraph_space' => 'json',
			'global_link_typography' => 'json',
			'global_link_hover_typography' => 'json',
			'global_h1_typography' => 'json',
			'global_h2_typography' => 'json',
			'global_h3_typography' => 'json',
			'global_h4_typography' => 'json',
			'global_h5_typography' => 'json',
			'global_h6_typography' => 'json',
		], $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$default = BaseSettings::get_default_data();
		$is_update = BaseSettings::save_settings( [
			'default_container_width' => $payload['default_container_width'] ?? $default['default_container_width'],
			'container_padding' => $payload['container_padding'] ?? $default['container_padding'],
			'container_element_gap' => $payload['container_element_gap'] ?? $default['container_element_gap'],
			'enabled_assets_file_generation' => $payload['enabled_assets_file_generation'] ?? $default['enabled_assets_file_generation'],
			'enabled_block_copy_paste_style' => $payload['enabled_block_copy_paste_style'] ?? $default['enabled_block_copy_paste_style'],
			'lock_global_typography' => $payload['lock_global_typography'] ?? $default['lock_global_typography'],
			'lock_global_typography_strict' => $payload['lock_global_typography_strict'] ?? $default['lock_global_typography_strict'],
			'lock_global_colors' => $payload['lock_global_colors'] ?? $default['lock_global_colors'],
			'enabled_load_google_font_locally' => $payload['enabled_load_google_font_locally'] ?? $default['enabled_load_google_font_locally'],
			'enabled_only_selected_fonts' => $payload['enabled_only_selected_fonts'] ?? $default['enabled_only_selected_fonts'],
			'selected_fonts' => $json_payload['selected_fonts'] ?? $default['selected_fonts'],
			'font_metric_fallback' => $payload['font_metric_fallback'] ?? $default['font_metric_fallback'],
			// Performance Suite — platform optimizations.
			'perf_disable_emojis' => $payload['perf_disable_emojis'] ?? $default['perf_disable_emojis'],
			'perf_disable_embeds' => $payload['perf_disable_embeds'] ?? $default['perf_disable_embeds'],
			'perf_disable_dashicons' => $payload['perf_disable_dashicons'] ?? $default['perf_disable_dashicons'],
			'perf_disable_jquery_migrate' => $payload['perf_disable_jquery_migrate'] ?? $default['perf_disable_jquery_migrate'],
			'perf_control_heartbeat' => $payload['perf_control_heartbeat'] ?? $default['perf_control_heartbeat'],
			'perf_heartbeat_frequency' => $payload['perf_heartbeat_frequency'] ?? $default['perf_heartbeat_frequency'],
			'perf_delay_js' => $payload['perf_delay_js'] ?? $default['perf_delay_js'],
			'perf_delay_js_timeout' => $payload['perf_delay_js_timeout'] ?? $default['perf_delay_js_timeout'],
			'perf_lazy_images' => $payload['perf_lazy_images'] ?? $default['perf_lazy_images'],
			'perf_lcp_eager_count' => $payload['perf_lcp_eager_count'] ?? $default['perf_lcp_eager_count'],
			'perf_image_dimensions' => $payload['perf_image_dimensions'] ?? $default['perf_image_dimensions'],
			'perf_responsive_images' => $payload['perf_responsive_images'] ?? $default['perf_responsive_images'],
			'perf_preload_lcp' => $payload['perf_preload_lcp'] ?? $default['perf_preload_lcp'],
			'perf_touch_targets' => $payload['perf_touch_targets'] ?? $default['perf_touch_targets'],
			'perf_inline_css' => $payload['perf_inline_css'] ?? $default['perf_inline_css'],
			'perf_async_css' => $payload['perf_async_css'] ?? $default['perf_async_css'],
			'perf_critical_css' => $payload['perf_critical_css'] ?? $default['perf_critical_css'],
			'perf_defer_js' => $payload['perf_defer_js'] ?? $default['perf_defer_js'],
			// Performance Suite — full-page cache.
			'perf_page_cache' => $payload['perf_page_cache'] ?? $default['perf_page_cache'],
			'perf_page_cache_scope' => $payload['perf_page_cache_scope'] ?? $default['perf_page_cache_scope'],
			'perf_page_cache_ttl' => $payload['perf_page_cache_ttl'] ?? $default['perf_page_cache_ttl'],
			'perf_page_cache_gzip' => $payload['perf_page_cache_gzip'] ?? $default['perf_page_cache_gzip'],
			'perf_page_cache_prewarm' => $payload['perf_page_cache_prewarm'] ?? $default['perf_page_cache_prewarm'],
			'perf_page_cache_mobile_variant' => $payload['perf_page_cache_mobile_variant'] ?? $default['perf_page_cache_mobile_variant'],
			'perf_page_cache_crawler' => $payload['perf_page_cache_crawler'] ?? $default['perf_page_cache_crawler'],
			'perf_page_cache_crawl_batch' => $payload['perf_page_cache_crawl_batch'] ?? $default['perf_page_cache_crawl_batch'],
			'perf_page_cache_query_args' => $json_payload['perf_page_cache_query_args'] ?? $default['perf_page_cache_query_args'],
			'perf_page_cache_exclusions' => $json_payload['perf_page_cache_exclusions'] ?? $default['perf_page_cache_exclusions'],
			'perf_consolidate_css' => $payload['perf_consolidate_css'] ?? $default['perf_consolidate_css'],
			'perf_consolidate_css_min' => $payload['perf_consolidate_css_min'] ?? $default['perf_consolidate_css_min'],
			'perf_fragment_cache' => $payload['perf_fragment_cache'] ?? $default['perf_fragment_cache'],
			'perf_fragment_cache_ttl' => $payload['perf_fragment_cache_ttl'] ?? $default['perf_fragment_cache_ttl'],
			'perf_template_cache' => $payload['perf_template_cache'] ?? $default['perf_template_cache'],
			'perf_template_cache_ttl' => $payload['perf_template_cache_ttl'] ?? $default['perf_template_cache_ttl'],
			'perf_image_optimize_on_upload' => $payload['perf_image_optimize_on_upload'] ?? $default['perf_image_optimize_on_upload'],
			'perf_image_level' => $payload['perf_image_level'] ?? $default['perf_image_level'],
			'perf_image_webp' => $payload['perf_image_webp'] ?? $default['perf_image_webp'],
			'perf_image_quarantine_autodelete' => $payload['perf_image_quarantine_autodelete'] ?? $default['perf_image_quarantine_autodelete'],
			'perf_image_quarantine_days' => $payload['perf_image_quarantine_days'] ?? $default['perf_image_quarantine_days'],
			'perf_image_require_filename' => $payload['perf_image_require_filename'] ?? $default['perf_image_require_filename'],
			'perf_image_require_alt' => $payload['perf_image_require_alt'] ?? $default['perf_image_require_alt'],
			// Marketing — query param passthrough
			'param_passthrough_enabled' => $payload['param_passthrough_enabled'] ?? $default['param_passthrough_enabled'],
			'param_passthrough_keys' => $payload['param_passthrough_keys'] ?? $default['param_passthrough_keys'],
			'param_passthrough_match' => $payload['param_passthrough_match'] ?? $default['param_passthrough_match'],
			'param_passthrough_class' => $payload['param_passthrough_class'] ?? $default['param_passthrough_class'],
			'param_passthrough_keyword' => $payload['param_passthrough_keyword'] ?? $default['param_passthrough_keyword'],
			'param_passthrough_persist' => $payload['param_passthrough_persist'] ?? $default['param_passthrough_persist'],
			'param_passthrough_cookie_days' => $payload['param_passthrough_cookie_days'] ?? $default['param_passthrough_cookie_days'],
			'enabled_coming_soon_page' => $payload['enabled_coming_soon_page'] ?? $default['enabled_coming_soon_page'],
			'coming_soon_page' => $payload['coming_soon_page'] ?? $default['coming_soon_page'],
			'enabled_maintenance_page' => $payload['enabled_maintenance_page'] ?? $default['enabled_maintenance_page'],
			'maintenance_page' => $payload['maintenance_page'] ?? $default['maintenance_page'],
			'frontend_dashboard_page' => $payload['frontend_dashboard_page'] ?? $default['frontend_dashboard_page'],
			'login_page' => $payload['login_page'] ?? $default['login_page'],
			'registration_page' => $payload['registration_page'] ?? $default['registration_page'],
			'forget_password_page' => $payload['forget_password_page'] ?? $default['forget_password_page'],

			'mailchimp_api_key' => $payload['mailchimp_api_key'] ?? '',
			'drip_api_key' => $payload['drip_api_key'] ?? '',
			'getresponse_api_key' => $payload['getresponse_api_key'] ?? '',
			'convertkit_api_key' => $payload['convertkit_api_key'] ?? '',
			'mailerlite_api_key' => $payload['mailerlite_api_key'] ?? '',
			// Global Color & Typography
			'global_color' => $json_payload['global_color'] ?? [],
			'global_typography' => $json_payload['global_typography'] ?? [],
			'global_font_family_fallback' => $payload['global_font_family_fallback'] ?? '',
			'global_body_typography' => $json_payload['global_body_typography'] ?? [],
			'global_body_paragraph_space' => $json_payload['global_body_paragraph_space'] ?? [],
			'global_link_typography' => $json_payload['global_link_typography'] ?? [],
			'global_link_hover_typography' => $json_payload['global_link_hover_typography'] ?? [],
			'global_h1_typography' => $json_payload['global_h1_typography'] ?? [],
			'global_h2_typography' => $json_payload['global_h2_typography'] ?? [],
			'global_h3_typography' => $json_payload['global_h3_typography'] ?? [],
			'global_h4_typography' => $json_payload['global_h4_typography'] ?? [],
			'global_h5_typography' => $json_payload['global_h5_typography'] ?? [],
			'global_h6_typography' => $json_payload['global_h6_typography'] ?? [],
			'global_body_text_color' => $payload['global_body_text_color'] ?? '',
			'global_link_color' => $payload['global_link_color'] ?? '',
			'global_link_hover_color' => $payload['global_link_hover_color'] ?? '',
			'global_h1_color' => $payload['global_h1_color'] ?? '',
			'global_h2_color' => $payload['global_h2_color'] ?? '',
			'global_h3_color' => $payload['global_h3_color'] ?? '',
			'global_h4_color' => $payload['global_h4_color'] ?? '',
			'global_h5_color' => $payload['global_h5_color'] ?? '',
			'global_h6_color' => $payload['global_h6_color'] ?? '',
		]);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		do_action( 'ablocks/after_save_settings', $is_update, 'base', $payload );
		wp_send_json_success( $is_update );
	}

	public function fetch_posts( $payload ) {
		$post_type = ( isset( $payload['postType'] ) ? $payload['postType'] : 'page' );
		$postId    = ( isset( $payload['postId'] ) ? $payload['postId'] : 0 );
		$keyword   = ( isset( $payload['keyword'] ) ? $payload['keyword'] : '' );

		if ( $postId ) {
			$args = array(
				'post_type' => $post_type,
				'p'         => $postId,
			);
		} else {
			$args = array(
				'post_type'      => $post_type,
				'posts_per_page' => 10,
			);
			if ( ! empty( $keyword ) ) {
				$args['s'] = $keyword;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				$args['author'] = get_current_user_id();
			}
		}
		$results = array();
		$posts   = get_posts( $args );
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$results[] = array(
					'label' => $post->post_title,
					'value' => $post->ID,
				);
			}
		}
		wp_send_json_success( $results );
	}

	public function get_fronted_dashboard_pages() {
		$settings = json_decode( get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ), true );
		foreach ( $settings as &$item ) {
			if ( isset( $item['parent_id'] ) ) {
				$item['parent_id'] = 'null' === $item['parent_id'] ? null : $item['parent_id'];
			}

			$item['edit_link'] = (
				'link' !== ( $item['type'] ?? '' ) &&
				is_numeric( $item['page_id'] )
			)
				? str_replace( '&amp;', '&', get_edit_post_link( $item['page_id'] ) )
				: null;

			if ( is_array( $item['children'] ?? null ) ) {
				foreach ( $item['children'] as &$child_item ) {
					if ( isset( $child_item['parent_id'] ) ) {
						$child_item['parent_id'] = 'null' === $child_item['parent_id'] ? null : $child_item['parent_id'];

					}

					$child_item['edit_link'] = (
						'link' !== ( $child_item['type'] ?? '' ) &&
						is_numeric( $child_item['page_id'] )
					)
						? str_replace( '&amp;', '&', get_edit_post_link( $child_item['page_id'] ) )
						: null;
				}
			}
		}//end foreach
		wp_send_json_success( $settings );
	}
	public function create_fronted_dashboard_page( $payload ) {
		global $wpdb;
		// Step 1: Get Frontend Dashboard Page ID
		$frontend_dashboard_id = (int) Helper::get_settings( 'frontend_dashboard_page' );
		$settings = json_decode( get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ), true );
		$root_page_id = 0;
		// Step 2: Check if "root" page exists under frontend dashboard
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$root_page = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_parent FROM $wpdb->posts WHERE post_parent=%d AND post_name = %s AND post_type = 'page'",
			$frontend_dashboard_id,
			'root'
		) );

		$post_parent = ! empty( $payload['parent_id'] ) ? $payload['parent_id'] : $frontend_dashboard_id;

		if ( ! $root_page || (int) $root_page->post_parent !== $frontend_dashboard_id ) {
			// Step 3: Create "root" page as child of frontend dashboard page
			$root_page_id = wp_insert_post( [
				'post_title'   => 'Root',
				'post_name'    => 'root',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
				'post_parent'  => $frontend_dashboard_id,
			] );

			$settings[] = [
				'page_id' => $root_page_id,
				'label'     => 'Root',
				'slug'      => 'root',
				'icon'      => '',
				'class_name' => '',
				'priority'  => 0,
			];
		}

		$insert_page_id = wp_insert_post( [
			'post_title'   => $payload['label'],
			'post_name'    => $payload['slug'],
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_parent'  => $post_parent,
		] );

		if ( $insert_page_id ) {
			$payload['page_id'] = $insert_page_id;

			if ( ! empty( $payload['parent_id'] ) ) {
				foreach ( $settings as &$setting ) {
					if ( $setting['page_id'] === $post_parent ) {
						if ( ! isset( $setting['children'] ) ) {
							$setting['children'] = [];
						}
						$setting['children'][] = $payload;
					}
				}
				unset( $setting ); // important to break reference
			} else {
				$settings[] = $payload;
			}

			update_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, wp_json_encode( $settings ) );
			flush_rewrite_rules();
		}

		wp_send_json_success( get_post( $insert_page_id ) );
	}

	public function create_fronted_dashboard_link( array $payload ) : void {
		global $wpdb;

		if ( empty( $payload['link'] ) || empty( $payload['label'] ) ) {
			wp_send_json_error( __( 'Link/Label is required.', 'ablocks' ) );
		}

		$settings = json_decode(
			get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ),
			true
		);

		$payload['type'] = 'link';
		$parent_id = $payload['parent_id'] = $payload['parent_id'] ?? 0;
		$payload['page_id'] = 'link_' . time();
		// print_r($payload);
		// var_dump(( $parent_id ));
		if ( empty( $parent_id ) || 'null' === $parent_id ) {
			$settings[] = $payload;
		} elseif ( ! is_null(
			$index = array_search(
				$parent_id,
				array_column( $settings, 'page_id' )
			)
		)
		) {
			$settings[ $index ]['children'][] = $payload;
		} else {
			wp_send_json_error( __( 'Invalid parent id.', 'ablocks' ) );
		}

		update_option(
			ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME,
			wp_json_encode( array_values( $settings ) )
		);

		wp_send_json_success( $payload );
	}

	public function edit_fronted_dashboard_link( array $payload ) : void {
		global $wpdb;

		if ( empty( $payload['page_id'] ) ||
			empty( $payload['link'] ) ||
			empty( $payload['label'] )
		) {
			wp_send_json_error( __( 'Link/Label/ID is required.', 'ablocks' ) );
		}

		$settings = json_decode(
			get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ),
			true
		);

		$page_id = $payload['page_id'] = $payload['page_id'] ?? 0;

		foreach ( $settings as &$items ) {
			// echo $items['page_id'],"\n";
			// var_dump($page_id ,  $items['page_id']);
			if ( $page_id === $items['page_id'] ) {
				$items['label'] = $payload['label'] ?: $items['label'];
				$items['link'] = $payload['link'] ?: $items['link'];
				$items['class_name'] = $payload['class_name'] ?: $items['class_name'];
				$items['icon'] = $payload['icon'] ?: $items['icon'];
				break;
			} elseif ( ! empty( $items['children'] ) ) {
				foreach ( $items['children'] as &$c_items ) {
					if ( $page_id === $c_items['page_id'] ) {
						$c_items['label'] = $payload['label'] ?: $c_items['label'];
						$c_items['link'] = $payload['link'] ?: $c_items['link'];
						$c_items['class_name'] = $payload['class_name'] ?: $c_items['class_name'];
						$c_items['icon'] = $payload['icon'] ?: $c_items['icon'];
						break 2;
					}
				}
			}
		}//end foreach

		update_option(
			ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME,
			wp_json_encode( array_values( $settings ) )
		);

		wp_send_json_success( $payload );
	}

	public function delete_fronted_dashboard_link( array $payload ) : void {
		global $wpdb;
		// print_r($payload);
		if ( empty( $payload['page_id'] )
		) {
			wp_send_json_error( __( 'ID is required.', 'ablocks' ) );
		}

		$settings = json_decode(
			get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ),
			true
		);

		$page_id = $payload['page_id'] = $payload['page_id'] ?? 0;

		foreach ( $settings as &$items ) {
			// echo $items['page_id'],"\n";
			// var_dump($page_id ,  $items['page_id']);
			if ( $page_id === $items['page_id'] ) {
				$items = null;
				break;
			} elseif ( ! empty( $items['children'] ) ) {
				foreach ( $items['children'] as &$c_items ) {
					if ( $page_id === $c_items['page_id'] ) {
						$c_items = null;
						$items['children'] = array_filter( $items['children'] );
						break 2;
					}
				}
			}
		}

		update_option(
			ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME,
			wp_json_encode( array_filter( $settings ) )
		);

		wp_send_json_success( $payload );
	}

	public function edit_fronted_dashboard_page( $payload ) {
		if ( empty( $payload['page_id'] ) ) {
			wp_send_json_error( 'Page ID is required for updating.' );
		}

		wp_update_post( [
			'ID' => $payload['page_id'],
			'post_title' => $payload['label'],
			'post_slug' => $payload['slug'],
			'priority' => $payload['priority'],
			'post_parent'  => ! empty( $payload['parent_id'] ) ? (int) $payload['parent_id'] : 0,
		], true );

		$settings = json_decode( get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ), true );

		$update_settings_recursive = null; // initialize first
		$update_settings_recursive = function ( &$settings ) use ( &$update_settings_recursive, $payload ) {
			foreach ( $settings as &$setting ) {
				if ( $setting['page_id'] === $payload['page_id'] ) {
					$setting['label']      = $payload['label'] ?? $setting['label'];
					$setting['slug']       = $payload['slug'] ?? $setting['slug'];
					$setting['icon']       = $payload['icon'] ?? $setting['icon'];
					$setting['class_name'] = $payload['class_name'] ?? $setting['class_name'];
					$setting['priority']   = $payload['priority'] ?? ( $setting['priority'] ?? 0 );
					if ( isset( $payload['slug'] ) ||
						isset( $payload['label'] )
					) {
						wp_update_post( [
							'ID' => $payload['page_id'],
							'post_title' => $payload['label'] ?? $setting['label'],
							'post_name' => $payload['slug'] ?? $setting['slug'],
						] );
					}
					if ( isset( $payload['children'] ) ) {
						$setting['children'] = $payload['children'];
					}
					return true;
				}//end if

				if ( ! empty( $setting['children'] ) ) {
					if ( $update_settings_recursive( $setting['children'] ) ) {
						return true;
					}
				}
			}//end foreach
			return false;
		};

		$update_settings_recursive( $settings );
		update_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, wp_json_encode( $settings ) );

		flush_rewrite_rules();
		// Step 4: Return updated page
		wp_send_json_success( get_post( $payload['page_id'] ) );
	}

	public function delete_fronted_dashboard_page( $payload ) {
		// Load existing frontend dashboard sub-pages settings
		$settings = json_decode( get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ), true );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$remove_id   = $payload['page_id'] ?? null;
		$remove_slug = $payload['slug'] ?? null;

		if ( ! $remove_id && ! $remove_slug ) {
			wp_send_json_error( 'Page ID or slug is required to delete.' );
		}

		// Step 1: Delete the post if ID is provided
		if ( $remove_id ) {
			wp_delete_post( (int) $remove_id, true );
		} elseif ( $remove_slug ) {
			// If only slug is provided, try to find the page
			$page = get_page_by_path( $remove_slug, OBJECT, 'page' );
			if ( $page ) {
				$remove_id = $page->ID;
				wp_delete_post( $remove_id, true );
			}
		}

		// Step 2: Recursively remove from settings
		$remove_from_settings = function ( &$settings ) use ( &$remove_from_settings, $remove_id, $remove_slug ) {
			$settings = array_values(array_filter($settings, function ( $item ) use ( $remove_id, $remove_slug ) {
				$match_id   = isset( $item['page_id'] ) && $remove_id && $item['page_id'] == $remove_id;
				$match_slug = isset( $item['slug'] ) && $remove_slug && $item['slug'] == $remove_slug;
				return ! $match_id && ! $match_slug;
			}));

			// Recurse into children
			foreach ( $settings as &$item ) {
				if ( ! empty( $item['children'] ) ) {
					$remove_from_settings( $item['children'] );
				}
			}
			unset( $item );
		};

		$remove_from_settings( $settings );

		// Step 3: Save updated settings
		update_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, wp_json_encode( $settings ) );

		wp_send_json_success( $remove_id ?? $remove_slug );
	}

	public function move_fronted_dashboard_page( array $payload ) : void {
		$reordered_settings = json_decode( $payload['reordered_items'] ?? '{}', true );
		$settings           = json_decode(
			get_option(
				ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}'
			),
			true
		);

		if ( is_array( $reordered_settings ) &&
			count( $reordered_settings ) === count( $settings )
		) {
			update_option(
				ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME,
				$payload['reordered_items'] ?? '{}'
			);
			wp_send_json_success( __( 'Success!', 'ablocks' ) );
		}
		wp_send_json_error( __( 'Invalid data', 'ablocks' ), 400 );
	}
}
