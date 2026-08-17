<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
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
	'tableWidth' => [
		'type'    => 'number',
		'default' => 100,
	],
	'tableColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableBackgroundH' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableBackground' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableLastBackground' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableLastBackgroundH' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableLastColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'tableLastColor' => [
		'type'    => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'firstTableTypography', true ),
	Typography::get_attribute( 'lastTableTypography', true ),
	Alignment::get_attribute( 'tableAlignment', true, [ 'value' => 'left' ] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

