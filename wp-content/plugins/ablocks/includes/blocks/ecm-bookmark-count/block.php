<?php

namespace ABlocks\Blocks\EcmBookmarkCount;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'ecm-bookmark-count';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$wrapper_class = ! empty( $attributes['wrapper_class'] ) ? $attributes['wrapper_class'] : 'wrapper';

		$css_generator->add_class_styles(
			'{{WRAPPER}} .' . $wrapper_class,
			$this->get_coutineu_button_wrapper_css( $attributes ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .' . $wrapper_class,
			$this->get_coutineu_button_css( $attributes ),
			$this->get_coutineu_button_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'user_id'     			  => Helper::get_attribute_value( $attributes, 'user_id' ),
			'not_found_text'     	  => Helper::get_attribute_value( $attributes, 'not_found_text' ),
			'show_label'     		  => Helper::get_attribute_value( $attributes, 'show_label' ) ? 'true' : 'false',
			'label_single'     		  => Helper::get_attribute_value( $attributes, 'label_single' ),
			'label_plural'     		  => Helper::get_attribute_value( $attributes, 'label_plural' ),
			'show_icon'     		  => Helper::get_attribute_value( $attributes, 'show_icon' ) ? 'true' : 'false',
			'wrapper_class'     	  => Helper::get_attribute_value( $attributes, 'wrapper_class' ),
		];

		$shortcode = '[ecm_bookmark_count ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

	public function get_coutineu_button_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];

		$css['width'] = '100%';
		$css['display'] = 'flex';
		$css['flex-direction'] = 'column';

		if ( ! empty( $attributes['countAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['align-items'] = $attributes['countAlignment'][ 'value' . $device ];
		}

		return array_merge(
			$alignment_css,
			$css,
		);

	}

	public function get_coutineu_button_css( $attributes, $device = '' ) {
		$css = [];

		if ( $attributes['numberColor'] !== '' ) {
			$css['color'] = $attributes['numberColor'];
		}
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['numberColor'] ) ? $attributes['numberColor'] : '' ) ],
			$css,
		);
	}

}
