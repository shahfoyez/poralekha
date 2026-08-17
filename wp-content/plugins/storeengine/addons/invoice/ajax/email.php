<?php

namespace StoreEngine\Addons\Invoice\Ajax;

use StoreEngine\Addons\Email\order\Invoice;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Order\OrderItemTax;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class Email extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_invoice';

	public function __construct() {
		$this->actions = [
			'send_invoice_customer' => [
				'callback' => [ $this, 'send_invoice_customer' ],
				'fields'   => [
					'order_id' => 'int',
				],
			],
			'update_invoice_tax'    => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_invoice_tax' ],
				'fields'     => [
					'order_id'   => 'int',
					// Optional manual override (decimal). When omitted, the tax is
					// derived from the gateway's recorded settled amount.
					'tax_amount' => 'string',
				],
			],
			'get_invoice_tax_status' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_invoice_tax_status' ],
				'fields'     => [
					'order_id' => 'int',
				],
			],
		];
	}

	/**
	 * Report whether an order's invoice may under-report what the gateway settled,
	 * so the admin UI can surface a reconcile action. Read-only.
	 */
	public function get_invoice_tax_status( array $payload ) {
		if ( ! isset( $payload['order_id'] ) || ! is_numeric( $payload['order_id'] ) ) {
			wp_send_json_error( __( 'Invalid order ID!', 'storeengine' ) );
		}

		$order = Helper::get_order( (int) $payload['order_id'] );
		if ( ! $order || is_wp_error( $order ) ) {
			wp_send_json_error( __( 'Order not found!', 'storeengine' ) );
		}

		$currency      = $order->get_currency();
		$decimals      = Formatting::get_price_decimals();
		$current_total = (float) $order->get_total();
		$current_tax   = (float) $order->get_total_tax();
		$net           = $current_total - $current_tax;

		$captured_raw  = $order->get_meta( Helper::META_GATEWAY_CAPTURED_AMOUNT );
		$has_captured  = ( '' !== $captured_raw && null !== $captured_raw );
		$captured      = $has_captured ? round( (float) $captured_raw, $decimals ) : null;
		$suggested_tax = $has_captured ? max( 0, round( $captured - $net, $decimals ) ) : null;

		$price = static function ( $amount ) use ( $currency ) {
			return self::plain_price( (float) $amount, $currency );
		};

		wp_send_json_success( [
			'mismatch'          => '1' === (string) $order->get_meta( Helper::META_INVOICE_TAX_MISMATCH ),
			'has_captured'      => $has_captured,
			'captured'          => $captured,
			'captured_html'     => $has_captured ? $price( $captured ) : '',
			'current_total'     => $current_total,
			'current_total_html' => $price( $current_total ),
			'current_tax'       => $current_tax,
			'current_tax_html'  => $price( $current_tax ),
			'suggested_tax'     => $suggested_tax,
			'suggested_tax_html' => null !== $suggested_tax ? $price( $suggested_tax ) : '',
			'revised_at'        => (string) $order->get_meta( '_storeengine_invoice_revised_at' ),
		] );
	}

	public function send_invoice_customer( array $payload ) {
		if ( ! Formatting::string_to_bool( get_option( 'storeengine_invoice_fonts_downloaded', false ) ) ) {
			wp_send_json_error( __( 'Please download the fonts before generating the PDF.', 'storeengine' ) );
		}

		if ( ! isset( $payload['order_id'] ) || ! is_numeric( $payload['order_id'] ) ) {
			wp_send_json_error( __( 'Invalid order ID!', 'storeengine' ) );
		}

		if ( ! class_exists( 'StoreEngine\Addons\Email\order\Invoice' ) ) {
			wp_send_json_error( __( 'Email addon is not enabled!', 'storeengine' ) );
		}

		$order_id = $payload['order_id'];
		$order    = Helper::get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			wp_send_json_error( __( 'Order not found!', 'storeengine' ) );
		}

		( new Invoice() )->send_email( $order );
		wp_send_json_success( __( 'Invoice mail sent to customer successfully', 'storeengine' ) );
	}

	/**
	 * Reconcile an order's tax against what the gateway actually settled, then
	 * update the stored order so the (on-demand) invoice reflects the real
	 * amount paid. Used when tax was computed outside StoreEngine and the invoice
	 * would otherwise under-report the total.
	 *
	 * The corrected tax is either taken from the gateway's recorded settled amount
	 * (`_storeengine_gateway_captured_amount`) or, when a `tax_amount` is provided,
	 * from the admin's manual entry. The order line-item nets are left untouched;
	 * only the tax line and grand total are adjusted.
	 */
	public function update_invoice_tax( array $payload ) {
		if ( ! isset( $payload['order_id'] ) || ! is_numeric( $payload['order_id'] ) ) {
			wp_send_json_error( __( 'Invalid order ID!', 'storeengine' ) );
		}

		$order = Helper::get_order( (int) $payload['order_id'] );
		if ( ! $order || is_wp_error( $order ) ) {
			wp_send_json_error( __( 'Order not found!', 'storeengine' ) );
		}

		$decimals      = Formatting::get_price_decimals();
		$current_total = (float) $order->get_total();
		$current_tax   = (float) $order->get_total_tax();
		$net           = $current_total - $current_tax;

		$manual = ( isset( $payload['tax_amount'] ) && '' !== trim( (string) $payload['tax_amount'] ) )
			? (float) $payload['tax_amount']
			: null;

		if ( null !== $manual ) {
			// Admin-entered tax, added on top of the existing net.
			$new_tax   = max( 0, round( $manual, $decimals ) );
			$new_total = round( $net + $new_tax, $decimals );
		} else {
			$captured = $order->get_meta( Helper::META_GATEWAY_CAPTURED_AMOUNT );
			if ( '' === $captured || null === $captured ) {
				wp_send_json_error( __( 'No settled gateway amount is recorded for this order. Enter the tax amount manually.', 'storeengine' ) );
			}

			$new_total = round( (float) $captured, $decimals );
			$new_tax   = round( $new_total - $net, $decimals );

			if ( $new_tax < 0 ) {
				wp_send_json_error( __( 'The settled amount is lower than the order net, so tax cannot be derived automatically. Enter it manually.', 'storeengine' ) );
			}
		}

		// Preserve any existing shipping tax; the reconciled delta is cart tax.
		$shipping_tax = (float) $order->get_shipping_tax();
		$cart_tax     = max( 0, round( $new_tax - $shipping_tax, $decimals ) );

		// Replace existing tax line items with a single reconciled line.
		foreach ( $order->get_taxes() as $tax_item ) {
			$order->remove_item( $tax_item->get_id() );
		}

		if ( $new_tax > 0 ) {
			$tax_item = new OrderItemTax();
			$tax_item->set_rate_id( 0 );
			$tax_item->set_rate_code( 'TAX-RECONCILED' );
			$tax_item->set_label( __( 'Tax', 'storeengine' ) );
			$tax_item->set_tax_total( $cart_tax );
			$tax_item->set_shipping_tax_total( $shipping_tax );
			$order->add_item( $tax_item );
		}

		// Order matters: set_cart_tax derives total_tax from the current shipping tax.
		$order->set_shipping_tax( $shipping_tax );
		$order->set_cart_tax( $cart_tax );
		$order->set_total( $new_total );
		$order->delete_meta_data( Helper::META_INVOICE_TAX_MISMATCH );
		$order->update_meta_data( '_storeengine_invoice_revised_at', current_time( 'mysql', true ) );

		$order->add_order_note( sprintf(
			/* translators: 1: tax amount, 2: grand total */
			__( 'Invoice tax reconciled — tax set to %1$s, order total %2$s.', 'storeengine' ),
			wp_strip_all_tags( Formatting::price( $new_tax, [ 'currency' => $order->get_currency() ] ) ),
			wp_strip_all_tags( Formatting::price( $new_total, [ 'currency' => $order->get_currency() ] ) )
		) );
		$order->save();

		wp_send_json_success( [
			'message'    => __( 'Invoice updated with the correct tax amount.', 'storeengine' ),
			'tax'        => $new_tax,
			'total'      => $new_total,
			'tax_html'   => self::plain_price( $new_tax, $order->get_currency() ),
			'total_html' => self::plain_price( $new_total, $order->get_currency() ),
		] );
	}

	/**
	 * Formatted price as plain text (currency symbol, no HTML markup), suitable
	 * for JSON returned to React — which escapes HTML in text nodes.
	 */
	private static function plain_price( float $amount, string $currency ): string {
		$html = Formatting::price( $amount, [ 'currency' => $currency ] );

		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5 ) );
	}
}
