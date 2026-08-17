<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Components\ButtonGroup;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => ''
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'isSwitch' => [
		'type' => 'boolean',
		'default' => false,
	],
	'leftLabel' => [
		'type' => 'string',
		'default' => 'Monthly',
	],
	'rightLabel' => [
		'type' => 'string',
		'default' => 'Yearly',
	],
	'toggleBarBgColor' => [
		'type' => 'string',
		'default' => ''
	],
	'labelNormalColor' => [
		'type' => 'string',
		'default' => 'black',
	],
	'labelActiveColor' => [
		'type' => 'string',
		'default' => '#562DD4',
	],
	'toggleNormalColor' => [
		'type' => 'string',
		'default' => 'white',
	],
	'toggleNormalBgColor' => [
		'type' => 'string',
		'default' => '#562DD4',
	],
	'toggleActiveColor' => [
		'type' => 'string',
		'default' => 'white',
	],
	'toggleActiveBgColor' => [
		'type' => 'string',
		'default' => '#d4552d',
	],
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'center' ] ),
	Typography::get_attribute( 'labelTypography', true ),
	Dimensions::get_attribute( 'toggleBarPadding', true ),
	Border::get_attribute( 'toggleBarBorder', true ),
	ButtonGroup::get_attribute( 'toggleDirection', false, [
		'value' => 'row',
	] ),
	ButtonGroup::get_attribute( 'labelColorState', false, [
		'value' => 'normal',
	] ),
	ButtonGroup::get_attribute( 'toggleColorState', false, [
		'value' => 'normal',
	] ),
	Range::get_attribute([
		'attributeName' => 'space',
		'isResponsive' => true,
		'attributeObjectKey' => 'value',
		'defaultValue' => 10,
		'hasUnit' => false,
	]),
	Range::get_attribute([
		'attributeName' => 'gap',
		'isResponsive' => true,
		'attributeObjectKey' => 'value',
		'defaultValue' => 10,
		'hasUnit' => false,
	]),
	Range::get_attribute([
		'attributeName' => 'toggleWidth',
		'isResponsive' => false,
		'defaultValue' => 60,
	]),
	Range::get_attribute([
		'attributeName' => 'toggleHeight',
		'isResponsive' => false,
		'defaultValue' => 28,
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

