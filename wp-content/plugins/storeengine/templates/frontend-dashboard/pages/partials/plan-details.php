<?php
/**
 * Order details table
 *
 * @var Subscription $subscription
 */

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$dates_to_display = [
	'start'                   => _x( 'Start date', 'customer subscription table header', 'storeengine' ),
	'last_order_date_created' => _x( 'Last order date', 'customer subscription table header', 'storeengine' ),
	'next_payment'            => _x( 'Next payment date', 'customer subscription table header', 'storeengine' ),
	'end'                     => _x( 'End date', 'customer subscription table header', 'storeengine' ),
	'trial_end'               => _x( 'Trial end date', 'customer subscription table header', 'storeengine' ),
];
$dates_to_display = apply_filters( 'storeengine/subscription/details_table_dates_to_display', $dates_to_display, $subscription );
?>
	<div class="storeengine-dashboard__section-wrapper">
		<?php do_action( 'storeengine/plan_details/before_plan_table', $subscription ); ?>
		<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper storeengine-dashboard__section--plan-details">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--plan-details" style="--separator-size:1px">
				<tbody class="no-header">
				<tr>
					<th class="storeengine-d-none storeengine-d-md-table-cell"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
					<td data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>"><?php echo esc_html( $subscription->get_status_title() ); ?></td>
				</tr>
				<?php foreach ( $dates_to_display as $date_type => $date_title ) { ?>
					<?php if ( $subscription->get_date( $date_type ) ) { ?>
						<tr>
							<th class="storeengine-d-none storeengine-d-md-table-cell"><?php echo esc_html( $date_title ); ?></th>
							<td data-title="<?php echo esc_attr( $date_title ); ?>"><?php echo esc_html( $subscription->get_date_to_display( $date_type ) ); ?></td>
						</tr>
					<?php } ?>
				<?php } ?>
				<?php if ( $subscription->get_next_payment_date() ) { ?>
					<tr>
						<th class="storeengine-d-none storeengine-d-md-table-cell"><?php esc_html_e( 'Payment', 'storeengine' ); ?></th>
						<td data-title="<?php esc_attr_e( 'Payment', 'storeengine' ); ?>">
							<span data-is_manual="<?php echo esc_attr( Formatting::bool_to_string( $subscription->is_manual() ) ); ?>" class="subscription-payment-method">
								<?php
								$payment_method = $subscription->get_payment_method_to_display( 'customer' );
								echo $payment_method ? esc_html( $payment_method ) : esc_html__( 'N/A', 'storeengine' )
								?>
							</span>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php do_action( 'storeengine/plan_details/after_plan_table', $subscription ); ?>
	</div>
<?php
