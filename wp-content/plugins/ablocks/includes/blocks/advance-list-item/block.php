<?php
namespace ABlocks\Blocks\AdvanceListItem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Width;
use ABlocks\Controls\Border;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {

	protected $parent_block_name = 'advance-lists';
	protected $block_name = 'advance-list-item';

	public function build_css( $attributes ) {
		// Generate CSS
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--advance-list-item .ablocks-block-container .ablocks-advance-list-item-link',
			$this->get_container_css( $attributes, '' ),
			$this->get_container_css( $attributes, 'Tablet' ),
			$this->get_container_css( $attributes, 'Mobile' ),
		);
		// Marker
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--advance-list-item .advance-list-item-marker',
			$this->get_marker_css( $attributes, '' ),
			$this->get_marker_css( $attributes, 'Tablet' ),
			$this->get_marker_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--advance-list-item .advance-list-item-marker,{{WRAPPER}}.ablocks-block--advance-list-item .ablocks-icon-wrap',
			$this->get_icon_order_css( $attributes, '' ),
			$this->get_icon_order_css( $attributes, 'Tablet' ),
			$this->get_icon_order_css( $attributes, 'Mobile' ),
		);
		// Icon Style
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap',
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
			'{{WRAPPER}} .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);

		// text
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-advance-list-item-text,
			{{WRAPPER}} .ablocks-block-container .ablocks-advance-list-item-text a',
			$this->get_paragraph_text_css( $attributes ),
			$this->get_paragraph_text_css( $attributes, 'Tablet' ),
			$this->get_paragraph_text_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-advance-list-item-text-drop-caps::first-letter',
			$this->get_paragraph_drop_text_css( $attributes ),
			$this->get_paragraph_drop_text_css( $attributes, 'Tablet' ),
			$this->get_paragraph_drop_text_css( $attributes, 'Mobile' ),
		);

		// Divider CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-advance-list-item-divider__pattern-' . ( $attributes['dividerType'] === 'mask-style' ? 'mask' : 'css' ),
			$this->get_divider_css( $attributes, '' ),
			$this->get_divider_css( $attributes, 'Tablet' ),
			$this->get_divider_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}


	public function get_marker_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['shapeColor'] ) && $attributes['markerType'] === 'Shapes' ) {
			$css['border-color'] = Color::get_css( isset( $attributes['shapeColor'] ) ? $attributes['shapeColor'] : '' ) . '!important';
		}
		if ( ! empty( $attributes['shapeSize'][ 'value' . $device ] ) ) {
			$shapeSize = $attributes['shapeSize'][ 'value' . $device ] . 'px';

			if ( $attributes['markerType'] === 'Emoji' ) {
				$css['font-size'] = $shapeSize;
			} else {
				if ( in_array( $attributes['shapeType'], [ 'inset', 'outset', 'ridge' ], true ) ) {
					$css['border'] = $shapeSize . ' ' . $attributes['shapeType'];
				} else {
					$css['border-bottom'] = $shapeSize . ' ' . $attributes['shapeType'];
					$css['width'] = $shapeSize;
				}
			}
		}
		return $css;
	}

	public function get_container_css( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			$css,
			isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'justify-items', $device ) : [],
			Range::get_css([
				'attributeValue' => $attributes['innerGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => null,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
		);
	}

	public function get_icon_order_css( $attributes, $device = '' ) {
		$css = [];

		$css['margin'] = 'auto';

		$iconAlignmentValue = $this->get_responsive_attribute_value( $attributes['iconAlignment'], $device );
		if ( $iconAlignmentValue === 'row' ) {
			$css['order'] = 1;
		} elseif ( $iconAlignmentValue === 'row-reverse' ) {
			$css['order'] = 2;
		}

		return array_merge(
			$css,
			isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'justify-items', $device ) : [],
			Range::get_css([
				'attributeValue' => $attributes['innerGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => null,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
		);
	}

	public function get_paragraph_text_css( $attributes, $device = '' ) {
		$css = [];
		$iconAlignmentValue = $this->get_responsive_attribute_value( $attributes['iconAlignment'], $device );
		$typographyValueGlobal = ! empty( $attributes['listTypographyGlobal'] ) ? $attributes['listTypographyGlobal'] : '';
		$typography_value = isset( $attributes['listTypography'] ) ? $attributes['listTypography'] : [];

		if ( $iconAlignmentValue === 'row' ) {
			$css['order'] = 2;
		} elseif ( $iconAlignmentValue === 'row-reverse' ) {
			$css['order'] = 1;
		}
		return array_merge(
			$css,
			[ 'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			isset( $attributes['listTextStroke'] ) ? TextStroke::get_css( $attributes['listTextStroke'], '', $device ) : [],
			isset( $attributes['listTextShadow'] ) ? TextShadow::get_css( $attributes['listTextShadow'] ) : [],
		);
	}
	public function get_paragraph_drop_text_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['dropCapsTextColor'] ) ? $attributes['dropCapsTextColor'] : '' ) ];
	}

	public function get_divider_css( $attributes, $device = '' ) {
		$css = [];
		$css['order'] = 3;
		$divider_width = isset( $attributes['width'][ 'value' . $device ] ) ? $attributes['width'][ 'value' . $device ] : 60;
		$default_Unit = '%';

		if ( ! empty( $attributes['listsDirection'][ 'value' . $device ] ) && $attributes['listsDirection'][ 'value' . $device ] === 'row' ) {
			$css['display'] = 'none';
		} else {
			$css['display'] = 'block';
		}
		if ( ! empty( $attributes['color'] ) ) {
			$css['--ablocks-divider-pattern-color'] = Color::get_css( isset( $attributes['color'] ) ? $attributes['color'] : '#000000' );
		}

		$moreRangeCSS = [];
		if ( isset( $attributes['dividerType'] ) && $attributes['dividerType'] === 'mask-style' && isset( $attributes['size'] ) && ! empty( $attributes['size'] ) ) {
			$moreRangeCSS = Range::get_css([
				'attributeValue' => $attributes['size'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => null,
				'hasUnit' => false,
				'unitDefaultValue' => 'px',
				'property' => '--ablocks-divider-pattern-height',
			]);
		} elseif ( isset( $attributes['weight'] ) && ! empty( $attributes['weight'] ) ) {
			$moreRangeCSS = Range::get_css([
				'attributeValue' => $attributes['weight'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => null,
				'hasUnit' => false,
				'unitDefaultValue' => 'px',
				'property' => '--ablocks-divider-pattern-weight',
			]);
		}//end if

		if ( ! empty( $attributes['dividerPatternUrl'] ) ) {
			if ( $attributes['dividerType'] === 'mask-style' ) {
				$css['--ablocks-divider-pattern-url'] = 'url(' . $attributes['dividerPatternUrl'] . ')';
			} else {
				$css['--ablocks-divider-pattern-style'] = $attributes['dividerPatternUrl'];
			}
		}

		return array_merge(
			$css,
			isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'justify-self', $device ) : [],
			Range::get_css([
				'attributeValue' => $attributes['width'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => null,
				'hasUnit' => false,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			$moreRangeCSS,
		);
	}

	// don't delete this function, it's used in the render method to get responsive attribute values
	public function get_responsive_attribute_value( $attribute, $device ) {

		if ( $device === 'Mobile' ) {
			return ! empty( $attribute['valueMobile'] ) ? $attribute['valueMobile']
				: ( ! empty( $attribute['valueTablet'] ) ? $attribute['valueTablet']
				: ( $attribute['value'] ?? '' ) );
		}

		if ( $device === 'Tablet' ) {
			return ! empty( $attribute['valueTablet'] ) ? $attribute['valueTablet']
				: ( $attribute['value'] ?? '' );
		}

		// Default (Desktop or others)
		return $attribute['value'] ?? '';
	}

}
