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
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'input_label_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'input_label_hover_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'input_field_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'input_field_placeholder_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'input_field_bg_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'form_button_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'form_button_background' => array(
		'type' => 'string',
		'default' => '#7B68EE',
	),
	'form_button_hover_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'form_button_hover_background' => array(
		'type' => 'string',
		'default' => '#6F5DD6',
	),



];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'input_label_typhography', true ),
	Typography::get_attribute( 'form_button_typhography', true ),
	Border::get_attribute( 'form_field_border', true ),
	Border::get_attribute( 'form_button_border', true ),
	Dimensions::get_attribute( 'form_button_padding', true ),
	Dimensions::get_attribute( 'form_padding', true ),
	Background::get_attribute( 'form_background', true ),
	Border::get_attribute( 'form_border', true ),
	Dimensions::get_attribute( 'card_hover_padding', true ),
	Dimensions::get_attribute( 'card_hover_margin', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

