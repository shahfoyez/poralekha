<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;

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
	'tableBackground' => [
		'type' => 'string',
		'default' => ''
	],
	'tableTransition' => [
		'type' => 'number',
		'default' => 0,
	],
	'tableBackgroundH' => [
		'type' => 'string',
		'default' => ''
	],
	'tableHeaderBackground' => [
		'type' => 'string',
		'default' => ''
	],
	'tableHeaderBackgroundH' => [
		'type' => 'string',
		'default' => ''
	],
	'thumbImage' => [
		'type' => 'number',
		'default' => 120
	],
	'productTitleColor' => [
		'type' => 'string',
		'default' => ''
	],
	'productTitleColorH' => [
		'type' => 'string',
		'default' => ''
	],
	'productSubTiteColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'productSubTiteColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'productPriceColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'productPriceColorH' => [
		'type'    => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'tableHeaderTypography', true ),
	Typography::get_attribute( 'productTilteTypography', true ),
	Typography::get_attribute( 'productsubTitleTypography', true ),
	Typography::get_attribute( 'productPriceTypography', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

