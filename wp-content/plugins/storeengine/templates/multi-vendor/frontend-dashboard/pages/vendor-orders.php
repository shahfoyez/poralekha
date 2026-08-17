<?php
/**
 * Vendor — orders containing the vendor's products, with per-item fulfilment.
 *
 * Each order is grouped from the vendor's OWN line items (lookup rows scoped to
 * vendor_id, positive order_item_id only). For each shippable line the vendor
 * can set the shipping status (forward-only), courier + tracking, and Save —
 * which POSTs to the vendor fulfilment REST endpoint. The page renders fully
 * server-side and degrades to read-only when JS is unavailable.
 *
 * @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor
 */

use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$storeengine_lookup = $wpdb->prefix . 'storeengine_order_product_lookup';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%i/%d) read over a custom StoreEngine lookup table for the vendor dashboard; not cacheable per request.
$storeengine_rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT order_item_id, order_id, product_id, variation_id, product_qty, shipping_status,
		date_created, product_net_revenue, commission_amount
	 FROM %i
	 WHERE vendor_id = %d AND order_item_id > 0
	 ORDER BY date_created DESC, order_id DESC
	 LIMIT 500",
	$storeengine_lookup,
	$vendor->get_user_id()
) );

// Group the vendor's line items by order, newest first (query order preserved).
$storeengine_orders = [];
foreach ( (array) $storeengine_rows as $storeengine_r ) {
	$storeengine_orders[ (int) $storeengine_r->order_id ][] = $storeengine_r;
}

