<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Countries;
use StoreEngine\Utils\CheckoutFields;
use StoreEngine\Utils\Helper;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$cf = static function ( $id, $default = [ 'enabled' => true, 'required' => true ] ) {
	$row = CheckoutFields::get( $id );
	return $row ? [ 'enabled' => $row['enabled'], 'required' => $row['required'] ] : $default;
};
$f_first   = $cf( 'shipping_first_name' );
$f_last    = $cf( 'shipping_last_name' );
$f_phone   = $cf( 'shipping_phone',          [ 'enabled' => true,  'required' => false ] );
$f_addr1   = $cf( 'shipping_address_line' );
$f_addr2   = $cf( 'shipping_address_line_2', [ 'enabled' => true,  'required' => false ] );
$f_apt     = $cf( 'shipping_apt',            [ 'enabled' => false, 'required' => false ] );
$f_country = $cf( 'shipping_country' );
$f_city    = $cf( 'shipping_city' );
$f_state   = $cf( 'shipping_state',          [ 'enabled' => true,  'required' => false ] );
$f_post    = $cf( 'shipping_post_code' );
$req_attr  = static fn ( $r ) => $r ? ' required' : '';
$req_mark  = static fn ( $r ) => $r ? '&nbsp;<abbr class="storeengine-required" title="' . esc_attr__( 'required', 'storeengine' ) . '">*</abbr>' : '';

$customer          = Helper::cart()->get_customer();
$states            = Countries::init()->get_states( $customer->get_shipping_country() );
$shipping_country  = $customer->get_shipping_country();
$shipping_country  = empty( $shipping_country ) ? Countries::init()->get_base_country() : $shipping_country;
$allowed_countries = Countries::init()->get_allowed_countries();
if ( ! array_key_exists( $shipping_country, $allowed_countries ) ) {
	$shipping_country = current( array_keys( $allowed_countries ) );
}
$address_fields = Countries::init()->get_address_fields( $shipping_country );

