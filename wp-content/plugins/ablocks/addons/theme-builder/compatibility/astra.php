<?php
namespace ABlocksThemeBuilder\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocksThemeBuilder\AbstractCompatibilityBase;

class Astra extends AbstractCompatibilityBase {
	protected $header_hook = 'astra_header';
	protected $footer_hook = 'astra_footer';
	protected $before_footer_hook = 'astra_footer_before';
	protected $enabled_header;
	protected $enabled_before_footer;
	protected $enabled_footer;

	public function init() {
		$this->enabled_header = $this->enabled_header();
		// Header
		if ( $this->enabled_header ) {
			add_action( 'template_redirect', [ $this, 'astra_setup_header' ], 10 );
			add_action( $this->header_hook, [ $this, 'render_header' ] );
		}

		// Footer
		$this->enabled_footer = $this->enabled_footer();
		$this->enabled_before_footer = $this->enabled_before_footer();

		if ( $this->enabled_footer || $this->enabled_before_footer ) {
			add_action( 'template_redirect', [ $this, 'astra_setup_footer' ], 10 );
		}

		if ( $this->enabled_before_footer ) {
			add_action( $this->before_footer_hook, [ $this, 'render_before_footer' ] );
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

	public function astra_setup_header() {
		remove_action( 'astra_header', 'astra_header_markup' );

		// Remove the new header builder action.
		if ( class_exists( 'Astra_Builder_Helper' ) && \Astra_Builder_Helper::$is_header_footer_builder_active ) {
			remove_action( 'astra_header', [ \Astra_Builder_Header::get_instance(), 'prepare_header_builder_markup' ] );
		}
	}

	public function astra_setup_footer() {
		remove_action( 'astra_footer', 'astra_footer_markup' );

		// Remove the new footer builder action.
		if ( class_exists( 'Astra_Builder_Helper' ) && \Astra_Builder_Helper::$is_header_footer_builder_active ) {
			remove_action( 'astra_footer', [ \Astra_Builder_Footer::get_instance(), 'footer_markup' ] );
		}
	}

	public function render_header() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_rendered_block_content_by_id( $this->get_settings( 'header' ) );
	}

	public function render_footer() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_rendered_block_content_by_id( $this->get_settings( 'footer' ) );
	}

	public function render_before_footer() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_rendered_block_content_by_id( $this->get_settings( 'before-footer' ) );
	}
}
