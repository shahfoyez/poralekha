<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'hideTrigger' => array(
		'type' => 'boolean',
		'default' => false,
	),
];

return $attributes;