$state_label    = $address_fields['shipping_state']['label'] ?? __( 'State / County', 'storeengine' );
$state_required = $f_state['required'] || ( $address_fields['shipping_state']['required'] ?? false );
?>
<div class="storeengine-ajax-checkout-form__shipping-address">
	<h4 class="storeengine-ajax-checkout-form__heading"><?php esc_html_e( 'Shipping address', 'storeengine' ); ?></h4>
	<div class="storeengine-form-group">
		<?php if ( $f_first['enabled'] ) : ?>
		<div class="storeengine-form__inner">
			<label for="shipping_first_name" class="storeengine-ajax-checkout-form__title"><?php esc_attr_e( 'First Name', 'storeengine' ); ?><?php echo $req_mark( $f_first['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_first_name" id="shipping_first_name" value="<?php echo esc_attr( $customer->get_shipping_first_name() ); ?>" placeholder="<?php esc_attr_e( 'First Name', 'storeengine' ); ?>"<?php echo $req_attr( $f_first['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
		<?php if ( $f_last['enabled'] ) : ?>
		<div class="storeengine-form__inner">
			<label for="shipping_last_name"><?php esc_attr_e( 'Last Name', 'storeengine' ); ?><?php echo $req_mark( $f_last['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_last_name" id="shipping_last_name" value="<?php echo esc_attr( $customer->get_shipping_last_name() ); ?>" placeholder="<?php esc_attr_e( 'Last Name', 'storeengine' ); ?>"<?php echo $req_attr( $f_last['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
	</div>
	<?php if ( $f_phone['enabled'] ) : ?>
	<div class="storeengine-form-group">
		<div class="storeengine-form__inner">
			<label for="shipping_phone"><?php esc_attr_e( 'Phone Number', 'storeengine' ); ?><?php echo $req_mark( $f_phone['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_phone" id="shipping_phone" value="<?php echo esc_attr( $customer->get_shipping_phone() ); ?>" placeholder="<?php esc_attr_e( 'Phone', 'storeengine' ); ?>"<?php echo $req_attr( $f_phone['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
	</div>
	<?php endif; ?>
	<div class="storeengine-form-address-group">
		<?php if ( $f_addr1['enabled'] ) : ?>
		<div class="storeengine-form-field">
			<label for="shipping_address_1"><?php esc_attr_e( 'Address Line 1', 'storeengine' ); ?><?php echo $req_mark( $f_addr1['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_address_1" id="shipping_address_1" placeholder="<?php esc_attr_e( 'Address', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_shipping_address_1() ); ?>"<?php echo $req_attr( $f_addr1['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
		<?php if ( $f_addr2['enabled'] ) : ?>
		<div class="storeengine-form-field">
			<label for="shipping_address_2"><?php esc_attr_e( 'Address Line 2', 'storeengine' ); ?>&nbsp;<span class="optional">(<?php esc_html_e( 'optional', 'storeengine' ); ?>)</span></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_address_2" id="shipping_address_2" placeholder="<?php esc_attr_e( 'Apartment, suite, etc. (optional)', 'storeengine' ); ?> " value="<?php echo esc_attr( $customer->get_shipping_address_2() ); ?>"<?php echo $req_attr( $f_addr2['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
		<?php if ( $f_apt['enabled'] ) : ?>
		<div class="storeengine-form-field">
			<label for="shipping_apt"><?php esc_html_e( 'Apt / Suite', 'storeengine' ); ?><?php echo $req_mark( $f_apt['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_apt" id="shipping_apt" value=""<?php echo $req_attr( $f_apt['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
		<div class="storeengine-form-group">
			<?php if ( $f_country['enabled'] ) : ?>
			<div class="storeengine-form__inner">
				<label for="shipping_country"><?php esc_attr_e( 'Country', 'storeengine' ); ?><?php echo $req_mark( $f_country['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<select class="storeengine-ajax-checkout-form__select" name="shipping_country" id="shipping_country"<?php echo $req_attr( $f_country['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php foreach ( Countries::init()->get_allowed_countries() as $country_key => $country_name ) : ?>
					<option value="<?php echo esc_attr( $country_key ); ?>" data-cc="<?php echo esc_attr( $country_key ); ?>" <?php selected( $customer->get_shipping_country(), $country_key ); ?>><?php echo esc_html( $country_name ); ?></option>
				<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>
			<?php if ( $f_city['enabled'] ) : ?>
			<div class="storeengine-form__inner">
				<label for="shipping_city"><?php esc_attr_e( 'City', 'storeengine' ); ?><?php echo $req_mark( $f_city['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_city" id="shipping_city" placeholder="<?php esc_attr_e( 'City', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_shipping_city() ); ?>"<?php echo $req_attr( $f_city['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $f_state['enabled'] || $f_post['enabled'] ) : // Skip the wrapper entirely when both halves are hidden; otherwise the empty flex container leaves a visible margin-bottom gap. ?>
	<div class="storeengine-form-group">
		<?php if ( $f_state['enabled'] ) : ?>
		<div class="storeengine-form__inner">
			<label for="shipping_state">
				<?php echo esc_html( $state_label ); ?>
				<?php echo $req_mark( $state_required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</label>
			<?php if ( is_array( $states ) && empty( $states ) ) { ?>
				<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_state" id="shipping_state" value="<?php echo esc_attr( $customer->get_shipping_state() ); ?>" readonly/>
			<?php } elseif ( ! is_null( $customer->get_shipping_country() ) && is_array( $states ) ) { ?>
				<select class="storeengine-ajax-checkout-form__select" id="shipping_state" name="shipping_state"<?php echo $req_attr( $state_required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<option value=""><?php esc_html_e( 'Select an option&hellip;', 'storeengine' ); ?></option>
					<?php foreach ( $states as $sk => $sv ) { ?>
						<option value="<?php echo esc_attr( $sk ); ?>"<?php selected( $customer->get_shipping_state(), $sk ); ?>><?php echo esc_html( $sv ); ?></option>
					<?php } ?>
				</select>
			<?php } else { ?>
				<input class="storeengine-ajax-checkout-form__input" type="text" id="shipping_state" name="shipping_state" placeholder="<?php esc_attr_e( 'State', 'storeengine' ); ?>" value="<?php echo esc_attr( $customer->get_shipping_state() ); ?>"<?php echo $req_attr( $state_required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
			<?php } ?>
		</div>
		<?php endif; ?>
		<?php if ( $f_post['enabled'] ) : ?>
		<div class="storeengine-form__inner">
			<label for="shipping_postal_code"><?php esc_attr_e( 'Postal Code', 'storeengine' ); ?><?php echo $req_mark( $f_post['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<input class="storeengine-ajax-checkout-form__input" type="text" name="shipping_postal_code" id="shipping_postal_code" value="<?php echo esc_attr( $customer->get_shipping_postcode() ); ?>" placeholder="<?php esc_attr_e( 'Postal Code', 'storeengine' ); ?>"<?php echo $req_attr( $f_post['required'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
