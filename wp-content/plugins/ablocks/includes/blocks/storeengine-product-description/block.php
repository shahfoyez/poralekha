<?php

namespace ABlocks\Blocks\StoreengineProductDescription;

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
	protected $block_name = 'storeengine-product-description';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .storeengine-product-single__content-item h2',
			$this->get_title_css( $attributes ),
			$this->get_title_css( $attributes, 'Tablet' ),
			$this->get_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .storeengine-product-single__content-item h2:hover',
			$this->get_title_hover_css( $attributes ),
			$this->get_title_hover_css( $attributes, 'Tablet' ),
			$this->get_title_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-product-single__content-item p',
			$this->get_description_css( $attributes ),
			$this->get_description_css( $attributes, 'Tablet' ),
			$this->get_description_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-product-single__content-item p:hover',
			$this->get_description_hover_css( $attributes ),
			$this->get_description_hover_css( $attributes, 'Tablet' ),
			$this->get_description_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_title_css( $attributes, $device = '' ) {
		$typography_global = isset( $attributes['titleTypographyGlobal'] )
							? $attributes['titleTypographyGlobal'] : '';

		$typography_value  = isset( $attributes['titleTypography'] )
			? Typography::get_css( $attributes['titleTypography'], '', $device, $typography_global )
			: array();

			return array_merge(
				Border::get_css( $attributes['border'], '', $device ),
				$typography_value,
				[
					'color' => Color::get_css(
						isset( $attributes['titleColor'] ) ? $attributes['titleColor'] : ''
					),
				]
			);
	}

	public function get_title_hover_css( $attributes, $device = '' ) {
			return array_merge(
				[
					'color' => Color::get_css(
						isset( $attributes['titleColor'] ) ? $attributes['titleColorH'] : ''
					)
				],
				Border::get_hover_css( $attributes['border'], '', $device ),
			);
	}

	public function get_description_css( $attributes, $device = '' ) {
		$typography_global = isset( $attributes['descriptionTypographyGlobal'] )
					? $attributes['descriptionTypographyGlobal'] : '';

		$typography_value  = isset( $attributes['descriptionTypography'] )
			? Typography::get_css( $attributes['descriptionTypography'], '', $device, $typography_global )
			: array();

			return array_merge(
				$typography_value,
				[
					'color' => Color::get_css(
						isset( $attributes['descriptionColor'] ) ? $attributes['descriptionColor'] : ''
					),
				]
			);
	}

	public function get_description_hover_css( $attributes, $device = '' ) {
			return [
				'color' => Color::get_css(
					isset( $attributes['descriptionColor'] ) ? $attributes['descriptionColorH'] : ''
				)
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

		$shortcode = '[storeengine_single_product_description ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );

	}

}
