<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Border;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Components\ButtonGroup;


$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type' => 'number',
		'default' => 2,
	],
	'logoutRedirect' => [
		'type' => 'string',
		'default' => 'current-url'
	],
	'logoutCustomUrl' => [
		'type' => 'string',
		'default' => ''
	],
	'loginRedirect' => [
		'type' => 'string',
		'default' => 'current-url'
	],
	'loginCustomUrl' => [
		'type' => 'string',
		'default' => ''
	],
	'logOutLabel' => [
		'type' => 'string',
		'default' => 'Logout',
	],
	'logInLabel' => [
		'type' => 'string',
		'default' => 'Login',
	],
	'isRedirect' => [
		'type' => 'boolean',
		'default' => true,
	],
	'isShowAvatar' => [
		'type' => 'boolean',
		'default' => false,
	],
	'logOutLabelColor' => [
		'type' => 'string',
		'default' => '',
	],
	'logOutLabelBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'isShowName' => [
		'type' => 'boolean',
		'default' => false
	],
	'nameColor' => [
		'type' => 'string',
		'default' => ''
	],
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'labelAlignment', true, [ 'value' => 'flex-start' ] ),
	ButtonGroup::get_attribute( 'direction', true, [
		'value' => 'row',
	] ),
	Border::get_attribute( 'avatarBorder', true ),
	Range::get_attribute([
		'attributeName' => 'avatarWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
		'defaultValue' => 40,
	]),
	Range::get_attribute([
		'attributeName' => 'avatarHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
		'defaultValue' => 40,
	]),
	Typography::get_attribute( 'nameTypography', true ),
	TextShadow::get_attribute( 'nameTextShadow', true ),
	TextStroke::get_attribute( 'nameTextStroke', true ),
	Typography::get_attribute( 'labelTypography', true ),
	TextShadow::get_attribute( 'labelTextShadow', true ),
	TextStroke::get_attribute( 'labelTextStroke', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
