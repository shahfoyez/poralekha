<?php
/**
 * @var Order $order
 * @var Subscription[] $subscriptions
 */

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Order;
use StoreEngine\Addons\Subscription\Classes\Utils;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

?>
<div class="storeengine-order-subscription-details storeengine-mt-6 storeengine-pt-6">
	<div class="storeengine-order-subscription-details-header">
		<h3><?php esc_html_e( 'Related Subscriptions', 'storeengine' ); ?></h3>
	</div>
	<div class="storeengine-order-subscription-details-body storeengine-dashboard__table-wrapper">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--subscriptions">
			<thead>
			<tr>
				<th scope="col" class="col-subscription-id">
					<span class="screen-reader-text"><?php esc_html_e( 'Subscription ID', 'storeengine' ); ?></span>
					#
				</th>
				<th scope="col" class="col-subscription-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-next-payment-date"><?php esc_html_e( 'Next payment', 'storeengine' ); ?></th>
				<th scope="col" class="col-subscription-total"><?php esc_html_e( 'Total', 'storeengine' ); ?></th>
				<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'storeengine' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $subscriptions as $subscription ): ?>
				<tr>
					<td class="col-subscription-id" data-title="<?php esc_attr_e( 'ID', 'storeengine' ); ?>">
						<a href="<?php echo esc_url( storeengine_get_dashboard_endpoint_url( 'plans', $subscription->get_id() ) ); ?>">
							<?php echo esc_html( $subscription->get_id() ); ?>
						</a>
					</td>
					<td class="col-subscription-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
						<span class="storeengine-status storeengine-status--<?php echo esc_attr( $subscription->get_status() ); ?>">
							<?php echo esc_html( $subscription->get_status_title() ); ?>
						</span>
					</td>
					<td class="col-subscription-next-payment-date" data-title="<?php esc_attr_e( 'Next renewal date', 'storeengine' ); ?>">
						<?php storeengine_print_time( $subscription->get_next_payment_date(), 'd M Y, h:i A (T)' ); ?>
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
