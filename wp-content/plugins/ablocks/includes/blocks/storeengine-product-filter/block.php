<?php
namespace ABlocks\Blocks\StoreengineProductFilter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-product-filter';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-products__filter .storeengine__header-ordering select',
			$this->get_product_filter_select_css( $attributes ),
			$this->get_product_filter_select_css( $attributes, 'Tablet' ),
			$this->get_product_filter_select_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-products__filter .storeengine__header-ordering select:hover',
			$this->get_product_filter_select_hover_css( $attributes ),
			$this->get_product_filter_select_hover_css( $attributes, 'Tablet' ),
			$this->get_product_filter_select_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function get_product_filter_select_css( $attributes, $device = '' ) {
		$cssWidth['width'] = $attributes['selectWidth'] . 'px';
		$typographyGlobal = ( isset( $attributes['selectTypographyGlobal'] ) ? $attributes['selectTypographyGlobal'] : '' );

		$typographyValue = ! empty( $attributes['selectTypography'] ) ? Typography::get_css( $attributes['selectTypography'], '', $device, $typographyGlobal ) : array();

		if ( ! empty( $attributes['selectPadding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['selectPadding'], '', $device );
		}

		if ( ! empty( $attributes['selectBorder'] ) ) {
			$cssBorder = Border::get_css( $attributes['selectBorder'], '', $device );
		}

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['selectTextcolor'] ) ? $attributes['selectTextcolor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['selectBackground'] ) ? $attributes['selectBackground'] : '' ) ],
			$cssBorder,
			$cssPadding,
			$typographyValue,
			$cssWidth,
		);

	}
	public function get_product_filter_select_hover_css( $attributes, $device = '' ) {
		if ( ! empty( $attributes['selectBorder'] ) ) {
			$cssBorder = Border::get_hover_css( $attributes['selectBorder'] ?? [], '', $device );

		}
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['selectTextcolorH'] ) ? $attributes['selectTextcolorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['selectBackgroundH'] ) ? $attributes['selectBackgroundH'] : '' ) ],
			$cssBorder,
		);

	}
	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'ids'  => Helper::get_attribute_value( $attributes, 'products_ids' ),
		];
		$shortcode = '[storeengine_archive_header_filter ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
