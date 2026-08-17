<?php

use ABlocks\Controls\Alignment;
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
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'enableSegments' => [
		'type' => 'boolean',
		'default' => false,
	],
	'animation' => [
		'type' => 'boolean',
		'default' => false,
	],
	'showIconInButton' => [
		'type' => 'boolean',
		'default' => true,
	],
	'showTextInButton' => [
		'type' => 'boolean',
		'default' => true,
	],		
	'itemTitle' => [
		'type' => 'string',
		'default' => 'Demo item title',
	],
	'itemText' => [
		'type' => 'string',
		'default' => 'Demo item text',
	],		
	'itemTrigger' => [
		'type' => 'string',
		'default' => 'click',
	],		
	'animationSpeed' => [
		'type' => 'number',
		'default' => 20,
	],	
	'selectedItemNumber' => [
		'type' => 'number',
		'default' => 1,
	],	
	'layoutShowTitle' => [
		'type' => 'boolean',
		'default' => true,
	],
	'layoutShowText' => [
		'type' => 'boolean',
		'default' => true,
	],
	'layoutShowImage' => [
		'type' => 'boolean',
		'default' => false,
	],			
	'layoutImagePosition' => [
		'type' => 'string',
		'default' => 'bottom',
	],		
	'layoutImageType' => [
		'type' => 'string',
		'default' => 'bottom',
	],		

	'iconColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
'itemLists' => [
    'type'    => 'array',
    'default' => [
        [
            'id' => 0,
            'isOpen' => false,
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
            'iconTitle' => 'Title',
            'iconTitleColor' => '',
            'iconColor' => '',
            'buttonBgColor' => '',
            'title' => 'Info Title',
            'titleColor' => '',
            'dividerType' => '',
            'dividerColor' => '',
            'description' => 'Info Description',
            'descriptionColor' => '',
            'backgroundImage' => '',
            'selectedImageSize' => 40,
            'itemButtonTransition' => 10,


        ],
    ],
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
		'titleColor' => [
		'type' => 'string',
		'default' => '#000000',
	],

	'divider' => [
		'type' => 'boolean',
		'default' => true,
	],
	'dividerPatternUrl' => [
		'type' => 'string',
		'default' => 'solid',
	],
	'borderColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
		'layoutColor' => [
		'type' => 'string',
		'default' => '#eddfdf',
	],
		'itemColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
		'defaultImg' => [
		'type' => 'string',
		'default' => '',
	],	
		'itemButtonColor' => [
		'type' => 'string',
		'default' => '#c3b021',
	],
];

$attributes = array_merge(
	$attributes,
	Border::get_attribute( 'circleBorder', true ),
	Border::get_attribute( 'layoutBorder', true ),
	Dimensions::get_attribute( 'circlePadding', true ),
	Dimensions::get_attribute( 'layoutPadding', true ),
	BoxShadow::get_attribute( 'circleBoxShadow', true ),
	BoxShadow::get_attribute( 'layoutBoxShadow', true ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'center' ] ),
	Dimensions::get_attribute( 'padding', false ),
	Dimensions::get_attribute( 'segMargin', false ),
	Border::get_attribute( 'border', true ),
	Typography::get_attribute( 'itemTypography', true ),
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'textTypography', true ),

	Range::get_attribute([
		'attributeName' => 'iconSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),

	Range::get_attribute([
		'attributeName' => 'innerCircleSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 300,
		'defaultValueTablet' => 260,
		'defaultValueMobile' => 150,

	]),
	Range::get_attribute([
		'attributeName' => 'itemButtonSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 150,
		'defaultValueTablet' => 120,
		'defaultValueMobile' => 80,

	]),	
		Range::get_attribute([
		'attributeName' => 'itemPosition',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 50,
		'defaultValueTablet' => 30,
		'defaultValueMobile' => 20,

	]),	
	Range::get_attribute([
		'attributeName' => 'circleSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 450,
		'defaultValueTablet' => 350,
		'defaultValueMobile' => 250,
	]),
	Range::get_attribute([
		'attributeName' => 'dividerWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'itemSpacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 1,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),	
	Range::get_attribute([
		'attributeName' => 'imgRadius',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),		
	Range::get_attribute([
		'attributeName' => 'imgWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'imgHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),		
	Range::get_attribute([
		'attributeName' => 'dividerWeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 5,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

