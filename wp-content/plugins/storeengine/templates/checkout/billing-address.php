<?php
/**
 * Billing Address Form.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Countries;
use StoreEngine\Utils\CheckoutFields;
use StoreEngine\Utils\Helper;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Per-field settings (enabled + required) — shared with the React embedded checkout.
// Falls back to "shown & required" so upgrading sites that haven't saved
// checkout_fields yet behave like before.
$cf = static function ( $id, $default = [ 'enabled' => true, 'required' => true ] ) {
	$row = CheckoutFields::get( $id );
	return $row ? [ 'enabled' => $row['enabled'], 'required' => $row['required'] ] : $default;
};
$f_first   = $cf( 'billing_first_name' );
$f_last    = $cf( 'billing_last_name' );
$f_phone   = $cf( 'billing_phone',          [ 'enabled' => true,  'required' => false ] );
$f_addr1   = $cf( 'billing_address_line' );
$f_addr2   = $cf( 'billing_address_line_2', [ 'enabled' => true,  'required' => false ] );
$f_apt     = $cf( 'billing_apt',            [ 'enabled' => false, 'required' => false ] );
$f_country = $cf( 'billing_country' );
$f_city    = $cf( 'billing_city' );
$f_state   = $cf( 'billing_state',          [ 'enabled' => true,  'required' => false ] );
$f_post    = $cf( 'billing_post_code' );
$req_attr  = static fn ( $r ) => $r ? ' required' : '';
$req_mark  = static fn ( $r ) => $r ? '&nbsp;<abbr class="storeengine-required" title="' . esc_attr__( 'required', 'storeengine' ) . '">*</abbr>' : '';

$customer          = Helper::cart()->get_customer();
$states            = Countries::init()->get_states( $customer->get_billing_country() );
$billing_country   = $customer->get_billing_country();
$billing_country   = empty( $billing_country ) ? Countries::init()->get_base_country() : $billing_country;
$allowed_countries = Countries::init()->get_allowed_countries();
if ( ! array_key_exists( $billing_country, $allowed_countries ) ) {
	$billing_country = current( array_keys( $allowed_countries ) );
}

$address_fields    = Countries::init()->get_address_fields( $billing_country );
$state_label       = $address_fields['billing_state']['label'] ?? __( 'State / County', 'storeengine' );
// Settings override the country-locale's "required" flag for state.
$state_required    = $f_state['required'] || ( $address_fields['billing_state']['required'] ?? false );
$different_address    = false;
$is_digital_cart      = $is_digital_cart ?? false;
$is_different_address = false;

if ( ! $is_digital_cart ) {
	// Honour the customer's explicit choice when it has been persisted by
	// CheckoutService::apply_addresses(). Falls back to the address-comparison
	// heuristic for legacy carts that pre-date the cart meta.
	$persisted_choice = Helper::cart()->get_meta( 'same_as_shipping' );

	if ( 'yes' === $persisted_choice ) {
		$is_different_address = false;
	} elseif ( 'no' === $persisted_choice ) {
		$is_different_address = true;
	} else {
		$billing  = $customer->get_billing();
		$shipping = $customer->get_shipping();
		unset( $shipping['email'], $billing['email'] );

		// Treat the billing block as "not yet entered" until the customer has
		// actually typed billing details. Without this guard the comparison
		// below returns true the moment shipping is filled (because empty
		// billing != filled shipping), which pre-selected "Use a different
		// billing address" on every first-visit physical-product checkout.
		$billing_has_data = false;
		foreach ( [ 'first_name', 'last_name', 'address_1', 'city', 'state', 'postcode' ] as $bk ) {
			if ( ! empty( $billing[ $bk ] ) ) {
				$billing_has_data = true;
				break;
			}
		}

		if ( $billing_has_data ) {
			ksort( $billing );
			ksort( $shipping );
			$is_different_address = ( md5( maybe_serialize( $billing ) ) !== md5( maybe_serialize( $shipping ) ) );
		}
	}
}

// Pre-apply the `storeengine-hide` class server-side when the customer is on
// "Same as shipping" so the billing fields don't flash visible for a frame
// before sameAsShippingAddress() in CheckoutManager.js hides them on load.
// (The JS still owns the on-change toggle; PHP just provides the right
// initial state.)
$hide_cls = ( ! $is_digital_cart && ! $is_different_address ) ? ' storeengine-hide' : '';
?>
<div class="storeengine-ajax-checkout-form__billing-address">
	<h4 class="storeengine-ajax-checkout-form__heading"><?php esc_html_e( 'Billing address', 'storeengine' ); ?></h4>
	<?php if ( ! $is_digital_cart ) { ?>
		<div class="storeengine-form-group storeengine-flex storeengine-form-group--same-as-shipping">
			<label><input type="radio" name="same_as_shipping" value="true" class="storeengine-radio"<?php checked( $is_different_address, false ); ?>><?php esc_html_e( 'Same as shipping address', 'storeengine' ); ?></label>
			<label><input type="radio" name="same_as_shipping" value="false" class="storeengine-radio"<?php checked( $is_different_address, true ); ?>><?php esc_html_e( 'Use A different billing address', 'storeengine' ); ?></label>
		</div>
	<?php } ?>
	<div class="storeengine-form-group<?php echo esc_attr( $hide_cls ); ?>">
		<?php if ( $f_first['enabled'] ) : ?>
		<div class="storeengine-form__inner">
			<label for="billing_first_name" class="storeengine-ajax-checkout-form__title"><?php esc_html_e( 'First Name', 'storeengine' ); ?><?php echo $req_mark( $f_first['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" id="billing_first_name" name="billing_first_name" placeholder="<?php esc_attr_e( 'First Name', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_first_name() ); ?>"<?php echo $req_attr( $f_first['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
		<?php if ( $f_last['enabled'] ) : ?>
		<div class="storeengine-form__inner">
			<label for="billing_last_name"><?php esc_html_e( 'Last Name', 'storeengine' ); ?><?php echo $req_mark( $f_last['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input"  type="text" id="billing_last_name" name="billing_last_name" placeholder="<?php esc_attr_e( 'Last Name', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_last_name() ); ?>"<?php echo $req_attr( $f_last['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
	</div>
	<?php if ( $f_phone['enabled'] ) : ?>
	<div class="storeengine-form-group<?php echo esc_attr( $hide_cls ); ?>">
		<div class="storeengine-form__inner">
			<label for="billing_phone"><?php esc_html_e( 'Phone Number', 'storeengine' ); ?><?php echo $req_mark( $f_phone['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input"  type="text" id="billing_phone" name="billing_phone" placeholder="<?php esc_attr_e( 'Phone', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_phone() ); ?>"<?php echo $req_attr( $f_phone['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
	</div>
	<?php endif; ?>
	<div class="storeengine-form-address-group<?php echo esc_attr( $hide_cls ); ?>">
		<?php if ( $f_addr1['enabled'] ) : ?>
		<div class="storeengine-form-field">
			<label for="billing_address_1"><?php esc_html_e( 'Address Line 1', 'storeengine' ); ?><?php echo $req_mark( $f_addr1['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input"  type="text" id="billing_address_1" name="billing_address_1" placeholder="<?php esc_attr_e( 'Address', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_address_1() ); ?>"<?php echo $req_attr( $f_addr1['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
		<?php if ( $f_addr2['enabled'] ) : ?>
		<div class="storeengine-form-field">
			<label for="billing_address_2"><?php esc_html_e( 'Address Line 2', 'storeengine' ); ?>&nbsp;<span class="optional">(<?php esc_html_e( 'optional', 'storeengine' ); ?>)</span></label>
			<input class="storeengine-ajax-checkout-form__input"  type="text" id="billing_address_2" name="billing_address_2" placeholder="<?php esc_attr_e( 'Apartment, suite, etc. (optional)', 'storeengine' ); ?> " value="<?php echo esc_attr( $customer->get_billing_address_2() ); ?>"<?php echo $req_attr( $f_addr2['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
		<?php if ( $f_apt['enabled'] ) : ?>
		<div class="storeengine-form-field">
			<label for="billing_apt"><?php esc_html_e( 'Apt / Suite', 'storeengine' ); ?><?php echo $req_mark( $f_apt['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input"  type="text" id="billing_apt" name="billing_apt" value=""<?php echo $req_attr( $f_apt['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
		<div class="storeengine-form-group">
			<?php if ( $f_country['enabled'] ) : ?>
			<div class="storeengine-form__inner">
				<label for="billing_country"><?php esc_html_e( 'Country', 'storeengine' ); ?><?php echo $req_mark( $f_country['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<select class="storeengine-ajax-checkout-form__select country_to_state" id="billing_country" name="billing_country"<?php echo $req_attr( $f_country['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php foreach ( Countries::init()->get_allowed_countries() as $country_key => $country_name ) : ?>
						<option value="<?php echo esc_attr( $country_key ); ?>" data-cc="<?php echo esc_html( $country_key ); ?>" <?php selected( $customer->get_billing_country(), $country_key ); ?>><?php echo esc_html( $country_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>
			<?php if ( $f_city['enabled'] ) : ?>
			<div class="storeengine-form__inner">
				<label for="billing_city"><?php esc_html_e( 'City', 'storeengine' ); ?><?php echo $req_mark( $f_city['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<input class="storeengine-ajax-checkout-form__input" type="text" id="billing_city" name="billing_city" placeholder="<?php esc_attr_e( 'City', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_city() ); ?>"<?php echo $req_attr( $f_city['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $f_state['enabled'] || $f_post['enabled'] ) : // Skip the wrapper entirely when both halves are hidden; otherwise the empty flex container leaves a visible margin-bottom gap. ?>
	<div class="storeengine-form-group<?php echo esc_attr( $hide_cls ); ?>">
		<?php if ( $f_state['enabled'] ) : ?>
		<div class="storeengine-form__inner">
			<label for="billing_state">
				<?php echo esc_html( $state_label ); ?>
				<?php echo $req_mark( $state_required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</label>
			<?php if ( is_array( $states ) && empty( $states ) ) { ?>
				<input class="storeengine-ajax-checkout-form__input" type="text" id="billing_state" name="billing_state" value="<?php echo esc_attr( $customer->get_billing_state() ); ?>" readonly/>
			<?php } elseif ( ! is_null( $customer->get_billing_country() ) && is_array( $states ) ) { ?>
				<select class="storeengine-ajax-checkout-form__select" id="billing_state" name="billing_state"<?php echo $req_attr( $state_required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<option value=""><?php esc_html_e( 'Select an option&hellip;', 'storeengine' ); ?></option>
					<?php foreach ( $states as $sk => $label ) { ?>
						<option value="<?php echo esc_attr( $sk ); ?>"<?php selected( $customer->get_billing_state(), $sk ); ?>><?php echo esc_html( $label ); ?></option>
					<?php } ?>
				</select>
			<?php } else { ?>
				<input class="storeengine-ajax-checkout-form__input" type="text" id="billing_state" name="billing_state" placeholder="<?php esc_attr_e( 'State', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_state() ); ?>"<?php echo $req_attr( $state_required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
			<?php } ?>
		</div>
		<?php endif; ?>
		<?php if ( $f_post['enabled'] ) : ?>
		<div class="storeengine-form__inner">
			<label for="billing_postcode"><?php esc_html_e( 'Postal Code', 'storeengine' ); ?><?php echo $req_mark( $f_post['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" id="billing_postcode" name="billing_postcode" placeholder="<?php esc_attr_e( 'Postal Code', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_billing_postcode() ); ?>"<?php echo $req_attr( $f_post['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
	</div>
	<?php do_action( 'storeengine/checkout/billing_address/after', $customer ); ?>
	<?php endif; ?>
</div>
