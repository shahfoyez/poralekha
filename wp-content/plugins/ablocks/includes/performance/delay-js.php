<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Performance Suite — delay JavaScript until the first user interaction.
 *
 * The aBlocks combined frontend script is emitted in a non-executing form and
 * swapped in on the first interaction (scroll/key/pointer/touch) or after a
 * fallback timeout, cutting main-thread work during initial load (INP/TBT).
 *
 * Opt-in via `perf_delay_js`. The handles affected are filterable so it stays
 * scoped to aBlocks scripts (third-party JS is never touched).
 */
class DelayJs {

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		$enabled = (bool) apply_filters(
			'ablocks/perf/perf_delay_js',
			(bool) Helper::get_settings( 'perf_delay_js', false )
		);
		if ( ! $enabled ) {
			return;
		}
		// Never delay scripts for a logged-in editor viewing the frontend, so
		// building/previewing the site is unaffected; real visitors still get it.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' )
			&& (bool) apply_filters( 'ablocks/perf/bypass_optimizations_for_editors', true ) ) {
			return;
		}
		$self = new self();
		add_filter( 'script_loader_tag', [ $self, 'delay_tag' ], 20, 3 );
		add_action( 'wp_footer', [ $self, 'print_loader' ], 99 );
	}

	private function handles() {
		return (array) apply_filters(
			'ablocks/perf/delay_js_handles',
			[ 'ablocks-blocks-combine-script' ]
		);
	}

	/**
	 * Rewrite the target script tag so the browser doesn't execute it yet.
	 */
	public function delay_tag( $tag, $handle, $src ) {
		if ( ! $this->should_delay( $handle ) ) {
			return $tag;
		}
		// Move src out of the way and mark the tag as delayed.
		$tag = preg_replace( '/\ssrc=/', ' data-ablocks-src=', $tag, 1 );
		$tag = preg_replace( '/^<script\s/', '<script type="ablocks/delayed" ', $tag, 1 );
		return $tag;
	}

	/**
	 * Whether a script handle should be delayed. Covers the combined per-page
	 * script (assets-generation on) and the per-block frontend scripts
	 * (assets-generation off), so delay works in both modes.
	 */
	private function should_delay( $handle ) {
		if ( in_array( $handle, $this->handles(), true ) ) {
			return true;
		}
		return (bool) preg_match( '/^ablocks-.+-block-static-script$/', (string) $handle );
	}

	/**
	 * Print the tiny vanilla loader that activates delayed scripts on interaction.
	 */
	public function print_loader() {
		$timeout = (int) Helper::get_settings( 'perf_delay_js_timeout', 5000 );
		$timeout = max( 0, $timeout );
		?>
		<script id="ablocks-delay-js-loader">
		( function () {
			var loaded = false;
			var events = [ 'scroll', 'keydown', 'pointerdown', 'touchstart', 'mousemove' ];
			function load() {
				if ( loaded ) { return; }
				loaded = true;
				events.forEach( function ( e ) { window.removeEventListener( e, load ); } );
				document.querySelectorAll( 'script[type="ablocks/delayed"]' ).forEach( function ( s ) {
					var n = document.createElement( 'script' );
					var src = s.getAttribute( 'data-ablocks-src' );
					if ( src ) { n.src = src; }
					if ( s.id ) { n.id = s.id + '-delayed'; }
					document.body.appendChild( n );
				} );
			}
			events.forEach( function ( e ) { window.addEventListener( e, load, { passive: true, once: true } ); } );
			<?php if ( $timeout > 0 ) : ?>
			setTimeout( load, <?php echo (int) $timeout; ?> );
			<?php endif; ?>
		} )();
		</script>
		<?php
	}
}
