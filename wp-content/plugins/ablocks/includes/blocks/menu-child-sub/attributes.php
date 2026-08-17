<?php


use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Components\ButtonGroup;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'background' => [
		'type' => 'string',
		'default' => 'white'
	],
	'direction' => [
		'type' => 'string',
		'default' => 'column',
	],
	'directionTablet' => [
		'type' => 'string',
		'default' => '',
	],
	'directionMobile' => [
		'type' => 'string',
		'default' => '',
	],
	'justify' => [
		'type' => 'string',
		'default' => '',
	],
	'justifyTablet' => [
		'type' => 'string',
		'default' => '',
	],
	'justifyMobile' => [
		'type' => 'string',
		'default' => '',
	],
	'align' => [
		'type' => 'string',
		'default' => '',
	],
	'alignTablet' => [
		'type' => 'string',
		'default' => '',
	],
	'alignMobile' => [
		'type' => 'string',
		'default' => '',
	],
	'menuItemTextColor' => [
		'type' => 'string',
		'default' => ''
	],
	'menuItemTextColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'menuItemBackground' => [
		'type' => 'string',
		'default' => '',
	],
	'menuItemBackgroundH' => [
		'type' => 'string',
		'default' => '',
	],
	'menuItemTransition' => [
		'type' => 'number',
		'default' => 0
	],
	'menuItemAlign' => [
		'type' => 'string',
		'default' => '',
		'copyStyle' => true,
	],
	'menuItemAlignTablet' => [
		'type' => 'string',
		'default' => '',
		'copyStyle' => true,
	],
	'menuItemAlignMobile' => [
		'type' => 'string',
		'default' => '',
		'copyStyle' => true,
	],
	'menuItemDirection' => [
		'type' => 'string',
		'default' => 'row',
		'copyStyle' => true,
	],
	'menuItemDirectionTablet' => [
		'type' => 'string',
		'default' => '',
		'copyStyle' => true,
	],
	'menuItemDirectionMobile' => [
		'type' => 'string',
		'default' => '',
		'copyStyle' => true,
	],
	'menuItemJustify' => [
		'type' => 'string',
		'default' => 'space-between',
		'copyStyle' => true,
	],
	'menuItemJustifyTablet' => [
		'type' => 'string',
		'default' => 'space-between',
		'copyStyle' => true,
	],
	'menuItemJustifyMobile' => [
		'type' => 'string',
		'default' => 'space-between',
		'copyStyle' => true,
	],

];

$attributes = array_merge(
	$attributes,
	ButtonGroup::get_attribute( 'menuItemJustification', true, [
		'value' => 'space-between',
	] ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Dimensions::get_attribute( 'padding', false ),
	Dimensions::get_attribute( 'menuItemPadding', false ),
	Dimensions::get_attribute( 'menuItemMargin', false ),
	Border::get_attribute( 'menuItemBorder', true ),
	Border::get_attribute( 'border', true ),
	Typography::get_attribute( 'menuItemTypography', true ),
	Range::get_attribute([
		'attributeName' => 'width',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'positionX',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,

	]),
	Range::get_attribute([
		'attributeName' => 'positionY',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
	]),
);
return $attributes;
