<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Typography;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Border;
use ABlocks\Components\ButtonGroup;
use ABlocks\Controls\Dimensions;


$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type' => 'number',
		'default' => '',
	],
	'targetTime' => [
		'type' => 'string',
		'default' => '',
	],
	// flex direction start
	'direction' => [
		'type' => 'string',
		'default' => 'row',
	],
	'directionTablet' => [
		'type' => 'string',
		'default' => 'row',
	],
	'directionMobile' => [
		'type' => 'string',
		'default' => 'row',
	],
	// flex direction end
	// flex 'justify content' start
	'justify' => [
		'type' => 'string',
		'default' => 'center',
	],
	'justifyTablet' => [
		'type' => 'string',
		'default' => 'center',
	],
	'justifyMobile' => [
		'type' => 'string',
		'default' => 'center',
	],
	// flex 'justify content' end
	// flex 'align items" start
	'align' => [
		'type' => 'string',
		'default' => 'stretch',
	],
	'alignTablet' => [
		'type' => 'string',
		'default' => 'stretch',
	],
	'alignMobile' => [
		'type' => 'string',
		'default' => 'stretch',
	],
	// flex 'align items" end

	// flex 'wrap" start
	'wrap' => [
		'type' => 'string',
		'default' => 'wrap',
	],
	'wrapTablet' => [
		'type' => 'string',
		'default' => 'wrap',
	],
	'wrapMobile' => [
		'type' => 'string',
		'default' => 'wrap',
	],
	'showDay' => [
		'type' => 'boolean',
		'default' => true,
	],
	'showHour' => [
		'type' => 'boolean',
		'default' => true,
	],
	'showMinute' => [
		'type' => 'boolean',
		'default' => true,
	],
	'showSecond' => [
		'type' => 'boolean',
		'default' => true,
	],
	'dayLabel' => [
		'type' => 'string',
		'default' => 'Days',
	],
	'hourLabel' => [
		'type' => 'string',
		'default' => 'Hours',
	],
	'minuteLabel' => [
		'type' => 'string',
		'default' => 'Minute',
	],
	'secondLabel' => [
		'type' => 'string',
		'default' => 'Second',
	],
	'isAction' => [
		'type' => 'boolean',
		'default' => true,
	],
	'evergreen' => [
		'type' => 'boolean',
		'default' => false,
	],
	'evergreenDays' => [
		'type' => 'number',
		'default' => 0,
	],
	'evergreenHours' => [
		'type' => 'number',
		'default' => 0,
	],
	'evergreenMinutes' => [
		'type' => 'number',
		'default' => 0,
	],
	'evergreenRestart' => [
		'type' => 'boolean',
		'default' => false,
	],
	'action' => [
		'type' => 'string',
		'default' => 'none',
	],
	'actionMessage' => [
		'type' => 'string',
		'default' => 'Time Expire',
	],
	'actionMessageColor' => [
		'type' => 'string',
		'default' => 'black',
	],
	'actionRedirectURL' => [
		'type' => 'string',
		'default' => '',
	],
	'labelPosition' => [
		'type' => 'string',
		'default' => 'column',
	],
	'showLabels' => [
		'type' => 'boolean',
		'default' => true,
	],
	'boxBackgroundType' => [
		'type' => 'string',
		'default' => 'transparent',
	],
	'boxBackgroundColor' => [
		'type' => 'string',
		'default' => '',
	],
	'labelColor' => [
		'type' => 'string',
		'default' => '',
	],
	'labelBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'numberColor' => [
		'type' => 'string',
		'default' => '',
	],
	'numberBgColor' => [
		'type' => 'string',
		'default' => '',

	],
	'showSeparator' => [
		'type' => 'boolean',
		'default' => true,
	],
	'separatorColor' => [
		'type' => 'string',
		'default' => 'black',
	],
];

$attributes = array_merge(
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'center' ] ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Border::get_attribute( 'boxBorder', true ),
	Typography::get_attribute( 'actionMessageTypography', true ),
	Typography::get_attribute( 'labelTypography', true ),
	Typography::get_attribute( 'numberTypography', true ),
	Typography::get_attribute( 'separatorTypography', true ),
	ButtonGroup::get_attribute( 'separator', false, [
		'value' => ':',
	] ),
	ButtonGroup::get_attribute( 'orient', true, [
		'value' => 'row',
	] ),
	ButtonGroup::get_attribute( 'justificationAlign', true, [
		'value' => 'center',
	] ),
	ButtonGroup::get_attribute( 'alignment', true, [
		'value' => 'stretch',
	] ),
	ButtonGroup::get_attribute( 'wrapping', true, [
		'value' => 'wrap',
	] ),
	Range::get_attribute( [
		'attributeName' => 'boxSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 130,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'boxRowGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'boxColumnGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'numberAndLabelGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 5,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Dimensions::get_attribute( 'padding', true ),
	$attributes
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

