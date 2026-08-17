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
	'course_id' => array(
		'type' => 'number',
		'default' => 0,
	),
	'review_bg' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'review_bg_hover' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'review_btn' => array(
		'type'    => 'string',
		'default' => '#fff',
	),
	'review_btn_hover' => array(
		'type'    => 'string',
		'default' => '#fff',
	),
	'review_btn_bg' => array(
		'type'    => 'string',
		'default' => '#7b68ee',
	),
	'review_btn_bg_hover' => array(
		'type'    => 'string',
		'default' => '#7b68ee',
	),
	'starColor' => array(
		'type'    => 'string',
		'default' => '#f4c150',
	),
	'starColorH' => array(
		'type'    => 'string',
		'default' => '#f4c150',
	),
	'formColor' => array(
		'type'    => 'string',
		'default' => '#444',
	),
	'formColorH' => array(
		'type'    => 'string',
		'default' => '#444',
	),
	'formBg' => array(
		'type'    => 'string',
		'default' => '#E5E4E6',
	),
	'formBgH' => array(
		'type'    => 'string',
		'default' => '#E5E4E6',
	),
	'formBtnColor' => array(
		'type'    => 'string',
		'default' => '#fff',
	),
	'formBtnColorH' => array(
		'type'    => 'string',
		'default' => '#fff',
	),
	'formBtnBg' => array(
		'type'    => 'string',
		'default' => '#7b68ee',
	),
	'formBtnBgH' => array(
		'type'    => 'string',
		'default' => '#7b68ee',
	),

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'review_btn_typography', true ),
	Typography::get_attribute( 'formTypography', true ),
	Typography::get_attribute( 'review_btn_typography', true ),
	Typography::get_attribute( 'formBtnTypography', true ),
	Border::get_attribute( 'border', true ),
	Border::get_attribute( 'formBorder', true ),
	Dimensions::get_attribute( 'padding', true ),
	Dimensions::get_attribute( 'formBtnPadding', true ),
	Dimensions::get_attribute( 'button_padding', true ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Range::get_attribute([
		'attributeName' => 'box_width',
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
		'defaultValue' => 16,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'box_transition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'btn_transition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'startT',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'formTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'formBtnT',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

