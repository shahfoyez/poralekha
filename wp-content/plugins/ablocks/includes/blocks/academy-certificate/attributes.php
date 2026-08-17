<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;


$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'backgroundImage' => array(
		'type' => 'string',
		'default' => '',
	),
	'pageSize' => array(
		'type' => 'string',
		'default' => 'A4',
	),
	'pageOrientation' => array(
		'type' => 'string',
		'default' => 'L',
	),
	'imageOptions' => array(
		'type' => 'array',
		'default' => [],
	),
	'isCustomImage' => array(
		'type' => 'boolean',
		'default' => false,
	),
];

$attributes = array_merge(
	$attributes,
	Dimensions::get_attribute( 'certificate_padding', true ),
	Range::get_attribute([
		'attributeName' => 'containerWidth',
		'isResponsive' => false,
		'defaultValue' => 80,
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
