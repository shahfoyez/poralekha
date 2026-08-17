<?php
namespace ABlocks\Blocks\DualButton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Alignment;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Range;


class Block extends BlockBaseAbstract {
	protected $block_name = 'dual-button';

	public function build_css_v1( $attributes ) {

		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--dual-button > .ablocks-block-container',
			$this->getDualButtonCss( $attributes ),
			$this->getDualButtonCss( $attributes, 'Tablet' ),
			$this->getDualButtonCss( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-button',
			$this->get_button_css( $attributes ),
			$this->get_button_css( $attributes, 'Tablet' ),
			$this->get_button_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {

		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}:not(.ablocks-has-block-container), {{WRAPPER}}.ablocks-block--dual-button > .ablocks-block-container',
			$this->getDualButtonCss( $attributes ),
			$this->getDualButtonCss( $attributes, 'Tablet' ),
			$this->getDualButtonCss( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-button',
			$this->get_button_css( $attributes ),
			$this->get_button_css( $attributes, 'Tablet' ),
			$this->get_button_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function getDualButtonCss( $attributes, $device = '' ) {
		$css = [];
		$stack = isset( $attributes['stack'] ) ? $attributes['stack'] : '';

		$css['flex-wrap'] = 'wrap';
		if ( $stack === 'vertical' ) {
			$css['flex-direction'] = 'column';
			$css['display'] = 'flex';
		}

		if ( $stack === 'horizontal' ) {
			$css['flex-direction'] = 'row';
			$css['display'] = 'flex';
		}
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['gap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 20,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
			$css,
			isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], $attributes['stack'] === 'horizontal' ? 'justify-content' : 'align-items', $device ) : [],
		);
	}
	public function get_button_css( $attributes, $device = '' ) {
		$css = [];
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		return array_merge(
			$css,
			Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ),
		);
	}
}
