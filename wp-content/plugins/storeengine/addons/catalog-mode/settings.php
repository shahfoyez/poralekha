<?php
/**
 * Settings.
 *
 * @since CatalogMode v1.0.0
 * @version 1.0.1
 */

namespace StoreEngine\Addons\CatalogMode;

use StoreEngine\Classes\AbstractAddonSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings extends AbstractAddonSettings {

	protected ?string $settings_name = 'catalog_mode';

	public function isEnabled(): bool {
		return $this->get_settings( 'enabled', false );
	}

	public function get_default_settings(): array {
		return [
			'enabled'                       => true,
			'disable_price'                 => true,
			'price_placeholder'             => '',
			'hide_add_to_cart_in'           => 'all',
			'add_to_cart_placeholder'       => '',
			'disable_cart_checkout'         => true,
			'customize_add_to_cart'         => true,
			'add_to_cart_shop_page_text'    => __( 'Request a Quote', 'storeengine' ),
			'add_to_cart_product_page_text' => __( 'Request a Quote', 'storeengine' ),
			'add_to_cart_link'              => '#',
			'add_to_cart_link_target'       => '_self',
			'exclude_role'                  => [ 'administrator' ],
		];
	}

	public function get_settings_fields(): array {
		return [
			'enabled'                       => 'boolean',
			'disable_price'                 => 'boolean',
			'price_placeholder'             => 'safe_text',
			'disable_cart_checkout'         => 'boolean',
			'hide_add_to_cart_in'           => 'string',
			'customize_add_to_cart'         => 'boolean',
			'add_to_cart_shop_page_text'    => 'string',
			'add_to_cart_product_page_text' => 'string',
			'add_to_cart_link'              => 'url',
			'add_to_cart_link_target'       => 'string',
			'add_to_cart_placeholder'       => 'safe_text',
			'exclude_role'                  => 'array-string',
		];
	}
}

// End of file settings.php.
