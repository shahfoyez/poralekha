<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type' => 'number',
		'default' => 2,
	],
	'stickyLabel' => [
		'type' => 'string',
		'default' => 'Breaking News',
	],
	'stickyLabelTag' => [
		'type' => 'string',
		'default' => 'div',
	],
	'labelPosition' => [
		'type' => 'string',
		'default' => 'left',
	],
	'queryType' => [
		'type' => 'string',
		'default' => 'customText',
	],
	'selectedPosts' => [
		'type' => 'array',
		'default' => [],
	],
	'postLink' => [
		'type' => 'boolean',
		'default' => false,
	],
	'selectedPages' => [
		'type' => 'array',
		'default' => [],
	],
	'pageLink' => [
		'type' => 'boolean',
		'default' => false,
	],
	'customText' => [
		'type' => 'string',
		'default' => ''
	],
	'lists' => [
		'type' => 'array',
		'default' => [
			[
				'id' => 0,
				'text' => 'Stay updated with the latest trends!',
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
				'isOpen' => false,
			]
		]
	],
	'markerType' => [
		'type' => 'string',
		'default' => 'icon',
	],
	'listIconsClasses' => [
		'type' => 'array',
		'default' => [],
	],
	'slideDirection' => [
		'type' => 'string',
		'default' => 'ltr',
	],
	'slideSpeed' => [
		'type' => 'number',
		'default' => 2,
	],
	'labelColor' => [
		'type' => 'string',
		'default' => 'white',
	],
	'labelBackgroundColor' => [
		'type' => 'string',
		'default' => 'black',
	],
	'labelColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'labelBackgroundColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'labelColorTransition' => [
		'type' => 'number',
		'default' => '',
	],
	'tickerColor' => [
		'type' => 'string',
		'default' => 'black',
	],
	'tickerBgColor' => [
		'type' => 'string',
		'default' => '#E5E5E6',
	],
	'tickerColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'tickerBgColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'tickerColorTransition' => [
		'type' => 'number',
		'default' => '',
	],
	'tickerType' => [
		'type' => 'string',
		'default' => 'marquee',
	],
	'navigatorBgColor' => [
		'type' => 'string',
		'default' => ''
	],
	'navigatorColor' => [
		'type' => 'string',
		'default' => '#000000'
	],
	'showTickerNavigator' => [
		'type' => 'boolean',
		'default' => false,
	],
	'navigatorPosition' => [
		'type' => 'string',
		'default' => 'right'
	],
	'tickerListStyle' => [
		'type' => 'string',
		'default' => 'none',
	],
	'isPauseOnOver' => [
		'type' => 'boolean',
		'default' => false,
	],
	'isPositionSticky' => [
		'type' => 'boolean',
		'default' => false
	],
	'stickyPosition' => [
		'type' => 'string',
		'default' => 'up'
	],
	'isShowLabel' => [
		'type' => 'boolean',
		'default' => true,
	],
	'tickerLabelShape' => [
		'type' => 'string',
		'default' => 'normal',
	],
	'labelPadding' => [
		'type' => 'number',
		'default' => 10,
	],
	'isShowTime' => [
		'type' => 'boolean',
		'default' => false,
	],
];
$attributes = array_merge(
	$attributes,
	Range::get_attribute([
		'attributeName' => 'labelPadding',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
		'defaultValue' => 10,
	]),
	Range::get_attribute([
		'attributeName' => 'tickerHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
		'defaultValue' => 50,
	]),
	Typography::get_attribute( 'labelTypography', true ),
	TextShadow::get_attribute( 'labelTextShadow' ),
	TextStroke::get_attribute( 'labelTextStroke', true ),
	Typography::get_attribute( 'tickerTypography', true ),
	TextShadow::get_attribute( 'tickerTextShadow' ),
	TextStroke::get_attribute( 'tickerTextStroke', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
