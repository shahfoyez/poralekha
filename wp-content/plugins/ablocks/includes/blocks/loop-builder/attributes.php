<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => ''
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'queryId' => [
		'type' => 'number'
	],
	'query' => [
		'type' => 'object',
		'default' => [
			'perPage' => null,
			'pages' => 0,
			'offset' => 0,
			'postType' => 'post',
			'order' => 'desc',
			'orderBy' => 'date',
			'author' => '',
			'search' => '',
			'exclude' => [],
			'sticky' => '',
			'inherit' => false,
			'taxQuery' => null,
			'parents' => [],
			'format' => []
		]
	],
	'selectedVariation' => [
		'type' => 'string',
		'default' => 'div'
	],
	'tagName' => [
		'type' => 'string',
		'default' => 'div'
	],
	'showOffset' => [
		'type' => 'boolean',
		'default' => false,
	],
];

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

