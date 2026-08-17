<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Icon;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'text_date' => [
		'type' => 'string',
		'default' => 'January 1, 2024',
	],
	'indexContent' => [
		'type' => 'number',
	],
	'parentAttribute' => [
		'type' => 'object',
		'default' => new stdClass(),
	],
	'changeChildIcon' => [
		'type' => 'boolean',
		'default' => false
	],
	'contentPosition' => [
		'type' => 'string',
		'default' => 'center',
	],
	'arrowAlignment' => [
		'type' => 'string',
		'default' => 'center',
	],
	'iconColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
	'iconBackgroundColor' => [
		'type' => 'string',
		'default' => '#eee',
	],
	'thicknessColor' => [
		'type' => 'string',
		'default' => '#eee',
	],
	'showAnimation' => [
		'type' => 'boolean',
		'default' => true,
	],
	'connectorAnimationColor' => [
		'type' => 'string',
		'default' => '#00ad6b',
	],
	'contentBackgroundColor' => [
		'type' => 'string',
		'default' => '#eee',
	],
	'showDate' => [
		'type' => 'boolean',
		'default' => true,
	],
	'showDateTablet' => [
		'type' => 'boolean',
		'default' => true,
	],
	'showDateMobile' => [
		'type' => 'boolean',
		'default' => true,
	],
	'dateColor' => [
		'type' => 'string',
		'default' => '#333333'
	],
	'dateAlign' => [
		'type' => 'string',
		'default' => 'left',
	],
	'dateFormat' => [
		'type' => 'string',
		'default' => 'F j, Y',
	],
	'dateBackground' => [
		'type' => 'string',
		'default' => '#eee',
	]

];
$attributes = array_merge(
	$attributes,
	Range::get_attribute( [
		'attributeName' => 'iconBackgroundSize',
		'isResponsive' => false,
		'defaultValue' => 48,
	] ),
	Range::get_attribute([
		'attributeName' => 'itemGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px'
	] ),
	Range::get_attribute([
		'attributeName' => 'thickness',
		'isResponsive' => false,
		'defaultValue' => 3,
	] ),
	Range::get_attribute([
		'attributeName' => 'iconSize',
		'isResponsive' => false,
		'defaultValue' => 18,
	] ),
	Icon::get_attribute(
		'icon', [
			'path' => 'M256 504c137 0 248-111 248-248S393 8 256 8 8 119 8 256s111 248 248 248zm0-448c110.5 0 200 89.5 200 200s-89.5 200-200 200S56 366.5 56 256 145.5 56 256 56zm20 328h-40c-6.6 0-12-5.4-12-12V256h-67c-10.7 0-16-12.9-8.5-20.5l99-99c4.7-4.7 12.3-4.7 17 0l99 99c7.6 7.6 2.2 20.5-8.5 20.5h-67v116c0 6.6-5.4 12-12 12z',
			'viewBox' => '0 0 512 512',
			'className' => 'far fa-arrow-alt-circle-up',
		]
	),
	Typography::get_attribute( 'dateTypography', true ),
	Dimensions::get_attribute( 'contentPadding', true ),
	Dimensions::get_attribute( 'datePadding', true ),
	Border::get_attribute( 'dateBorder', true ),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );


