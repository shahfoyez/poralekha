<?php
/**
 * Storefront authentication REST endpoints.
 *
 * Mirrors the legacy `customer_login` admin-ajax action over REST so the
 * storefront login form (and any headless storefront) can sign a shopper in
 * via cookie auth without round-tripping through admin-ajax.php.
 *
 * Routes:
 *   POST /wp-json/storeengine/v1/auth/login             — sign in (sets the WP auth cookie)
 *   POST /wp-json/storeengine/v1/auth/register          — create account + sign in
 *   POST /wp-json/storeengine/v1/auth/forgot-password   — request reset email
 *   POST /wp-json/storeengine/v1/auth/reset-password    — finalize reset with key+login
 *
 * Uses the standard WP REST nonce (`X-WP-Nonce`) for CSRF protection on
 * same-origin storefronts. Cross-origin headless storefronts can't currently
 * authenticate this way (you can't set the WP auth cookie from another origin) —
 * that's a deliberate limitation; cross-origin clients should use the dedicated
 * application-password REST flow instead.
 *
 * The non-JS form-post handlers (StoreEngine\Post\ForgotPassword,
 * StoreEngine\Post\Register) call the same WP core primitives this controller
 * does — both paths converge on retrieve_password() / wp_create_user() /
 * reset_password() so behavior stays identical regardless of submission style.
 */

namespace StoreEngine\API;

