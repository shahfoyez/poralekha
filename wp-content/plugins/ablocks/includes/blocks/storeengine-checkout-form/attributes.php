<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type'    => 'number',
		'default' => 2,
	],
	'titleColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'titleColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'labelColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'labelColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'selectTextcolor' => [
		'type'    => 'string',
		'default' => '',
	],
	'selectTextcolorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'selectBackground' => [
		'type'    => 'string',
		'default' => '',
	],
	'selectBackgroundH' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonBackground' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonBackgroundH' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonColorH' => [
		'type'    => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'labelTypography', true ),
	Typography::get_attribute( 'buttonTypography', true ),
	Dimensions::get_attribute( 'padding', true ),
	Border::get_attribute( 'inputBorder', true ),
	Typography::get_attribute( 'selectTypography', true ),
	Dimensions::get_attribute( 'selectPadding', true ),
	Border::get_attribute( 'selectBorder', true ),
	Range::get_attribute( [
		'attributeName' => 'selectWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 200,
		'defaultValueMobile' => 80,
		'defaultValueTablet' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
