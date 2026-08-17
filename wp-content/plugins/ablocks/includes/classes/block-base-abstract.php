<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \ABlocks\Helper;
use \ABlocks\Assets;

abstract class BlockBaseAbstract {

	protected $namespace = 'ablocks';

	protected $assets_url = ABLOCKS_ASSETS_URL;

	protected $assets_path = ABLOCKS_ASSETS_PATH;

	protected $blocks_dir_path = ABLOCKS_BLOCKS_DIR_PATH;

	protected $parent_block_name = '';

	protected $is_skip_inner_block = false;

	protected $block_name = '';

	protected $style_depends = [];
	protected $script_depends = [];

	protected $animation_settings = [];

	public function __construct( $keep_silent = false ) {
		if ( $this->is_enabled_block() && ! $keep_silent ) {
			add_action( 'init', array( $this, 'register_block' ), 20 );
			add_action( 'ablocks/before_render_' . $this->block_name . '_block_content', array( $this, 'enqueue_block_specific_static_assets' ) );
		}
	}

	public function is_enabled_block() {
		global $ablocks_blocks;
		$block_name = ! empty( $this->parent_block_name ) ? $this->parent_block_name : $this->block_name;
		if ( isset( $ablocks_blocks->{$block_name} ) ) {
			return (bool) $ablocks_blocks->{$block_name};
		}
		return false;
	}

	public function register_block() {
		$block_path = $this->assets_path . 'build/blocks/' . $this->block_name . '/block.json';
		$args = [
			'render_callback' => array( $this, 'render_callback' ),
		];
		if ( isset( $metadata['usesContext'] ) ) {
			$args['usesContext'] = $metadata['usesContext'];
		}
		if ( isset( $metadata['providesContext'] ) ) {
			$args['providesContext'] = $metadata['providesContext'];
		}
		if ( $this->is_skip_inner_block ) {
			$args['skip_inner_blocks'] = $this->is_skip_inner_block;
		}
		register_block_type( $block_path, $args );
	}

	public function get_attributes() {
		$block_attributes = include $this->blocks_dir_path . $this->block_name . '/attributes.php';
		return apply_filters( 'ablocks/register_block_attributes', $block_attributes, $this->block_name, $this->parent_block_name );
	}

	private function get_block_class( $css_class ) {
		$classes = array();
		if ( $css_class ) {
			if ( ! is_array( $css_class ) ) {
				$css_class = preg_split( '#\s+#', $css_class );
			}
			$classes = array_map( 'esc_attr', $css_class );
		} else {
			// Ensure that we always coerce class to being an array.
			$css_class = array();
		}

		return 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
	}
	private function get_block_data_settings_attributes( $settings ) {
		if ( ! is_array( $settings ) || empty( $settings['animationType'] ) || $settings['animationType'] !== 'none' ) {
			return '';
		}

		// Sanitize each setting in the array
		$sanitized_settings = array_map( 'esc_attr', $settings );
		// Encode sanitized settings to JSON and escape it for safe output
		return sprintf( 'data-settings="%s"', esc_attr( wp_json_encode( $sanitized_settings ) ) );
	}
	private function get_dynamic_block_wrap( $attributes, $content, $block_instance ) {
		$block_id = ( isset( $attributes['block_id'] ) ? $attributes['block_id'] : '' );
		$animation = ( isset( $attributes['_animation'] ) ? $attributes['_animation'] : [] );
		$block_class_args = array( 'ablocks-block', 'ablocks-block-' . $block_id, 'ablocks-block--' . $this->block_name );
		$has_transform = (bool) ( isset( $attributes['_transform']['isApplied'] ) ? $attributes['_transform']['isApplied'] : false );

		if ( count( $animation ) && (
				( ! empty( $animation['animationType'] ) && $animation['animationType'] !== 'none' ) ||
				( ! empty( $animation['animationTypeTablet'] ) && $animation['animationTypeTablet'] !== 'none' ) ||
				( ! empty( $animation['animationTypeMobile'] ) && $animation['animationTypeMobile'] !== 'none' )
			)
		) {
			array_push( $block_class_args, 'ablocks-invisible' );
		}

		if ( $has_transform ) {
			array_push( $block_class_args, 'ablocks-has-block-container' );
		}

		ob_start();
		?>
		<div <?php echo $this->get_block_class( $block_class_args ) . $this->get_block_data_settings_attributes( $animation ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			if ( $has_transform ) :
				?>
			<div class="ablocks-block-container">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->render_block_content( $attributes, $content, $block_instance );
				?>
			</div> 
				<?php
				else :
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->render_block_content( $attributes, $content, $block_instance );
				endif;
				?>
		</div>
		<?php
		return ob_get_clean();
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		return $content;
	}

	public function render_callback( $attributes, $content, $block_instance ) {
		$block_name = $block_instance->name;
		do_action( 'ablocks/before_render_' . explode( '/', $block_name )[1] . '_block_content', $block_name );
		do_action( 'ablocks/render_callback', $block_name, $attributes );

		// Animation CSS is no longer a global dependency (see get_style_depends).
		// In the fallback path (asset-generation off) enqueue it only when this
		// block actually animates, so pages without animation never load it.
		$this->maybe_enqueue_animate_style( $attributes );

		// Dynamic block
		if ( ! $content || $this->is_skip_inner_block ) {
			// When called from the editor's ServerSideRender (REST API), RenderContainer (JS) already
			// provides the outer ablocks-block-{blockId} wrapper via useBlockProps. Including it here
			// too causes the Advanced-settings CSS selector to match two elements → double border/padding.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				ob_start();
				echo $this->render_block_content( $attributes, $content, $block_instance );
				$content = ob_get_clean();
			} else {
				$content = $this->get_dynamic_block_wrap( $attributes, $content, $block_instance );
			}
		}

		// Static Block but control render from php
		$content = apply_filters( 'ablocks/get_render_block_content', $content, $attributes, $block_instance );
		if ( ! $content ) {
			return $content;
		}

		// Pure Static block
		if ( apply_filters( 'ablocks/is_allow_block_inline_assets', ( ! is_admin() || defined( 'DOING_AJAX' ) && DOING_AJAX ) && ! Helper::is_enabled_assets_generation() ) ) {

			$build_css = '<style>' . $this->build_css( $attributes ) . '</style>';

			return $build_css . $content;
		}

		return $content;
	}

