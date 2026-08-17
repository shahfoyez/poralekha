<?php

namespace StoreEngine\Addons\EmbeddableCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams the slim Quick Checkout SDK to external sites at /se-embed/v1/sdk.js
 * so partners can drop a single <script> tag without having to host the asset
 * themselves. The bundle itself is built by the core webpack pipeline (entry
 * `embed-sdk`) and lives in the storeengine plugin's `assets/build/` directory.
 */
class Routes {

	const QUERY_VAR  = 'se_embed_sdk';
	const REWRITE_OPT = 'storeengine_embeddable_checkout_rewrite_v';
	const REWRITE_VER = '1';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_serve_sdk' ], 1 );
	}

	public static function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^se-embed/v1/sdk\\.js$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );

		if ( self::REWRITE_VER !== get_option( self::REWRITE_OPT ) ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_OPT, self::REWRITE_VER, false );
		}
	}

	public static function maybe_serve_sdk(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! get_query_var( self::QUERY_VAR ) && empty( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		$file = STOREENGINE_ASSETS_DIR_PATH . sprintf( 'build/embed-sdk.%s.js', STOREENGINE_VERSION );
		if ( ! is_readable( $file ) ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo '/* StoreEngine Embed SDK not built. Run `npm run build`. */';
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Access-Control-Allow-Origin: *' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.Security.EscapeOutput.OutputNotEscaped
		readfile( $file );
		exit;
	}
}
