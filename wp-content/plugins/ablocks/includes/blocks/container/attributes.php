<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Range;
use ABlocks\Controls\BackgroundOverlay;
use ABlocks\Components\ButtonGroup;
use ABlocks\Controls\Link;

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => ''
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'className' => [
		'type' => 'string',
		'default' => '',
	],
	'isRootContainer' => [
		'type' => 'boolean',
		'default' => false,
	],
	'variationSelected' => [
		'type' => 'boolean',
		'default' => false,
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
	'wrap' => [
		'type' => 'string',
		'default' => '',
	],
	'wrapTablet' => [
		'type' => 'string',
		'default' => '',
	],
	'wrapMobile' => [
		'type' => 'string',
		'default' => '',
	],
	'layout' => [
		'type' => 'string',
		'default' => '',
	],
	'htmlTag' => [
		'type' => 'string',
		'default' => '',
	],

	// shape divider
	'shapeTop' => [
		'type' => 'string',
		'default' => '',
	],
	'shapeBottom' => [
		'type' => 'string',
		'default' => '',
	],
	'shapeTopColor' => [
		'type' => 'string',
		'default' => '#61CE70',
	],
	'shapeBottomColor' => [
		'type' => 'string',
		'default' => '#61CE70',
	],
	'bottomShapeBringToFront' => [
		'type' => 'boolean',
		'default' => false,
	],
	'topShapeBringToFront' => [
		'type' => 'boolean',
		'default' => false,
	],
	'topShapeFlip' => [
		'type' => 'boolean',
		'default' => false,
	],
	'bottomShapeFlip' => [
		'type' => 'boolean',
		'default' => false,
	],

];

$columnGap = Range::get_attribute([
	'attributeName' => 'gap',
	'attributeObjectKey' => 'columnGap',
	'isResponsive' => true,
	'defaultValue' => 0,
	'hasUnit' => true,
	'unitDefaultValue' => 'px',
]);
$rowGap = Range::get_attribute([
	'attributeName' => 'gap',
	'attributeObjectKey' => 'rowGap',
	'isResponsive' => true,
	'defaultValue' => 0,
	'hasUnit' => true,
	'unitDefaultValue' => 'px',
]);

$attributes = array_merge(
	$attributes,
	[
		'gap' => [
			'type' => 'object',
			'default' => array_merge( $columnGap['gap']['default'], $rowGap['gap']['default'] ),
		]
	],
	ButtonGroup::get_attribute( 'containerWidthType', false, [
		'value' => 'boxed',
	] ),
	ButtonGroup::get_attribute( 'overflow', false, [
		'value' => 'visible',
	] ),
	ButtonGroup::get_attribute( 'dir', true, [
		'value' => 'column',
	] ),
	ButtonGroup::get_attribute( 'justification', true, [
		'value' => '',
	] ),
	ButtonGroup::get_attribute( 'alignment', true, [
		'value' => '',
	] ),
	ButtonGroup::get_attribute( 'wrapping', true, [
		'value' => '',
	] ),
	Range::get_attribute( [
		'attributeName' => 'minimumHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => null,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute([
		'attributeName' => 'containerWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute([
		'attributeName' => 'gridColumn',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'defaultValueTablet' => 2,
		'defaultValueMobile' => 1,
		'hasUnit' => false,
		'unitDefaultValue' => '',
	]),
	Range::get_attribute([
		'attributeName' => 'gridRow',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => '',
		'hasUnit' => false,
		'unitDefaultValue' => '',
	]),
	Range::get_attribute([
		'attributeName' => 'containerContentWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => '',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'shapeBottomHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => '100',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'shapeBottomWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => '100',
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute([
		'attributeName' => 'shapeTopHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => '100',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'shapeTopWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => '100',
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	BackgroundOverlay::get_attribute( '_backgroundOverlay', true ),
	Link::get_attribute( 'link' ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
