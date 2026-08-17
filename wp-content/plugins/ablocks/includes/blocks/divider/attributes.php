<?php
namespace ABlocks\Blocks\Divider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Icon;
use ABlocks\Components\ButtonGroup;


$attributes                = [
	'block_id'             => [
		'type'             => 'string',
		'default'          => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'dividerPatternUrl'    => [
		'type'             => 'string',
		'default'          => 'solid',
	],
	'dividerType'          => [
		'type'             => 'string',
		'default'          => 'css-style',
	],
	'color'                => [
		'type'             => 'string',
		'default'          => '#000000',
	],
	'elementText'          => [
		'type'             => 'string',
		'default'          => 'Divider',
	],
	'elementTextColor'     => [
		'type'             => 'string',
		'default'          => '#000000',
	],
	'elementTextPosition'  => [
		'type'             => 'string',
		'default'          => 'center',
	],

	'elementIconPosition'  => [
		'type'             => 'string',
		'default'          => 'center',
	],


];

$attributes = array_merge(
	Typography::get_attribute( 'elementTextTypography', true ),
	TextStroke::get_attribute( 'elementTextStroke', true ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Icon::get_attribute(),
	ButtonGroup::get_attribute( 'element', false, [
		'value' => 'none',
	] ),
	Range::get_attribute( [
		'attributeName' => 'width',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	] ),
	Range::get_attribute( [
		'attributeName' => 'weight',
		'isResponsive' => false,
		'defaultValue' => 4,
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
	] ),
	Range::get_attribute( [
		'attributeName' => 'elementTextSpacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'elementIconSpacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'defaultValueMobile' => 0,
		'defaultValueTablet' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	$attributes
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

