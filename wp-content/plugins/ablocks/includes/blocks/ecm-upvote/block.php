<?php

namespace ABlocks\Blocks\EcmUpvote;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;

class Block extends BlockBaseAbstract {
	protected $block_name = 'ecm-upvote';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote',
			$this->get_coutineu_button_wrapper_css( $attributes ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote .content-manager-btn',
			$this->get_coutineu_button_css( $attributes ),
			$this->get_coutineu_button_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote .content-manager-btn:hover',
			$this->get_coutineu_button_hover_css( $attributes ),
			$this->get_coutineu_button_hover_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote .content-manager-btn svg',
			$this->get_coutineu_button_button_badge_css( $attributes ),
			$this->get_coutineu_button_button_badge_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_button_badge_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote .content-manager-btn svg path',
			$this->get_badge_fill_color_css( $attributes ),
			$this->get_badge_fill_color_css( $attributes ),
			$this->get_badge_fill_color_css( $attributes )
		);

		return $css_generator->generate_css();
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'text'     		  => Helper::get_attribute_value( $attributes, 'text' ),
		];

		$shortcode = '[ecm_upvote ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

	public function get_coutineu_button_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];
		$alignment_css['display'] = 'flex';
		$alignment_css['flex-direction'] = 'column';

		if ( ! empty( $attributes['buttonAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['align-items'] = $attributes['buttonAlignment'][ 'value' . $device ];
		}

		return array_merge(
			$alignment_css,
			$css,
		);

	}

	public function get_coutineu_button_css( $attributes, $device = '' ) {
		$css = [];

		if ( $attributes['buttonColor'] !== '' ) {
			$css['color'] = $attributes['buttonColor'];
		}
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
		);
	}

	public function get_coutineu_button_hover_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['buttonColorH'] ) ) {
			$css['color'] = Color::get_css( $attributes['buttonColorH'] );
		}
		if ( ! empty( $attributes['buttonBackgroundH'] ) ) {
			$css['background'] = Color::get_css( $attributes['buttonBackgroundH'] );
		}

		return array_merge(
			$css,
			Border::get_hover_css( $attributes['buttonBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], $device ),
		);
	}

	public function get_coutineu_button_button_badge_css( $attributes, $device = '' ) {
			return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['badgeSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 30,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['badgeSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 30,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			])
			);

	}

	public function get_badge_fill_color_css( $attributes ) {
		$fill_color = Color::get_css( isset( $attributes['badgeFillColor'] ) ? $attributes['badgeFillColor'] : '' );
		return $fill_color ? [ 'fill' => $fill_color ] : [];
	}

}
