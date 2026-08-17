<?php
namespace ABlocks\Blocks\FormTextarea;

use ABlocks\Controls\Range;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'form-builder';
	protected $block_name = 'form-textarea';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--form-textarea',
			$this->get_input_block_main_wrapper( $attributes ),
			$this->get_input_block_main_wrapper( $attributes, 'Tablet' ),
			$this->get_input_block_main_wrapper( $attributes, 'Mobile' ),
		);
		return $css_generator->generate_css();
	}
	public function get_wrapper_css() {
		return [];
	}
	public function get_input_block_main_wrapper( $attributes, $device = '' ) {
		$css = [];
		$css['box-sizing'] = 'border-box';

		$valueKey = $device === 'Tablet' ? 'valueTablet' : ( $device === 'Mobile' ? 'valueMobile' : 'value' );
		$unitKey = $device === 'Tablet' ? 'valueUnitTablet' : ( $device === 'Mobile' ? 'valueUnitMobile' : 'valueUnit' );

		$widthValue = isset( $attributes['inputWidth'][ $valueKey ] ) && $attributes['inputWidth'][ $valueKey ] !== ''
		? $attributes['inputWidth'][ $valueKey ]
		: ( isset( $attributes['inputWidth']['value'] ) ? $attributes['inputWidth']['value'] : 100 );

		$widthUnit = isset( $attributes['inputWidth'][ $unitKey ] ) && $attributes['inputWidth'][ $unitKey ] !== ''
		? $attributes['inputWidth'][ $unitKey ]
		: '%';

		if ( is_numeric( $widthValue ) ) {
			$widthValue = max( 0, floatval( $widthValue ) - 1 );
		}

		$css['width'] = $widthValue . $widthUnit;

		return $css;
	}
}
