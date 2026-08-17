<?php
/**
 * GDPR addon.
 *
 * Wires StoreEngine into WordPress' core Privacy API. StoreEngine already
 * *creates* personal-data export/erase requests (account dashboard +
 * /me/privacy/erase-request), but core then runs zero StoreEngine exporters or
 * erasers — so the export ZIP and the erasure report come back empty of store
 * data. This addon registers those callbacks:
 *
 *   - Exporters return the customer's orders, customer-lookup record, saved
 *     addresses, consent prefs, download history, payment tokens and API keys.
 *   - Erasers anonymize completed orders in place (preserving accounting/tax
 *     integrity, following the common storefront convention) and hard-delete the rest.
 *
 * Both registered customers and *guests* are matched: WordPress passes an email
 * address (not a user id) to every callback, so orders are matched by
 * billing_email as well as customer_id.
 *
 * IMPORTANT: requests are created regardless of whether this addon is active,
 * but they only return/erase StoreEngine data while it is active. Keep the
 * addon enabled to stay GDPR-compliant.
 *
 * Addons that own their own personal-data tables (multi-vendor, affiliate,
 * eu-compliance consent logs) register their own exporters/erasers via the
 * `storeengine/privacy/exporters` and `storeengine/privacy/erasers` filters
 * this addon exposes — they only appear when that addon is itself active.
 */

namespace StoreEngine\Addons\Gdpr;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gdpr extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'gdpr';

	public function define_constants() {
		define( 'STOREENGINE_GDPR_VERSION', '1.0.0' );
	}

	/**
	 * Registered only while the addon is active (AbstractAddon::run() gates this).
	 */
	public function init_addon() {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporters' ], 10, 1 );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_erasers' ], 10, 1 );

		// Suggested privacy-policy text. Runs on admin_init (post-`init`) so the
		// translated string is built after textdomains load (avoids the WP 6.7+
		// "translation loading triggered too early" notice).
		add_action( 'admin_init', [ $this, 'add_privacy_policy_content' ] );
	}

	/**
	 * Append StoreEngine's core exporters, then let active addons add theirs.
	 *
	 * @param array $exporters Registered exporters keyed by id.
	 * @return array
	 */
	public function register_exporters( array $exporters ): array {
		$exporters['storeengine-orders'] = [
			'exporter_friendly_name' => __( 'StoreEngine Orders', 'storeengine' ),
			'callback'               => [ Exporters::class, 'orders' ],
		];
		$exporters['storeengine-customer-data'] = [
			'exporter_friendly_name' => __( 'StoreEngine Customer Data', 'storeengine' ),
			'callback'               => [ Exporters::class, 'customer_data' ],
		];
		$exporters['storeengine-downloads'] = [
			'exporter_friendly_name' => __( 'StoreEngine Download History', 'storeengine' ),
			'callback'               => [ Exporters::class, 'downloads' ],
		];
		$exporters['storeengine-payment-tokens'] = [
			'exporter_friendly_name' => __( 'StoreEngine Saved Payment Methods', 'storeengine' ),
			'callback'               => [ Exporters::class, 'payment_tokens' ],
		];
		$exporters['storeengine-api-keys'] = [
			'exporter_friendly_name' => __( 'StoreEngine API Keys', 'storeengine' ),
			'callback'               => [ Exporters::class, 'api_keys' ],
		];

		/**
		 * Let active addons contribute their own exporters (multi-vendor,
		 * affiliate, eu-compliance consent logs, …). Each entry must be a
		 * WP exporter array: `[ 'exporter_friendly_name' => string, 'callback' => callable ]`.
		 *
		 * @param array $exporters
		 */
		return apply_filters( 'storeengine/privacy/exporters', $exporters );
	}

	/**
	 * Append StoreEngine's core erasers, then let active addons add theirs.
	 *
	 * @param array $erasers Registered erasers keyed by id.
	 * @return array
	 */
	public function register_erasers( array $erasers ): array {
		$erasers['storeengine-orders'] = [
			'eraser_friendly_name' => __( 'StoreEngine Orders', 'storeengine' ),
			'callback'             => [ Erasers::class, 'orders' ],
		];
		$erasers['storeengine-customer-data'] = [
			'eraser_friendly_name' => __( 'StoreEngine Customer Data', 'storeengine' ),
			'callback'             => [ Erasers::class, 'customer_data' ],
		];
		$erasers['storeengine-downloads'] = [
			'eraser_friendly_name' => __( 'StoreEngine Download History', 'storeengine' ),
			'callback'             => [ Erasers::class, 'downloads' ],
		];
		$erasers['storeengine-payment-tokens'] = [
			'eraser_friendly_name' => __( 'StoreEngine Saved Payment Methods', 'storeengine' ),
			'callback'             => [ Erasers::class, 'payment_tokens' ],
		];
		$erasers['storeengine-api-keys'] = [
			'eraser_friendly_name' => __( 'StoreEngine API Keys', 'storeengine' ),
			'callback'             => [ Erasers::class, 'api_keys' ],
		];

		/**
		 * Let active addons contribute their own erasers. Each entry must be a
		 * WP eraser array: `[ 'eraser_friendly_name' => string, 'callback' => callable ]`.
		 *
		 * @param array $erasers
		 */
		return apply_filters( 'storeengine/privacy/erasers', $erasers );
	}

	/**
	 * Register suggested text for the site's Privacy Policy page.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = wpautop(
			__( 'When you shop on this store, we collect and store personal data so we can process your orders, deliver downloads, handle payments, comply with tax/accounting obligations, and prevent fraud. This includes your billing and shipping name and address, email address, phone number, IP address, the contents of your orders, saved payment-method tokens, download history, API keys you create, and any consent preferences you set.', 'storeengine' ) .
			"\n\n" .
			__( 'We retain order records for as long as required for accounting and tax purposes. You can request a copy of your personal data, or request its erasure, from your account dashboard or by contacting us. When we erase your data, completed order records are anonymized (so financial totals remain accurate) while your saved addresses, payment tokens, API keys and consent preferences are deleted.', 'storeengine' )
		);

		wp_add_privacy_policy_content( 'StoreEngine', wp_kses_post( $content ) );
	}
}
