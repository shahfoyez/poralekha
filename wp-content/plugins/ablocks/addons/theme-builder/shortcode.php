<?php
namespace ABlocksThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocksThemeBuilder\Traits\RenderContent;

class Shortcode {
	use RenderContent;

	public static function init() {
		$self = new self();
		add_shortcode( 'ablocks_template', [ $self, 'render_template' ] );
	}
	public function render_template( $attributes ) {
		$attributes = shortcode_atts(
			[
				'id' => '',
			],
			$attributes,
			'ablocks_template'
		);

		$id = ! empty( $attributes['id'] ) ? apply_filters( 'ablocks/theme_builder/render_template_id', intval( $attributes['id'] ) ) : '';

		if ( empty( $id ) ) {
			return '';
		}
		add_filter( 'ablocks/is_allow_block_inline_assets', '__return_true' );
		add_filter( 'ablocks/is_enabled_assets_generation', '__return_false' );
		$content = $this->get_rendered_block_content_by_id( $id );
		remove_filter( 'ablocks/is_allow_block_inline_assets', '__return_true' );
		remove_filter( 'ablocks/is_enabled_assets_generation', '__return_false' );
		return $content;
	}
}
