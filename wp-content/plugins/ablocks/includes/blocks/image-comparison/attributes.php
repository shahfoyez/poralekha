<?php

use ABlocks\Controls\Range;
use ABlocks\Controls\Border;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'beforeImage' => [
		'type' => 'string',
		'default' => '',
	],
	'afterImage' => [
		'type' => 'string',
		'default' => '',
	],
	'sliderOrientation' => [
		'type' => 'string',
		'default' => 'horizontal',
	],
	'showLabels' => [
		'type' => 'boolean',
		'default' => false,
	],
	'labelPosition' => [
		'type' => 'number',
		'default' => 45,
	],
	'labelBgColor' => [
		'type' => 'string',
		'default' => 'rgba(0, 0, 0, 0.5)',
	],
	'labelTextColor' => [
		'type' => 'string',
		'default' => 'white',
	],
	'labelWithOverlay' => [
		'type' => 'boolean',
		'default' => false,
	],
	'labelOverlayColor' => [
		'type' => 'string',
		'default' => 'rgba(0, 0, 0, 0.5)',
	],
	'labelBorderType' => [
		'type' => 'string',
		'default' => 'solid',
	],
	'labelOnHover' => [
		'type' => 'boolean',
		'default' => false,
	],
	'beforeImageLabel' => [
		'type' => 'string',
		'default' => 'Before',
	],
	'afterImageLabel' => [
		'type' => 'string',
		'default' => 'After',
	],
	'showHandle' => [
		'type' => 'boolean',
		'default' => true,
	],
	'moveOnHover' => [
		'type' => 'boolean',
		'default' => false,
	],
	'handleColor' => [
		'type' => 'string',
		'default' => 'white',
	],
	'swapImages' => [
		'type' => 'boolean',
		'default' => false,
	],
];
$attributes = array_merge(
	$attributes,
	Range::get_attribute( [
		'attributeName' => 'sliderPosition',
		'isResponsive' => false,
		'defaultValue' => 50,
	]),
	Range::get_attribute( [
		'attributeName' => 'sliderBarSize',
		'isResponsive' => true,
		'defaultValue' => 4,
	]),
	Range::get_attribute( [
		'attributeName' => 'sliderIconSize',
		'isResponsive' => true,
		'defaultValue' => 50,
	]),
	Range::get_attribute( [
		'attributeName' => 'sliderIconBorderSize',
		'isResponsive' => true,
		'defaultValue' => 2,
	]),
	Border::get_attribute( 'labelBorder', true ),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

