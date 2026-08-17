<?php

namespace ABlocks\Blocks\EcmClaimCount;

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
	protected $block_name = 'ecm-claim-count';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--ecm-claim-count',
			$this->get_coutineu_button_wrapper_css( $attributes ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Mobile' )
		);
		$wrapper_class = ! empty( $attributes['wrapper_class'] ) ? $attributes['wrapper_class'] : 'claim-count-wrapper';
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
			'status'     			  => Helper::get_attribute_value( $attributes, 'status' ),
			'show_label'   => Helper::get_attribute_value( $attributes, 'show_label' ) ? 'true' : 'false',
			'label_single'     		  => Helper::get_attribute_value( $attributes, 'label_single' ),
			'label_plural'     		  => Helper::get_attribute_value( $attributes, 'label_plural' ),
			'show_icon'     		  => Helper::get_attribute_value( $attributes, 'show_icon' ) ? 'true' : 'false',
			'wrapper_class'     	  => Helper::get_attribute_value( $attributes, 'wrapper_class' ),
		];

		$shortcode = '[ecm_claim_count ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

	public function get_coutineu_button_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];

		$css['width'] = '100%';
		$css['display'] = 'flex';
		$css['align-items'] = 'center';

		if ( ! empty( $attributes['countAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['countAlignment'][ 'value' . $device ];
		}

		return array_merge(
			$alignment_css,
			$css,
		);

	}

	public function get_coutineu_button_css( $attributes, $device = '' ) {
		$css = [];

		if ( $attributes['upvoteNumberColor'] !== '' ) {
			$css['color'] = $attributes['upvoteNumberColor'];
		}

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['upvoteNumberColor'] ) ? $attributes['upvoteNumberColor'] : '' ) ],
			$css,
		);
	}

}
