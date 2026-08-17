<?php

namespace ABlocks\Blocks\TextPath;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\Icon;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Responsive;
use ABlocks\Controls\Color;
use ABlocks\Controls\TextShadow;
use ABlocks\Classes\CssGeneratorV2;


class Block extends BlockBaseAbstract {

	protected $block_name = 'text-path';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--text-path:not(.ablocks-block-container),
			{{WRAPPER}}.ablocks-block--text-path .ablocks-block-container',
			$this->get_text_path_container_css( $attributes ),
			$this->get_text_path_container_css( $attributes, 'Tablet' ),
			$this->get_text_path_container_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-text-path-text',
			$this->get_text_css( $attributes ),
			$this->get_text_css( $attributes, 'Tablet' ),
			$this->get_text_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-text-path-text:hover',
			$this->get_text_hover_css( $attributes ),
			$this->get_text_hover_css( $attributes, 'Tablet' ),
			$this->get_text_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-path-text-path',
			$this->get_text_path_css( $attributes ),
			$this->get_text_path_css( $attributes, 'Tablet' ),
			$this->get_text_path_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--text-path:not(.ablocks-block-container),
			{{WRAPPER}}.ablocks-block--text-path .ablocks-block-container',
			$this->get_text_path_container_css( $attributes ),
			$this->get_text_path_container_css( $attributes, 'Tablet' ),
			$this->get_text_path_container_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-text-path-text',
			$this->get_text_css( $attributes ),
			$this->get_text_css( $attributes, 'Tablet' ),
			$this->get_text_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-text-path-text:hover',
			$this->get_text_hover_css( $attributes ),
			$this->get_text_hover_css( $attributes, 'Tablet' ),
			$this->get_text_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-path-text-path',
			$this->get_text_path_css( $attributes ),
			$this->get_text_path_css( $attributes, 'Tablet' ),
			$this->get_text_path_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	// text path css generate
	public function get_text_css( $attributes, $device = '' ) {
		$typography = isset( $attributes['typography'] ) && is_array( $attributes['typography'] )
			? $attributes['typography']
			: [];
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : [];

		$text_shadow = ! empty( $attributes['textShadow'] )
			? TextShadow::get_css( $attributes['textShadow'], '', $device ) : [];

		$typography_value = array_merge( $typography, [ 'font-weight' => '400' ] );

		return array_merge(
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			$text_shadow
		);
	}

	// container css genarate
	public function get_text_path_container_css( $attributes, $device = '' ) {
		$text_path_container_css = Range::get_css( [] );

		$text_path_css = Range::get_css( [] );

		if ( ! empty( $attributes['alignment'][ 'value' . $device ] ) ) {
			$text_path_container_css['justify-content'] = $attributes['alignment'][ 'value' . $device ];
		}
		return array_merge(
			$text_path_container_css,
			$text_path_css,
		);

	}

	// text hover css generate
	public function get_text_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['textColorH'] ) ? $attributes['textColorH'] : '' ) ],
			Range::get_css([
				'attributeValue' => isset( $attributes['transition'] ) ? $attributes['transition'] : '',
				'attribute_object_key' => 'value',
				'defaultValue' => 0,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
				'device' => $device,
			]),
		);
	}
	// path css genarate
	public function get_text_path_css( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['rotate'] ) ) {
			$css['transform'] = "rotate({$attributes['rotate']}deg)";
		}
		$css['--width'] = isset( $attributes['iconSize'] ) ? $attributes['iconSize'] . 'px' : '0px';
		return $css;
	}
}

