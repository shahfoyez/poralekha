<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Range;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;
use ABlocks\Components\ButtonGroup;
$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'taxonomy' => [
		'type' => 'string',
		'default' => 'category'
	],
	'taxonomy_term_items' => array(
		'type' => 'array',
		'default' => [],
	),
	'filterBtnTextColor' => [
		'type' => 'string',
		'default' => '#000000',
		'copyStyle' => true,
	],
	'filterBtnBgColor' => [
		'type' => 'string',
		'default' => '#F4F4F5',
		'copyStyle' => true,
	],
	'activeBtnTextColor' => [
		'type' => 'string',
		'default' => '#FFFFFF',
		'copyStyle' => true,
	],
	'activeBtnBgColor' => [
		'type' => 'string',
		'default' => '#000000',
		'copyStyle' => true,
	],

];
$attributes = array_merge(
	$attributes,
	Range::get_attribute([
		'attributeName' => 'FilterBtnGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Border::get_attribute( 'activeBtnBorder', true ),
	Typography::get_attribute( 'typography', true ),
	Alignment::get_attribute( 'filterBtnAlignment', false, [ 'value' => 'center' ] ),
	Alignment::get_attribute( 'rowAlignBtn', false, [ 'value' => 'center' ] ),
	ButtonGroup::get_attribute( 'buttonAlignment', false, [
		'value' => 'row',
	] ),
	Dimensions::get_attribute( 'filterButtonPadding', false ),
	Dimensions::get_attribute( 'filterButtonMargin', false ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

