<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;

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
	'course_id' => array(
		'type' => 'number',
		'default' => 0,
	),
	'layout' => array(
		'type' => 'string',
		'default' => 'legacy',
	),
	'start_btn_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'enroll_btn_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'start_btn_color_hover' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'enroll_btn_color_hover' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'start_btn_bg_color' => array(
		'type' => 'string',
		'default' => '#7B68EE',
	),
	'enroll_btn_bg_color' => array(
		'type' => 'string',
		'default' => '#7B68EE',
	),
	'start_btn_bg_hover_color' => array(
		'type' => 'string',
		'default' => '#6F5DD6',
	),
	'enroll_btn_bg_hover_color' => array(
		'type' => 'string',
		'default' => '#6F5DD6',
	),
	'massage_title_bg' => array(
		'type' => 'string',
		'default' => '',
	),
	'massage_title_color' => array(
		'type' => 'string',
		'default' => '',
	),
	'massage_title_hover_bg' => array(
		'type' => 'string',
		'default' => '',
	),
	'massage_title_hover_color' => array(
		'type' => 'string',
		'default' => '',
	),
	'list_color' => array(
		'type' => 'string',
		'default' => '',
	),
	'list_hover_color' => array(
		'type' => 'string',
		'default' => '',
	),
	'price_hover_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'price_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'price_title_hover_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'price_title_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'info_color_hover' => array(
		'type' => 'string',
		'default' => '#7b68ee',
	),
	'info_color' => array(
		'type' => 'string',
		'default' => '#7b68ee',
	),
	'info_bg_hover' => array(
		'type' => 'string',
		'default' => '#eae8fa',
	),
	'info_bg' => array(
		'type' => 'string',
		'default' => '#eae8fa',
	),

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'start_btn_typography', true ),
	Typography::get_attribute( 'price_typography', true ),
	Typography::get_attribute( 'price_title_typography', true ),
	Typography::get_attribute( 'list_typography', true ),
	Typography::get_attribute( 'massage_title_typography', true ),
	Typography::get_attribute( 'info_typography', true ),
	Dimensions::get_attribute( 'start_btn_padding', true ),
	Dimensions::get_attribute( 'enroll_btn_padding', true ),
	Typography::get_attribute( 'enroll_btn_typography', true ),
	Border::get_attribute( 'start_btn_border', true ),
	Border::get_attribute( 'enroll_btn_border', true ),
	Range::get_attribute([
		'attributeName' => 'modalWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 150,
		'defaultValueTablet' => 100,
		'defaultValueMobile' => 80,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'bg_transition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

