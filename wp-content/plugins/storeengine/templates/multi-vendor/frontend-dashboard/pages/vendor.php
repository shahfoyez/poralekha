<?php
/**
 * Vendor store overview — Free basic stats.
 *
 * @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$storeengine_user_id = (int) $vendor->get_user_id();
$storeengine_lookup  = $wpdb->prefix . 'storeengine_order_product_lookup';

// Total products (own, published or pending).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Vendor stats count over core/custom tables; per-request dashboard figure, not cacheable.
$storeengine_total_products = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status IN ('publish','pending','draft')",
	$storeengine_user_id,
	\StoreEngine\Utils\Helper::PRODUCT_POST_TYPE
) );

// Lifetime distinct orders + revenue + commission, for this vendor.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregated vendor stats over a custom lookup table; per-request dashboard figure, not cacheable.
$storeengine_row = $wpdb->get_row( $wpdb->prepare(
	"SELECT COUNT(DISTINCT order_id) AS orders, COALESCE(SUM(product_net_revenue),0) AS revenue, COALESCE(SUM(commission_amount),0) AS balance
	 FROM %i WHERE vendor_id = %d",
	$storeengine_lookup,
	$storeengine_user_id
) );

$storeengine_orders  = (int) ( $storeengine_row->orders ?? 0 );
$storeengine_revenue = (float) ( $storeengine_row->revenue ?? 0 );
$storeengine_balance = (float) ( $storeengine_row->balance ?? 0 );

/**
 * Allow the Pro addon to take over the entire overview by short-circuiting this output.
 * Pro hooks here and prints rich charts then returns true to suppress the basic stats below.
 */
$storeengine_handled = apply_filters( 'storeengine/multi_vendor/render_overview', false, $vendor );
if ( $storeengine_handled ) {
	return;
}
?>
<div class="storeengine-vendor-overview">
	<h2><?php echo esc_html( $vendor->get_store_name() ); ?></h2>

	<div class="storeengine-vendor-overview__cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
		<div class="storeengine-stat-card">
			<div class="storeengine-stat-card__label"><?php esc_html_e( 'Total products', 'storeengine' ); ?></div>
			<div class="storeengine-stat-card__value"><?php echo esc_html( (string) $storeengine_total_products ); ?></div>
		</div>
		<div class="storeengine-stat-card">
			<div class="storeengine-stat-card__label"><?php esc_html_e( 'Lifetime orders', 'storeengine' ); ?></div>
			<div class="storeengine-stat-card__value"><?php echo esc_html( (string) $storeengine_orders ); ?></div>
		</div>
		<div class="storeengine-stat-card">
			<div class="storeengine-stat-card__label"><?php esc_html_e( 'Lifetime revenue', 'storeengine' ); ?></div>
			<div class="storeengine-stat-card__value"><?php echo wp_kses_post( \StoreEngine\Utils\Formatting::price( $storeengine_revenue ) ); ?></div>
		</div>
		<div class="storeengine-stat-card">
			<div class="storeengine-stat-card__label"><?php esc_html_e( 'Current balance', 'storeengine' ); ?></div>
			<div class="storeengine-stat-card__value"><?php echo wp_kses_post( \StoreEngine\Utils\Formatting::price( $storeengine_balance ) ); ?></div>
		</div>
	</div>

	<?php
	/**
	 * Pro upsell card. Pro addon removes this via apply_filters return true above
	 * (or by overriding the template entirely).
	 */
	?>
	<div class="storeengine-vendor-upsell" style="margin-top:24px;padding:20px;border:1px solid #e5e7eb;border-radius:8px;">
		<h3><?php esc_html_e( 'Unlock vendor analytics', 'storeengine' ); ?></h3>
		<p><?php esc_html_e( 'Date filters, top products, refund breakdown, payouts, and more — available with StoreEngine Pro.', 'storeengine' ); ?></p>
	</div>
</div>
