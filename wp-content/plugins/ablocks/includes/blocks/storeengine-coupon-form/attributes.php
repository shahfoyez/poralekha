<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\GroupButton;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type'    => 'number',
		'default' => 2,
	],
	// 'inputWidth' => [
	// 'type'    => 'number',
	// 'default' => 300,
	// ],
	'input_placeholder' => [
		'type'    => 'string',
		'default' => '',
	],
	'buttonColor' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonBackground' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonBackgroundH' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonTitle' => [
		'type'    => 'string',
		'default' => '',
	],

];

$attributes = array_merge(
	$attributes,
	Dimensions::get_attribute( 'padding', true ),
	Typography::get_attribute( 'buttonTypography', true ),
	Border::get_attribute( 'inputBorder', true ),
	Border::get_attribute( 'buttonBorder', true ),
	Border::get_attribute( 'inputBorderH', true ),
	Alignment::get_attribute( 'formAlignment', true, [ 'value' => 'left' ] ),
	GroupButton::get_attribute( 'direction', false, [
		'value' => 'column'
	] ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Range::get_attribute( [
		'attributeName' => 'inputWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 200,
		'defaultValueMobile' => 80,
		'defaultValueTablet' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'buttonWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 200,
		'defaultValueMobile' => 80,
		'defaultValueTablet' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