	/**
	 * file_exists + filemtime for a per-block asset, memoized per request so
	 * repeated instances of the same block don't re-stat the same file.
	 * Returns the mtime (cache-bust version) or false when the file is missing.
	 */
	private static $asset_version_cache = [];
	private function cached_asset_version( $path ) {
		if ( ! array_key_exists( $path, self::$asset_version_cache ) ) {
			self::$asset_version_cache[ $path ] = file_exists( $path ) ? filemtime( $path ) : false;
		}
		return self::$asset_version_cache[ $path ];
	}

	/**
	 * Enqueue the animate.css library only when a block actually uses an
	 * animation, and only in the fallback (per-block) asset path — with
	 * asset-generation on, the combined generator bakes it in on demand instead.
	 */
	private function maybe_enqueue_animate_style( $attributes ) {
		if ( is_admin() || Helper::is_enabled_assets_generation() ) {
			return;
		}
		$animation_type = isset( $attributes['_animation']['animationType'] ) ? $attributes['_animation']['animationType'] : '';
		if ( ! empty( $animation_type ) && 'none' !== $animation_type ) {
			wp_enqueue_style( 'ablocks-animate-style' );
		}
	}

	private function enqueue_static_assets( $block_name ) {
		// Library
		if ( count( $this->get_style_depends() ) ) {
			foreach ( $this->get_style_depends() as $style_handler ) {
				wp_enqueue_style( $style_handler );
			}
		}

		// Library
		if ( count( $this->get_script_depends() ) ) {
			foreach ( $this->get_script_depends() as $script_handler ) {
				wp_enqueue_script( $script_handler );
			}
		}

		// block static css
		$style_version = $this->cached_asset_version( $this->assets_path . 'build/blocks/' . $block_name . '/style.css' );
		if ( false !== $style_version ) {
			wp_enqueue_style( 'ablocks-' . $block_name . '-block-static-style', $this->assets_url . 'build/blocks/' . $block_name . '/style.css', array(), $style_version, 'all' );
		}

		$script_loading_strategy = \ABlocks\Helper::get_script_loading_strategy();
		$args = [ 'strategy' => $script_loading_strategy ];

		if ( false !== $this->cached_asset_version( $this->assets_path . 'build/blocks/' . $block_name . '/view.js' ) && ! wp_script_is( 'ablocks-' . $block_name . '-block-static-script', 'enqueued' ) ) {
			$dependencies = include $this->assets_path . 'build/blocks/' . $block_name . '/view.asset.php';
			// Depend on the shared data-only handle so ABlocksGlobal is printed
			// before this script runs, without each block carrying its own copy.
			$deps = array_values( array_unique( array_merge( array( 'ablocks-globals' ), $dependencies['dependencies'] ) ) );
			wp_enqueue_script(
				'ablocks-' . $block_name . '-block-static-script',
				$this->assets_url . 'build/blocks/' . $block_name . '/view.js',
				$deps,
				$dependencies['version'],
				$args
			);
			Assets::localize_globals_once();
		}

	}

