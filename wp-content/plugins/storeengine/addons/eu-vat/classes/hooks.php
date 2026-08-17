<?php
/**
 * EU VAT Hooks
 *
 * All integration with StoreEngine core happens here. Core never calls this
 * addon directly — it only fires hooks.
 *
 * Why is_vat_exempt is set on the cart customer (not the order directly)
 * ────────────────────────────────────────────────────────────────────────
 * The Customer object on the cart drives Tax::calc_tax() through the
 * storeengine/tax/calculator filter. When we set $customer->set_is_vat_exempt(true)
 * inside the AJAX validate handler, every subsequent cart total recalculation
 * applied during the request — and the next request, since the customer is
 * persisted to session — will see zero rates.
 *
 * The order's is_vat_exempt meta is written separately (see Ajax::apply_to_draft_order
 * and the after_place_order hook) so the persisted order keeps the exempt flag
 * even after the session goes away.
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat\Classes;

use StoreEngine\Utils\Helper;

use function StoreEngine\Addons\EuVat\normalize_vat_input;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	public function __construct() {

		// ── Render VAT field on the checkout billing address ──────────────
		add_action( 'storeengine/checkout/billing_address/after', [ $this, 'render_checkout_field' ] );

		// ── Capture submitted VAT on update_checkout (data filter) ────────
		add_filter( 'storeengine/frontend/checkout/before_update_draft_order', [ $this, 'capture_vat_on_update' ] );

		// ── Block place_order when field is required and missing/invalid ──
		add_action( 'storeengine/frontend/checkout/before_place_order', [ $this, 'guard_required_field' ] );

		// ── Persist final VAT meta to placed order ────────────────────────
		add_action( 'storeengine/checkout/after_place_order', [ $this, 'persist_order_meta' ], 10, 2 );

		// ── Zero tax rates when customer is VAT-exempt ────────────────────
		add_filter( 'storeengine/tax/calculator', [ $this, 'zero_rates_when_exempt' ], 10, 4 );

		// ── Sync user meta after order placed (for My Account view) ───────
		add_action( 'storeengine/checkout/after_place_order', [ $this, 'sync_user_meta' ], 20, 2 );

		// ── My Account: render VAT row under the billing address card ─────
		add_action( 'storeengine/dashboard/after-edit-address', [ $this, 'render_account_vat' ] );

		// ── My Account: editable VAT field inside the edit-address form ───
		add_action( 'storeengine/dashboard/edit-address/after-fields', [ $this, 'render_account_vat_field' ] );

		// ── Persist the editable VAT field on address save (raw $_POST) ───
		// Priority 1 so it runs before core's change_address handler, which
		// redirects + dies at the end of the same admin-post action.
		add_action( 'admin_post_storeengine/frontend_dashboard_edit_address', [ $this, 'save_account_vat_field' ], 1 );

		// ── Show VAT under billing address on order-detail screens ────────
		add_action( 'storeengine/order/billing_address/after', [ $this, 'render_order_vat_line' ] );
	}

	// ──────────────────────────────────────────────────────────────────────
	// Checkout
	// ──────────────────────────────────────────────────────────────────────

	public function render_checkout_field( $customer ): void {
		$stored = '';
		$order  = Helper::get_recent_draft_order();
		if ( $order ) {
			$stored = (string) $order->get_meta( '_billing_eu_vat_number' );
		}
		if ( '' === $stored && $customer && method_exists( $customer, 'get_id' ) && $customer->get_id() ) {
			$stored = (string) get_user_meta( $customer->get_id(), 'billing_eu_vat_number', true );
		}

		include STOREENGINE_EU_VAT_DIR . 'templates/checkout-field.php';
	}

	/**
	 * The AJAX schema for update_checkout strips unknown fields, so we read
	 * directly from $_POST here. Nonce verification is handled by the parent
	 * AbstractAjaxHandler before this filter ever fires.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 */
	public function capture_vat_on_update( $data ) {
		$raw = isset( $_POST['billing_eu_vat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_eu_vat_number'] ) ) : null;

		if ( null === $raw ) {
			return $data;
		}

		$order = Helper::get_recent_draft_order();
		if ( ! $order ) {
			return $data;
		}

		// Field cleared — drop everything. Otherwise leave it alone: the frontend
		// JS calls Ajax::validate separately, which writes the validated meta and
		// flips the customer's exempt flag. update_checkout shouldn't second-guess
		// that or it'll race with a still-in-flight validation.
		if ( '' === trim( $raw ) ) {
			$order->update_meta_data( '_billing_eu_vat_number', '' );
			$order->update_meta_data( 'is_vat_exempt', 'no' );
			$order->save();

			$cart = \StoreEngine::init()->get_cart();
			$customer = $cart ? $cart->get_customer() : null;
			if ( $customer ) {
				$customer->set_is_vat_exempt( false );
			}
		}

		return $data;
	}

	public function guard_required_field( $order ): void {
		$mode = (string) Settings::get( 'field_required', 'optional' );
		if ( 'optional' === $mode ) {
			return;
		}

		$company  = (string) $order->get_billing_company();
		$vat      = (string) $order->get_meta( '_billing_eu_vat_number' );
		$exempt   = 'yes' === (string) $order->get_meta( 'is_vat_exempt' );

		$require_now = false;
		if ( 'required' === $mode ) {
			$require_now = true;
		} elseif ( 'required_if_company' === $mode && '' !== $company ) {
			$require_now = true;
		}

		if ( ! $require_now ) {
			return;
		}

		if ( '' === $vat || ! $exempt ) {
			$messages = (array) Settings::get( 'messages', [] );
			$message  = $exempt ? '' : ( $messages['invalid'] ?? __( 'A valid VAT number is required.', 'storeengine' ) );
			if ( '' === $vat ) {
				$message = __( 'A VAT number is required.', 'storeengine' );
			}
			wp_send_json_error( [ 'message' => $message ] );
		}
	}

	public function persist_order_meta( $order, $payload ): void {
		// Draft → final order is the same record, so the meta we wrote during
		// validation is already there. We only need to handle the case where
		// the JS skipped validation and dropped the value into POST directly.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$existing = (string) $order->get_meta( '_billing_eu_vat_number' );
		if ( '' === $existing && ! empty( $_POST['billing_eu_vat_number'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST['billing_eu_vat_number'] ) );
			$order->update_meta_data( '_billing_eu_vat_number', $raw );
			$order->save();
		}
	}

	// ──────────────────────────────────────────────────────────────────────
	// Tax
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Zero out tax rates when the active customer is flagged as VAT-exempt.
	 * Returning a non-null array short-circuits Tax::calc_tax() with that
	 * array — see includes/classes/tax.php:61.
	 *
	 * @param mixed $override
	 * @param float $price
	 * @param array $rates
	 * @param bool  $price_includes_tax
	 */
	public function zero_rates_when_exempt( $override, $price, $rates, $price_includes_tax ) {
		if ( null !== $override ) {
			return $override;
		}

		// This filter fires during Cart::load_cart(), before StoreEngine->initialize_cart()
		// has stored the cart on the singleton. get_cart() returns null in that window.
		$cart = \StoreEngine::init()->get_cart();
		if ( ! $cart ) {
			return $override;
		}

		$customer = $cart->get_customer();
		if ( ! $customer || ! $customer->is_vat_exempt() ) {
			return $override;
		}

		$zeroed = [];
		foreach ( $rates as $key => $rate ) {
			$zeroed[ $key ] = 0.0;
		}
		return $zeroed;
	}

	// ──────────────────────────────────────────────────────────────────────
	// Customer / My Account
	// ──────────────────────────────────────────────────────────────────────

	public function sync_user_meta( $order, $payload ): void {
		$user_id = (int) $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$vat = (string) $order->get_meta( '_billing_eu_vat_number' );
		if ( '' !== $vat ) {
			update_user_meta( $user_id, 'billing_eu_vat_number', $vat );
		}
	}

	public function render_account_vat( $addr_type = '' ): void {
		if ( 'billing' !== $addr_type ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$vat = (string) get_user_meta( $user_id, 'billing_eu_vat_number', true );
		include STOREENGINE_EU_VAT_DIR . 'templates/account-vat.php';
	}

	/**
	 * Editable VAT input rendered inside the My Account edit-address form
	 * (billing only). Saved by save_account_vat_field().
	 *
	 * @param string $addr_type Address type being edited (billing|shipping).
	 */
	public function render_account_vat_field( $addr_type = '' ): void {
		if ( 'billing' !== $addr_type ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$vat = (string) get_user_meta( $user_id, 'billing_eu_vat_number', true );
		include STOREENGINE_EU_VAT_DIR . 'templates/account-vat-field.php';
	}

	/**
	 * Persist the editable VAT field when the billing address form is saved.
	 *
	 * The core dashboard save handler only loops fields from get_address_fields()
	 * and its registrar schema strips unknown keys, so we read raw $_POST here —
	 * same approach as capture_vat_on_update() on checkout. Nonce + capability are
	 * re-checked because this fires on the public admin-post action.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 */
	public function save_account_vat_field(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'storeengine_nonce' ) ) {
			return;
		}

		$addr_type = isset( $_POST['address_type'] ) ? sanitize_text_field( wp_unslash( $_POST['address_type'] ) ) : '';
		if ( 'billing' !== $addr_type ) {
			return;
		}

		if ( ! isset( $_POST['billing_eu_vat_number'] ) ) {
			return;
		}

		$raw = sanitize_text_field( wp_unslash( $_POST['billing_eu_vat_number'] ) );
		$vat = '' === trim( $raw ) ? '' : normalize_vat_input( $raw );

		update_user_meta( get_current_user_id(), 'billing_eu_vat_number', $vat );
	}

	public function render_order_vat_line( $order ): void {
		$vat = (string) $order->get_meta( '_billing_eu_vat_number' );
		if ( '' === $vat ) {
			return;
		}
		$exempt = 'yes' === (string) $order->get_meta( 'is_vat_exempt' );
		?>
		<p class="storeengine-order-vat-line">
			<strong><?php esc_html_e( 'VAT Number:', 'storeengine' ); ?></strong>
			<?php echo esc_html( $vat ); ?>
			<?php if ( $exempt ) : ?>
				<span class="storeengine-order-vat-exempt">(<?php esc_html_e( 'VAT exempt', 'storeengine' ); ?>)</span>
			<?php endif; ?>
		</p>
		<?php
	}
}
