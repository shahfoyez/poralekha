<?php
/**
 * Order details.
 *
 * @var Order $order
 */

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$notes     = $order->get_customer_order_notes();
$downloads = $order->get_downloadable_items();
$actions   = array_filter( storeengine_get_account_orders_actions( $order ), fn( $key ) => 'view' !== $key, ARRAY_FILTER_USE_KEY );

?>
<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--order">
	<div class="storeengine-frontend-dashboard-page--header storeengine-mb-6">
		<div class="storeengine-frontend-dashboard-page--header__left">
			<h3 class="storeengine-mt-0 storeengine-mb-2">
				<span class="order-number">
					<span aria-hidden="true">#</span>
					<?php echo esc_html( $order->get_order_number() ); ?>
				</span>
				<span class="storeengine-status storeengine-status--<?php echo esc_attr( $order->get_status() ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Order status:', 'storeengine' ); ?></span>
					<?php echo esc_html( $order->get_status_title() ); ?>
				</span>
			</h3>
			<div class="order-date">
				<?php
				$date = $order->get_order_placed_date_gmt() ?? $order->get_date_created_gmt();
				if ( $date ) {
					printf(
					// translators: %s: Order created date.
						esc_html__( 'Ordered on %s', 'storeengine' ),
						sprintf(
							'<time datetime="%1$s" data-format="%3$s">%2$s</time>',
							esc_attr( $date->date_i18n( 'Y-m-d H:i:s' ) ),
							sprintf(
								// translators: %1$s: Order created date. %2$s: Order created time.
								esc_html__( '%1$s at %2$s', 'storeengine' ),
								esc_html( $date->toLocal( 'M d, Y' ) ),
								esc_html( $date->toLocal( 'g:i A' ) )
							),
							esc_attr_x( 'MMM DD, YYYY [at] h:mm A', 'Moment.js supported date format for user-dashboard order date (e.g Aug 26, 2025 at 5:48 PM).', 'storeengine' ),
						)
					);
				}
				?>
			</div>
		</div>
		<div class="storeengine-frontend-dashboard-page--header__right">
			<?php storeengine_render_dashboard_action_buttons( $actions ); ?>
		</div>
	</div>
	<?php
	if ( $notes ) {
		Template::get_template(
			'frontend-dashboard/pages/partials/order-notes.php',
			[ 'notes' => $notes ]
		);
	}

	if ( ! empty( $downloads ) ) {
		Template::get_template(
			'frontend-dashboard/pages/partials/order-downloads.php',
			[ 'downloads' => $downloads ]
		);
	}

	do_action( 'storeengine/order/before_order_details', $order );

	Template::get_template(
		'frontend-dashboard/pages/partials/order-details.php',
		[ 'order' => $order ]
	);

	do_action( 'storeengine/order/after_order_details', $order );

	// Per-item shipping status + tracking (physical items only; the partial
	// renders nothing for digital-only orders).
	Template::get_template(
		'frontend-dashboard/pages/partials/order-shipping-status.php',
		[ 'order' => $order ]
	);

	Template::get_template(
		'frontend-dashboard/pages/partials/customer-details.php',
		[ 'order' => $order ]
	);
	?>
</div>
<?php
