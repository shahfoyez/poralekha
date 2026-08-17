<?php
/**
 * Boots Stripe Automatic Tax — the only file that registers WP hooks.
 *
 * Keeps WordPress glue in one place; the rest of the namespace is plain PHP
 * that's easy to unit-test.
 */

namespace StoreEngine\Addons\Stripe\Tax;

use StoreEngine\Addons\Stripe\StripeService;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeTaxBridge {

	/**
	 * Cached calculation for the current request, keyed by cart hash.
	 *
	 * @var array|null
	 */
	private static ?array $current_calculation = null;
	private static string $current_cart_hash    = '';

	private static ?StripeTaxService $service = null;

	/**
	 * Most-recent calculation outcome, used to render the checkout status row.
	 * One of: 'idle' | 'calculated' | 'zero' | 'no_address' | 'no_client' | 'error'.
	 *
	 * @var array{ state: string, message: string, tax_amount: int, currency: string }
	 */
	private static array $last_status = [
		'state'      => 'idle',
		'message'    => '',
		'tax_amount' => 0,
		'currency'   => '',
	];

	/**
	 * Order ids we've already committed in this PHP request, to avoid races
	 * between processing -> completed status transitions in the same request.
	 *
	 * @var array<int, true>
	 */
	private static array $committed_this_request = [];

	/**
	 * Two-phase registration so we don't pay the cost of WP filter dispatch on
	 * every cart/order event when the merchant has Stripe Auto Tax turned off.
	 *
	 * Phase 1 (always-on):
	 *   - Settings allowlist — must work even when the feature is off, so the
	 *     toggle itself can be saved.
	 *   - Meta registration — lets already-stored `_stripe_tax_code` /
	 *     `_stripe_tax_status` values surface in REST regardless of toggle.
	 *
	 * Phase 2 (runtime — only when enabled):
	 *   - Cart-totals filter, order-commit hooks, refund hook, status row.
	 *     None of these run unless the merchant has flipped the toggle on.
	 */
	public static function bootstrap(): void {
		// Settings allowlist must register unconditionally so the toggle itself
		// can be saved (without it, flipping the toggle on never persists).
		add_filter( 'storeengine/ajax/settings_fields', [ self::class, 'register_settings_fields' ] );

		if ( ! StripeTaxSettings::is_enabled() ) {
			return;
		}

		// Meta registration is gated with the rest. The meta values stay in the
		// DB regardless; gating only affects whether they're REST-exposed. With
		// Stripe Tax off, exposing them serves no purpose.
		add_action( 'init', [ self::class, 'register_meta_fields' ] );

		// Proactively warn in wp-admin when Stripe Tax is on but Stripe isn't
		// connected — otherwise checkout silently collects no tax.
		if ( is_admin() ) {
			add_action( 'admin_init', [ self::class, 'maybe_warn_misconfigured' ] );
		}

		self::register_runtime_hooks();
	}

	/**
	 * Self-healing admin guardrail: while Stripe Automatic Tax is enabled but the
	 * Stripe gateway has no usable client, surface the "not connected" notice.
	 * Once Stripe is connected the one-shot flag is cleared, so a later breakage
	 * warns again. Runs only in wp-admin.
	 */
	public static function maybe_warn_misconfigured(): void {
		if ( StripeService::init()->get_client() ) {
			// Config is valid — reset so a future disconnect can warn again.
			delete_option( 'storeengine_stripe_tax_gateway_warning_seen' );

			return;
		}

		// Misconfigured — register the notice once (respects prior dismissal).
		self::warn_missing_gateway_once();
	}

	/**
	 * Register the cart/order/refund hooks. Split out so re-registration
	 * remains possible from tests, and so the bootstrap path stays readable.
	 */
	private static function register_runtime_hooks(): void {
		// Stripe Automatic Tax is an alternate tax *engine*. The local
		// `enable_product_tax` switch may be off, which would leave
		// TaxUtil::is_tax_enabled() false — and then Cart_Totals never applies the
		// item-tax filters below, so Stripe's tax never enters get_total(). Assert
		// the engine is on so the computed tax is charged, persisted and invoiced.
		add_filter( 'storeengine/tax_enabled', '__return_true' );

		add_filter( 'storeengine/calculate_item_totals_taxes', [ self::class, 'override_item_taxes' ], 10, 3 );
		add_filter( 'storeengine/cart/tax_totals', [ self::class, 'inject_tax_totals' ], 10, 2 );
		add_action( 'storeengine/cart/after_calculate_totals', [ self::class, 'persist_calculation_id_to_cart' ], 20 );
		add_action( 'storeengine/checkout/after_place_order', [ self::class, 'transfer_calculation_id_to_order' ], 20, 2 );
		add_action( 'storeengine/payment_complete', [ self::class, 'on_payment_complete' ], 20, 2 );
		// The Stripe addon transitions orders via set_status() which fires
		// `storeengine/order/status_changed` but NOT `storeengine/payment_complete`,
		// so we listen to both — `on_status_changed` is the real-world trigger.
		add_action( 'storeengine/order/status_changed', [ self::class, 'on_status_changed' ], 20, 4 );
		add_action( 'storeengine/order/refund_created', [ self::class, 'on_refund_created' ], 20, 2 );
		add_action( 'storeengine/cart/cart_totals_before_order_total', [ self::class, 'render_status_row' ] );
	}

	/**
	 * Inject a Stripe-Tax row into Cart::get_tax_totals() so the cart template
	 * actually renders something. The default code path filters out our
	 * synthetic rate_id=0 because it doesn't resolve to a DB rate.
	 */
	public static function inject_tax_totals( $tax_totals, $cart ) {
		if ( ! StripeTaxSettings::is_enabled() ) {
			return $tax_totals;
		}
		if ( ! self::$current_calculation ) {
			return $tax_totals;
		}

		$calc       = self::$current_calculation;
		$tax_amount = ( (int) ( $calc['tax_amount_exclusive'] ?? 0 ) + (int) ( $calc['tax_amount_inclusive'] ?? 0 ) ) / 100;

		if ( $tax_amount <= 0 ) {
			return $tax_totals;
		}

		// Default label; override per-jurisdiction if Stripe gives us a clear breakdown.
		$label    = __( 'Tax', 'storeengine' );
		$breakdown = $calc['tax_breakdown'] ?? [];
		if ( ! empty( $breakdown[0]['tax_rate_details']['display_name'] ) ) {
			$label = (string) $breakdown[0]['tax_rate_details']['display_name'];
		} elseif ( ! empty( $breakdown[0]['jurisdiction']['display_name'] ) ) {
			$label = (string) $breakdown[0]['jurisdiction']['display_name'];
		}

		$row                   = new \stdClass();
		$row->tax_rate_id      = 'stripe_tax';
		$row->is_compound      = false;
		$row->label            = $label;
		$row->amount           = $tax_amount;
		$row->formatted_amount = \StoreEngine\Utils\Formatting::price( $tax_amount );

		// Replace any synthetic rows the local pipeline produced so we don't double up.
		if ( is_array( $tax_totals ) ) {
			$tax_totals = array_filter( $tax_totals, static function ( $existing ) {
				return ! empty( $existing->tax_rate_id ) && 0 !== (int) $existing->tax_rate_id;
			} );
		} else {
			$tax_totals = [];
		}

		$tax_totals['stripe_tax'] = $row;

		return $tax_totals;
	}

	/**
	 * Optional service injection — used by tests.
	 *
	 * In production we lazy-build a StripeTaxService inside `service()`.
	 */
	public static function init( ?StripeTaxService $service = null ): void {
		self::$service = $service;
	}

	/**
	 * Allow our new settings keys through the base-settings save allowlist.
	 *
	 * `update_base_settings` filters the incoming payload through
	 * `populate_field_data($settings_fields, ...)`, so any key not in this
	 * array is silently dropped before save.
	 */
	public static function register_settings_fields( array $fields ): array {
		$fields['enable_stripe_tax']            = 'boolean';
		$fields['stripe_tax_default_code']      = 'string';
		$fields['stripe_tax_shipping_code']     = 'string';
		$fields['stripe_tax_fallback_to_local'] = 'boolean';

		return $fields;
	}

	/**
	 * Register product + user meta so they're REST-accessible and editable
	 * through WP-CLI / Custom Fields without further code.
	 */
	public static function register_meta_fields(): void {
		register_post_meta( 'storeengine_product', '_stripe_tax_code', [
			'type'              => 'string',
			'description'       => 'Stripe product tax code (e.g. txcd_99999999).',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => static function () {
				return current_user_can( 'edit_posts' );
			},
		] );

		register_meta( 'user', '_stripe_tax_status', [
			'type'              => 'string',
			'description'       => 'Stripe Tax customer status: taxable | exempt | reverse.',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => static function ( $value ) {
				$value = is_string( $value ) ? sanitize_key( $value ) : '';

				return in_array( $value, [ 'taxable', 'exempt', 'reverse' ], true ) ? $value : '';
			},
			'auth_callback'     => static function () {
				return current_user_can( 'edit_users' );
			},
		] );

		register_meta( 'user', '_stripe_tax_id_value', [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => static function () {
				return current_user_can( 'edit_users' );
			},
		] );

		register_meta( 'user', '_stripe_tax_id_type', [
			'type'              => 'string',
			'description'       => 'Stripe tax_id type, e.g. eu_vat, us_ein, gb_vat.',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => static function () {
				return current_user_can( 'edit_users' );
			},
		] );
	}

	/**
	 * Lazily build a StripeTaxService. Returns null if the Stripe SDK client
	 * is not available — typically because the gateway is disabled or has no
	 * API key. Callers must handle null gracefully.
	 */
	private static function service(): ?StripeTaxService {
		if ( self::$service ) {
			return self::$service;
		}

		// StripeService::init() builds an instance with whatever gateway is
		// available; get_client() returns null when keys are missing.
		$client = StripeService::init()->get_client();
		if ( ! $client ) {
			self::warn_missing_gateway_once();

			return null;
		}

		self::$service = new StripeTaxService( $client );

		return self::$service;
	}

	private static function warn_missing_gateway_once(): void {
		if ( get_option( 'storeengine_stripe_tax_gateway_warning_seen' ) ) {
			return;
		}
		update_option( 'storeengine_stripe_tax_gateway_warning_seen', 1 );

		\StoreEngine\Admin\Notices::add_notice( 'storeengine_stripe_tax_gateway_warning', [
			'type'        => 'warning',
			'title'       => __( 'Stripe Automatic Tax is on but Stripe is not connected.', 'storeengine' ),
			'message'     => __( 'Enable the Stripe payment method and provide your API keys under Settings → Payment Methods. Until then, no tax will be calculated at checkout.', 'storeengine' ),
			'dismissible' => true,
		] );
	}

	/**
	 * Filter callback for `storeengine/calculate_item_totals_taxes`.
	 *
	 * Replaces local per-line tax with Stripe's calculated amount. Lazy-primes
	 * the Stripe calculation on the first item the filter sees in this request.
	 *
	 * @param array  $total_taxes  Local-engine result: [rate_id => amount_in_precision_units]
	 * @param object $item         Cart-totals item with `key`, `price`, `total`, `tax_rates`.
	 * @param object $cart_totals  CartTotals instance (`->cart` is the Cart).
	 *
	 * @return array
	 */
	public static function override_item_taxes( $total_taxes, $item, $cart_totals ): array {
		if ( ! StripeTaxSettings::is_enabled() ) {
			return $total_taxes;
		}

		$cart = self::resolve_cart( $cart_totals );
		if ( ! $cart instanceof Cart ) {
			return $total_taxes;
		}

		$calc = self::ensure_calculated( $cart );
		if ( ! $calc ) {
			return $total_taxes;
		}

		$key      = (string) ( $item->key ?? '' );
		$line_tax = self::lookup_line_tax( $calc, $key );
		$rate_id  = ! empty( $total_taxes ) ? (int) array_keys( $total_taxes )[0] : 0;

		// Stripe `amount_tax` is in the currency's smallest unit (cents).
		// StoreEngine's cart-totals also operates in precision-multiplied integers
		// (which equals cents for 2-decimal currencies). v1 assumes parity.
		return [ $rate_id => $line_tax ];
	}

	private static function lookup_line_tax( array $calc, string $reference ): int {
		foreach ( $calc['line_items'] as $line ) {
			if ( ( $line['reference'] ?? '' ) === $reference ) {
				return (int) ( $line['amount_tax'] ?? 0 );
			}
		}

		return 0;
	}

	/**
	 * Read address values currently in $_POST (checkout form) when the
	 * Customer object hasn't been populated yet. Prefers shipping fields,
	 * falls back to billing.
	 */
	private static function merge_post_address( array $details ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$pick = static function ( string $ship_key, string $bill_key ): string {
			$ship_val = isset( $_POST[ $ship_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $ship_key ] ) ) : '';
			if ( '' !== $ship_val ) {
				return $ship_val;
			}

			return isset( $_POST[ $bill_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $bill_key ] ) ) : '';
		};

		$country  = $pick( 'shipping_country', 'billing_country' );
		$state    = $pick( 'shipping_state', 'billing_state' );
		$city     = $pick( 'shipping_city', 'billing_city' );
		$postcode = $pick( 'shipping_postcode', 'billing_postcode' );
		$line1    = $pick( 'shipping_address_1', 'billing_address_1' );
		$line2    = $pick( 'shipping_address_2', 'billing_address_2' );
		// phpcs:enable

		if ( ! $country && empty( $details['address']['country'] ) ) {
			return $details;
		}

		$details['address'] = array_merge(
			$details['address'] ?? [],
			array_filter( [
				'line1'       => $line1,
				'line2'       => $line2,
				'city'        => $city,
				'state'       => $state,
				'postal_code' => $postcode,
				'country'     => $country,
			], static fn( $v ) => '' !== $v )
		);

		return $details;
	}

	/**
	 * Resolve the active Cart for this calculation.
	 *
	 * Priority order:
	 *   1. CartTotals::$cart — the cart this calc is operating on (read via
	 *      reflection because it's protected).
	 *   2. Helper::cart() — the global singleton; may be null in some AJAX paths.
	 */
	private static function resolve_cart( $cart_totals ): ?Cart {
		if ( is_object( $cart_totals ) ) {
			try {
				$ref = new \ReflectionObject( $cart_totals );
				if ( $ref->hasProperty( 'cart' ) ) {
					$prop = $ref->getProperty( 'cart' );
					$prop->setAccessible( true );
					$value = $prop->getValue( $cart_totals );
					if ( $value instanceof Cart ) {
						return $value;
					}
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Reflection failed (e.g. non-object payload); fall through.
			}
		}

		$fallback = Helper::cart();

		return $fallback instanceof Cart ? $fallback : null;
	}

	private static function ensure_calculated( Cart $cart ): ?array {
		$cart_hash = method_exists( $cart, 'get_cart_hash' ) ? (string) $cart->get_cart_hash() : '';
		if ( ! $cart_hash ) {
			return null;
		}

		if ( $cart_hash === self::$current_cart_hash && self::$current_calculation ) {
			return self::$current_calculation;
		}

		$cached = CalculationCache::get( $cart_hash );
		if ( ! $cached ) {
			$cached = self::recalculate( $cart );
			if ( is_wp_error( $cached ) ) {
				self::record_status_from_error( $cached );
				self::handle_failure( $cached );

				return null;
			}
			CalculationCache::put( $cart_hash, $cached );
		}

		self::$current_cart_hash   = $cart_hash;
		self::$current_calculation = $cached;
		self::record_status_from_calc( $cached );

		return $cached;
	}

	/**
	 * Stash the cart's last-used calculation id so we can carry it to the order.
	 */
	public static function persist_calculation_id_to_cart( $cart ): void {
		if ( ! $cart instanceof Cart || ! self::$current_calculation ) {
			return;
		}
		if ( method_exists( $cart, 'set_meta' ) ) {
			$cart->set_meta( 'stripe_tax_calculation_id', self::$current_calculation['id'] );
		}
	}

	/**
	 * On order placement, copy cart's calculation id to order meta so the
	 * payment_complete handler can commit it.
	 */
	public static function transfer_calculation_id_to_order( $order, $payload = null ): void {
		if ( ! StripeTaxSettings::is_enabled() || ! $order instanceof Order ) {
			return;
		}

		// Best case: a cart calc already ran this request and set $current_calculation.
		$calc_id = self::$current_calculation['id'] ?? '';

		// Fallback 1: the cart's hash hits a cached calculation from an earlier request.
		if ( ! $calc_id ) {
			$cart = Helper::cart();
			if ( $cart instanceof Cart ) {
				$cart_hash = method_exists( $cart, 'get_cart_hash' ) ? (string) $cart->get_cart_hash() : '';
				if ( $cart_hash ) {
					$cached = CalculationCache::get( $cart_hash );
					if ( $cached ) {
						$calc_id = (string) ( $cached['id'] ?? '' );
					}
				}
			}
		}

		// Fallback 2: force a fresh calculation right now using the order's address.
		// The order has the customer details by the time `after_place_order` fires.
		if ( ! $calc_id && isset( $cart ) && $cart instanceof Cart ) {
			$calc = self::recalculate( $cart );
			if ( ! is_wp_error( $calc ) ) {
				$calc_id                   = (string) ( $calc['id'] ?? '' );
				self::$current_calculation = $calc;
			}
		}

		if ( ! $calc_id ) {
			return;
		}

		$order->update_meta_data( '_stripe_tax_calculation_id', $calc_id );
		$order->save();
	}

	private static function recalculate( Cart $cart ) {
		$service = self::service();
		if ( ! $service ) {
			return new WP_Error( 'storeengine_stripe_tax_no_client', 'Stripe payment gateway is not configured.' );
		}

		$line_items = LineItemMapper::from_cart( $cart );
		if ( empty( $line_items ) ) {
			return new WP_Error( 'storeengine_stripe_tax_empty_cart', 'cart has no line items' );
		}

		// Prefer the cart's customer (kept in sync by checkout AJAX), fall back to
		// the global helper. Either may be empty for a brand-new guest cart.
		$customer = method_exists( $cart, 'get_customer' ) ? $cart->get_customer() : null;
		if ( ! $customer ) {
			$customer = Helper::get_customer();
		}

		$customer_details = AddressMapper::from_customer( $customer, null );

		// Final fallback: the values currently being typed into the checkout
		// form. They reach us via $_POST during the update_order_review AJAX
		// before they're committed back to the Customer object.
		if ( empty( $customer_details['address']['country'] ) ) {
			$customer_details = self::merge_post_address( $customer_details );
		}

		if ( empty( $customer_details['address']['country'] ) ) {
			return new WP_Error( 'storeengine_stripe_tax_no_address', 'no customer address yet' );
		}

		$payload = [
			'currency'         => strtolower( (string) Formatting::get_currency() ),
			'line_items'       => $line_items,
			'customer_details' => $customer_details,
			'expand'           => [ 'line_items.data.tax_breakdown' ],
		];

		$shipping = LineItemMapper::shipping_cost( $cart );
		if ( $shipping ) {
			$payload['shipping_cost'] = $shipping;
		}

		$result = $service->calculate( $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( 0 === (int) $result['tax_amount_exclusive'] && 0 === (int) $result['tax_amount_inclusive'] ) {
			self::flag_zero_tax_once();
		}

		return $result;
	}

	private static function record_status_from_calc( array $calc ): void {
		$tax = (int) ( $calc['tax_amount_exclusive'] ?? 0 ) + (int) ( $calc['tax_amount_inclusive'] ?? 0 );

		self::$last_status = [
			'state'      => $tax > 0 ? 'calculated' : 'zero',
			'message'    => $tax > 0
				? __( 'Stripe Automatic Tax calculated successfully.', 'storeengine' )
				: __( 'Stripe returned zero tax for this address — likely no tax registration matches this jurisdiction.', 'storeengine' ),
			'tax_amount' => $tax,
			'currency'   => strtoupper( (string) ( $calc['currency'] ?? Formatting::get_currency() ) ),
		];
	}

	private static function record_status_from_error( WP_Error $error ): void {
		$code = (string) $error->get_error_code();
		$map  = [
			'storeengine_stripe_tax_no_client'      => [ 'no_client',  __( 'Stripe payment method is not connected.', 'storeengine' ) ],
			'storeengine_stripe_tax_no_address'     => [ 'no_address', __( 'Waiting for customer country/address.', 'storeengine' ) ],
			'storeengine_stripe_tax_empty_cart'     => [ 'idle',       __( 'Cart has no line items.', 'storeengine' ) ],
			'storeengine_stripe_tax_calc_failed'    => [ 'error',      $error->get_error_message() ],
		];
		$entry = $map[ $code ] ?? [ 'error', $error->get_error_message() ];

		self::$last_status = [
			'state'      => $entry[0],
			'message'    => $entry[1],
			'tax_amount' => 0,
			'currency'   => strtoupper( (string) Formatting::get_currency() ),
		];
	}

	/**
	 * Render a "Stripe Automatic Tax: …" row inside the cart totals table.
	 *
	 * Always shown to admins (so you can confirm the integration is firing
	 * even when Stripe returns zero). Hidden from shoppers by default — flip
	 * `apply_filters( 'storeengine/stripe_tax/show_status_to_shoppers', false )`
	 * to expose it on the customer-facing checkout for debugging.
	 */
	public static function render_status_row(): void {
		if ( ! StripeTaxSettings::is_enabled() ) {
			return;
		}

		$show = current_user_can( 'manage_options' );
		if ( ! $show ) {
			$show = (bool) apply_filters( 'storeengine/stripe_tax/show_status_to_shoppers', false );
		}
		if ( ! $show ) {
			return;
		}

		$status   = self::$last_status;
		$state    = $status['state'];
		$amount   = (float) $status['tax_amount'] / 100;
		$currency = $status['currency'];
		$icon     = [
			'calculated' => '✓',
			'zero'       => '⚠',
			'no_address' => '⏳',
			'no_client'  => '⚠',
			'error'      => '✗',
			'idle'       => '⏳',
		][ $state ] ?? '•';

		$amount_html = sprintf(
			'%s&nbsp;%s',
			esc_html( $currency ),
			esc_html( number_format_i18n( $amount, 2 ) )
		);

		echo '<tr class="storeengine-stripe-tax-status storeengine-stripe-tax-status--' . esc_attr( $state ) . '">';
		echo '<th scope="row">' . esc_html__( 'Stripe Automatic Tax', 'storeengine' );
		echo '<small style="display:block;font-weight:normal;opacity:0.75;margin-top:2px;">'
			. esc_html( $icon . ' ' . $status['message'] )
			. '</small>';
		echo '</th>';
		echo '<td data-title="' . esc_attr__( 'Stripe Automatic Tax', 'storeengine' ) . '">'
			. wp_kses_post( $amount_html )
			. '</td>';
		echo '</tr>';
	}

	private static function handle_failure( WP_Error $error ): void {
		if ( ! StripeTaxSettings::get( 'fallback_to_local', false ) ) {
			// Surface to checkout so the customer can't proceed silently.
			do_action( 'storeengine/stripe_tax/calculation_failed', $error );
		}
	}

	/**
	 * After payment is marked complete, commit the calculation to record a transaction.
	 *
	 * Fires on `storeengine/payment_complete` with ($order_id, $transaction_id).
	 */
	/**
	 * Backup commit trigger.
	 *
	 * Many StoreEngine gateways move orders via `$order->set_status()` rather
	 * than `$order->payment_complete()`, so the dedicated payment_complete
	 * action never fires. We listen to status_changed and commit when the
	 * order reaches a paid status. The handler is idempotent — guard inside
	 * `on_payment_complete()` skips orders that already have a tx id.
	 *
	 * Fires on `storeengine/order/status_changed` with ($order_id, $old, $new, $order).
	 */
	public static function on_status_changed( $order_id, $old_status, $new_status, $order = null ): void {
		$paid_statuses = apply_filters(
			'storeengine/stripe_tax/paid_statuses',
			[ 'processing', 'completed' ]
		);

		// Only fire when the order is transitioning INTO a paid status from a
		// non-paid one. Moving between paid statuses (processing -> completed)
		// is the same payment, not a new one — we already committed.
		$entering_paid = in_array( (string) $new_status, $paid_statuses, true )
			&& ! in_array( (string) $old_status, $paid_statuses, true );

		if ( ! $entering_paid ) {
			return;
		}

		self::on_payment_complete( (int) $order_id );
	}

	public static function on_payment_complete( $order_id, $transaction_id = '' ): void {
		if ( ! StripeTaxSettings::is_enabled() ) {
			return;
		}

		$order_id = (int) $order_id;

		// Per-request guard. Prevents double-commits when the same request
		// fires both `payment_complete` and `status_changed`, or when a stale
		// in-memory order object hides the transaction-id meta we just saved.
		if ( isset( self::$committed_this_request[ $order_id ] ) ) {
			return;
		}

		$order = Helper::get_order( $order_id );
		if ( ! $order instanceof Order ) {
			return;
		}

		// Persistent guard — already committed in a previous request.
		if ( (string) $order->get_meta( '_stripe_tax_transaction_id' ) ) {
			self::$committed_this_request[ $order_id ] = true;
			return;
		}

		$calc_id = (string) $order->get_meta( '_stripe_tax_calculation_id' );

		// Safety net: if the calc id never landed on the order, try one more
		// time before giving up. Stripe calcs expire after ~48h, so we always
		// recompute fresh against the order's own address rather than reusing
		// a stale cached id.
		if ( ! $calc_id ) {
			$calc_id = self::create_calc_from_order( $order );
			if ( $calc_id ) {
				$order->update_meta_data( '_stripe_tax_calculation_id', $calc_id );
				$order->save();
			}
		}

		if ( ! $calc_id ) {
			return;
		}

		$service = self::service();
		if ( ! $service ) {
			return;
		}

		$tx_id = $service->commit( $calc_id, (string) $order->get_id() );
		if ( is_wp_error( $tx_id ) ) {
			do_action( 'storeengine/stripe_tax/commit_failed', $order, $tx_id );

			return;
		}

		$order->update_meta_data( '_stripe_tax_transaction_id', $tx_id );
		$order->save();
		self::$committed_this_request[ $order_id ] = true;
	}

	/**
	 * Build a fresh tax calculation directly from a placed order's data.
	 * Used as a last-chance fallback when the cart-time calc didn't land.
	 */
	private static function create_calc_from_order( Order $order ): string {
		$service = self::service();
		if ( ! $service ) {
			return '';
		}

		$line_items = [];
		$default    = (string) StripeTaxSettings::get( 'default_tax_code', 'txcd_99999999' );
		$behavior   = ( method_exists( $order, 'get_prices_include_tax' ) && $order->get_prices_include_tax() )
			? 'inclusive'
			: 'exclusive';

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! method_exists( $item, 'get_total' ) ) {
				continue;
			}
			$amount = (int) round( (float) $item->get_total() * 100 );
			if ( $amount <= 0 ) {
				continue;
			}

			$tax_code = $default;
			$pid      = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
			if ( $pid ) {
				$override = (string) get_post_meta( $pid, '_stripe_tax_code', true );
				if ( $override ) {
					$tax_code = $override;
				}
			}

			$line_items[] = [
				'amount'       => $amount,
				'reference'    => 'order_item_' . $item_id,
				'tax_code'     => $tax_code,
				'quantity'     => method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1,
				'tax_behavior' => $behavior,
			];
		}

		if ( empty( $line_items ) ) {
			return '';
		}

		$details = AddressMapper::from_customer( null, $order );
		if ( empty( $details['address']['country'] ) ) {
			return '';
		}

		$payload = [
			'currency'         => strtolower( (string) $order->get_currency() ),
			'line_items'       => $line_items,
			'customer_details' => $details,
			'expand'           => [ 'line_items.data.tax_breakdown' ],
		];

		$shipping_total = method_exists( $order, 'get_shipping_total' ) ? (float) $order->get_shipping_total() : 0.0;
		if ( $shipping_total > 0 ) {
			$payload['shipping_cost'] = [
				'amount'       => (int) round( $shipping_total * 100 ),
				'tax_code'     => (string) StripeTaxSettings::get( 'shipping_tax_code', 'txcd_92010001' ),
				'tax_behavior' => $behavior,
			];
		}

		$result = $service->calculate( $payload );
		if ( is_wp_error( $result ) ) {
			return '';
		}

		return (string) ( $result['id'] ?? '' );
	}

	/**
	 * Reverse the Stripe Tax transaction when a refund is created.
	 *
	 * Fires on `storeengine/order/refund_created` with ($refund, $args).
	 *
	 * @param object $refund
	 * @param array  $args   Refund args (`order_id`, `amount`, `reason`, etc.)
	 */
	public static function on_refund_created( $refund, $args = [] ): void {
		if ( ! StripeTaxSettings::is_enabled() ) {
			return;
		}

		$order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
		if ( ! $order_id && method_exists( $refund, 'get_parent_id' ) ) {
			$order_id = (int) $refund->get_parent_id();
		}

		$order = $order_id ? Helper::get_order( $order_id ) : null;
		if ( ! $order instanceof Order ) {
			return;
		}

		$tx_id = (string) $order->get_meta( '_stripe_tax_transaction_id' );
		if ( ! $tx_id ) {
			return;
		}

		$mode  = self::detect_refund_mode( $order, $refund );
		$ref   = 'refund_' . ( method_exists( $refund, 'get_id' ) ? $refund->get_id() : uniqid() );

		$params = [
			'reference'          => $ref,
			'mode'               => $mode,
			'original_transaction' => $tx_id,
		];

		if ( 'partial' === $mode ) {
			$params['shipping_cost'] = self::partial_shipping( $refund );
			$params['line_items']    = self::partial_line_items( $refund );
		}

		$service = self::service();
		if ( ! $service ) {
			return;
		}

		$reversal_id = $service->reverse( $params );
		if ( is_wp_error( $reversal_id ) ) {
			do_action( 'storeengine/stripe_tax/reversal_failed', $refund, $reversal_id );

			return;
		}

		if ( method_exists( $refund, 'update_meta_data' ) ) {
			$refund->update_meta_data( '_stripe_tax_reversal_id', $reversal_id );
			if ( method_exists( $refund, 'save' ) ) {
				$refund->save();
			}
		}
	}

	private static function detect_refund_mode( Order $order, $refund ): string {
		$order_total  = (float) $order->get_total();
		$refund_total = method_exists( $refund, 'get_amount' ) ? (float) $refund->get_amount() : 0.0;

		return $refund_total >= $order_total ? 'full' : 'partial';
	}

	private static function partial_line_items( $refund ): array {
		$items = [];
		if ( ! method_exists( $refund, 'get_items' ) ) {
			return $items;
		}
		foreach ( $refund->get_items() as $key => $item ) {
			$items[] = [
				'reference'         => (string) $key,
				'amount'            => method_exists( $item, 'get_total' ) ? (int) round( $item->get_total() * 100 ) : 0,
				'amount_tax'        => method_exists( $item, 'get_total_tax' ) ? (int) round( $item->get_total_tax() * 100 ) : 0,
			];
		}

		return $items;
	}

	private static function partial_shipping( $refund ): array {
		if ( method_exists( $refund, 'get_shipping_total' ) && (float) $refund->get_shipping_total() > 0 ) {
			return [
				'amount'     => (int) round( $refund->get_shipping_total() * 100 ),
				'amount_tax' => method_exists( $refund, 'get_shipping_tax' ) ? (int) round( $refund->get_shipping_tax() * 100 ) : 0,
			];
		}

		return [];
	}

	private static function flag_zero_tax_once(): void {
		if ( get_option( 'storeengine_stripe_tax_zero_warning_seen' ) ) {
			return;
		}
		update_option( 'storeengine_stripe_tax_zero_warning_seen', 1 );

		\StoreEngine\Admin\Notices::add_notice( 'storeengine_stripe_tax_zero_warning', [
			'type'        => 'warning',
			'title'       => __( 'Stripe Automatic Tax returned zero tax', 'storeengine' ),
			'message'     => __( 'No tax was calculated on the latest cart. This usually means you have no active tax registrations in your Stripe Dashboard. Add a registration at https://dashboard.stripe.com/settings/tax to start collecting tax.', 'storeengine' ),
			'dismissible' => true,
		] );
	}

}
