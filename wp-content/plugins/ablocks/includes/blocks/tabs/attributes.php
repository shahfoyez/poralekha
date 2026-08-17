<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Components\ButtonGroup;

$attributes = [
	'block_id' => [
		'type'      => 'string',
		'default'   => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'className' => [
		'type' => 'string',
		'default'   => '',
	],
	'tabIcons' => [
		'type' => 'array',
		'default' => []
	],
	'tabIconsClasses' => [
		'type' => 'array',
		'default' => [],
	],
	'clickedTabIndex' => [
		'type' => 'number',
		'default' => 0
	],
	'tabMenusBackgroundColor' => [
		'type' => 'string',
		'default' => ''
	],
	'tabPositionChange' => [
		'type' => 'boolean',
		'default' => false
	],
	'tabHeaders' => [
		'type' => 'array',
		'default' => [
			__( 'Tab 1', 'ablocks' ),
			__( 'Tab 2', 'ablocks' ),
			__( 'Tab 3', 'ablocks' ),
		]
	],
	'tabSubTitles' => [
		'type' => 'array',
		'default' => [
			__( 'Subtitle 1 Content: This tab provides general information about our company', 'ablocks' ),
			__( 'Subtitle 2 Content: This tab provides general information about our company', 'ablocks' ),
			__( 'Subtitle 3 Content: This tab provides general information about our company', 'ablocks' ),
		]
	],
	'tabActive' => [
		'type' => 'number',
		'default' => 0
	],
	'previousTotalBlock' => [
		'type' => 'number',
		'default' => 3
	],
	'tabsMenuPosition' => [
		'type' => 'string',
		'default' => 'left',
	],
	'tabsMenuPositionTablet' => [
		'type' => 'string',
		'default' => '',
	],
	'tabsMenuPositionMobile' => [
		'type' => 'string',
		'default' => '',
	],
	'initialOpen' => [
		'type' => 'number',
		'default' => 1
	],
	'activeDuration' => [
		'type' => 'number',
		'default' => 5000
	],
	'activeColorOptions' => [
		'type' => 'string',
		'default' => 'background'
	],
	'enableHoverSwitch' => [
		'type' => 'boolean',
		'default' => false,
	],
	'tabMenuAlign' => [
		'type' => 'string',
		'default'   => '',
	],
	'tabMenuAlignTablet' => [
		'type' => 'string',
		'default'   => '',
	],
	'tabMenuAlignMobile' => [
		'type' => 'string',
		'default'   => '',
	],
	'menuContentAlign' => [
		'type' => 'string',
		'default' => 'center'
	],
	'menuContentAlignTablet' => [
		'type' => 'string',
		'default' => '',
	],
	'menuContentAlignMobile' => [
		'type' => 'string',
		'default' => '',
	],
	'tabBackgroundColor' => [
		'type' => 'string',
		'default' => '#F9F9F9'
	],
	'contentBackgroundColor' => [
		'type' => 'string',
		'default' => ''
	],
	'tabActiveBackgroundColor' => [
		'type' => 'string',
		'default' => '#61CE70'
	],
	'titleTextColor' => [
		'type' => 'string',
		'default' => '#13191B'
	],
	'titleTextActiveColor' => [
		'type' => 'string',
		'default' => '#0A0909'
	],
	'subTitleTextColor' => [
		'type' => 'string',
		'default' => '#3A3A3A '
	],
	'subTitleTextActiveColor' => [
		'type' => 'string',
		'default' => '#3A3A3A'
	],
	'activeBorderColor' => [
		'type' => 'string',
		'default'   => '#61CE70',
	],
	'showTitle' => [
		'type' => 'boolean',
		'default' => true
	],
	'showSubTitle' => [
		'type' => 'boolean',
		'default' => false
	],
	'showActiveSubTitle' => [
		'type' => 'boolean',
		'default' => false
	],
	'tabsChangingEffect' => [
		'type' => 'string',
		'default' => 'default',
	],
	'showIcon' => [
		'type' => 'boolean',
		'default' => false
	],
	'progressBarColor' => [
		'type' => 'string',
		'default' => '#13191B'
	],
	'iconColor' => [
		'type' => 'string',
		'default' => '#000000'
	],
	'iconActiveColor' => [
		'type' => 'string',
		'default'   => '',
	],
	'iconType' => [
		'type' => 'string',
		'default' => 'default'
	],
	'iconShape' => [
		'type' => 'string',
		'default' => 'circle'
	],
	'iconBackground' => [
		'type' => 'string',
		'default' => '#FFFFFF'
	],
	'iconActiveBackground' => [
		'type' => 'string',
		'default' => '#E5E5EC'
	],
];

$attributes = array_merge(
	$attributes,
	Range::get_attribute( [
		'attributeName' => 'size',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 18,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'spacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'tabsWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 30,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	] ),
	Range::get_attribute( [
		'attributeName' => 'contentWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 70,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	] ),
	Range::get_attribute( [
		'attributeName' => 'tabsGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'contentGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Icon::get_attribute( 'icon' ),
	ButtonGroup::get_attribute( 'iconPosition', true, [
		'value' => 'left',
	] ),
	ButtonGroup::get_attribute( 'tabsMenuPositioning', true, [
		'value' => 'left',
	] ),
	ButtonGroup::get_attribute( 'tabsMenuDirection', true, [
		'value' => 'row',
	] ),
	ButtonGroup::get_attribute( 'tabsWidthType', true, [
		'value' => 'auto',
	] ),
	ButtonGroup::get_attribute( 'tabsContentWidthType', true, [
		'value' => 'auto',
	] ),
	ButtonGroup::get_attribute( 'tabWrap', true, [
		'value' => 'wrap',
	] ),
	ButtonGroup::get_attribute( 'tabMenuAlignment', true, [
		'value' => 'center',
	] ),
	ButtonGroup::get_attribute( 'menuContentAlignment', true, [
		'value' => 'center',
	] ),
	Dimensions::get_attribute( 'tabMenusMargin', true ),
	Dimensions::get_attribute( 'tabMenusPadding', true ),
	Border::get_attribute( 'tabMenusBorder', true ),
	Dimensions::get_attribute( 'contentMargin', true ),
	Dimensions::get_attribute( 'iconPositionMargin', true ),
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'subTitleTypography', true ),
	Dimensions::get_attribute( 'menuContentPadding', true ),
	Dimensions::get_attribute( 'contentPadding', true ),
	Border::get_attribute( 'menuContentBorder', true ),
	Border::get_attribute( 'contentBorder', true ),
	BoxShadow::get_attribute( 'boxShadow', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
