<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'src' => array(
		'type' => 'string',
		'default' => 'http://example.com/example.pdf',
	),
	'width' => array(
		'type' => 'string',
		'default' => '100%',
	),
	'height' => array(
		'type' => 'string',
		'default' => '500px',
	),
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'cat_typography', true ),
	Typography::get_attribute( 'title_typography', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

