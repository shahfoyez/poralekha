<?php

namespace ABlocks\Blocks\EcmBookmarkBadge;

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
	protected $block_name = 'ecm-bookmark-badge';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark',
			$this->get_coutineu_button_wrapper_css( $attributes ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark svg',
			$this->get_coutineu_button_css( $attributes ),
			$this->get_coutineu_button_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark svg:hover',
			$this->get_coutineu_button_hover_css( $attributes ),
			$this->get_coutineu_button_hover_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark svg path',
			$this->get_badge_fill_color_css( $attributes ),
			$this->get_badge_fill_color_css($attributes, 'Tablet'),
			$this->get_badge_fill_color_css($attributes, 'Mobile')
		);

		return $css_generator->generate_css();
	}

	public function get_coutineu_button_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];

		$css['width'] = '100%';
		$css['display'] = 'flex';
		$css['flex-direction'] = 'column';

		if ( ! empty( $attributes['buttonAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['align-items'] = $attributes['buttonAlignment'][ 'value' . $device ];
		}

		return array_merge(
			$alignment_css,
			$css,
		);

	}

	public function get_badge_fill_color_css( $attributes ) {
		$fill_color = Color::get_css( isset( $attributes['badgeFillColor'] ) ? $attributes['badgeFillColor'] : '' );
		return $fill_color ? [ 'fill' => $fill_color ] : [];
	}


	public function get_coutineu_button_css( $attributes, $device = '' ) {
		$css = [];

		if ( $attributes['buttonColor'] !== '' ) {
			$css['color'] = $attributes['buttonColor'];
		}
		// if ( $attributes['buttonWidth'] !== '' ) {
		// $css['width'] = $attributes['buttonWidth'] . '%';
		// }
		if ( $attributes['buttonBackground'] !== '' ) {
			$css['background'] = $attributes['buttonBackground'];
		}

		if ( ! empty( $attributes['padding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['padding'], 'padding', $device );
		}

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBackground'] ) ? $attributes['buttonBackground'] : '' ) ],
			$css,
			Border::get_css( $attributes['buttonBorder'], '', $device ),
			$cssPadding,
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['bandageHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 20,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['bandageHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 20,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			])
		);
	}
	public function get_coutineu_button_hover_css( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			$css,
			[ 'color' => Color::get_css( isset( $attributes['buttonColorH'] ) ? $attributes['buttonColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBackgroundH'] ) ? $attributes['buttonBackgroundH'] : '' ) ],
			Border::get_hover_css( $attributes['buttonBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device )
		);
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'id'     				  => Helper::get_attribute_value( $attributes, 'id' ),
		];

		$shortcode = '[ecm_bookmark_badge ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}


}
