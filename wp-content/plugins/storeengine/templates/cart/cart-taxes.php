<?php

use StoreEngine\Classes\Countries;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! isset( $cart ) ) {
	$cart = storeengine_cart();
}

if ( TaxUtil::is_tax_enabled() && ! $cart->display_prices_including_tax() ) {
	$estimated_text = TaxUtil::get_tax_label_suffix();

	if ( 'itemized' === Helper::get_settings( 'tax_total_display' ) ) {
		foreach ( $cart->get_tax_totals() as $code => $tax ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			?>
			<tr class="storeengine-cart-sub-total-table__tax-rate">
				<th><?php echo esc_html( $tax->label ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<td data-title="<?php echo esc_attr( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
			</tr>
			<?php
		}
	} else {
		?>
		<tr class="tax-total">
			<th scope="row"><?php echo esc_html( Countries::get_instance()->tax_or_vat() ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
			<td data-title="<?php echo esc_attr( Countries::get_instance()->tax_or_vat() ); ?>"><?php echo Formatting::price( $cart->get_taxes_total() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
		</tr>
		<?php
	}
}
