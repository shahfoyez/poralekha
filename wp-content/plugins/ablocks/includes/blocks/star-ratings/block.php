<?php
namespace ABlocks\Blocks\StarRatings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'star-ratings';

	public function build_css_v1( $attributes ) {

		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container',
			$this->get_container_css( $attributes ),
			$this->get_container_css( $attributes, 'Tablet' ),
			$this->get_container_css( $attributes, 'Mobile' ),
		);
		// rating icon css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-rating',
			$this->get_rating_css( $attributes ),
			$this->get_rating_css( $attributes, 'Tablet' ),
			$this->get_rating_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);

		// rating icon spacing css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-star-ratings-icons',
			$this->get_rating_icon_spacing_css( $attributes ),
			$this->get_rating_icon_spacing_css( $attributes, 'Tablet' ),
			$this->get_rating_icon_spacing_css( $attributes, 'Mobile' ),
		);

		$get_desktop_rating_number_css = $this->get_rating_number_css( $attributes );
		if ( ! empty( $attributes['ratingNumberColor'] ) ) {
			$get_desktop_rating_number_css['color'] = $attributes['ratingNumberColor'];
		}
		if ( ! empty( $attributes['ratingNumberPosition'] ) ) {
			if ( 'left' === $attributes['ratingNumberPosition'] ) {
				$get_desktop_rating_number_css['order'] = '-5';
			} else {
				$get_desktop_rating_number_css['order'] = '10';
			}
		}
		// rating number css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-star-rating-number',
			$get_desktop_rating_number_css,
			$this->get_rating_number_css( $attributes, 'Tablet' ),
			$this->get_rating_number_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {

		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}:not(.ablocks-has-block-container), {{WRAPPER}}.ablocks-block--star-ratings > .ablocks-block-container',
			$this->get_container_css( $attributes ),
			$this->get_container_css( $attributes, 'Tablet' ),
			$this->get_container_css( $attributes, 'Mobile' ),
		);
		// rating icon css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-rating',
			$this->get_rating_css( $attributes ),
			$this->get_rating_css( $attributes, 'Tablet' ),
			$this->get_rating_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);

		// rating icon spacing css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-star-ratings-icons',
			$this->get_rating_icon_spacing_css( $attributes ),
			$this->get_rating_icon_spacing_css( $attributes, 'Tablet' ),
			$this->get_rating_icon_spacing_css( $attributes, 'Mobile' ),
		);
		// rating number css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-star-rating-number',
			$this->get_rating_number_css( $attributes ),
			$this->get_rating_number_css( $attributes, 'Tablet' ),
			$this->get_rating_number_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}


	public function get_container_css( $attributes, $device = '' ) {
		$css = [];
		$css['display'] = 'flex';
		$css['align-items'] = 'center';
		$css['flex-wrap'] = 'wrap';
		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['ratingNumberGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 0,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
			isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'justify-content', $device ) : [],
		);
	}

	public function get_rating_number_css( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['ratingNumberPosition'] ) ) {
			if ( 'left' === $attributes['ratingNumberPosition'] ) {
				$css['order'] = '-5';
			} else {
				$css['order'] = '10';
			}
		}
		$typographyValueGlobal = ! empty( $attributes['ratingNumberTypographyGlobal'] ) ? $attributes['ratingNumberTypographyGlobal'] : array();
		// rating number css
			return array_merge(
				[ 'color' => Color::get_css( isset( $attributes['ratingNumberColor'] ) ? $attributes['ratingNumberColor'] : '' ) ],
				Typography::get_css( $attributes['ratingNumberTypography'], '', $device, $typographyValueGlobal )
			);

	}

	public function get_rating_css( $attributes, $device = '' ) {
		$rating_css = [];
		$size_value = $attributes['size'][ 'value' . $device ] ?? '';
		$size_unit = $attributes['size'][ 'valueUnit' . $device ] ?? 'px';
		$unit = ! empty( $attributes['gap'][ 'valueUnit' . $device ] ) ? $attributes['gap'][ 'valueUnit' . $device ] : 'px';

		if ( ! empty( $size_value ) ) {
			$rating_css['font-size'] = $size_value . $size_unit;
		}
		return $rating_css;
	}


	public function get_rating_icon_spacing_css( $attributes, $device = '' ) {
		$spacing_value = Range::get_css([
			'attributeValue' => $attributes['spacing'],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 0,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'gap',
			'device' => $device,
		]);
		return $spacing_value;
	}
}
