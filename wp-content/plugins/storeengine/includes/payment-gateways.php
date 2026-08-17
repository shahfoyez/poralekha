<?php
/**
 * Payment Gateways
 *
 * @package StoreEngine/PaymentGateways
 */

namespace StoreEngine;

use StoreEngine;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Logger;
use StoreEngine\Classes\Order;
use StoreEngine\Traits\Singleton;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Payment\Gateways\GatewayCod;
use StoreEngine\Payment\Gateways\GatewayBacs;
use StoreEngine\Payment\Gateways\GatewayCheck;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\PaymentUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of available payment gateways.
 */
final class Payment_Gateways {
	use Singleton;

	/**
	 * Option storing the enabled state + sort index of every gateway, so the
	 * loader can know what is enabled without instantiating a single gateway.
	 *
	 * Shape: [ id => [ 'enabled' => bool, 'index' => int ] ]
	 */
	const MANIFEST_OPTION = 'storeengine_payment_gateways_state';

	/**
	 * Materialized gateway instances, keyed by gateway id. Populated lazily.
	 *
	 * @var PaymentGateway[]
	 */
	protected array $gateways = [];

	/**
	 * Registry of gateway id => class-string|callable factory. Built on `init`
	 * without instantiating anything.
	 *
	 * @var array<string, string|callable>
	 */
	protected array $gateway_classes = [];

	protected ?array $manifest = null;

	protected bool $loaded = false;

	protected bool $all_loaded = false;

	protected bool $enabled_loaded = false;

	protected function __construct() {
		add_action( 'init', [ $this, 'load_gateways' ] );

		add_filter( 'storeengine/api/settings', [ $this, 'add_to_settings_api' ] );
		add_filter( 'storeengine/payment_settings_fields', [ $this, 'add_to_payment_settings_fields' ] );

		add_action( 'storeengine/payment_gateways_initialized', [ __CLASS__, 'on_init_gateways' ] );
	}

	public function load_gateways() {
		if ( $this->loaded ) {
			return;
		}

		$this->loaded = true;

		/*
		 * Legacy registration: bare class-name array. Core offline gateways and
		 * the in-core addons (stripe, paypal) register this way. We can't learn a
		 * gateway's id without constructing it, so these are built eagerly — the
		 * set is small and bounded.
		 */
		$legacy = [
			GatewayCod::class,
			GatewayBacs::class,
			GatewayCheck::class,
		];

		$legacy = apply_filters( 'storeengine/payment_gateways', $legacy );

		foreach ( $legacy as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}

			$gateway = new $class();
			if ( ! is_a( $gateway, PaymentGateway::class ) ) {
				continue;
			}

			$this->gateway_classes[ $gateway->id ] = $class;
			$this->gateways[ $gateway->id ]        = $gateway;
			$this->boot_gateway( $gateway );
		}

		/*
		 * Lazy registration: id => class-string|callable factory. Gateways
		 * registered here are NOT instantiated until something actually needs
		 * them (an enabled gateway on checkout, the admin settings screen, or a
		 * by-id lookup such as a refund). A callable factory lets a plugin hand
		 * back a configured shared base class without a subclass per provider.
		 *
		 * @example
		 * ```php
		 * add_filter( 'storeengine/payment_gateway_classes', function ( array $gateways ) {
		 *     $gateways['mollie'] = fn() => new AbstractRedirectGateway( $mollie_config );
		 *     $gateways['foo']    = Foo\GatewayFoo::class;
		 *     return $gateways;
		 * } );
		 * ```
		 *
		 * @param array<string, string|callable> $registry
		 */
		$registry = apply_filters( 'storeengine/payment_gateway_classes', [] );

		if ( is_array( $registry ) ) {
			foreach ( $registry as $id => $def ) {
				if ( ! is_string( $id ) || '' === $id || isset( $this->gateway_classes[ $id ] ) ) {
					continue;
				}

				$this->gateway_classes[ $id ] = $def;
			}
		}

