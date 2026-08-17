<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Link;

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'heading' => array(
		'type' => 'string',
		'source' => 'html',
		'selector' => '.ablocks-block--certificate__heading-text',
		'default' => 'Add Your Heading Text Here',
	),
	'headingTag' => array(
		'type' => 'string',
		'default' => 'h2',
	),
	'textColor' => array(
		'type' => 'string',
		'default' => '',
	)
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'typography', true ),
	TextShadow::get_attribute( 'textShadow' ),
	Link::get_attribute( 'link' ),
	TextStroke::get_attribute( 'textStroke', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

