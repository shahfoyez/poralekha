<?php

namespace ABlocks\Blocks\Spacer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;

class Block extends BlockBaseAbstract {
	protected $block_name = 'spacer';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-spacer__box',
			$this->get_spacer_css( $attributes ),
			$this->get_spacer_css( $attributes, 'Tablet' ),
			$this->get_spacer_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-spacer__box',
			$this->get_spacer_css( $attributes ),
			$this->get_spacer_css( $attributes, 'Tablet' ),
			$this->get_spacer_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_spacer_css( $attributes, $device = '' ) {
		$spacer_css = [];
		$height = isset( $attributes['spacerHeight'] ) ? $attributes['spacerHeight'] : '';
		$height_key = 'value' . $device;

		if ( ! empty( $height[ $height_key ] ) ) {
			$value_unit = isset( $height['valueUnit'] ) ? $height['valueUnit'] : 'px';
			$spacer_css['height'] = $height[ $height_key ] . $value_unit . '!important';
		}

		return $spacer_css;
	}
}
