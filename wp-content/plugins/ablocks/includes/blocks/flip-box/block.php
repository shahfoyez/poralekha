<?php
namespace ABlocks\Blocks\FlipBox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;


class Block extends BlockBaseAbstract {
	protected $block_name = 'flip-box';

	// No Dom optimization needed
	public function build_css( $attributes ) {

		// Generate CSS start
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container:first-child',
			$this->get_flipbox_wrapper_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-flipbox__front > div:nth-child(1)',
			$this->get_front_card_css( $attributes ),
			$this->get_front_card_css( $attributes, 'Tablet' ),
			$this->get_front_card_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-flipbox__back > div:nth-child(1)',
			$this->get_back_card_css( $attributes ),
			$this->get_back_card_css( $attributes, 'Tablet' ),
			$this->get_back_card_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-flipbox__front:hover > div:nth-child(1)',
			$this->get_front_card_hover_css( $attributes ),
			$this->get_front_card_hover_css( $attributes, 'Tablet' ),
			$this->get_front_card_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-flipbox__back:hover > div:nth-child(1)',
			$this->get_back_card_hover_css( $attributes ),
			$this->get_back_card_hover_css( $attributes, 'Tablet' ),
			$this->get_back_card_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_flipbox_wrapper_css( $attributes ) {

		return (
			Range::get_css([
				'attributeValue' => $attributes['transitionSpeed'],
				'attribute_object_key' => 'value',
				'defaultValue' => 0.6,
				'unitDefaultValue' => 's',
				'property' => 'transition',
			])
		);
	}

	public function get_front_card_css( $attributes, $device = '' ) {
		$css = [];
		$css = array_merge(
			Background::get_css( $attributes['frontCardBackground'], 'background', $device ),
			Dimensions::get_css( $attributes['frontPadding'], 'padding', $device ),
			Border::get_css( $attributes['cardBorder'], $device )
		);

		return $css;
	}

	public function get_back_card_css( $attributes, $device = '' ) {
		$css = [];
		$css = array_merge(
			Background::get_css( $attributes['backCardBackground'], 'background', $device ),
			Dimensions::get_css( $attributes['backPadding'], 'padding', $device ),
			Border::get_css( $attributes['cardBorder'], $device )
		);

		return $css;
	}

	public function get_front_card_hover_css( $attributes, $device = '' ) {
		$css = [];
		$css = array_merge(
			Background::get_hover_css( $attributes['frontCardBackground'], 'background', $device ),
			Dimensions::get_css( $attributes['frontPadding'], 'padding', $device ),
			Border::get_hover_css( $attributes['cardBorder'], $device )
		);

		return $css;
	}

	public function get_back_card_hover_css( $attributes, $device = '' ) {
		$css = [];
		$css = array_merge(
			Background::get_hover_css( $attributes['backCardBackground'], 'background', $device ),
			Dimensions::get_css( $attributes['backPadding'], 'padding', $device ),
			Border::get_hover_css( $attributes['cardBorder'], $device )
		);

		return $css;
	}

	public function get_card_height_css( $attributes, $device = '' ) {
		$css = [];
		// find max height of front and back card
		$front_height = isset( $attributes['frontPadding']['padding']['top'] ) ? $attributes['frontPadding']['padding']['top'] : 0;
		$front_height += isset( $attributes['frontPadding']['padding']['bottom'] ) ? $attributes['frontPadding']['padding']['bottom'] : 0;
		$back_height = isset( $attributes['backPadding']['padding']['top'] ) ? $attributes['backPadding']['padding']['top'] : 0;
		$back_height += isset( $attributes['backPadding']['padding']['bottom'] ) ? $attributes['backPadding']['padding']['bottom'] : 0;
		$max_height = max( $front_height, $back_height );

		$css['height'] = $max_height . 'px';

		return $css;
	}

}
