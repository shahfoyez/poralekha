<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'noCloseButton' => array(
		'type' => 'boolean',
		'default' => false,
	),
];

return $attributes;
