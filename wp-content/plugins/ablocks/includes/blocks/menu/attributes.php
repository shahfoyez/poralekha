<?php


use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
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
	'alignment' => [
		'type' => 'string',
		'default' => 'left'
	],

	'menuItemTextColor' => [
		'type' => 'string',
		'default' => ''
	],
	'menuItemTextColorH' => [
		'type' => 'string',
		'default' => '#000000',
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
	'menuItemDisplay' => [
		'type' => 'string',
		'default' => 'inline',
	],
	'hamburgerAlignment' => [
		'type' => 'string',
		'default' => 'left'
	],
	'hamburgerColor' => [
		'type' => 'string',
		'default' => 'black'
	],
	'hamburgerBackground' => [
		'type' => 'string',
		'default' => ''
	],
	'sideBarMenuDevice' => [
		'type' => 'string',
		'default' => 'mobile'
	],
	'mobileMenuPosition' => [
		'type' => 'string',
		'default' => 'below-header'
	],
	'subMenuTrigger' => [
		'type' => 'string',
		'default' => 'hover'
	],
	'menuResponsiveBackground' => [
		'type' => 'string',
		'default' => ''
	],
	'menuResponsiveTextColor' => [
		'type' => 'string',
		'default' => ''
	],
	'subMenuResponsiveColor' => [
		'type' => 'string',
		'default' => ''
	],
	'subMenuResponsiveBg' => [
		'type' => 'string',
		'default' => ''
	],
	'subMenuItemTextColor' => [
		'type' => 'string',
		'default' => '',
	],
	'subMenuItemBackground' => [
		'type' => 'string',
		'default' => '',
	],
	'subMenuItemTextColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'subMenuItemBackgroundH' => [
		'type' => 'string',
		'default' => '',
	],
	'subMenuItemTransition' => [
		'type' => 'number',
		'default' => 0,
	],
	'menuActveColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'menuActveColorBg' => [
		'type'    => 'string',
		'default' => '',
	],

];

$attributes = array_merge(
	$attributes,
	Dimensions::get_attribute( 'padding', false ),
	Dimensions::get_attribute( 'hamburgerPadding', false ),
	Dimensions::get_attribute( 'menuItemPadding', false ),
	Dimensions::get_attribute( 'menuItemMargin', false ),
	Border::get_attribute( 'menuItemBorder', true ),
	Border::get_attribute( 'hamburgerBorder', true ),
	Typography::get_attribute( 'menuItemTypography', true ),
	// sub menu style
	BoxShadow::get_attribute( 'subMenuBoxShadow', true ),
	Dimensions::get_attribute( 'subMenuPadding', false ),
	Border::get_attribute( 'subMenuBorder', true ),
	Range::get_attribute( [
		'attributeName' => 'hamburgerHeight',
		'isResponsive' => false,
		'defaultValue' => 3,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'hamburgerWidth',
		'isResponsive' => false,
		'defaultValue' => 30,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'subMenuWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 250,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

