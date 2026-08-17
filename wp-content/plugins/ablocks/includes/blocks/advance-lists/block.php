<?php
namespace ABlocks\Blocks\AdvanceLists;

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
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {

	protected $block_name = 'advance-lists';

	public function build_css( $attributes ) {
		// Generate CSS
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--advance-lists > .ablocks-block-container',
			$this->get_container_css( $attributes, '' ),
			$this->get_container_css( $attributes, 'Tablet' ),
			$this->get_container_css( $attributes, 'Mobile' ),
		);

		// text
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-advance-list-item-text',
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

	public function get_container_css( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['listsDirection'][ 'value' . $device ] ) ) {
			$css['flex-direction'] = $attributes['listsDirection'][ 'value' . $device ];
		}
		$listsDirection = ! empty( $attributes['listsDirection'][ 'value' . $device ] ) && $attributes['listsDirection'][ 'value' . $device ];
		$alignment = isset( $attributes['alignment'] ) ? $attributes['alignment'] : '';
		return array_merge(
			$css,
			Alignment::get_css( $alignment, 'text-align', $device ),
			Alignment::get_css( $alignment, ( $listsDirection && $attributes['listsDirection'][ 'value' . $device ] === 'row' ) ? 'justify-content' : 'align-items', $device ),
			Range::get_css([
				'attributeValue' => $attributes['columnGap'],
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
		$typographyValueGlobal = ! empty( $attributes['listTypographyGlobal'] ) ? $attributes['listTypographyGlobal'] : '';
		$typography_value = isset( $attributes['listTypography'] ) ? $attributes['listTypography'] : [];
		return array_merge(
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
		$divider_width = isset( $attributes['width'][ 'value' . $device ] ) ? $attributes['width'][ 'value' . $device ] : 60;
		$default_Unit = '%';

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
			Range::get_css([
				'attributeValue' => $attributes['width'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => false,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			$moreRangeCSS,
		);
	}

}
