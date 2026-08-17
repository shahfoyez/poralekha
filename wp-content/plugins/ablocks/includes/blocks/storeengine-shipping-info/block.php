<?php

namespace ABlocks\Blocks\StoreengineShippingInfo;

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
	protected $block_name = 'storeengine-shipping-info';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		return $css_generator->generate_css();
	}


	public function build_css_v2( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-shipping-shortcode .storeengine-order-shipping-heading',
			$this->get_shipping_heading_css( $attributes, '' ),
			$this->get_shipping_heading_css( $attributes, 'Tablet' ),
			$this->get_shipping_heading_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-shipping-shortcode .storeengine-order-shipping-heading:hover',
			$this->get_shipping_heading_hover_css( $attributes, '' ),
			$this->get_shipping_heading_hover_css( $attributes, 'Tablet' ),
			$this->get_shipping_heading_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-shipping-shortcode .storeengine-order-shipping-address p',
			$this->get_shipping_address_css( $attributes, '' ),
			$this->get_shipping_address_css( $attributes, 'Tablet' ),
			$this->get_shipping_address_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-shipping-shortcode .storeengine-order-shipping-address p:hover',
			$this->get_shipping_address_hover_css( $attributes, '' ),
			$this->get_shipping_address_hover_css( $attributes, 'Tablet' ),
			$this->get_shipping_address_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_shipping_heading_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['shipping_heading_typograhyGlobal'] ) ? $attributes['shipping_heading_typograhyGlobal'] : '' );
		$typography_value = isset( $attributes['shipping_heading_typography'] ) ?
			Typography::get_css( $attributes['shipping_heading_typography'], '', $device, $typographyGlobal )
			: [];

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['shipping_heading_color'] ) ? $attributes['shipping_heading_color'] : '' ) ],
			$typography_value,
		);

	}

	public function get_shipping_heading_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['shipping_heading_hover_color'] ) ? $attributes['shipping_heading_hover_color'] : '' ) ];
	}
	public function get_shipping_address_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['shipping_address_typographyGlobal'] ) ? $attributes['shipping_address_typograhyGlobal'] : '' );
		$typography_value = isset( $attributes['shipping_address_typography'] ) ?
			Typography::get_css( $attributes['shipping_address_typography'], '', $device, $typographyGlobal )
			: [];

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['shipping_address_color'] ) ? $attributes['shipping_address_color'] : '' ) ],
			$typography_value,
		);

	}

	public function get_shipping_address_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['shipping_address_hover_color'] ) ? $attributes['shipping_address_hover_color'] : '' ) ];
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		// StoreEngine core falls back to sample content when there's no real
		// order (e.g. in the editor), so no dummy flag is needed here.
		echo do_shortcode( '[storeengine_order_shipping_address]' );
	}

}
