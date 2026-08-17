<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Width;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Components\ButtonGroup;
use ABlocks\Controls\BoxShadow;

$attributes = [
	'block_id'               => [
		'type'               => 'string',
		'default'            => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'buttonType'               => [
		'type'               => 'string',
		'default'            => 'Facebook',
	],
	'viewButton'               => [
		'type'               => 'string',
		'default'            => 'Icon & Text',
	],
	'shape'               => [
		'type'               => 'string',
		'default'            => 'Square',
	],
	'lists'          => [
		'type'    => 'array',
		'default' => [
			[
				'id'                     => 0,
				'text'                   => 'Facebook',
				'icon' => [
					'viewBox' => '0 0 320 512',
					'path' => 'M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z'
				],
				'buttonBackgroundColor'  => 'ablocks-social-share-item--facebook-bg',
				'iconBackgroundColor'    => 'ablocks-social-share-item--facebook-icon-bg',
				'link'                    => 'https://www.facebook.com/sharer.php?u=',
				'isOpen'                  => false,
				'backgroundH'            => '#2d4373',
				'buttonType'              => 'Facebook'
			],
			[
				'id'                     => 1,
				'text'                   => 'Twitter',
				'icon' => [
					'viewBox' => '0 0 512 512',
					'path' => 'M459.37 151.716c.325 4.548.325 9.097.325 13.645 0 138.72-105.583 298.558-298.558 298.558-59.452 0-114.68-17.219-161.137-47.106 8.447.974 16.568 1.299 25.34 1.299 49.055 0 94.213-16.568 130.274-44.832-46.132-.975-84.792-31.188-98.112-72.772 6.498.974 12.995 1.624 19.818 1.624 9.421 0 18.843-1.3 27.614-3.573-48.081-9.747-84.143-51.98-84.143-102.985v-1.299c13.969 7.797 30.214 12.67 47.431 13.319-28.264-18.843-46.781-51.005-46.781-87.391 0-19.492 5.197-37.36 14.294-52.954 51.655 63.675 129.3 105.258 216.365 109.807-1.624-7.797-2.599-15.918-2.599-24.04 0-57.828 46.782-104.934 104.934-104.934 30.213 0 57.502 12.67 76.67 33.137 23.715-4.548 46.456-13.32 66.599-25.34-7.798 24.366-24.366 44.833-46.132 57.827 21.117-2.273 41.584-8.122 60.426-16.243-14.292 20.791-32.161 39.308-52.628 54.253z'
				],
				'buttonBackgroundColor'  => 'ablocks-social-share-item--twitter-bg',
				'iconBackgroundColor'    => 'ablocks-social-share-item--twitter-icon-bg',
				'link'                    => 'https://twitter.com/intent/tweet?url=',
				'isOpen'                  => false,
				'backgroundH'            => '#1da1f2',
				'buttonType'              => 'Twitter'
			],
			[
				'id'                     => 2,
				'text'                   => 'Telegram',
				'icon' => [
					'viewBox' => '0 0 448 512',
					'path' => 'M446.7 98.6l-67.6 318.8c-5.1 22.5-18.4 28.1-37.3 17.5l-103-75.9-49.7 47.8c-5.5 5.5-10.1 10.1-20.7 10.1l7.4-104.9 190.9-172.5c8.3-7.4-1.8-11.5-12.9-4.1L117.8 284 16.2 252.2c-22.1-6.9-22.5-22.1 4.6-32.7L418.2 66.4c18.4-6.9 34.5 4.1 28.5 32.2z'
				],
				'buttonBackgroundColor'  => 'ablocks-social-share-item--telegram-bg',
				'iconBackgroundColor'    => 'ablocks-social-share-item--telegram-icon-bg',
				'link'                    => 'https://t.me/share/url?url=',
				'isOpen'                  => false,
				'backgroundH'            => '#007ab8',
				'buttonType'              => 'Telegram'
			]
		]
	],

	'listIconsClasses'          => [
		'type'           => 'array',
		'default'        => [],
	],
	'iconType'          => [
		'type'           => 'string',
		'default'        => 'default',
	],
	'iconShape'          => [
		'type'           => 'string',
		'default'        => 'circle',
	],
	'windowsPopUp' => [
		'type' => 'boolean',
		'default' => false,

	],
	'buttonBackground' => [
		'type' => 'string',
		'default' => '',

	],
	'buttonHover' => [
		'type' => 'string',
		'default' => '',

	],
	// Share Button
	'shareBar' => [
		'type' => 'boolean',
		'default' => true,
	],
	'shareBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'iconFillColor' => [
		'type' => 'string',
		'default' => '#ffffff',
	],
	'shareTextBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'shareIconH' => [
		'type' => 'string',
		'default' => '',
	],
	'shareTextH' => [
		'type' => 'string',
		'default' => '',
	],
	'backgroundColor' => [
		'type' => 'string',
		'default' => '',
	],
	'backgroundColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'shareButtonIconColor' => [
		'type' => 'string',
		'default' => '#170a1b',
	],
	'shareButtonIconColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'belowItem'             => [
		'type'              => 'number',
		'default'           => 0,
	],
];
$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'position', true, [ 'value' => 'center' ] ),
	TextStroke::get_attribute( 'textStroke', true ),
	Typography::get_attribute( 'typography', true ),
	Alignment::get_attribute( 'horizontalAlignment', true, [ 'value' => 'flex-start' ] ),
	Width::get_attribute( 'width', false ),
	Dimensions::get_attribute( 'radius', false ),
	Border::get_attribute( 'border', true ),
	Border::get_attribute( 'itemBorder', true ),
	BoxShadow::get_attribute( 'shareItemShadow', true ),
	ButtonGroup::get_attribute( 'stack', false, [
		'value' => 'horizontal',
	] ),
	ButtonGroup::get_attribute( 'verticalAlignment', false, [
		'value' => 'flex-start',
	] ),
	Range::get_attribute( [
		'attributeName' => 'spaceBetween',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 16,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'shareSize',
		'isResponsive' => false,
		'defaultValue' => 48,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'shareIconSize',
		'isResponsive' => false,
		'defaultValue' => 16,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'shareItemIconSize',
		'isResponsive' => false,
		'defaultValue' => 25,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'itemIconWidth',
		'isResponsive' => false,
		'defaultValue' => 43,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'itemIconHeight',
		'isResponsive' => false,
		'defaultValue' => 42,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'itemTextWidth',
		'isResponsive' => false,
		'defaultValue' => 80,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'itemTextHeight',
		'isResponsive' => false,
		'defaultValue' => 42,
		'attributeObjectKey' => 'value',
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

