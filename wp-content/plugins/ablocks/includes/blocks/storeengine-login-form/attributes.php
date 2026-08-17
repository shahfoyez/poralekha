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
	'form_title' => array(
		'type' => 'string',
		'default' => 'Log In into your Account',
	),
	'username_label' => array(
		'type' => 'string',
		'default' => 'Username or Email Address',
	),
	'username_placeholder' => array(
		'type' => 'string',
		'default' => 'Username or Email Address',
	),
	'password_label' => array(
		'type' => 'string',
		'default' => 'Password',
	),
	'password_placeholder' => array(
		'type' => 'string',
		'default' => 'Password',
	),
	'remember_label' => array(
		'type' => 'string',
		'default' => 'Remember me',
	),
	'login_button_label' => array(
		'type' => 'string',
		'default' => 'Log in',
	),
	'reset_password_label' => array(
		'type' => 'string',
		'default' => 'Reset password',
	),
	'show_logged_in_message' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'customer_register_url' => array(
		'type' => 'string',
		'default' => '',
	),
	'login_redirect_url' => array(
		'type' => 'string',
		'default' => '',
	),
	'logout_redirect_url' => array(
		'type' => 'string',
		'default' => '',
	),
	'login_btn_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'login_btn_bg_color' => array(
		'type' => 'string',
		'default' => '#7B68EE',
	),
	'login_btn_bg_hover_color' => array(
		'type' => 'string',
		'default' => '#6F5DD6',
	),
	'login_btn_hover_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'title_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'title_hover_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'input_field_label_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'input_field_label_hover_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'form_footer_title_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'input_field_bg_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'inputFieldColor' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'input_field_bg_hover_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'inputFieldColorH' => array(
		'type' => 'string',
		'default' => '#333',
	),
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'login_btn_typography', true ),
	Background::get_attribute( 'form_bg_color', true ),
	Typography::get_attribute( 'title_typography', true ),
	Typography::get_attribute( 'input_field_label_typography', true ),
	Typography::get_attribute( 'form_footer_title_typography', true ),
	Border::get_attribute( 'input_field_border', true ),
	Border::get_attribute( 'form_border', true ),
	Dimensions::get_attribute( 'input_field_padding', true ),
	Dimensions::get_attribute( 'form_padding', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

