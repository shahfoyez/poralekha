<?php
/**
 * Login Template.
 *
 * Template variables:
 *
 * @var string $form_title Form title text
 * @var string $username_label Label for the username field
 * @var string $username_placeholder Placeholder for the username field
 * @var string $password_label Label for the password field
 * @var string $password_placeholder Placeholder for the password field
 * @var string $remember_label Label for remembered checkbox
 * @var string $login_button_label Label for login button
 * @var string $reset_password_label Label for reset password link
 * @var boolean $show_logged_in_message Whether to show a logged-in message
 * @var string $register_url URL for registration
 * @var string $login_redirect_url URL to redirect after login
 * @var string $logout_redirect_url URL to redirect after logout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="storeengine-login-form-wrapper">
	<?php do_action( 'storeengine/templates/shortcode/before_login' ); ?>
	<?php
	// Customer just completed the password-reset flow — confirm the new
	// credentials are live so they don't second-guess whether the form
	// submission worked when they see a fresh login screen.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state mutation here.
	if ( isset( $_GET['password_updated'] ) && '1' === $_GET['password_updated'] ) :
		?>
		<p class="storeengine-form-notice storeengine-form-notice--success">
			<?php esc_html_e( 'Your password has been updated. Sign in with the new password below.', 'storeengine' ); ?>
		</p>
	<?php endif; ?>
	<h2 class="storeengine-login-form-heading"><?php echo esc_html( $form_title ); ?></h2>
	<form id="storeengine_login_form" class="storeengine-login-form" action="#" method="post">
		<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
		<div class="storeengine-form-group">
			<label for="username" class="storeengine-form-group__title"><?php echo esc_html( $username_label ); ?></label>
			<?php
			// Pre-fill the username when the shopper arrived from the checkout
			// "email already registered" prompt (see storeengine_checkout_login_url()),
			// so they don't have to retype the address they just entered.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill, no state change.
			$storeengine_login_prefill = isset( $_GET['se_email'] ) ? sanitize_email( wp_unslash( $_GET['se_email'] ) ) : '';
			?>
			<input id="username" type="text" class="storeengine-form-control" name="username" value="<?php echo esc_attr( $storeengine_login_prefill ); ?>" placeholder="<?php echo esc_attr( $username_placeholder ); ?>">
		</div>
		<div class="storeengine-form-group">
			<label for="password" class="storeengine-form-group__title"><?php echo esc_html( $password_label ); ?></label>
			<div class="storeengine-password-wrapper">
				<input id="password" type="password" class="storeengine-form-control" name="password" placeholder="<?php echo esc_attr( $password_placeholder ); ?>">
				<button type="button" class="toggle-password" data-toggle="false" aria-label="<?php echo esc_attr( $password_label ); ?>">
					<span class="storeengine-icon storeengine-icon--eye" aria-hidden="true"></span>
				</button>
			</div>
		</div>
		<div class="storeengine-form-group storeengine-d-flex storeengine-flex-row storeengine-justify-content-between">
			<div class="storeengine-form-group__inner storeengine-d-flex storeengine-flex-row">
				<input name="rememberme" type="checkbox" id="rememberme" value="forever" class="storeengine-input-check" <?php checked( ! empty( $_POST['rememberme'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>>
				<label for="rememberme"><?php echo esc_html( $remember_label ); ?></label>
			</div>
			<div class="storeengine-form-group__inner">
				<a class="storeengine-form-text-link" href="<?php echo esc_url( \StoreEngine\Utils\Helper::get_account_endpoint_url( 'forgot-password' ) ); ?>"><?php echo esc_html( $reset_password_label ); ?></a>
			</div>
		</div>
		<?php do_action( 'storeengine/templates/login_form_before_submit' ); ?>
		<div class="storeengine-form-group">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url_raw( isset( $_GET['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_GET['redirect_to'] ) ) : $login_redirect_url ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>"/>

			<?php if ( isset( $_GET['action'] ) && '' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>"/>
			<?php } ?>

			<button class="storeengine-btn storeengine-btn--bg-blue" type="submit"><?php echo esc_html( $login_button_label ); ?></button>
		</div>
	</form>
	<?php if ( $register_url ) { ?>
		<div class="storeengine-login-form-info">
			<p><?php esc_html_e( 'Don\'t have an account?', 'storeengine' ); ?> <a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register Now', 'storeengine' ); ?></a></p>
			<?php
			// Surface a hint when WP's "Anyone can register" toggle is off
			// so admins viewing the login page understand why clicking
			// through lands on the "Registration is closed" notice.
			// Customers see the link either way — the register endpoint
			// handles the closed state with a clear message.
			if ( ! get_option( 'users_can_register' ) && current_user_can( 'manage_options' ) ) :
				?>
				<p class="storeengine-login-form-info__admin-hint">
					<small>
						<?php
						printf(
							/* translators: %s: link to wp-admin Settings → General */
							esc_html__( 'Admin note: new account registration is currently disabled site-wide. Enable it under %s.', 'storeengine' ),
							'<a href="' . esc_url( admin_url( 'options-general.php#users_can_register' ) ) . '">' . esc_html__( 'Settings → General → Membership', 'storeengine' ) . '</a>'
						);
						?>
					</small>
				</p>
			<?php endif; ?>
		</div>
	<?php } ?>
	<?php do_action( 'storeengine/templates/shortcode/after_login' ); ?>
</div>
