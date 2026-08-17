<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;


$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'course_id' => array(
		'type' => 'number',
		'default' => 0,
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'heading_color_hover' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'heading_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'buttonBackgroundH' => array(
		'type' => 'string',
		'default' => '',
	),
	'buttonBackground' => array(
		'type' => 'string',
		'default' => '',
	),
	'buttonColorH' => array(
		'type' => 'string',
		'default' => '',
	),
	'buttonColor' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'contentColorH' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'contentColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'title_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'title_color_hover' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'lesson_list_hover' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'lesson_list_bg' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'read_icon_hover' => array(
		'type' => 'string',
		'default' => '#e12a2a',
	),
	'read_icon_color' => array(
		'type' => 'string',
		'default' => '#e12a2a',
	),
	'lock_icon_color' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'lock_icon_hover' => array(
		'type' => 'string',
		'default' => '#000',
	),
	'title_bg' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'title_bg_hover' => array(
		'type' => 'string',
		'default' => '#fff',
	),
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'buttonTypography', true ),
	Typography::get_attribute( 'title_typography', true ),
	Typography::get_attribute( 'contentTypography', true ),
	Typography::get_attribute( 'heading_typography', true ),
	Range::get_attribute([
		'attributeName' => 'lock_icon_size',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 16,
		'defaultValueTablet' => 16,
		'defaultValueMobile' => 12,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'readIconSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 16,
		'defaultValueTablet' => 16,
		'defaultValueMobile' => 12,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

