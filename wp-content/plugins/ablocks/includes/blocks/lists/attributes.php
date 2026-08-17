<?php

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Components\ButtonGroup;
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
	'markerType' => [
		'type' => 'string',
		'default' => 'icon',
	],
	'markerColor' => [
		'type' => 'string',
		'default' => ''
	],
	'emoji' => [
		'type' => 'string',
		'default' => '✍',
	],
	'iconColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
	'lists' => [
		'type' => 'array',
		'default' => [
			[
				'id' => 0,
				'text' => '',
				'link' => [
					'linkDestination' => '',
					'href' => '',
					'lightbox' => '',
					'linkTarget' => '',
					'rel' => '',
					'noFollow' => '',
					'keyValue' => '',
					'linkClass' => '',
				],
				'iconColor' => '',
				'textColor' => '',
				'makerColor' => '',
				'isOpen' => false,
			]
		]
	],
	'listIconsClasses' => [
		'type' => 'array',
		'default' => [],
	],
	'listIcons' => [
		'type' => 'array',
		'default' => []
	],
	'iconType' => [
		'type' => 'string',
		'default' => 'default',
	],
	'iconShape' => [
		'type' => 'string',
		'default' => 'circle',
	],
	'iconBackground' => [
		'type' => 'boolean',
		'default' => false,
	],
	'iconBackgroundColor' => [
		'type' => 'string',
		'default' => '',
	],
	'textColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
	'belowItem' => [
		'type' => 'number',
		'default' => 0,
	],
	'divider' => [
		'type' => 'boolean',
		'default' => false,
	],
	'dividerPatternUrl' => [
		'type' => 'string',
		'default' => 'solid',
	],
	'borderColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'position', true, [ 'value' => 'center' ] ),
	Alignment::get_attribute( 'horizontalAlignment', true, [ 'value' => 'flex-start' ] ),
	Dimensions::get_attribute( 'padding', false ),
	Border::get_attribute( 'border', true ),
	Typography::get_attribute( 'typography', true ),
	ButtonGroup::get_attribute( 'verticalAlignment', false, [
		'value' => 'flex-start',
	] ),
	ButtonGroup::get_attribute( 'stack', false, [
		'value' => 'vertical',
	] ),
	Range::get_attribute([
		'attributeName' => 'iconSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'markerSize',
		'attributeObjectKey' => 'value',
		'defaultValue' => 10,
		'defaultValueTablet' => 10,
		'defaultValueMobile' => 10,
	]),
	Range::get_attribute([
		'attributeName' => 'textIndent',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 8,
		'defaultValueTablet' => 8,
		'defaultValueMobile' => 8,

	]),
	Range::get_attribute([
		'attributeName' => 'spaceBetween',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'defaultValueTablet' => 20,
		'defaultValueMobile' => 20,
	]),
	Range::get_attribute([
		'attributeName' => 'width',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'weight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 5,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

