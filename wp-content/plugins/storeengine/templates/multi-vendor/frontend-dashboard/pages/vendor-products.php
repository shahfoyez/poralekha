<?php
/**
 * Vendor — My Products page.
 *
 * Thin PHP shell: enqueue the React bundle, render a single mount node,
 * let `vendor-products-page.js` own the rest (table + Add/Edit modal +
 * data fetching). The full-page React approach replaced the prior
 * PHP-table + inline-modal hybrid, which had cross-boundary state-sync
 * bugs.
 *
 * The standalone `vendor-product-edit` endpoint is still wired up
 * server-side as a no-JS / direct-link fallback.
 *
 * @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_bundle_relative      = 'assets/build/vendor-products-page.' . STOREENGINE_VERSION . '.js';
$storeengine_bundle_asset_phpfile = STOREENGINE_ROOT_DIR_PATH . 'assets/build/vendor-products-page.' . STOREENGINE_VERSION . '.asset.php';
$storeengine_bundle_built         = file_exists( STOREENGINE_ROOT_DIR_PATH . $storeengine_bundle_relative );

if ( $storeengine_bundle_built ) {
	$storeengine_dependencies = file_exists( $storeengine_bundle_asset_phpfile )
		? include $storeengine_bundle_asset_phpfile
		: [ 'dependencies' => [], 'version' => STOREENGINE_VERSION ];

	wp_enqueue_style(
		'storeengine-admin-style',
		STOREENGINE_PLUGIN_ROOT_URI . 'assets/build/backend.css',
		[ 'wp-components' ],
		$storeengine_dependencies['version']
	);

	wp_enqueue_script(
		'storeengine-vendor-products-page',
		STOREENGINE_PLUGIN_ROOT_URI . $storeengine_bundle_relative,
		$storeengine_dependencies['dependencies'],
		$storeengine_dependencies['version'],
		true
	);

	// Provide the React app with the global config the AddProduct + admin
	// components expect, plus the vendor scope on the side.
	$storeengine_assets                                  = new \StoreEngine\Assets();
	$storeengine_global                                  = $storeengine_assets->get_backend_script_data();
	$storeengine_global['mode']                          = 'vendor';
	$storeengine_global['vendor_id']                     = $vendor->get_user_id();
	wp_localize_script( 'storeengine-vendor-products-page', 'StoreEngineGlobal', $storeengine_global );

	wp_localize_script(
		'storeengine-vendor-products-page',
		'StoreEngineVendorProductsPage',
		[
			'vendorId'        => $vendor->get_user_id(),
			'restUrl'         => esc_url_raw( rest_url() ),
			'restNonce'       => wp_create_nonce( 'wp_rest' ),
			'productPostType' => \StoreEngine\Utils\Helper::PRODUCT_POST_TYPE,
		]
	);

	if ( ! did_action( 'wp_enqueue_media' ) ) {
		wp_enqueue_media();
	}
}
?>
<?php if ( $storeengine_bundle_built ) : ?>
	<div
		id="storeengine-vendor-products-mount"
		class="storeengine-vendor-products-mount"
	></div>
<?php else : ?>
	<div class="storeengine-notice storeengine-notice--error">
		<?php
		esc_html_e(
			'The vendor products bundle has not been built yet. Run `npm run build` in the StoreEngine plugin directory.',
			'storeengine'
		);
		?>
	</div>
<?php endif; ?>
