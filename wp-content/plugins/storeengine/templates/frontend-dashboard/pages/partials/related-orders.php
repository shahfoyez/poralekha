<?php
/**
 * Order details table
 *
 * @var Order[] $related_orders
 * @var AbstractOrder $order
 */

use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$related_orders_columns = apply_filters(
	'storeengine/templates/orders/related_orders_columns',
	[
		'id'      => __( 'Order', 'storeengine' ),
		'status'  => __( 'Status', 'storeengine' ),
		'total'   => __( 'Total', 'storeengine' ),
		'date'    => __( 'Order Placed', 'storeengine' ),
		'actions' => __( 'Actions', 'storeengine' ),
	]
);
?>
	<div class="storeengine-dashboard__section-wrapper<?php echo ! empty( $wrapper ) && is_string( $wrapper ) ? ' ' . sanitize_html_class( $wrapper ) : '' ;?>">
		<div class="storeengine-dashboard__section-title">
			<?php if ( ! empty( $title ) ) { ?>
			<h4 class="storeengine-dashboard__section-title-heading"><?php echo esc_html( $title ); ?></h4>
			<?php } else { ?>
			<h4 class="storeengine-dashboard__section-title-heading"><?php esc_html_e( 'Related orders', 'storeengine' ); ?></h4>
			<?php } ?>
		</div>
		<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper storeengine-dashboard__section--related-order-list">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--related-orders">
				<thead>
				<tr>
					<?php if ( ! empty( $related_orders_columns['order-number'] ) ) { ?>
					<?php } ?>
					<?php foreach ( $related_orders_columns as $column => $label ) { ?>
					<th scope="col" class="storeengine-related-orders-table__header col-<?php echo esc_attr( $column ); ?>">
						<?php if ( $column === 'number' ) { ?>
							#
							<span class="screen-reader-text"><?php echo esc_html( $related_orders_columns['order-number'] ); ?></span>
						<?php
						} else {
							echo esc_html( $label );
						}
						?>
					</th>
					<?php } ?>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $related_orders as $order ) {
					$order      = Helper::get_order( $order );
					$item_count = $order->get_item_count();
					?>
					<tr class="storeengine-related-orders-table__row storeengine-related-orders-table__row--type-<?php echo esc_attr( $order->get_type() ); ?> storeengine-related-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?>"
						id="related-<?php echo esc_attr( $order->get_type() ); ?>-<?php echo esc_attr( $order->get_id() ); ?>">
						<?php
						foreach ( $related_orders_columns as $column => $label ) {
							$is_id_col = 'id' === $column;
							if ( $is_id_col ) {
								?>
								<th class="storeengine-related-orders-table__cell storeengine-related-orders-table__cell-<?php echo esc_attr( $column ); ?>" data-title="<?php echo esc_attr( $label ); ?>" scope="row">
							<?php } else { ?>
								<td class="storeengine-related-orders-table__cell storeengine-related-orders-table__cell-<?php echo esc_attr( $column ); ?>" data-title="<?php echo esc_attr( $label ); ?>">
							<?php } ?>
							<?php
							if ( has_action( 'storeengine/templates/related_orders_' . $column ) ) {
								do_action( 'storeengine/templates/related_orders_' . $column, $order );
							} else {
								switch ( $column ) {
									case 'id':
										?>
										<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'orders', $order->get_id() ) ); ?>">
											<?php echo esc_html( _x( '#', 'hash before order number', 'storeengine' ) . $order->get_order_number() ); ?>
										</a>
										<?php
										break;
									case 'date':
										storeengine_print_time( $order->get_order_placed_date_gmt() ?? $order->get_date_created_gmt() );
										break;
									case 'status':
										?>
										<span class="storeengine-status storeengine-status--<?php echo esc_attr( $order->get_status() ); ?>">
											<?php echo esc_html( $order->get_status_title() ); ?>
										</span>
										<?php
										break;
									case 'total':
										?>
										<span>
											<?php
											// translators: $1: formatted order total for the order, $2: number of items bought
											echo wp_kses_post( sprintf( _n( '%1$s for %2$d item', '%1$s for %2$d items', $item_count, 'storeengine' ), $order->get_formatted_order_total(), $item_count ) );
											?>
										</span>
										<?php
										break;
									case 'actions':
										storeengine_render_dashboard_action_buttons( storeengine_get_account_orders_actions( $order ) );
										break;
									default:
										break;
								}
							}
							?>
							<?php if ( $is_id_col ) { ?>
								</th>
							<?php } else { ?>
								</td>
							<?php } ?>
						<?php } ?>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
<?php
