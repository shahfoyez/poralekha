<?php
/**
 * Vendor product editor — mounts the React vendor-product-editor bundle.
 *
 * The React entry (`dev_storeengine/vendor-product-editor.js`) reuses the
 * admin AddProduct component in mode='vendor'. The bundle output lives at
 * `assets/build/vendor-product-editor.{ver}.js` after `npm run build`.
 *
 * Vendor scope is enforced server-side by the ProductPermissions class
 * (rest_pre_insert hook forces post_author=current_user and post_status=pending).
 *
 * @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor
 * @var int                                            $product_id  0 = new
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_is_new = empty( $product_id ) || 'new' === get_query_var( 'storeengine_dashboard_sub_page' );
$post_id            = $storeengine_is_new ? 0 : (int) $product_id;

if ( $post_id > 0 ) {
	$storeengine_author = (int) get_post_field( 'post_author', $post_id );
	if ( $storeengine_author !== $vendor->get_user_id() ) {
		echo '<p>' . esc_html__( 'You can only edit your own products.', 'storeengine' ) . '</p>';
		return;
	}
}

$storeengine_products_list_url    = \StoreEngine\Utils\Helper::get_current_dashboard_endpoint_url( 'vendor-products' );
$storeengine_edit_url_template    = \StoreEngine\Utils\Helper::get_current_dashboard_endpoint_url( 'vendor-product-edit', '__ID__' );
$storeengine_bundle_relative      = 'assets/build/vendor-product-editor.' . STOREENGINE_VERSION . '.js';
$storeengine_bundle_asset_phpfile = STOREENGINE_ROOT_DIR_PATH . 'assets/build/vendor-product-editor.' . STOREENGINE_VERSION . '.asset.php';
$storeengine_bundle_built         = file_exists( STOREENGINE_ROOT_DIR_PATH . $storeengine_bundle_relative );

if ( ! $storeengine_bundle_built ) {
	echo '<div class="storeengine-notice storeengine-notice--error">'
		. esc_html__( 'The vendor product editor bundle has not been built yet. Run `npm run build` in the StoreEngine plugin directory.', 'storeengine' )
		. '</div>';
	return;
}

$storeengine_dependencies = file_exists( $storeengine_bundle_asset_phpfile )
	? include $storeengine_bundle_asset_phpfile
	: [ 'dependencies' => [], 'version' => STOREENGINE_VERSION ];

// Reuse the admin stylesheet so the editor renders with admin styling.
wp_enqueue_style(
	'storeengine-admin-style',
	STOREENGINE_PLUGIN_ROOT_URI . 'assets/build/backend.css',
	[ 'wp-components' ],
	$storeengine_dependencies['version']
);

wp_enqueue_script(
	'storeengine-vendor-product-editor',
	STOREENGINE_PLUGIN_ROOT_URI . $storeengine_bundle_relative,
	$storeengine_dependencies['dependencies'],
	$storeengine_dependencies['version'],
	true
);

// The React app reads window.StoreEngineGlobal — provide the same payload the
// admin bundle gets, but with vendor context injected so client-side code can
// branch where helpful.
// Build the same payload the admin bundle reads. Using `new Assets()` instead of
// `::init()` avoids the side-effect of re-registering enqueue actions.
$storeengine_assets = new \StoreEngine\Assets();
$storeengine_global = $storeengine_assets->get_backend_script_data();
$storeengine_global['mode']                          = 'vendor';
$storeengine_global['vendor_id']                     = $vendor->get_user_id();
$storeengine_global['vendor_dashboard_products_url'] = $storeengine_products_list_url;
wp_localize_script( 'storeengine-vendor-product-editor', 'StoreEngineGlobal', $storeengine_global );

if ( ! did_action( 'wp_enqueue_media' ) ) {
	wp_enqueue_media();
}
?>
<div class="storeengine-vendor-product-edit">
	<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
		<h2>
			<?php echo $post_id ? esc_html__( 'Edit product', 'storeengine' ) : esc_html__( 'Add new product', 'storeengine' ); ?>
		</h2>
		<a class="storeengine-btn storeengine-btn--md storeengine-btn--preset-transparent" href="<?php echo esc_url( $storeengine_products_list_url ); ?>">
			<?php esc_html_e( 'Back to my products', 'storeengine' ); ?>
		</a>
	</div>

	<div
		id="storeengine-vendor-product-editor"
		class="storeengine-admin storeengine-admin--vendor se-admin-tw"
		data-product-id="<?php echo esc_attr( (string) $post_id ); ?>"
		data-products-url="<?php echo esc_attr( $storeengine_products_list_url ); ?>"
		data-edit-url-template="<?php echo esc_attr( $storeengine_edit_url_template ); ?>"
	></div>

	<noscript>
		<p><?php esc_html_e( 'JavaScript is required to use the product editor.', 'storeengine' ); ?></p>
	</noscript>
</div>
