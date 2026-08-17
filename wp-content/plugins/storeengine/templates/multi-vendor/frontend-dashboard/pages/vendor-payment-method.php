<?php
/**
 * Vendor payment method form. Stores method + arbitrary key/value data on the
 * vendor row's `payment_method` and `payment_data` columns. Submitted values
 * are validated and persisted via the standard frontend POST handler below
 * (kept inline since this is a single small form).
 *
 * @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\MultiVendor\Settings;

$storeengine_enabled = (array) Settings::get( 'enabled_payment_methods', [ 'paypal', 'bank' ] );
$storeengine_saved   = false;

if ( isset( $_POST['storeengine_vendor_pm_nonce'] )
	&& wp_verify_nonce( sanitize_key( $_POST['storeengine_vendor_pm_nonce'] ), 'storeengine_vendor_pm' )
) {
	$storeengine_method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
	if ( in_array( $storeengine_method, $storeengine_enabled, true ) ) {
		$vendor->set_payment_method( $storeengine_method );
		$storeengine_data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' ) : [];
		$vendor->set_payment_data( $storeengine_data );
		$vendor->save();
		$storeengine_saved = true;
	}
}

$storeengine_method  = $vendor->get_payment_method() ?: 'paypal';
$storeengine_pd      = $vendor->get_payment_data();
$storeengine_paypal  = $storeengine_pd['paypal_email'] ?? '';
$storeengine_account = $storeengine_pd['account_holder'] ?? '';
$storeengine_bank    = $storeengine_pd['bank_name'] ?? '';
$storeengine_iban    = $storeengine_pd['account_number'] ?? '';
$storeengine_swift   = $storeengine_pd['swift'] ?? '';
?>
<div class="storeengine-vendor-pm">
	<p style="color:#64748b;font-size:13px;margin-top:0;">
		<?php esc_html_e( 'How you want to receive payouts. Add your details before requesting a withdrawal.', 'storeengine' ); ?>
	</p>

	<?php if ( $storeengine_saved ) : ?>
		<div class="storeengine-notice storeengine-notice--success">
			<?php esc_html_e( 'Payment method saved.', 'storeengine' ); ?>
		</div>
	<?php endif; ?>

	<form method="post" class="storeengine-form">
		<?php wp_nonce_field( 'storeengine_vendor_pm', 'storeengine_vendor_pm_nonce' ); ?>

		<p class="storeengine-form-row">
			<label class="storeengine-form-row__label"><?php esc_html_e( 'Method', 'storeengine' ); ?></label>
			<select name="method">
				<?php if ( in_array( 'paypal', $storeengine_enabled, true ) ) : ?>
					<option value="paypal" <?php selected( $storeengine_method, 'paypal' ); ?>><?php esc_html_e( 'PayPal', 'storeengine' ); ?></option>
				<?php endif; ?>
				<?php if ( in_array( 'bank', $storeengine_enabled, true ) ) : ?>
					<option value="bank" <?php selected( $storeengine_method, 'bank' ); ?>><?php esc_html_e( 'Bank transfer', 'storeengine' ); ?></option>
				<?php endif; ?>
				<?php if ( in_array( 'stripe', $storeengine_enabled, true ) ) : ?>
					<option value="stripe" <?php selected( $storeengine_method, 'stripe' ); ?>><?php esc_html_e( 'Stripe', 'storeengine' ); ?></option>
				<?php endif; ?>
			</select>
		</p>

		<div class="storeengine-vendor-pm__paypal" <?php echo 'paypal' !== $storeengine_method ? 'style="display:none;"' : ''; ?>>
			<p class="storeengine-form-row">
				<label class="storeengine-form-row__label"><?php esc_html_e( 'PayPal email', 'storeengine' ); ?></label>
				<input type="email" name="data[paypal_email]" value="<?php echo esc_attr( $storeengine_paypal ); ?>">
			</p>
		</div>

		<div class="storeengine-vendor-pm__bank" <?php echo 'bank' !== $storeengine_method ? 'style="display:none;"' : ''; ?>>
			<p class="storeengine-form-row">
				<label class="storeengine-form-row__label"><?php esc_html_e( 'Account holder', 'storeengine' ); ?></label>
				<input type="text" name="data[account_holder]" value="<?php echo esc_attr( $storeengine_account ); ?>">
			</p>
			<p class="storeengine-form-row">
				<label class="storeengine-form-row__label"><?php esc_html_e( 'Bank name', 'storeengine' ); ?></label>
				<input type="text" name="data[bank_name]" value="<?php echo esc_attr( $storeengine_bank ); ?>">
			</p>
			<p class="storeengine-form-row">
				<label class="storeengine-form-row__label"><?php esc_html_e( 'Account / IBAN', 'storeengine' ); ?></label>
				<input type="text" name="data[account_number]" value="<?php echo esc_attr( $storeengine_iban ); ?>">
			</p>
			<p class="storeengine-form-row">
				<label class="storeengine-form-row__label"><?php esc_html_e( 'SWIFT / BIC', 'storeengine' ); ?></label>
				<input type="text" name="data[swift]" value="<?php echo esc_attr( $storeengine_swift ); ?>">
			</p>
		</div>

		<p class="storeengine-form-row">
			<button type="submit" class="storeengine-btn storeengine-btn--md storeengine-btn--preset-blue">
				<?php esc_html_e( 'Save payment method', 'storeengine' ); ?>
			</button>
		</p>
	</form>
</div>

<script>
( function () {
	var sel = document.querySelector( '.storeengine-vendor-pm select[name="method"]' );
	if ( ! sel ) { return; }
	sel.addEventListener( 'change', function () {
		var v = sel.value;
		var paypal = document.querySelector( '.storeengine-vendor-pm__paypal' );
		var bank   = document.querySelector( '.storeengine-vendor-pm__bank' );
		if ( paypal ) { paypal.style.display = v === 'paypal' ? '' : 'none'; }
		if ( bank ) { bank.style.display = v === 'bank' ? '' : 'none'; }
	} );
} )();
</script>
