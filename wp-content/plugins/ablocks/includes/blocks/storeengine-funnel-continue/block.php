<?php
namespace ABlocks\Blocks\StoreengineFunnelContinue;

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
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-funnel-continue';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_coutineu_button_wrapper_css( $attributes ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-btn',
			$this->get_coutineu_button_css( $attributes ),
			$this->get_coutineu_button_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-funnel-btn:hover',
			$this->get_coutineu_button_hover_css( $attributes ),
			$this->get_coutineu_button_hover_css( $attributes, 'Tablet' ),
			$this->get_coutineu_button_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function get_coutineu_button_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];

		$css['width'] = '100%';
		$css['display'] = 'flex';

		if ( ! empty( $attributes['buttonAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['buttonAlignment'][ 'value' . $device ];
		}

		return array_merge(
			$alignment_css,
			$css,
		);

	}

	public function get_coutineu_button_css( $attributes, $device = '' ) {
		$css = [];

		if ( $attributes['buttonColor'] !== '' ) {
			$css['color'] = $attributes['buttonColor'];
		}
		// if ( $attributes['buttonWidth'] !== '' ) {
		// $css['width'] = $attributes['buttonWidth'] . '%';
		// }
		if ( $attributes['buttonBackground'] !== '' ) {
			$css['background'] = $attributes['buttonBackground'];
		}
		$typography = isset( $attributes['buttonTypography'] ) && is_array( $attributes['buttonTypography'] )
			? $attributes['buttonTypography']
			: [];
		$typographyValueGlobal = ( isset( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : '' );

		$typography_value = array_merge( $typography, [ 'font-weight' => '400' ] );

		if ( ! empty( $attributes['padding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['padding'], 'padding', $device );
		}

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBackground'] ) ? $attributes['buttonBackground'] : '' ) ],
			$css,
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			Border::get_css( $attributes['buttonBorder'], '', $device ),
			$cssPadding,
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['buttonWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 100,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			])
		);
	}

	public function get_coutineu_button_hover_css( $attributes, $device = '' ) {
			return array_merge(
				[ 'color' => Color::get_css( isset( $attributes['buttonColorH'] ) ? $attributes['buttonColorH'] : '' ) ],
				[ 'background' => Color::get_css( isset( $attributes['buttonBackgroundH'] ) ? $attributes['buttonBackgroundH'] : '' ) ],
				Border::get_hover_css( $attributes['buttonBorder'] ?? [], '', $device ),
				BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
			);

	}
	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'label' => Helper::get_attribute_value( $attributes, 'label' ),
		];
		$shortcode = '[storeengine_funnel_next_step ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
