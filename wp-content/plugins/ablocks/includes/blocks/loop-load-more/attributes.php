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
		'default' => '',
	],
	'taxonomy' => [
		'type' => 'string',
		'default' => 'category'
	],
	'moreButtonAlignment' => [
		'type' => 'string',
		'default' => 'center'
	],
	'loadMoreButtonText' => [
		'type' => 'string',
		'default' => 'Load More',
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
	'noMoreItemsText' => [
		'type' => 'string',
		'default' => 'No More Items !'
	]
];
$attributes = array_merge(
	Range::get_attribute([
		'attributeName' => 'loadMoreButtonGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => '',
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	// button style
	Border::get_attribute( 'moreButtonBorder', true ),
	Dimensions::get_attribute( 'moreButtonPadding', true ),
	Typography::get_attribute( 'moreButtonTypography', true ),
	Alignment::get_attribute( 'position', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	BoxShadow::get_attribute( 'moreButtonboxShadow', true ),
	$attributes
);


return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

