<?php
namespace ABlocks\Blocks\FormBuilder;

use ABlocks\Controls\Icon;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Components\ButtonGroup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => ''
	),
	// 'inputWidth' => [
	// 'type' => 'number',
	// 'default' => 100
	// ],
	'customName' => [
		'type' => 'string',
		'default' => '',
	],
	'label' => [
		'type' => 'string',
		'default' => 'Name'
	],
	'helperText' => [
		'type' => 'string',
		'default' => ''
	],
	'formType' => [
		'type' => 'string',
		'default' => ''
	],
	'errorMsg' => [
		'type' => 'string',
		'default' => 'This field is required',
	],
	'name' => [
		'type' => 'string',
		'default' => ''
	],
	'placeholder' => [
		'type' => 'string',
		'default' => 'name'
	],
	'inputType' => [
		'type' => 'string',
		'default' => ''
	],
	'isRequired' => [
		'type' => 'boolean',
		'default' => true
	],
	'nameChangeable' => [
		'type' => 'boolean',
		'default' => true
	],
	'showIcon' => [
		'type' => 'boolean',
		'default' => false
	],
	'iconColor' => [
		'type' => 'string',
		'default' => ''
	],
];

$attributes = array_merge(
	$attributes,
	Border::get_attribute( 'border', true ),
	Icon::get_attribute('icon', [
		'size' => 28,
	] ),
	ButtonGroup::get_attribute( 'emailType', false, [
		'value' => 'email',
	] ),
	Range::get_attribute( [
		'attributeName' => 'inputIconSize',
		'isResponsive' => false,
		'defaultValue' => 28,
	] ),
	Range::get_attribute( [
		'attributeName' => 'inputIconSpace',
		'isResponsive' => false,
		'defaultValue' => 38,
	] ),
	Range::get_attribute( [
		'attributeName' => 'inputWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

