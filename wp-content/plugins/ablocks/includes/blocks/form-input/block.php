<?php
namespace ABlocks\Blocks\FormInput;

use ABlocks\Controls\Color;
use ABlocks\Controls\Range;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'form-builder';
	protected $block_name = 'form-input';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--form-input',
			$this->get_input_block_main_wrapper( $attributes ),
			$this->get_input_block_main_wrapper( $attributes, 'Tablet' ),
			$this->get_input_block_main_wrapper( $attributes, 'Mobile' )
		);

		// Generate button icon CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap',
			$this->get_icon_wrapper_css( $attributes ),
			$this->get_icon_wrapper_css( $attributes, 'Tablet' ),
			$this->get_icon_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input-show-icon',
			$this->get_icon_space_css( $attributes ),
			$this->get_icon_space_css( $attributes, 'Tablet' ),
			$this->get_icon_space_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input-toggle-password .ablocks-icon-wrap ',
			$this->get_password_show_hide_icon_wrapper_css( $attributes )
		);
		return $css_generator->generate_css();
	}

	public function get_wrapper_css() {

		return [];

	}
	public function get_icon_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		if ( isset( $attributes['inputIconSize'] ) ) {
			$css['font-size'] = $attributes['inputIconSize'] . 'px';
		}
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '' ) ],
			$css
		);
	}
	public function get_icon_space_css( $attributes, $device = '' ) {
		$css = [];
		if ( isset( $attributes['inputIconSpace'] ) ) {
			$css['padding-left'] = $attributes['inputIconSpace'] . 'px';
		}

		return $css;
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

	public function get_password_show_hide_icon_wrapper_css( $attributes ) {
		$password_show_hide_icon_wrapper_css = [];

		if ( isset( $attributes['passwordShowHideIconSize'] ) ) {
			$password_show_hide_icon_wrapper_css['font-size'] = $attributes['passwordShowHideIconSize'] . 'px';
		}

		return $password_show_hide_icon_wrapper_css;
	}
}
