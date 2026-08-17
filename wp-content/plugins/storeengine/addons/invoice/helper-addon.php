<?php

namespace StoreEngine\Addons\Invoice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Invoice\PDF\Generator;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Tax;
use StoreEngine\Mpdf\MpdfException;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Fs;
use StoreEngine\Utils\Helper;
use Throwable;

class HelperAddon {

	public static function get_fonts_dir(): string {
		return Helper::get_upload_dir() . '/invoice/fonts';
	}

	public static function get_setting( string $name, $default = null ) {
		$settings = Settings::get_settings_saved_data();

		return $settings[ $name ] ?? $default;
	}

	public static function get_pdf_url( int $order_id, string $document_type = 'invoice', bool $download = false ): string {
		return add_query_arg( [
			'action'        => 'storeengine_invoice/generate_pdf',
			'document_type' => $document_type,
			'order_id'      => $order_id,
			'download'      => $download,
			'security'      => wp_create_nonce( 'storeengine_nonce' ),
		], admin_url( 'admin-ajax.php' ) );
	}

	public static function get_invoice_preview_url( string $order_key ): string {
		return add_query_arg( [
			'store_document_type' => 'invoice',
			'key'                 => str_replace( 'se_order_', 'store_order_', $order_key ),
		], site_url() );
	}

	public static function generate_pdf( Order $order ): ?string {
		try {
			$invoice_date = null;

			switch ( self::get_setting( 'invoice_date_from', 'order_paid' ) ) {
				case 'order_created':
					$invoice_date = $order->get_date_created_gmt() ? $order->get_date_created_gmt()->format( self::get_setting( 'date_format', 'd F, Y' ) ) : null;
					break;
				case 'order_paid':
				default:
					$invoice_date = $order->get_date_paid_gmt() ? $order->get_date_paid_gmt()->format( self::get_setting( 'date_format', 'd F, Y' ) ) : null;
					break;
			}

			ob_start();
			include STOREENGINE_INVOICE_TEMPLATE_DIR . 'invoice/template.php';
			$invoice_html = ob_get_clean();
			$generator    = new Generator( $invoice_html, Fs::get_contents( STOREENGINE_INVOICE_TEMPLATE_DIR . 'invoice/style.css' ) );

			$dir = Helper::get_upload_dir() . '/tmp-invoices';

			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			$file_path = $dir . '/invoice-' . $order->get_id() . '.pdf';

			$generator->save( $file_path );

			if ( ! as_next_scheduled_action( 'storeengine/mail/clean_tmp_invoices', [ 'pdf' => $file_path ] ) ) {
				// Delete after 10 minutes.
				as_schedule_single_action( time() + 600, 'storeengine/mail/clean_tmp_invoices', [ 'pdf' => $file_path ] );
			}

			return $file_path;
		} catch ( Throwable $e ) {
			Helper::log_error( 'Failed to create order pdf invoice.' );
			Helper::log_error( $e );

			return null;
		}
	}

	/**
	 * Per-tax-rate breakdown for §14 UStG (Entgelt je Steuersatz).
	 *
	 * Net per rate is derived from the rate's tax amount and percentage
	 * (net = tax / (percent/100)), which is exact for standard VAT and avoids
	 * having to re-attribute per-item nets. Zero-rated buckets are hidden by
	 * core (storeengine/order_hide_zero_taxes), so this lists charged rates only;
	 * the net/tax/gross summary is taken straight from the order totals.
	 *
	 * @return array{rates: array<int, array{label:string, percent:float, net:float, tax:float}>, net:float, tax:float, gross:float}
	 */
	public static function get_tax_breakdown( Order $order ): array {
		$rates     = [];
		$gross     = (float) $order->get_total();
		$total_tax = (float) $order->get_total_tax();

		foreach ( $order->get_tax_totals() as $tax_total ) {
			$tax_amount = (float) ( $tax_total->amount ?? 0 );
			if ( $tax_amount <= 0 ) {
				continue;
			}
			$percent = isset( $tax_total->rate_id ) ? Tax::get_rate_percent_value( $tax_total->rate_id ) : 0.0;
			if ( $percent > 0 ) {
				// Exact for a known local VAT rate: net = tax / (percent/100).
				$net = $tax_amount / ( $percent / 100 );
			} else {
				// Gateway-computed lines (e.g. Stripe Automatic Tax) carry no local
				// percent. Attribute the order-level net (gross − tax) to this rate in
				// proportion to its share of total tax, so the row stays consistent
				// with the summary instead of showing a bogus zero net.
				$net = $total_tax > 0 ? ( ( $gross - $total_tax ) * ( $tax_amount / $total_tax ) ) : 0.0;
			}

			$rates[] = [
				'label'   => (string) ( $tax_total->label ?? __( 'VAT', 'storeengine' ) ),
				'percent' => $percent,
				'net'     => $net,
				'tax'     => $tax_amount,
			];
		}

		return [
			'rates' => $rates,
			'net'   => $gross - $total_tax,
			'tax'   => $total_tax,
			'gross' => $gross,
		];
	}

	/**
	 * Reverse-charge state for an order (intra-EU B2B), derived from the eu-vat
	 * addon's order meta. No hard dependency — reads meta only, with a fallback to
	 * the customer's profile VAT number which is then attached to the order.
	 *
	 * @return array{is_reverse_charge:bool, vat_number:string}
	 */
	public static function get_reverse_charge( Order $order ): array {
		$exempt     = 'yes' === (string) $order->get_meta( 'is_vat_exempt' );
		$vat_number = (string) $order->get_meta( '_billing_eu_vat_number' );

		// Fall back to the customer's profile VAT number (set at checkout or via
		// My Account) when the order itself has none, then attach it to the order
		// permanently so every later view — order detail, future invoices — has it.
		if ( '' === $vat_number && $order->get_customer_id() > 0 ) {
			$profile_vat = (string) get_user_meta( $order->get_customer_id(), 'billing_eu_vat_number', true );
			if ( '' !== $profile_vat ) {
				$vat_number = $profile_vat;
				$order->update_meta_data( '_billing_eu_vat_number', $vat_number );
				$order->save();
			}
		}

		return [
			'is_reverse_charge' => $exempt && '' !== $vat_number && (float) $order->get_total_tax() <= 0,
			'vat_number'        => $vat_number,
		];
	}

	/**
	 * Format a money amount in the order's currency.
	 */
	public static function price( $amount, Order $order ): string {
		return Formatting::price( (float) $amount, [ 'currency' => $order->get_currency() ] );
	}
}
