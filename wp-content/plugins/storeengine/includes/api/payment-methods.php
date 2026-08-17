<?php
/**
 * Saved payment methods REST controller.
 *
 * Replaces the legacy admin-ajax handler at includes/post/saved-payment-method.php.
 * Used by the storefront "Add a payment method" form (Stripe Card Element)
 * and by the dashboard's saved-card list (delete + set-default).
 *
 * Routes:
 *   POST   /wp-json/storeengine/v1/payment-methods                  — add a payment method (gateway add_payment_method)
 *   POST   /wp-json/storeengine/v1/payment-methods/setup-intent     — create a SetupIntent (no cart/order required)
 *   DELETE /wp-json/storeengine/v1/payment-methods/<token_id>       — delete a saved token
 *   POST   /wp-json/storeengine/v1/payment-methods/<token_id>/default — mark a token as default
 *
 * Auth: standard WP REST cookie + X-WP-Nonce. Logged-in users only.
 */

namespace StoreEngine\API;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\PaymentTokens\PaymentTokens;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaymentMethods extends AbstractRestApiController {

	protected $rest_base = 'payment-methods';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_payment_method' ],
				'permission_callback' => [ $this, 'logged_in_callback' ],
				'args'                => [
					'payment_method'  => [ 'type' => 'string', 'required' => true ],
					'payment_payload' => [ 'type' => 'object', 'required' => false ],
					'fields'          => [ 'type' => 'object', 'required' => false ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/setup-intent', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_setup_intent' ],
				'permission_callback' => [ $this, 'logged_in_callback' ],
				'args'                => [
					'payment_method' => [ 'type' => 'string', 'required' => true ],
					'return_url'     => [ 'type' => 'string', 'required' => false ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<token_id>\d+)', [
			'args' => [
				'token_id' => [ 'type' => 'integer', 'required' => true ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_payment_method' ],
				'permission_callback' => [ $this, 'logged_in_callback' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<token_id>\d+)/default', [
			'args' => [
				'token_id' => [ 'type' => 'integer', 'required' => true ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_default_payment_method' ],
				'permission_callback' => [ $this, 'logged_in_callback' ],
			],
		] );
	}

	public function logged_in_callback() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'storeengine_payment_methods_login_required', __( 'You must be logged in.', 'storeengine' ), [ 'status' => 401 ] );
		}
		return true;
	}

	public function add_payment_method( WP_REST_Request $request ) {
		$payment_method = sanitize_text_field( (string) $request->get_param( 'payment_method' ) );
		$payment_data   = (array) $request->get_param( 'payment_payload' );
		$fields         = (array) $request->get_param( 'fields' );

		if ( '' === $payment_method ) {
			return new WP_Error( 'storeengine_payment_methods_required', __( 'Payment method is required.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$gateway = Helper::get_payment_gateways()->get_available_payment_gateway( $payment_method );
		if ( ! $gateway ) {
			return new WP_Error( 'storeengine_payment_methods_invalid_gateway', __( 'Invalid payment gateway.', 'storeengine' ), [ 'status' => 422 ] );
		}
		if ( ! $gateway->supports( 'add_payment_method' ) && ! $gateway->supports( 'tokenization' ) ) {
			return new WP_Error( 'storeengine_payment_methods_unsupported', __( 'Gateway does not support saved methods.', 'storeengine' ), [ 'status' => 422 ] );
		}

		// Pipe form fields + gateway payload into $_POST so legacy gateway
		// validate_fields() / add_payment_method() (written for admin-ajax)
		// keep working unmodified.
		foreach ( $fields as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$_POST[ $k ]    = $v;
				$_REQUEST[ $k ] = $v;
			}
		}
		foreach ( $payment_data as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$_POST[ $k ]    = $v;
				$_REQUEST[ $k ] = $v;
			}
		}

		try {
			$gateway->validate_fields();
			$result = $gateway->add_payment_method( Formatting::clean( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} catch ( StoreEngineException $e ) {
			return new WP_Error( $e->get_wp_error_code() ?: 'storeengine_payment_methods_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Create a gateway SetupIntent for saving a payment method WITHOUT a cart
	 * or order (the dashboard "Add payment method" form). The checkout flow's
	 * /checkout/payment-intent/{gw} endpoint requires a populated cart and a
	 * draft order; this one bypasses both.
	 *
	 * Currently only Stripe is supported (the only gateway in-tree that
	 * supports tokenization without a charge). Other gateways return 422.
	 */
	public function create_setup_intent( WP_REST_Request $request ) {
		$payment_method = sanitize_text_field( (string) $request->get_param( 'payment_method' ) );
		if ( '' === $payment_method ) {
			return new WP_Error( 'storeengine_payment_methods_required', __( 'Payment method is required.', 'storeengine' ), [ 'status' => 422 ] );
		}

		if ( 'stripe' !== $payment_method ) {
			return new WP_Error(
				'storeengine_payment_methods_setup_unsupported',
				/* translators: %s: gateway id */
				sprintf( __( 'Gateway "%s" does not support saving methods without a charge.', 'storeengine' ), $payment_method ),
				[ 'status' => 422 ]
			);
		}

		if ( ! class_exists( '\\StoreEngine\\Addons\\Stripe\\StripeService' ) ) {
			return new WP_Error( 'storeengine_payment_methods_stripe_inactive', __( 'Stripe addon is not active.', 'storeengine' ), [ 'status' => 422 ] );
		}

		try {
			$intent = \StoreEngine\Addons\Stripe\StripeService::init()->create_setup_intent(
				get_current_user_id(),
				(string) $request->get_param( 'return_url' )
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'storeengine_payment_methods_setup_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'client_secret' => $intent->client_secret,
			'intent_id'     => $intent->id,
			'mode'          => 'setup',
		] );
	}

	public function delete_payment_method( WP_REST_Request $request ) {
		$token_id = (int) $request->get_param( 'token_id' );
		$token    = PaymentTokens::get_token( $token_id );

		if ( ! $token || get_current_user_id() !== $token->get_user_id() ) {
			return new WP_Error( 'storeengine_payment_methods_invalid_token', __( 'Invalid payment method.', 'storeengine' ), [ 'status' => 404 ] );
		}

		PaymentTokens::delete( $token_id );

		return rest_ensure_response( [ 'deleted' => true, 'token_id' => $token_id ] );
	}

	public function set_default_payment_method( WP_REST_Request $request ) {
		$token_id = (int) $request->get_param( 'token_id' );
		$token    = PaymentTokens::get_token( $token_id );

		if ( ! $token || get_current_user_id() !== $token->get_user_id() ) {
			return new WP_Error( 'storeengine_payment_methods_invalid_token', __( 'Invalid payment method.', 'storeengine' ), [ 'status' => 404 ] );
		}

		PaymentTokens::set_users_default( $token->get_user_id(), $token_id );

		return rest_ensure_response( [ 'default' => true, 'token_id' => $token_id ] );
	}
}
