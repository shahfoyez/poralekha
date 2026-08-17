<?php
namespace ABlocks\Blocks\StoreengineProducts;

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
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-products';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-products--grid .storeengine-products__body .storeengine-row .storeengine-product',
			$this->get_products_card_css( $attributes ),
			$this->get_products_card_css( $attributes, 'Tablet' ),
			$this->get_products_card_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-products--grid .storeengine-products__body .storeengine-row .storeengine-product:hover',
			$this->get_products_card_hover_css( $attributes ),
			$this->get_products_card_hover_css( $attributes, 'Tablet' ),
			$this->get_products_card_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-products--grid .storeengine-row .storeengine-product .storeengine-product__body .storeengine-product__title, 
            {{WRAPPER}} .storeengine-products--grid .storeengine-row .storeengine-product .storeengine-product__body .storeengine-product__title a',
			$this->get_products_card_title_css( $attributes ),
			$this->get_products_card_title_css( $attributes, 'Tablet' ),
			$this->get_products_card_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-products--grid .storeengine-row .storeengine-product .storeengine-product__body .storeengine-product__title:hover, 
            {{WRAPPER}} .storeengine-products--grid .storeengine-row .storeengine-product .storeengine-product__body .storeengine-product__title:hover a',
			$this->get_products_card_title_hover_css( $attributes ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-ajax__amount .storeengine-product__simple-price',
			$this->get_products_price_css( $attributes ),
			$this->get_products_price_css( $attributes, 'Tablet' ),
			$this->get_products_price_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-add-to-cart-buttons .storeengine-btn',
			$this->get_products_cart_button_css( $attributes ),
			$this->get_products_cart_button_css( $attributes, 'Tablet' ),
			$this->get_products_cart_button_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-add-to-cart-buttons .storeengine-btn:hover',
			$this->get_products_cart_button_hover_css( $attributes ),
			$this->get_products_cart_button_hover_css( $attributes, 'Tablet' ),
			$this->get_products_cart_button_hover_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();

	}
	public function get_products_card_title_hover_css( $attributes, $device = '' ) {

		return [ 'color' => Color::get_css( isset( $attributes['title_hover_color'] ) ? $attributes['title_hover_color'] : '' ) ];
	}
	public function get_products_card_css( $attributes, $device = '' ) {
		$products_background_css = ! empty( $attributes['card_background'] ) ? Background::get_css( $attributes['card_background'], 'background', $device ) : array();
		$products_border_css = ! empty( $attributes['card_border'] ) ? Border::get_css( $attributes['card_border'], '', $device ) : array();
		$products_margin_css = ! empty( $attributes['card_margin'] ) ? Dimensions::get_css( $attributes['card_margin'], 'margin', $device ) : array();
		$products_padding_css = ! empty( $attributes['card_padding'] ) ? Dimensions::get_css( $attributes['card_padding'], 'padding', $device ) : array();

		return array_merge(
			$products_background_css,
			$products_border_css,
			$products_margin_css,
			$products_padding_css
		);
	}

	public function get_products_card_hover_css( $attributes, $device = '' ) {
		$products_background_hover_css = ! empty( $attributes['card_background'] ) ? Background::get_hover_css( $attributes['card_background'], 'background', $device ) : array();
		$products_border_hover_css = ! empty( $attributes['card_border'] ) ? Border::get_hover_css( $attributes['card_border'], '', $device ) : array();
		$products_margin_hover_css = ! empty( $attributes['card_hover_margin'] ) ? Dimensions::get_css( $attributes['card_hover_margin'], 'margin', $device ) : array();
		$products_padding_hover_css = ! empty( $attributes['card_hover_padding'] ) ? Dimensions::get_css( $attributes['card_hover_padding'], 'padding', $device ) : array();

		return array_merge(
			$products_background_hover_css,
			$products_border_hover_css,
			$products_margin_hover_css,
			$products_padding_hover_css
		);
	}

	public function get_products_price_css( $attributes, $device = '' ) {
		$alignment_css = [];

		if ( ! empty( $attributes['priceTextAlign'][ 'value' . $device ] ) ) {
			$alignment_css['justify-content'] = $attributes['priceTextAlign'][ 'value' . $device ];
		}

		$typographyGlobal = ( isset( $attributes['price_typographyGlobal'] ) ? $attributes['price_typographyGlobal'] : '' );
		$products_price_typography_css = ! empty( $attributes['price_typography'] ) ? Typography::get_css( $attributes['price_typography'], '', $device, $typographyGlobal ) : array();
		return array_merge(
			$alignment_css,
			[ 'color' => Color::get_css( isset( $attributes['price_color'] ) ? $attributes['price_color'] : '' ) ],
			$products_price_typography_css
		);
	}
	public function get_products_card_title_css( $attributes, $device = '' ) {
		$alignment_css = [];

		if ( ! empty( $attributes['titleTextAlign'][ 'value' . $device ] ) ) {
			$alignment_css['text-align'] = $attributes['titleTextAlign'][ 'value' . $device ];
		}

		$typographyGlobal = ( isset( $attributes['title_typographyGlobal'] ) ? $attributes['title_typographyGlobal'] : '' );
		$course_title_typography_css = ! empty( $attributes['title_typography'] ) ? Typography::get_css( $attributes['title_typography'], '', $device, $typographyGlobal ) : array();
		return array_merge(
			$alignment_css,
			[ 'color' => Color::get_css( isset( $attributes['title_color'] ) ? $attributes['title_color'] : '' ) ],
			$course_title_typography_css
		);
	}

	public function get_products_cart_button_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['cart_button_typographyGlobal'] ) ? $attributes['cart_button_typographyGlobal'] : '' );
		$typography_value = ! empty( $attributes['cart_button_typography'] ) ? Typography::get_css( $attributes['cart_button_typography'], '', $device, $typographyGlobal ) : array();
		$products_border_css = ! empty( $attributes['cart_border'] ) ? Border::get_css( $attributes['cart_border'], '', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['cart_button_text_color'] ) ? $attributes['cart_button_text_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['cart_button_color'] ) ? $attributes['cart_button_color'] : '' ) ],
			$typography_value,
			$products_border_css,
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

	public function get_products_cart_button_hover_css( $attributes, $device = '' ) {
		$products_border_hover_css = ! empty( $attributes['cart_border'] ) ? Border::get_hover_css( $attributes['cart_border'], '', $device ) : array();

		return array_merge(
			$products_border_hover_css,
			[ 'color' => Color::get_css( isset( $attributes['cart_button_text_hover_color'] ) ? $attributes['cart_button_text_hover_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['cart_button_hover_color'] ) ? $attributes['cart_button_hover_color'] : '' ) ],
		);
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'count'                 => Helper::get_attribute_value( $attributes, 'products_count' ),
			'column_per_row'        => Helper::get_attribute_value( $attributes, 'products_columns' ),
			'has_pagination'        => Helper::get_attribute_value( $attributes, 'show_pagination' ),
			'products_level'          => Helper::get_attribute_value( $attributes, 'difficulty_levels' ),
			'price_type'            => Helper::get_attribute_value( $attributes, 'price_types' ),
			'orderby'               => Helper::get_attribute_value( $attributes, 'order_by' ),
			'order'                 => Helper::get_attribute_value( $attributes, 'products_order' ),
			'ids'                   => Helper::get_attribute_value( $attributes, 'products_ids' ),
			'exclude_ids'           => Helper::get_attribute_value( $attributes, 'products_exclude_ids' ),
			'category'              => Helper::get_attribute_value( $attributes, 'products_categories' ),
			'cat_not_in'            => Helper::get_attribute_value( $attributes, 'products_exclude_categories' ),
			'tag'                   => Helper::get_attribute_value( $attributes, 'products_tags' ),
			'tag_not_in'            => Helper::get_attribute_value( $attributes, 'products_exclude_tags' ),
		];
		$shortcode = '[storeengine_products ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
