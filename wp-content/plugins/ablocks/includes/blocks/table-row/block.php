<?php
namespace ABlocks\Blocks\TableRow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'table';
	protected $block_name = 'table-row';

	public function build_css( $attributes ) {
		// Create a new instance of CssGenerator
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		// Generate CSS using the row styles
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_row_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}:hover',
			$this->get_row_hover_css( $attributes )
		);
		return $css_generator->generate_css();
	}

	public function get_row_css( $attributes, $device = '' ) {
		$css = [];
			$css['background'] = Color::get_css(
			isset( $attributes['rowColor'] ) ? $attributes['rowColor'] : '') . ' !important';
		return $css;
	}
	public function get_row_hover_css( $attributes, $device = '' ) {
		$css = [];
			$css['background'] = Color::get_css(
			isset( $attributes['rowColorH'] ) ? $attributes['rowColorH'] : '') . ' !important';

		return $css;
	}
}
