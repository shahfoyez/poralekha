<?php
/**
 * VAT Number row on the My Account billing address card.
 *
 * Vars in scope from Hooks::render_account_vat():
 *   $vat string  Stored VAT number for the current user (may be empty).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( '' === $vat ) {
	return;
}
?>
<div class="storeengine-eu-vat-account-row">
	<strong><?php esc_html_e( 'EU VAT Number', 'storeengine' ); ?></strong>
	<span class="storeengine-eu-vat-account-value"><?php echo esc_html( $vat ); ?></span>
</div>
