<?php
/**
 * Editable EU VAT field — rendered inside the My Account edit-address form
 * (billing only). Saved to user meta billing_eu_vat_number by
 * Hooks::save_account_vat_field().
 *
 * Vars in scope from Hooks::render_account_vat_field():
 *   $vat string Stored VAT number for the current user (may be empty).
 */

use StoreEngine\Addons\EuVat\Classes\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_label       = (string) Settings::get( 'field_label', __( 'EU VAT Number', 'storeengine' ) );
$storeengine_placeholder = (string) Settings::get( 'field_placeholder', '' );
$storeengine_description  = (string) Settings::get( 'field_description', '' );
?>
<div class="storeengine-col-12 storeengine-form-group storeengine-eu-vat-row">
	<div class="storeengine-form__inner">
		<label for="billing_eu_vat_number"><?php echo esc_html( $storeengine_label ); ?></label>
		<input
			type="text"
			id="billing_eu_vat_number"
			name="billing_eu_vat_number"
			placeholder="<?php echo esc_attr( $storeengine_placeholder ); ?>"
			value="<?php echo esc_attr( $vat ); ?>"
			autocomplete="off"
		/>
		<?php if ( $storeengine_description ) : ?>
			<small class="storeengine-eu-vat-description"><?php echo wp_kses_post( $storeengine_description ); ?></small>
		<?php endif; ?>
	</div>
</div>
