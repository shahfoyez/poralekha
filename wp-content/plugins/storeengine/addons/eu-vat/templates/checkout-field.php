<?php
/**
 * EU VAT field — rendered on checkout under the billing address.
 *
 * Vars in scope from Hooks::render_checkout_field():
 *   $stored   string   Pre-filled value (from draft order or user meta).
 *   $customer Customer Cart customer object.
 */

use StoreEngine\Addons\EuVat\Classes\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_label       = (string) Settings::get( 'field_label', __( 'EU VAT Number', 'storeengine' ) );
$storeengine_placeholder = (string) Settings::get( 'field_placeholder', '' );
$storeengine_description  = (string) Settings::get( 'field_description', '' );
$storeengine_required     = (string) Settings::get( 'field_required', 'optional' );
$storeengine_hard_req     = 'required' === $storeengine_required;
?>
<div class="storeengine-form-group storeengine-eu-vat-row">
	<div class="storeengine-form__inner">
		<label for="billing_eu_vat_number">
			<?php echo esc_html( $storeengine_label ); ?>
			<?php if ( $storeengine_hard_req ) : ?>
				&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr>
			<?php endif; ?>
		</label>
		<input
			type="text"
			id="billing_eu_vat_number"
			name="billing_eu_vat_number"
			placeholder="<?php echo esc_attr( $storeengine_placeholder ); ?>"
			value="<?php echo esc_attr( $stored ); ?>"
			autocomplete="off"
			data-required-mode="<?php echo esc_attr( $storeengine_required ); ?>"
			<?php echo $storeengine_hard_req ? 'required' : ''; ?>
		/>
		<?php if ( $storeengine_description ) : ?>
			<small class="storeengine-eu-vat-description"><?php echo wp_kses_post( $storeengine_description ); ?></small>
		<?php endif; ?>
	</div>
</div>
