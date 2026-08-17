<?php
namespace ABlocks\Blocks\QRCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Classes\CssGeneratorV2;

class Block extends BlockBaseAbstract {
	protected $block_name = 'qr-code';


	public function build_css_v1( $attributes ) {
			// Generate CSS start
			$css_generator = new CssGenerator( $attributes );
			return $css_generator->generate_css();

	}
	public function build_css_v2( $attributes ) {
			// Generate CSS start
			$css_generator = new CssGenerator( $attributes );
			$css_generator->add_class_styles(
				'{{WRAPPER}}.ablocks-block--qr-code:not(.ablocks-block-container),
   			 {{WRAPPER}}.ablocks-block--qr-code .ablocks-block-container',
				$this->get_qr_code_css( $attributes ),
				$this->get_qr_code_css( $attributes, 'Tablet' ),
				$this->get_qr_code_css( $attributes, 'Mobile' )
			);
			return $css_generator->generate_css();

	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_qr_code_css( $attributes, $device = '' ) {
		$qr_code_css = Range::get_css( [] );

		if ( ! empty( $attributes['alignment'][ 'value' . $device ] ) ) {
			$qr_code_css['justify-content'] = $attributes['alignment'][ 'value' . $device ];
			$qr_code_css['display'] = 'flex';
		}
		return array_merge(
			$qr_code_css
		);
	}
}
