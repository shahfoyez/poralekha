<?php
/**
 * Vendor inventory page.
 *
 * Lists stock levels for the current vendor's own products. Read-only here
 * — adjustments still go through the wp-admin inventory screen (or a future
 * inline-edit on this page). Vendor scope is enforced both in the SQL below
 * AND defensively at the REST layer via Inventory\Authorization, so a vendor
 * can never see another vendor's stock.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor */
if ( empty( $vendor ) || ! $vendor->is_approved() ) {
	return;
}

global $wpdb;

$storeengine_user_id    = (int) $vendor->get_user_id();
$storeengine_variations = $wpdb->prefix . 'storeengine_product_variations';
$storeengine_movements  = $wpdb->prefix . 'storeengine_stock_movements';

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted $wpdb->prefix/core table identifiers interpolated; vendor scope bound via %d in prepare(); read-only inventory over custom StoreEngine tables.
$storeengine_variation_rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT v.id AS variation_id, v.product_id, v.sku, v.barcode,
	        COALESCE(v.stock_quantity, 0) AS stock_quantity,
	        v.stock_status, v.low_stock_threshold,
	        p.post_title AS product_title
	   FROM {$storeengine_variations} v
	   INNER JOIN {$wpdb->posts} p ON p.ID = v.product_id
	  WHERE v.manage_stock = 1
	    AND p.post_status IN ('publish','draft','private')
	    AND p.post_author = %d
	  ORDER BY p.post_title ASC, v.id ASC
	  LIMIT 200",
	$storeengine_user_id
) );

$storeengine_simple_rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT 0 AS variation_id, p.ID AS product_id,
	        sku.meta_value  AS sku,
	        bc.meta_value   AS barcode,
	        CAST(COALESCE(sq.meta_value, 0) AS SIGNED) AS stock_quantity,
	        COALESCE(ss.meta_value, 'instock') AS stock_status,
	        CAST(NULLIF(lst.meta_value, '') AS SIGNED) AS low_stock_threshold,
	        p.post_title    AS product_title
	   FROM {$wpdb->posts} p
	   INNER JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = '_storeengine_manage_stock'
	   LEFT JOIN  {$wpdb->postmeta} pt  ON pt.post_id  = p.ID AND pt.meta_key  = '_storeengine_product_type'
	   LEFT JOIN  {$wpdb->postmeta} sku ON sku.post_id = p.ID AND sku.meta_key = '_storeengine_sku'
	   LEFT JOIN  {$wpdb->postmeta} bc  ON bc.post_id  = p.ID AND bc.meta_key  = '_storeengine_barcode'
	   LEFT JOIN  {$wpdb->postmeta} sq  ON sq.post_id  = p.ID AND sq.meta_key  = '_storeengine_stock_quantity'
	   LEFT JOIN  {$wpdb->postmeta} ss  ON ss.post_id  = p.ID AND ss.meta_key  = '_storeengine_stock_status'
	   LEFT JOIN  {$wpdb->postmeta} lst ON lst.post_id = p.ID AND lst.meta_key = '_storeengine_low_stock_threshold'
	  WHERE p.post_type = 'storeengine_product'
	    AND p.post_status IN ('publish','draft','private')
	    AND ms.meta_value IN ('1','true')
	    AND (pt.meta_value IS NULL OR pt.meta_value <> 'variable')
	    AND p.post_author = %d
	  ORDER BY p.post_title ASC
	  LIMIT 200",
	$storeengine_user_id
) );

$storeengine_rows = array_merge( (array) $storeengine_variation_rows, (array) $storeengine_simple_rows );

