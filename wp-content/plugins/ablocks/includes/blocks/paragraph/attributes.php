<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Typography;

$attributes = [
	'block_id'          => [
		'type'          => 'string',
		'default'       => ''
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'paragraph'         => [
		'type'          => 'string',
		'source'        => 'html',
		'selector'      => '.ablocks-paragraph-text'
	],
	'dropCaps'          => [
		'type'          => 'boolean',
		'default'       => false,
	],
	'dropCapsTextColor' => [
		'type'          => 'string',
		'default'       => '#0f2aff',
	],
	'paragraphSize'     => [
		'type'          => 'string',
		'default'       => 'md',
	],
	'textColor'         => [
		'type'          => 'string',
		'default'       => '#000000',
	]
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'typography', true ),
	TextShadow::get_attribute( 'textShadow' ),
	TextStroke::get_attribute( 'textStroke', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

