<?php
use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Alignment;


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
	'post_id' => [
		'type'    => 'number',
	],
	'text' => [
		'type'    => 'string',
		'default' => '',
	],
	'textColor' => [
		'type'    => 'string',
		'default' => '#b300ef',
	],
	'buttonColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonColorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonBackground' => [
		'type'    => 'string',
		'default' => '',
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
	Alignment::get_attribute( 'buttonAlignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'textTypography', true ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Range::get_attribute([
		'attributeName' => 'bandageHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'textGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

