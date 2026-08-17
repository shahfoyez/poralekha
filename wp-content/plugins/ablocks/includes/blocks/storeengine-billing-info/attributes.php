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
	'heading_color' => [
		'type'    => 'string',
		'default' => '',
	],
	'heading_hover_color' => [
		'type'    => 'string',
		'default' => '',
	],
	'address_hover_color' => [
		'type'    => 'string',
		'default' => '',
	],
	'address_color' => [
		'type'    => 'string',
		'default' => '',
	],

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'heading_typograhy', true ),
	Typography::get_attribute( 'address_typograhy', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

