<?php
/**
 * Gateway PayPal.
 */

namespace StoreEngine\Addons\Paypal;

use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\GatewayAdapterInterface;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class GatewayPaypal extends PaymentGateway implements GatewayAdapterInterface {

	public int $index = 1;

	public function __construct() {
		$this->setup();

		$this->init_admin_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
	}

	protected function setup() {
		$this->id                 = 'paypal';
		$this->icon               = apply_filters( 'storeengine/paypal_icon', Helper::get_assets_url( 'images/payment-methods/paypal-alt.svg' ) );
		$this->method_title       = __( 'PayPal', 'storeengine' );
		$this->method_description = __( 'PayPal Standard redirects customers to PayPal to enter their payment information.', 'storeengine' );
		$this->has_fields         = true;
		$this->verify_config      = true;
		$this->supports           = [
			'products',
			//'refunds',
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change',
		];
	}

	/**
	 * Whether PayPal still needs its API credentials before it can accept payments.
	 *
	 * Powers the "toggle on -> redirect to settings" flow and the post-setup
	 * "connect your payment method" admin notice.
	 *
	 * @return bool
	 */
	public function needs_setup(): bool {
		$key_type = $this->get_option( 'is_production', true ) ? 'production' : 'sandbox';

		return empty( $this->get_option( 'client_id_' . $key_type ) ) || empty( $this->get_option( 'client_secret_' . $key_type ) );
	}

	/**
	 * Verify Config.
	 *
	 * @param array $config
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	public function verify_config( array $config ) {
		$is_production = $config['is_production'] ?? true;
		$key_type      = $is_production ? 'production' : 'sandbox';
		$client_id     = $config[ 'client_id_' . $key_type ] ?? '';
		$client_secret = $config[ 'client_secret_' . $key_type ] ?? '';

		if ( ! $this->is_currency_supported() ) {
			throw new StoreEngineException(
				sprintf(
				/* translators: %1$s the shop currency, %2$s the PayPal currency support page link opening HTML tag, %3$s the link ending HTML tag. */
					esc_html__(
						'Attention: Your current StoreEngine store currency (%1$s) is not supported by PayPal. Please update your store currency to one that is supported by PayPal to ensure smooth transactions. Visit the %2$sPayPal currency support page%3$s for more information on supported currencies.',
						'storeengine'
					),
					esc_html( Formatting::get_currency() ),
					'<a href="' . esc_url( 'https://developer.paypal.com/api/rest/reference/currency-codes/' ) . '" target="_blank">',
					'</a>'
				),
				'currency-not-supported',
				null,
				400
			);
		}

		if ( ! $client_id ) {
			throw new StoreEngineException( esc_html__( 'PayPal Client ID is required.', 'storeengine' ), 'paypal-client-id-required', 400 );
		}

		if ( ! $client_secret ) {
			throw new StoreEngineException( esc_html__( 'PayPal Client secret is required.', 'storeengine' ), 'paypal-secret-id-required', 400 );
		}

		$response = PaypalExpressService::validate_credentials( $client_id, $client_secret, $is_production );

		if ( is_wp_error( $response ) ) {
			if ( 'http_request_failed' === $response->get_error_code() ) {
				throw StoreEngineException::from_wp_error( $response ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			throw new StoreEngineException( esc_html__( 'PayPal API keys are not valid. Please check your client id and client secret.', 'storeengine' ), 'invalid-paypal-api-keys', 400 );
		}
	}

	public function is_currency_supported( string $currency = null ): bool {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return in_array( $currency, PaypalExpressService::get_supported_currencies(), true );
	}

	public function is_available(): bool {
		if ( ! $this->is_currency_supported() ) {
			return false;
		}

		return parent::is_available();
	}

	protected function init_admin_fields() {
		$this->admin_fields = [
			'title'                    => [
				'label'    => __( 'Title', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'PayPal', 'storeengine' ),
				'priority' => 0,
			],
			'description'              => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your website.', 'storeengine' ),
				'default'  => '',
				'priority' => 0,
			],
			'is_production'            => [
				'label'    => __( 'Is Live Mode?', 'storeengine' ),
				'tooltip'  => __( 'Enable PayPal Live (Production) Mode.', 'storeengine' ),
				'type'     => 'checkbox',
				'default'  => true,
				'priority' => 0,
			],
			'client_id_production'     => [
				'label'        => __( 'Client ID', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'client_secret_production' => [
				'label'        => __( 'Client Secret', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'client_id_sandbox'        => [
				'label'        => __( 'Client ID (Sandbox)', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'client_secret_sandbox'    => [
				'label'        => __( 'Client Secret (Sandbox)', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
		];
	}

	public function payment_fields() {
		$user        = wp_get_current_user();
		$user_email  = '';
		$description = $this->get_description();
		$description = ! empty( $description ) ? $description : '';
		$firstname   = '';
		$lastname    = '';

		if ( $user && $user->ID ) {
			$user_email = get_user_meta( $user->ID, 'billing_email', true );
			$user_email = $user_email ?: $user->user_email;
			$firstname  = $user->user_firstname;
			$lastname   = $user->user_lastname;
		}

		if ( ! $this->get_option( 'is_production', true ) ) {
			$description .= PHP_EOL . '<h4>' . __( 'Sandbox Mode Enabled', 'storeengine' ) . '</h4>';
			/** @noinspection HtmlUnknownTarget */
			$description .= PHP_EOL . '<p>' . sprintf(
				/* translators: %s: Link to PayPal sandbox testing guide */
					__( 'Payments are not real in this environment. Use your PayPal <b>Sandbox test accounts</b> to complete a transaction. Refer to PayPal’s <a href="%s" target="_blank" rel="noopener noreferrer">Sandbox Testing Guide</a> for more details.', 'storeengine' ),
					'https://developer.paypal.com/tools/sandbox/'
				) . '</p>';
		}

		if ( $description ) {
			echo '<div class="storeengine-payment-method-description storeengine-mb-4">';
			// KSES is running within get_description, but not here since there may be custom HTML returned by extensions.
			echo wpautop( wptexturize( $description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		}

		?>
		<fieldset
			id="storeengine-<?php echo esc_attr( $this->id ); ?>-form"
			class="storeengine-<?php echo esc_attr( $this->id ); ?>-form storeengine-payment-form"
			data-email="<?php echo esc_attr( $user_email ); ?>"
			data-full-name="<?php echo esc_attr( trim( $firstname . ' ' . $lastname ) ); ?>"
			data-currency="<?php echo esc_attr( strtolower( Formatting::get_currency() ) ); ?>"
			style="background:transparent;border:none;padding:0;"
		>
			<div id="storeengine-paypal-element" class="storeengine-paypal-elements-field">
				<!-- A PayPal Element will be inserted here by js. -->
			</div>
		</fieldset>
		<?php
	}

	public function process_payment( Order $order ): array {
		/**
		 * Fires before collect payment for PayPal.
		 *
		 * @param $order $order Order object.
		 * @param PaymentGateway $gateway Gateway object.
		 */
		do_action( 'storeengine/api/paypal/before_collect_payment', $order, $this );

		$paypal_order_id = $order->get_meta( '_paypal_order_id' );

		if ( ! $paypal_order_id ) {
			throw new StoreEngineException( esc_html__( 'PayPal intent id missing.', 'storeengine' ), 'paypal-intent-id-missing' );
		}

		$order_context = new OrderContext( $order->get_status() );

		// Try capture.
		$result = PaypalExpressService::init()->capture_order( $paypal_order_id );

		if ( empty( $result ) ) {
			throw new StoreEngineException( esc_html__( 'Invalid paypal order.', 'storeengine' ), 'invalid_paypal_order', null, 400 );
		}

		$payment_success = 'COMPLETED' === strtoupper( $result->status );

		if ( $payment_success ) {
			$order->set_paid_status( 'paid' );
			// translators: %s is the gateway title.
			$order_context->proceed_to_next_status( 'process_order', $order, sprintf( __( '%s payment captured.', 'storeengine' ), $this->get_title() ) );
		} else {
			$order->set_paid_status( 'on_hold' );
			// Keep the order on hold for admin review.
			$order_context->proceed_to_next_status( 'hold_order', $order, __( 'Payment required review.', 'storeengine' ) );
		}

		if ( isset( $result->purchase_units[0]->payments->captures[0]->id ) ) {
			$payment_data = $result->purchase_units[0]->payments->captures[0];
			$order->set_transaction_id( $payment_data->id );

			if ( ! empty( $payment_data->seller_receivable_breakdown ) ) {
				// __data__.paypal_fee.value
				$order->update_meta_data( '_paypal_fees', $payment_data->seller_receivable_breakdown );
			}

			// Record what PayPal actually captured and flag any divergence from the
			// order total (e.g. gateway-added tax) so the invoice can be reconciled.
			if ( isset( $payment_data->amount->value ) ) {
				$currency = isset( $payment_data->amount->currency_code ) ? (string) $payment_data->amount->currency_code : $order->get_currency();
				Helper::record_order_settlement( $order, (float) $payment_data->amount->value, $currency );
			}
		}

		$order->save();

		/**
		 * Fires after PayPal credentials validation.
		 *
		 * @param array|mixed $result Result.
		 * @param $order $order Order object.
		 * @param PaymentGateway $gateway Gateway object.
		 */
		do_action( 'storeengine/api/paypal/after_collect_payment', $result, $order, $this );

		return [
			'result'   => $payment_success ? 'success' : 'failed',
			'redirect' => $order->get_checkout_order_received_url(),
		];
	}

	/**
	 * GatewayAdapterInterface — create a PayPal order so the client-side
	 * Buttons widget can drive the approval flow. Returns the PayPal order id;
	 * the React adapter feeds it to `paypal.Buttons({ createOrder })` and
	 * `process_payment()` captures it after `onApprove`.
	 *
	 * @return array|WP_Error  { paypal_order_id: string }
	 */
	public function create_intent( Order $order, Cart $cart ) {
		// Mirror the existing /create-order REST endpoint: persist the order
		// total + currency on the draft so PayPal's payload matches the cart.
		// Pay-for-existing-order context has no cart — the order's own totals
		// are already authoritative, so don't overwrite them with cart zeros.
		if ( ! $cart->is_cart_empty() ) {
			$order->set_currency( Formatting::get_currency() );
			$order->set_total( (float) $cart->get_total( 'edit' ) );
		}
		$order->set_payment_method( 'paypal' );
		$order->save();

		try {
			$paypal_order = PaypalExpressService::init()->create_order( $order );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}

		if ( ! isset( $paypal_order->id ) ) {
			return new WP_Error(
				'storeengine_paypal_intent_id_missing',
				__( 'Failed to initialize PayPal order.', 'storeengine' ),
				[ 'status' => 502 ]
			);
		}

		// Persist the PayPal order id on the WP order so process_payment() can
		// capture it after `onApprove` completes.
		$order->add_meta_data( '_paypal_order_id', $paypal_order->id, true );
		$order->save();

		return [
			'intent_id'       => $paypal_order->id,
			'paypal_order_id' => $paypal_order->id,
			// PayPal's popup OAuth round-trip can invalidate the page-load
			// X-WP-Nonce. Ship a fresh one so the client can swap it in
			// before posting /checkout/place or /checkout/pay-order.
			'wp_rest_nonce'   => wp_create_nonce( 'wp_rest' ),
		];
	}
}

// End of file gateway-paypal.php.
