<?php

namespace StoreEngine\Addons\InstantCheckout;

use StoreEngine\Classes\AbstractAddonSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings extends AbstractAddonSettings {

	protected ?string $settings_name = 'instant_checkout';

	public function get_default_settings(): array {
		return [
			'show_on_archive'    => true,
			'show_on_single'     => true,
			'button_label_modal' => __( 'Quick Checkout', 'storeengine' ),
		];
	}

	public function get_settings_fields(): array {
		return [
			'show_on_archive'    => 'boolean',
			'show_on_single'     => 'boolean',
			'button_label_modal' => 'string',
		];
	}
}
