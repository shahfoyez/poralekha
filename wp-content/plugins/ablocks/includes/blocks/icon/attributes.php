<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;
use ABlocks\Helper;
use ABlocks\Controls\Range;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Link;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
];

$attributes = array_merge(
	$attributes,
	Icon::get_attribute(),
	Link::get_attribute( 'link' ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'flex-start' ] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

