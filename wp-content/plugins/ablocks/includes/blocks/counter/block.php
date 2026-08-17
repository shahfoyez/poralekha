<?php
namespace ABlocks\Blocks\Counter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'counter';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		// wrapper css
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--number .ablocks-block-container',
			$this->get_number_wrapper_css( $attributes ),
			$this->get_number_wrapper_css( $attributes, 'Tablet' ),
			$this->get_number_wrapper_css( $attributes, 'Mobile' ),
		);
		// number text css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-counter__content',
			$this->get_number_text_css( $attributes ),
			$this->get_number_text_css( $attributes, 'Tablet' ),
			$this->get_number_text_css( $attributes, 'Mobile' ),
		);
		// heading text css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-counter__text',
			$this->get_heading_text_css( $attributes ),
			$this->get_heading_text_css( $attributes, 'Tablet' ),
			$this->get_heading_text_css( $attributes, 'Mobile' ),
		);
		// counter bar css

		// counter circle css
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--bar .ablocks-bar-counter__background',
			$this->get_counter_bar_bg_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--bar .ablocks-bar-counter__progress',
			$this->get_counter_bar_progress_css( $attributes ),
			$this->get_counter_bar_progress_css( $attributes, 'Tablet' ),
			$this->get_counter_bar_progress_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--circle',
			$this->get_counter_circle_css( $attributes ),
			$this->get_counter_circle_css( $attributes, 'Tablet' ),
			$this->get_counter_circle_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--circle .ablocks-circle-counter__background',
			$this->get_counter_circle_bg_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--circle .ablocks-circle-counter__progress',
			$this->get_counter_circle_progress_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
				$css_generator->add_class_styles(
					'{{WRAPPER}} .ablocks-icon-wrap:hover',
					Icon::get_wrapper_hover_css( $attributes ),
					Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
					Icon::get_wrapper_hover_css( $attributes, 'Mobile' )
				);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		// wrapper css
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--bar:not(.ablocks-has-container),
			{{WRAPPER}}.ablocks-block--counter--bar .ablocks-block-container',
			$this->get_counter_bar_css( $attributes ),
			$this->get_counter_bar_css( $attributes, 'Tablet' ),
			$this->get_counter_bar_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--number:not(.ablocks-has-container),
			 {{WRAPPER}}.ablocks-block--counter--number .ablocks-block-container',
			$this->get_number_wrapper_css( $attributes ),
			$this->get_number_wrapper_css( $attributes, 'Tablet' ),
			$this->get_number_wrapper_css( $attributes, 'Mobile' ),
		);
		// number text css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-counter__content',
			$this->get_number_text_css( $attributes ),
			$this->get_number_text_css( $attributes, 'Tablet' ),
			$this->get_number_text_css( $attributes, 'Mobile' ),
		);
		// heading text css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-counter__text',
			$this->get_heading_text_css( $attributes ),
			$this->get_heading_text_css( $attributes, 'Tablet' ),
			$this->get_heading_text_css( $attributes, 'Mobile' ),
		);
		// counter bar css

		// counter circle css
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--bar .ablocks-bar-counter__background',
			$this->get_counter_bar_bg_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--bar .ablocks-bar-counter__progress',
			$this->get_counter_bar_progress_css( $attributes ),
			$this->get_counter_bar_progress_css( $attributes, 'Tablet' ),
			$this->get_counter_bar_progress_css( $attributes, 'Mobile' )
		);

		// double for transform rotate issue in frontend
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--circle:not(.ablocks-has-block-container)',
			$this->get_counter_circle_css( $attributes ),
			$this->get_counter_circle_css( $attributes, 'Tablet' ),
			$this->get_counter_circle_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container',
			$this->get_counter_circle_css( $attributes ),
			$this->get_counter_circle_css( $attributes, 'Tablet' ),
			$this->get_counter_circle_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--circle .ablocks-circle-counter__background',
			$this->get_counter_circle_bg_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--counter--circle .ablocks-circle-counter__progress',
			$this->get_counter_circle_progress_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_counter_bar_css( $attributes, $device = '' ) {
		$css = [];

		$alignment = isset( $attributes['alignment'] ) ? $attributes['alignment'] : [];
		$device_key = 'value' . $device;

		$alignment_value = isset( $alignment[ $device_key ] ) ? $alignment[ $device_key ] : ( $alignment['value'] ?? '' );

		if ( $alignment_value ) {
			$css['align-items'] = $alignment_value;
		}

		return $css;
	}

	public function get_number_wrapper_css( $attributes, $device = '' ) {
		$counterNumberWrapperCSS = [];
		$alignment = isset( $attributes['alignment'][ 'value' . $device ] )
			? $attributes['alignment'][ 'value' . $device ]
			: '';

		if ( $alignment ) {
			$counterNumberWrapperCSS['justify-content'] = $alignment;

			if ( $alignment === 'center' ) {
				$counterNumberWrapperCSS['text-align'] = 'center';
			} elseif ( $alignment === 'flex-end' ) {
				$counterNumberWrapperCSS['text-align'] = 'right';
			}
		}
		return $counterNumberWrapperCSS;
	}

	public function get_number_text_css( $attributes, $device = '' ) {
		$css = [];
		if ( isset( $attributes['mediaPosition'] ) && $attributes['mediaPosition'] === 'leftOfNumber' || isset( $attributes['mediaPosition'] ) && $attributes['mediaPosition'] === 'rightOfNumber' || isset( $attributes['layout'] ) && $attributes['layout'] === 'bar' ) {
			$css['display'] = 'inline-flex';
			$css['align-items'] = 'center';
			$css['justify-content'] = 'center';
		} else {
			$css['display'] = 'block';
		}
		$typographyGlobal = ( isset( $attributes['numberTypographyGlobal'] ) ? $attributes['numberTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['numberColor'] ) ? $attributes['numberColor'] : '' ) ],
			isset( $attributes['numberTypography'] ) ? Typography::get_css( $attributes['numberTypography'], '', $device, $typographyGlobal ) : [],
			isset( $attributes['numberMargin'] ) ? Dimensions::get_css( $attributes['numberMargin'], 'margin', '', $device ) : [],
			$css
		);
	}

	public function get_heading_text_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['headingTypographyGlobal'] ) ? $attributes['headingTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['headingColor'] ) ? $attributes['headingColor'] : '' ) ],
			isset( $attributes['headingTypography'] ) ? Typography::get_css( $attributes['headingTypography'], '', $device, $typographyGlobal ) : [],
			isset( $attributes['headingMargin'] ) ? Dimensions::get_css( $attributes['headingMargin'], 'margin', $device ) : [],
		);
	}


	public function get_counter_bar_bg_css( $attributes ) {
		return [ 'background' => Color::get_css( isset( $attributes['barBackgroundColor'] ) ? $attributes['barBackgroundColor'] : '' ) ];
	}

	public function get_counter_bar_progress_css( $attributes, $device = '' ) {
		$counter_bar_progress_css = [];
		if ( ! empty( $attributes['barHeadingPosition'] ) ) {
			if ( $attributes['barHeadingPosition'] === 'inner' ) {
				$counter_bar_progress_css['justify-content'] = 'space-between';
			} elseif (
				$attributes['barHeadingPosition'] === 'top' ||
				$attributes['barHeadingPosition'] === 'bottom'
			) {
				$counter_bar_progress_css['justify-content'] = 'right';
			}
		}

		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['barProgressColor'] ) ? $attributes['barProgressColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['barSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 40,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			$counter_bar_progress_css,
		);
	}

	public function get_counter_circle_css( $attributes, $device = '' ) {
		$css = [];

		if ( isset( $attributes['alignment'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['alignment'][ 'value' . $device ];
			$css['align-items']     = $attributes['alignment'][ 'value' . $device ];
		}
		return $css;
	}


	public function get_counter_circle_bg_css( $attributes ) {
		$counter_circle_bg_css = [];
		if ( ! empty( $attributes['circleStrokeSize'] ) ) {
			$counter_circle_bg_css['stroke-width'] = $attributes['circleStrokeSize'];
		}

		return array_merge(
			[ 'stroke' => Color::get_css( isset( $attributes['circleBackgroundColor'] ) ? $attributes['circleBackgroundColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['circleStrokeSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 20,
				'property' => 'stroke-width',
			]),
			$counter_circle_bg_css,
		);
	}

	public function get_counter_circle_progress_css( $attributes ) {
		return array_merge(
			[ 'stroke' => Color::get_css( isset( $attributes['circleProgressColor'] ) ? $attributes['circleProgressColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['circleStrokeSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 20,
				'property' => 'stroke-width',
			]),
		);
	}

}
