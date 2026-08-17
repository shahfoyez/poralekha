<?php
/**
 * Vendor signup form template.
 *
 * @package StoreEngine\MultiVendor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag from a post-redirect GET; sanitized with sanitize_key, no state change.
$status = isset( $_GET['storeengine_vendor_signup'] ) ? sanitize_key( wp_unslash( $_GET['storeengine_vendor_signup'] ) ) : '';
?>
<style>
	.storeengine-vendor-signup__name-row { display: flex; gap: 12px; }
	.storeengine-vendor-signup__name-row > .storeengine-form-row { flex: 1; }
</style>
<div class="storeengine-vendor-signup">
	<?php if ( 'pending' === $status ) : ?>
		<div class="storeengine-notice storeengine-notice--success">
			<?php esc_html_e( 'Thanks for applying. Your account is pending review.', 'storeengine' ); ?>
		</div>
	<?php elseif ( 'exists' === $status ) : ?>
		<div class="storeengine-notice storeengine-notice--error">
			<?php esc_html_e( 'An account with that email already exists.', 'storeengine' ); ?>
		</div>
	<?php elseif ( 'invalid' === $status ) : ?>
		<div class="storeengine-notice storeengine-notice--error">
			<?php esc_html_e( 'Please complete all required fields, accept the terms, and use a password of at least 8 characters.', 'storeengine' ); ?>
		</div>
	<?php elseif ( 'error' === $status ) : ?>
		<div class="storeengine-notice storeengine-notice--error">
			<?php esc_html_e( 'Could not create your account. Please try again.', 'storeengine' ); ?>
		</div>
	<?php endif; ?>

	<form method="post" class="storeengine-form storeengine-vendor-signup__form">
		<?php wp_nonce_field( 'storeengine_vendor_signup', 'storeengine_vendor_signup_nonce' ); ?>
		<input type="hidden" name="_signup_url" value="<?php echo esc_url( ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . strtok( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', '?' ) ); ?>">

		<p class="storeengine-form-row">
			<label for="storeengine-vendor-store-name"><?php esc_html_e( 'Store name', 'storeengine' ); ?> <span class="required">*</span></label>
			<input type="text" id="storeengine-vendor-store-name" name="store_name" required>
		</p>

		<p class="storeengine-form-row">
			<label for="storeengine-vendor-store-description"><?php esc_html_e( 'About your store', 'storeengine' ); ?> <span class="required">*</span></label>
			<textarea id="storeengine-vendor-store-description" name="store_description" rows="4" maxlength="1000" required placeholder="<?php esc_attr_e( 'Tell us what your store sells and what makes it unique.', 'storeengine' ); ?>"></textarea>
		</p>

		<div class="storeengine-vendor-signup__name-row">
			<p class="storeengine-form-row">
				<label for="storeengine-vendor-first-name"><?php esc_html_e( 'First name', 'storeengine' ); ?> <span class="required">*</span></label>
				<input type="text" id="storeengine-vendor-first-name" name="first_name" required>
			</p>

			<p class="storeengine-form-row">
				<label for="storeengine-vendor-last-name"><?php esc_html_e( 'Last name', 'storeengine' ); ?> <span class="required">*</span></label>
				<input type="text" id="storeengine-vendor-last-name" name="last_name" required>
			</p>
		</div>

		<p class="storeengine-form-row">
			<label for="storeengine-vendor-email"><?php esc_html_e( 'Email', 'storeengine' ); ?> <span class="required">*</span></label>
			<input type="email" id="storeengine-vendor-email" name="email" required>
		</p>

		<p class="storeengine-form-row">
			<label for="storeengine-vendor-phone"><?php esc_html_e( 'Phone', 'storeengine' ); ?> <span class="required">*</span></label>
			<input type="tel" id="storeengine-vendor-phone" name="phone" required>
		</p>

		<p class="storeengine-form-row">
			<label for="storeengine-vendor-address"><?php esc_html_e( 'Address', 'storeengine' ); ?> <span class="required">*</span></label>
			<textarea id="storeengine-vendor-address" name="address" rows="3" required placeholder="<?php esc_attr_e( 'Street, city, state, postcode, country', 'storeengine' ); ?>"></textarea>
		</p>

		<p class="storeengine-form-row">
			<span class="storeengine-form-row__label"><?php esc_html_e( 'Business type', 'storeengine' ); ?> <span class="required">*</span></span>
			<label class="storeengine-radio">
				<input type="radio" name="business_type" value="individual" class="storeengine-radio" checked>
				<?php esc_html_e( 'Individual', 'storeengine' ); ?>
			</label>
			<label class="storeengine-radio">
				<input type="radio" name="business_type" value="company" class="storeengine-radio">
				<?php esc_html_e( 'Company', 'storeengine' ); ?>
			</label>
		</p>

		<p class="storeengine-form-row">
			<label for="storeengine-vendor-password"><?php esc_html_e( 'Password', 'storeengine' ); ?> <span class="required">*</span></label>
			<input type="password" id="storeengine-vendor-password" name="password" minlength="8" required>
		</p>

		<p class="storeengine-form-row storeengine-form-row--checkbox">
			<label>
				<input type="checkbox" name="terms_accepted" value="1" class="storeengine-input-check" required>
				<?php esc_html_e( 'I agree to the vendor terms and conditions.', 'storeengine' ); ?> <span class="required">*</span>
			</label>
		</p>

		<p class="storeengine-form-row">
			<button type="submit" class="storeengine-btn storeengine-btn--md storeengine-btn--preset-blue">
				<?php esc_html_e( 'Apply to become a vendor', 'storeengine' ); ?>
			</button>
		</p>
	</form>
</div>
