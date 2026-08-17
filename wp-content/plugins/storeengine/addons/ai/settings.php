<?php
/**
 * AI Settings.
 *
 * One option array under the `ai` key (StoreEngine convention). Read via
 * Settings::init()->get_settings( 'key' ). These tune HOW content is generated
 * (tone, language, creativity, model preference) — credentials are never stored
 * here; they live in WordPress Settings → Connectors.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai;

use StoreEngine\Classes\AbstractAddonSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings extends AbstractAddonSettings {

	protected ?string $settings_name = 'ai';

	public function get_default_settings(): array {
		return [
			'enabled'           => true,

			// Authoring defaults applied to every generation unless overridden.
			'default_tone'      => 'professional', // professional | friendly | persuasive | minimal
			'default_language'  => '',             // '' = site locale
			'temperature'       => 0.7,
			'model_preference'  => '',             // '' = connector default

			// Product fields the editor exposes a Generate action for.
			'product_fields'    => [ 'title', 'description', 'short_description', 'image_alt' ],
			'image_alt_enabled' => true,
		];
	}

	public function get_settings_fields(): array {
		return [
			'enabled'           => 'boolean',
			'default_tone'      => 'string',
			'default_language'  => 'string',
			'temperature'       => 'number',
			'model_preference'  => 'string',
			'product_fields'    => 'array',
			'image_alt_enabled' => 'boolean',
		];
	}
}

// End of file settings.php.
