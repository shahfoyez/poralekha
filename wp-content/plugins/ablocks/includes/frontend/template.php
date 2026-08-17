<?php
namespace  ABlocks\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Academy;
use ABlocks\Helper;
use WP_Query;
use ABlocks\Blocks\FormBuilder\EmailVerification;

class Template {
	public static function init() {
		$self = new self();
		$self->dispatch_hook();
	}

	public function dispatch_hook() {
		add_filter( 'body_class', [ $this, 'add_fse_theme_body_class' ] );
		add_filter( 'theme_page_templates', [ $this, 'add_template_to_dropdown' ] );
		add_filter( 'template_include', array( $this, 'load_full_width_template' ), 99 );
		if ( ! is_user_logged_in() && ( Helper::get_settings( 'enabled_coming_soon_page' ) || Helper::get_settings( 'enabled_maintenance_page' ) ) ) {
			add_action( 'template_include', array( $this, 'set_website_visibility' ), 99 );
			add_filter( 'ablocks/is_enabled_assets_generation', '__return_false' );
		}

		// load email verification handler
		add_action( 'template_redirect', [ $this, 'email_verification_handler' ] );
	}


	public function add_fse_theme_body_class( $classes ) {
		if ( Helper::is_fse_theme() ) {
			$classes[] = 'ablocks-is-fse-theme';
		} else {
			$classes[] = 'ablocks-is-classic-theme';
		}
		return $classes;
	}

	public function set_website_visibility() {
		$GLOBALS['ablocks_visibility_page_id'] = Helper::get_settings( 'coming_soon_page' );
		if ( Helper::get_settings( 'enabled_maintenance_page' ) ) {
			$GLOBALS['ablocks_visibility_page_id'] = Helper::get_settings( 'maintenance_page' );
			status_header( 503 );
		}
		return ABLOCKS_ROOT_DIR_PATH . 'templates/website-visibility.php';
	}

	public function add_template_to_dropdown( $templates ) {
		if ( ! Helper::is_fse_theme() ) {
			$templates['ablocks-full-width-template.php'] = __( 'aBlocks Full Width', 'ablocks' );
		}
		return $templates;
	}

	public function load_full_width_template( $template ) {
		if ( is_page_template( 'ablocks-full-width-template.php' ) && ! Helper::is_fse_theme() ) {
			$template = ABLOCKS_ROOT_DIR_PATH . 'templates/full-width-template.php';
		}
		return $template;
	}

	public function email_verification_handler() {
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$signature = isset( $_GET['signature'] ) ? sanitize_text_field( wp_unslash( $_GET['signature'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$expire = isset( $_GET['expire'] ) ? sanitize_text_field( wp_unslash( $_GET['expire'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $id ) || empty( $signature ) || empty( $expire ) ) {
			return;
		}
		// Sanitize input before usage
		$get_data = [
			'id'        => $id,
			'signature' => $signature,
			'expire'    => $expire,
		];
		// Verify method has a sanitization system inside.
		$msg = EmailVerification::verify( $get_data );
		if ( is_array( $msg ) && ! empty( $msg['success'] ) ) {
			\ABlocks\Helper::get_template( 'verification/verification-success.php' );
			wp_die( esc_html__( 'Verification successful.', 'ablocks' ) );
		}
	}

}