$storeengine_recent_movements = $wpdb->get_results( $wpdb->prepare(
	"SELECT m.id, m.product_id, m.variation_id, m.type, m.reason,
	        m.qty_change, m.qty_before, m.qty_after, m.created_at,
	        p.post_title AS product_title
	   FROM {$storeengine_movements} m
	   INNER JOIN {$wpdb->posts} p ON p.ID = m.product_id
	  WHERE m.vendor_id = %d
	  ORDER BY m.id DESC
	  LIMIT 20",
	$storeengine_user_id
) );
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
?>
<div class="storeengine-vendor-inventory">
	<h3 class="storeengine-vendor-inventory__subheading"><?php esc_html_e( 'Stock levels', 'storeengine' ); ?></h3>

	<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--vendor-inventory">
			<thead>
				<tr>
					<th scope="col" class="col-product"><?php esc_html_e( 'Product', 'storeengine' ); ?></th>
					<th scope="col" class="col-sku"><?php esc_html_e( 'SKU', 'storeengine' ); ?></th>
					<th scope="col" class="col-stock"><?php esc_html_e( 'Stock', 'storeengine' ); ?></th>
					<th scope="col" class="col-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
					<th scope="col" class="col-threshold"><?php esc_html_e( 'Low-stock threshold', 'storeengine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $storeengine_rows ) ) : ?>
					<tr class="storeengine-dashboard__table-empty">
						<td colspan="5"><?php esc_html_e( 'You do not have any stock-tracked products yet.', 'storeengine' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $storeengine_rows as $storeengine_r ) : ?>
						<?php
						$storeengine_is_low = ( null !== $storeengine_r->low_stock_threshold && '' !== $storeengine_r->low_stock_threshold )
							&& ( (int) $storeengine_r->stock_quantity <= (int) $storeengine_r->low_stock_threshold );
						?>
						<tr<?php echo $storeengine_is_low ? ' class="storeengine-dashboard__table-row--low-stock"' : ''; ?>>
							<td class="col-product" data-title="<?php esc_attr_e( 'Product', 'storeengine' ); ?>">
								<?php echo esc_html( (string) $storeengine_r->product_title ); ?>
								<?php if ( (int) $storeengine_r->variation_id ) : ?>
									<span class="storeengine-text-muted">#<?php echo (int) $storeengine_r->variation_id; ?></span>
								<?php endif; ?>
							</td>
							<td class="col-sku" data-title="<?php esc_attr_e( 'SKU', 'storeengine' ); ?>"><?php echo esc_html( (string) ( $storeengine_r->sku ?? '' ) ); ?></td>
							<td class="col-stock col-num" data-title="<?php esc_attr_e( 'Stock', 'storeengine' ); ?>">
								<?php echo (int) $storeengine_r->stock_quantity; ?>
							</td>
							<td class="col-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
								<?php echo esc_html( ucfirst( (string) $storeengine_r->stock_status ) ); ?>
							</td>
							<td class="col-threshold col-num" data-title="<?php esc_attr_e( 'Low-stock threshold', 'storeengine' ); ?>">
								<?php echo null !== $storeengine_r->low_stock_threshold && '' !== $storeengine_r->low_stock_threshold ? (int) $storeengine_r->low_stock_threshold : '—'; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<h3 class="storeengine-vendor-inventory__subheading"><?php esc_html_e( 'Recent stock movements', 'storeengine' ); ?></h3>

	<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--vendor-movements">
			<thead>
				<tr>
					<th scope="col" class="col-date"><?php esc_html_e( 'Date', 'storeengine' ); ?></th>
					<th scope="col" class="col-product"><?php esc_html_e( 'Product', 'storeengine' ); ?></th>
					<th scope="col" class="col-type"><?php esc_html_e( 'Type', 'storeengine' ); ?></th>
					<th scope="col" class="col-reason"><?php esc_html_e( 'Reason', 'storeengine' ); ?></th>
					<th scope="col" class="col-delta"><?php esc_html_e( 'Δ', 'storeengine' ); ?></th>
					<th scope="col" class="col-after"><?php esc_html_e( 'After', 'storeengine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $storeengine_recent_movements ) ) : ?>
					<tr class="storeengine-dashboard__table-empty">
						<td colspan="6"><?php esc_html_e( 'No movements recorded yet.', 'storeengine' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $storeengine_recent_movements as $m ) : ?>
						<tr>
							<td class="col-date" data-title="<?php esc_attr_e( 'Date', 'storeengine' ); ?>"><?php echo esc_html( (string) $m->created_at ); ?></td>
							<td class="col-product" data-title="<?php esc_attr_e( 'Product', 'storeengine' ); ?>">
								<?php echo esc_html( (string) $m->product_title ); ?>
								<?php if ( (int) $m->variation_id ) : ?>
									<span class="storeengine-text-muted">#<?php echo (int) $m->variation_id; ?></span>
								<?php endif; ?>
							</td>
							<td class="col-type" data-title="<?php esc_attr_e( 'Type', 'storeengine' ); ?>"><?php echo esc_html( (string) $m->type ); ?></td>
							<td class="col-reason" data-title="<?php esc_attr_e( 'Reason', 'storeengine' ); ?>"><?php echo esc_html( (string) ( $m->reason ?? '' ) ); ?></td>
							<td class="col-delta col-num" data-title="<?php esc_attr_e( 'Δ', 'storeengine' ); ?>">
								<?php $storeengine_sign = (int) $m->qty_change >= 0 ? '+' : ''; ?>
								<?php echo esc_html( $storeengine_sign . (int) $m->qty_change ); ?>
							</td>
							<td class="col-after col-num" data-title="<?php esc_attr_e( 'After', 'storeengine' ); ?>">
								<?php echo null !== $m->qty_after ? (int) $m->qty_after : '—'; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
