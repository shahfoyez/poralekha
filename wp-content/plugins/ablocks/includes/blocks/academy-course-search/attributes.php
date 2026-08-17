<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'placeholder' => array(
		'type' => 'string',
		'default' => 'Search Course',
	),
	'search_box_color' => array(
		'type' => 'string',
		'default' => '#000000',
	),
	'search_placeholder_color' => array(
		'type' => 'string',
		'default' => '#444',
	),
	'search_icon_color' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'search_background_color' => array(
		'type' => 'string',
		'default' => '#fff',
	),

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'search_typography', true ),
	Border::get_attribute( 'search_border', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

