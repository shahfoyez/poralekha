<?php
/**
 * Forgot password / reset password — single template, two states.
 *
 * Routing:
 *   - No `key` query var          → email-entry form (step 1).
 *   - `key` + `login` + WP says
 *     the pair is valid           → new-password form (step 2).
 *   - `key` present but tampered
 *     or expired                  → "link is invalid" notice + link to step 1.
 *
 * Submissions go through admin-post.php, dispatched by
 * StoreEngine\Post\ForgotPassword (allow_visitor_action: true).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- these are GET-only display branches; the actual writes go through admin-post.php with nonces.

$storeengine_endpoint_url = Helper::get_account_endpoint_url( 'forgot-password' );

$storeengine_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$storeengine_login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

$storeengine_reset_user      = null;
$storeengine_reset_key_error = null;

if ( $storeengine_key && $storeengine_login ) {
	$storeengine_check = check_password_reset_key( $storeengine_key, $storeengine_login );
	if ( is_wp_error( $storeengine_check ) ) {
		$storeengine_reset_key_error = $storeengine_check->get_error_message();
	} else {
		$storeengine_reset_user = $storeengine_check;
	}
}

$storeengine_reset_sent  = isset( $_GET['reset_sent'] ) && '1' === $_GET['reset_sent'];
$storeengine_reset_error = isset( $_GET['reset_error'] ) ? sanitize_key( $_GET['reset_error'] ) : '';

// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>
<div class="storeengine-login-form-wrapper storeengine-forgot-password">
	<?php do_action( 'storeengine/templates/forgot-password/before' ); ?>

	<?php if ( $storeengine_reset_user ) : ?>
		<h2 class="storeengine-login-form-heading"><?php esc_html_e( 'Set a new password', 'storeengine' ); ?></h2>
		<p class="storeengine-form-description"><?php
			/* translators: %s: user login */
			printf( esc_html__( 'Enter a new password for %s below.', 'storeengine' ), '<strong>' . esc_html( $storeengine_reset_user->user_login ) . '</strong>' );
		?></p>

		<?php
		$storeengine_min_length = (int) apply_filters( 'storeengine/auth/min_password_length', 8 );
		if ( 'mismatch' === $storeengine_reset_error ) : ?>
			<p class="storeengine-form-notice storeengine-form-notice--error"><?php esc_html_e( 'The two passwords didn\'t match. Please try again.', 'storeengine' ); ?></p>
		<?php elseif ( 'empty' === $storeengine_reset_error ) : ?>
			<p class="storeengine-form-notice storeengine-form-notice--error"><?php esc_html_e( 'Please fill in both password fields.', 'storeengine' ); ?></p>
		<?php elseif ( 'too_short' === $storeengine_reset_error ) : ?>
			<p class="storeengine-form-notice storeengine-form-notice--error">
				<?php
				printf(
					/* translators: %d: minimum password length */
					esc_html( _n( 'Password must be at least %d character long.', 'Password must be at least %d characters long.', $storeengine_min_length, 'storeengine' ) ),
					(int) $storeengine_min_length
				);
				?>
			</p>
		<?php endif; ?>

		<form class="storeengine-login-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
			<input type="hidden" name="action" value="storeengine/forgot_password/set_password">
			<input type="hidden" name="key" value="<?php echo esc_attr( $storeengine_key ); ?>">
			<input type="hidden" name="login" value="<?php echo esc_attr( $storeengine_login ); ?>">
			<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>

			<div class="storeengine-form-group">
				<label for="pass1" class="storeengine-form-group__title"><?php esc_html_e( 'New password', 'storeengine' ); ?></label>
				<div class="storeengine-password-wrapper">
					<input id="pass1" type="password" class="storeengine-form-control" name="pass1" autocomplete="new-password" placeholder="<?php esc_attr_e( 'New password', 'storeengine' ); ?>" required>
					<button type="button" class="toggle-password" data-toggle="false" aria-label="<?php esc_attr_e( 'Show password', 'storeengine' ); ?>">
						<span class="storeengine-icon storeengine-icon--eye" aria-hidden="true"></span>
					</button>
				</div>
			</div>

			<div class="storeengine-form-group">
				<label for="pass2" class="storeengine-form-group__title"><?php esc_html_e( 'Confirm new password', 'storeengine' ); ?></label>
				<div class="storeengine-password-wrapper">
					<input id="pass2" type="password" class="storeengine-form-control" name="pass2" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Confirm new password', 'storeengine' ); ?>" required>
					<button type="button" class="toggle-password" data-toggle="false" aria-label="<?php esc_attr_e( 'Show password', 'storeengine' ); ?>">
						<span class="storeengine-icon storeengine-icon--eye" aria-hidden="true"></span>
					</button>
				</div>
			</div>

			<div class="storeengine-form-group">
				<button class="storeengine-btn storeengine-btn--bg-blue" type="submit"><?php esc_html_e( 'Save new password', 'storeengine' ); ?></button>
			</div>
		</form>

	<?php elseif ( $storeengine_reset_key_error || ( $storeengine_key && ! $storeengine_login ) ) : ?>
		<h2 class="storeengine-login-form-heading"><?php esc_html_e( 'Reset link is invalid or expired', 'storeengine' ); ?></h2>
		<p class="storeengine-form-description"><?php esc_html_e( 'The password reset link you used can\'t be verified. Reset links expire after a short time and can only be used once. Request a new one below.', 'storeengine' ); ?></p>
		<p><a class="storeengine-btn storeengine-btn--bg-blue" href="<?php echo esc_url( $storeengine_endpoint_url ); ?>"><?php esc_html_e( 'Request a new reset link', 'storeengine' ); ?></a></p>

	<?php else : ?>
		<h2 class="storeengine-login-form-heading"><?php esc_html_e( 'Forgot your password?', 'storeengine' ); ?></h2>
		<p class="storeengine-form-description"><?php esc_html_e( 'Enter the email address tied to your account. If we find a match, we\'ll send you a link to set a new password.', 'storeengine' ); ?></p>

		<?php if ( $storeengine_reset_sent ) : ?>
			<p class="storeengine-form-notice storeengine-form-notice--success"><?php esc_html_e( 'If an account exists with that email, a reset link is on its way. Check your inbox in a few minutes.', 'storeengine' ); ?></p>
		<?php elseif ( 'invalid_email' === $storeengine_reset_error ) : ?>
			<p class="storeengine-form-notice storeengine-form-notice--error"><?php esc_html_e( 'Please enter a valid email address.', 'storeengine' ); ?></p>
		<?php endif; ?>

		<form class="storeengine-login-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
			<input type="hidden" name="action" value="storeengine/forgot_password/request_reset">
			<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>

			<div class="storeengine-form-group">
				<label for="forgot-password-email" class="storeengine-form-group__title"><?php esc_html_e( 'Email address', 'storeengine' ); ?></label>
				<input id="forgot-password-email" type="email" class="storeengine-form-control" name="email" autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'storeengine' ); ?>" required>
			</div>

			<div class="storeengine-form-group">
				<button class="storeengine-btn storeengine-btn--bg-blue" type="submit"><?php esc_html_e( 'Send reset link', 'storeengine' ); ?></button>
			</div>
		</form>

		<div class="storeengine-login-form-info">
			<p><a class="storeengine-form-text-link" href="<?php echo esc_url( storeengine_login_url( Helper::get_dashboard_url() ) ); ?>"><?php esc_html_e( 'Back to sign in', 'storeengine' ); ?></a></p>
		</div>
	<?php endif; ?>

	<?php do_action( 'storeengine/templates/forgot-password/after' ); ?>
</div>