$storeengine_statuses   = Constants::get_shipping_statuses();
$storeengine_rest_url   = esc_url_raw( rest_url( 'storeengine/v1/vendor/fulfillment/shipment' ) );
$storeengine_rest_nonce = wp_create_nonce( 'wp_rest' );
?>
<div class="storeengine-vendor-orders storeengine-vendor-fulfillment"
	data-se-fulfillment-endpoint="<?php echo esc_attr( $storeengine_rest_url ); ?>"
	data-se-nonce="<?php echo esc_attr( $storeengine_rest_nonce ); ?>">


	<?php if ( empty( $storeengine_orders ) ) : ?>
		<div class="storeengine-vendor-fulfillment__empty">
			<?php esc_html_e( 'No orders yet for your products.', 'storeengine' ); ?>
		</div>
	<?php else : ?>
		<?php foreach ( $storeengine_orders as $storeengine_order_id => $storeengine_items ) : ?>
			<?php
			$storeengine_order = Helper::get_order( (int) $storeengine_order_id );
			if ( is_wp_error( $storeengine_order ) ) {
				continue;
			}
			$storeengine_placed     = ! empty( $storeengine_items[0]->date_created ) ? (string) $storeengine_items[0]->date_created : '';
			$storeengine_item_count = count( $storeengine_items );

			// Does any line item actually require shipping? Digital/non-physical
			// lines never do — an all-digital order needs no fulfilment UI at all.
			// The summary is computed over shippable lines only.
			$storeengine_shippable_count = 0;
			$storeengine_with_status     = 0;
			$storeengine_delivered       = 0;
			foreach ( $storeengine_items as $storeengine_summary_item ) {
				if ( 'digital' === get_post_meta( (int) $storeengine_summary_item->product_id, '_storeengine_product_shipping_type', true ) ) {
					continue;
				}
				$storeengine_shippable_count++;
				$storeengine_s = (string) $storeengine_summary_item->shipping_status;
				if ( '' !== $storeengine_s ) {
					$storeengine_with_status++;
				}
				if ( Constants::DELIVERED === $storeengine_s ) {
					$storeengine_delivered++;
				}
			}
			$storeengine_needs_shipment = $storeengine_shippable_count > 0;

			if ( $storeengine_shippable_count > 0 && $storeengine_delivered === $storeengine_shippable_count ) {
				$storeengine_summary_label = __( 'Delivered', 'storeengine' );
				$storeengine_summary_class = 'delivered';
			} elseif ( 0 === $storeengine_with_status ) {
				$storeengine_summary_label = __( 'Not shipped', 'storeengine' );
				$storeengine_summary_class = 'none';
			} else {
				$storeengine_summary_label = __( 'In progress', 'storeengine' );
				$storeengine_summary_class = 'shipped';
			}
			?>
			<div class="storeengine-vendor-fulfillment__order<?php echo $storeengine_needs_shipment ? '' : ' storeengine-vendor-fulfillment__order--no-shipment'; ?>">
				<?php if ( $storeengine_needs_shipment ) : ?>
				<button type="button" class="storeengine-vendor-fulfillment__order-head" data-role="toggle" aria-expanded="false">
					<span class="storeengine-vendor-fulfillment__order-id">#<?php echo esc_html( (string) $storeengine_order_id ); ?></span>
					<?php if ( $storeengine_placed ) : ?>
						<span class="storeengine-vendor-fulfillment__order-placed"><?php echo esc_html( $storeengine_placed ); ?></span>
					<?php endif; ?>
					<span class="storeengine-vendor-fulfillment__pill storeengine-vendor-fulfillment__pill--<?php echo esc_attr( $storeengine_summary_class ); ?> storeengine-vendor-fulfillment__order-summary">
						<?php echo esc_html( $storeengine_summary_label ); ?>
					</span>
					<span class="storeengine-vendor-fulfillment__order-count">
						<?php
						/* translators: %d: number of the vendor's items in this order */
						echo esc_html( sprintf( _n( '%d item', '%d items', $storeengine_item_count, 'storeengine' ), $storeengine_item_count ) );
						?>
					</span>
					<span class="storeengine-vendor-fulfillment__chevron" aria-hidden="true"></span>
				</button>

				<div class="storeengine-vendor-fulfillment__items">
					<?php foreach ( $storeengine_items as $storeengine_item ) : ?>
						<?php
						$storeengine_item_id    = (int) $storeengine_item->order_item_id;
						$storeengine_product_id = (int) $storeengine_item->product_id;
						$storeengine_current    = (string) $storeengine_item->shipping_status;
						$storeengine_cur_idx    = array_search( $storeengine_current, $storeengine_statuses, true );
						$storeengine_is_digital = 'digital' === get_post_meta( $storeengine_product_id, '_storeengine_product_shipping_type', true );
						$storeengine_name       = get_the_title( $storeengine_product_id ) ?: ( '#' . $storeengine_product_id );

						$storeengine_shipment   = $storeengine_order->get_meta( '_storeengine_shipment_' . $storeengine_item_id );
						$storeengine_shipment   = is_array( $storeengine_shipment ) ? $storeengine_shipment : [];
						$storeengine_courier    = (string) ( $storeengine_shipment['courier'] ?? '' );
						$storeengine_tracking   = (string) ( $storeengine_shipment['tracking_number'] ?? '' );
						$storeengine_track_url  = (string) ( $storeengine_shipment['tracking_url'] ?? '' );
						?>
						<div class="storeengine-vendor-fulfillment__item" data-order-item-id="<?php echo esc_attr( (string) $storeengine_item_id ); ?>">
							<div class="storeengine-vendor-fulfillment__item-info">
								<span class="storeengine-vendor-fulfillment__item-name"><?php echo esc_html( $storeengine_name ); ?></span>
								<span class="storeengine-vendor-fulfillment__item-qty">&times; <?php echo esc_html( (string) (int) $storeengine_item->product_qty ); ?></span>
								<?php if ( $storeengine_current ) : ?>
									<span class="storeengine-vendor-fulfillment__pill storeengine-vendor-fulfillment__pill--<?php echo esc_attr( $storeengine_current ); ?>" data-role="current-status">
										<?php echo esc_html( Constants::get_shipping_status_label( $storeengine_current ) ); ?>
									</span>
								<?php else : ?>
									<span class="storeengine-vendor-fulfillment__pill storeengine-vendor-fulfillment__pill--none" data-role="current-status">
										<?php esc_html_e( 'Not shipped', 'storeengine' ); ?>
									</span>
								<?php endif; ?>
							</div>

							<?php if ( $storeengine_is_digital ) : ?>
								<div class="storeengine-vendor-fulfillment__not-shippable">
									<?php esc_html_e( 'Digital product — not shippable', 'storeengine' ); ?>
								</div>
							<?php else : ?>
								<div class="storeengine-vendor-fulfillment__form">
									<label class="storeengine-vendor-fulfillment__field">
										<span><?php esc_html_e( 'Status', 'storeengine' ); ?></span>
										<select data-field="shipping_status">
											<?php if ( '' === $storeengine_current ) : ?>
												<option value="" disabled selected>&mdash;</option>
											<?php endif; ?>
											<?php foreach ( $storeengine_statuses as $storeengine_idx => $storeengine_status ) : ?>
												<option value="<?php echo esc_attr( $storeengine_status ); ?>"
													<?php selected( $storeengine_current, $storeengine_status ); ?>
													<?php disabled( false !== $storeengine_cur_idx && $storeengine_idx < $storeengine_cur_idx ); ?>>
													<?php echo esc_html( Constants::get_shipping_status_label( $storeengine_status ) ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>
									<label class="storeengine-vendor-fulfillment__field">
										<span><?php esc_html_e( 'Courier', 'storeengine' ); ?></span>
										<input type="text" data-field="courier" value="<?php echo esc_attr( $storeengine_courier ); ?>" placeholder="<?php esc_attr_e( 'e.g. DHL', 'storeengine' ); ?>">
									</label>
									<label class="storeengine-vendor-fulfillment__field">
										<span><?php esc_html_e( 'Tracking #', 'storeengine' ); ?></span>
										<input type="text" data-field="tracking_number" value="<?php echo esc_attr( $storeengine_tracking ); ?>" placeholder="<?php esc_attr_e( 'Tracking number', 'storeengine' ); ?>">
									</label>
									<label class="storeengine-vendor-fulfillment__field storeengine-vendor-fulfillment__field--wide">
										<span><?php esc_html_e( 'Tracking URL', 'storeengine' ); ?></span>
										<input type="url" data-field="tracking_url" value="<?php echo esc_attr( $storeengine_track_url ); ?>" placeholder="https://">
									</label>
									<div class="storeengine-vendor-fulfillment__actions">
										<button type="button" class="storeengine-btn storeengine-btn--bg-blue" data-role="save">
											<?php esc_html_e( 'Save', 'storeengine' ); ?>
										</button>
										<span class="storeengine-vendor-fulfillment__feedback" data-role="feedback" aria-live="polite"></span>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php else : ?>
				<div class="storeengine-vendor-fulfillment__order-head storeengine-vendor-fulfillment__order-head--static">
					<span class="storeengine-vendor-fulfillment__order-id">#<?php echo esc_html( (string) $storeengine_order_id ); ?></span>
					<?php if ( $storeengine_placed ) : ?>
						<span class="storeengine-vendor-fulfillment__order-placed"><?php echo esc_html( $storeengine_placed ); ?></span>
					<?php endif; ?>
					<span class="storeengine-vendor-fulfillment__no-shipment-note"><?php esc_html_e( 'No shipment required', 'storeengine' ); ?></span>
					<span class="storeengine-vendor-fulfillment__order-count">
						<?php
						/* translators: %d: number of items in the order */
						echo esc_html( sprintf( _n( '%d item', '%d items', $storeengine_item_count, 'storeengine' ), $storeengine_item_count ) );
						?>
					</span>
				</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<?php
// The Save handler is the enqueued `assets/fulfillment.js` (delegated on
// document, reads the endpoint + nonce from this container's data attributes).
unset( $storeengine_rest_url, $storeengine_rest_nonce, $storeengine_statuses, $storeengine_orders, $storeengine_rows );
