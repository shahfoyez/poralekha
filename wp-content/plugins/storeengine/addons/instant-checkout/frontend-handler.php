<?php

namespace StoreEngine\Addons\InstantCheckout;

use StoreEngine;
use StoreEngine\Addons\InstantCheckout\Api\Session;
use StoreEngine\API\Checkout as CoreCheckout;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the iframe checkout host page at /?se_checkout=1&sid=…
 *
 * Cross-origin embeds (Embeddable Checkout addon) use a separate /se-embed/v1/sdk.js
 * route to deliver the slim SDK to external sites; the iframe URL itself is served
 * from this handler regardless of which auth path created the session.
 */
class FrontendHandler {

	const QUERY_VAR   = 'se_checkout';
	const REWRITE_OPT = 'storeengine_instant_checkout_rewrite_v';
	const REWRITE_VER = '5';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_request' ], 5 );
	}

	public static function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = 'sid';

		return $vars;
	}

	public static function add_rewrite_rules() {
		// One-time flush after addon activation/upgrade.
		if ( self::REWRITE_VER !== get_option( self::REWRITE_OPT ) ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_OPT, self::REWRITE_VER, false );
		}
	}

	public static function handle_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$session_id = isset( $_GET['sid'] ) ? sanitize_text_field( wp_unslash( $_GET['sid'] ) ) : '';
		if ( ! $session_id ) {
			self::abort( 400, __( 'Missing session id.', 'storeengine' ) );
		}

		$session = Session::consume_session( $session_id );
		if ( ! $session ) {
			self::abort( 404, __( 'Checkout session expired or not found.', 'storeengine' ) );
		}

		$origin    = ! empty( $session['origin'] ) ? $session['origin'] : '';
		$ancestors = $origin ? esc_url_raw( $origin ) . " 'self'" : "'self'";
		header( 'Content-Security-Policy: frame-ancestors ' . $ancestors );

		StoreEngine::init()->load_cart();
		$cart = Helper::cart();
		if ( $cart ) {
			CoreCheckout::hydrate_cart_from_session( $cart, $session );
		}

		add_filter( 'show_admin_bar', '__return_false' );
		status_header( 200 );
		nocache_headers();
		self::render_template( $session );
		exit;
	}

	protected static function render_template( array $session ): void {
		$template = STOREENGINE_INSTANT_CHECKOUT_PATH . 'templates/embedded.php';
		if ( ! is_readable( $template ) ) {
			self::abort( 500, __( 'Instant Checkout template missing.', 'storeengine' ) );
		}

		// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$parent_origin = ! empty( $session['origin'] ) ? $session['origin'] : home_url( '/' );

		include $template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile
	}

	protected static function abort( int $code, string $message ): void {
		status_header( $code );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		printf(
			'<!doctype html><meta charset="utf-8"><title>%1$s</title><body style="font-family:system-ui;padding:24px;color:#222"><h2>%1$s</h2><p>%2$s</p>',
			esc_html__( 'Checkout unavailable', 'storeengine' ),
			esc_html( $message )
		);
		exit;
	}
}
