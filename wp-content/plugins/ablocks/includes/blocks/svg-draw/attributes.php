<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;
use ABlocks\Helper;
use ABlocks\Controls\Range;
use ABlocks\Controls\Link;
use ABlocks\Controls\Icon;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'svgDrawColor' => [
		'type' => 'string',
		'default' => 'gray',
	],
	'duration' => [
		'type' => 'number',
		'default' => 5,
	],
];

$attributes = array_merge(
	$attributes,
	Icon::get_attribute( 'icon' ),
	Link::get_attribute( 'link' ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'flex-start' ] ),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

