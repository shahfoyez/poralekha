<?php
namespace ABlocksThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocksThemeBuilder\Traits\RenderContent;

abstract class AbstractCompatibilityBase {
	use RenderContent;
	protected $theme;
	protected $header_hook = 'ablocks/templates/theme_builder/header';
	protected $footer_hook = 'ablocks/templates/theme_builder/footer';
	protected $before_footer_hook = 'ablocks/templates/theme_builder/footer';
	protected $enabled_header;
	protected $enabled_before_footer;
	protected $enabled_footer;

	public function __construct( $theme ) {
		$this->theme = $theme;
	}

	public function init() {
		$this->enabled_header = $this->enabled_header();
		// Header
		if ( $this->enabled_header ) {
			add_action( 'get_header', [ $this, 'header_template' ] );
			add_action( $this->header_hook, [ $this, 'render_header' ] );
		}

		// Footer
		$this->enabled_footer = $this->enabled_footer();
		$this->enabled_before_footer = $this->enabled_before_footer();
		if ( $this->enabled_footer || $this->enabled_before_footer ) {
			add_action( 'get_footer', [ $this, 'footer_template' ] );
		}
		// Before Footer
		if ( $this->enabled_before_footer ) {
			add_action( $this->before_footer_hook, [ $this, 'render_before_footer' ], 5 );
		}
		if ( $this->enabled_footer ) {
			add_action( $this->footer_hook, [ $this, 'render_footer' ] );
		}

		do_action('ablocks_theme_builder_after_dispatch', [
			'header' => $this->enabled_header,
			'before_footer' => $this->enabled_before_footer,
			'footer' => $this->enabled_footer,
		]);
	}

	public function enabled_header() {
		return $this->get_settings( 'header' );
	}

	public function enabled_footer() {
		return $this->get_settings( 'footer' );
	}

	public function enabled_before_footer() {
		return $this->get_settings( 'before-footer' );
	}

	public function get_settings( $type ) {
		return Helper::get_template_id( $type );
	}

	public function header_template() {
		require ABLOCKS_ROOT_DIR_PATH . $this->get_template_path( 'header.php' );
		$templates   = [];
		$templates[] = 'header.php';
		// Avoid running wp_head hooks again.
		remove_all_actions( 'wp_head' );
		ob_start();
		locate_template( $templates, true );
		ob_get_clean();
	}
	public function footer_template() {
		require ABLOCKS_ROOT_DIR_PATH . $this->get_template_path( 'footer.php' );
		$templates   = [];
		$templates[] = 'footer.php';
		// Avoid running wp_footer hooks again.
		remove_all_actions( 'wp_footer' );
		ob_start();
		locate_template( $templates, true );
		ob_get_clean();
	}

	public function get_template_path( $suffix ) {
		$prefix = ! empty( $this->theme ) ? "{$this->theme}-" : '';
		return "templates/theme-builder/{$prefix}{$suffix}";
	}
	// To be overridden by specific themes
	abstract public function render_header();
	abstract public function render_footer();
	abstract public function render_before_footer();
}
