<?php

namespace ABlocks\Blocks\EcmBookmarkButton;

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


class Block extends BlockBaseAbstract {
	protected $block_name = 'ecm-bookmark-button';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark',
			$this->get_coutineu_button_wrapper_css( $attributes ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark button',
			$this->get_coutineu_button_css( $attributes ),
			$this->get_coutineu_button_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark button:hover',
			$this->get_coutineu_button_hover_css( $attributes ),
			$this->get_coutineu_button_hover_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark button svg',
			$this->get_coutineu_button_button_badge_css( $attributes ),
			$this->get_coutineu_button_button_badge_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_button_badge_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark button svg path',
			$this->get_badge_fill_color_css( $attributes ),
			$this->get_badge_fill_color_css( $attributes ),
			$this->get_badge_fill_color_css( $attributes )
		);

		return $css_generator->generate_css();
	}

	public function get_coutineu_button_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];
		$css['display'] = 'flex';

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
		$typography = isset( $attributes['buttonTypography'] ) && is_array( $attributes['buttonTypography'] )
			? $attributes['buttonTypography']
			: [];
		$typographyValueGlobal = ( isset( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : '' );

		$typography_value = array_merge( $typography, [ 'font-weight' => '400' ] );

		if ( ! empty( $attributes['padding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['padding'], 'padding', $device );
		}

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
			return array_merge(
				[ 'color' => Color::get_css( isset( $attributes	['buttonColorH'] ) ? $attributes['buttonColorH'] : '' ) ],
				[ 'background' => Color::get_css( isset( $attributes['buttonBackgroundH'] ) ? $attributes['buttonBackgroundH'] : '' ) ],
				Border::get_hover_css( $attributes['buttonBorder'] ?? [], '', $device ),
				BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
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

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'bookmark_text'           => Helper::get_attribute_value( $attributes, 'bookmark_text' ),
			'bookmarked_text'         => Helper::get_attribute_value( $attributes, 'bookmarked_text' ),
			'id'     				  => Helper::get_attribute_value( $attributes, 'id' ),
		];

		// if ( isset( $block_instance->context['postId'] ) && ! empty( $block_instance->context['postId'] ) && empty( $attr_array['product_id'] ) ) {
		// 	$attr_array['product_id'] = $block_instance->context['postId'];
		// }

		// // phpcs:ignore  WordPress.Security.NonceVerification.Recommended,  WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
		// if ( isset( $_GET['context'] ) && 'edit' === sanitize_text_field( $_GET['context'] ) ) {
		// 	$attr_array['dummy'] = true;
		// }

		$shortcode = '[ecm_bookmark_button ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
