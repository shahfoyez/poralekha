<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Link;
use ABlocks\Controls\Range;

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'isAnimated' => array(
		'type' => 'boolean',
		'default' => false,
	),
	'heading' => array(
		'type' => 'string',
		'source' => 'html',
		'selector' => '.ablocks-heading-text'
	),
	'headingTag' => array(
		'type' => 'string',
		'default' => 'h2',
	),
	'textColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'animationType' => array(
		'type' => 'string',
		'default' => 'highlighted',
	),
	'animationStyle' => array(
		'type' => 'string',
		'default' => 'highlighter-circle',
	),
	'startingText' => array(
		'type' => 'string',
		'default' => '',
	),
	'animatedText' => array(
		'type' => 'string',
		'default' => 'Animated',
	),
	'endingText' => array(
		'type' => 'string',
		'default' => '',
	),

	'highlightColor' => array(
		'type' => 'string',
		'default' => '#ff000e',
	),
	'highlightDuration' => array(
		'type' => 'number',
		'default' => 2000,
	),
	'isInfiniteLoop' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'highlightDelay' => array(
		'type' => 'number',
		'default' => 1000,
	),
	'animatedTextColor' => array(
		'type' => 'string',
		'default' => '',
	),
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'typography', true ),
	TextShadow::get_attribute( 'textShadow' ),
	Link::get_attribute( 'link' ),
	TextStroke::get_attribute( 'textStroke', true ),
	Range::get_attribute([
		'attributeName' => 'highlightStrokeWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

