<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\OrderCollection;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$query = new OrderCollection( [
	'per_page' => Helper::is_dashboard_index() ? 5 : 10,
	'page'     => max( 1, absint( get_query_var( 'paged' ) ) ),
	'where'    => [
		[
			'key'   => 'type',
			'value' => 'order',
		],
		[
			'key'   => 'customer_id',
			'value' => get_current_user_id(),
		],
		[
			'key'     => 'status',
			'value'   => [ 'draft', 'auto-draft', 'trash' ],
			'compare' => 'NOT IN',
		],
		[
			'relation' => 'AND',
			[
				'relation' => 'OR',
				[
					'key'   => 'parent_order_id',
					'value' => 0
				],
				[
					'key'     => 'parent_order_id',
					'compare' => 'IS NULL',
				],
			],
		],
	],
] );

?>
	<?php if ( Helper::is_dashboard_index() ) : ?>
		<div class="storeengine-dashboard__section-title">
			<h4 class="storeengine-dashboard__section-title-heading"><?php esc_html_e( 'Order History', 'storeengine' ); ?></h4>
			<?php if ( $query->get_found_results() ) : ?>
				<a class="storeengine-dashboard__section-title-link" href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'orders' ) ); ?>" style="display:flex;justify-content:center;align-items:center;line-height:1;gap:2px">
					<?php esc_html_e( 'View All', 'storeengine' ); ?>
					<i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
<?php if ( $query->have_results() ) : ?>
	<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--orders">
			<thead>
			<tr>
				<th scope="col" class="col-order-id">
					<span class="screen-reader-text"><?php esc_html_e( 'Order', 'storeengine' ); ?></span>
					#
				</th>
				<th scope="col" class="col-order-items"><?php esc_html_e( 'Product Items', 'storeengine' ); ?></th>
				<th scope="col" class="col-order-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
				<th scope="col" class="col-order-total"><?php esc_html_e( 'Total', 'storeengine' ); ?></th>
				<th scope="col" class="col-order-order-date"><?php esc_html_e( 'Order Placed', 'storeengine' ); ?></th>
				<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'storeengine' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $query->get_results() as $order ) : ?>
				<tr>
					<td class="col-order-id" data-title="<?php esc_attr_e( 'Order', 'storeengine' ); ?>">
						<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'orders', $order->get_id() ) ); ?>">
							<?php echo esc_html( _x( '#', 'hash before order number', 'storeengine' ) . $order->get_order_number() ); ?>
						</a>
					</td>
					<td class="col-order-items" data-title="<?php esc_attr_e( 'Item', 'storeengine' ); ?>">
						<?php
						$order_items = $order->get_line_product_items();
						if ( 1 === count( $order_items ) ) {
							$item              = reset( $order_items );
							$product           = $item->get_product();
							$purchase_note     = $product ? $product->get_purchase_note() : '';
							$is_visible        = $product && $product->is_visible();
							$product_permalink = apply_filters( 'storeengine/order/item_permalink', $is_visible ? get_permalink( $item->get_product_id() ) : '', $item, $order );

							?>
							<span class="nobr" style="font-size:14px;font-weight:400;line-height:1.5"><?php echo wp_kses_post( apply_filters( 'storeengine/order/item_name', $product_permalink ? sprintf( '<a href="%s">%s</a>', $product_permalink, $item->get_name() ) : $item->get_name(), $item, $is_visible ) ); ?></span>
							<?php

							if ( $item->get_price_name() ) {
								echo '&nbsp;<span class="nobr" style="font-size:14px;font-weight:400;line-height:1.5">(' . esc_html( $item->get_price_name() ) . ')</span>';
							}
							$qty          = $item->get_quantity();
							$refunded_qty = $order->get_qty_refunded_for_item( $item->get_id() );

							if ( $refunded_qty ) {
								$qty_display = '<del>' . esc_html( $qty ) . '</del> <ins>' . esc_html( $qty - ( $refunded_qty * - 1 ) ) . '</ins>';
							} else {
								$qty_display = esc_html( $qty );
							}

							echo '&nbsp;<strong>&times;&nbsp;' . $qty_display . '</strong>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							$total_qty = array_sum( array_map( fn( $order_item ) => $order_item->get_quantity(), $order_items ) );
							printf(
							/* translators: %d Total number of items in the order. */
								esc_html( _n( '%d item', '%d items', $total_qty, 'storeengine' ) ),
								esc_html( $total_qty )
							);
						}
						?>
					</td>
					<td class="col-order-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
						<div class="storeengine-status storeengine-status--<?php echo esc_attr( $order->get_status() ); ?>">
							<?php echo esc_html( $order->get_status_title() ); ?>
						</div>
					</td>
					<td class="col-order-total" data-title="<?php esc_attr_e( 'Amount', 'storeengine' ); ?>">
						<?php
						if ( $order->get_total_refunded() > 0 ) :
							$net_amount = $order->get_total() - $order->get_total_refunded();
							?>
							<s><?php echo wp_kses_post( Formatting::price( $order->get_total() ) ); ?></s>
							<p><?php echo wp_kses_post( Formatting::price( $net_amount ) ); ?></p>
						<?php
						else :
							echo wp_kses_post( Formatting::price( $order->get_total(), [
								'currency' => $order->get_currency()
							] ) );
						endif;
						?>
					</td>
					<td class="col-order-order-date nobr" data-title="<?php esc_attr_e( 'Order Placed', 'storeengine' ); ?>">
						<?php storeengine_print_time( $order->get_order_placed_date_gmt(), 'd M Y, h:i A (T)' ); ?>
					</td>
					<td class="col-actions">
						<?php storeengine_render_dashboard_action_buttons( storeengine_get_account_orders_actions( $order ), 'order', __( 'Order', 'storeengine' ), false ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( ! Helper::is_dashboard_index() ) {
			do_action_deprecated( 'storeengine/templates/dashboard_order_pagination', [ $query ], '1.6.9', '', 'Use storeengine_dashboard_collection_query_pagination() function instead.' );
			storeengine_dashboard_collection_query_pagination( $query );
		}
		?>
	</div>
<?php else : ?>
	<?php storeengine_oops_message( [
		'classes' => 'storeengine-my-5',
		'title'   => __( 'No Orders Found!', 'storeengine' ),
		'message' => __( 'No purchases found. Start by making your first purchase.', 'storeengine' ),
	] ); ?>
<?php endif;
