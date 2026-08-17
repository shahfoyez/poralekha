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
	'iconColorH' => [
		'type' => 'string',
		'default' => '#111',
	],
	'iconColor' => [
		'type' => 'string',
		'default' => '#111',
	],
	'countBg' => [
		'type' => 'string',
		'default' => '#008DFF',
	],
	'countBgH' => [
		'type' => 'string',
		'default' => '#008DFF',
	],
	'countColor' => [
		'type' => 'string',
		'default' => '#fff',
	],
	'countColorH' => [
		'type' => 'string',
		'default' => '#fff',
	],

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'countTypography', true ),
	Dimensions::get_attribute( 'padding', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

