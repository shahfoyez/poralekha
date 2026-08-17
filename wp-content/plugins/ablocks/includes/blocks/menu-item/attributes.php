<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Link;
$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'label' => [
		'type' => 'string',
		'default' => 'Menu',
	],
	'isSubMenu' => [
		'type' => 'boolean',
		'default' => false,
	],
	'hasMegaMenu' => [
		'type' => 'boolean',
		'default' => false
	],
	'hasLink' => [
		'type' => 'boolean',
		'default' => false,
	],
	'link' => [
		'type' => 'string',
		'default' => '#',
	],
];

$attributes = array_merge(
	$attributes,
	// Adding attributes from various controls
	Link::get_attribute( 'link' ),
);

return $attributes;
