<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\Icon;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Responsive;
use ABlocks\Controls\Color;
use ABlocks\Controls\Link;
use ABlocks\Controls\TextShadow;


$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'text' => [
		'type' => 'string',
		'default' => 'Add Your Curvy Text Here',
	],
	'pathType' => [
		'type' => 'string',
		'default' => 'wave',
	],
	'isShowIcon' => [
		'type' => 'boolean',
		'default' => true,
	],
	'strokeColor' => [
		'type' => 'string',
		'default' => 'blue',
	],
	'textColor' => [
		'type' => 'string',
		'default' => 'black',
		'copyStyle' => true,
	],
	'textColorH' => [
		'type' => 'string',
		'default' => '',
		'copyStyle' => true,
	],
	'textStrokeShow' => [
		'type' => 'boolean',
		'default' => false,
	],
	'textStroke' => [
		'type' => 'number',
		'default' => 0,
	],
	'strokeTextColor' => [
		'type' => 'string',
		'default' => 'black',
		'copyStyle' => true,
	],
	'offsetControl' => [
		'type' => 'number',
		'default' => 0,
	],
	'transition' => [
		'type' => 'number',
		'default' => 1,
		'copyStyle' => true,
	],
	'strokeWidth' => [
		'type' => 'number',
		'default' => 1,
	],
	'rotate' => [
		'type' => 'number',
		'default' => 0,
		'copyStyle' => true,
	],
	'iconSize' => [
		'type' => 'number',
		'default' => 250,
	],
	'iconSvgPath' => [
		'type' => 'string',
		'default' => '',
	],
	'iconSvgViewBox' => [
		'type' => 'string',
		'default' => '',
	],
	'iconClass' => [
		'type' => 'string',
		'default' => ''
	],
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'typography', true ),
	TextShadow::get_attribute( 'textShadow' ),
	Link::get_attribute( 'link' ),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
