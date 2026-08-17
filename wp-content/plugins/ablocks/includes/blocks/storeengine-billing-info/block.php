<?php

namespace ABlocks\Blocks\StoreengineBillingInfo;

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
	protected $block_name = 'storeengine-billing-info';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		return $css_generator->generate_css();
	}

	public function build_css_v2( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-billing-shortcode .storeengine-order-billing-heading',
			$this->get_billing_heading_css( $attributes, '' ),
			$this->get_billing_heading_css( $attributes, 'Tablet' ),
			$this->get_billing_heading_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-billing-shortcode .storeengine-order-billing-heading:hover',
			$this->get_billing_heading_hover_css( $attributes, '' ),
			$this->get_billing_heading_hover_css( $attributes, 'Tablet' ),
			$this->get_billing_heading_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-billing-shortcode .storeengine-order-billing-address p',
			$this->get_billing_address_css( $attributes, '' ),
			$this->get_billing_address_css( $attributes, 'Tablet' ),
			$this->get_billing_address_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-order-billing-shortcode .storeengine-order-billing-address p:hover',
			$this->get_billing_address_hover_css( $attributes, '' ),
			$this->get_billing_address_hover_css( $attributes, 'Tablet' ),
			$this->get_billing_address_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_billing_heading_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['heading_typograhyGlobal'] ) ? $attributes['heading_typograhyGlobal'] : '';
		$typography_css = isset( $attributes['heading_typograhy'] ) ? $attributes['heading_typograhy'] : [];

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['heading_color'] ) ? $attributes['heading_color'] : '' ) ],
			Typography::get_css( $typography_css, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_billing_heading_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['heading_hover_color'] ) ? $attributes['heading_hover_color'] : '' ) ];

	}


	public function get_billing_address_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['address_typograhyGlobal'] ) ? $attributes['address_typograhyGlobal'] : '';
		$typography_css = isset( $attributes['address_typograhy'] ) ? $attributes['address_typograhy'] : [];

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['address_color'] ) ? $attributes['address_color'] : '' ) ],
			Typography::get_css( $typography_css, '', $device, $typographyValueGlobal )
		);
	}

	public function get_billing_address_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['address_hover_color'] ) ? $attributes['address_hover_color'] : '' ) ];
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		// StoreEngine core falls back to sample content when there's no real
		// order (e.g. in the editor), so no dummy flag is needed here.
		echo do_shortcode( '[storeengine_order_billing_address]' );
	}

}
