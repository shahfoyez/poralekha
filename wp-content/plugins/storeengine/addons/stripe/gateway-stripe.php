<?php
/**
 * Gateway Stripe.
 */

namespace StoreEngine\Addons\Stripe;

use Exception;
use StoreEngine;
use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokenCc;
use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokens;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\GatewayAdapterInterface;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Classes\PaymentTokens\PaymentTokens;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Stripe\Exception\ApiErrorException;
use StoreEngine\Stripe\PaymentIntent;
use StoreEngine\Stripe\PaymentMethod;
use StoreEngine\Stripe\Source;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use Throwable;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class GatewayStripe extends PaymentGateway implements GatewayAdapterInterface {

	public int $index = 1;

	protected array $amex_unsupported_currencies = [
		'AFN',
		'AOA',
		'ARS',
		'BOB',
		'BRL',
		'CLP',
		'COP',
		'CRC',
		'CVE',
		'DJF',
		'FKP',
		'GNF',
		'GTQ',
		'HNL',
		'LAK',
		'MUR',
		'NIO',
		'PAB',
		'PEN',
		'PYG',
		'SHP',
		'SRD',
		'STD',
		'UYU',
		'XOF',
		'XPF',
	];

	public function __construct() {
		$this->setup();

		$this->init_admin_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->saved_cards = $this->get_option( 'saved_cards' );

		add_filter( 'storeengine/saved_payment_methods_list', [ $this, 'filter_saved_payment_methods_list' ], 10, 2 );
		// @TODO add-action storeengine/payment_gateway/$this->id/settings_saved", [ $this, 'handle_webhook_setup' ]

		// Registers both storeengine/subscription/scheduled_payment_{id}
		// and storeengine_pro/installment-plan/scheduled_payment_{id} automatically.
		$this->register_subscription_hooks();
		$this->tokenization_script();
	}

	public function handle_webhook_setup( &$self ) {
		// @TODO implement webhook for capture delayed payments.
	}

	/**
	 * Removes all saved payment methods when the setting to save cards is disabled.
	 *
	 * @param array $list List of payment methods passed from the saved-payment-methods list.
	 * @param int|string $customer_id The customer to fetch payment methods for.
	 *
	 * @return array  Filtered list of customers payment methods.
	 */
	public function filter_saved_payment_methods_list( array $list, $customer_id ): array {
		if ( ! $this->saved_cards ) {
			return [];
		}

		return $list;
	}

	protected function setup() {
		$this->id                 = 'stripe';
		$this->icon               = apply_filters( 'storeengine/stripe_icon', Helper::get_assets_url( 'images/payment-methods/stripe-alt.svg' ) );
		$this->method_title       = __( 'Stripe', 'storeengine' );
		$this->method_description = __( 'Stripe works by adding payment fields on the checkout and then sending the details to Stripe for verification.', 'storeengine' );
		$this->has_fields         = true;
		$this->verify_config      = true;
		$this->supports           = [
			'products',
			'refunds',
			// Saved cards features.
			'tokenization',
			'add_payment_method',
			// Subscriptions features.
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscriptions_automatic_payments',
			// SubscriptionScheduler checks this flag before generating renewal orders.
			'gateway_scheduled_payments',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change',
		];
	}

	/**
	 * Whether Stripe still needs its API keys before it can accept payments.
	 *
	 * Powers the "toggle on -> redirect to settings" flow and the post-setup
	 * "connect your payment method" admin notice.
	 *
	 * @return bool
	 */
	public function needs_setup(): bool {
		if ( (bool) $this->get_option( 'is_production', true ) ) {
			return empty( $this->get_option( 'publishable_key' ) ) || empty( $this->get_option( 'secret_key' ) );
		}

		return empty( $this->get_option( 'test_publishable_key' ) ) || empty( $this->get_option( 'test_secret_key' ) );
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
		if ( $is_production ) {
			$publishable_key = $config['publishable_key'] ?? '';
			$secret_key      = $config['secret_key'] ?? '';
		} else {
			$publishable_key = $config['test_publishable_key'] ?? '';
			$secret_key      = $config['test_secret_key'] ?? '';
		}

		if ( ! $publishable_key ) {
			throw new StoreEngineException( esc_html__( 'Stripe Publishable Key is required.', 'storeengine' ), 'publishable-key-is-required', 400 );
		}

		if ( ! $secret_key ) {
			throw new StoreEngineException( esc_html__( 'Stripe Secret Key is required.', 'storeengine' ), 'secret-key-is-required', 400 );
		}

		if ( ! $this->is_currency_supported() ) {
			throw new StoreEngineException(
				sprintf(
				/* translators: %1$s the shop currency, %2$s the PayPal currency support page link opening HTML tag, %3$s the link ending HTML tag. */
					esc_html__(
						'Attention: Your current StoreEngine store currency (%1$s) is not supported by Stripe. Please update your store currency to one that is supported by Stripe to ensure smooth transactions. Visit the %2$sStripe currency support page%3$s for more information on supported currencies.',
						'storeengine'
					),
					esc_html( Formatting::get_currency() ),
					'<a href="' . esc_url( 'https://docs.stripe.com/currencies#presentment-currencies' ) . '" target="_blank">',
					'</a>'
				),
				'currency-not-supported',
				null,
				400
			);
		}

		$result = StripeService::validate_publishable_key( $publishable_key );

		if ( ! $result ) {
			throw new StoreEngineException( esc_html__( 'Stripe Publishable Key Is Invalid! Please update publishable key.', 'storeengine' ), 'stripe-publishable-key-is-invalid', 400 );
		}

		$account_id = StripeService::validate_keys( $secret_key );

		if ( is_wp_error( $account_id ) ) {
			throw new StoreEngineException( esc_html__( 'Stripe Secret Key Is Invalid! Please update secret key.', 'storeengine' ), 'stripe-secret-key-is-invalid', 400 );
		}
	}

	public function is_currency_supported( string $currency = null ): bool {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return in_array( $currency, StripeService::get_supported_currencies(), true );
	}

	public function is_available(): bool {
		if ( Helper::is_add_payment_method_page() && ! $this->saved_cards ) {
			return false;
		}

		if ( Helper::is_request( 'admin' ) && Helper::is_request( 'ajax' ) ) {
			// @TODO Maybe check referrer as ajax can be from frontend (exclude frontend ajax actions).
			$frontend_ajax_actions = [ 'storeengine/update_checkout' ];

			if ( ! empty( $_REQUEST['action'] ) && ! in_array( wp_unslash( $_REQUEST['action'] ), $frontend_ajax_actions, true ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Recommended
				return parent::is_available();
			}
		}

		if ( Helper::is_request( 'ajax' ) && isset( $_REQUEST['payment_method'], $_REQUEST['payment_intent_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return parent::is_available();
		}

		if ( ! Helper::is_dashboard() && StoreEngine::init()->cart ) {
			if ( ! $this->is_currency_supported() ) {
				return false;
			}
		}

		// Gateway is not available without SSL if using live mode.
		if ( $this->needs_ssl_setup() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Whether the store needs to use SSL.
	 *
	 * @return bool True if SSL is needed but not set.
	 * @since 1.6.9
	 */
	private function needs_ssl_setup(): bool {
		return $this->get_option( 'is_production', true ) && ! is_ssl(); // (testmode is defined in the classes that use this class)
	}

	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < StripeService::get_minimum_amount() ) {
			throw new StoreEngineException(
				wp_kses_post(
					sprintf(
					/* translators: %1$s. Minimum allowed amount (including currency symbol) by the gateway. */
						__( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'storeengine' ),
						Formatting::price( StripeService::get_minimum_amount() / 100 )
					)
				),
				'did-not-meet-minimum-amount'
			);
		}
	}

	/**
	 * Build the Stripe webhook REST URL safely.
	 *
	 * Admin fields are built while StoreEngine boots on `plugins_loaded` (via the
	 * settings AJAX handler), which is BEFORE WordPress instantiates the global
	 * `$wp_rewrite`. Calling rest_url() then fatals with
	 * "Call to a member function using_index_permalinks() on null". Instantiating
	 * `$wp_rewrite` on demand makes rest_url() safe; core replaces it later in
	 * wp-settings.php with an identical instance, so this has no side effects.
	 */
	protected function get_webhook_url(): string {
		if ( empty( $GLOBALS['wp_rewrite'] ) ) {
			$GLOBALS['wp_rewrite'] = new \WP_Rewrite(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return esc_url_raw( rest_url( STOREENGINE_PLUGIN_SLUG . '/v1/stripe/trigger' ) );
	}

	protected function init_admin_fields() {
		$webhook_url = $this->get_webhook_url();

		$this->admin_fields = [
			'title'                => [
				'label'    => __( 'Title', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'Debit/Credit Card', 'storeengine' ),
				'priority' => 0,
			],
			'description'          => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your website.', 'storeengine' ),
				'priority' => 0,
			],
			'is_production'        => [
				'label'    => __( 'Is Live Mode?', 'storeengine' ),
				'tooltip'  => __( 'Enable Stripe Live (Production) Mode.', 'storeengine' ),
				'type'     => 'checkbox',
				'default'  => true,
				'priority' => 0,
			],
			'publishable_key'      => [
				'label'        => __( 'Publishable Key', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'secret_key'           => [
				'label'        => __( 'Secret Key', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'test_publishable_key' => [
				'label'        => __( 'Publishable Key (Sandbox)', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'test_secret_key'      => [
				'label'        => __( 'Secret Key (Sandbox)', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'saved_cards'          => [
				'title'       => __( 'Saved Cards', 'storeengine' ),
				'label'       => __( 'Enable Payment via Saved Cards', 'storeengine' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.', 'storeengine' ),
				'default'     => true,
			],
			'use_native_subscriptions' => [
				'title'       => __( 'Native Stripe Billing', 'storeengine' ),
				'label'       => __( 'Use native Stripe subscriptions (Billing)', 'storeengine' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, new subscriptions are created as real Stripe subscriptions and billed/dunned by Stripe (they appear in your Stripe dashboard). Renewals are recorded from Stripe webhooks. If disabled, StoreEngine charges the saved card itself on each renewal. Only affects NEW subscriptions — existing ones keep their current billing method.', 'storeengine' ),
				'default'     => false,
			],
			'webhook_url'              => [
				'label'       => __( 'Webhook URL', 'storeengine' ),
				'type'        => 'copy_text',
				'value'       => $webhook_url,
				'priority'    => 0,
				'description' => __( 'Add this URL as an event destination in your Stripe dashboard (Snapshot payload), then subscribe it to the invoice.paid, invoice.payment_succeeded, invoice.payment_failed, customer.subscription.updated and customer.subscription.deleted events. Paste the destination\'s signing secret below.', 'storeengine' ),
			],
			'webhook_secret'           => [
				'label'        => __( 'Webhook Signing Secret', 'storeengine' ),
				'type'         => 'password',
				'tooltip'      => __( 'Stripe webhook endpoint signing secret (starts with whsec_) for Live mode. Required for native Stripe Billing.', 'storeengine' ),
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
			],
			'test_webhook_secret'      => [
				'label'        => __( 'Webhook Signing Secret (Sandbox)', 'storeengine' ),
				'type'         => 'password',
				'tooltip'      => __( 'Stripe webhook endpoint signing secret (starts with whsec_) for Test mode. Required for native Stripe Billing.', 'storeengine' ),
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
			],
		];
	}

	/**
	 * Whether native Stripe Billing (real Stripe subscriptions) is enabled for
	 * this gateway. When false, subscriptions use StoreEngine's own off-session
	 * renewal charging.
	 */
	public function is_native_subscriptions_enabled(): bool {
		return Formatting::string_to_bool( $this->get_option( 'use_native_subscriptions', false ) );
	}

	public function payment_fields() {
		$user                 = wp_get_current_user();
		$user_email           = '';
		$description          = $this->get_description();
		$description          = ! empty( $description ) ? $description : '';
		$firstname            = '';
		$lastname             = '';

		if ( $user && $user->ID ) {
			$user_email = get_user_meta( $user->ID, 'billing_email', true );
			$user_email = $user_email ?: $user->user_email;
			$firstname  = $user->user_firstname;
			$lastname   = $user->user_lastname;
		}

		if ( ! $this->get_option( 'is_production', true ) ) {
			$description .= PHP_EOL . '<h4>' . __( 'Test Mode Enabled!', 'storeengine' ) . '</h4>';
			/** @noinspection HtmlUnknownTarget */
			$description .= PHP_EOL . '<p>' . sprintf(
				/* translators: %s: Link to Stripe test mode testing guide */
					__( 'Payments are not real in this environment. Use <b>4242 4242 4242 4242</b>, any CVC, and a valid expiration date. Refer to Stripe’s <a href="%s" target="_blank" rel="noopener noreferrer">testing documentation</a> for more test cards.', 'storeengine' ),
					'https://docs.stripe.com/testing'
				) . '</p>';
		}

		ob_start();
		?>
		<div class="storeengine-payment-method-description storeengine-mb-4">
			<?php
			// KSES is running within get_description, but not here since there may be custom HTML returned by extensions.
			echo wpautop( wptexturize( $description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<?php
		if ( $this->maybe_display_tokenization() ) {
			$this->saved_payment_methods();
		}
		?>
		<fieldset id="storeengine-<?php echo esc_attr( $this->id ); ?>-cc-form"
				  class="storeengine-credit-card-form storeengine-payment-form storeengine-mt-4"
				  data-email="<?php echo esc_attr( $user_email ); ?>"
				  data-full-name="<?php echo esc_attr( trim( $firstname . ' ' . $lastname ) ); ?>"
				  data-currency="<?php echo esc_attr( strtolower( Formatting::get_currency() ) ); ?>"
				  style="background:transparent;border:none;padding:0;">
			<div id="storeengine-stripe-card-element" class="storeengine-stripe-elements-field">
				<!-- A Stripe Element will be inserted here by js. -->
			</div>
		</fieldset>
		<?php

		if ( $this->is_saved_cards_enabled() ) {
			$this->save_payment_method_checkbox( $this->maybe_force_save_payment() );
		}

		ob_end_flush();
	}

	/**
	 * Get user from order.
	 *
	 * @param Order $order
	 *
	 * @return WP_User
	 */
	public function get_user_from_order( $order ) {
		$user = $order->get_user();
		if ( false === $user ) {
			$user = wp_get_current_user();
		}

		return $user;
	}

	/**
	 * Get Stripe customer from order.
	 *
	 * @param Order $order
	 *
	 * @return StripeCustomer
	 * @throws StoreEngineException
	 */
	public function get_stripe_customer_from_order( Order $order ): StripeCustomer {
		$user = $this->get_user_from_order( $order );

		return new StripeCustomer( $user->ID );
	}

	/**
	 * Newly created customer (guest checkout).
	 *
	 * @param StripeCustomer $customer
	 * @param Order $order
	 * @param string $customer_id
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	public function update_new_stripe_customer( StripeCustomer $customer, Order $order, string $customer_id ) {
		if ( $customer->get_id() ) {
			return;
		}
		$customer->set_id( $customer_id );
		$customer->update_id_in_meta( $customer_id );
		$customer->clear_cache();
		$customer->update_customer( [ 'order' => $order ] );
	}

	/**
	 * Returns true if a payment is needed for the current cart or order.
	 * Pre-Orders and Subscriptions may not require an upfront payment, so we need to check whether
	 * or not the payment is necessary to decide for either a setup intent or a payment intent.
	 *
	 * @param Order $order The order ID being processed.
	 *
	 * @return bool Whether a payment is necessary.
	 */
	public function is_payment_needed( Order $order ): bool {
		return 0 < StripeService::get_stripe_amount( $this->get_total_payment( $order ), $order->get_currency() );
	}

	/**
	 * @param Order $order
	 *
	 * @return array|WP_Error
	 * @throws StoreEngineException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 */
	public function process_payment( Order $order ) {
		$payment_needed     = $this->is_payment_needed( $order );
		$using_saved_method = false;

		// Check for selected token.
		$selected_payment_token = $this->get_selected_token_from_request();

		// Check for subscription.
		$force_save_source = $this->should_force_save_payment( $order );

		if ( $payment_needed ) {
			$this->validate_minimum_order_amount( $order );
		}

		try {
			$customer         = $this->get_stripe_customer_from_order( $order );
			$order_context    = new OrderContext( $order->get_status() );
			$has_subscription = StoreEngine::init()->get_cart()->get_meta( 'has_subscription' ) ?? false;
			$has_trial        = StoreEngine::init()->get_cart()->get_meta( 'has_trial' ) ?? false;

			// Native Stripe Billing: create real Stripe subscriptions instead of
			// charging the order ourselves. Additive branch — the off-session flow
			// below is untouched when native mode is off.
			if ( $has_subscription && $this->is_native_subscriptions_enabled() ) {
				return $this->process_native_subscription_payment( $order, $customer, $order_context, $selected_payment_token );
			}

			// No up-front payment is due (free trial, $0 first billing period,
			// full discount, etc.). When a card must be kept on file for future
			// subscription renewals, collect it via a SetupIntent instead of
			// creating a $0 PaymentIntent — Stripe rejects the latter with the
			// "amount must be >= the minimum charge amount ... use a Setup Intent
			// instead" error. This is now driven off the order (via
			// $force_save_source, which checks the order's items), not just live
			// cart meta, so it also covers order-pay / saved-card flows where the
			// checkout cart is empty.
			if ( ! $payment_needed && $force_save_source ) {
				if ( 'new' === $selected_payment_token ) {
					// add_payment_method() returns the saved token's DB id (int),
					// not the token object — reload the PaymentToken so the
					// get_token() call below resolves the Stripe source id.
					[ 'token' => $token_id ] = $this->add_payment_method( Formatting::clean( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$token                   = PaymentTokens::get_token( absint( $token_id ) );

					if ( ! $token ) {
						throw new StoreEngineException( esc_html__( 'Payment token not found.', 'storeengine' ), 'payment-token-not-found', [
							'status' => 404,
							'token'  => $token_id,
						] );
					}
				} else {
					$token = PaymentTokens::get_token( absint( $selected_payment_token ) );

					if ( ! $token ) {
						throw new StoreEngineException( esc_html__( 'Payment token not found.', 'storeengine' ), 'payment-token-not-found', [
							'status' => 404,
							'token'  => $selected_payment_token,
						] );
					}

					$this->assert_token_ownership( $token, $order );
				}

				$payment_method = StripeService::init()->get_payment_method( $token->get_token( 'process_trial_checkout' ), false );
				$this->update_new_stripe_customer( $customer, $order, $payment_method->customer );
				// Set order as paid
				$order->set_paid_status( 'paid' );
				$order_context->proceed_to_next_status( 'process_order', $order, _x( 'Payment not needed.', 'Stripe payment method', 'storeengine' ) );

				$order->delete_meta_data( StripeOrder::META_STRIPE_PAYMENT_AWAITING_ACTION );
				$order->add_meta_data( '_stripe_payment_method', $payment_method->type, true );
				$order->add_meta_data( '_stripe_customer_id', $payment_method->customer, true );
				$order->add_meta_data( '_stripe_source_id', $payment_method->id, true );
				$order->save();

				$this->maybe_update_source_on_subscription_order( $order, $payment_method, $payment_method->type );

				return [
					'result'   => 'success',
					'redirect' => $order->get_checkout_order_received_url(),
				];
			}

			// Still nothing due and no card to keep on file (e.g. a fully
			// discounted one-off order) — complete the $0 order without creating
			// any Stripe intent. Everything below this point assumes an actual
			// charge is required.
			if ( ! $payment_needed ) {
				$order->set_paid_status( 'paid' );
				$order_context->proceed_to_next_status( 'process_order', $order, _x( 'Payment not needed.', 'Stripe payment method', 'storeengine' ) );
				$order->delete_meta_data( StripeOrder::META_STRIPE_PAYMENT_AWAITING_ACTION );
				$order->save();

				return [
					'result'   => 'success',
					'redirect' => $order->get_checkout_order_received_url(),
				];
			}

			// Using saved payment method.
			if ( $selected_payment_token && 'new' !== $selected_payment_token ) {
				$force_save_source = false;
				$token             = PaymentTokens::get_token( absint( $selected_payment_token ) );

				if ( ! $token ) {
					throw new StoreEngineException( esc_html__( 'Payment token not found.', 'storeengine' ), 'payment-token-not-found', [
						'status' => 404,
						'token'  => $selected_payment_token,
					] );
				}

				$this->assert_token_ownership( $token, $order );

				// Create off_session payment intent from saved-payment-method (token).
				$intent             = StripeService::init()->create_payment_intent( $order, (float) $order->get_total( 'create_intent' ), $customer->get_id(), $token->get_token( 'payment_intent' ) );
				$using_saved_method = true;
				$order->update_meta_data( '_stripe_intent_id', $intent->id );
				$order->save();
			} else {
				// Check whether there is an existing intent.
				// This function can only deal with *payment* intents
				$payment_intent_id = $order->get_meta( '_stripe_intent_id', true, 'edit' );
				$payment_intent_id = $payment_intent_id ?: sanitize_text_field( wp_unslash( $_POST['payment_intent_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

				if ( $payment_intent_id ) {
					$intent = StripeService::init()->get_payment_intent( $payment_intent_id, true );
				} else {
					// No client-created intent. When the customer hasn't chosen
					// to enter a new card — e.g. paying an installment / renewal
					// order from the frontend dashboard — charge the saved Stripe
					// source off-session, the same way the automatic
					// scheduled-payment flow does. The source rides on the order
					// or, for renewal/installment orders, on its subscription.
					$saved = ( 'new' !== $selected_payment_token )
						? $this->resolve_saved_stripe_source( $order )
						: [ 'source_id' => '', 'customer_id' => '' ];

					if ( empty( $saved['source_id'] ) ) {
						throw new StoreEngineException( esc_html__( 'Stripe payment intent id is missing!', 'storeengine' ), 'payment-intent-missing' );
					}

					$intent             = StripeService::init()->create_payment_intent(
						$order,
						(float) $order->get_total( 'create_intent' ),
						$saved['customer_id'] ?: $customer->get_id(),
						$saved['source_id']
					);
					$using_saved_method = true;
					$order->update_meta_data( '_stripe_intent_id', $intent->id );
					$order->save();
				}
			}

			// Newly created customer (guest checkout).
			$this->update_new_stripe_customer( $customer, $order, $intent->customer );

			if ( $payment_needed ) {
				$response = $this->stripe_process_payment( $order, $intent, $order_context );
				if ( is_wp_error( $response ) ) {
					return $response;
				}
			} else {
				$order->set_paid_status( 'paid' );
				$order_context->proceed_to_next_status( 'process_order', $order, _x( 'Payment not needed.', 'Stripe payment method', 'storeengine' ) );
				$order->delete_meta_data( StripeOrder::META_STRIPE_PAYMENT_AWAITING_ACTION );
			}

			//$source = StripeService::init()->get_payment_method( $intent->payment_method, false );
			$source = StripeService::init()->get_payment_method( $response->payment_method, false );
			$order->add_meta_data( '_stripe_response_id', $response->id, true );
			$order->add_meta_data( '_stripe_currency', $response->currency, true );
			$order->add_meta_data( '_stripe_payment_method', $source->type, true );
			$order->add_meta_data( '_stripe_customer_id', $source->customer, true );
			$order->add_meta_data( '_stripe_source_id', $source->id, true );
			$order->add_meta_data( StripeOrder::META_STRIPE_CHARGE_CAPTURED, 'yes', true );
			$order->save();

			if ( $force_save_source && $source ) {
				$this->save_payment_method( $source );
			}

			if ( $this->has_subscription( $order ) ) {
				$this->maybe_update_source_on_subscription_order( $order, $source, $source->type );
			}

			/**
			 * Fires after stripe intent creation.
			 *
			 * @param PaymentIntent|WP_Error $result Result.
			 * @param Order $order Order object.
			 */
			do_action( 'storeengine/api/stripe/after_capture_payment', $response, $order );

			if ( $using_saved_method && $source ) {
				$this->update_stripe_payment_source( $order, $source );
			}

			return [
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			];
		} catch ( StoreEngineException $e ) {
			$order->update_status(
				OrderStatus::PAYMENT_FAILED,
				/* translators: %s. Error details. */
				sprintf( __( 'Payment failed. Error: %s', 'storeengine' ), $e->getMessage() )
			);

			throw $e;
		} catch ( Throwable $e ) {
			$order->update_status(
				OrderStatus::PAYMENT_FAILED,
				/* translators: %s. Error details. */
				sprintf( __( 'Payment failed. Error: %s', 'storeengine' ), $e->getMessage() )
			);

			throw StoreEngineException::convert_exception( $e, 'stripe_processing_error' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * Ensure a selected saved payment token belongs to the person legitimately
	 * driving this checkout before it is used to charge a card off-session.
	 *
	 * PaymentTokens::get_token() is an unscoped primary-key lookup, so without
	 * this guard a logged-in customer could pass another customer's token id and
	 * charge that customer's saved card (IDOR). The token is accepted only when
	 * it belongs to the current user or to the order's customer (the latter
	 * covers admin / order-pay flows acting on a customer's behalf).
	 *
	 * @throws StoreEngineException When the token is not owned by the caller.
	 */
	protected function assert_token_ownership( PaymentToken $token, Order $order ): void {
		$token_user_id  = (int) $token->get_user_id();
		$current_user   = (int) get_current_user_id();
		$order_customer = (int) $order->get_customer_id();

		$owns = $token_user_id > 0
			&& ( $token_user_id === $current_user || $token_user_id === $order_customer );

		if ( ! $owns ) {
			throw new StoreEngineException(
				esc_html__( 'The selected payment method is not available for this account.', 'storeengine' ),
				'payment-token-forbidden',
				[ 'status' => 403 ]
			);
		}
	}

	/**
	 * Native Stripe Billing checkout. Creates real Stripe subscriptions for the
	 * order's StoreEngine subscriptions, charging each first invoice off-session
	 * with the customer's saved card. One-time items (mixed cart) ride on the
	 * first subscription's first invoice.
	 *
	 * The card is collected + authenticated client-side via a SetupIntent (no
	 * up-front order charge), so the request carries a saved payment-method token
	 * by the time we get here — same acquisition as the trial SetupIntent branch.
	 *
	 * @throws StoreEngineException
	 */
	protected function process_native_subscription_payment( Order $order, $customer, OrderContext $order_context, $selected_payment_token ) {
		if ( 'new' === $selected_payment_token || ! $selected_payment_token ) {
			// add_payment_method() returns the saved token's DB id (int) — reload
			// the PaymentToken object so get_token() below returns the source id.
			[ 'token' => $token_id ] = $this->add_payment_method( Formatting::clean( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$token                   = PaymentTokens::get_token( absint( $token_id ) );
			if ( ! $token ) {
				throw new StoreEngineException( esc_html__( 'Payment token not found.', 'storeengine' ), 'payment-token-not-found', [ 'status' => 404 ] );
			}
		} else {
			$token = PaymentTokens::get_token( absint( $selected_payment_token ) );
			if ( ! $token ) {
				throw new StoreEngineException( esc_html__( 'Payment token not found.', 'storeengine' ), 'payment-token-not-found', [ 'status' => 404 ] );
			}

			$this->assert_token_ownership( $token, $order );
		}

		$payment_method = StripeService::init()->get_payment_method( $token->get_token( 'process_trial_checkout' ), false );
		$customer_id    = $payment_method->customer;
		$pm_id          = $payment_method->id;

		$this->update_new_stripe_customer( $customer, $order, $customer_id );

		$order->add_meta_data( '_stripe_payment_method', $payment_method->type, true );
		$order->add_meta_data( '_stripe_customer_id', $customer_id, true );
		$order->add_meta_data( '_stripe_source_id', $pm_id, true );
		$order->update_meta_data( '_stripe_native_billing', 'yes' );
		$order->save();

		// Keep the card on file for renewals / payment-method changes.
		$this->save_payment_method( $payment_method );

		// One-time charges billed on the first subscription's first invoice:
		// non-subscription products in a mixed cart, plus each subscription's
		// one-time setup fee (native Stripe bills the clean recurring price, so
		// the fee is not collected unless added here explicitly). Renewals never
		// reach this path — Stripe drives them directly.
		$is_renewal     = (bool) $order->get_meta( '_subscription_renewal' );
		$one_time_items = [];
		foreach ( $order->get_items() as $item ) {
			if ( ! method_exists( $item, 'get_price_type' ) ) {
				continue;
			}

			$qty = max( 1, (int) $item->get_quantity() );

			if ( 'subscription' === $item->get_price_type() ) {
				$fee_item = $is_renewal ? null : SubscriptionSync::build_setup_fee_invoice_item(
					(bool) $item->get_setup_fee(),
					(float) $item->get_setup_fee_price(),
					(string) $item->get_setup_fee_name(),
					$qty
				);
				if ( $fee_item ) {
					$one_time_items[] = $fee_item;
				}
				continue;
			}

			$one_time_items[] = [
				'name'     => $item->get_name(),
				'amount'   => round( (float) $item->get_total() / $qty, 4 ),
				'quantity' => $qty,
			];
		}

		$result = SubscriptionSync::create_for_order( $order, $customer_id, $pm_id, $one_time_items );

		if ( ! empty( $result['error'] ) ) {
			$order->update_status(
				OrderStatus::PAYMENT_FAILED,
				/* translators: %s. Error details. */
				sprintf( __( 'Stripe subscription creation failed: %s', 'storeengine' ), $result['error']->get_error_message() )
			);

			throw new StoreEngineException( esc_html( $result['error']->get_error_message() ), 'stripe-native-subscription-failed' );
		}

		// Subscriptions created and their first invoices charged by Stripe.
		$order->set_paid_status( 'paid' );
		$order_context->proceed_to_next_status( 'process_order', $order, _x( 'Paid via native Stripe subscription(s).', 'Stripe payment', 'storeengine' ) );
		$order->delete_meta_data( StripeOrder::META_STRIPE_PAYMENT_AWAITING_ACTION );
		$order->save();

		do_action( 'storeengine/api/stripe/after_native_subscription_created', $result['created'], $order );

		return [
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		];
	}

	protected function stripe_process_payment( Order $order, $intent, $order_context ) {
		try {
			// Ensure the PaymentIntent includes expanded charge and balance transaction
			if ( ! isset( $intent->latest_charge->balance_transaction ) ) {
				$intent = StripeService::init()->getClient()->paymentIntents->retrieve(
					$intent->id,
					[ 'expand' => [ 'latest_charge.balance_transaction' ] ]
				);
			}

			$charge  = $intent->latest_charge;
			$balance = $charge->balance_transaction;

			// Determine transaction outcome
			if ( $intent->status === 'succeeded' ) {
				if ( ! empty( $charge->review ) ) {
					// Payment is under manual review (Radar)
					$order->set_transaction_id( $charge->id );
					$order->set_paid_status( 'on_hold' );
					$order_context->proceed_to_next_status( 'hold_order', $order, [
						'note'           => sprintf(
						// translators: %s. Stripe ChargeId.
							__( 'Payment requires review (Charge ID: %s).', 'storeengine' ),
							$charge->id
						),
						'transaction_id' => $charge->id,
					] );
				} else {
					// Payment succeeded
					$order->set_transaction_id( $charge->id );
					$order->set_paid_status( 'paid' );
					$order_context->proceed_to_next_status( 'process_order', $order, [
						'note'           => sprintf(
							// translators: %s. Stripe ChargeId.
							__( 'Stripe charge complete (Charge ID: %s).', 'storeengine' ),
							$charge->id
						),
						'transaction_id' => $charge->id,
					] );
					$order->save();
				}
			} else {
				// Payment failed or requires action
				$message = $charge->outcome->seller_message ?? __( 'Charge not successful.', 'storeengine' );
				$order->set_paid_status( 'failed' );
				$order->set_transaction_id( $charge->id ?? null );
				$order_context->proceed_to_next_status( 'payment_failed', $order, $message );
				$order->save();

				return new WP_Error( 'charge_failed', __( 'Charge failed or requires review.', 'storeengine' ) );
			}

			// Record fees and net amount from Stripe
			$fee = $balance->fee ?? 0;
			$net = $balance->net ?? 0;

			$order->update_meta_data( StripeOrder::META_STRIPE_FEE, (float) $order->get_meta( StripeOrder::META_STRIPE_FEE ) + (float) $fee );
			$order->update_meta_data( StripeOrder::META_STRIPE_NET, (float) $order->get_meta( StripeOrder::META_STRIPE_NET ) + (float) $net );

			// Record what Stripe actually settled and flag any divergence from the
			// order total (e.g. gateway-added tax) so the invoice can be reconciled.
			if ( isset( $charge->amount ) ) {
				$captured = StripeService::get_stripe_amount_reverse( $charge->amount, $order->get_currency() );
				Helper::record_order_settlement( $order, $captured, $order->get_currency() );
			}

		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'error-processing-stripe-payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		} catch ( Exception $e ) {
			Helper::log_error( $e );
			$order->set_paid_status( 'failed' );
			// translators: %s. Payment Error.
			$order_context->proceed_to_next_status( 'payment_failed', $order, sprintf( __( 'Error while processing payment: %s', 'storeengine' ), $e->getMessage() ) );
		}

		return $intent;
	}

	public function update_stripe_payment_source( Order $order, $source ) {
		try {
			StripeService::init()->update_payment_method( $source, [
				'billing_details' => [
					'address' => [
						'city'        => $order->get_billing_city(),
						'country'     => $order->get_billing_country(),
						'line1'       => $order->get_billing_address_1(),
						'line2'       => $order->get_billing_address_2(),
						'postal_code' => $order->get_billing_postcode(),
						'state'       => $order->get_billing_state(),
					],
					'email'   => $order->get_billing_email(),
					'name'    => trim( $order->get_formatted_billing_full_name() ),
					'phone'   => $order->get_billing_phone(),
				],
			] );
		} catch ( StoreEngineException $e ) {
			Helper::log_error( $e );
		}
	}

	public function maybe_update_source_on_subscription_order( Order $order, $source, $stripe_gateway_type = '' ) {
		if ( ! Helper::get_addon_active_status( 'subscription' ) ) {
			return;
		}

		if ( SubscriptionCollection::order_contains_subscription( $order->get_id() ) ) {
			$subscriptions = SubscriptionCollection::get_subscriptions_for_order( $order->get_id() );
		} elseif ( SubscriptionCollection::order_contains_subscription( $order->get_id(), [ 'renewal' ] ) ) {
			$subscriptions = SubscriptionCollection::get_subscriptions_for_renewal_order( $order->get_id() );
		} else {
			$subscriptions = [];
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription->update_meta_data( '_stripe_customer_id', $source->customer );
			$subscription->update_meta_data( '_stripe_source_id', $source->id );

			if ( ! empty( $stripe_gateway_type ) ) {
				$subscription->update_meta_data( '_stripe_payment_method', $stripe_gateway_type );
			}

			// Update the payment method.
			$subscription->set_payment_method( $this->id );

			$subscription->save();
		}
	}

	/**
	 * Resolve a saved Stripe payment source (payment method) + customer for an
	 * order, so it can be charged off-session when the customer pays an
	 * installment / renewal order from the dashboard without re-entering a card.
	 * Prefers the order's own meta, then falls back to the subscription the
	 * order belongs to (initial or renewal).
	 *
	 * @param Order $order
	 *
	 * @return array{source_id:string, customer_id:string}
	 */
	protected function resolve_saved_stripe_source( Order $order ): array {
		$source_id   = (string) $order->get_meta( '_stripe_source_id', true, 'edit' );
		$customer_id = (string) $order->get_meta( '_stripe_customer_id', true, 'edit' );

		if ( ! $source_id && Helper::get_addon_active_status( 'subscription' ) ) {
			if ( SubscriptionCollection::order_contains_subscription( $order->get_id() ) ) {
				$subscriptions = SubscriptionCollection::get_subscriptions_for_order( $order->get_id() );
			} elseif ( SubscriptionCollection::order_contains_subscription( $order->get_id(), [ 'renewal' ] ) ) {
				$subscriptions = SubscriptionCollection::get_subscriptions_for_renewal_order( $order->get_id() );
			} else {
				$subscriptions = [];
			}

			foreach ( $subscriptions as $subscription ) {
				$sub_source = (string) $subscription->get_meta( '_stripe_source_id', true, 'edit' );
				if ( $sub_source ) {
					$source_id   = $sub_source;
					$customer_id = $customer_id ?: (string) $subscription->get_meta( '_stripe_customer_id', true, 'edit' );
					break;
				}
			}
		}

		return [
			'source_id'   => $source_id,
			'customer_id' => $customer_id,
		];
	}

	/**
	 * Process refund.
	 *
	 * @param int $order_id Order ID.
	 * @param float|string|null $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 */
	public function process_refund( int $order_id, $amount = null, string $reason = '' ) {
		$order = Helper::get_order( $order_id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! is_a( $order, Order::class ) ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'storeengine' ) );
		}

		// Refund without an amount is a no-op, but required to succeed
		if ( '0.00' === sprintf( '%0.2f', $amount ?? 0 ) ) {
			return true;
		}

		// Transaction ID should now be the Charge ID
		$charge_id = $order->get_transaction_id();

		if ( empty( $charge_id ) ) {
			return new WP_Error( 'missing_charge', __( 'Missing Stripe charge ID.', 'storeengine' ) );
		}

		try {
			$refund_result = StripeService::init()->refund( $charge_id, $amount, $order );

			$refunded = 'succeeded' === $refund_result->status;

			if ( $refunded ) {
				// Can be multiple.
				// Admin can refund multiple time.
				$order->add_meta_data( '_stripe_refund_id', $refund_result->id );

				// Balance transaction contains fee/net information
				if ( ! empty( $refund_result->balance_transaction ) ) {
					$balance = $refund_result->balance_transaction;

					// Update order fee/net metadata.
					if ( isset( $balance->fee ) ) {
						$order->update_meta_data( StripeOrder::META_STRIPE_FEE, (float) $order->get_meta( StripeOrder::META_STRIPE_FEE ) - (float) $balance->fee );
					}

					if ( isset( $balance->net ) ) {
						$order->update_meta_data( StripeOrder::META_STRIPE_NET, (float) $order->get_meta( StripeOrder::META_STRIPE_NET ) - (float) $balance->net );
					}
				}
			}

			$order->save();

			return $refunded;
		} catch ( StoreEngineException $e ) {
			Helper::log_error( $e );

			return $e->toWpError();
		} catch ( Throwable $e ) {
			Helper::log_error( $e );

			return new WP_Error(
				'stripe-refund-error',
				esc_html(
					sprintf(
					// translators: %s. Error message.
						__( 'Error while refunding payment: %s', 'storeengine' ),
						$e->getMessage()
					)
				)
			);
		}
	}

	/**
	 * @param array $payload
	 *
	 * @return array
	 * @throws StoreEngineException
	 */
	public function add_payment_method( array $payload ): array {
		if ( ! is_user_logged_in() ) {
			throw new StoreEngineException( esc_html__( 'No logged-in user found.', 'storeengine' ), 'user-must-be-logged-in' );
		}

		// Accept any of the historical key names: the React/legacy unified
		// adapter sends `stripe_payment_intent_id`; legacy `payment_intent_id`
		// is what this method's original contract used; some older subscription
		// flows also pass `setup_intent_id`. Fall back to $_POST in case the
		// payload was filtered upstream by a custom hook.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Stripe intent id echoed back on a logged-in add-payment-method call; sanitized inline and re-verified against Stripe by the gateway.
		$intent_id_raw = $payload['payment_intent_id']
			?? ( $payload['stripe_payment_intent_id']
			?? ( $payload['setup_intent_id']
			?? ( ( isset( $_POST['payment_intent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_intent_id'] ) ) : null )
			?? ( ( isset( $_POST['stripe_payment_intent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_payment_intent_id'] ) ) : null )
			?? ( isset( $_POST['setup_intent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['setup_intent_id'] ) ) : '' ) ) ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $intent_id_raw ) ) {
			// Log the keys we received so the failure is debuggable. Don't log
			// values — they may contain sensitive data.
			$payload_keys = array_keys( $payload );
			$post_keys    = array_keys( (array) $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Logging only the received key names (not values) to debug a missing Stripe intent id.
			Helper::log_error( new \Exception( sprintf(
				'Stripe setup-intent missing on add_payment_method. payload keys: %s | $_POST keys: %s',
				implode( ',', $payload_keys ),
				implode( ',', $post_keys )
			) ) );
			throw new StoreEngineException( esc_html__( 'Stripe setup intent is missing.', 'storeengine' ), 'setup-intent-missing' );
		}

		$user            = wp_get_current_user();
		$setup_intent_id = Formatting::clean( wp_unslash( $intent_id_raw ) );
		$setup_intent    = StripeService::init()->get_setup_intent( $setup_intent_id );

		if ( ! empty( $setup_intent->last_payment_error ) ) {
			throw new StoreEngineException(
				esc_html(
					sprintf(
					// translators: %1$s. Stripe intent ID. %2$s. Stripe error message.
						__( 'Error fetching the setup intent (ID %1$s) from Stripe. Error: %2$s', 'storeengine' ),
						$setup_intent_id,
						! empty( $setup_intent->last_payment_error->message ) ? $setup_intent->last_payment_error->message : __( 'Unknown Error.', 'storeengine' )
					)
				),
				'setup-intent-error'
			);
		}

		$payment_method_object = StripeService::init()->get_payment_method( $setup_intent->payment_method, false );
		$customer              = new StripeCustomer( $user->ID );
		$customer->clear_cache();

		// Check if a token with the same payment method details exist. If so, just updates the payment method ID and return.
		$found_token = StripePaymentTokens::get_duplicate_token( $payment_method_object, $user->ID, $this->id );

		// If we have a token found, update it and return.
		if ( $found_token ) {
			$token = $this->update_payment_token( $found_token, $payment_method_object->id );
		} else {
			// Create a new token if not.
			$token = $this->create_payment_token_for_user( $user->ID, $payment_method_object );
		}

		if ( ! is_a( $token, PaymentToken::class ) ) {
			throw new StoreEngineException( esc_html__( 'Invalid payment token.', 'storeengine' ), 'failed-to-save-token' );
		}

		do_action( 'storeengine/stripe/add_payment_method', $user->ID, $payment_method_object );

		return [
			'result'   => 'success',
			'redirect' => Helper::get_account_endpoint_url( 'payment-methods' ),
			'found'    => ! ! $found_token,
			'message'  => $found_token ? __( 'Duplicate payment method.', 'storeengine' ) : __( 'Payment method successfully added.', 'storeengine' ),
			'customer' => $customer->get_id(),
			'isDefault' => $token->is_default(),
			'token'    => $token->get_id(),
			'last4'    => $token->get_last4(),
			'expire'   => [
				'month' => $token->get_expiry_month(),
				'year'  => $token->get_expiry_year(),
			],
		];
	}

	/**
	 * Updates a payment token.
	 *
	 * @param PaymentToken $token The token to update.
	 * @param string $payment_method_id The new payment method ID.
	 *
	 * @return PaymentToken
	 */
	public function update_payment_token( $token, $payment_method_id ) {
		$token->set_token( $payment_method_id );
		$token->save();

		return $token;
	}

	/**
	 * Create and return a payment token for user.
	 *
	 * This will be used from the payment-tokens service
	 * as opposed to the unified payment-element gateway.
	 *
	 * @param string|int $user_id WP_User ID
	 * @param PaymentMethod $payment_method Stripe payment method object
	 *
	 * @return StripePaymentTokenCc
	 */
	public function create_payment_token_for_user( $user_id, PaymentMethod $payment_method ): StripePaymentTokenCc {
		$token = new StripePaymentTokenCc();
		$token->set_expiry_month( $payment_method->card->exp_month );
		$token->set_expiry_year( $payment_method->card->exp_year );
		$token->set_card_type( strtolower( $payment_method->card->display_brand ?? $payment_method->card->networks->preferred ?? $payment_method->card->brand ) );
		$token->set_last4( $payment_method->card->last4 );
		$token->set_gateway_id( 'stripe' );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user_id );
		$token->set_fingerprint( $payment_method->card->fingerprint );
		$token->save();

		return $token;
	}

	/**
	 * @param $payload
	 *
	 * Retrieves and returns the source_id for the given $_POST variables.
	 *
	 * @return object
	 * @throws StoreEngineException Error while attempting to retrieve the source_id.
	 */
	private function get_source_object_from_request( $payload ) {
		if ( empty( $payload['stripe_source'] ) && empty( $payload['stripe_token'] ) ) {
			throw new StoreEngineException( esc_html__( 'Missing stripe_source and stripe_token from the request.', 'storeengine' ) );
		}

		$source = $payload['stripe_source'] ?? '';

		if ( ! empty( $source ) ) {
			// This method throws a Stripe exception when there's an error. It's intended to be caught by the calling method.
			return StripeService::init()->get_payment_method( $source, false );
		}

		$stripe_token_as_source_id = isset( $payload['stripe_token'] ) ? Formatting::clean( wp_unslash( $payload['stripe_token'] ) ) : '';

		if ( ! empty( $stripe_token_as_source_id ) ) {
			// This method throws a Stripe exception when there's an error. It's intended to be caught by the calling method.
			return StripeService::init()->get_payment_method( $stripe_token_as_source_id, false );
		}

		throw new StoreEngineException( esc_html__( "The source object couldn't be retrieved.", 'storeengine' ) );
	}

	/**
	 * Get source object by source ID.
	 *
	 * @param string $source_id The source ID to get source object for.
	 *
	 * @return PaymentMethod|Source|WP_Error
	 * @throws StoreEngineException
	 */
	public function get_source_object( string $source_id = '' ) {
		return StripeService::init()->get_payment_method( $source_id, false );
	}

	/**
	 * Attaches a source to the Stripe Customer object if the source type needs manual attachment.
	 *
	 * SEPA sources need to be manually attached to the customer object as they use legacy source objects.
	 * Other reusable payment methods (eg cards), are attached to the customer object via the setup/payment intent.
	 *
	 * @param PaymentMethod|Source $source The source object to attach.
	 * @param ?StripeCustomer $customer The customer object to attach the source to. Optional.
	 *
	 * @return bool True if the source was successfully attached to the customer.
	 * @throws StoreEngineException If the source could not be attached to the customer.
	 */
	private function maybe_attach_source_to_customer( $source, ?StripeCustomer $customer = null ) {
		if ( ! isset( $source->type ) || 'sepa_debit' !== $source->type ) {
			return false;
		}

		if ( ! $customer ) {
			$customer = new StripeCustomer( get_current_user_id() );
		}

		$response = $customer->attach_source( $source->id );

		if ( is_wp_error( $response ) ) {
			throw StoreEngineException::from_wp_error( $response ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}

		return true;
	}

	/**
	 * Attaches the given payment method to the currently logged-in user.
	 *
	 * @param PaymentMethod|Source $source_object The payment method to be attached.
	 *
	 * @throws StoreEngineException
	 */
	public function save_payment_method( $source_object ) {
		$customer = new StripeCustomer( get_current_user_id() );

		if ( $customer->get_user_id() && StripeService::is_reusable_payment_method( $source_object ) ) {
			$response = $customer->add_source( $source_object->id );

			if ( is_wp_error( $response ) ) {
				throw StoreEngineException::from_wp_error( $response ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
			}
		}
	}

	/**
	 * @throws StoreEngineException
	 * @throws StoreEngineInvalidOrderStatusException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 */
	public function process_scheduled_payment( Order $renewal_order ): void {
		$this->process_subscription_payment( $renewal_order );
	}

	/**
	 * Process subscription payment.
	 *
	 * @param Order $renewal_order
	 *
	 * @return void
	 * @throws StoreEngineException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineInvalidOrderStatusException
	 */
	public function process_subscription_payment( Order $renewal_order ) {
		try {
			$order_context = new OrderContext( $renewal_order->get_status() );

			if ( $this->is_payment_needed( $renewal_order ) ) {
				$customer_id = $renewal_order->get_meta( '_stripe_customer_id' );

				// Fallback to customer account if missing from older order metadata
				if ( ! $customer_id ) {
					$customer    = $this->get_stripe_customer_from_order( $renewal_order );
					$customer_id = $customer->get_id();
				}

				$source_id = $renewal_order->get_meta( '_stripe_source_id' );
				$intent    = StripeService::init()->create_payment_intent( $renewal_order, null, $customer_id, $source_id );
				$renewal_order->update_meta_data( '_stripe_intent_id', $intent->id );
				$response = $this->stripe_process_payment( $renewal_order, $intent, $order_context );

				if ( is_wp_error( $response ) ) {
					return;
				}

				$source = StripeService::init()->get_payment_method( $response->payment_method, false );

				$renewal_order->add_meta_data( '_stripe_response_id', $response->id, true );
				$renewal_order->add_meta_data( '_stripe_currency', $response->currency, true );
				$renewal_order->add_meta_data( '_stripe_payment_method', $source->type, true );
				$renewal_order->add_meta_data( '_stripe_customer_id', $source->customer, true );
				$renewal_order->add_meta_data( '_stripe_source_id', $source->id, true );
				$renewal_order->add_meta_data( StripeOrder::META_STRIPE_CHARGE_CAPTURED, 'yes', true );
				$renewal_order->save();

				$this->save_payment_method( $source );
				$this->update_stripe_payment_source( $renewal_order, $source );
				$this->maybe_update_source_on_subscription_order( $renewal_order, $source, $source->type );
			} else {
				$renewal_order->set_paid_status( 'paid' );
				$order_context->proceed_to_next_status(
					'process_order',
					$renewal_order,
					_x( 'Payment not needed.', 'Stripe payment', 'storeengine' )
				);
				$renewal_order->delete_meta_data( StripeOrder::META_STRIPE_PAYMENT_AWAITING_ACTION );
			}

			$renewal_order->save();
		} catch ( Exception $e ) {
			Helper::log_error( $e );

			$renewal_order->update_status(
				OrderStatus::PAYMENT_FAILED,
				sprintf(
				/* translators: %s. Error details. */
					__( 'Payment failed. Error: %s', 'storeengine' ),
					$e->getMessage()
				)
			);

			throw $e;
		}
	}

	/**
	 * GatewayAdapterInterface — create a PaymentIntent for the given order
	 * and return its client_secret so the React/vanilla-JS client can confirm
	 * client-side via stripe.confirmCardPayment().
	 *
	 * Persists the intent id on the order meta so the legacy
	 * GatewayStripe::process_payment() lookup finds it later.
	 *
	 * @return array|WP_Error
	 */
	public function create_intent( Order $order, Cart $cart ) {
		try {
			$stripe_customer_id = null;
			if ( is_user_logged_in() ) {
				$customer = new StripeCustomer( get_current_user_id() );
				if ( ! $customer->get_id() ) {
					$stripe_customer_id = $customer->create_customer( [ 'order' => $order ] );
				} else {
					$stripe_customer_id = $customer->update_customer( [ 'order' => $order ] );
				}
			}

			// Allow the client to ask for a SetupIntent (trial-only subscriptions
			// where the cart total is 0 — we just need to collect a payment
			// method for future off-session charges) or a save-the-card flag
			// on the PaymentIntent.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mode        = isset( $_REQUEST['mode'] ) ? sanitize_key( wp_unslash( $_REQUEST['mode'] ) ) : 'payment';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$save_method = ! empty( $_REQUEST['save_method'] );

			// Native Stripe Billing: a subscription cart always collects the card
			// via a SetupIntent (no up-front charge) — Stripe bills the
			// subscription invoices instead.
			if ( $this->is_native_subscriptions_enabled() && $cart && $cart->get_meta( 'has_subscription' ) ) {
				$mode = 'setup';
			}

			// Non-native subscription whose first charge is $0 (free trial / full
			// discount): collect the card via a SetupIntent rather than attempting
			// a $0 PaymentIntent, which Stripe rejects.
			if ( 'setup' !== $mode && $cart && $cart->get_meta( 'has_subscription' ) && (float) $order->get_total( 'edit' ) <= 0 ) {
				$mode = 'setup';
			}

			if ( 'setup' === $mode ) {
				$return_url = ''; // Caller handles redirect via stripe.confirmSetup return_url.
				$user_id    = is_user_logged_in() ? get_current_user_id() : 0;
				$intent     = StripeService::init()->create_setup_intent( $user_id, $return_url );

				$order->add_meta_data( '_stripe_intent_id', $intent->id, true );
				$order->save();

				return [
					'payment_intent_id' => $intent->id,
					'intent_id'         => $intent->id,
					'client_secret'     => $intent->client_secret,
					'order_id'          => $order->get_id(),
					'mode'              => 'setup',
				];
			}

			$intent = StripeService::init()->create_checkout_payment_intent(
				$order,
				(float) $order->get_total(),
				$stripe_customer_id,
				$save_method
			);

			$order->add_meta_data( '_stripe_intent_id', $intent->id, true );
			$order->add_meta_data( '_stripe_currency', $intent->currency, true );
			$order->save();

			return [
				'payment_intent_id' => $intent->id,
				'intent_id'         => $intent->id,
				'client_secret'     => $intent->client_secret,
				'order_id'          => $order->get_id(),
				'mode'              => 'payment',
			];
		} catch ( Throwable $e ) {
			Helper::log_error( $e );
			return new WP_Error(
				'storeengine_stripe_create_intent_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}
}

// End of file gateway-stripe.php.
