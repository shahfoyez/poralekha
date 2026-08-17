<?php

use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\GroupButton;
use ABlocks\Controls\Range;
use ABlocks\Controls\Typography;
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
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'device' => [
		'type' => 'string',
		'default'  => '',
	],
	'columnGap' => [
		'type' => 'object',
		'default' => [
			'value' => 20,
			'valueTablet' => 20,
			'valueMobile' => 10,
		],
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
	'itemBackgroundH' => [
		'type' => 'string',
		'default' => '',
	],
	'itemBackground' => [
		'type' => 'string',
		'default' => '',
	],
	'transition' => [
		'type' => 'number',
		'default' => '',
	],
	'titleTag' => [
		'type' => 'string',
		'default' => 'p',
	],
	'titleColor' => [
		'type' => 'string',
		'default' => '#13191B',
	],
	'descriptionTag' => [
		'type' => 'string',
		'default' => 'p',
	],
	'descriptionColor' => [
		'type' => 'string',
		'default' => '#595959',
	],
	// divider related attributes
	'placeDivider' => [
		'type' => 'string',
		'default' => 'near title',
	],
	'dividerPatternUrl' => [
		'type' => 'string',
		'default' => 'dashed',
	],
	'dividerType' => [
		'type' => 'string',
		'default' => 'dashed',
	],
	'color' => [
		'type' => 'string',
		'default' => '#000000',
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
		'default' => '#595959',
	],
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute('titleTypography', true, [
		'weight' => '600',
	]),
	TextShadow::get_attribute( 'titleTextShadow' ),
	TextStroke::get_attribute( 'titleTextStroke', true ),
	Typography::get_attribute( 'descriptionTypography', true ),
	TextShadow::get_attribute( 'descriptionTextShadow' ),
	TextStroke::get_attribute( 'descriptionTextStroke', true ),
	Typography::get_attribute( 'priceTypography', true ),
	TextShadow::get_attribute( 'priceTextShadow' ),
	TextStroke::get_attribute( 'priceTextStroke', true ),
	Icon::get_attribute( 'icon' ),
	Typography::get_attribute( 'elementTextTypography', true ),
	TextStroke::get_attribute( 'elementTextStroke', true ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'flex-start' ] ),
	Alignment::get_attribute( 'titleAlignment', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'descriptionAlignment', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'priceAlignment', true, [ 'value' => 'left' ] ),
	GroupButton::get_attribute( 'itemsDirection', true, [
		'value' => 'row'
	] ),
	Border::get_attribute( 'itemBorder', true ),
	Dimensions::get_attribute( 'itemPadding', true ),
	Range::get_attribute( [
		'attributeName' => 'columnGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'defaultValueTablet' => 20,
		'defaultValueMobile' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'width',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'defaultValueMobile' => 100,
		'defaultValueTablet' => 100,
		'hasUnit' => false,
	] ),
	Range::get_attribute( [
		'attributeName' => 'weight',
		'isResponsive' => false,
		'defaultValue' => 2,
	] ),
	Range::get_attribute( [
		'attributeName' => 'size',
		'isResponsive' => false,
		'defaultValue' => 20,
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

