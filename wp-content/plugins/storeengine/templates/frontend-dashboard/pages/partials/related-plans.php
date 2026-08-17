<?php
/**
 * Order details table
 *
 * @var Subscription[] $subscriptions
 */

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Addons\Subscription\Classes\Utils;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

?>
	<div class="storeengine-dashboard__section-wrapper">
		<div class="storeengine-dashboard__section-title">
			<h4 class="storeengine-dashboard__section-title-heading"><?php esc_html_e( 'Related subscriptions', 'storeengine' ); ?></h4>
		</div>
		<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper storeengine-dashboard__section--subscription-list">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--subscriptions">
				<thead>
				<tr>
					<th scope="col" class="col-subscription-id">
						<span class="screen-reader-text"><?php esc_html_e( 'Subscription ID', 'storeengine' ); ?></span>
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
				<?php foreach ( $subscriptions as $subscription ) : ?>
					<tr>
						<td class="col-subscription-id" data-title="<?php esc_attr_e( 'ID', 'storeengine' ); ?>">
							<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'plans', $subscription->get_id() ) ); ?>">
								<?php echo esc_html( $subscription->get_id() ); ?>
							</a>
						</td>
						<td class="col-subscription-items" data-title="<?php esc_attr_e( 'Item', 'storeengine' ); ?>">
							<?php
							$line_item = $subscription->get_items();
							if ( $line_item ) {
								/** @var \StoreEngine\Classes\Order\OrderItemProduct $line_item */
								$line_item = reset( $line_item );
								$item_link = $line_item->get_product() && 'publish' === $line_item->get_product()->get_status() ? get_permalink( $line_item->get_product_id() ) : null;
								if ( $item_link ) {
									printf(
										'<a href="%1$s">%2$s</a>',
										esc_url( $item_link ),
										esc_html( $line_item->get_name() )
									);
								} else {
									echo esc_html( $line_item->get_name() );
								}
							} else {
								esc_html_e( 'N/A', 'storeengine' );
							}
							?>
						</td>
						<td class="col-subscription-date" data-title="<?php esc_attr_e( 'Created Date', 'storeengine' ); ?>">
							<?php storeengine_print_time( $subscription->get_date_created_gmt(), 'd M Y, h:i A (T)' ); ?>
						</td>
						<td class="col-subscription-date" data-title="<?php esc_attr_e( 'Next renewal date', 'storeengine' ); ?>">
							<?php storeengine_print_time( $subscription->get_next_payment_date(), 'd M Y, h:i A (T)' ); ?>
						</td>
						<td class="col-subscription-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
							<span class="storeengine-status storeengine-status--<?php echo esc_attr( $subscription->get_status() ); ?>">
								<?php echo esc_html( $subscription->get_status_title() ); ?>
							</span>
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
		</div>
	</div>
<?php
