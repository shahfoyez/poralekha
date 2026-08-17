<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\BoxShadow;
$attributes = [
	'block_id' => [
		'type' => 'string',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'filterList' => [
		'type'    => 'array',
		'default' => [
			[
				'id' => 0,
				'text' => 'All',
				'isActive' => true
			],
			[
				'id' => 1,
				'text' => 'Item 01',
				'isActive' => false
			],
		],
	],
	'filterableCardsNumbers' => [
		'type' => 'number',
		'default' => '5'
	],
	'animation' => [
		'type'    => 'string',
		'default' => 'fade-in'
	],
	'enableFilter' => [
		'type' => 'boolean',
		'default' => true
	],
	'layout' => [
		'type' => 'string',
		'default' => 'filter'
	],
	'searchNotFoundText' => [
		'type' => 'string',
		'default' => 'No items Found'
	],
	'filterButtonColor' => [
		'type'    => 'string',
		'default' => ''
	],
	'filterButtonColorH' => [
		'type'    => 'string',
		'default' => ''
	],
	'filterButtonBackground' => [
		'type'    => 'string',
		'default' => ''
	],
	'filterButtonBackgroundH' => [
		'type'    => 'string',
		'default' => ''
	],
	'filterButtonTransition' => [
		'type'    => 'number',
		'default' => 0
	],
	'itemShows' => [
		'type' => 'number',
		'default' => 6,
	],
	'gridStyle' => [
		'type' => 'string',
		'default' => 'grid'
	],
	'searchMenuColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'searchMenuColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'searchMenuBackground' => [
		'type'    => 'string',
		'default' => '',
	],
	'searchMenuBackgroundH' => [
		'type'    => 'string',
		'default' => '',
	],
	'searchMenuTransition' => [
		'type' => 'string',
		'default' => ''
	],

	'searchPlaceHolder' => [
		'type' => 'string',
		'default' => 'Search...'
	],
	'gridColumns' => [
		'type' => 'number',
		'default' => 2,
	],
	'cardHeight' => [
		'type' => 'number'
	],
	'activeClassColor' => [
		'type' => 'string',
		'default' => '#fff'
	],
	'activeClassBackground' => [
		'type' => 'string',
		'default' => '#13191B'

	],
	// button style load more
	'moreButtonAlignment' => [
		'type' => 'string',
		'default' => 'center'
	],
	'loadMoreButton' => [
		'type' => 'boolean',
		'default' => false
	],
	'loadMoreButtonText' => [
		'type' => 'string',
		'default' => 'Show More',
	],
	'loadMoreButtonTextColor' => [
		'type' => 'string',
		'default' => '#fff',
		'copyStyle' => true,
	],
	'loadMoreButtonTextColorH' => [
		'type' => 'string',
		'default' => '#fff ',
		'copyStyle' => true,
	],
	'loadMoreButtonBackground' => [
		'type' => 'string',
		'default' => '#13191B',
		'copyStyle' => true,
	],
	'loadMoreButtonBackgroundH' => [
		'type' => 'string',
		'default' => '#13191B',
		'copyStyle' => true,
	],
	'loadMoreButtonTransition' => [
		'type' => 'number',
		'default' => '',
		'copyStyle' => true,
	],
	'dataPerPageShow' => [
		'type' => 'number',
		'default' => '6'
	],
	'noMoreItemsText' => [
		'type' => 'string',
		'default' => 'No More Items !'
	]


];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'filterButtonTypography', true ),
	Border::get_attribute( 'filterButtonBorder', true ),
	Border::get_attribute( 'activeClassBorder', true ),
	Dimensions::get_attribute( 'filterButtonPadding', false ),
	Dimensions::get_attribute( 'filterButtonMargin', false ),
	Border::get_attribute( 'searchMenuBorder', true ),
	Dimensions::get_attribute( 'searchMenuPadding', false ),
	Dimensions::get_attribute( 'searchMenuMargin', false ),
	Alignment::get_attribute( 'filterAlignment', true, [ 'value' => 'Center' ] ),
	Range::get_attribute([
		'attributeName' => 'filterButtonGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 8,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'loadMoreButtonGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => '',
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'itemGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'animationDuration',
		'isResponsive' => false,
		'defaultValue' => 700,
	] ),
	// button style
	Border::get_attribute( 'moreButtonBorder', true ),
	Dimensions::get_attribute( 'moreButtonPadding', true ),
	Typography::get_attribute( 'moreButtonTypography', true ),
	Alignment::get_attribute( 'position', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	BoxShadow::get_attribute( 'moreButtonboxShadow', true ),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
