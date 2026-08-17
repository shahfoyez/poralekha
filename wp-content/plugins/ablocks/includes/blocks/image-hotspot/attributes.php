<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'backgroundImage' => array(
		'type' => 'string',
		'default' => '',
	),
	'activeIndex' => array(
		'type' => 'number',
		'default' => 0,
	),
	// image size option
	'imageSizes' => array(
		'type' => 'object',
		'default' => array(),
	),
	'selectedImageSize' => array(
		'type' => 'string',
		'default' => '',
	),


	'pinColor' => array(
		'type' => 'string',
		'default' => '#d9d9d9',
	),
	'pinColorEffect' => array(
		'type' => 'string',
		'default' => '#d9d9d9',
	),
	'pinHoverColor' => array(
		'type' => 'string',
		'default' => '#d9d9d9',
	),
	'pinSize' => array(
		'type' => 'number',
		'default' => 15,
	),
	'pinHoverSize' => array(
		'type' => 'number',
		'default' => 1.5,
	),
	'lists' => array(
		'type' => 'array',
		'default' => array(
			array(
				'id' => 0,
				'text' => 'Your title here 0',
				'xAxis' => 35,
				'yAxis' => 32,
			    'xAxisTab' => 30,
				'yAxisTab' => 28,
				'xAxisMob' => 22,
				'yAxisMob' => 16,
				'isOpen' => false,
				'pinColor' => '',
				'pinColorEffect' => '',
				'pinHoverColor' => '',
				'pinSize' => '',
				'pinHoverSize' => '',
			),
		),
	),
	'animationType' => array(
		'type' => 'string',
		'default' => 'ablocks-hotspot-puls-effect 1s ease infinite',
	),
	'contentAnimation' => array(
		'type' => 'string',
		'default' => 'ablocks-hotspot-fadeIn',
	),
	'contentTrigger' => array(
		'type' => 'string',
		'default' => 'onClick',
	),
	'contentPosition' => array(
		'type' => 'string',
		'default' => 'bottom',
	),
	'backgroundColor' => array(
		'type' => 'string',
		'default' => 'white',
	),
];

$attributes = array_merge(
	$attributes,
	Range::get_attribute([
		'attributeName' => 'childWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 200,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	BoxShadow::get_attribute( 'commonBoxShadow' ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
