<?php
namespace ABlocks\Blocks\AcademyPdf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;

class Block extends BlockBaseAbstract {
	protected $block_name = 'academy-pdf';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		return $css_generator->generate_css();
	}



	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'src'    => esc_url_raw( Helper::get_attribute_value( $attributes, 'src' ) ),
			'width'  => sanitize_text_field( Helper::get_attribute_value( $attributes, 'width' ) ),
			'height' => sanitize_text_field( Helper::get_attribute_value( $attributes, 'height' ) ),
		];
		$shortcode = '[academy_pdf ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}


}
