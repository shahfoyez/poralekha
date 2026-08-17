<?php

namespace ABlocks\Blocks\StoreengineOrderInfo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-order-info';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		return $css_generator->generate_css();
	}

	public function build_css_v2( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success__content h4',
			$this->get_status_title_css( $attributes ),
			$this->get_status_title_css( $attributes, 'Tablet' ),
			$this->get_status_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success__content h4:hover',
			$this->get_status_title_hover_css( $attributes ),
			$this->get_status_title_hover_css( $attributes, 'Tablet' ),
			$this->get_status_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success__content p span,
			 {{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success__content p time',
			$this->get_deatils_title_css( $attributes ),
			$this->get_deatils_title_css( $attributes, 'Tablet' ),
			$this->get_deatils_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success__content p span:hover,
			 {{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success__content p time:hover',
			$this->get_deatils_title_hover_css( $attributes ),
			$this->get_deatils_title_hover_css( $attributes, 'Tablet' ),
			$this->get_deatils_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success .storeengine-thankyou-order-info-success__email p span,
			{{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success .storeengine-thankyou-order-info-success__email p a',
			$this->get_email_css( $attributes ),
			$this->get_email_css( $attributes, 'Tablet' ),
			$this->get_email_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success .storeengine-thankyou-order-info-success__email p span:hover,
			{{WRAPPER}} .storeengine-thankyou-order-info-shortcode .storeengine-thankyou-order-info-success .storeengine-thankyou-order-info-success__email p a:hover',
			$this->get_email_hover_css( $attributes ),
			$this->get_email_hover_css( $attributes, 'Tablet' ),
			$this->get_email_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_status_title_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['titleContentTypographyGlobal'] ) ? $attributes['titleContentTypographyGlobal'] : '' );
		$typography_value = ! empty( $attributes['titleContentTypography'] ) ? Typography::get_css( $attributes['titleContentTypography'], '', $device, $typographyGlobal ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['titleContentColor'] ) ? $attributes['titleContentColor'] : '' ) ],
			$typography_value
		);
	}
	public function get_status_title_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['titleContentColorH'] ) ? $attributes['titleContentColorH'] : '' ) ];
	}
	public function get_deatils_title_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['detailsTypographyGlobal'] ) ? $attributes['detailsTypographyGlobal'] : '' );
		$typography_value = ! empty( $attributes['detailsTypography'] ) ? Typography::get_css( $attributes['detailsTypography'], '', $device, $typographyGlobal ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['detailsColor'] ) ? $attributes['detailsColor'] : '' ) ],
			$typography_value
		);
	}

	public function get_deatils_title_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['detailsColorH'] ) ? $attributes['detailsColorH'] : '' ) ];
	}

	public function get_email_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['emailTypographyGlobal'] ) ? $attributes['emailTypographyGlobal'] : '' );
		$typography_value = ! empty( $attributes['emailTypography'] ) ? Typography::get_css( $attributes['emailTypography'], '', $device, $typographyGlobal ) : array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['emailColor'] ) ? $attributes['emailColor'] : '' ) ],
			$typography_value
		);
	}
	public function get_email_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['emailColorH'] ) ? $attributes['emailColorH'] : '' ) ];
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		// StoreEngine core falls back to sample content when there's no real
		// order (e.g. in the editor), so no dummy flag is needed here.
		$attr_array = [
			'ids' => Helper::get_attribute_value( $attributes, 'products_ids' ),
		];

		$shortcode = '[storeengine_thankyou_order_info ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
