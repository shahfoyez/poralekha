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
	'text' => [
		'type' => 'string',
		'default' => 'Buy now',
	],
	'buttonType' => [
		'type' => 'string',
		'default' => '#032e82',
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

	// PayPal Pricing & Payments
	'paypalAccount' => [
		'type' => 'string',
		'default' => '',
	],
	'trxType' => [
		'type' => 'string',
		'default' => '_xclick',
	],
	'itemName' => [
		'type' => 'string',
		'default' => '',
	],
	'sku' => [
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
		'default' => '1',
	],
	'shippingPrice' => [
		'type' => 'number',
		'default' => '0',
	],
	'tax' => [
		'type' => 'number',
		'default' => '0',
	],
	// donation extra
	'isAmountFixed' => [
		'type' => 'boolean',
		'default' => false,
	],
	// subscription extra
	'isAutoRenewal' => [
		'type' => 'boolean',
		'default' => true,
	],
	'billingCycle' => [
		'type' => 'string',
		'default' => 'M',
	],

	// PayPal Additional Options
	'redirectionAfterPayment' => [
		'type' => 'string',
		'default' => '',
	],
	'sandboxMode' => [
		'type' => 'boolean',
		'default' => false,
	],
	'sandboxEmail' => [
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
		'className' => 'fab fa-paypal',
		'path' => 'M111.4 295.9c-3.5 19.2-17.4 108.7-21.5 134-.3 1.8-1 2.5-3 2.5H12.3c-7.6 0-13.1-6.6-12.1-13.9L58.8 46.6c1.5-9.6 10.1-16.9 20-16.9 152.3 0 165.1-3.7 204 11.4 60.1 23.3 65.6 79.5 44 140.3-21.5 62.6-72.5 89.5-140.1 90.3-43.4.7-69.5-7-75.3 24.2zM357.1 152c-1.8-1.3-2.5-1.8-3 1.3-2 11.4-5.1 22.5-8.8 33.6-39.9 113.8-150.5 103.9-204.5 103.9-6.1 0-10.1 3.3-10.9 9.4-22.6 140.4-27.1 169.7-27.1 169.7-1 7.1 3.5 12.9 10.6 12.9h63.5c8.6 0 15.7-6.3 17.4-14.9.7-5.4-1.1 6.1 14.4-91.3 4.6-22 14.3-19.7 29.3-19.7 71 0 126.4-28.8 142.9-112.3 6.5-34.8 4.6-71.4-23.8-92.6z',
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

