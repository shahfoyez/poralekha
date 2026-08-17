<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Range;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => ''
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'couponStyle' => [
		'type' => 'string',
		'default' => 'default'
	],
	'couponCode' => [
		'type' => 'string',
		'default' => 'KODEZEN50'
	],
	'couponBtnText' => [
		'type' => 'string',
		'default' => 'Copy Code'
	],
	'couponBtnAfterCopyText' => [
		'type' => 'string',
		'default' => 'Copied!'
	],

	'couponCodeColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
	'couponCodeBgColor' => [
		'type' => 'string',
		'default' => '#ffffff',
	],
	'couponBtnTextColor' => [
		'type' => 'string',
		'default' => '#ffffff',
	],
	'couponBtnBgColor' => [
		'type' => 'string',
		'default' => '#000000',
	],

	'isShowIcon' => [
		'type' => 'boolean',
		'default' => true,
	],
];


$attributes = array_merge(
	Range::get_attribute( [
		'attributeName' => 'iconSize',
		'isResponsive' => true,
		'attributeObjectKey' => 'value',
		'defaultValue' => 20,
	]),
	Range::get_attribute( [
		'attributeName' => 'iconRotate',
		'isResponsive' => false,
		'defaultValue' => 0,
	]),
	Icon::get_attribute( 'icon', [ 'size' => '20' ] ),
	Alignment::get_attribute( 'position', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'couponTypography', true ),
	TextShadow::get_attribute( 'couponTextShadow' ),
	Typography::get_attribute( 'buttonTypography', true ),
	TextShadow::get_attribute( 'buttonTextShadow' ),
	Dimensions::get_attribute( 'couponPadding', true ),
	Dimensions::get_attribute( 'buttonPadding', true ),
	Border::get_attribute( 'couponBorder', true ),
	Border::get_attribute( 'buttonBorder', true ),
	$attributes
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

