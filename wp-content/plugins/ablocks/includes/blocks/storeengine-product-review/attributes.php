<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'product_id' => array(
		'type' => 'number',
		'default' => 0,
	),
	'heading_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'heading_hover_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'section_bg_hover' => array(
		'type' => 'string',
		'default' => '#FFF',
	),
	'section_bg' => array(
		'type' => 'string',
		'default' => '#FFF',
	),
	'avg_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'avg_color_hover' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'rating_color_hover' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'rating_color' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'total_rating_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'total_rating_hover' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'listColor' => array(
		'type' => 'string',
		'default' => '#999',
	),
	'listColorH' => array(
		'type' => 'string',
		'default' => '#999',
	),
	'starColorH' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'starColor' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'fillBg' => array(
		'type' => 'string',
		'default' => '#e7e7e7',
	),
	'fillBgH' => array(
		'type' => 'string',
		'default' => '#e7e7e7',
	),
	'fillABg' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'fillABgH' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'isCustom' => [
		'type' => 'boolean',
		'default' => false,
	],

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'heading_typography', true ),
	Typography::get_attribute( 'listTypography', true ),
	Typography::get_attribute( 'avg_typography', true ),
	Typography::get_attribute( 'total_typography', true ),
	Border::get_attribute( 'border', true ),
	Dimensions::get_attribute( 'padding', true ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Range::get_attribute([
		'attributeName' => 'rating_size',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 16,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'section_height',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute([
		'attributeName' => 'section_width',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute([
		'attributeName' => 'startSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 12,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

