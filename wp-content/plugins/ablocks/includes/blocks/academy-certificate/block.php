<?php
namespace ABlocks\Blocks\AcademyCertificate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Dimensions;

class Block extends BlockBaseAbstract {
	protected $block_name = 'academy-certificate';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_certificate_wrapper_css( $attributes ),
			$this->get_certificate_wrapper_css( $attributes, 'Tablet' ),
			$this->get_certificate_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--certificate__background-image',
			$this->get_certificate_bg_img_css( $attributes ),
			$this->get_certificate_bg_img_css( $attributes, 'Tablet' ),
			$this->get_certificate_bg_img_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block--certificate__background-image-inner-block',
			$this->get_certificate_inner_block_css( $attributes ),
			$this->get_certificate_inner_block_css( $attributes, 'Tablet' ),
			$this->get_certificate_inner_block_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}



	public function get_certificate_wrapper_css( $attributes, $device = '' ) {
		$css = array();
		$css['width'] = '100%';
		$css['height'] = '100% !important';
		$css['padding'] = '0px !important';
		return $css;
	}
	public function get_certificate_bg_img_css( $attributes, $device = '' ) {
		$css = array();
		if ( ! empty( $attributes['backgroundImage'] ) ) {
			$css['background-image'] = 'url(' . $attributes['backgroundImage'] . ')';
		}

		if ( $attributes['pageOrientation'] === 'P' ) {
			$css['background-size'] = 'inherit';
			$css['background-repeat'] = 'repeat !important';
			$css['background-position'] = 'center !important';

		} else {
			$css['background-image-resize'] = '6';
			$css['background-repeat'] = 'no-repeat !important';
		}
		$css['width'] = '100%';
		$css['height'] = '100% !important';
		return $css;
	}

	public function get_certificate_inner_block_css( $attributes, $device = '' ) {
		$css = array();
		if ( ! empty( $attributes['containerWidth'] ) ) {
			$css['width'] = $attributes['containerWidth'] . '%';
		}
			$css['height'] = '100%';
			$css['box-sizing'] = 'border-box';
			$css['padding'] = '100px';
			$css['margin'] = 'auto !important';
		if ( ! empty( $attributes['certificate_padding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['certificate_padding'], 'padding', $device );
		}

		return array_merge(
			$css,
			$cssPadding
		);
	}



}
