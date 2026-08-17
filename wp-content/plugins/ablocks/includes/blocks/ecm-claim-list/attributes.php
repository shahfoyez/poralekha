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
	'user_id' => [
		'type'    => 'number',
	],
	'status' => [
		'type'    => 'string',
		'default' => '',
	],
	'not_found_text' => [
		'type'    => 'string',
		'default' => '',
	],
	'listStyle' => [
		'type'    => 'string',
		'default' => 'disc',
	],
	'listBackground' => [
		'type'    => 'string',
		'default' => '',
	],
	'titleColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'desColor' => [
		'type'    => 'string',
		'default' => '',
	],
	'notFoundColor' => [
		'type'    => 'string',
		'default' => '',
	],

];

$attributes = array_merge(
	$attributes,
	Range::get_attribute([
	'attributeName' => 'listWidth',
	'attributeObjectKey' => 'value',
	'isResponsive' => true,
	'defaultValue' => 33,
	'hasUnit' => false,
	'unitDefaultValue' => '%',
	]),
	Alignment::get_attribute( 'listAlignment', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'notFoundAlignment', true, [ 'value' => 'center' ] ),
	Dimensions::get_attribute( 'listPadding', true ),
	Border::get_attribute( 'listBorder', true ),
	BoxShadow::get_attribute( 'listBoxShadow', true ),
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'notFoundTypography', true ),
	TextShadow::get_attribute( 'titleTextShadow' ),
	TextStroke::get_attribute( 'titleTextStroke', true ),
	Typography::get_attribute( 'desTypography', true ),
	TextShadow::get_attribute( 'desTextShadow' ),
	TextStroke::get_attribute( 'desTextStroke', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

