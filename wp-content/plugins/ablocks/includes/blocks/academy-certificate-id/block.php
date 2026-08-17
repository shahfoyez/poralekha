<?php
namespace ABlocks\Blocks\AcademyCertificateId;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'academy-certificate';
	protected $block_name = 'academy-certificate-id';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--certificate__verification-id',
			$this->get_verification_id_css( $attributes ),
			$this->get_verification_id_css( $attributes, 'Tablet' ),
			$this->get_verification_id_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_wrapper_css( $attributes, $device = '' ) {
		return isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'text-align', $device ) : [];
	}

	public function get_verification_id_css( $attributes, $device = '' ) {
		$css = array();
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		$typography_css = ! empty( $attributes['typography'] ) ? Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ) : array();
		$textAlignCss = ! empty( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'text-align', $device ) : [];
		$css['width'] = '100%';
		$css['display'] = 'block';
		return array_merge( $typography_css, $textAlignCss, $css );
	}

}
