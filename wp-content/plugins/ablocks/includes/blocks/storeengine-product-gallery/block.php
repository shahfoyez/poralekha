<?php

namespace ABlocks\Blocks\StoreengineProductGallery;

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
	protected $block_name = 'storeengine-product-gallery';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-gallery_wrapper .storeengine-col-12 .storeengine-placeholder,
			{{WRAPPER}} .is-layout-constrained .storeengine-gallery_wrapper .storeengine-col-12 img,
			{{WRAPPER}} .is-layout-constrained .storeengine-gallery_wrapper .carousel-main .flickity-slider img',
			$this->get_gallary_image_css( $attributes ),
			$this->get_gallary_image_css( $attributes, 'Tablet' ),
			$this->get_gallary_image_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-gallery_wrapper .storeengine-row .storeengine-col-12',
			$this->get_gallary_image_container_css( $attributes ),
			$this->get_gallary_image_container_css( $attributes, 'Tablet' ),
			$this->get_gallary_image_container_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-gallery_wrapper .storeengine-col-12 .storeengine-placeholder:hover',
			$this->get_gallary_image_hover_css( $attributes ),
			$this->get_gallary_image_hover_css( $attributes, 'Tablet' ),
			$this->get_gallary_image_hover_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}

	public function get_gallary_image_css( $attributes, $device = '' ) {
		$css = [];
		$css['opacity']  = $attributes['imageOpacity'] ?? '';
		if ( ! empty( $alignment_value ) ) {
			$css['display'] = 'flex';
			$css['width'] = '100%';
			$css['justify-content'] = $alignment_value;
		}
		return array_merge(
			$css,
			isset( $attributes['border'] ) ? Border::get_css( $attributes['border'], '', $device ) : [],
			isset( $attributes['boxShadow'] ) ? BoxShadow::get_css( $attributes['boxShadow'], $device ) : [],
			Range::get_css([
				'attributeValue' => $attributes['imageWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['imageHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'height',
				'device' => $device,
			]),
		);

	}

	public function get_gallary_image_container_css( $attributes, $device = '' ) {
		$alignment_value = $attributes['alignment'][ 'value' . $device ] ?? '';
		$css = [];
		return $css;
	}

	public function get_gallary_image_hover_css( $attributes, $device = '' ) {
		$css = [];
		$css['opacity']  = $attributes['imageOpacityH'] ?? '';

		return array_merge(
			isset( $attributes['border'] ) ? Border::get_hover_css( $attributes['border'], '', $device ) : [],
			isset( $attributes['boxShadow'] ) ? BoxShadow::get_hover_css( $attributes['boxShadow'], $device ) : [],
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

		$shortcode = '[storeengine_single_product_gallery ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
