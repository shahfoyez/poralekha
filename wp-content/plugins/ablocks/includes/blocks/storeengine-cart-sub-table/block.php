<?php
namespace ABlocks\Blocks\StoreengineCartSubTable;

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

	protected $block_name = 'storeengine-cart-sub-table';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode table',
			$this->get_cart_sub_wrapper_css( $attributes ),
			$this->get_cart_sub_wrapper_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode',
			$this->get_cart_sub_css( $attributes ),
			$this->get_cart_sub_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr:first-child',
			$this->get_cart_sub_row_css( $attributes ),
			$this->get_cart_sub_row_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_row_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr:first-child:hover',
			$this->get_cart_sub_row_hover_css( $attributes ),
			$this->get_cart_sub_row_hover_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_row_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr:first-child td,
			{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr:first-child th',
			$this->get_cart_sub_row_text_css( $attributes ),
			$this->get_cart_sub_row_text_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_row_text_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr:first-child td:hover,
			 {{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr:first-child th:hover',
			$this->get_cart_sub_row_text_hover_css( $attributes ),
			$this->get_cart_sub_row_text_hover_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_row_text_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr',
			$this->get_cart_sub_row_last_css( $attributes ),
			$this->get_cart_sub_row_last_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_row_last_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr:hover',
			$this->get_cart_sub_row_last_hover_css( $attributes ),
			$this->get_cart_sub_row_last_hover_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_row_last_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr td,
			{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr th',
			$this->get_cart_sub_row_last_text_css( $attributes ),
			$this->get_cart_sub_row_last_text_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_row_last_text_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr td:hover,
			 {{WRAPPER}} .storeengine-cart-sub-total-table-shortcode .storeengine-cart-sub-total-table tr th:hover',
			$this->get_cart_sub_row_last_text_hover_css( $attributes ),
			$this->get_cart_sub_row_last_text_hover_css( $attributes, 'Tablet' ),
			$this->get_cart_sub_row_last_text_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function get_cart_sub_wrapper_css( $attributes, $device = '' ) {
		$css = [];

		$css['width'] = $attributes['tableWidth'] . '%';

		return array_merge(
			$css,
		);
	}

	public function get_cart_sub_css( $attributes, $device = '' ) {
		$alignment_css = [];
		$css = [];

		$css['display'] = 'flex';
		$css['width'] = '100%';

		if ( ! empty( $attributes['tableAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['tableAlignment'][ 'value' . $device ];
		}

		return array_merge(
			$alignment_css,
			$css,
		);
	}

	public function get_cart_sub_row_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['tableBackground'] ) ? $attributes['tableBackground'] : '' ) ];
	}
	public function get_cart_sub_row_hover_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['tableBackgroundH'] ) ? $attributes['tableBackgroundH'] : '' ) ];
	}

	public function get_cart_sub_row_text_css( $attributes, $device = '' ) {
		$typography_css = isset( $attributes['firstTableTypography'] ) && is_array( $attributes['firstTableTypography'] )
			? $attributes['firstTableTypography']
			: [];

		$typography_value = array_merge( $typography_css, [ 'font-weight' => '400' ] );
		$typographyValueGlobal = ( isset( $attributes['firstTableTypographyGlobal'] ) ? $attributes['firstTableTypographyGlobal'] : '' );

		return array_merge(
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			[ 'color' => Color::get_css( isset( $attributes['tableColor'] ) ? $attributes['tableColor'] : '' ) ]
		);

	}

	public function get_cart_sub_row_text_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['tableColorH'] ) ? $attributes['tableColorH'] : '' ) ];

	}


	public function get_cart_sub_row_last_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['tableLastBackground'] ) ? $attributes['tableLastBackground'] : '' ) ];
	}
	public function get_cart_sub_row_last_hover_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['tableLastBackgroundH'] ) ? $attributes['tableLastBackgroundH'] : '' ) ];
	}

	public function get_cart_sub_row_last_text_css( $attributes, $device = '' ) {

		$typography_css = isset( $attributes['lastTableTypography'] ) && is_array( $attributes['lastTableTypography'] )
			? $attributes['lastTableTypography']
			: [];

		$typography_value = array_merge( $typography_css, [ 'font-weight' => '400' ] );
		$typographyValueGlobal = ( isset( $attributes['lastTableTypographyGlobal'] ) ? $attributes['lastTableTypographyGlobal'] : '' );

		return array_merge(
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			[ 'color' => Color::get_css( isset( $attributes['tableLastColor'] ) ? $attributes['tableLastColor'] : '' ) ]
		);

	}
	public function get_cart_sub_row_last_text_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['tableLastColorH'] ) ? $attributes['tableLastColorH'] : '' ) ];

	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [];
		$shortcode = '[storeengine_cart_sub_total_table ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
