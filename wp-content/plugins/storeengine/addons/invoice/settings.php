<?php

namespace StoreEngine\Addons\Invoice;

use StoreEngine\Classes\OrderStatus\Completed;
use StoreEngine\Classes\OrderStatus\Processing;

class Settings {

	public static function get_settings_saved_data() {
		$settings = get_option( STOREENGINE_INVOICE_SETTINGS );
		if ( $settings ) {
			return json_decode( $settings, true );
		}

		return [];
	}

	public static function get_settings_default_data() {
		return apply_filters( 'storeengine/invoice/settings_default_data', [
			'date_format'                => 'd F, Y',
			'logo'                       => null,
			'invoice_mail_attachment'    => [
				'order_confirmation',
				'order_refund',
			],
			'invoice_paper_size'         => 'A4',
			'invoice_for_free_order'     => false,
			'invoice_show_product_image' => false,
			'invoice_front_view'         => 'preview',
			'invoice_front_btn'          => 'order_paid',
			'invoice_date_from'          => 'order_paid',
			'invoice_default_note'       => '',
			'invoice_footer_text'        => '',
			// §14 UStG — sequential invoice numbering.
			'invoice_number_prefix'      => '',
			'invoice_number_padding'     => 4,
			'invoice_number_reset'       => 'none', // none | yearly
			'invoice_number_next'        => 1,
			// §14 UStG — seller legal info + tax IDs.
			'invoice_seller_legal_name'  => '',
			'invoice_tax_number'         => '', // Steuernummer
			'invoice_vat_id'             => '', // USt-IdNr.
			'invoice_seller_extra'       => '', // register court / HRB / managing director
			// §14 UStG — delivery date + small-business notes.
			'invoice_show_delivery_note' => true,
			'invoice_kleinunternehmer'   => false,
			'invoice_kleinunternehmer_note' => __( 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.', 'storeengine' ),
		] );
	}

	public static function save_settings( $form_data = false ) {
		$default_data  = self::get_settings_default_data();
		$saved_data    = self::get_settings_saved_data();
		$settings_data = wp_parse_args( $saved_data, $default_data );
		if ( $form_data ) {
			$settings_data = wp_parse_args( $form_data, $settings_data );
		}

		update_option( STOREENGINE_INVOICE_SETTINGS, wp_json_encode( $settings_data ) );
	}

}
