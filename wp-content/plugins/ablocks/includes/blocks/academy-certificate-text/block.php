<?php
namespace ABlocks\Blocks\AcademyCertificateText;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'academy-certificate';
	protected $block_name = 'academy-certificate-text';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		$desktop_heading_text_styles = $this->get_heading_text_css( $attributes );
		if ( ! empty( $attributes['textColor'] ) ) {
			$desktop_heading_text_styles['color'] = $attributes['textColor'];
		}

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--certificate__heading-text',
			$desktop_heading_text_styles,
			$this->get_heading_text_css( $attributes, 'Tablet' ),
			$this->get_heading_text_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_wrapper_css( $attributes, $device = '' ) {
		return isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'text-align', $device ) : [];
	}
	public function get_heading_text_css( $attributes, $device = '' ) {
		$css = array();
		$css['margin'] = '0px';
		$css['padding'] = '0px';
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		$typography_css = ! empty( $attributes['typography'] ) ? Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ) : array();
		$textShadowCss = ! empty( $attributes['textShadow'] ) ? TextShadow::get_css( $attributes['textShadow'], '', $device ) : array();
		$textStrokeCss = ! empty( $attributes['textStroke'] ) ? TextStroke::get_css( $attributes['textStroke'], '', $device ) : array();

		return array_merge( $typography_css, $textShadowCss, $textStrokeCss, $css );
	}
}
