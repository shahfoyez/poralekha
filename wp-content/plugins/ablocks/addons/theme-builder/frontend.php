<?php
namespace ABlocksThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {

	public static function init() {
		$self = new self();
		add_action( 'wp_body_open', array( $self, 'render_info_popup_builder' ) );
	}

	public function render_info_popup_builder() {
		$info_popup_builder = Helper::get_info_popup_builder_content();
		if ( is_array( $info_popup_builder ) && count( $info_popup_builder ) ) {
			add_filter( 'ablocks/is_allow_block_inline_assets', '__return_true' );
			add_filter( 'ablocks/is_enabled_assets_generation', '__return_false' );
			foreach ( $info_popup_builder as $post ) {
				$content = apply_filters( 'the_content', $post->post_content );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Post content is sanitized on save by WordPress
				echo do_blocks( $content );
			}
			remove_filter( 'ablocks/is_allow_block_inline_assets', '__return_true' );
			remove_filter( 'ablocks/is_enabled_assets_generation', '__return_false' );
		}
	}
}

