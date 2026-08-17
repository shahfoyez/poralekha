<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\CssFilter;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;
use ABlocks\Controls\LinkControl;
use ABlocks\Controls\Link;

$attributes = [
	'block_id'                   => [
		'type'                   => 'string',
		'default'                => '',
	],
	'imgId'                      => [
		'type'                  => 'string',
		'default'                => '',
	],
	'imgIdTablet'                => [
		'type' => 'string',
		'default'                => '',
	],
	'imgIdMobile'                => [
		'type' => 'string',
		'default'                => '',
	],
	'imgUrl'                     => [
		'type'                   => 'string',
		'source'                 => 'attribute',
		'selector'               => '.ablocks-image',
		'attribute'              => 'srcset',
	],
	'imgUrlTablet'               => [
		'type'                   => 'string',
		'source'                 => 'attribute',
		'selector'               => '.ablocks-image-tablet',
		'attribute'              => 'srcset',
	],
	'imgUrlMobile'               => [
		'type'                   => 'string',
		'source'                 => 'attribute',
		'selector'               => '.ablocks-image-mobile',
		'attribute'              => 'src',
	],
	'imgSize'                    => [
		'type'                   => 'object',
		'default'                => [
			'value'            => 'large',
			'valueTablet'      => '',
			'valueMobile'      => '',
		],
	],
	'aspectRatio'                => [
		'type'                   => 'object',
		'default'                => [
			'value'          => 'original',
			'valueTablet'    => '',
			'valueMobile'    => '',
		],
	],
	'imageScrollOption' => [
		'type' => 'object',
		'default' => [
			'value' => 'mouse-scroll'
		],
	],
	'imageDataAttribute' => [
		'type' => 'object'
	],
	'widthHeightWidget'          => [
		'type'                   => 'object',
		'default'                => [
			'imgNaturalWidth'    => '',
			'imgNaturalWidthTablet'  => '',
			'imgNaturalWidthMobile'  => '',
			'imgNaturalHeight'   => '',
			'imgNaturalHeightTablet' => '',
			'imgNaturalHeightMobile' => '',
			'width'              => '',
			'widthTablet'        => '',
			'widthMobile'        => '',
			'height'             => '',
			'heightTablet'       => '',
			'heightMobile'       => '',
			'customHeight'       => false,
			'customHeightTablet' => false,
			'customHeightMobile' => false,
		],
	],
	'objectFit'                  => [
		'type'                   => 'object',
		'default'                => [
			'value'          => 'default',
			'valueTablet'    => '',
			'valueMobile'    => '',
		],
	],
	'position'                   => [
		'type'                   => 'string',
		'default'                => 'below',
	],
	'imgAltText'                 => [
		'type'                   => 'string',
		'default'                => '',
	],
	'imgTitle'                   => [
		'type'                   => 'string',
		'default'                => '',
	],
	'showNotice' => [
		'type' => 'boolean',
		'default' => false
	],
	'transitionTime' => [
		'type' => 'number',
		'default' => 3
	],
	'showOverlay' => [
		'type' => 'boolean',
		'default' => false
	],
	'showIcon' => [
		'type' => 'boolean',
		'default' => false
	],
	'overlayColor' => [
		'type' => 'string',
		'default' => '#0202024C'
	],
	'iconColor' => [
		'type' => 'string',
		'default' => ''
	],
];

$attributes = array_merge(
	$attributes,
	CssFilter::get_attribute( 'cssFilter' ),
	CssFilter::get_attribute( 'cssHoverFilter' ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Border::get_attribute( 'border', true ),
	Dimensions::get_attribute( 'padding', true ),
	Link::get_attribute( 'link' ),
	Range::get_attribute([
		'attributeName' => 'opacity',
		'defaultValue' => 1
	]),
	Range::get_attribute([
		'attributeName' => 'opacityH',
		'defaultValue' => '',
	]),
	Range::get_attribute([
		'attributeName' => 'transitionDuration',
		'defaultValue' => 0.5,
	]),
	Range::get_attribute([
		'attributeName' => 'filterTransitionDuration',
		'defaultValue' => 0.5,
	]),
	Range::get_attribute( [
		'attributeName' => 'iconFontSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 36,
		'defaultValueTablet' => 24,
		'defaultValueMobile' => 16,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'scrollHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 200,
	] ),
	Range::get_attribute( [
		'attributeName' => 'scrollWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

