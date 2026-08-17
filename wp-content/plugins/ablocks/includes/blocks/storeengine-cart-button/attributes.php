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
	'quantity' => [
		'type'    => 'number',
	],
	'product_id' => [
		'type'    => 'number',
	],
	'price_display' => [
		'type'    => 'string',
		'default' => 'radio',
	],
	'label' => [
		'type' => 'string',
		'default' => 'Buy Now',
	],
	'direct_checkout' => [
		'type' => 'boolean',
		'default' => true,
	],
	'button_color' => [
		'type'    => 'string',
		'default' => '#fff',
	],
	'button_color_hover' => [
		'type'    => 'string',
		'default' => '#fff',
	],
	'button_bg_hover' => [
		'type'    => 'string',
		'default' => '#008DFF',
	],
	'button_bg' => [
		'type'    => 'string',
		'default' => '#008DFF',
	],
	'priceColor' => [
		'type'    => 'string',
		'default' => '#111',
	],
	'priceColorH' => [
		'type'    => 'string',
		'default' => '#111',
	],
	'priceNameColor' => [
		'type'    => 'string',
		'default' => '#101828',
	],
	'priceNameColorH' => [
		'type'    => 'string',
		'default' => '#101828',
	],
	'button_display' => [
		'type'    => 'string',
		'default' => 'yes',
	],
	'boxBackground' => [
		'type'    => 'string',
		'default' => '#fff',
	],
	'boxBackgroundH' => [
		'type'    => 'string',
		'default' => '#fff',
	],

];

$attributes = array_merge(
	$attributes,
	Dimensions::get_attribute( 'padding', true ),
	Typography::get_attribute( 'btn_typography', true ),
	Typography::get_attribute( 'priceTypography', true ),
	Typography::get_attribute( 'priceNameTypography', true ),
	Typography::get_attribute( 'priceTypography', true ),
	Border::get_attribute( 'buttonBorder', true ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Dimensions::get_attribute( 'boxPadding', true ),
	Border::get_attribute( 'boxBorder', true ),
	Alignment::get_attribute( 'buttonAlign', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'priceAlign', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'buttonTextAlign', true, [ 'value' => 'center' ] ),
	Range::get_attribute( [
		'attributeName' => 'button_width',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute( [
		'attributeName' => 'boxWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute( [
		'attributeName' => 'radioWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 15,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'radioHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 15,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'elementGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 12,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

