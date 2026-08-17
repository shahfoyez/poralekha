<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Link;
use ABlocks\Helper;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Range;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'postId' => [
		'type' => 'number',
		'default' => '',
	],
	'text' => [
		'type' => 'string',
		'default' => 'Buy now',
	],
	'buttonType' => [
		'type' => 'string',
		'default' => '#635bff',
	],
	'buttonSize' => [
		'type' => 'string',
		'default' => 'md',
	],
	'textColor' => [
		'type' => 'string',
		'default' => '#FFFFFF',
	],
	'textColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'background' => [
		'type' => 'string',
		'default' => '',
	],
	'backgroundH' => [
		'type' => 'string',
		'default' => '',
	],
	'transition' => [
		'type' => 'number',
		'default' => '',
	],
	'showIcon' => [
		'type' => 'boolean',
		'default' => true,
	],
	'iconPosition' => [
		'type' => 'string',
		'default' => 'left',
	],

	// Stripe Pricing & Payments
	'stripeApi' => [
		'type' => 'string',
		'default' => '',
	],
	'itemName' => [
		'type' => 'string',
		'default' => '',
	],
	'price' => [
		'type' => 'number',
		'default' => '0',
	],
	'currency' => [
		'type' => 'string',
		'default' => 'USD',
	],
	'quantity' => [
		'type' => 'number',
		'default' => '0',
	],
	'shippingPrice' => [
		'type' => 'number',
		'default' => '0',
	],
	'hasTax' => [
		'type' => 'boolean',
		'default' => false,
	],
	'tax' => [
		'type' => 'array',
		'default' => [],
	],
	'taxId' => [
		'type' => 'string',
		'default' => '',
	],

	// Stripe Additional Options
	'redirectionAfterPayment' => [
		'type' => 'string',
		'default' => '',
	],
	'openInNewTab' => [
		'type' => 'boolean',
		'default' => true,
	],
	'customMessage' => [
		'type' => 'boolean',
		'default' => false,
	],
	'errorMessage' => [
		'type' => 'string',
		'default' => 'No payment method connected. Contact seller.',
	]

];


$attributes = array_merge(
	$attributes,
	Icon::get_attribute( 'icon', [
		'size' => '16',
		'className' => 'fab fa-stripe',
		'path' => 'M155.3 154.6c0-22.3 18.6-30.9 48.4-30.9 43.4 0 98.5 13.3 141.9 36.7V26.1C298.3 7.2 251.1 0 203.8 0 88.1 0 11 60.4 11 161.4c0 157.9 216.8 132.3 216.8 200.4 0 26.4-22.9 34.9-54.7 34.9-47.2 0-108.2-19.5-156.1-45.5v128.5a396.09 396.09 0 0 0 156 32.4c118.6 0 200.3-51 200.3-153.6 0-170.2-218-139.7-218-203.9z',
		'viewBox' => '0 0 384 512',
		'iconType' => 'default',
		'color' => '#ffffff',
	] ),
	Link::get_attribute( 'link' ),
	Border::get_attribute( 'border', true ),
	Dimensions::get_attribute( 'padding', true ),
	Typography::get_attribute( 'typography', true ),
	TextShadow::get_attribute( 'textShadow' ),
	Alignment::get_attribute( 'position', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	BoxShadow::get_attribute( 'boxShadow', true ),
	Range::get_attribute([
		'attributeName' => 'iconSpace',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

