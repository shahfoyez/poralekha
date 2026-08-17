<?php

namespace ABlocks\Blocks\StoreengineProductReview;

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
	protected $block_name = 'storeengine-product-review';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings',
			$this->get_feedback_section_css( $attributes ),
			$this->get_feedback_section_css( $attributes, 'Tablet' ),
			$this->get_feedback_section_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings:hover',
			$this->get_feedback_section_hover_css( $attributes ),
			$this->get_feedback_section_hover_css( $attributes, 'Tablet' ),
			$this->get_feedback_section_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item h2',
			$this->get_heading_css( $attributes ),
			$this->get_heading_css( $attributes, 'Tablet' ),
			$this->get_heading_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item h2:hover',
			$this->get_heading_hover_css( $attributes ),
			$this->get_heading_hover_css( $attributes, 'Tablet' ),
			$this->get_heading_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-avg-rating',
			$this->get_avarage_css( $attributes, '' ),
			$this->get_avarage_css( $attributes, 'Tablet' ),
			$this->get_avarage_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-avg-rating:hover',
			$this->get_avarage_hover_css( $attributes, '' ),
			$this->get_avarage_hover_css( $attributes, 'Tablet' ),
			$this->get_avarage_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-avg-rating-html .storeengine-icon,
			{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-avg-rating-html .storeengine-icon:before',
			$this->get_rating_css( $attributes, '' ),
			$this->get_rating_css( $attributes, 'Tablet' ),
			$this->get_rating_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-avg-rating-html .storeengine-icon:hover,
			{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-avg-rating-html .storeengine-icon:hover::before',
			$this->get_rating_hover_css( $attributes, '' ),
			$this->get_rating_hover_css( $attributes, 'Tablet' ),
			$this->get_rating_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-avg-review',
			$this->get_review_text_css( $attributes, '' ),
			$this->get_review_text_css( $attributes, 'Tablet' ),
			$this->get_review_text_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-avg-review:hover',
			$this->get_review_text_hover_css( $attributes, '' ),
			$this->get_review_text_hover_css( $attributes, 'Tablet' ),
			$this->get_review_text_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-fill',
			$this->get_rating_fill_css( $attributes, '' ),
			$this->get_rating_fill_css( $attributes, 'Tablet' ),
			$this->get_rating_fill_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-fill:hover',
			$this->get_rating_fill_hover_css( $attributes, '' ),
			$this->get_rating_fill_hover_css( $attributes, 'Tablet' ),
			$this->get_rating_fill_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-fill,
			{{WEAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-label span,
			{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-label,
			{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-col',
			$this->get_rating_text_css( $attributes, '' ),
			$this->get_rating_text_css( $attributes, 'Tablet' ),
			$this->get_rating_text_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-fill:hover,
			{{WEAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-label span:hover,
			{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-label:hover,
			{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-ratings-list-item-col:hover',
			$this->get_rating_text_hover_css( $attributes, '' ),
			$this->get_rating_text_hover_css( $attributes, 'Tablet' ),
			$this->get_rating_text_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-icon',
			$this->get_feedback_star_css( $attributes, '' ),
			$this->get_feedback_star_css( $attributes, 'Tablet' ),
			$this->get_feedback_star_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-single-product__content-item--feedback .storeengine-product-feedback-ratings .storeengine-ratings-list-item .storeengine-icon:hover',
			$this->get_feedback_star_hover_css( $attributes, '' ),
			$this->get_feedback_star_hover_css( $attributes, 'Tablet' ),
			$this->get_feedback_star_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_feedback_section_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['section_bg'] ) ? $attributes['section_bg'] : '#FFF' ) ],
			Border::get_css( $attributes['border'], '', $device ),
			Dimensions::get_css( $attributes['padding'], 'padding', $device ),
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['section_height'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['section_width'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
		);

	}

	public function get_feedback_section_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['section_bg_hover'] ) ? $attributes['section_bg_hover'] : '#FFF' ) ],
			Border::get_hover_css( $attributes['border'], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
		);
	}

	public function get_heading_css( $attributes, $device = '' ) {
		$typography_global = isset( $attributes['heading_typographyGlobal'] ) ? $attributes['heading_typographyGlobal'] : '';

		$typography_value = ! empty( $attributes['heading_typography'] ) ?
				Typography::get_css( $attributes['heading_typography'], '', $device, $typography_global )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['heading_color'] ) ? $attributes['heading_color'] : '' ) ],
			$typography_value
		);
	}

	public function get_heading_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['heading_hover_color'] ) ? $attributes['heading_hover_color'] : '' ) ];
	}

	public function get_avarage_css( $attributes, $device = '' ) {
		$typography_global = isset( $attributes['avg_typographyGlobal'] ) ? $attributes['avg_typographyGlobal'] : '';

		$typography_value = ! empty( $attributes['avg_typography'] ) ?
				Typography::get_css( $attributes['avg_typography'], '', $device, $typography_global )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['avg_color'] ) ? $attributes['avg_color'] : '#111' ) ],
			$typography_value
		);
	}

	public function get_avarage_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css( isset( $attributes['avg_color_hover'] ) ? $attributes['avg_color_hover'] : '#111' )
		];
	}

	public function get_rating_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['rating_color'] ) ? $attributes['rating_color'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['rating_size'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 16,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'font-size',
				'device' => $device,
			]),
		);
	}

	public function get_rating_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['rating_color_hover'] ) ? $attributes['rating_color_hover'] : '#111' ) ];
	}

	public function get_review_text_css( $attributes, $device = '' ) {
		$typography_global = isset( $attributes['total_typographyGlobal'] )
						? $attributes['total_typographyGlobal'] : '';

		$typography_value = ! empty( $attributes['total_typography'] ) ?
				Typography::get_css( $attributes['total_typography'], '', $device )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['total_rating_color'] ) ? $attributes['total_rating_color'] : '#999' ) ],
			$typography_value
		);
	}

	public function get_review_text_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css( isset( $attributes['total_rating_hover'] ) ? $attributes['total_rating_hover'] : '#999' )
		];
	}

	public function get_rating_fill_css( $attributes, $device = '' ) {
		return [
			'background' => Color::get_css( isset( $attributes['fillBg'] ) ? $attributes['fillBg'] : '#e7e7e7' )
		];
	}

	public function get_rating_fill_hover_css( $attributes, $device = '' ) {
		return [
			'background' => Color::get_css( isset( $attributes['fillBgH'] ) ? $attributes['fillBgH'] : '#e7e7e7' )
		];
	}

	public function get_rating_text_css( $attributes, $device = '' ) {
		$typography_global = isset( $attributes['listTypographyGlobal'] )
		? $attributes['listTypographyGlobal'] : '';

		$typography_value = ! empty( $attributes['listTypography'] ) ?
				Typography::get_css( $attributes['listTypography'], '', $device, $typography_global )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['listColor'] ) ? $attributes['listColor'] : '#999' ) ],
			$typography_value
		);
	}

	public function get_rating_text_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css( isset( $attributes['listColorH'] ) ? $attributes['listColorH'] : '#999' )
		];
	}

	public function get_feedback_star_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['starColor'] ) ? $attributes['starColor'] : '#f4c150' ) ],
			Range::get_css([
				'attributeValue' => $attributes['startSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 16,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'font-size',
				'device' => $device,
			]),
		);
	}

	public function get_feedback_star_hover_css( $attributes, $device = '' ) {
		return [
			'color' => Color::get_css( isset( $attributes['starColorH'] ) ? $attributes['starColorH'] : '#999' )
		];
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

		$shortcode = '[storeengine_single_product_reviews ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );

	}
}
