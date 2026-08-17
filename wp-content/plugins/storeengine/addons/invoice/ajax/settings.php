<?php

namespace StoreEngine\Addons\Invoice\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;

class Settings extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_invoice';

	public function __construct() {
		$this->actions = [
			'update_settings' => [
				'callback' => [ $this, 'update_settings' ],
				'fields'   => [
					'date_format'                => 'string',
					'logo'                       => 'int',
					'invoice_mail_attachment'    => 'string',
					'invoice_paper_size'         => 'string',
					'invoice_show_product_image' => 'bool',
					'invoice_front_view'         => 'string',
					'invoice_front_btn'          => 'string',
					'invoice_date_from'          => 'string',
					'invoice_default_note'       => 'string',
					'invoice_footer_text'        => 'string',
					// §14 UStG — numbering.
					'invoice_number_prefix'      => 'string',
					'invoice_number_padding'     => 'int',
					'invoice_number_reset'       => 'string',
					'invoice_number_next'        => 'int',
					// §14 UStG — seller legal info + tax IDs.
					'invoice_seller_legal_name'  => 'string',
					'invoice_tax_number'         => 'string',
					'invoice_vat_id'             => 'string',
					'invoice_seller_extra'       => 'textarea',
					// §14 UStG — delivery + small-business notes.
					'invoice_show_delivery_note' => 'bool',
					'invoice_kleinunternehmer'   => 'bool',
					'invoice_kleinunternehmer_note' => 'string',
				],
			],
		];
	}

	public function update_settings( array $payload ) {
		$payload['invoice_mail_attachment'] = explode( ',', ! empty( $payload['invoice_mail_attachment'] ) ? $payload['invoice_mail_attachment'] : [] );

		\StoreEngine\Addons\Invoice\Settings::save_settings( $payload );

		wp_send_json_success( \StoreEngine\Addons\Invoice\Settings::get_settings_saved_data() );
	}

}
