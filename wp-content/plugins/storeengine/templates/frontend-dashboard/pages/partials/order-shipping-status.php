<?php
/**
 * Order shipping & tracking (customer view).
 *
 * Shows the per-item shipping status + courier/tracking the vendor/admin
 * recorded, for PHYSICAL line items only. Digital items are skipped, so a
 * digital-only order renders nothing (the whole section is omitted).
 *
 * @var Order $order
 */

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Collect physical line items only — digital products don't ship.
$storeengine_shippable = [];
foreach ( $order->get_line_product_items() as $storeengine_item ) {
	if ( 'digital' === get_post_meta( $storeengine_item->get_product_id(), '_storeengine_product_shipping_type', true ) ) {
		continue;
	}
	$storeengine_shippable[] = $storeengine_item;
}

if ( empty( $storeengine_shippable ) ) {
	return;
}

global $wpdb;

// Canonical per-line shipping status from the lookup, fetched in one query.
$storeengine_ids          = array_map( static fn( $storeengine_item ) => (int) $storeengine_item->get_id(), $storeengine_shippable );
$storeengine_placeholders = implode( ',', array_fill( 0, count( $storeengine_ids ), '%d' ) );
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Prepared %d placeholders built for a dynamic IN() over a custom StoreEngine lookup table; not cacheable per request.
$storeengine_status_rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT order_item_id, shipping_status FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_item_id IN ($storeengine_placeholders)",
	$storeengine_ids
), OBJECT_K );
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
?>
<div class="storeengine-dashboard__section-wrapper">
	<div class="storeengine-dashboard__section-title">
		<h4 class="storeengine-dashboard__section-title-heading"><?php esc_html_e( 'Shipping &amp; tracking', 'storeengine' ); ?></h4>
	</div>
	<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--order-shipping">
			<thead>
				<tr>
					<th scope="col" class="col-product-name"><?php esc_html_e( 'Product', 'storeengine' ); ?></th>
					<th scope="col" class="col-shipping-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
					<th scope="col" class="col-shipping-tracking"><?php esc_html_e( 'Tracking', 'storeengine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $storeengine_shippable as $storeengine_item ) :
					$storeengine_oiid     = (int) $storeengine_item->get_id();
					$storeengine_status   = isset( $storeengine_status_rows[ $storeengine_oiid ] ) ? (string) $storeengine_status_rows[ $storeengine_oiid ]->shipping_status : '';
					$storeengine_shipment = $order->get_meta( '_storeengine_shipment_' . $storeengine_oiid );
					$storeengine_shipment = is_array( $storeengine_shipment ) ? $storeengine_shipment : [];
					$storeengine_courier  = (string) ( $storeengine_shipment['courier'] ?? '' );
					$storeengine_tracking = (string) ( $storeengine_shipment['tracking_number'] ?? '' );
					$storeengine_track_url = (string) ( $storeengine_shipment['tracking_url'] ?? '' );
					?>
					<tr>
						<td class="col-product-name" data-title="<?php esc_attr_e( 'Product', 'storeengine' ); ?>">
							<?php echo esc_html( $storeengine_item->get_name() ); ?>
						</td>
						<td class="col-shipping-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
							<span class="storeengine-shipping-pill storeengine-shipping-pill--<?php echo esc_attr( $storeengine_status ?: 'none' ); ?>">
								<?php echo esc_html( $storeengine_status ? Constants::get_shipping_status_label( $storeengine_status ) : __( 'Not shipped yet', 'storeengine' ) ); ?>
							</span>
						</td>
						<td class="col-shipping-tracking" data-title="<?php esc_attr_e( 'Tracking', 'storeengine' ); ?>">
							<?php
							if ( $storeengine_courier || $storeengine_tracking ) {
								$storeengine_label = trim( $storeengine_courier . ( $storeengine_tracking ? ' — ' . $storeengine_tracking : '' ) );
								if ( $storeengine_track_url ) {
									printf(
										'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
										esc_url( $storeengine_track_url ),
										esc_html( $storeengine_label )
									);
								} else {
									echo esc_html( $storeengine_label );
								}
							} else {
								echo '<span class="storeengine-shipping-tracking--empty">&mdash;</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php
unset( $storeengine_shippable, $storeengine_status_rows, $storeengine_ids, $storeengine_placeholders );
