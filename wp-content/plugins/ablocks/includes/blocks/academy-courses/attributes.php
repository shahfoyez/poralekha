<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => [
		'type' => 'number',
		'default' => 2,
	],
	'course_count' => array(
		'type' => 'number',
		'default' => 3,
	),
	'course_columns' => array(
		'type' => 'number',
		'default' => 3,
	),
	'show_pagination' => array(
		'type' => 'boolean',
		'default' => true,

	),
	'difficulty_levels' => array(
		'type' => 'array',
		'default' => array(),
	),
	'price_types' => array(
		'type' => 'array',
		'default' => array(),
	),
	'order_by' => array(
		'type' => 'string',
		'default' => 'date',
	),
	'course_order' => array(
		'type' => 'string',
		'default' => 'DESC',
	),
	'course_ids' => array(
		'type' => 'array',
		'default' => array(),
	),
	'course_categories' => array(
		'type' => 'array',
		'default' => array(),
	),
	'course_tags' => array(
		'type' => 'array',
		'default' => array(),
	),
	'course_exclude_ids' => array(
		'type' => 'array',
		'default' => array(),
	),
	'course_exclude_categories' => array(
		'type' => 'array',
		'default' => array(),
	),
	'course_exclude_tags' => array(
		'type' => 'array',
		'default' => array(),
	),
	'category_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'category_hover_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'title_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'title_hover_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'author_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'author_hover_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'rating_color' => array(
		'type' => 'string',
		'default' => '#5a3d00',
	),
	'rating_hover_color' => array(
		'type' => 'string',
		'default' => '#5a3d00',
	),
	'price_color' => array(
		'type' => 'string',
		'default' => '#1a1a1a',
	),
	'price_hover_color' => array(
		'type' => 'string',
		'default' => '#1a1a1a',
	),
	'wish_icon_color' => array(
		'type' => 'string',
		'default' => '#999',
	),
	'wish_icon_hover_color' => array(
		'type' => 'string',
		'default' => '#999',
	),
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'cat_typography', true ),
	Typography::get_attribute( 'title_typography', true ),
	Typography::get_attribute( 'author_typography', true ),
	Typography::get_attribute( 'rating_typography', true ),
	Typography::get_attribute( 'price_typography', true ),
	Background::get_attribute( 'card_background', true ),
	Border::get_attribute( 'card_border', true ),
	Dimensions::get_attribute( 'card_margin', true ),
	Dimensions::get_attribute( 'card_hover_margin', true ),
	Dimensions::get_attribute( 'card_padding', true ),
	Dimensions::get_attribute( 'card_hover_padding', true ),
	Background::get_attribute( 'wish_icon_background', true )
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

