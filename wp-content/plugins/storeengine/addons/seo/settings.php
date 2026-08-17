<?php
/**
 * SEO Settings.
 *
 * One option array under the `seo` key (StoreEngine convention). Read via
 * Settings::init()->get_settings( 'key' ).
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo;

use StoreEngine\Classes\AbstractAddonSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings extends AbstractAddonSettings {

	protected ?string $settings_name = 'seo';

	public function get_default_settings(): array {
		return [
			// auto = bridge into an active SEO plugin, else stand alone.
			'mode'                   => 'auto',

			// Schema output.
			'schema_product_enabled' => true,
			'schema_breadcrumb_enabled' => true,

			// Social cards.
			'og_enabled'             => true,
			'twitter_card_type'      => 'summary_large_image',
			'twitter_site'           => '',
			'default_share_image_id' => 0,

			// Organization / brand (schema fallback when no brand taxonomy).
			'org_name'               => '',
			'org_logo_id'            => 0,

			// noindex defaults for transactional pages. Generic SEO plugins
			// don't know StoreEngine's cart/checkout/account/order-received
			// pages, so they leak into the index without this.
			'noindex_cart'           => true,
			'noindex_checkout'       => true,
			'noindex_account'        => true,
			'noindex_thankyou'       => true,
		];
	}

	public function get_settings_fields(): array {
		return [
			'mode'                      => 'string',
			'schema_product_enabled'    => 'boolean',
			'schema_breadcrumb_enabled' => 'boolean',
			'og_enabled'                => 'boolean',
			'twitter_card_type'         => 'string',
			'twitter_site'              => 'string',
			'default_share_image_id'    => 'number',
			'org_name'                  => 'string',
			'org_logo_id'               => 'number',
			'noindex_cart'              => 'boolean',
			'noindex_checkout'          => 'boolean',
			'noindex_account'           => 'boolean',
			'noindex_thankyou'          => 'boolean',
		];
	}
}

// End of file settings.php.
