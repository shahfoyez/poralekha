<?php

use StoreEngine\Utils\Helper;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$available_gateways = Helper::get_payment_gateways()->get_available_payment_gateways();

if ( $available_gateways ) : ?>
	<?php current( $available_gateways )->set_current(); ?>
	<form id="add_payment_method" class="storeengine-add-payment-method-form" method="post">
		<div id="payment" class="storeengine-dashboard-payment storeengine-p-0">
			<ul class="storeengine-dashboard-payment-methods payment_methods methods storeengine-p-0 storeengine-m-0" style="list-style:none">
				<?php foreach ( $available_gateways as $gateway ) : ?>
					<li class="storeengine-payment-method storeengine-payment-method--<?php echo esc_attr( $gateway->id ); ?> payment_method_<?php echo esc_attr( $gateway->id ); ?>">
						<input
							id="payment_method_<?php echo esc_attr( $gateway->id ); ?>"
							type="radio"
							class="input-radio storeengine-mt-0 storeengine-ml-0 storeengine-radio"
							name="payment_method"
							value="<?php echo esc_attr( $gateway->id ); ?>"
							<?php checked( $gateway->chosen, true ); ?>
						/>
						<label class="storeengine-payment-method__label" for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
							<?php echo wp_kses_post( $gateway->get_icon() ); ?>
							<span><?php echo esc_html( $gateway->get_title() ); ?></span>
						</label>
						<div class="storeengine-payment-box storeengine-payment-box--<?php echo esc_attr( $gateway->id ); ?> payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>">
							<div class="storeengine-payment-box__inner">
								<div class="storeengine-payment-method-description__text">
									<?php $gateway->payment_fields(); ?>
								</div>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php do_action( 'storeengine/add_payment_method_form_bottom' ); ?>

			<div class="storeengine-checkout__order-btn">
				<input type="hidden" name="storeengine_add_payment_method" id="storeengine_add_payment_method" value="1" />
				<button
					type="submit"
					class="storeengine-btn storeengine-btn--preset-blue"
					id="place_order"
					value="<?php esc_attr_e( 'Add payment method', 'storeengine' ); ?>"
					style="--padding:13px 30px;--font-size:1rem;--font-weight:600;width:100%"
				><?php esc_html_e( 'Add payment method', 'storeengine' ); ?></button>
			</div>
		</div>
	</form>
<?php else : ?>
	<div class="storeengine-dashboard-payment-gateways-empty storeengine-notice storeengine-notice-warning">
		<p><?php esc_html_e( 'New payment methods can only be added during checkout. Please contact us if you require assistance.', 'storeengine' ); ?></p>
	</div>
<?php endif; ?>
