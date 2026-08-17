<?php

namespace ABlocks\Blocks\StoreengineCartNotice;

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
	protected $block_name = 'storeengine-cart-notice';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-notice--info',
			$this->get_notice_css( $attributes ),
			$this->get_notice_css( $attributes, 'Tablet' ),
			$this->get_notice_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-notice--info:hover',
			$this->get_notice_Hover_css( $attributes ),
			$this->get_notice_Hover_css( $attributes, 'Tablet' ),
			$this->get_notice_Hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-notice .storeengine-notice--message p,
			{{WRAPPER}} .storeengine-notice .storeengine-notice--message i',
			$this->get_notice_massage_css( $attributes ),
			$this->get_notice_massage_css( $attributes, 'Tablet' ),
			$this->get_notice_massage_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-notice a,
			{{WRAPPER}} .storeengine-notice button',
			$this->get_notice_link_css( $attributes ),
			$this->get_notice_link_css( $attributes, 'Tablet' ),
			$this->get_notice_link_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-notice a:hover,
			{{WRAPPER}} .storeengine-notice button:hover',
			$this->get_notice_link_hover_css( $attributes ),
			$this->get_notice_link_hover_css( $attributes, 'Tablet' ),
			$this->get_notice_link_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--storeengine-cart-notice:not(.ablocks-block-container),
			{{WRAPPER}}.ablocks-block--storeengine-cart-notice .ablocks-block-container',
			$this->get_notice_wrapper_css( $attributes ),
			$this->get_notice_wrapper_css( $attributes, 'Tablet' ),
			$this->get_notice_wrapper_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}

	public function get_notice_css( $attributes, $device = '' ) {
		if ( ! empty( $attributes['infoBoxPadding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['infoBoxPadding'], 'padding', $device );
		}

		return array_merge(
			[
				'color' => Color::get_css(
					isset( $attributes['boxColor'] ) ? $attributes['boxColor'] : ''
				),
			],
			[
				'background' => Color::get_css(
					isset( $attributes['boxBackground'] ) ? $attributes['boxBackground'] : ''
				),
			],
			$cssPadding,
			Border::get_css( $attributes['infoBoxBorder'], '', $device ),
			BoxShadow::get_css( $attributes['infoBoxoxShadow'], '', $device ),
			Range::get_css( [
				'attributeValue'       => $attributes['infoBoxWidth'],
				'attribute_object_key' => 'value',
				'isResponsive'         => true,
				'defaultValue'         => 100,
				'hasUnit'              => true,
				'unitDefaultValue'     => '%',
				'property'             => 'width',
				'device'               => $device,
			] )
		);
	}

	public function get_notice_Hover_css( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( $attributes['infoBoxBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['infoBoxoxShadow'], '', $device ),
			[
				'color' => Color::get_css(
					isset( $attributes['boxColorH'] ) ? $attributes['boxColorH'] : ''
				),
			],
			[
				'background' => Color::get_css(
					isset( $attributes['boxBackgroundH'] ) ? $attributes['boxBackgroundH'] : ''
				),
			],
		);
	}

	public function get_notice_massage_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ( isset( $attributes['infoTypographyGlobal'] ) ? $attributes['infoTypographyGlobal'] : '' );

		$typography_value  = isset( $attributes['infoTypography'] ) ?
								Typography::get_css( $attributes['infoTypography'], '', $device, $typographyValueGlobal )
										: array();
		return $typography_value;
	}

	public function get_notice_link_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ( isset( $attributes['linkTypographyGlobal'] ) ? $attributes['linkTypographyGlobal'] : '' );

		$typography_value  = isset( $attributes['linkTypography'] ) ?
								Typography::get_css( $attributes['linkTypography'], '', $device, $typographyValueGlobal )
										: array();
		return array_merge(
			[
				'color' => Color::get_css(
					isset( $attributes['linkColor'] ) ? $attributes['linkColor'] : ''
				),
			],
			$typography_value,
		);
	}

	public function get_notice_link_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css(
				isset( $attributes['linkColorH'] ) ? $attributes['linkColorH'] : ''
			),
		];
	}

	public function get_notice_wrapper_css( $attributes, $device = '' ) {
		$alignment_value = $attributes['infoboxAlignment'][ 'value' . $device ] ?? '';
		$css = [];
		if ( ! empty( $alignment_value ) ) {
			$css['display'] = 'flex';
			$css['width'] = '100%';
			$css['justify-content'] = $alignment_value;
		}
		return $css;
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'product_id' => Helper::get_attribute_value( $attributes, 'product_id' ),
		];

		// phpcs:ignore  WordPress.Security.NonceVerification.Recommended,  WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
		$is_editor = ( isset( $_GET['context'] ) && 'edit' === sanitize_text_field( $_GET['context'] ) );

		$is_custom = $attributes['isCustom'];

		if ( $is_custom ) {
			if ( empty( $attr_array['product_id'] ) && isset( $block_instance->context['postId'] ) && ! empty( $block_instance->context['postId'] ) ) {
				$attr_array['product_id'] = $block_instance->context['postId'];
			}
		} else {
			if ( ! $is_editor && isset( $block_instance->context['postId'] ) && ! empty( $block_instance->context['postId'] ) ) {
				$attr_array['product_id'] = $block_instance->context['postId'];
			}
		}

		if ( $is_editor && empty( $attr_array['product_id'] ) ) {
			return '<p>Only in editor preview. Please Select a Product</p>';
		}

		if ( $is_editor ) {
			$attr_array['dummy'] = true;
		}

		$shortcode = '[storeengine_single_product_cart_notice ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
