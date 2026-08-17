<?php
/**
 * Vendor — store profile self-service.
 *
 * @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_saved      = false;
$storeengine_is_locked  = $vendor->is_approved() || 'suspended' === $vendor->get_status();
$storeengine_user       = get_userdata( $vendor->get_user_id() );

if ( isset( $_POST['storeengine_vendor_profile_nonce'] )
	&& wp_verify_nonce( sanitize_key( $_POST['storeengine_vendor_profile_nonce'] ), 'storeengine_vendor_profile' )
) {
	if ( $storeengine_is_locked ) {
		// After approval, only these three fields are editable.
		$storeengine_store_description = isset( $_POST['store_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['store_description'] ) ) : '';
		$storeengine_phone             = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$storeengine_address           = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';

		$vendor->set_store_description( $storeengine_store_description );
		$vendor->set_phone( $storeengine_phone );
		$vendor->set_address( $storeengine_address );
	} else {
		// Pending vendors can still edit everything (typos before admin review).
		$storeengine_store_name        = isset( $_POST['store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['store_name'] ) ) : '';
		$storeengine_payout_email      = isset( $_POST['payout_email'] ) ? sanitize_email( wp_unslash( $_POST['payout_email'] ) ) : '';
		$storeengine_first_name        = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$storeengine_last_name         = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$storeengine_phone             = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$storeengine_address           = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
		$storeengine_store_description = isset( $_POST['store_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['store_description'] ) ) : '';
		$storeengine_business_type     = isset( $_POST['business_type'] ) ? sanitize_key( wp_unslash( $_POST['business_type'] ) ) : 'individual';

		if ( $storeengine_store_name ) {
			$vendor->set_store_name( $storeengine_store_name );
		}
		if ( $storeengine_payout_email && is_email( $storeengine_payout_email ) ) {
			$vendor->set_payout_email( $storeengine_payout_email );
		}
		$vendor->set_first_name( $storeengine_first_name );
		$vendor->set_last_name( $storeengine_last_name );
		$vendor->set_phone( $storeengine_phone );
		$vendor->set_address( $storeengine_address );
		$vendor->set_store_description( $storeengine_store_description );
		$vendor->set_business_type( $storeengine_business_type );

		if ( $storeengine_user && $storeengine_user->ID ) {
			wp_update_user( [
				'ID'         => $storeengine_user->ID,
				'first_name' => $storeengine_first_name,
				'last_name'  => $storeengine_last_name,
			] );
		}
	}

	$vendor->save();
	$storeengine_saved = true;
	$storeengine_user  = get_userdata( $vendor->get_user_id() );
}

$storeengine_store_url = $vendor->is_approved()
	? \StoreEngine\Addons\MultiVendor\StorePage::url_for( $vendor )
	: '';
?>
<div class="storeengine-frontend-dashboard-page storeengine-vendor-profile">
	<?php // Title + "View public store" action render in the shared page-header. ?>
	<?php if ( $storeengine_saved ) : ?>
		<div class="storeengine-notice storeengine-notice--success">
			<?php esc_html_e( 'Saved.', 'storeengine' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $storeengine_is_locked ) : ?>
		<div class="storeengine-notice storeengine-notice--info">
			<?php esc_html_e( 'Your store is approved. You can update your store description, phone, and address. To change other details (store name, payout email, legal name) please contact support.', 'storeengine' ); ?>
		</div>
	<?php endif; ?>

	<form method="post" class="storeengine-edit-account-form">
		<?php wp_nonce_field( 'storeengine_vendor_profile', 'storeengine_vendor_profile_nonce' ); ?>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="store_name"><?php esc_html_e( 'Store name', 'storeengine' ); ?></label>
				<input type="text" id="store_name" name="store_name" value="<?php echo esc_attr( $vendor->get_store_name() ); ?>"<?php disabled( $storeengine_is_locked ); ?>>
				<?php if ( $storeengine_is_locked ) : ?>
					<small class="storeengine-form__hint"><?php esc_html_e( 'Locked after approval. Contact support to change.', 'storeengine' ); ?></small>
				<?php endif; ?>
			</div>
		</div>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="store_description"><?php esc_html_e( 'About your store', 'storeengine' ); ?></label>
				<textarea id="store_description" name="store_description" rows="4" maxlength="1000"><?php echo esc_textarea( $vendor->get_store_description() ); ?></textarea>
			</div>
		</div>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="store_slug"><?php esc_html_e( 'Store URL slug', 'storeengine' ); ?></label>
				<input type="text" id="store_slug" value="<?php echo esc_attr( $vendor->get_store_slug() ); ?>" disabled>
				<small class="storeengine-form__hint"><?php esc_html_e( 'Slug is fixed once approved. Contact support to change it.', 'storeengine' ); ?></small>
			</div>
		</div>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="first_name"><?php esc_html_e( 'First name', 'storeengine' ); ?></label>
				<input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $storeengine_user ? $storeengine_user->first_name : $vendor->get_first_name() ); ?>"<?php disabled( $storeengine_is_locked ); ?>>
				<?php if ( $storeengine_is_locked ) : ?>
					<small class="storeengine-form__hint"><?php esc_html_e( 'Locked after approval.', 'storeengine' ); ?></small>
				<?php endif; ?>
			</div>
		</div>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="last_name"><?php esc_html_e( 'Last name', 'storeengine' ); ?></label>
				<input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $storeengine_user ? $storeengine_user->last_name : $vendor->get_last_name() ); ?>"<?php disabled( $storeengine_is_locked ); ?>>
			</div>
		</div>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="phone"><?php esc_html_e( 'Phone', 'storeengine' ); ?></label>
				<input type="tel" id="phone" name="phone" value="<?php echo esc_attr( $vendor->get_phone() ); ?>">
			</div>
		</div>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="address"><?php esc_html_e( 'Address', 'storeengine' ); ?></label>
				<textarea id="address" name="address" rows="3"><?php echo esc_textarea( $vendor->get_address() ); ?></textarea>
			</div>
		</div>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label><?php esc_html_e( 'Business type', 'storeengine' ); ?></label>
				<label class="storeengine-radio">
					<input type="radio" name="business_type" value="individual" <?php checked( $vendor->get_business_type(), 'individual' ); ?><?php disabled( $storeengine_is_locked ); ?>>
					<?php esc_html_e( 'Individual', 'storeengine' ); ?>
				</label>
				<label class="storeengine-radio">
					<input type="radio" name="business_type" value="company" <?php checked( $vendor->get_business_type(), 'company' ); ?><?php disabled( $storeengine_is_locked ); ?>>
					<?php esc_html_e( 'Company', 'storeengine' ); ?>
				</label>
				<?php if ( $storeengine_is_locked ) : ?>
					<small class="storeengine-form__hint"><?php esc_html_e( 'Locked after approval.', 'storeengine' ); ?></small>
				<?php endif; ?>
			</div>
		</div>

		<div class="storeengine-form-group">
			<div class="storeengine-form__inner">
				<label for="payout_email"><?php esc_html_e( 'Payout email', 'storeengine' ); ?></label>
				<input type="email" id="payout_email" name="payout_email" value="<?php echo esc_attr( $vendor->get_payout_email() ); ?>"<?php disabled( $storeengine_is_locked ); ?>>
				<?php if ( $storeengine_is_locked ) : ?>
					<small class="storeengine-form__hint"><?php esc_html_e( 'Locked after approval.', 'storeengine' ); ?></small>
				<?php endif; ?>
			</div>
		</div>

		<button type="submit" class="storeengine-btn storeengine-btn--md storeengine-btn--preset-blue">
			<?php esc_html_e( 'Save', 'storeengine' ); ?>
		</button>
	</form>
</div>