		/*
		 * Register the settings verify/update actions for every registered id
		 * up-front, but resolve the instance lazily inside the closure. This keeps
		 * saving a gateway's settings working without instantiating all of them.
		 */
		foreach ( array_keys( $this->gateway_classes ) as $id ) {
			add_action( "storeengine/api/settings/payment-gateways/verify/$id", function ( $payload ) use ( $id ) {
				$gateway = $this->get_gateway( $id );
				if ( $gateway && $gateway->need_config_verification() && method_exists( $gateway, 'verify_config' ) ) {
					$gateway->verify_config( $payload );
				}
			} );

			add_action( "storeengine/api/settings/payment-gateways/update/$id", function ( $payload ) use ( $id ) {
				$gateway = $this->get_gateway( $id );
				if ( ! $gateway ) {
					return;
				}
				$this->payment_gateway_settings_option_changed( $gateway, $payload, $gateway->get_settings() );
				$gateway->handle_save_request( $payload );
				$this->sync_manifest( $gateway );
			} );
		}

		/**
		 * Hook that is called when the payment gateways have been registered.
		 *
		 * Note: with lazy loading this fires after the registry is built, not
		 * after every gateway is instantiated.
		 *
		 * @param Payment_Gateways $payment_gateways The payment gateways instance.
		 */
		do_action( 'storeengine/payment_gateways_initialized', $this );

