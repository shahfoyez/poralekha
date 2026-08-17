<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Alignment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'products_ids' => [
		'type' => 'array',
		'default' => [],
	],
	'products_categories' => [
		'type' => 'array',
		'default' => [],
	],
	'products_tags' => [
		'type' => 'array',
		'default' => [],
	],
	'products_exclude_ids' => [
		'type' => 'array',
		'default' => [],
	],
	'products_exclude_categories' => [
		'type' => 'array',
		'default' => [],
	],
	'products_exclude_tags' => [
		'type' => 'array',
		'default' => [],
	],
	'category_color' => [
		'type' => 'string',
		'default' => '#000',
	],
	'category_hover_color' => [
		'type' => 'string',
		'default' => '#000',
	],

	'products_count' => [
		'type' => 'number',
		'default' => 3,
	],
	'products_columns' => [
		'type' => 'number',
		'default' => 3,
	],
	'show_pagination' => [
		'type' => 'boolean',
		'default' => true,
	],
	'difficulty_levels' => [
		'type' => 'array',
		'default' => [],
	],
	'price_types' => [
		'type' => 'array',
		'default' => [],
	],
	'cart_button_color' => [
		'type' => 'string',
		'default' => '',
	],
	'cart_button_hover_color' => [
		'type' => 'string',
		'default' => '',
	],
	'cart_button_text_color' => [
		'type' => 'string',
		'default' => '',
	],
	'cart_button_text_hover_color' => [
		'type' => 'string',
		'default' => '',
	],
	'cart_button_transition' => [
		'type' => 'number',
		'default' => 0,
	],
	'order_by' => [
		'type' => 'string',
		'default' => 'date',
	],
	'products_order' => [
		'type' => 'string',
		'default' => 'DESC',
	],
	'title_color' => [
		'type' => 'string',
		'default' => '#000',
	],
	'title_hover_color' => [
		'type' => 'string',
		'default' => '',
	],
	'price_color' => [
		'type' => 'string',
		'default' => '',
	],
	'price_hover_color' => [
		'type' => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'cat_typography', true ),
	Typography::get_attribute( 'cart_button_typography', true ),
	Typography::get_attribute( 'title_typography', true ),
	Typography::get_attribute( 'author_typography', true ),
	Typography::get_attribute( 'rating_typography', true ),
	Typography::get_attribute( 'price_typography', true ),
	Background::get_attribute( 'card_background', true ),
	Border::get_attribute( 'card_border', true ),
	Border::get_attribute( 'cart_border', true ),
	Dimensions::get_attribute( 'card_margin', true ),
	Dimensions::get_attribute( 'card_hover_margin', true ),
	Dimensions::get_attribute( 'card_padding', true ),
	Dimensions::get_attribute( 'card_hover_padding', true ),
	Background::get_attribute( 'wish_icon_background', true ),
	Background::get_attribute( 'wish_icon_background', true ),
	Alignment::get_attribute( 'titleTextAlign', true, [ 'value' => 'center' ] ),
	Alignment::get_attribute( 'priceTextAlign', true, [ 'value' => 'center' ] ),
	Range::get_attribute([
		'attributeName' => 'buttonWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => false,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute([
		'attributeName' => 'buttonDriection',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 'column',
		'hasUnit' => false,
		'unitDefaultValue' => '',
	]),
	Range::get_attribute([
		'attributeName' => 'buttonGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 8,
		'hasUnit' => false,
		// 'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

