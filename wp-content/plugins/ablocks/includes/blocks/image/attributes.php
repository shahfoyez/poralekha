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

$attributes = [
	'block_id'                   => [
		'type'                   => 'string',
		'default'                => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
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
	'imgCaption'                 => [
		'type'                   => 'boolean',
		'default'                => false,
	],
	'position'                   => [
		'type'                   => 'string',
		'default'                => 'below',
	],
	'caption'                    => [
		'type'                   => 'string',
		'default'                => '',
	],
	'captionBackground'          => [
		'type'                   => 'string',
		'default'                => '',
	],
	'captionColor'               => [
		'type'                   => 'string',
		'default'                => '',
	],
	'imgLink'                    => [
		'type'                   => 'object',
		'default'                => [
			'linkDestination'    => '',
			'href'               => '',
			'lightbox'           => '',
			'linkTarget'         => '',
			'rel'                => '',
			'linkClass'          => '',
		],
	],
	'imgAltText'                 => [
		'type'                   => 'string',
		'default'                => '',
	],
	'onHoverImg'                 => [
		'type'                   => 'string',
		'default'                => 'static',
	],
	'imgTitle'                   => [
		'type'                   => 'string',
		'default'                => '',
	],
];

$attributes = array_merge(
	$attributes,
	CssFilter::get_attribute( 'cssFilter' ),
	CssFilter::get_attribute( 'cssHoverFilter' ),
	BoxShadow::get_attribute( 'boxShadow' ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'captionAlignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'captionTypography', true ),
	Alignment::get_attribute( 'captionPosition', true ),
	Dimensions::get_attribute( 'captionPadding', true ),
	Border::get_attribute( 'captionBorder', true ),
	Border::get_attribute( 'border', true ),
	Dimensions::get_attribute( 'padding', true ),
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
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

