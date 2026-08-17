<?php

namespace ABlocks\Blocks\EcmClaimBadge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Background;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'ecm-claim-badge';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--claim-badge',
			$this->get_coutineu_button_wrapper_css( $attributes ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--claim-badge__badge-text',
			$this->get_coutineu_button_with_text( $attributes ),
			$this->get_coutineu_button_with_text( $attributes, 'Tablet' ),
			$this->get_coutineu_button_with_text( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--claim-badge svg',
			$this->get_coutineu_button_css( $attributes ),
			$this->get_coutineu_button_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--claim-badge svg path',
			$this->get_badge_fill_color_css( $attributes ),
			$this->get_badge_fill_color_css($attributes, 'Tablet'),
			$this->get_badge_fill_color_css($attributes, 'Mobile')
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--claim-badge:hover svg',
			$this->get_coutineu_button_hover_css( $attributes ),
			$this->get_coutineu_button_hover_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_coutineu_button_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];

		$css['width'] = '100%';
		$css['display'] = 'flex';
		$css['align-items'] = 'center';

		if ( ! empty( $attributes['buttonAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['buttonAlignment'][ 'value' . $device ];
		}

		return array_merge(
			$alignment_css,
			$css,
			Range::get_css([
				'attributeValue' => $attributes['textGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 10,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
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
	public function get_coutineu_button_with_text( $attributes, $device = '' ) {
		$css = [];

		if ( $attributes['textColor'] !== '' ) {
			$css['color'] = $attributes['textColor'];
		}

		$typography = isset( $attributes['textTypography'] ) && is_array( $attributes['textTypography'] )
			? $attributes['textTypography']
			: [];
		$typographyValueGlobal = ( isset( $attributes['textTypographyGlobal'] ) ? $attributes['textTypographyGlobal'] : '' );

		$typography_value = array_merge( $typography, [ 'font-weight' => '400' ] );

		return array_merge(
			$css,
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			[ 'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '' ) ],
		);
	}

	public function get_badge_fill_color_css( $attributes ) {
		$fill_color = Color::get_css( isset( $attributes['badgeFillColor'] ) ? $attributes['badgeFillColor'] : '' );
		return $fill_color ? [ 'fill' => $fill_color ] : [];
	}


	public function get_coutineu_button_hover_css( $attributes, $device = '' ) {
		$button_border = array_merge(
			[ 'unitWidthH' => 'px', 'unitRadiusH' => 'px' ],
			$attributes['buttonBorder'] ?? []
		);

		return array_merge(
			[ 'color' => Color::get_css( $attributes['buttonColorH'] ?? '' ) ],
			[ 'background' => Color::get_css( $attributes['buttonBackgroundH'] ?? '' ) ],
			Border::get_hover_css( $button_border, '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'] ?? [], $device )
		);
	}

	public function get_coutineu_button_text_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css( $attributes['textColorH'] ?? '' ),
		];
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'post_id'     				  => Helper::get_attribute_value( $attributes, 'post_id' ),
			'text'     				  => Helper::get_attribute_value( $attributes, 'text' ),
		];

		$shortcode = '[ecm_claim_badge ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}


}