use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StorefrontAuth extends AbstractRestApiController {

	protected $rest_base = 'auth';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/login', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'login' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'username'    => [ 'type' => 'string', 'required' => true ],
					'password'    => [ 'type' => 'string', 'required' => true ],
					'remember'    => [ 'type' => 'boolean', 'default' => false ],
					'redirect_to' => [ 'type' => 'string', 'required' => false, 'format' => 'uri' ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/register', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'register' ],
				'permission_callback' => [ $this, 'registration_open' ],
				'args'                => [
					'email'      => [ 'type' => 'string', 'required' => true, 'format' => 'email' ],
					'password'   => [ 'type' => 'string', 'required' => true ],
					'first_name' => [ 'type' => 'string', 'required' => false ],
					'last_name'  => [ 'type' => 'string', 'required' => false ],
					'auto_login' => [ 'type' => 'boolean', 'default' => true ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/forgot-password', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'forgot_password' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email' => [ 'type' => 'string', 'required' => true, 'format' => 'email' ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/reset-password', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reset_password' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'key'      => [ 'type' => 'string', 'required' => true ],
					'login'    => [ 'type' => 'string', 'required' => true ],
					'password' => [ 'type' => 'string', 'required' => true ],
				],
			],
		] );
	}

	/**
	 * Block registration entirely when WP's "Anyone can register" toggle is off.
	 * Matches the gate `wp_registration_url()` and our login template already
	 * respect, so the REST surface can't quietly bypass site policy.
	 */
	public function registration_open() {
		if ( ! get_option( 'users_can_register' ) ) {
			return new WP_Error( 'storeengine_registration_closed', __( 'New account registration is currently disabled.', 'storeengine' ), [ 'status' => 403 ] );
		}
		return true;
	}

	public function login( WP_REST_Request $request ) {
		$username = sanitize_text_field( (string) $request->get_param( 'username' ) );
		$password = (string) $request->get_param( 'password' );

		if ( '' === $username ) {
			return new WP_Error( 'storeengine_auth_username_required', __( 'Username is required', 'storeengine' ), [ 'status' => 422 ] );
		}
		if ( '' === $password ) {
			return new WP_Error( 'storeengine_auth_password_required', __( 'Password is required', 'storeengine' ), [ 'status' => 422 ] );
		}

		// Wipe any stale auth cookie before attempting a fresh sign-on so a
		// failed login can't leave the previous session in place.
		wp_clear_auth_cookie();

		do_action( 'storeengine/shortcode/before_customer_signon' );

		$user = wp_signon( [
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => (bool) $request->get_param( 'remember' ),
		], is_ssl() );

		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'storeengine_auth_failed', $user->get_error_message(), [ 'status' => 401 ] );
		}

		wp_set_current_user( $user->ID );

		do_action( 'storeengine/shortcode/after_customer_signon' );

		$redirect_to = (string) $request->get_param( 'redirect_to' );
		if ( '' === $redirect_to ) {
			$redirect_to = $user->has_cap( 'manage_options' ) ? admin_url() : Helper::get_dashboard_url();
		}

		// If the site is HTTPS-only, ensure a wp-admin redirect doesn't downgrade.
		if ( is_ssl() && str_contains( $redirect_to, 'wp-admin' ) && str_starts_with( $redirect_to, 'http://' ) ) {
			$redirect_to = preg_replace( '|^http://|', 'https://', $redirect_to );
		}

		return rest_ensure_response( [
			'message'      => esc_html__( 'You have logged in successfully. Redirecting...', 'storeengine' ),
			'redirect_url' => esc_url_raw( wp_validate_redirect( $redirect_to, home_url() ) ),
			'user'         => [
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
			],
		] );
	}

	/**
	 * Create a customer account. Mirrors the auto-create-on-checkout flow at
	 * CheckoutService::create_customer() so usernames are derived the same way
	 * (email-local-part + dedupe counter) and the same `customer_created`
	 * action fires — which the NewUserNotification email already listens to.
	 *
	 * `auto_login` defaults true. Setting it false is the explicit opt-out for
	 * back-office workflows that create accounts on behalf of someone else.
	 */
	public function register( WP_REST_Request $request ) {
		$email      = sanitize_email( (string) $request->get_param( 'email' ) );
		$password   = (string) $request->get_param( 'password' );
		$first_name = sanitize_text_field( (string) $request->get_param( 'first_name' ) );
		$last_name  = sanitize_text_field( (string) $request->get_param( 'last_name' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'storeengine_register_invalid_email', __( 'Please enter a valid email address.', 'storeengine' ), [ 'status' => 422 ] );
		}
		if ( '' === $password ) {
			return new WP_Error( 'storeengine_register_password_required', __( 'Please choose a password.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$min_length = (int) apply_filters( 'storeengine/auth/min_password_length', 8 );
		if ( strlen( $password ) < $min_length ) {
			return new WP_Error(
				'storeengine_register_password_too_short',
				sprintf(
					/* translators: %d: minimum password length */
					_n( 'Password must be at least %d character long.', 'Password must be at least %d characters long.', $min_length, 'storeengine' ),
					$min_length
				),
				[ 'status' => 422 ]
			);
		}

		if ( email_exists( $email ) ) {
			// Deliberately specific — for *registration* (where you know your
			// own email), surfacing existence is fine and far more useful than
			// a generic "something went wrong". The enumeration concern only
			// applies to the forgot-password flow, where it leaks third-party
			// accounts.
			return new WP_Error( 'storeengine_register_email_taken', __( 'An account with this email already exists. Try signing in instead.', 'storeengine' ), [ 'status' => 409 ] );
		}

		$username = $this->derive_unique_username( $email );

		$userdata = apply_filters( 'storeengine/auth/register_userdata', [
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'role'         => 'storeengine_customer',
			'display_name' => trim( $first_name . ' ' . $last_name ) ?: $username,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
		], $request );

		$user_id = wp_insert_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'storeengine_register_failed', $user_id->get_error_message(), [ 'status' => 422 ] );
		}

		// Reuse the existing checkout-side hook so NewUserNotification email
		// (the welcome-with-credentials mail) fires on REST signup too.
		do_action( 'storeengine/checkout/customer_created', $user_id, $userdata );

		$redirect_to = Helper::get_dashboard_url();

		if ( $request->get_param( 'auto_login' ) ) {
			wp_clear_auth_cookie();
			$user = wp_signon( [
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => true,
			], is_ssl() );

			if ( ! is_wp_error( $user ) ) {
				wp_set_current_user( $user->ID );
			}
		}

		return rest_ensure_response( [
			'message'      => esc_html__( 'Your account has been created.', 'storeengine' ),
			'redirect_url' => esc_url_raw( $redirect_to ),
			'user'         => [
				'id'    => (int) $user_id,
				'email' => $email,
				'login' => $username,
			],
		] );
	}

	/**
	 * Step 1 of password reset: send the branded email.
	 *
	 * Always returns the same generic response whether or not the email
	 * matches a real account — leaking existence here is the classic
	 * enumeration mistake the form-post handler also takes care to avoid.
	 */
	public function forgot_password( WP_REST_Request $request ) {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'storeengine_forgot_invalid_email', __( 'Please enter a valid email address.', 'storeengine' ), [ 'status' => 422 ] );
		}

		// retrieve_password() fires `retrieve_password_notification_email`,
		// which the PasswordReset email class hooks to send the branded HTML
		// version with a link back into our in-dashboard reset form.
		retrieve_password( $email );

		return rest_ensure_response( [
			'message' => esc_html__( 'If an account exists with that email, a reset link is on its way.', 'storeengine' ),
		] );
	}

	/**
	 * Step 2 of password reset: validate the {key, login} from the email link
	 * and apply the new password. `reset_password()` fires the standard WP
	 * `password_reset` action so any integrations stay informed.
	 */
	public function reset_password( WP_REST_Request $request ) {
		$key      = sanitize_text_field( (string) $request->get_param( 'key' ) );
		$login    = sanitize_text_field( (string) $request->get_param( 'login' ) );
		$password = (string) $request->get_param( 'password' );

		if ( '' === $key || '' === $login ) {
			return new WP_Error( 'storeengine_reset_invalid_link', __( 'This reset link is missing required information.', 'storeengine' ), [ 'status' => 422 ] );
		}
		if ( '' === $password ) {
			return new WP_Error( 'storeengine_reset_password_required', __( 'Please choose a new password.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$min_length = (int) apply_filters( 'storeengine/auth/min_password_length', 8 );
		if ( strlen( $password ) < $min_length ) {
			return new WP_Error(
				'storeengine_reset_password_too_short',
				sprintf(
					/* translators: %d: minimum password length */
					_n( 'Password must be at least %d character long.', 'Password must be at least %d characters long.', $min_length, 'storeengine' ),
					$min_length
				),
				[ 'status' => 422 ]
			);
		}

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return new WP_Error( 'storeengine_reset_expired', __( 'This reset link is invalid or has expired.', 'storeengine' ), [ 'status' => 410 ] );
		}

		reset_password( $user, $password );

		return rest_ensure_response( [
			'message'      => esc_html__( 'Your password has been updated. You can now sign in with the new password.', 'storeengine' ),
			'redirect_url' => esc_url_raw( add_query_arg( 'password_updated', '1', Helper::get_dashboard_url() ) ),
		] );
	}

	/**
	 * Build a unique username from an email's local part, dedup'ing with a
	 * numeric suffix if needed. Same algorithm as
	 * CheckoutService::create_customer() so the two registration paths produce
	 * identical usernames for the same email.
	 */
	protected function derive_unique_username( string $email ): string {
		$base    = strstr( $email, '@', true ) ?: 'user';
		$username = $base;
		$counter  = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			$counter++;
		}
		return $username;
	}
}
