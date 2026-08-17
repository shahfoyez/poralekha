<?php

namespace StoreEngine\Addons\EmbeddableCheckout;

use StoreEngine\Classes\AbstractAddonSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings extends AbstractAddonSettings {

	protected ?string $settings_name = 'embeddable_checkout';

	public function get_default_settings(): array {
		return [
			// Cross-origin SDK rate limit (sessions per key per minute).
			'session_rate_limit' => 60,
		];
	}

	public function get_settings_fields(): array {
		return [
			'session_rate_limit' => 'integer',
		];
	}
}