	public function enqueue_block_static_assets() {
		if ( ! is_admin() && ! Helper::is_enabled_assets_generation() ) {
			$this->enqueue_static_assets( $this->block_name );
		}//end if
	}
	public function enqueue_block_specific_static_assets( $block_name ) {
		if ( ! is_admin() && ! Helper::is_enabled_assets_generation() ) {
			$this->enqueue_static_assets( explode( '/', $block_name )[1] );
		}//end if
	}

	public function get_static_css() {
		if ( file_exists( $this->assets_path . 'build/blocks/' . $this->block_name . '/style.css' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$has_static_css = file_get_contents( $this->assets_path . 'build/blocks/' . $this->block_name . '/style.css' );
			if ( $has_static_css ) {
				// A block's style.css doubles as its editorStyle, so it can carry
				// editor-chrome rules (.block-editor-*, .editor-styles-wrapper …)
				// that never match on the front end. Strip them from the frontend
				// static CSS (this getter feeds the combined/inline page CSS; the
				// editor loads style.css directly via block.json).
				return self::strip_editor_only_css( $has_static_css );
			}
		}
		return '';
	}

	/**
	 * Remove rules whose selector is entirely editor-chrome (every
	 * comma-separated part references an editor-only class), leaving mixed and
	 * front-end rules untouched. Brace-aware so @media blocks stay intact.
	 */
	private static $editor_css_cache = [];
	public static function strip_editor_only_css( $css ) {
		$key = md5( $css );
		if ( isset( self::$editor_css_cache[ $key ] ) ) {
			return self::$editor_css_cache[ $key ];
		}
		$markers = [ '.block-editor-', '.editor-styles-wrapper', '.block-list-appender', '.components-base-control', '.block-editor-block-variation-picker', '.block-editor-button-block-appender' ];

		$out = '';
		$len = strlen( $css );
		$i   = 0;
		$buf = '';
		while ( $i < $len ) {
			$ch   = $css[ $i ];
			$buf .= $ch;
			if ( '{' === $ch ) {
				$prelude = trim( substr( $buf, 0, -1 ) );
				$depth   = 1;
				$i++;
				while ( $i < $len && $depth > 0 ) {
					$c    = $css[ $i ];
					$buf .= $c;
					if ( '{' === $c ) {
						$depth++;
					} elseif ( '}' === $c ) {
						$depth--;
					}
					$i++;
				}
				$drop = false;
				if ( '' !== $prelude && '@' !== $prelude[0] ) {
					$parts = array_map( 'trim', explode( ',', $prelude ) );
					$drop  = true;
					foreach ( $parts as $part ) {
						// Drop :not(...) negations first — a selector like
						// ":not(.block-editor-block-list__block) .foo" is a FRONT-END
						// rule (applies everywhere except the editor), NOT editor
						// chrome, so the marker inside :not() must not count.
						$positive  = preg_replace( '/:not\([^)]*\)/', '', $part );
						$is_editor = false;
						foreach ( $markers as $m ) {
							if ( false !== strpos( $positive, $m ) ) {
								$is_editor = true;
								break;
							}
						}
						if ( ! $is_editor ) {
							$drop = false; // a front-end part exists — keep the rule
							break;
						}
					}
				}
				if ( ! $drop ) {
					$out .= $buf;
				}
				$buf = '';
				continue;
			}
			$i++;
		}
		$out .= $buf;

		self::$editor_css_cache[ $key ] = $out;
		return $out;
	}
	public function get_static_js() {
		if ( file_exists( $this->assets_path . 'build/blocks/' . $this->block_name . '/view.js' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$has_static_js = file_get_contents( $this->assets_path . 'build/blocks/' . $this->block_name . '/view.js' );
			if ( $has_static_js ) {
				return $has_static_js;
			}
		}
		return '';
	}
	public function get_style_depends() {
		// NOTE: 'ablocks-animate-style' is intentionally NOT included here. Animation
		// CSS is heavy and most blocks don't animate, so it's added on demand only:
		// the combined generator adds it when a block's _animation attribute is set
		// (AssetsGenerator::recursive_block_parser), and the fallback render path
		// enqueues it per-block via maybe_enqueue_animate_style().
		return apply_filters( 'ablocks/block_style_depends', array_merge( $this->style_depends, array( 'ablocks-frontend-google-fonts', 'ablocks-common-style' ) ) );
	}
	public function get_script_depends() {
		return apply_filters( 'ablocks/block_script_depends', array_merge( $this->script_depends, array( 'ablocks-common-script' ) ) );
	}
	abstract public function build_css( $attributes);
}
