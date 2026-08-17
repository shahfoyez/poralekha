<?php
namespace ABlocks\Blocks\PriceMenuItem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'price-menu';
	protected $block_name = 'price-menu-item';

	public function build_css( $attributes ) {

		$css_generator = new CssGenerator( $attributes, $this->block_name );
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
			'{{WRAPPER}} .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		// TitleText CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-price-menu-item-details-title',
			$this->get_title_text_css( $attributes, '' ),
			$this->get_title_text_css( $attributes, 'Tablet' ),
			$this->get_title_text_css( $attributes, 'Mobile' ),
		);
		// DescriptionText CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-price-menu-item-details-des',
			$this->get_description_text_css( $attributes, '' ),
			$this->get_description_text_css( $attributes, 'Tablet' ),
			$this->get_description_text_css( $attributes, 'Mobile' ),
		);
		// SeparatorText CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-price-menu-divider__pattern-' . ( $attributes['dividerType'] === 'mask-style' ? 'mask' : 'css' ),
			$this->get_divider_css( $attributes, '' ),
			$this->get_divider_css( $attributes, 'Tablet' ),
			$this->get_divider_css( $attributes, 'Mobile' ),
		);
		// PriceText CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-price-menu-item-price',
			$this->get_price_text_css( $attributes, '' ),
			$this->get_price_text_css( $attributes, 'Tablet' ),
			$this->get_price_text_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}

	public function get_title_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['titleTypographyGlobal'] ) ? $attributes['titleTypographyGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['titleColor'] ) ? $attributes['titleColor'] : '' ) ],
			isset( $attributes['titleTypography'] ) ? Typography::get_css( $attributes['titleTypography'], '', $device, $typographyValueGlobal ) : [],
			isset( $attributes['titleTextStroke'] ) ? TextStroke::get_css( $attributes['titleTextStroke'], '', $device ) : [],
			isset( $attributes['titleTextShadow'] ) ? TextShadow::get_css( $attributes['titleTextShadow'], '', $device ) : [],
			isset( $attributes['titleAlignment'] ) ? Alignment::get_css( $attributes['titleAlignment'], 'text-align', $device ) : [],
		);
	}
	public function get_description_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['descriptionTypographyGlobal'] ) ? $attributes['descriptionTypographyGlobal'] : array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['descriptionColor'] ) ? $attributes['descriptionColor'] : '' ) ],
			isset( $attributes['descriptionTypography'] ) ? Typography::get_css( $attributes['descriptionTypography'], '', $device, $typographyValueGlobal ) : [],
			isset( $attributes['descriptionTextStroke'] ) ? TextStroke::get_css( $attributes['descriptionTextStroke'], '', $device ) : [],
			isset( $attributes['descriptionTextShadow'] ) ? TextShadow::get_css( $attributes['descriptionTextShadow'], '', $device ) : [],
			isset( $attributes['descriptionAlignment'] ) ? Alignment::get_css( $attributes['descriptionAlignment'], 'text-align', $device ) : [],
		);
	}
	public function get_divider_css( $attributes, $device = '' ) {
		$css = [];
		$divider_width = isset( $attributes['width'][ 'value' . $device ] ) ? $attributes['width'][ 'value' . $device ] : 60;
		$default_Unit = $attributes['allowDescription'] === true ? '%' : 'px';

		if ( ! empty( $attributes['color'] ) ) {
			$css['--ablocks-divider-pattern-color'] = Color::get_css(
			isset( $attributes['color'] ) ? $attributes['color'] : '#000000');

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
				'defaultValue' => null,
				'hasUnit' => false,
				'unitDefaultValue' => $attributes['allowDescription'] === true ? '%' : 'px',
				'property' => 'width',
				'device' => $device,
			]),
			$moreRangeCSS,
		);
	}
	public function get_price_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['priceTypographyGlobal'] ) ? $attributes['priceTypographyGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['priceColor'] ) ? $attributes['priceColor'] : '' ) ],
			isset( $attributes['priceTypography'] ) ? Typography::get_css( $attributes['priceTypography'], '', $device, $typographyValueGlobal ) : [],
			isset( $attributes['priceTextStroke'] ) ? TextStroke::get_css( $attributes['priceTextStroke'], '', $device ) : [],
			isset( $attributes['priceTextShadow'] ) ? TextShadow::get_css( $attributes['priceTextShadow'], '', $device ) : [],
			isset( $attributes['priceAlignment'] ) ? Alignment::get_css( $attributes['priceAlignment'], 'text-align', $device ) : [],
		);
	}
}
