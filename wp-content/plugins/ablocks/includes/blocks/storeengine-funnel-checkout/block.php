<?php

namespace ABlocks\Blocks\StoreengineFunnelCheckout;

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
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-funnel-checkout';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		return $css_generator->generate_css();
	}

	public function build_css_v2( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		// error_log(print_r($attributes));

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-summary .storeengine-ajax-checkout-form__title',
			$this->get_order_details_css( $attributes ),
			$this->get_order_details_css( $attributes, 'Tablet' ),
			$this->get_order_details_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-summary .storeengine-ajax-checkout-form__title:hover',
			$this->get_order_details_hover_css( $attributes ),
			$this->get_order_details_hover_css( $attributes, 'Tablet' ),
			$this->get_order_details_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-item-entry-left img',
			$this->get_order_details_image_css( $attributes ),
			$this->get_order_details_image_css( $attributes, 'Tablet' ),
			$this->get_order_details_image_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-item h6 a',
			$this->get_order_details_product_title_css( $attributes ),
			$this->get_order_details_product_title_css( $attributes, 'Tablet' ),
			$this->get_order_details_product_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-item h6 a:hover',
			$this->get_order_details_product_title_hover_css( $attributes ),
			$this->get_order_details_product_title_hover_css( $attributes, 'Tablet' ),
			$this->get_order_details_product_title_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-item__price',
			$this->get_order_details_price_css( $attributes ),
			$this->get_order_details_price_css( $attributes, 'Tablet' ),
			$this->get_order_details_price_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-item__price:hover',
			$this->get_order_details_price_hover_css( $attributes ),
			$this->get_order_details_price_hover_css( $attributes, 'Tablet' ),
			$this->get_order_details_price_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-summary-shortcode .storeengine-order-summary__item .storeengine-order-item-entry-right .storeengine-order-item__price .storeengine-order-item__price-regular',
			$this->get_order_details_regular_price_css( $attributes ),
			$this->get_order_details_regular_price_css( $attributes, 'Tablet' ),
			$this->get_order_details_regular_price_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-summary-shortcode .storeengine-order-summary__item .storeengine-order-item-entry-right .storeengine-order-item__price .storeengine-order-item__price-regular:hover',
			$this->get_order_details_price_regular_hover_css( $attributes ),
			$this->get_order_details_price_regular_hover_css( $attributes, 'Tablet' ),
			$this->get_order_details_price_regular_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-item__qty,
			 {{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-order-item__qty span',
			$this->get_order_details_quality_css( $attributes ),
			$this->get_order_details_quality_css( $attributes, 'Tablet' ),
			$this->get_order_details_quality_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-summary-shortcode .storeengine-order-summary__item .storeengine-order-item-entry-right .storeengine-order-item__qty:hover,
			 {{WRAPPER}} .storeengine-order-summary-shortcode .storeengine-order-summary__item .storeengine-order-item-entry-right .storeengine-order-item__qty span:hover',
			$this->get_order_details_quality_hover_css( $attributes ),
			$this->get_order_details_quality_hover_css( $attributes, 'Tablet' ),
			$this->get_order_details_quality_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-cart-sub-total-table,
			 {{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-cart-sub-total-table td',
			$this->get_table_text_css( $attributes ),
			$this->get_table_text_css( $attributes, 'Tablet' ),
			$this->get_table_text_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-cart-sub-total-table:hover,
			 {{WRAPPER}} .storeengine-funnel-checkout-shortcode .storeengine-cart-sub-total-table td:hover',
			$this->get_table_text_hover_css( $attributes ),
			$this->get_table_text_hover_css( $attributes, 'Tablet' ),
			$this->get_table_text_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_order_details_css( $attributes, $device = '' ) {
		$title_typography_css = ! empty( $attributes['titleTypography'] ) ? $attributes['titleTypography'] : [];
		$typographyGlobal = ( isset( $attributes['titleTypographyGlobal'] ) ? $attributes['titleTypographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['titleColor'] ) ? $attributes['titleColor'] : '' ) ],
			Typography::get_css( $title_typography_css, '', $device, $typographyGlobal )
		);
	}

	public function get_order_details_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['titleColorH'] ) ? $attributes['titleColorH'] : '' ) ],
		);
	}

	public function get_order_details_image_css( $attributes, $device = '' ) {
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['imageWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['imageHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
		);

	}

	public function get_order_details_product_title_css( $attributes, $device = '' ) {
		$title_typography_css = ! empty( $attributes['ProductTitleTypography'] ) ? $attributes['ProductTitleTypography'] : [];
		$typographyGlobal = ( isset( $attributes['ProductTitleTypographyGlobal'] ) ? $attributes['ProductTitleTypographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['productTitleColor'] ) ? $attributes['productTitleColor'] : '' ) ],
			Typography::get_css( $title_typography_css, '', $device, $typographyGlobal )
		);
	}

	public function get_order_details_product_title_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['productTitleColorH'] ) ? $attributes['productTitleColorH'] : '' ) ];
	}

	public function get_order_details_price_css( $attributes, $device = '' ) {
		$typography_value = isset( $attributes['discountPriceTypography'] ) ? $attributes['discountPriceTypography'] : [];
		$typographyGlobal = ( isset( $attributes['discountPriceTypographyGlobal'] ) ? $attributes['discountPriceTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['discountPriceColor'] ) ? $attributes['discountPriceColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyGlobal )
		);
	}

	public function get_order_details_price_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['discountPriceColorH'] ) ? $attributes['discountPriceColorH'] : '' ) ];

	}

	public function get_order_details_regular_price_css( $attributes, $device = '' ) {
		$typography_value = isset( $attributes['regularPriceTypography'] ) ? $attributes['regularPriceTypography'] : [];
		$typographyGlobal = ( isset( $attributes['regularPriceTypographyGlobal'] ) ? $attributes['regularPriceTypographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['regularPriceColor'] ) ? $attributes['regularPriceColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyGlobal )
		);
	}

	public function get_order_details_price_regular_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['regularPriceColorH'] ) ? $attributes['regularPriceColorH'] : '' ) ];

	}

	public function get_order_details_quality_css( $attributes, $device = '' ) {
		$typography_value = isset( $attributes['qualityTypography'] ) ? $attributes['qualityTypography'] : [];
		$typographyGlobal = ( isset( $attributes['qualityTypographyGlobal'] ) ? $attributes['qualityTypographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['qualityColor'] ) ? $attributes['qualityColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyGlobal )
		);
	}

	public function get_order_details_quality_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['qualityColorH'] ) ? $attributes['qualityColorH'] : '' ) ];

	}

	public function get_table_text_css( $attributes, $device = '' ) {
		$typography_value = isset( $attributes['tableTextTypography'] ) ? $attributes['tableTextTypography'] : [];
		$typographyGlobal = ( isset( $attributes['tableTextTypographyGlobal'] ) ? $attributes['tableTextTypographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['tableTextColor'] ) ? $attributes['tableTextColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyGlobal )
		);
	}
	public function get_table_text_hover_css( $attributes, $device = '' ) {

		return [ 'color' => Color::get_css( isset( $attributes['tableTextColorH'] ) ? $attributes['tableTextColorH'] : '#000' ) ];
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			// Order summary is on by default; the block toggle can hide it.
			'summary' => ( isset( $attributes['showSummary'] ) && false === $attributes['showSummary'] ) ? 'no' : 'yes',
		];

		// phpcs:ignore  WordPress.Security.NonceVerification.Recommended,  WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( isset( $_GET['context'] ) && 'edit' === sanitize_text_field( $_GET['context'] ) ) {
			$attr_array['dummy'] = true;
		}

		$shortcode = '[storeengine_funnel_checkout ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}
}

