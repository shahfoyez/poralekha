<?php

namespace ABlocks\Blocks\StoreengineProductSummary;

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
	protected $block_name = 'storeengine-product-summary';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single__title',
			$this->get_product_title_css( $attributes ),
			$this->get_product_title_css( $attributes, 'Tablet' ),
			$this->get_product_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single__title:hover',
			$this->get_product_title_hover_css( $attributes ),
			$this->get_product_title_hover_css( $attributes, 'Tablet' ),
			$this->get_product_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-simple-price .storeengine-price',
			$this->get_product_price_css( $attributes ),
			$this->get_product_price_css( $attributes, 'Tablet' ),
			$this->get_product_price_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-simple-price .storeengine-price:hover',
			$this->get_product_price_hover_css( $attributes ),
			$this->get_product_price_hover_css( $attributes, 'Tablet' ),
			$this->get_product_price_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-quantity input[type=number]',
			$this->get_input_box_css( $attributes ),
			$this->get_input_box_css( $attributes, 'Tablet' ),
			$this->get_input_box_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-quantity input[type=number]:hover',
			$this->get_input_box_hover_css( $attributes ),
			$this->get_input_box_hover_css( $attributes, 'Tablet' ),
			$this->get_input_box_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-btn--direct-checkout',
			$this->get_button_css( $attributes ),
			$this->get_button_css( $attributes, 'Tablet' ),
			$this->get_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-btn--direct-checkout:hover',
			$this->get_button_hover_css( $attributes ),
			$this->get_button_hover_css( $attributes, 'Tablet' ),
			$this->get_button_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-ajax-add-to-cart-form__entry-footer .storeengine-btn--add-to-cart',
			$this->get_add_button_css( $attributes ),
			$this->get_add_button_css( $attributes, 'Tablet' ),
			$this->get_add_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-ajax-add-to-cart-form__entry-footer .storeengine-btn--add-to-cart:hover',
			$this->get_add_hover_button_css( $attributes ),
			$this->get_add_hover_button_css( $attributes, 'Tablet' ),
			$this->get_add_hover_button_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_button_css( $attributes, $device = '' ) {
		$css               = [];
		$css['color']      = $attributes['buttonColor'] ?? '';
		$css['background'] = $attributes['buttonBackground'] ?? '';
		$alignment_css = [];
		$typography_global = isset( $attributes['btnTypographyGlobal'] ) ? $attributes['btnTypographyGlobal'] : '';

		$typography_value  = isset( $attributes['btnTypography'] ) ? Typography::get_css( $attributes['btnTypography'], '', $device, $typography_global ) : array();

		if ( ! empty( $attributes['buttonPadding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['buttonPadding'], 'padding', $device );
		}

		if ( ! empty( $attributes['buttonTextAlign'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['buttonTextAlign'][ 'value' . $device ];
		}

		return array_merge(
			$css,
			$typography_value,
			$cssPadding,
			$alignment_css,
			Border::get_css( $attributes['buttonBorder'], '', $device ),
			// BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css( [
				'attributeValue'       => $attributes['buttonWidth'],
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

	public function get_button_hover_css( $attributes, $device = '' ) {
		$css               = [];
		$css['color']      = $attributes['buttonColorH'] ?? '';
		$css['background'] = $attributes['buttonBackgroundH'] ?? '';

		return array_merge(
			$css,
			Border::get_hover_css( $attributes['buttonBorder'] ?? [], '', $device ),
			// BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
		);
	}

	public function get_add_button_css( $attributes, $device = '' ) {
		$css               = [];
		$css['color']      = $attributes['AddButtonColor'] ?? '';
		$css['background'] = $attributes['AddButtonBackground'] ?? '';
		$alignment_css = [];
		$typography_global = isset( $attributes['AddBtnTypographyGlobal'] ) ? $attributes['AddBtnTypographyGlobal'] : '';

		$typography_value  = isset( $attributes['AddBtnTypography'] ) ? Typography::get_css( $attributes['btnTypography'], '', $device, $typography_global ) : array();

		if ( ! empty( $attributes['buttonPadding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['AddButtonPadding'], 'padding', $device );
		}

		// if ( ! empty( $attributes['buttonTextAlign'][ 'value' . $device ] ) ) {
		// $alignment_css['justify-content'] = $attributes['uttonTextAlign'][ 'value' . $device ];
		// }

		return array_merge(
			$css,
			$typography_value,
			$cssPadding,
			$alignment_css,
			Border::get_css( $attributes['AddButtonBorder'], '', $device ),
			// BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css( [
				'attributeValue'       => $attributes['AddButtonWidth'],
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

	public function get_add_hover_button_css( $attributes, $device = '' ) {
		$css               = [];
		$css['color']      = $attributes['buttonColorH'] ?? '';
		$css['background'] = $attributes['buttonBackgroundH'] ?? '';

		return array_merge(
			$css,
			Border::get_hover_css( $attributes['buttonBorder'] ?? [], '', $device ),
			// BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
		);
	}

	public function get_product_title_css( $attributes, $device = '' ) {
		$typography_global = isset( $attributes['titleTypographyGlobal'] ) ? $attributes['titleTypographyGlobal'] : '';

		$typography_value  = isset( $attributes['titleTypography'] ) ? Typography::get_css( $attributes['titleTypography'], '', $device, $typography_global ) : array();

		return array_merge(
			$typography_value,
			[
				'color' => Color::get_css(
					isset( $attributes['titleColor'] ) ? $attributes['titleColor'] : ''
				),
			]
		);
	}

	public function get_product_title_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css(
				isset( $attributes['titleColorH'] ) ? $attributes['titleColorH'] : ''
			),
		];
	}

	public function get_product_price_css( $attributes, $device = '' ) {
		$typography_global = isset( $attributes['ProductPriceTypographyGlobal'] ) ? $attributes['ProductPriceTypographyGlobal'] : '';

		$typography_value  = isset( $attributes['ProductPriceTypography'] ) ? Typography::get_css( $attributes['ProductPriceTypography'], '', $device, $typography_global ) : array();

		return array_merge(
			$typography_value,
			[
				'color' => Color::get_css(
					isset( $attributes['productPriceColor'] ) ? $attributes['productPriceColor'] : ''
				),
			]
		);
	}

	public function get_product_price_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css(
				isset( $attributes['productPriceColorH'] ) ? $attributes['productPriceColorH'] : ''
			),
		];
	}

	public function get_input_box_css( $attributes, $device = '' ) {
		$typography_value  = isset( $attributes['inputTextTypography'] ) ? Typography::get_css( $attributes['inputTextTypography'], '', $device ) : array();
		if ( ! empty( $attributes['inputPadding'] ) ) {
			$css_padding = Dimensions::get_css( $attributes['inputPadding'], 'padding', $device );
		}

		return array_merge(
			$typography_value,
			$css_padding,
			Border::get_css( $attributes['inputBorder'], '', $device ),
			[
				'color' => Color::get_css(
					isset( $attributes['inputTextColor'] ) ? $attributes['inputTextColor'] : ''
				),
			],
			Range::get_css( [
				'attributeValue'       => $attributes['inputWidth'],
				'attribute_object_key' => 'value',
				'isResponsive'         => true,
				'defaultValue'         => 50,
				'hasUnit'              => true,
				'unitDefaultValue'     => 'px',
				'property'             => 'width',
				'device'               => $device,
			] ),
		);
	}

	public function get_input_box_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[
				'color' => Color::get_css(
					isset( $attributes['inputTextColorH'] ) ? $attributes['inputTextColorH'] : ''
				),
			],
			Border::get_hover_css( $attributes['inputBorder'] ?? [], '', $device ),
		);
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

		$shortcode = '[storeengine_single_product_summary ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
