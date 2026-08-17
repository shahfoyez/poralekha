<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Alignment;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type' => 'number',
		'default' => 2,
	],
	'product_id' => [
		'type' => 'number',
		'default' => 0,
	],
	'isCustom' => [
		'type' => 'boolean',
		'default' => false,
	],
	'titleColor' => [
		'type' => 'string',
		'default' => '#111',
	],
	'titleColorH' => [
		'type' => 'string',
		'default' => '#111',
	],
	'productPriceColor' => [
		'type' => 'string',
		'default' => '#111',
	],
	'productPriceColorH' => [
		'type' => 'string',
		'default' => '#111',
	],
	'inputTextColor' => [
		'type' => 'string',
		'default' => '#111',
	],
	'inputTextColorH' => [
		'type' => 'string',
		'default' => '#111',
	],
	'buttonBackground' => [
		'type' => 'string',
		'default' => '#008DFF',
	],
	'buttonBackgroundH' => [
		'type' => 'string',
		'default' => '#008DFF',
	],
	'buttonColor' => [
		'type' => 'string',
		'default' => '#fff',
	],
	'buttonColorH' => [
		'type' => 'string',
		'default' => '#fff',
	],
	'AddButtonColor' => [
		'type' => 'string',
		'default' => '#111',
	],
	'AddButtonColorH' => [
		'type' => 'string',
		'default' => '#111',
	],
	'AddButtonBackground' => [
		'type' => 'string',
		'default' => '#fff',
	],
	'AddButtonBackgroundH' => [
		'type' => 'string',
		'default' => '#fff',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'ProductPriceTypography', true ),
	Typography::get_attribute( 'inputTextTypography', true ),
	Typography::get_attribute( 'btnTypography', true ),
	Typography::get_attribute( 'AddBtnTypography', true ),
	// BoxShadow::get_attribute( 'boxShadow', true ),
	Dimensions::get_attribute( 'inputPadding', true ),
	Border::get_attribute( 'inputBorder', true ),
	Dimensions::get_attribute( 'buttonPadding', true ),
	Border::get_attribute( 'buttonBorder', true ),
	Dimensions::get_attribute( 'AddButtonPadding', true ),
	Border::get_attribute( 'AddButtonBorder', true ),
	// Alignment::get_attribute( 'buttonAlign', true, [ 'value' => 'left' ] ),
	Range::get_attribute( [
		'attributeName' => 'inputWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 50,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'buttonWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute( [
		'attributeName' => 'AddButtonWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

