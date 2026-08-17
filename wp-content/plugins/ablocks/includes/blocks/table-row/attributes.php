<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'rowColor' => [
		'type' => 'string',
		'default' => '',
	],
	'rowColorH' => [
		'type' => 'string',
		'default' => '',
	],
];

return $attributes;
