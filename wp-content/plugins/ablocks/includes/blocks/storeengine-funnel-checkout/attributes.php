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
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'showSummary' => [
		'type'    => 'boolean',
		'default' => true,
	],
	'titleColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'titleColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'productTitleColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'productTitleColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'discountPriceColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'discountPriceColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'regularPriceColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'regularPriceColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'qualityColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'qualityColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableTextColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableTextColor' => [
		'type'    => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'ProductTitleTypography', true ),
	Typography::get_attribute( 'discountPriceTypography', true ),
	Typography::get_attribute( 'regularPriceTypography', true ),
	Typography::get_attribute( 'tableTextTypography', true ),
	Typography::get_attribute( 'qualityTypography', true ),
	Range::get_attribute([
		'attributeName' => 'imageWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'defaultValueTablet' => 80,
		'defaultValueMobile' => 40,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'imageHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'defaultValueTablet' => 80,
		'defaultValueMobile' => 40,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
