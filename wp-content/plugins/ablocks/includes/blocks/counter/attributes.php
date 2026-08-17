<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Icon;
use ABlocks\Components\ButtonGroup;
$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type' => 'number',
		'default' => '',
	],
	'isShowIcon' => [
		'type' => 'boolean',
		'default' => true,
	],
	'mediaPosition' => [
		'type' => 'string',
		'default' => 'top',
	],
	'startNumber' => [
		'type' => 'number',
		'default' => 0,
	],
	'endNumber' => [
		'type' => 'number',
		'default' => 80,
	],
	'totalNumber' => [
		'type' => 'number',
		'default' => 100,
	],

	'animationRepeat' => [
		'type' => 'boolean',
		'default' => false,
	],
	'counterPrefix' => [
		'type' => 'string',
		'default' => '',
	],
	'counterSuffix' => [
		'type' => 'string',
		'default' => '%',
	],
	'separator' => [
		'type' => 'string',
		'default' => ',',
	],
	'counterTitle' => [
		'type' => 'string',
		'default' => 'Enter your title',
	],
	'numberColor' => [
		'type' => 'string',
		'default' => '#08fd00ff',
	],
	'headingColor' => [
		'type' => 'string',
		'default' => '#4B4F58',
	],
	'circleProgressColor' => [
		'type' => 'string',
		'default' => 'black',
	],
	'circleBackgroundColor' => [
		'type' => 'string',
		'default' => '#dadada',
	],
	'barProgressColor' => [
		'type' => 'string',
		'default' => 'black',
	],
	'barBackgroundColor' => [
		'type' => 'string',
		'default' => '#dadada',
	],
	'barHeadingPosition' => [
		'type' => 'string',
		'default' => 'top'
	],

];
$attributes = array_merge(
	Dimensions::get_attribute( 'numberMargin', true ),
	Dimensions::get_attribute( 'headingMargin', true ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'center' ] ),
	Typography::get_attribute( 'numberTypography', true ),
	Typography::get_attribute( 'headingTypography', true ),
	ButtonGroup::get_attribute( 'layout', false, [
		'value' => 'number',
	] ),
	Range::get_attribute( [
		'attributeName' => 'duration',
		'isResponsive' => false,
		'defaultValue' => 1000,
	] ),
	Range::get_attribute( [
		'attributeName' => 'decimalPlaces',
		'isResponsive' => false,
		'defaultValue' => 0,
	] ),
	Range::get_attribute( [
		'attributeName' => 'circleSize',
		'isResponsive' => false,
		'defaultValue' => 220,
	] ),
	Range::get_attribute( [
		'attributeName' => 'circleStrokeSize',
		'isResponsive' => false,
		'defaultValue' => 20,
	]),
	Range::get_attribute( [
		'attributeName' => 'barSize',
		'isResponsive' => true,
		'defaultValue' => 30,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
		'copyStyle' => true,
	] ),
	Range::get_attribute( [
		'attributeName' => 'iconSize',
		'isResponsive' => true,
		'attributeObjectKey' => 'value',
		'defaultValue' => 30,
	]),
	Range::get_attribute( [
		'attributeName' => 'iconRotate',
		'isResponsive' => false,
		'defaultValue' => 0,
	]),
	Icon::get_attribute(),
	$attributes
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