		// Materialize ENABLED gateways now (on `init`) so their `storeengine/gateway/{id}/init`
		// hooks fire early enough to register frontend assets / checkout glue. Disabled
		// gateways stay lazy — they're only built on demand (admin settings, by-id lookups).
		$this->ensure_enabled();
	}

	/**
	 * Instantiate (once) and return a single gateway by id, or null if unknown.
	 *
	 * Works for disabled gateways too, so by-id lookups (refunds, renewals,
	 * webhooks) keep resolving even when a gateway is turned off.
	 */
	protected function materialize_gateway( string $id ): ?PaymentGateway {
		$this->load_gateways();

		if ( isset( $this->gateways[ $id ] ) ) {
			return $this->gateways[ $id ];
		}

		if ( ! isset( $this->gateway_classes[ $id ] ) ) {
			return null;
		}

		$def     = $this->gateway_classes[ $id ];
		$gateway = is_string( $def ) ? new $def() : $def();

		if ( ! is_a( $gateway, PaymentGateway::class ) ) {
			return null;
		}

		$this->gateways[ $gateway->id ] = $gateway;
		$this->boot_gateway( $gateway );

		return $gateway;
	}

	/**
	 * Fire the gateway-specific init hook (wires up Hooks/Assets) for an enabled
	 * gateway, preserving the contract from the eager loader.
	 *
	 * @example
	 * ```php
	 * add_action( 'storeengine/gateway/stripe/init', function( GatewayStripe $gateway ) {} );
	 * ```
	 */
	protected function boot_gateway( PaymentGateway $gateway ): void {
		if ( has_action( "storeengine/gateway/{$gateway->id}/init" ) && $gateway->is_enabled() ) {
			do_action_ref_array( "storeengine/gateway/{$gateway->id}/init", [ &$gateway ] );
		}
	}

	/**
	 * Enabled state + sort index of every registered gateway, without
	 * instantiating any of them. Reads a single autoloaded option; if missing it
	 * is back-filled once from the per-gateway settings options (cheap reads, no
	 * instantiation) and cached.
	 */
	protected function get_manifest(): array {
		if ( null !== $this->manifest ) {
			return $this->manifest;
		}

		$manifest = get_option( self::MANIFEST_OPTION, null );

		// Use the cached manifest only if it already covers every registered id;
		// otherwise (newly installed gateway, migration) rebuild it.
		if ( is_array( $manifest ) && ! array_diff_key( $this->gateway_classes, $manifest ) ) {
			$this->manifest = $manifest;

			return $this->manifest;
		}

		// Back-fill from per-gateway option keys (no gateway is constructed).
		$manifest = is_array( $manifest ) ? $manifest : [];
		foreach ( array_keys( $this->gateway_classes ) as $id ) {
			$settings = get_option( 'storeengine_payment_' . $id . '_settings', null );
			$enabled  = is_array( $settings ) && in_array( $settings['is_enabled'] ?? false, [ true, 'true', 'yes', 'on', '1', 1 ], true );

			$manifest[ $id ] = [
				'enabled' => $enabled,
				'index'   => is_array( $settings ) ? (int) ( $settings['index'] ?? 0 ) : 0,
			];
		}

		update_option( self::MANIFEST_OPTION, $manifest, true );

		$this->manifest = $manifest;

		return $this->manifest;
	}

	/**
	 * Update a single gateway's enabled/index entry in the manifest after its
	 * settings are saved, so the next request's frontend hot path sees the change
	 * without instantiating every gateway.
	 */
	protected function sync_manifest( PaymentGateway $gateway ): void {
		$manifest = $this->get_manifest();

		$manifest[ $gateway->id ] = [
			'enabled' => $gateway->is_enabled(),
			'index'   => $gateway->get_index(),
		];

		update_option( self::MANIFEST_OPTION, $manifest, true );

		$this->manifest = $manifest;
	}

	/**
	 * Instantiate every registered gateway. Used by admin / settings contexts
	 * that must render the full list. The cost is paid only when those run.
	 */
	protected function ensure_all(): void {
		$this->load_gateways();

		if ( $this->all_loaded ) {
			return;
		}

		foreach ( array_keys( $this->gateway_classes ) as $id ) {
			$this->materialize_gateway( $id );
		}

		$this->all_loaded = true;
		$this->sort_gateways();
	}

	/**
	 * Instantiate only the gateways flagged enabled in the manifest. Used by the
	 * frontend/checkout hot path — typically 2–4 gateways instead of 20+.
	 */
	protected function ensure_enabled(): void {
		$this->load_gateways();

		if ( $this->all_loaded || $this->enabled_loaded ) {
			return;
		}

		foreach ( $this->get_manifest() as $id => $state ) {
			if ( ! empty( $state['enabled'] ) ) {
				$this->materialize_gateway( $id );
			}
		}

		$this->enabled_loaded = true;
		$this->sort_gateways();
	}

	protected function sort_gateways(): void {
		uasort( $this->gateways, fn( $a, $b ) => $a->get_index() <=> $b->get_index() );
	}

	public static function on_init_gateways() {
		add_filter( 'storeengine/frontend_scripts_data', [ __CLASS__, 'gateway_javascript_params' ] );
	}

	public static function gateway_javascript_params( array $data ): array {
		$cart                     = StoreEngine::init()->get_cart();
		$is_pay_for_order         = PaymentUtil::is_valid_order_pay_page();
		$is_change_payment_method = PaymentUtil::is_changing_payment_method_for_subscription();

		$order = null;
		if ( $is_pay_for_order ) {
			$order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );
		}

		if ( $is_pay_for_order && ! is_wp_error( $order ) && $order ) {
			$data['payment_data'] = [
				'currency'            => $order->get_currency() ? $order->get_currency() : Formatting::get_currency(),
				'cart_total'          => (float) $order->get_total( 'payment' ),
				'has_subscription'    => PaymentUtil::has_subscription( $order ),
				'has_trial'           => PaymentUtil::has_subscription_trial( $order ),
				'customerBillingData' => null,
			];
			$data['is_page']['order_id'] = (int) $order->get_id();
		} else {
			$data['payment_data'] = [
				'currency'            => Formatting::get_currency(),
				'cart_total'          => $cart ? (float) $cart->get_total( 'payment' ) : 0,
				'has_subscription'    => $cart && Helper::get_addon_active_status( 'subscription' ) && $cart->get_meta( 'has_subscription' ),
				'has_trial'           => $cart && $cart->get_meta( 'has_trial' ),
				'customerBillingData' => null,
			];
		}

		if ( Helper::is_endpoint( 'add-payment-method' ) || $is_pay_for_order || $is_change_payment_method ) {
			$customer = storeengine()->get_customer();

			if ( $customer ) {
				$data['payment_data']['customerBillingData'] = [
					'name'    => trim( $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() ),
					'email'   => $customer->get_billing_email(),
					'phone'   => $customer->get_billing_phone(),
					'address' => [
						'country'     => $customer->get_billing_country(),
						'line1'       => $customer->get_billing_address_1(),
						'line2'       => $customer->get_billing_address_2(),
						'city'        => $customer->get_billing_city(),
						'state'       => $customer->get_billing_state(),
						'postal_code' => $customer->get_billing_postcode(),
					],
				];
			}
		}

		return $data;
	}


	public function get_enabled_gateways(): array {
		$this->ensure_enabled();

		return array_filter( $this->gateways, fn( $gateway ) => $gateway->is_enabled );
	}

	public function get_available_gateways(): array {
		$this->ensure_enabled();

		return array_filter( $this->gateways, fn( $gateway ) => $gateway->is_available() );
	}

	/**
	 * @return PaymentGateway[]
	 */
	public function get_gateways(): array {
		$this->ensure_all();

		return $this->gateways;
	}

	public function get_gateway( string $id ): ?PaymentGateway {
		if ( ! $id ) {
			return null;
		}

		return $this->materialize_gateway( $id );
	}

	public function add_gateways_data( array $data ): array {
		$this->ensure_all();

		return array_merge( $data, [
			'payment_gateways' => array_map( fn( $gateway ) => [
				'id'            => $gateway->id,
				'index'         => $gateway->get_index(),
				'label'         => $gateway->get_method_title(),
				'description'   => $gateway->get_method_description(),
				'fields'        => $gateway->get_admin_fields_sorted(),
				'settings'      => $gateway->get_settings(),
				'verify_config' => $gateway->need_config_verification(),
			], array_values( $this->gateways ) ),
		] );
	}

	public function add_to_settings_api( $settings ) {
		$this->ensure_all();

		$settings->payment_gateways = array_map( fn( $gateway ) => [
			'id'            => $gateway->id,
			'index'         => $gateway->get_index(),
			'label'         => $gateway->get_method_title(),
			'description'   => $gateway->get_method_description(),
			'fields'        => $gateway->get_admin_fields_sorted(),
			'verify_config' => $gateway->need_config_verification(),
			'icon'          => $gateway->get_icon_url(),
			'settings'      => $gateway->get_settings(),
		], array_values( $this->gateways ) );

		return $settings;
	}

	public function add_to_payment_settings_fields( array $fields ): array {
		$this->ensure_all();

		$type_mapping = [
			'password'  => 'text',
			'checkbox'  => 'boolean',
			// Display-only field (read-only URL with a copy button); validate as a
			// plain string — it is never submitted by the form.
			'copy_text' => 'text',
		];

		foreach ( $this->gateways as $gateway ) {
			$fields[ $gateway->id ] = [
				'is_enabled' => 'boolean',
				'index'      => 'int',
			];
			foreach ( $gateway->get_admin_fields() as $field => $cfg ) {
				$type = $cfg['type'] ?? 'text';
				if ( 'repeater' === $type ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
					// @TODO repeater ...
				} else {
					$fields[ $gateway->id ][ $field ] = $type_mapping[ $type ] ?? $type;
				}
			}
		}

		return $fields;
	}

	/**
	 * Callback for when a gateway settings option was added or updated.
	 *
	 * @param PaymentGateway $gateway The gateway for which the option was added or updated.
	 * @param array $payload New value.
	 * @param ?array $old_settings Option name.
	 */
	private function payment_gateway_settings_option_changed( PaymentGateway $gateway, array $payload, ?array $old_settings = null ) {
		if ( ! $this->was_gateway_enabled( $payload, $old_settings ) ) {
			return;
		}

		// This is a change to a payment gateway's settings and it was just enabled. Let's send an email to the admin.
		// "untitled" shouldn't happen, but just in case.
		$this->notify_admin_payment_gateway_enabled( $gateway );
	}

	/**
	 * Email the site admin when a payment gateway has been enabled.
	 *
	 * @param PaymentGateway $gateway The gateway that was enabled.
	 *
	 * @return bool Whether the email was sent or not.
	 */
	private function notify_admin_payment_gateway_enabled( $gateway ) {
		$admin_email          = get_option( 'admin_email' );
		$user                 = get_user_by( 'email', $admin_email );
		$username             = $user ? $user->user_login : $admin_email;
		$gateway_title        = $gateway->get_method_title();
		$gateway_settings_url = esc_url_raw( self_admin_url( 'admin.php?page=storeengine-settings&path=payment-method&method=' . $gateway->id ) );
		$site_name            = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$site_url             = home_url();

		/**
		 * Allows adding to the addresses that receive payment gateway enabled notifications.
		 *
		 * @param array $email_addresses The array of email addresses to notify.
		 * @param PaymentGateway $gateway The gateway that was enabled.
		 *
		 * @return array             The augmented array of email addresses to notify.
		 */
		$email_addresses   = apply_filters( 'storeengine/payment_gateway_enabled_notification_email_addresses', [], $gateway );
		$email_addresses[] = $admin_email;
		$email_addresses   = array_unique( array_filter( $email_addresses, fn( $email_address ) => filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) );

		Logger::log( 'Payment gateway enabled', "Payment gateway {$gateway_title} enabled.", Logger::INFO, 'payment-gateway' );

		$email_text = sprintf(
		/* translators: Payment gateway enabled notification email. 1: Username, 2: Gateway Title, 3: Site URL, 4: Gateway Settings URL, 5: Admin Email, 6: Site Name, 7: Site URL. */
			__(
				'Howdy %1$s,

The payment gateway "%2$s" was just enabled on this site:
%3$s

If this was intentional you can safely ignore and delete this email.

If you did not enable this payment gateway, please log in to your site and consider disabling it here:
%4$s

This email has been sent to %5$s

Regards,
All at %6$s
%7$s',
				'storeengine'
			),
			$username,
			$gateway_title,
			$site_url,
			$gateway_settings_url,
			$admin_email,
			$site_name,
			$site_url
		);

		if ( '' !== get_option( 'blogname' ) ) {
			$site_title = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		} else {
			$site_title = wp_parse_url( home_url(), PHP_URL_HOST );
		}

		return wp_mail(
			$email_addresses,
			sprintf(
			/* translators: Payment gateway enabled notification email subject. %s1: Site title, $s2: Gateway title. */
				__( '[%1$s] Payment gateway "%2$s" enabled', 'storeengine' ),
				$site_title,
				$gateway_title
			),
			$email_text
		);
	}

	/**
	 * Determines from changes in settings if a gateway was enabled.
	 *
	 * @param array $value New value.
	 * @param array|null $old_value Old value.
	 *
	 * @return bool Whether the gateway was enabled or not.
	 */
	private function was_gateway_enabled( array $value, ?array $old_value = null ): bool {
		if ( null === $old_value ) {
			// There was no old value, so this is a new option.
			return array_key_exists( 'is_enabled', $value ) && $value['is_enabled'];
		}

		$new_val = array_key_exists( 'is_enabled', $value ) ? $value['is_enabled'] : null;
		$old_val = $old_value && array_key_exists( 'is_enabled', $old_value ) ? $old_value['is_enabled'] : null;

		// There was an old value, so this is an update.
		return $new_val && ! $old_val;
	}

	/**
	 * Get gateways.
	 *
	 * @return PaymentGateway[]
	 */
	public function payment_gateways(): array {
		$this->ensure_enabled();

		$_available_gateways = [];

		if ( count( $this->gateways ) > 0 ) {
			foreach ( $this->gateways as $gateway ) {
				$_available_gateways[ $gateway->id ] = $gateway;
			}
		}

		return $_available_gateways;
	}

	/**
	 * Get array of registered gateway ids.
	 *
	 * Returns the full registered set without instantiating any gateway.
	 *
	 * @return array of strings
	 */
	public function get_payment_gateway_ids() {
		$this->load_gateways();

		return array_keys( $this->gateway_classes );
	}

	/**
	 * Get available gateways.
	 *
	 * @return PaymentGateway[]
	 */
	public function get_available_payment_gateways(): array {
		$this->ensure_enabled();

		$_available_gateways = [];

		foreach ( $this->gateways as $gateway ) {
			if ( $gateway->is_available() ) {
				if ( ! Helper::is_add_payment_method_page() ) {
					$_available_gateways[ $gateway->id ] = $gateway;
				} elseif ( $gateway->supports( 'add_payment_method' ) || $gateway->supports( 'tokenization' ) ) {
					$_available_gateways[ $gateway->id ] = $gateway;
				}
			}
		}

		return array_filter( (array) apply_filters( 'storeengine/available_payment_gateways', $_available_gateways ), [
			$this,
			'filter_valid_gateway_class'
		] );
	}

	protected static array $one_gateway_supports = [];

	public function one_gateway_supports( string $supports_flag ) {
		// Only check if we haven't already run the check
		if ( ! isset( self::$one_gateway_supports[ $supports_flag ] ) ) {
			self::$one_gateway_supports[ $supports_flag ] = false;

			foreach ( $this->get_available_payment_gateways() as $gateway ) {
				if ( $gateway->supports( $supports_flag ) ) {
					self::$one_gateway_supports[ $supports_flag ] = true;
					break;
				}
			}
		}

		return self::$one_gateway_supports[ $supports_flag ];
	}

	/**
	 * Get payment gateway class by order data.
	 *
	 * @param Order|Subscription $order Order instance.
	 *
	 * @return PaymentGateway|bool
	 */
	public static function get_payment_gateway_by_order( $order ) {
		// Resolve by id so a now-disabled gateway still loads for refunds/renewals.
		$gateway = Helper::get_payment_gateways()->get_gateway( $order->get_payment_method() );

		return $gateway ?: false;
	}

	public function get_available_payment_gateway( string $id ): ?PaymentGateway {
		if ( ! $id ) {
			return null;
		}

		$available_gateways = $this->get_available_payment_gateways();

		return $available_gateways[ $id ] ?? null;
	}

	/**
	 * Callback for array filter. Returns true if gateway is of correct type.
	 *
	 * @param object $gateway Gateway to check.
	 *
	 * @return bool
	 */
	protected function filter_valid_gateway_class( object $gateway ): bool {
		return $gateway && is_a( $gateway, '\StoreEngine\Payment\Gateways\PaymentGateway' );
	}

	/**
	 * Set the current, active gateway.
	 *
	 * @param PaymentGateway[] $gateways  Available payment gateways.
	 * @param string           $preferred Optional gateway id to prefer (e.g. the
	 *                                     order's saved payment method on the
	 *                                     order-pay page, where there is no draft
	 *                                     cart order to read from).
	 */
	public function set_current_gateway( array $gateways, string $preferred = '' ) {
		// Be on the defensive.
		if ( empty( $gateways ) ) {
			return;
		}

		$current_gateway = false;

		// Prefer an explicitly-requested gateway when it is available (used by the
		// order-pay page to keep the order's existing payment method selected).
		if ( $preferred && isset( $gateways[ $preferred ] ) ) {
			$current_gateway = $gateways[ $preferred ];
		}

		if ( ! $current_gateway ) {
			$draft_order = Helper::get_recent_draft_order();

			if ( $draft_order ) {
				$current = $draft_order->get_payment_method( 'edit' );

				if ( $current && isset( $gateways[ $current ] ) ) {
					$current_gateway = $gateways[ $current ];
				}
			}
		}

		if ( ! $current_gateway ) {
			$current_gateway = current( $gateways );
		}

		// Ensure we can make a call to set_current() without triggering an error.
		$current_gateway->set_current();
	}
}
