<?php
/**
 * Iframe checkout host. Renders the React mount div + script and nothing else;
 * the React app owns the entire flow (form, payment, success).
 *
 * @var string $parent_origin Allowed parent origin for postMessage (consumed by
 *                            the React app via the data-parent-origin attribute).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only session hint for the iframe; no state change.
$storeengine_session_id = isset( $_GET['sid'] ) ? sanitize_text_field( wp_unslash( $_GET['sid'] ) ) : '';

// Signal that this iframe *is* a checkout context BEFORE firing the enqueue
// lifecycle. Payment gateways (Stripe, PayPal, …) gate their script enqueue on
// `Helper::is_checkout()`; without this the iframe is just a normal front-end
// request and every gateway silently no-ops (PayPal SDK / Stripe.js never load).
if ( ! defined( 'STOREENGINE_CHECKOUT' ) ) {
	define( 'STOREENGINE_CHECKOUT', true );
}
add_filter( 'storeengine_is_checkout', '__return_true' );

// Fire the standard `wp_enqueue_scripts` lifecycle so payment gateway plugins
// (and the base plugin's frontend script registrar) can hook in and enqueue
// their assets. The iframe never calls wp_head(), so this action wouldn't
// otherwise fire.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing the WordPress core "wp_enqueue_scripts" lifecycle action so gateway assets enqueue inside the iframe.
do_action( 'wp_enqueue_scripts' );

wp_enqueue_script( 'storeengine-instant-checkout-app' );
wp_enqueue_style( 'storeengine-instant-checkout-app' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html__( 'Checkout', 'storeengine' ); ?></title>
	<style>
		html, body { margin: 0; padding: 0; background: transparent; }
		body { padding: 12px; }
	</style>
	<?php wp_print_styles( [ 'storeengine-instant-checkout-app' ] ); ?>
	<?php wp_print_head_scripts(); ?>
</head>
<body>
<div
	data-storeengine-checkout
	data-store-url="<?php echo esc_url( home_url() ); ?>"
	data-rest-url="<?php echo esc_url( rest_url() ); ?>"
	data-product-id="0"
	data-session-id="<?php echo esc_attr( $storeengine_session_id ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	data-parent-origin="<?php echo esc_attr( $parent_origin ); ?>"
></div>
<?php wp_print_footer_scripts(); ?>
</body>
</html>
