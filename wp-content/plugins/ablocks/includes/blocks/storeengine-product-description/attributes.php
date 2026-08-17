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
	'titleColor' => [
		'type' => 'string',
		'default' => '#111',
	],
	'titleColorH' => [
		'type' => 'string',
		'default' => '#111',
	],
	'descriptionColor' => [
		'type' => 'string',
		'default' => '#111',
	],
	'descriptionColorH' => [
		'type' => 'string',
		'default' => '#111',
	],
	'isCustom' => [
		'type' => 'boolean',
		'default' => false,
	],

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'descriptionTypography', true ),
	Border::get_attribute( 'border', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

