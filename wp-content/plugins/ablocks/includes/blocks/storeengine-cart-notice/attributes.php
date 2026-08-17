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
	'boxColor' => [
		'type' => 'string',
		'default' => '#515a62',
	],
	'boxColorH' => [
		'type' => 'string',
		'default' => '#515a62',
	],
	'linkColor' => [
		'type' => 'string',
		'default' => '#101828',
	],
	'linkColorH' => [
		'type' => 'string',
		'default' => '#101828',
	],
	'boxBackground' => [
		'type' => 'string',
		'default' => '#e1f2ff',
	],
	'boxBackgroundH' => [
		'type' => 'string',
		'default' => '#e1f2ff',
	],
	'isCustom' => [
		'type' => 'boolean',
		'default' => true,
	],
];

$attributes = array_merge(
	$attributes,
	Dimensions::get_attribute( 'infoBoxPadding', true ),
	Typography::get_attribute( 'infoTypography', true ),
	Typography::get_attribute( 'linkTypography', true ),
	BoxShadow::get_attribute( 'infoBoxoxShadow', true ),
	Border::get_attribute( 'infoBoxBorder', true ),
	Alignment::get_attribute( 'infoboxAlignment', true, [ 'value' => 'left' ] ),
	Range::get_attribute( [
		'attributeName' => 'infoBoxWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
