<?php
namespace ABlocks\Blocks\Paragraph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Typography;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;
class Block extends BlockBaseAbstract {
	protected $block_name = 'paragraph';
	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);

		$desktop_paragraph_text_style = $this->get_paragraph_text_css( $attributes );

		if ( ! empty( $attributes['textColor'] ) ) {
			$desktop_paragraph_text_style['color'] = $attributes['textColor'];
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-paragraph-text',
			$desktop_paragraph_text_style,
			$this->get_paragraph_text_css( $attributes, 'Tablet' ),
			$this->get_paragraph_text_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-paragraph-text-drop-caps::first-letter',
			$this->get_paragraph_drop_text_css( $attributes ),
			$this->get_paragraph_drop_text_css( $attributes, 'Tablet' ),
			$this->get_paragraph_drop_text_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-paragraph-text',
			$this->get_paragraph_text_css( $attributes ),
			$this->get_paragraph_text_css( $attributes, 'Tablet' ),
			$this->get_paragraph_text_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-paragraph-text-drop-caps::first-letter',
			$this->get_paragraph_drop_text_css( $attributes ),
			$this->get_paragraph_drop_text_css( $attributes, 'Tablet' ),
			$this->get_paragraph_drop_text_css( $attributes, 'Mobile' ),
		);
		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}


	public function get_wrapper_css( $attributes, $device = '' ) {
		return isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'text-align', $device ) : [];
	}

	public function get_paragraph_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '' ) ],
			isset( $attributes['typography'] ) ? Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ) : [],
			isset( $attributes['textStroke'] ) ? TextStroke::get_css( $attributes['textStroke'], '', $device ) : [],
			isset( $attributes['textShadow'] ) ? TextShadow::get_css( $attributes['textShadow'], '', $device ) : [],
		);
	}

	public function get_paragraph_drop_text_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['dropCapsTextColor'] ) ? $attributes['dropCapsTextColor'] : '' ) ];
	}
}
