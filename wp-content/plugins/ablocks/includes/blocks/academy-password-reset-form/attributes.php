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
	'form_title' => array(
		'type' => 'string',
		'default' => 'RESET YOUR PASSWORD',
	),
	'username_label' => array(
		'type' => 'string',
		'default' => 'Username or Email Address',
	),
	'reset_button_label' => array(
		'type' => 'string',
		'default' => 'Get New Password',
	),
	'login_button_label' => array(
		'type' => 'string',
		'default' => 'Back To Login',
	),
	'show_logged_in_message' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'form_background_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'form_background_hover_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'label_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'input_field_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'button_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'button_hover_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'button_background_color' => array(
		'type' => 'string',
		'default' => '#7B68EE',
	),
	'button_background_hover_color' => array(
		'type' => 'string',
		'default' => '#6F5DD6',
	),
	'form_title_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'form_footer_title_color' => array(
		'type' => 'string',
		'default' => '#333',
	),




];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'label_typography', true ),
	Typography::get_attribute( 'input_field_typography', true ),
	Typography::get_attribute( 'button_typography', true ),
	Typography::get_attribute( 'form_title_typography', true ),
	Typography::get_attribute( 'form_footer_title_typography', true ),
	Background::get_attribute( 'card_background', true ),
	Border::get_attribute( 'form_border', true ),
	Border::get_attribute( 'input_border', true ),
	Border::get_attribute( 'button_border', true ),
	Dimensions::get_attribute( 'card_margin', true ),
	Dimensions::get_attribute( 'button_padding', true ),
	Dimensions::get_attribute( 'form_padding', true ),
	Dimensions::get_attribute( 'input_padding', true ),
	Background::get_attribute( 'wish_icon_background', true )
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

