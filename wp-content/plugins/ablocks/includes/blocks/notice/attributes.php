<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Icon;


$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'heading' => [
		'type' => 'string',
		'source' => 'html',
		'selector' => '.ablocks-notice-title',
		'default' => 'Add Your Heading Text Here',
	],
	'headingTag' => [
		'type' => 'string',
		'default' => 'h1',
	],
	'textColor' => [
		'type' => 'string',
		'default' => '',
	],
	'backgroundColor' => [
		'type' => 'string',
		'default' => '#EFEFF0',
	],
	'isDismissible' => [
		'type'    => 'boolean',
		'default' => true,
	],
	'noticeClose' => [
		'type'    => 'string',
		'default' => 'oneTime',

	],

];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'typography', true ),
	TextShadow::get_attribute( 'textShadow' ),
	TextStroke::get_attribute( 'textStroke', true ),
	Dimensions::get_attribute( 'noticeHeaderPadding', true ),
	Icon::get_attribute( 'icon', [
		'path' => 'M448 32C483.3 32 512 60.65 512 96V416C512 451.3 483.3 480 448 480H64C28.65 480 0 451.3 0 416V96C0 60.65 28.65 32 64 32H448zM175 208.1L222.1 255.1L175 303C165.7 312.4 165.7 327.6 175 336.1C184.4 346.3 199.6 346.3 208.1 336.1L255.1 289.9L303 336.1C312.4 346.3 327.6 346.3 336.1 336.1C346.3 327.6 346.3 312.4 336.1 303L289.9 255.1L336.1 208.1C346.3 199.6 346.3 184.4 336.1 175C327.6 165.7 312.4 165.7 303 175L255.1 222.1L208.1 175C199.6 165.7 184.4 165.7 175 175C165.7 184.4 165.7 199.6 175 208.1V208.1z',
		'viewBox' => '0 0 512 512',
		'className' => 'far fa-window-close',
		'size' => 20,
	]),
);


return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

