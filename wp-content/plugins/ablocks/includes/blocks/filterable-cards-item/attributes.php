<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
	],
	'dataCategory' => [
		'type' => 'string',
		'default' => ''
	]


];

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
