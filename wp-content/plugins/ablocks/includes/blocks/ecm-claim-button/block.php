<?php

namespace ABlocks\Blocks\EcmClaimButton;

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
use ABlocks\Controls\Alignment;


class Block extends BlockBaseAbstract {
	protected $block_name = 'ecm-claim-button';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--claim',
			$this->get_coutineu_button_wrapper_css( $attributes ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--claim button',
			$this->get_coutineu_button_css( $attributes ),
			$this->get_coutineu_button_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--claim button:hover',
			$this->get_coutineu_button_hover_css( $attributes ),
			$this->get_coutineu_button_hover_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_hover_css( $attributes, 'Mobile' )
		);
		foreach ( [ '.ecm-shortcode--tooltip', '.ecm-shortcode--tooltip svg', '.ecm-shortcode--tooltip svg path' ] as $selector ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} ' . $selector,
				$this->get_badge_css( $attributes, 'pendingBadgeFillColor', 'pendingBadgeSize' ),
				$this->get_badge_css( $attributes, 'pendingBadgeFillColor', 'pendingBadgeSize', 'Tablet' ),
				$this->get_badge_css( $attributes, 'pendingBadgeFillColor', 'pendingBadgeSize', 'Mobile' )
			);
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--ecm-claim-button',
			$this->get_list_alignment_css( $attributes ),
			$this->get_list_alignment_css( $attributes, 'Tablet' ),
			$this->get_list_alignment_css( $attributes, 'Mobile' )
		);
		foreach ( [ '.ecm-shortcode--claim__message', '.ecm-shortcode--claim__message svg', '.ecm-shortcode--claim__message svg path' ] as $selector ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} ' . $selector,
				$this->get_badge_css( $attributes, 'confirmationBandageFillColor', 'confirmationBandageSize', '', true ),
				$this->get_badge_css( $attributes, 'confirmationBandageFillColor', 'confirmationBandageSize', 'Tablet', true ),
				$this->get_badge_css( $attributes, 'confirmationBandageFillColor', 'confirmationBandageSize', 'Mobile', true )
			);
		}

		return $css_generator->generate_css();
	}

	public function get_coutineu_button_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];
		$css['display'] = 'flex';

		if ( ! empty( $attributes['buttonAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['buttonAlignment'][ 'value' . $device ];
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
		$typography = isset( $attributes['buttonTypography'] ) && is_array( $attributes['buttonTypography'] )
			? $attributes['buttonTypography']
			: [];
		$typographyValueGlobal = ( isset( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : '' );

		$typography_value = array_merge( $typography, [ 'font-weight' => '400' ] );

		$cssPadding = ! empty( $attributes['padding'] ) ? Dimensions::get_css( $attributes['padding'], 'padding', $device ) : [];

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBackground'] ) ? $attributes['buttonBackground'] : '' ) ],
			$css,
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			Border::get_css( $attributes['buttonBorder'], '', $device ),
			$cssPadding,
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
		);
	}

	public function get_coutineu_button_hover_css( $attributes, $device = '' ) {
		$hover_css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonColorH'] ) ? $attributes['buttonColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBackgroundH'] ) ? $attributes['buttonBackgroundH'] : '' ) ],
			Border::get_hover_css( $attributes['buttonBorder'] ?? [], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
		);

		if ( ! empty( $hover_css['box-shadow'] ) ) {
			$hover_css['box-shadow'] = $hover_css['box-shadow'] . ' !important';
		} else {
			$normal_shadow = BoxShadow::get_css( $attributes['boxShadow'], '', $device );
			if ( ! empty( $normal_shadow['box-shadow'] ) ) {
				$hover_css['box-shadow'] = $normal_shadow['box-shadow'] . ' !important';
			}
		}

		return $hover_css;
	}

	public function get_badge_css( $attributes, $fill_key, $size_key, $device = '', $transparent_bg = false ) {
		$base = [ 'fill' => Color::get_css( $attributes[ $fill_key ] ?? '' ) ];
		if ( $transparent_bg ) {
			$base['background'] = 'transparent';
		}
		$size_args = [
			'attributeValue'     => $attributes[ $size_key ],
			'attribute_object_key' => 'value',
			'isResponsive'       => true,
			'hasUnit'            => true,
			'defaultValue'       => 20,
			'unitDefaultValue'   => 'px',
			'device'             => $device,
		];
		return array_merge(
			$base,
			Range::get_css( array_merge( $size_args, [ 'property' => 'height' ] ) ),
			Range::get_css( array_merge( $size_args, [ 'property' => 'width' ] ) )
		);
	}

	public function get_list_alignment_css( $attributes, $device = '' ) {
		$css = [ 'display' => 'flex', 'align-items' => 'center' ];
		$alignment_css = [];

		if ( ! empty( $attributes['listAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['listAlignment'][ 'value' . $device ];
		}
		return array_merge( $alignment_css, $css );
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'bookmark_text'           => Helper::get_attribute_value( $attributes, 'bookmark_text' ),
			'bookmarked_text'         => Helper::get_attribute_value( $attributes, 'bookmarked_text' ),
			'id'     				  => Helper::get_attribute_value( $attributes, 'id' ),
		];

		$shortcode = '[ecm_claim_button ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
