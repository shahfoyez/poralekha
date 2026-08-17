<?php

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Link;
use ABlocks\Helper;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Components\ButtonGroup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'blockElements' => [
		'type' => 'array',
		'default' => [
			[
				'id' => 0,
				'slug' => 'Heading',
			],
			[
				'id' => 1,
				'slug' => 'Sub-Heading',
			],
			[
				'id' => 2,
				'slug' => 'Description',
			],
			[
				'id' => 3,
				'slug' => 'Rating',
			],
			[
				'id' => 4,
				'slug' => 'Button',
			],
		]
	],
	'des' => [
		'type' => 'string',
		'source' => 'html',
		'selector' => '.ablocks-info-box-text',
		'default' => 'Showcase details with style and precision! Customize captivating visuals and text for an engaging, attention-grabbing display!',
	],
	'allowBadgeHover' => [
		'type' => 'boolean',
		'default' => false,
	],
	'allowButtonHover' => [
		'type' => 'boolean',
		'default' => false,
	],
	'allowBadge' => [
		'type' => 'boolean',
		'default' => false,
	],
	'allowIcon' => [
		'type' => 'boolean',
		'default' => true,
	],
	'allowHeading' => [
		'type' => 'boolean',
		'default' => true,
	],
	'allowSubHeading' => [
		'type' => 'boolean',
		'default' => false,
	],
	'allowDes' => [
		'type' => 'boolean',
		'default' => true,
	],
	'allowRating' => [
		'type' => 'boolean',
		'default' => false,
	],
	'allowButton' => [
		'type' => 'boolean',
		'default' => true,
	],
	// badge starts
	'badgeText' => [
		'type' => 'string',
		'default' => 'Featured',
	],
	'badgeSize' => [
		'type' => 'string',
		'default' => 'xs',
	],
	'badgeTextColor' => [
		'type' => 'string',
		'default' => '#13191B',
	],
	'badgeTextColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'badgeBackground' => [
		'type' => 'string',
		'default' => '#DDDDDF',
	],
	'badgeBackgroundH' => [
		'type' => 'string',
		'default' => '',
	],
	'badgeTransition' => [
		'type' => 'number',
		'default' => '',
	],
	// icon starts
	'iconPrimaryColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'iconBackgroundColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'iconTransition' => [
		'type' => 'number',
		'default' => '',
	],
	// Heading starts
	'heading' => array(
		'type' => 'string',
		'source' => 'html',
		'selector' => '.ablocks-info-box-heading',
		'default' => 'Your Info Box Title',
	),
	'headingTag' => array(
		'type' => 'string',
		'default' => 'h2',
	),
	'headingTextColor' => array(
		'type' => 'string',
		'default' => '#13191B',
	),
	'headingTextColorHover' => array(
		'type' => 'string',
		'default' => '',
	),
	'headingTransition' => [
		'type' => 'number',
		'default' => '',
	],
	// Sub Heading starts
	'subHeading' => array(
		'type' => 'string',
		'source' => 'html',
		'selector' => '.ablocks-info-box-sub-heading',
		'default' => 'Your Info Box Sub Title',
	),
	'subHeadingTag' => array(
		'type' => 'string',
		'default' => 'h3',
	),
	'subHeadingTextColor' => array(
		'type' => 'string',
		'default' => '#13191B',
	),
	'subHeadingTextColorHover' => array(
		'type' => 'string',
		'default' => '',
	),
	'subHeadingTransition' => [
		'type' => 'number',
		'default' => '',
	],
	// paragraph starts
	'des'         => [
		'type'          => 'string',
		'source'        => 'html',
		'selector'      => '.ablocks-info-box-text',
		'default'       => 'Showcase details with style and precision! Customize captivating visuals and text for an engaging, attention-grabbing display!',
	],
	'desDropCaps'          => [
		'type'          => 'boolean',
		'default'       => false,
	],
	'desDropCapsTextColor' => [
		'type'          => 'string',
		'default'       => '#0f2aff',
	],
	'desSize'     => [
		'type'          => 'string',
		'default'       => 'sm',
	],
	'desGraphSize' => [
		'type' => 'string',
		'default' => 'sm',
	],
	'desTextColor'         => [
		'type'          => 'string',
		'default'       => '#595959',
	],
	'desTextColorHover'         => [
		'type'          => 'string',
		'default'       => '',
	],
	'desTransition' => [
		'type' => 'number',
		'default' => '',
	],
	// Star Rating starts
	'ratingScale'            => [
		'type'         => 'number',
		'default'      => 5,
	],
	'ratingColor'      => [
		'type'         => 'string',
		'default'      => '#e99516',
	],
	'ratingColorHover'      => [
		'type'         => 'string',
		'default'      => '',
	],
	'ratingUnmarkedColor'  => [
		'type'         => 'string',
		'default'      => '#696969'
	],
	'ratingTransition' => [
		'type' => 'number',
		'default' => '',
	],
	'ratingUnmarkedColorHover'  => [
		'type'         => 'string',
		'default'      => ''
	],
	'ratingShowCount'        => [
		'type'         => 'boolean',
		'default'      => true,
	],
	'showRatingNumber' => [
		'type'         => 'boolean',
		'default'      => true,
	],
	'showCount'        => [
		'type'         => 'boolean',
		'default'      => true,
	],
	'ratingNumberColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
	'ratingNumberPosition' => [
		'type' => 'string',
		'default' => 'right',
	],
	// button starts
	'btnText' => [
		'type' => 'string',
		'default' => 'Learn More',
	],
	'btnSize' => [
		'type' => 'string',
		'default' => 'sm',
	],
	'btnTextColor' => [
		'type' => 'string',
		'default' => '#FAFAFA',
	],
	'btnTextColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'btnBackground' => [
		'type' => 'string',
		'default' => '#13191B',
	],
	'btnBackgroundH' => [
		'type' => 'string',
		'default' => '',
	],
	'btnTransition' => [
		'type' => 'number',
		'default' => '',
	],
	'btnIconPosition' => [
		'type' => 'string',
		'default' => 'right',
	],
	'btnIconSpace' => [
		'type' => 'number',
		'default' => 10,
	],
	'btnShowIcon' => [
		'type' => 'boolean',
		'default' => true
	],
	'stack' => [
		'type' => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true ),
	ButtonGroup::get_attribute( 'iconPlacement', true, [
		'value' => '',
	] ),
	// badge starts
	Border::get_attribute( 'badgeBorder', true ),
	Dimensions::get_attribute( 'badgePadding', true ),
	Typography::get_attribute( 'badgeTypography', true, [
		'fontFamily' => 'Roboto',
		'weight' => '400',
		'fontSize' => '12',
	] ),
	TextShadow::get_attribute( 'badgeTextShadow' ),
	Alignment::get_attribute( 'badgePosition', true, [ 'value' => 'top-right' ] ),
	Alignment::get_attribute( 'badgeAlignment', true, [ 'value' => 'center' ] ),
	// icon starts
	Icon::get_attribute('icon', [
		'color' => '#000000',
		'hasNoSelectorOrSource' => true
	] ),
	Dimensions::get_attribute( 'iconMargin', true ),
	Link::get_attribute( 'iconLink' ),
	Alignment::get_attribute( 'iconAlignment', true ),
	// heading starts
	Typography::get_attribute( 'headingTypography', true, [
		'fontFamily' => 'Roboto',
		'weight' => '600',
		'fontSize' => '28',
	]),
	TextShadow::get_attribute( 'headingTextShadow' ),
	TextStroke::get_attribute( 'headingTextStroke', true ),
	Dimensions::get_attribute( 'headingMargin', true ),
	// sub heading starts
	Typography::get_attribute( 'subHeadingTypography', true, [
		'fontFamily' => 'Roboto',
		'weight' => '500',
		'fontSize' => '20',
	]),
	TextShadow::get_attribute( 'subHeadingTextShadow' ),
	TextStroke::get_attribute( 'subHeadingTextStroke', true ),
	Dimensions::get_attribute( 'subHeadingMargin', true ),
	// paragraph starts
	Typography::get_attribute( 'desTypography', true, [
		'fontFamily' => 'Roboto',
		'weight' => '400',
		'fontSize' => '16',
	]),
	TextShadow::get_attribute( 'desTextShadow' ),
	TextStroke::get_attribute( 'desTextStroke', true ),
	Dimensions::get_attribute( 'desMargin', true ),
	// button starts
	Icon::get_attribute('btnIcon', [
		'size' => 20,
		'color' => '#FAFAFA',
		'path' => 'M190.5 66.9l22.2-22.2c9.4-9.4 24.6-9.4 33.9 0L441 239c9.4 9.4 9.4 24.6 0 33.9L246.6 467.3c-9.4 9.4-24.6 9.4-33.9 0l-22.2-22.2c-9.5-9.5-9.3-25 .4-34.3L311.4 296H24c-13.3 0-24-10.7-24-24v-32c0-13.3 10.7-24 24-24h287.4L190.9 101.2c-9.8-9.3-10-24.8-.4-34.3z',
		'viewBox' => '0 0 448 512',
		'className' => 'fas fa-arrow-right',
		'hasNoSelectorOrSource' => true,
	] ),
	Link::get_attribute( 'btnLink' ),
	Border::get_attribute( 'btnBorder', true ),
	Dimensions::get_attribute( 'btnPadding', true ),
	Dimensions::get_attribute( 'btnMargin', true ),
	Typography::get_attribute( 'btnTypography', true ),
	TextShadow::get_attribute( 'btnTextShadow' ),
	Alignment::get_attribute( 'btnAlignment', true, [ 'value' => 'center' ] ),
	// star rating starts
	Typography::get_attribute( 'ratingNumberTypography', true ),
	Dimensions::get_attribute( 'ratingMargin', true ),
	Icon::get_attribute( 'starIcon', [
		'path' => 'M528.1 171.5L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6zM388.6 312.3l23.7 138.4L288 385.4l-124.3 65.3 23.7-138.4-100.6-98 139-20.2 62.2-126 62.2 126 139 20.2-100.6 98z',
		'viewBox' => '0 0 576 512',
		'className' => 'far fa-star',
		'hasNoSelectorOrSource' => true,
	] ),
	Range::get_attribute( [
		'attributeName' => 'rating',
		'isResponsive' => false,
		'defaultValue' => 4,
	]),
	Range::get_attribute( [
		'attributeName' => 'spacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'ratingNumberGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute([
		'attributeName' => 'iconGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 16,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'contentGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );


