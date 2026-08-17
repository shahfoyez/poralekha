<?php
/**
 * Payment methods
 *
 * Shows customer payment methods on the account page.
 */

use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\PaymentUtil;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$saved_methods        = PaymentUtil::get_customer_saved_methods_list( get_current_user_id() );
$columns              = PaymentUtil::get_account_payment_methods_columns();
$brand_abbreviations  = PaymentUtil::get_card_brand_abbreviations();
$get_card_brand_icons = PaymentUtil::get_card_brand_icons();
$has_methods          = (bool) $saved_methods;

do_action( 'storeengine/before_account_payment_methods', $has_methods );
?>
<section class="storeengine-payments">
	<?php
	// Title, description and the "Add Payment Method" action now render in the
	// shared dashboard page-header (topbar). See
	// storeengine_frontend_dashboard_topbar_actions() / _page_description().
	?>
	<?php if ( $has_methods ) : ?>
		<div class="storeengine-dashboard__table-wrapper">
			<table class="storeengine-dashboard__table">
				<thead>
				<tr>
					<?php foreach ( $columns as $column => $label ) { ?>
						<th scope="row" class="payment-method-<?php echo esc_attr( $column ); ?> col-<?php echo esc_attr( $column ); ?>">
							<?php if ( 'id' === $column ) { ?>
								<span aria-hidden="true">#</span>
								<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
							<?php } else { ?>
								<span class="nobr"><?php echo esc_html( $label ); ?></span>
							<?php } ?>
						</th>
					<?php } ?>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $saved_methods as $type => $methods ) : ?>
					<?php foreach ( $methods as $method ) : ?>
						<?php
						$brand       = strtolower( trim( (string) ( $method['method']['brand'] ?? '' ) ) );
						$brand_key   = sanitize_title( $brand ? $brand : 'card' );
						$brand_badge = $brand_abbreviations[ $brand ] ?? strtoupper( substr( preg_replace( '/[^a-z0-9]/i', '', $brand ), 0, 4 ) ?: 'CARD' );
						$brand_icons = $get_card_brand_icons[ $brand ] ?? '';
						$last4       = (string) ( $method['method']['last4'] ?? '' );
						$gateway     = (string) ( $method['method']['gateway'] ?? '' );
						$brand_label = PaymentUtil::get_credit_card_type_label( (string) ( $method['method']['brand'] ?? '' ) );
						$is_default  = ! empty( $method['is_default'] );
						$row_classes = 'storeengine-payments__row storeengine-payment-method storeengine-payment-method--' . $type;
						$row_classes .= ' storeengine-payment-method--' . $gateway;
						if ( $is_default ) {
							$row_classes .= ' storeengine-payment-method--default';
						}
						?>
						<tr class="<?php echo esc_attr( $row_classes ); ?>">
							<?php foreach ( $columns as $column => $label ) { ?>
								<?php if ( 'id' === $column ) { ?>
									<th scope="col" class="col-<?php echo esc_attr( $column ); ?>" data-title="<?php echo esc_attr( $label ); ?>">
										#<?php echo esc_html( $method['id'] ); ?>
									</th>
								<?php } else { ?>
									<td class="col-<?php echo esc_attr( $column ); ?> storeengine-payments__<?php echo esc_attr( $column ); ?>" data-title="<?php echo esc_attr( $label ); ?>">
										<?php
										if ( has_action( 'storeengine/account_payment_methods_column_' . $column ) ) {
											do_action( 'storeengine/account_payment_methods_column_' . $column, $method );
										} elseif ( 'method' === $column ) {
											?>
											<div class="storeengine-payments__method-info">
												<?php if ( $brand_icons ) { ?>
													<img src="<?php echo esc_url( $brand_icons ); ?>" alt="<?php echo esc_attr( $brand_badge ); ?>" class="storeengine-payments__brand-icon">
												<?php } else { ?>
												<span class="storeengine-payments__brand storeengine-payments__brand--<?php echo esc_attr( $brand_key ); ?>"><?php echo esc_html( $brand_badge ); ?></span>
												<?php } ?>
												<span class="storeengine-payments__method-copy">
													<?php
													if ( $last4 ) {
														/* translators: 1: credit card type 2: last 4 digits */
														printf( esc_html__( '%1$s ending in %2$s', 'storeengine' ), esc_html( $brand_label ), esc_html( $last4 ) );
													} else {
														echo esc_html( $brand_label );
													}
													?>
												</span>
											</div>
											<?php
//											if ( ! empty( $method['method']['last4'] ) ) {
//												/* translators: 1: credit card type 2: last 4 digits */
//												echo sprintf( esc_html__( '%1$s ending in %2$s', 'storeengine' ), esc_html( PaymentUtil::get_credit_card_type_label( $method['method']['brand'] ) ), esc_html( $method['method']['last4'] ) );
//											} else {
//												echo esc_html( PaymentUtil::get_credit_card_type_label( $method['method']['brand'] ) );
//											}
										} elseif ( 'gateway' === $column ) {
											?>
											<div class="storeengine-payments__method-info">
												<?php
												$gateway_instance = Payment_Gateways::get_instance()->get_gateway( $gateway );
												if ( $gateway_instance ) {
													echo wp_kses_post( $gateway_instance->get_title() );
												} else {
													echo esc_html( $gateway );
												}
												?>
											</div>
											<?php
										} elseif ( 'expires' === $column ) {
											?>
											<div class="storeengine-payments__expiry-info">
												<span><?php echo esc_html( $method['expires'] ); ?></span>
												<?php if ( $is_default ) : ?>
													<span class="storeengine-payments__badge"><?php esc_html_e( 'Default', 'storeengine' ); ?></span>
												<?php endif; ?>
											</div>
											<?php
										} elseif ( 'actions' === $column ) {
											storeengine_render_dashboard_action_buttons( $method['actions'] ?? [], 'payment-methods', __( 'Payment Methods', 'storeengine' ), false );
										}
										?>
									</td>
								<?php } ?>
							<?php } ?>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<?php
		storeengine_oops_message(
			[
				'title'   => esc_html__( 'No payment methods found', 'storeengine' ),
				'message' =>
					esc_html__( 'You do not have any payment method saved.', 'storeengine' ) .
					PHP_EOL .
					esc_html__( 'Click “Add Payment Method” to save a new payment method for faster checkout.', 'storeengine' ),
			]
		);
		?>
	<?php endif; ?>
</section>
<?php do_action( 'storeengine/after_account_payment_methods', $has_methods ); ?>
