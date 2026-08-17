<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\DownloadPermission;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use WP_Error;

class OrderDownloads {

	public function __construct() {
		add_shortcode( 'storeengine_order_downloads', [ $this, 'render' ] );
	}

	public function render( $atts ) {
		$attributes = shortcode_atts( [ 'dummy' => false ], $atts );
		$dummy      = Formatting::string_to_bool( $attributes['dummy'] );

		if ( ! $dummy ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
			$order      = Helper::get_order_by_key( $order_hash );
			$order      = $order instanceof WP_Error ? false : $order;

			// Fall back to sample content when there's no real order to show
			// (page opened directly, previewed, or rendered in the editor).
			if ( ! $order ) {
				$dummy = true;
			}
		}

		$downloadable_permissions = $dummy
			? self::get_dummy_permissions()
			: apply_filters( 'storeengine/order/downloadable_permissions', $order->get_downloadable_permissions(), $order );

		ob_start();
		Template::get_template( 'shortcode/order-downloads.php', [
			'downloadable_permissions' => $downloadable_permissions,
		] );

		return ob_get_clean();
	}

	/**
	 * A sample download row for previews / empty-order rendering. The template
	 * only reads the product title, file name and download URL, so a lightweight
	 * {@see DownloadPermission} subclass overriding just those three is enough.
	 *
	 * @return DownloadPermission[]
	 */
	protected static function get_dummy_permissions(): array {
		$permission = new class( 0 ) extends DownloadPermission {
			public function get_product_title(): string {
				return __( 'Sample product', 'storeengine' );
			}

			public function get_file_name(): string {
				return __( 'sample-file.zip', 'storeengine' );
			}

			public function get_download_url(): string {
				return '#';
			}
		};

		return [ $permission ];
	}
}
