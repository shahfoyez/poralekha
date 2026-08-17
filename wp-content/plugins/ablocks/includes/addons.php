<?php
namespace ABlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \ABlocks\Helper;

class Addons {
	public static function init() {
		$self = new self();
		// Load all addons
		$self->addons_loader();
		// Addons Ajax
		add_action( 'wp_ajax_ablocks/addons/get_all_addons', array( $self, 'get_all_addons' ) );
		add_action( 'wp_ajax_ablocks/addons/saved_addon_status', array( $self, 'saved_addon_status' ) );
	}

	private function addons_loader() {
		$Autoload = Autoload::get_instance();
		$addons = apply_filters('ablocks/addons/loader_args', [
			'theme-builder' => 'ThemeBuilder',
		]);

		foreach ( $addons as $addon_name => $addon_class_name ) {
			$addon_root_path = ABLOCKS_ADDONS_DIR_PATH . $addon_name . '/';
			// Register the addon's root namespace and path.
			$addon_namespace = 'ABlocks' . $addon_class_name;
			$Autoload->add_namespace_directory( $addon_namespace, $addon_root_path );
			// Initialize the addon's main class.
			$class = $addon_namespace . '\\' . $addon_class_name;

			$class::init();
		}
	}

	public function get_all_addons() {
		check_ajax_referer( 'ablocks_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		$ablocks_addons = json_decode( get_option( ABLOCKS_ADDONS_SETTINGS_NAME, '{}' ) );
		wp_send_json_success( $ablocks_addons );
	}

	public function saved_addon_status() {
		check_ajax_referer( 'ablocks_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		// phpcs:ignore  WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
		$addon_name = ( isset( $_POST['addon_name'] ) ? sanitize_text_field( $_POST['addon_name'] ) : '' );
		// phpcs:ignore  WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
		$addon_slug = ( isset( $_POST['addon_slug'] ) ? sanitize_text_field( $_POST['addon_slug'] ) : '' );
		// phpcs:ignore  WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 
		$status = (bool) ( isset( $_POST['status'] ) ? \ABlocks\Helper::sanitize_checkbox_field( $_POST['status'] ) : false );

		if ( empty( $addon_slug ) ) {
			wp_send_json_error( __( 'Addon Name missing', 'ablocks' ) );
		}

		if ( $status ) {

			$required_plugin = array();

			if ( isset( $_POST['required_plugin'] ) ) {
				$raw_json = wp_unslash( $_POST['required_plugin'] );
				$decoded  = json_decode( $raw_json, true );

				if ( is_array( $decoded ) ) {
					$required_plugin = map_deep( $decoded, 'sanitize_text_field' );
				}
			}

			do_action( 'ablocks/before_active_addon', $addon_slug, $required_plugin );

			if ( ! empty( $required_plugin ) ) {
				foreach ( $required_plugin as $plugin ) {

					if ( empty( $plugin['plugin_dir_path'] ) || empty( $plugin['plugin_name'] ) ) {
						continue;
					}

					if ( ! Helper::is_plugin_active( $plugin['plugin_dir_path'] ) ) {
						$error_message = sprintf(
							'%s Plugin is required to activate %s addon.',
							esc_html( $plugin['plugin_name'] ),
							esc_html( $addon_name )
						);

						wp_send_json_error( $error_message );
					}
				}
			}
		}//end if

		// Saved Data
		$saved_addons = (array) json_decode( get_option( ABLOCKS_ADDONS_SETTINGS_NAME ), true );
		$saved_addons[ $addon_slug ] = $status;
		update_option( ABLOCKS_ADDONS_SETTINGS_NAME, wp_json_encode( $saved_addons ) );
		// Fire Addon Action
		if ( $status ) {
			do_action( "ablocks/addons/activated_{$addon_slug}", $status );
		} else {
			do_action( "ablocks/addons/deactivated_{$addon_slug}", $status );
		}
		// response
		wp_send_json_success( $saved_addons );
	}
}
