<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Login state is resolved once by the shortcode (through the
// `storeengine/shortcode/affiliate_registration_is_user_logged_in` filter) and
// passed in. Fall back to evaluating it here only if the template is rendered
// directly, so an overridden template can't hit an undefined variable.
$is_user_logged_in = isset( $is_user_logged_in )
	? (bool) $is_user_logged_in
	: (bool) apply_filters( 'storeengine/shortcode/affiliate_registration_is_user_logged_in', is_user_logged_in(), [] );
$affiliate_pending = isset( $affiliate_pending ) ? (bool) $affiliate_pending : false;

/**
 * Shared affiliate profile fields (website URL, promotional methods, terms).
 * Rendered on both the logged-in "apply" form and the logged-out "register"
 * form so the same profile data is captured either way. All fields required.
 *
 * @var string $website_url_label
 * @var string $website_url_placeholder
 * @var string $promotional_methods_label
 * @var string $promotional_methods_placeholder
 * @var string $terms_label
 * @var string $terms_url
 */
$render_affiliate_profile_fields = static function () use (
	$website_url_label,
	$website_url_placeholder,
	$promotional_methods_label,
	$promotional_methods_placeholder,
	$terms_label,
	$terms_url
) {
	?>
	<div class="storeengine-form-group">
		<?php if ( $website_url_label ) : ?>
			<label class="storeengine-form-group__title" for="affiliate_website_url"><?php echo esc_html( $website_url_label ); ?> <span class="storeengine-form-required" aria-hidden="true">*</span></label>
		<?php endif; ?>
		<input id="affiliate_website_url" type="url" class="storeengine-form-control" name="website_url" value="" placeholder="<?php echo esc_attr( $website_url_placeholder ); ?>" required>
	</div>
	<div class="storeengine-form-group">
		<?php if ( $promotional_methods_label ) : ?>
			<label class="storeengine-form-group__title" for="affiliate_promotional_methods"><?php echo esc_html( $promotional_methods_label ); ?> <span class="storeengine-form-required" aria-hidden="true">*</span></label>
		<?php endif; ?>
		<textarea id="affiliate_promotional_methods" class="storeengine-form-control" name="promotional_methods" rows="3" placeholder="<?php echo esc_attr( $promotional_methods_placeholder ); ?>" required></textarea>
	</div>
	<div class="storeengine-form-group storeengine-form-group--checkbox">
		<label class="storeengine-form-group__checkbox-label" for="affiliate_agree_terms">
			<input id="affiliate_agree_terms" type="checkbox" name="agree_terms" value="1" required>
			<?php
			if ( $terms_url ) {
				printf(
					/* translators: %s: affiliate terms page URL. */
					wp_kses_post( __( 'I agree to the <a href="%s" target="_blank" rel="noopener noreferrer">affiliate program terms &amp; conditions</a>', 'storeengine' ) ),
					esc_url( $terms_url )
				);
			} else {
				echo esc_html( $terms_label );
			}
			?>
			<span class="storeengine-form-required" aria-hidden="true">*</span>
		</label>
	</div>
	<?php
};
?>

<style>
	.storeengine-affiliate-registration-form .storeengine-form-required { color: #e02b2b; }
</style>

<div class="storeengine-logged-in-message">
	<?php if ( $is_user_logged_in ) : ?>
		<?php if ( $affiliate_pending ) : ?>
			<p><?php esc_html_e('Your affiliate registration request is pending approval. We’ll review your application and notify you soon. Thank you for your patience!', 'storeengine'); ?></p>
		<?php elseif ( ! user_can( get_current_user_id(), 'storeengine_affiliate' ) ) : ?>
			<form class="storeengine-affiliate-registration-form" method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<p><?php esc_html_e( 'Earn rewards by sharing our products! Join our affiliate program and start earning commissions for every referral. Fill in a few details below to submit your application.', 'storeengine'); ?></p>
				<input type="hidden" name="action" value="storeengine/apply_for_affiliation">
				<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
				<?php $render_affiliate_profile_fields(); ?>
				<div class="storeengine-form-group">
					<button type="submit" class="storeengine-btn storeengine-btn--bg-blue"><?php esc_html_e( 'Apply for affiliate', 'storeengine' ); ?></button>
				</div>
			</form>
		<?php else : ?>
			<p>
				<?php esc_html_e('You are already an affiliate member! Start earning commissions by promoting our products.', 'storeengine'); ?>
			</p>
			<a href="<?php echo esc_url(\StoreEngine\Utils\Helper::get_account_endpoint_url('affiliate-partner')); ?>"><?php esc_html_e('Affiliate Dashboard', 'storeengine'); ?></a>
		<?php endif; ?>
	<?php else : ?>
		<?php
		if ( isset( $_GET['registration_success'] ) && 'true' === $_GET['registration_success'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="success-message">' . esc_html__( 'Registration successful! We’ve emailed you a link to set your password.', 'storeengine' ) . '</div>';
		}
		?>
		<form id="storeengine_affiliate_registration_form" class="storeengine-affiliate-registration-form storeengine-login-form-wrapper" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
			<input type="hidden" name="action" value="storeengine/register_for_affiliate">

			<?php if ( ! empty( $form_title) ) : ?>
			<h2 class="storeengine-login-form-heading"><?php echo esc_html( $form_title ); ?></h2>
			<?php endif; ?>

			<div class="storeengine-form-row" style="display:flex; gap:16px;">
				<div class="storeengine-form-group" style="flex:1;">
					<?php if ( $first_name_label ) : ?>
						<label class="storeengine-form-group__title" for="affiliate_first_name"><?php echo esc_html( $first_name_label ); ?> <span class="storeengine-form-required" aria-hidden="true">*</span></label>
					<?php endif; ?>
					<input id="affiliate_first_name" type="text" class="storeengine-form-control" name="first_name" value="" placeholder="<?php echo esc_attr( $first_name_placeholder ); ?>" required>
				</div>
				<div class="storeengine-form-group" style="flex:1;">
					<?php if ( $last_name_label ) : ?>
						<label class="storeengine-form-group__title" for="affiliate_last_name"><?php echo esc_html( $last_name_label ); ?> <span class="storeengine-form-required" aria-hidden="true">*</span></label>
					<?php endif; ?>
					<input id="affiliate_last_name" type="text" class="storeengine-form-control" name="last_name" value="" placeholder="<?php echo esc_attr( $last_name_placeholder ); ?>" required>
				</div>
			</div>
			<div class="storeengine-form-group">
				<?php if ( $email_label ) : ?>
					<label class="storeengine-form-group__title" for="affiliate_email"><?php echo esc_html( $email_label ); ?> <span class="storeengine-form-required" aria-hidden="true">*</span></label>
				<?php endif; ?>
				<input id="affiliate_email" type="email" class="storeengine-form-control" name="email" value="" placeholder="<?php echo esc_attr( $email_placeholder ); ?>" required>
			</div>
			<?php $render_affiliate_profile_fields(); ?>
			<?php do_action( 'storeengine/affiliate/templates/registration-form-before-submit' ); ?>
			<div class="storeengine-form-group">
				<button class="storeengine-btn storeengine-btn--bg-blue" type="submit"><?php echo esc_html( $registration_button_label ); ?></button>
			</div>
		</form>
	<?php endif; ?>
</div>
