<?php
/**
 * Shortcode → Block bridge (StoreEngine core).
 *
 * StoreEngine owns the bridge: a generic `storeengine/shortcode` block that
 * renders ANY registered shortcode with real editor controls, available with
 * just StoreEngine active (no page-builder dependency). aBlocks, when installed,
 * layers styled native blocks on top and offers a one-click Convert — so the
 * block doubles as a promotion for aBlocks.
 *
 * Plugins register their shortcodes via storeengine_register_shortcode_block() or the
 * `storeengine_shortcode_block_registry` filter.
 *
 * @package StoreEngine\Blocks
 */

namespace StoreEngine\Blocks {

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class Bridge {

		const BLOCK      = 'storeengine/shortcode';
		const HANDLE     = 'storeengine-blocks-editor';
		const JS_GLOBAL  = 'StoreEngineShortcodeBlock';

		public static function init() {
			$self = new self();

			add_action( 'init', [ $self, 'register_block' ], 20 );
			add_action( 'enqueue_block_editor_assets', [ $self, 'enqueue_editor' ] );
			add_action( 'enqueue_block_assets', [ $self, 'enqueue_preview_styles' ] );
			add_action( 'rest_api_init', [ $self, 'register_rest' ] );
		}

		/**
		 * Load StoreEngine's front-end stylesheet into the block editor canvas so
		 * the server-rendered shortcode preview looks the same as the front end.
		 *
		 * The editor canvas is an iframe; only `enqueue_block_assets` reaches inside
		 * it (`enqueue_block_editor_assets` targets the outer editor chrome). We gate
		 * on is_admin() so this fires only in the editor — the front end already
		 * loads these styles on the store pages that need them. Independent of
		 * aBlocks: the core block must preview correctly with just StoreEngine active.
		 */
		public function enqueue_preview_styles() {
			if ( ! is_admin() ) {
				return;
			}

			$assets = new \StoreEngine\Assets();

			wp_enqueue_style(
				'storeengine-frontend-icon',
				STOREENGINE_ASSETS_URI . 'library/icons/storeengine-icons.css',
				[ 'wp-components' ],
				filemtime( STOREENGINE_ASSETS_DIR_PATH . 'library/icons/storeengine-icons.css' ),
				'all'
			);

			wp_enqueue_style(
				'storeengine-frontend-style',
				STOREENGINE_ASSETS_URI . 'build/frontend.css',
				[],
				filemtime( STOREENGINE_ASSETS_DIR_PATH . 'build/frontend.css' ),
				'all'
			);
			wp_add_inline_style( 'storeengine-frontend-style', $assets->get_dynamic_css() );
		}

		/**
		 * Build a `storeengine/shortcode` block comment for seeding into
		 * post_content — the recommended replacement for a raw `[shortcode]` or
		 * legacy `<!-- wp:shortcode -->[…]` block. Renders identically on the front
		 * end while giving editor controls and one-click Convert to aBlocks.
		 *
		 * @param string $id      Descriptor id (owner/tag), e.g. "storeengine/storeengine_dashboard".
		 * @param array  $atts    Optional shortcode attributes.
		 * @param string $content Optional inner content for content-supporting shortcodes.
		 */
		public static function block( string $id, array $atts = [], string $content = '' ): string {
			$data = [ 'shortcode' => $id ];
			if ( ! empty( $atts ) ) {
				$data['atts'] = $atts;
			}
			if ( '' !== $content ) {
				$data['content'] = $content;
			}

			return '<!-- wp:' . self::BLOCK . ' ' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
		}

		/* -------------------------------------------------------------- */
		/* Block registration + render                                     */
		/* -------------------------------------------------------------- */

		public function register_block() {
			// Register the editor script handle so block.json's editorScript can
			// enqueue it (and we can localize the registry onto it).
			$asset_file = STOREENGINE_ASSETS_DIR_PATH . 'build/blocks.' . STOREENGINE_VERSION . '.js';
			$asset_meta = STOREENGINE_ASSETS_DIR_PATH . 'build/blocks.' . STOREENGINE_VERSION . '.asset.php';
			$deps       = file_exists( $asset_meta )
				? ( include $asset_meta )['dependencies']
				: [ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ];

			wp_register_script(
				self::HANDLE,
				STOREENGINE_ASSETS_URI . 'build/blocks.' . STOREENGINE_VERSION . '.js',
				$deps,
				file_exists( $asset_file ) ? filemtime( $asset_file ) : STOREENGINE_VERSION,
				true
			);

			// Editor styles for the block (promo card, picker, field labels).
			$style_file = STOREENGINE_ASSETS_DIR_PATH . 'build/blocks.css';
			if ( file_exists( $style_file ) ) {
				wp_register_style(
					self::HANDLE,
					STOREENGINE_ASSETS_URI . 'build/blocks.css',
					[ 'wp-components' ],
					filemtime( $style_file )
				);
			}

			register_block_type(
				STOREENGINE_INCLUDES_DIR_PATH . 'blocks/block.json',
				[ 'render_callback' => [ $this, 'render' ] ]
			);
		}

		/**
		 * Server render: rebuild a minimal shortcode (only non-default atts, each
		 * coerced by its sanitize hint) and delegate to do_shortcode.
		 */
		public function render( $attributes, $content = '' ): string {
			$id = isset( $attributes['shortcode'] ) ? (string) $attributes['shortcode'] : '';
			if ( '' === $id ) {
				return '';
			}

			$descriptor = Registry::instance()->get( $id );
			if ( $descriptor ) {
				return do_shortcode( $this->build_shortcode( $descriptor, $attributes, (string) $content ) );
			}

			// No descriptor registered — still render the bare shortcode from the
			// tag embedded in the id (everything after "owner/"), so the block works
			// for any shortcode, described or not.
			$tag = false !== strpos( $id, '/' ) ? substr( $id, strpos( $id, '/' ) + 1 ) : $id;
			$tag = preg_replace( '/[^a-z0-9_]/', '', $tag );
			if ( '' === $tag || ! shortcode_exists( $tag ) ) {
				return '';
			}

			return do_shortcode( '[' . $tag . ']' );
		}

		protected function build_shortcode( array $descriptor, array $attributes, string $inner ): string {
			$tag     = $descriptor['tag'];
			$atts_in = ( isset( $attributes['atts'] ) && is_array( $attributes['atts'] ) ) ? $attributes['atts'] : [];

			$pairs = [];
			foreach ( $descriptor['attributes'] as $attr ) {
				$name = $attr['name'];
				if ( ! array_key_exists( $name, $atts_in ) ) {
					continue;
				}
				$value = $atts_in[ $name ];
				if ( (string) $value === (string) $attr['default'] || '' === (string) $value ) {
					continue;
				}
				$clean = self::sanitize( $value, $attr['sanitize'] );
				if ( '' === $clean ) {
					continue;
				}
				$pairs[] = $name . '="' . esc_attr( $clean ) . '"';
			}
			$attr_str = $pairs ? ' ' . implode( ' ', $pairs ) : '';

			if ( empty( $descriptor['content']['supported'] ) ) {
				return '[' . $tag . $attr_str . ']';
			}
			$body = ( ( $descriptor['content']['mode'] ?? 'plain' ) === 'innerblocks' )
				? $inner
				: ( isset( $attributes['content'] ) ? (string) $attributes['content'] : '' );

			return '[' . $tag . $attr_str . ']' . $body . '[/' . $tag . ']';
		}

		/* -------------------------------------------------------------- */
		/* Editor exposure (registry + aBlocks promotion state)            */
		/* -------------------------------------------------------------- */

		public function enqueue_editor() {
			if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
				$this->register_block();
			}
			wp_enqueue_script( self::HANDLE );
			if ( wp_style_is( self::HANDLE, 'registered' ) ) {
				wp_enqueue_style( self::HANDLE );
			}

			$ablocks_active = function_exists( 'is_plugin_active' )
				? is_plugin_active( 'ablocks/ablocks.php' )
				: in_array( 'ablocks/ablocks.php', (array) get_option( 'active_plugins', [] ), true );

			$payload = [
				'schema_version' => Registry::SCHEMA_VERSION,
				'types'          => Registry::TYPES,
				'shortcodes'     => Registry::instance()->all(),
				'ablocks'        => [
					'active'      => (bool) $ablocks_active,
					'install_url' => self::ablocks_install_url(),
				],
			];

			wp_add_inline_script(
				self::HANDLE,
				'window.' . self::JS_GLOBAL . ' = ' . wp_json_encode( $payload ) . ';',
				'before'
			);
		}

