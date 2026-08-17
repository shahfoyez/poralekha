<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id'          => [
		'type'          => 'string',
		'default'       => ''
	],
];

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
