<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class RegisterScripts {
	public static function get_register_styles() {
		return apply_filters('ablocks/register_styles', [
			'ablocks-prism-style' => [
				'path'  => ABLOCKS_ASSETS_PATH . 'library/prism/prism.css',
				'url'   => ABLOCKS_ASSETS_URL . 'library/prism/prism.css',
				'deps'  => array(),
				'ver'   => false,
				'media' => 'all'
			],
			'ablocks-prism-line-numbers-style' => [
				'path' => ABLOCKS_ASSETS_PATH . 'library/prism/prism-line-numbers.css',
				'url'  => ABLOCKS_ASSETS_URL . 'library/prism/prism-line-numbers.css',
				'deps' => array(),
				'ver'  => false,
				'media' => 'all'
			],
			'ablocks-prism-line-highlight-style' => [
				'path' => ABLOCKS_ASSETS_PATH . 'library/prism/prism-line-highlight.css',
				'url'  => ABLOCKS_ASSETS_URL . 'library/prism/prism-line-highlight.css',
				'deps' => array(),
				'ver'  => false,
				'media' => 'all'
			],
			'ablocks-leaflet-style' => [
				'path'  => ABLOCKS_ASSETS_PATH . 'library/leaflet/leaflet.css',
				'url'   => ABLOCKS_ASSETS_URL . 'library/leaflet/leaflet.css',
				'deps'  => array(),
				'ver'   => false,
				'media' => 'all'
			],
			'ablocks-leaflet-full-screen-style' => [
				'path'  => ABLOCKS_ASSETS_PATH . 'library/leaflet/leaflet.fullscreen.css',
				'url'   => ABLOCKS_ASSETS_URL . 'library/leaflet/leaflet.fullscreen.css',
				'deps'  => array(),
				'ver'   => false,
				'media' => 'all'
			],
			'ablocks-animate-style' => [
				'path'  => ABLOCKS_ASSETS_PATH . 'library/animate/animate.min.css',
				'url'   => ABLOCKS_ASSETS_URL . 'library/animate/animate.min.css',
				'deps'  => array(),
				'ver'   => false,
				'media' => 'all'
			],
			'ablocks-swiper-style' => [
				'path'  => ABLOCKS_ASSETS_PATH . 'library/swiper/swiper-bundle.min.css',
				'url'   => ABLOCKS_ASSETS_URL . 'library/swiper/swiper-bundle.min.css',
				'deps'  => array(),
				'ver'   => false,
				'media' => 'all'
			],
			'ablocks-plyr-style' => [
				'path'  => ABLOCKS_ASSETS_PATH . 'library/plyr/plyr.css',
				'url'   => ABLOCKS_ASSETS_URL . 'library/plyr/plyr.css',
				'deps'  => array(),
				'ver'   => false,
				'media' => 'all'
			],
			'ablocks-common-style' => [
				'path'  => ABLOCKS_ASSETS_PATH . 'build/blocks-common.css',
				'url'   => ABLOCKS_ASSETS_URL . 'build/blocks-common.css',
				'deps'  => array(),
				'ver'   => false,
				'media' => 'all'
			],
		]);
	}
	public static function get_register_scripts() {
		$script_loading_strategy = \ABlocks\Helper::get_script_loading_strategy();
		$args = [ 'strategy' => $script_loading_strategy ];
		return apply_filters('ablocks/register_scripts', [
			// Data-only handle (no src) that hosts the single frontend
			// ABlocksGlobal payload. Every aBlocks frontend script depends on it
			// — directly, or transitively through ablocks-common-script — so WP
			// prints the object exactly once per page instead of once per block
			// script handle (asset-generation off) or once per combined template
			// script (asset-generation on). See Assets::localize_globals_once().
			'ablocks-globals' => [
				'url'           => false,
				'deps'          => array(),
				'ver'           => ABLOCKS_VERSION,
				'args'          => array(),
			],
			'ablocks-prism-script' => [
				'path'          => ABLOCKS_ASSETS_PATH . 'library/prism/prism.js',
				'url'           => ABLOCKS_ASSETS_URL . 'library/prism/prism.js',
				'deps'          => array(),
				'ver'           => false,
				'args'          => $args
			],
			'ablocks-prism-line-numbers-script' => [
				'path'          => ABLOCKS_ASSETS_PATH . 'library/prism/prism-line-numbers.js',
				'url'           => ABLOCKS_ASSETS_URL . 'library/prism/prism-line-numbers.js',
				'deps'          => array(),
				'ver'           => false,
				'args'          => $args
			],
			'ablocks-prism-line-highlight-script' => [
				'path'          => ABLOCKS_ASSETS_PATH . 'library/prism/prism-line-highlight.js',
				'url'           => ABLOCKS_ASSETS_URL . 'library/prism/prism-line-highlight.js',
				'deps'          => array(),
				'ver'           => false,
				'args'          => $args
			],
			'ablocks-leaflet-script' => [
				'path'          => ABLOCKS_ASSETS_PATH . 'library/leaflet/leaflet.js',
				'url'           => ABLOCKS_ASSETS_URL . 'library/leaflet/leaflet.js',
				'deps'          => array(),
				'ver'           => false,
				'args'          => $args
			],
			'ablocks-leaflet-full-screen-script' => [
				'path'          => ABLOCKS_ASSETS_PATH . 'library/leaflet/Leaflet.fullscreen.js',
				'url'           => ABLOCKS_ASSETS_URL . 'library/leaflet/Leaflet.fullscreen.js',
				'deps'          => array(),
				'ver'           => false,
				'args'          => $args
			],
			'ablocks-swiper-script' => [
				'path'          => ABLOCKS_ASSETS_PATH . 'library/swiper/swiper-bundle.min.js',
				'url'           => ABLOCKS_ASSETS_URL . 'library/swiper/swiper-bundle.min.js',
				'deps'          => array(),
				'ver'           => false,
				'args'          => $args
			],
			'ablocks-chart.js-script' => [
				'path'          => ABLOCKS_ASSETS_PATH . 'library/chart/chart.js',
				'url'           => ABLOCKS_ASSETS_URL . 'library/chart/chart.js',
				'deps'          => array(),
				'ver'           => false,
				'args'          => $args
			],
			'ablocks-plyr-script' => [
				'path'  => ABLOCKS_ASSETS_PATH . 'library/plyr/plyr.js',
				'url'   => ABLOCKS_ASSETS_URL . 'library/plyr/plyr.js',
				'deps'          => array(),
				'ver'           => false,
				'args'          => true,
				'dependencies'  => ABLOCKS_ASSETS_PATH . 'build/blocks-common.asset.php'
			],
			'ablocks-common-script' => [
				'path'          => ABLOCKS_ASSETS_PATH . 'build/blocks-common.js',
				'url'           => ABLOCKS_ASSETS_URL . 'build/blocks-common.js',
				// Every block script depends on this handle (see
				// BlockBaseAbstract::get_script_depends), so hanging
				// ablocks-globals off it makes the payload reachable from any
				// rendered block without touching each block's own deps.
				'deps'          => array( 'ablocks-globals' ),
				'ver'           => false,
				'args'          => $args,
				'dependencies'  => ABLOCKS_ASSETS_PATH . 'build/blocks-common.asset.php'
			],
		]);
	}
}