		protected static function ablocks_install_url(): string {
			if ( current_user_can( 'install_plugins' ) ) {
				return wp_nonce_url(
					self_admin_url( 'update.php?action=install-plugin&plugin=ablocks' ),
					'install-plugin_ablocks'
				);
			}

			return 'https://wordpress.org/plugins/ablocks/';
		}

		public function register_rest() {
			register_rest_route(
				'shortcode-block/v1',
				'/registry',
				[
					'methods'             => 'GET',
					'permission_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
					'callback'            => static function () {
						return rest_ensure_response( [
							'schema_version' => Registry::SCHEMA_VERSION,
							'types'          => Registry::TYPES,
							'shortcodes'     => Registry::instance()->all(),
						] );
					},
				]
			);
		}

		/**
		 * Coerce a stored attribute value using the descriptor's sanitize hint,
		 * before it goes into the shortcode string.
		 *
		 * @param mixed $value
		 */
		public static function sanitize( $value, string $hint ): string {
			switch ( $hint ) {
				case 'int':
					return (string) intval( $value );
				case 'key':
					return sanitize_key( is_scalar( $value ) ? (string) $value : '' );
				case 'url':
					return esc_url_raw( is_scalar( $value ) ? (string) $value : '' );
				case 'color':
					$value = is_scalar( $value ) ? (string) $value : '';
					return sanitize_hex_color( $value ) ?: preg_replace( '/[^a-zA-Z0-9#(),.%\s-]/', '', $value );
				case 'csv':
					$parts = array_map( 'trim', explode( ',', is_scalar( $value ) ? (string) $value : '' ) );
					$parts = array_filter( $parts, static fn( $p ) => '' !== $p );
					return implode( ',', array_map( 'sanitize_text_field', $parts ) );
				case 'text':
				default:
					return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
			}
		}
	}
}

namespace {

	use StoreEngine\Blocks\Registry;

	if ( ! function_exists( 'storeengine_register_shortcode_block' ) ) {
		/**
		 * Register a shortcode as an editor block via the StoreEngine bridge.
		 *
		 * Call on an init-level hook, after the shortcode itself is added. Returns
		 * false (and logs under WP_DEBUG) when the descriptor is malformed.
		 *
		 * @param array $descriptor See the v1 contract.
		 */
		function storeengine_register_shortcode_block( array $descriptor ): bool {
			return Registry::instance()->register( $descriptor );
		}
	}
}
