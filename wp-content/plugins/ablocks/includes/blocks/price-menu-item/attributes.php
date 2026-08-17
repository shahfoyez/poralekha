<?php

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Typography;
use ABlocks\Controls\GroupButton;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Icon;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default'  => '',
	],
	'device' => [
		'type' => 'string',
		'default'  => '',
	],
	'allowIcon' => [
		'type' => 'boolean',
		'default' => true,
	],
	'allowDescription' => [
		'type' => 'boolean',
		'default' => true,
	],
	'allowDivider' => [
		'type' => 'boolean',
		'default' => true,
	],
	'title' => [
		'type' => 'string',
		'default' => 'Sushi Roll...',
	],
	'titleTag' => [
		'type' => 'string',
		'default' => 'p',
	],
	'titleColor' => [
		'type' => 'string',
		'default' => '',
	],
	'description' => [
		'type' => 'string',
		'source' => 'html',
		'selector' => '.ablocks-price-menu-item-details-des',
		'default' => 'Lorem ipsum dolor sit amet consectetur adipiscing, elit praesent. Sed do eiusmod tempor incididunt aliqua.',
	],
	'descriptionTag' => [
		'type' => 'string',
		'default' => 'p',
	],
	'descriptionColor' => [
		'type' => 'string',
		'default' => '',
	],
	// divider related attributes
	'placeDivider' => [
		'type' => 'string',
		'default' => 'near title',
	],
	'dividerPatternUrl' => [
		'type' => 'string',
		'default' => '',
	],
	'dividerType' => [
		'type' => 'string',
		'default' => '',
	],
	'color' => [
		'type' => 'string',
		'default' => '',
	],
	'price' => [
		'type' => 'string',
		'source' => 'html',
		'selector' => '.ablocks-price-menu-item-price',
		'default' => '$15.00',
	],
	'placePrice' => [
		'type' => 'string',
		'default' => 'right',
	],
	'priceTag' => [
		'type' => 'string',
		'default' => 'p',
	],
	'priceColor' => [
		'type' => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'titleTypography', true ),
	TextShadow::get_attribute( 'titleTextShadow' ),
	TextStroke::get_attribute( 'titleTextStroke', true ),
	Typography::get_attribute( 'descriptionTypography', true ),
	TextShadow::get_attribute( 'descriptionTextShadow' ),
	TextStroke::get_attribute( 'descriptionTextStroke', true ),
	Typography::get_attribute( 'priceTypography', true ),
	TextShadow::get_attribute( 'priceTextShadow' ),
	TextStroke::get_attribute( 'priceTextStroke', true ),
	Icon::get_attribute( 'icon', [
		'size' => '40',
		'iconType' => 'stacked',
		'iconShape' => 'square',
	] ),
	Typography::get_attribute( 'elementTextTypography', true ),
	TextStroke::get_attribute( 'elementTextStroke', true ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => '' ] ),
	Alignment::get_attribute( 'titleAlignment', true, [ 'value' => '' ] ),
	Alignment::get_attribute( 'descriptionAlignment', true, [ 'value' => '' ] ),
	Alignment::get_attribute( 'priceAlignment', true, [ 'value' => '' ] ),
	GroupButton::get_attribute( 'itemsDirection', true, [
		'value' => 'row',
	] ),
	Range::get_attribute( [
		'attributeName' => 'width',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => null,
		'defaultValueMobile' => null,
		'defaultValueTablet' => null,
		'hasUnit' => false,
	] ),
	Range::get_attribute( [
		'attributeName' => 'weight',
		'isResponsive' => false,
		'defaultValue' => null,
	] ),
	Range::get_attribute( [
		'attributeName' => 'size',
		'isResponsive' => false,
		'defaultValue' => null,
	] ),
	Range::get_attribute( [
		'attributeName' => 'gap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'defaultValueMobile' => 3,
		'defaultValueTablet' => 1,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

