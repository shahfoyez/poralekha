<?php

namespace ABlocks\Blocks\StoreengineMiniCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-mini-cart';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		return $css_generator->generate_css();
	}

	public function build_css_v2( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-mini-cart-link svg',
			$this->get_icon_button_css( $attributes, '' ),
			$this->get_icon_button_css( $attributes, 'Tablet' ),
			$this->get_icon_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-mini-cart',
			$this->get_wrapper_css( $attributes, '' ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-mini-cart-link svg:hover',
			$this->get_icon_button_hover_css( $attributes, '' ),
			$this->get_icon_button_hover_css( $attributes, 'Tablet' ),
			$this->get_icon_button_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-mini-cart>button.storeengine-mini-cart-link>.storeengine-min-cart-count',
			$this->get_icon_text_css( $attributes, '' ),
			$this->get_icon_text_css( $attributes, 'Tablet' ),
			$this->get_icon_text_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-mini-cart>button.storeengine-mini-cart-link>.storeengine-min-cart-count:hover',
			$this->get_icon_text_hover_css( $attributes, '' ),
			$this->get_icon_text_hover_css( $attributes, 'Tablet' ),
			$this->get_icon_text_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_icon_button_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '' ) ],
		);
	}

	public function get_icon_button_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['iconColorH'] ) ? $attributes['iconColorH'] : '' ) ];

	}
	public function get_icon_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['countTypographyGlobal'] ) ? $attributes['countTypographyGlobal'] : '';
		$typography_css = isset( $attributes['countTypography'] )
		? Typography::get_css( $attributes['countTypography'], '', $device, $typographyValueGlobal )
		: [];

		return array_merge(
			$typography_css,
			[ 'color' => Color::get_css( isset( $attributes['countColor'] ) ? $attributes['countColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['countBg'] ) ? $attributes['countBg'] : '' ) ],
		);
	}

	public function get_wrapper_css( $attributes, $device = '' ) {
		$css_padding = [];

		if ( ! empty( $attributes['padding'] ) ) {
			$css_padding = Dimensions::get_css( $attributes['padding'], 'padding', $device );
		}

		return $css_padding;
	}

	public function get_icon_text_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['countColorH'] ) ? $attributes['countColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['countBgH'] ) ? $attributes['countBgH'] : '' ) ],
		);
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [];

		$shortcode = '[storeengine_mini_cart ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
