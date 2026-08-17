<?php
namespace ABlocks\Blocks\ImageHotspotChild;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'image-hotspot';
	protected $block_name = 'image-hotspot-child';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__tooltip--active',
			$this->get_active_content_css( $attributes ),
			$this->get_active_content_css( $attributes, 'Tablet' ),
			$this->get_active_content_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__tooltip--active:hover',
			$this->get_active_content_hover_css( $attributes ),
			$this->get_active_content_hover_css( $attributes, 'Tablet' ),
			$this->get_active_content_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_active_content_css( $attributes, $device = '' ) {
		// Add border and padding styles
		$border_css = isset( $attributes['contentBorder'] ) ? Border::get_css( $attributes['contentBorder'], '', $device ) : [];
		$padding_css = isset( $attributes['contentPadding'] ) ? Dimensions::get_css( $attributes['contentPadding'], 'padding', $device ) : [];

		// Merge border and padding styles with the main CSS
		$css = array_merge($border_css, $padding_css,
			[ 'background' => Color::get_css( isset( $attributes['backgroundColor'] ) ? $attributes['backgroundColor'] : '' ) ],
			Range::get_css(
				[
					'attributeValue' => $attributes['contentWidth'],
					'attribute_object_key' => 'value',
					'isResponsive' => true,
					'defaultValue' => '',
					'hasUnit' => true,
					'unitDefaultValue' => 'px',
					'property' => 'width',
					'device' => $device,
				]
			)
		);

		return $css;
	}

	public function get_active_content_hover_css( $attributes, $device = '' ) {
		$css = [];

		$border_hover_css = isset( $attributes['contentBorder'] ) ? Border::get_hover_css( $attributes['contentBorder'], $device ) : [];

		$css = array_merge( $css, $border_hover_css );

		return $css;
	}
}
