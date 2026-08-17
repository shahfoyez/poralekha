<?php

namespace ABlocks\Blocks\StoreengineCartButton;

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
	protected $block_name = 'storeengine-cart-button';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-btn--add-to-cart, 
			{{WRAPPER}} .storeengine-btn--add-to-cart-replacement, 
			{{WRAPPER}} .storeengine-btn--direct-checkout, 
			{{WRAPPER}} .storeengine-btn--view-options',
			$this->get_button_css( $attributes ),
			$this->get_button_css( $attributes, 'Tablet' ),
			$this->get_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-btn--add-to-cart:hover, 
			{{WRAPPER}} .storeengine-btn--add-to-cart-replacement:hover, 
			{{WRAPPER}} .storeengine-btn--direct-checkout:hover, 
			{{WRAPPER}} .storeengine-btn--view-options:hover',
			$this->get_button_hover_css( $attributes, '' ),
			$this->get_button_hover_css( $attributes, 'Tablet' ),
			$this->get_button_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-quantity-wrap',
			$this->get_button_alignment_css( $attributes, '' ),
			$this->get_button_alignment_css( $attributes, 'Tablet' ),
			$this->get_button_alignment_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-add-to-cart-shortcode .storeengine-price.amount',
			$this->get_price_css( $attributes, '' ),
			$this->get_price_css( $attributes, 'Tablet' ),
			$this->get_price_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-add-to-cart-shortcode .storeengine-price.amount:hover',
			$this->get_price_hover_css( $attributes, '' ),
			$this->get_price_hover_css( $attributes, 'Tablet' ),
			$this->get_price_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-price-name,
			{{WRAPPER}} .storeengine-loop-product-price-summery .storeengine-loop-product-price-label',
			$this->get_price_name_css( $attributes, '' ),
			$this->get_price_name_css( $attributes, 'Tablet' ),
			$this->get_price_name_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-price-name:hover,
			{{WRAPPER}} .storeengine-loop-product-price-summery .storeengine-loop-product-price-label:hover',
			$this->get_price_name_hover_css( $attributes, '' ),
			$this->get_price_name_hover_css( $attributes, 'Tablet' ),
			$this->get_price_name_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single__amount .storeengine-dropdown,
			{{WRAPPER}} .storeengine-single__amount .storeengine-product__multi-prices,
			{{WRAPPER}} .storeengine-dropdown.open .storeengine-dropdown-content .storeengine-product__multi-price,
			{{WRAPPER}} .storeengine-dropdown.open .storeengine-dropdown-content .storeengine-product__multi-price label,
			{{WRAPPER}} .storeengine-single-product-prices',
			$this->get_box_css( $attributes, '' ),
			$this->get_box_css( $attributes, 'Tablet' ),
			$this->get_box_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single__amount .storeengine-dropdown:hover,
			{{WRAPPER}} .storeengine-single__amount .storeengine-product__multi-prices:hover,
			{{WRAPPER}} .storeengine-dropdown.open .storeengine-dropdown-content .storeengine-product__multi-price:hover,
			{{WRAPPER}} .storeengine-dropdown.open .storeengine-dropdown-content .storeengine-product__multi-price label:hover,
			{{WRAPPER}} .storeengine-single-product-prices:hover',
			$this->get_box_hover_css( $attributes, '' ),
			$this->get_box_hover_css( $attributes, 'Tablet' ),
			$this->get_box_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-price-summery .storeengine-single-product-price-label input[type=radio]',
			$this->get_radio_css( $attributes, '' ),
			$this->get_radio_css( $attributes, 'Tablet' ),
			$this->get_radio_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single__amount',
			$this->get_alignment_css( $attributes, '' ),
			$this->get_alignment_css( $attributes, 'Tablet' ),
			$this->get_alignment_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-add-to-cart-shortcode',
			$this->get_gap_css( $attributes, '' ),
			$this->get_gap_css( $attributes, 'Tablet' ),
			$this->get_gap_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product-prices .storeengine-single-product-price,
			{{WRAPPER}} .storeengine-product__multi-prices .storeengine-dropdown__toggle,
			{{WRAPPER}} .storeengine-single__amount .storeengine-product__multi-prices',
			$this->get_price_box_css( $attributes, '' ),
			$this->get_price_box_css( $attributes, 'Tablet' ),
			$this->get_price_box_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_button_css( $attributes, $device = '' ) {
		$css            = [];
		$cssPadding     = [];
		$alignment_css  = [];
		$typographyValueGlobal = ( isset( $attributes['btn_typographyGlobal'] ) ? $attributes['btn_typographyGlobal'] : '' );
		$typography_value  = isset( $attributes['btn_typography'] ) ? $attributes['btn_typography'] : [];

		if ( ! empty( $attributes['padding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['padding'], 'padding', $device );
		}

		if ( ! empty( $attributes['buttonTextAlign'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['buttonTextAlign'][ 'value' . $device ];
		}

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['button_color'] ) ? $attributes['button_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['button_bg'] ) ? $attributes['button_bg'] : '' ) ],
			$cssPadding,
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			$alignment_css,
			Border::get_css( $attributes['buttonBorder'], '', $device ),
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css( [
				'attributeValue'       => $attributes['button_width'],
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

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['button_color_hover'] ) ? $attributes['button_color_hover'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['button_bg_hover'] ) ? $attributes['button_bg_hover'] : '' ) ],
			Border::get_hover_css( $attributes['buttonBorder'] ?? [], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
		);
	}

	public function get_button_alignment_css( $attributes, $device = '' ) {
		$alignment_css = [];

		if ( ! empty( $attributes['buttonAlign'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['buttonAlign'][ 'value' . $device ];
		}

		return $alignment_css;
	}

	public function get_price_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ( isset( $attributes['priceTypographyGlobal'] ) ? $attributes['priceTypographyGlobal'] : '' );
		$typography_value  = isset( $attributes['priceTypography'] ) ? $attributes['priceTypography'] : [];
		return array_merge(
			$typography_value,
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			[
				'color' => Color::get_css(
					isset( $attributes['priceColor'] ) ? $attributes['priceColor'] : ''
				),
			]
		);
	}

	public function get_price_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css(
				isset( $attributes['priceColorH'] ) ? $attributes['priceColorH'] : ''
			),
		];
	}

	public function get_price_name_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ( isset( $attributes['priceNameTypographyGlobal'] ) ? $attributes['priceNameTypographyGlobal'] : '' );
		$typography_value  = isset( $attributes['priceNameTypography'] ) ? $attributes['priceNameTypography'] : [];

		return array_merge(
			$typography_value,
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			[
				'color' => Color::get_css(
					isset( $attributes['priceNameColor'] ) ? $attributes['priceNameColor'] : ''
				),
			]
		);
	}

	public function get_price_name_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css(
				isset( $attributes['priceNameColorH'] ) ? $attributes['priceNameColorH'] : ''
			),
		];
	}

	public function get_box_css( $attributes, $device = '' ) {
		return array_merge(
			[
				'background' => Color::get_css(
				isset( $attributes['boxBackground'] ) ? $attributes['boxBackground'] : ''),
			],
			Range::get_css( [
				'attributeValue'       => $attributes['boxWidth'],
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

	public function get_radio_css( $attributes, $device = '' ) {
		return array_merge(
			Range::get_css([
				'attributeValue'       => $attributes['radioWidth'],
				'attribute_object_key' => 'value',
				'isResponsive'         => true,
				'defaultValue'         => 15,
				'hasUnit'              => true,
				'unitDefaultValue'     => 'px',
				'property'             => 'width',
				'device'               => $device,
			]),
			Range::get_css([
				'attributeValue'       => $attributes['radioHeight'],
				'attribute_object_key' => 'value',
				'isResponsive'         => true,
				'defaultValue'         => 15,
				'hasUnit'              => true,
				'unitDefaultValue'     => 'px',
				'property'             => 'height',
				'device'               => $device,
			])
		);
	}

	public function get_alignment_css( $attributes, $device = '' ) {
		$alignment_css = [];
		$css['display'] = 'flex';
		if ( ! empty( $attributes['priceAlign'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['priceAlign'][ 'value' . $device ];
		}

		return array_merge(
			$alignment_css,
			$css,
		);
	}


	public function get_box_hover_css( $attributes, $device = '' ) {
		return [
			'background' => Color::get_css(
			isset( $attributes['boxBackgroundH'] ) ? $attributes['boxBackgroundH'] : ''),
		];
	}

	public function get_gap_css( $attributes, $device = '' ) {
		$css_flex['display'] = 'flex';
		$css_direction['flex-direction'] = 'column';

		return array_merge(
			$css_direction,
			$css_flex,
			Range::get_css([
				'attributeValue' => $attributes['elementGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 12,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
		);
	}

	public function get_price_box_css( $attributes, $device = '' ) {
		if ( ! empty( $attributes['boxPadding'] ) ) {
			$css_padding = Dimensions::get_css( $attributes['boxPadding'], 'padding', $device );
		}

		return array_merge(
			$css_padding,
			Border::get_css( $attributes['boxBorder'], '', $device ),
		);

	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'label'           => Helper::get_attribute_value( $attributes, 'label' ),
			'product_id'      => Helper::get_attribute_value( $attributes, 'product_id' ),
			'price_id'        => Helper::get_attribute_value( $attributes, 'price_id' ),
			'variation_id'    => Helper::get_attribute_value( $attributes, 'variation_id' ),
			'direct_checkout' => Helper::get_attribute_value( $attributes, 'direct_checkout' ) ? 'true' : 'false',
			'quantity'        => Helper::get_attribute_value( $attributes, 'quantity' ),
			'show_quantity'   => Helper::get_attribute_value( $attributes, 'show_quantity' ),
			'disabled'        => Helper::get_attribute_value( $attributes, 'disabled' ),
			'price_display'   => Helper::get_attribute_value( $attributes, 'price_display' ),
			'button_display'  => Helper::get_attribute_value( $attributes, 'button_display' ),
		];

		if ( isset( $block_instance->context['postId'] ) && ! empty( $block_instance->context['postId'] ) && empty( $attr_array['product_id'] ) ) {
			$attr_array['product_id'] = $block_instance->context['postId'];
		}

		// phpcs:ignore  WordPress.Security.NonceVerification.Recommended,  WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
		if ( isset( $_GET['context'] ) && 'edit' === sanitize_text_field( $_GET['context'] ) ) {
			$attr_array['dummy'] = true;
		}

		$shortcode = '[storeengine_add_to_cart ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
