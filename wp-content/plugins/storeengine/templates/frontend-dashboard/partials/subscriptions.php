<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Addons\Subscription\Classes\Utils;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$query = new SubscriptionCollection( [
	'per_page' => Helper::is_dashboard_index() ? 5 : 10,
	'page'     => max( 1, absint( get_query_var( 'paged' ) ) ),
	'orderby'  => 'id',
	'order'    => 'DESC',
	'where'    => [
		[
			'key'   => 'customer_id',
			'value' => get_current_user_id(),
		],
	],
] );

?>
	<?php if ( Helper::is_dashboard_index() ) : ?>
		<div class="storeengine-dashboard__section-title">
			<h4 class="storeengine-dashboard__section-title-heading"><?php esc_html_e( 'Subscription History', 'storeengine' ); ?></h4>
			<?php if ( $query->get_found_results() ) : ?>
				<a class="storeengine-dashboard__section-title-link" href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'plans' ) ); ?>" style="display:flex;justify-content:center;align-items:center;line-height:1;gap:2px">
					<?php esc_html_e( 'View All', 'storeengine' ); ?>
					<i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
<?php if ( $query->have_results() ) : ?>
	<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--subscriptions">
			<thead>
			<tr>
				<th scope="col" class="col-subscription-id">
					<span class="screen-reader-text"><?php esc_html_e( 'Subscription', 'storeengine' ); ?></span>
					#
				</th>
				<th scope="col" class="col-subscription-item"><?php esc_html_e( 'Item', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-date"><?php esc_html_e( 'Date', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-date"><?php esc_html_e( 'Next Renewal', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-total"><?php esc_html_e( 'Total', 'storeengine' ); ?></th>
				<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'storeengine' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $query->get_results() as $subscription ) : ?>
				<tr>
					<td class="col-subscription-id" data-title="<?php esc_attr_e( 'Subscription', 'storeengine' ); ?>">
						<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'plans', $subscription->get_id() ) ); ?>">
							<?php echo esc_html( _x( '#', 'hash before order number', 'storeengine' ) . $subscription->get_order_number() ); ?>
						</a>
					</td>
					<td class="col-subscription-items" data-title="<?php esc_attr_e( 'Item', 'storeengine' ); ?>">
						<?php
						$line_items = $subscription->get_items();
						if ( $line_items ) {
							$rendered_items = [];
							foreach ( $line_items as $line_item ) {
								/** @var \StoreEngine\Classes\Order\OrderItemProduct $line_item */
								$item_link = $line_item->get_product() && 'publish' === $line_item->get_product()->get_status() ? get_permalink( $line_item->get_product_id() ) : null;
								if ( $item_link ) {
									$rendered_items[] = sprintf(
										'<a href="%1$s">%2$s</a>',
										esc_url( $item_link ),
										esc_html( $line_item->get_name() )
									);
								} else {
									$rendered_items[] = esc_html( $line_item->get_name() );
								}
							}
							echo wp_kses_post( implode( ', ', $rendered_items ) );
						} else {
							esc_html_e( 'N/A', 'storeengine' );
						}
						?>
					</td>
					<td class="col-subscription-date" data-title="<?php esc_attr_e( 'Created Date', 'storeengine' ); ?>">
						<?php storeengine_print_time( $subscription->get_date_created_gmt(), 'd M Y, h:i A (T)' ); ?>
					</td>
					<td class="col-subscription-date" data-title="<?php esc_attr_e( 'Next renewal date', 'storeengine' ); ?>">
						<?php
						if ( $subscription->get_next_payment_date() ) {
							storeengine_print_time( $subscription->get_next_payment_date(), 'd M Y, h:i A (T)' );
							$payment_method = $subscription->get_payment_method_to_display( 'customer' );
							if ( $payment_method ) {
								?>
								<br/>
								<small><?php echo esc_html( $payment_method ); ?></small>
								<?php
							}
						}
						?>
					</td>
					<td class="col-subscription-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
						<div class="storeengine-status storeengine-status--<?php echo esc_attr( $subscription->get_status() ); ?>">
							<?php echo esc_html( $subscription->get_status_title() ); ?>
						</div>
					</td>
					<td class="col-subscription-total" data-title="<?php esc_attr_e( 'Amount', 'storeengine' ); ?>">
						<?php echo wp_kses_post( Formatting::price( $subscription->get_total() ) ); ?>
					</td>
					<td class="col-actions">
						<?php storeengine_render_dashboard_action_buttons( Utils::get_account_subscription_actions( $subscription ), 'subscription' ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( ! Helper::is_dashboard_index() ) {
			do_action_deprecated( 'storeengine/templates/dashboard_subscription_pagination', [ $query ], '1.6.9', '', 'Use storeengine_dashboard_collection_query_pagination() function instead.' );
			storeengine_dashboard_collection_query_pagination( $query );
		}
		?>
	</div>
<?php else : ?>
	<?php storeengine_oops_message( [
		'classes' => 'storeengine-my-5',
		'title'   => __( 'No Subscriptions Found!', 'storeengine' ),
		'message' => __( 'No subscriptions found. You don’t have any active subscriptions yet.', 'storeengine' ),
	] ); ?>
<?php
endif;
