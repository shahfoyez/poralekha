<?php
/**
 * Gateway COD.
 */

namespace StoreEngine\Payment\Gateways;

use StoreEngine\Admin\Notices;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Shipping\Shipping;
use StoreEngine\Shipping\ShippingZone;
use StoreEngine\Shipping\ShippingZones;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\ShippingUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class GatewayCod extends PaymentGateway {

	public string $instructions;
	public bool $enable_for_virtual;

	public ?array $enable_for_methods;

	public function __construct() {
		$this->setup();

		$this->init_admin_fields();
		$this->init_settings();

		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', false );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', [] );

		add_action( 'storeengine/thankyou/' . $this->id, [ $this, 'thankyou_page' ] );
		add_action( 'storeengine/email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );

		if ( $this->is_enabled() && ! $this->enable_for_virtual && ! ShippingUtils::get_shipping_methods_count() ) {
			Notices::add_notice( 'no-shipping-zone-methods', [
				'type'        => 'warning',
				'dismissible' => false,
				'message'     => sprintf(
				// translators: %s: link to addon page.
					__( 'The <b>Cash on Delivery</b> payment method is available only if at least one <a href="%s">Shipping Method</a> is configured, or if the <code>Accept for virtual orders</code> option is enabled.', 'storeengine' ),
					esc_url( admin_url( 'admin.php?page=storeengine-settings&path=shipping' ) )
				),
			] );
		}
	}

	protected function setup() {
		$this->id                 = 'cod';
		$this->icon               = apply_filters( 'storeengine/cod_icon', Helper::get_assets_url( 'images/payment-methods/cod-alt.svg' ) );
		$this->method_title       = __( 'Cash on delivery', 'storeengine' );
		$this->method_description = __( 'Let your shoppers pay upon delivery — by cash or other methods of payment.', 'storeengine' );
		$this->has_fields         = false;
	}

	public function is_available(): bool {
		$is_virtual       = true;
		$shipping_methods = [];

		if ( Formatting::string_to_bool( get_query_var( 'order_pay' ) ) ) {
			$order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );

			if ( ! is_wp_error( $order ) ) {
				$shipping_methods = $order->get_shipping_methods();
				$is_virtual       = $order->get_auto_complete_digital_order() || ! count( $order->get_shipping_methods() );
			}
		} elseif ( \StoreEngine::init()->get_cart() && \StoreEngine::init()->get_cart()->needs_shipping() ) {
			$shipping_methods = \StoreEngine::init()->get_cart()->get_shipping_methods();
			$is_virtual       = false;
		}

		// If COD is not enabled for virtual orders and the order does not need shipping, return false.
		if ( ! $this->enable_for_virtual && $is_virtual ) {
			return false;
		}

		// Return early if:
		// - There are no shipping methods restrictions in place.
		// - The order is virtual so needs no shipping.
		// - Shipping methods are not set yet.
		if ( empty( $this->enable_for_methods ) || $is_virtual || ! $shipping_methods ) {
			return parent::is_available();
		}

		// Get the selected shipping method ids. This works on both ShippingRate and OrderItemShipping class instances.
		$canonical_rate_ids = array_unique(
			array_values(
				array_filter(
					array_map(
						function ( $shipping_method ) {
							return $shipping_method
							       && is_callable( array( $shipping_method, 'get_method_id' ) )
							       && is_callable( array( $shipping_method, 'get_instance_id' ) )
								? $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id()
								: null;
						},
						$shipping_methods
					)
				)
			)
		);

		if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  1.6.4
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return array
	 */
	private function get_matching_rates( array $rate_ids ): array {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique(
			array_merge(
				array_intersect( $this->enable_for_methods, $rate_ids ),
				array_intersect(
					$this->enable_for_methods,
					array_unique( array_map( [ Formatting::class, 'get_string_before_colon' ], $rate_ids ) )
				)
			)
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param Order $order
	 *
	 * @return array
	 * @throws StoreEngineInvalidOrderStatusException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineException
	 */

	public function process_payment( Order $order ): array {
		try {
			if ( $this->is_payment_needed( $order ) ) {
				$order->add_order_note( _x( 'Payment to be made upon delivery.', 'COD payment method', 'storeengine' ) );
				$order->set_paid_status( 'unpaid' );
			} else {
				$order->set_paid_status( 'paid' );
				$order->add_order_note( _x( 'Payment not needed.', 'COD payment method', 'storeengine' ) );
			}

			$order_context = new OrderContext( $order->get_status() );
			$order_context->proceed_to_next_status( 'processing', $order );
			$order->save();

			Helper::cart()->clear_cart();

			\StoreEngine\Classes\Logger::log( 'COD Payment Success', [ 'order_id' => $order->get_id() ], 'success', 'cod' );

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];

		} catch ( \Exception $e ) {
			\StoreEngine\Classes\Logger::log( 'COD Payment Failed', $e->getMessage(), 'error', 'cod' );
			return[
				'result'  => 'failure',
				'message' => $e->getMessage()
			];
		}
	}

	protected function init_admin_fields() {
		$this->admin_fields = [
			'title'              => [
				'label'    => __( 'Title', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'This controls the title which the user sees during checkout.', 'storeengine' ),
				'default'  => __( 'Cash on delivery', 'storeengine' ),
				'priority' => 0,
			],
			'description'        => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'Pay with cash upon delivery.', 'storeengine' ),
				'priority' => 0,
			],
			'instructions'       => [
				'label'    => __( 'Instructions', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Instructions that will be added to the thank you page and emails.', 'storeengine' ),
				'default'  => __( 'Pay with cash upon delivery.', 'storeengine' ),
				'priority' => 0,
			],
			'enable_for_methods' => [
				'label'       => __( 'Enable for shipping methods', 'storeengine' ),
				'placeholder' => __( 'Select shipping methods', 'storeengine' ),
				'type'        => 'multiselect',
				'multi'       => true,
				'default'     => [],
				'tooltip'     => __( 'If COD is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'storeengine' ),
				'options'     => $this->get_shipping_method_options(),
			],
			'enable_for_virtual' => [
				'label'   => __( 'Accept for virtual orders', 'storeengine' ),
				'tooltip' => __( 'Accept COD if the order is virtual.', 'storeengine' ),
				'type'    => 'checkbox',
				'default' => true,
			],
		];
	}

	private function get_shipping_method_options(): array {
		try {
			$raw_zones = ShippingZones::get_zones();
			$zones     = [];

			foreach ( $raw_zones as $raw_zone ) {
				$zones[] = new ShippingZone( $raw_zone['zone_id'] );
			}

			$zones[] = new ShippingZone( 0 );

			$options = [];

			foreach ( Shipping::init()->load_shipping_methods() as $method ) {

				$options[ $method->get_method_id() ] = [
					'label'   => $method->get_method_title(),
					'options' => [],
				];

				$options[ $method->get_method_id() ]['options'][] = [
					// Translators: %1$s. Shipping method name.
					'label' => sprintf( __( 'Any "%1$s" method', 'storeengine' ), $method->get_method_title() ),
					'value' => $method->get_method_id(),
				];

				foreach ( $zones as $zone ) {

					$shipping_method_instances = $zone->get_shipping_methods();

					foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

						if ( $shipping_method_instance->get_method_id() !== $method->get_method_id() ) {
							continue;
						}

						$option_instance_title = sprintf(
						// Translators: %1$s shipping method title, %2$s shipping method id.
							_x( '%1$s (#%2$s)', 'Shipping method & instance ID (Dropdown option label formatting).', 'storeengine' ),
							$shipping_method_instance->get_name(),
							$shipping_method_instance_id
						);

						$option_title = sprintf(
						// Translators: %1$s zone name, %2$s shipping method instance name.
							_x( '%1$s – %2$s', 'Shipping zone & shipping method name (Dropdown option label formatting).', 'storeengine' ),
							$zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'storeengine' ),
							$option_instance_title
						);

						$options[ $method->get_method_id() ]['options'][] = [
							'label' => $option_title,
							'value' => $shipping_method_instance->get_rate_id(),
						];
					}
				}
			}

			// React-select only allow array.
			return array_values( $options );
		} catch (\Exception $e) {}

		return [];
	}
}

// End of file gateway-cod.php.
