<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type' => 'number',
		'default' => 2,
	],
	'text' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonColor' => [
		'type'    => 'string',
		'default' => '#ffffff',
	],
	'buttonColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonBackground' => [
		'type'    => 'string',
		'default' => '#ea580c',
	],
	'buttonBackgroundH' => [
		'type'    => 'string',
		'default' => '',
	],
	'badgeFillColor' => [
		'type'    => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Dimensions::get_attribute( 'padding', true ),
	Border::get_attribute( 'buttonBorder', true ),
	Alignment::get_attribute( 'buttonAlignment', true, [ 'value' => 'flex-start' ] ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Range::get_attribute([
		'attributeName' => 'badgeSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 30,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

