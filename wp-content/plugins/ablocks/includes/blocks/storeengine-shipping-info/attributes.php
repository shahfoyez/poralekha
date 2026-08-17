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
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'shipping_heading_hover_color' => [
		'type'    => 'string',
		'default' => '',
	],
	'shipping_heading_color' => [
		'type'    => 'string',
		'default' => '',
	],
	'shipping_address_hover_color' => [
		'type'    => 'string',
		'default' => '#000000',
	],
	'shipping_address_color' => [
		'type'    => 'string',
		'default' => '#000000',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'shipping_heading_typograhy', true ),
	Typography::get_attribute( 'shipping_address_typograhy', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

