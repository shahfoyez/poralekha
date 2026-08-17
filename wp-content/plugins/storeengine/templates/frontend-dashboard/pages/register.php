<?php
/**
 * Customer registration form.
 *
 * Two states:
 *   - users_can_register is OFF  → "registration closed" notice.
 *   - users_can_register is ON   → full form (email, password, optional name).
 *
 * Submits to admin-post.php which dispatches to StoreEngine\Post\Register.
 * JS clients should POST to /wp-json/storeengine/v1/auth/register instead —
 * both paths converge on wp_insert_user() + the `customer_created` action so
 * behavior stays identical regardless of submission style.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- these are GET-only display branches; the actual write goes through admin-post.php with a nonce.

$storeengine_endpoint_url    = Helper::get_account_endpoint_url( 'register' );
$storeengine_registration_on = (bool) get_option( 'users_can_register' );

$storeengine_register_error = isset( $_GET['register_error'] ) ? sanitize_key( $_GET['register_error'] ) : '';
$storeengine_login_url      = function_exists( 'storeengine_login_url' )
	? storeengine_login_url( Helper::get_dashboard_url() )
	: wp_login_url( Helper::get_dashboard_url() );

// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>
<div class="storeengine-login-form-wrapper storeengine-register">
	<?php do_action( 'storeengine/templates/register/before' ); ?>

	<?php if ( ! $storeengine_registration_on ) : ?>
		<h2 class="storeengine-login-form-heading"><?php esc_html_e( 'Registration is closed', 'storeengine' ); ?></h2>
		<p><?php esc_html_e( 'New account signup is currently disabled on this store.', 'storeengine' ); ?></p>
		<p><a class="storeengine-form-text-link" href="<?php echo esc_url( $storeengine_login_url ); ?>"><?php esc_html_e( 'Back to sign in', 'storeengine' ); ?></a></p>

	<?php else : ?>
		<h2 class="storeengine-login-form-heading"><?php esc_html_e( 'Create an account', 'storeengine' ); ?></h2>

		<?php
		$storeengine_min_length = (int) apply_filters( 'storeengine/auth/min_password_length', 8 );
		switch ( $storeengine_register_error ) {
			case 'invalid_email':
				echo '<p class="storeengine-form-notice storeengine-form-notice--error">' . esc_html__( 'Please enter a valid email address.', 'storeengine' ) . '</p>';
				break;
			case 'password':
				echo '<p class="storeengine-form-notice storeengine-form-notice--error">' . esc_html__( 'Please choose a password.', 'storeengine' ) . '</p>';
				break;
			case 'too_short':
				echo '<p class="storeengine-form-notice storeengine-form-notice--error">';
				printf(
					/* translators: %d: minimum password length */
					esc_html( _n( 'Password must be at least %d character long.', 'Password must be at least %d characters long.', $storeengine_min_length, 'storeengine' ) ),
					(int) $storeengine_min_length
				);
				echo '</p>';
				break;
			case 'email_taken':
				echo '<p class="storeengine-form-notice storeengine-form-notice--error">' . esc_html__( 'An account with this email already exists. Try signing in instead.', 'storeengine' ) . '</p>';
				break;
			case 'failed':
				echo '<p class="storeengine-form-notice storeengine-form-notice--error">' . esc_html__( 'We couldn\'t create your account. Please try again.', 'storeengine' ) . '</p>';
				break;
		}
		?>

		<form id="storeengine_register_form" class="storeengine-login-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
			<input type="hidden" name="action" value="storeengine/auth/register">
			<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>

			<div class="storeengine-form-group">
				<label for="register-email" class="storeengine-form-group__title"><?php esc_html_e( 'Email address', 'storeengine' ); ?> <span class="storeengine-required" aria-hidden="true">*</span></label>
				<input id="register-email" type="email" class="storeengine-form-control" name="email" autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'storeengine' ); ?>" required>
			</div>

			<div class="storeengine-form-row">
				<div class="storeengine-form-group">
					<label for="register-first-name" class="storeengine-form-group__title"><?php esc_html_e( 'First name', 'storeengine' ); ?></label>
					<input id="register-first-name" type="text" class="storeengine-form-control" name="first_name" autocomplete="given-name" placeholder="<?php esc_attr_e( 'First name', 'storeengine' ); ?>">
				</div>

				<div class="storeengine-form-group">
					<label for="register-last-name" class="storeengine-form-group__title"><?php esc_html_e( 'Last name', 'storeengine' ); ?></label>
					<input id="register-last-name" type="text" class="storeengine-form-control" name="last_name" autocomplete="family-name" placeholder="<?php esc_attr_e( 'Last name', 'storeengine' ); ?>">
				</div>
			</div>

			<div class="storeengine-form-group">
				<label for="register-password" class="storeengine-form-group__title"><?php esc_html_e( 'Password', 'storeengine' ); ?> <span class="storeengine-required" aria-hidden="true">*</span></label>
				<div class="storeengine-password-wrapper">
					<input id="register-password" type="password" class="storeengine-form-control" name="password" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Password', 'storeengine' ); ?>" required>
					<button type="button" class="toggle-password" data-toggle="false" aria-label="<?php esc_attr_e( 'Show password', 'storeengine' ); ?>">
						<span class="storeengine-icon storeengine-icon--eye" aria-hidden="true"></span>
					</button>
				</div>
			</div>

			<?php do_action( 'storeengine/templates/register/form_before_submit' ); ?>

			<div class="storeengine-form-group">
				<button class="storeengine-btn storeengine-btn--bg-blue" type="submit"><?php esc_html_e( 'Create account', 'storeengine' ); ?></button>
			</div>
		</form>

		<div class="storeengine-login-form-info">
			<p><?php esc_html_e( 'Already have an account?', 'storeengine' ); ?> <a class="storeengine-form-text-link" href="<?php echo esc_url( $storeengine_login_url ); ?>"><?php esc_html_e( 'Sign in', 'storeengine' ); ?></a></p>
		</div>
	<?php endif; ?>

	<?php do_action( 'storeengine/templates/register/after' ); ?>
</div>
