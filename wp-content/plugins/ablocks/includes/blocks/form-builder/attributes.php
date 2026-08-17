<?php
namespace ABlocks\Blocks\FormBuilder;

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Link;
use ABlocks\Components\ButtonGroup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => ''
	),
	'email_list_id' => array(
		'type' => 'string',
		'default' => ''
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'variationSelected' => [
		'type' => 'boolean',
		'default' => true,
	],
	'postId' => [
		'type' => 'number',
		'default' => '',
	],
	'email_template_id' => [
		'type' => 'string',
		'default' => '',
	],
	'showLabels' => [
		'type' => 'boolean',
		'default' => true
	],
	'subscriberToUser' => [
		'type' => 'boolean',
		'default' => false
	],
	'adminNotify' => [
		'type' => 'boolean',
		'default' => true
	],
	'loginRedirect' => [
		'type' => 'boolean',
		'default' => false
	],
	'registerRedirect' => [
		'type' => 'boolean',
		'default' => false
	],
	'navigatorAccess' => [
		'type' => 'boolean',
		'default' => false
	],
	'formName' => array(
		'type' => 'string',
		'default' => ''
	),
	'formType' => array(
		'type' => 'string',
		'default' => ''
	),
	'formActions' => array(
		'type' => 'array',
		'default' => []
	),
	'childDetails' => array(
		'type' => 'array',
		'default' => []
	),
	'labelColor' => [
		'type' => 'string',
		'default' => '#000000'
	],
	'helperTextColor' => [
		'type' => 'string',
		'default' => ''
	],
	'customForm' => [
		'type' => 'boolean',
		'default' => false
	],
	'inputColor' => [
		'type' => 'string',
		'default' => ''
	],
	'inputBgColor' => [
		'type' => 'string',
		'default' => 'white'
	],
	'inputPlaceholderColor' => [
		'type' => 'string',
		'default' => ''
	],
	'buttonSize' => [
		'type' => 'string',
		'default' => 'full-width'
	],
	'buttonColor' => [
		'type' => 'string',
		'default' => ''
	],
	'buttonBgColor' => [
		'type' => 'string',
		'default' => ''
	],
	'buttonHColor' => [
		'type' => 'string',
		'default' => ''
	],
	'buttonBgHColor' => [
		'type' => 'string',
		'default' => ''
	],
	'buttonText' => [
		'type' => 'string',
		'default' => 'Submit Here',
	],
	// email One settings
	'emailOneTo' => [
		'type' => 'string',
		'default' => ''
	],
	'emailOneSubject' => [
		'type' => 'string',
		'default' => 'New message'
	],
	'emailOneMessage' => [
		'type' => 'string',
		'default' => '{all-fields}'
	],
	'emailOneFormEmail' => [
		'type' => 'string',
		'default' => ''
	],
	'emailOneFormName' => [
		'type' => 'string',
		'default' => 'Local Name'
	],
	'emailOneReplyTo' => [
		'type' => 'string',
		'default' => ''
	],
	'emailOneCc' => [
		'type' => 'string',
		'default' => ''
	],
	'emailOneBcc' => [
		'type' => 'string',
		'default' => ''
	],
	'emailOneType' => [
		'type' => 'string',
		'default' => 'HTML'
	],
	// email two settings
	'emailTwoTo' => [
		'type' => 'string',
		'default' => ''
	],
	'emailTwoSubject' => [
		'type' => 'string',
		'default' => 'New message'
	],
	'emailTwoMessage' => [
		'type' => 'string',
		'default' => '{all-fields}'
	],
	'emailTwoFormEmail' => [
		'type' => 'string',
		'default' => ''
	],
	'emailTwoFormName' => [
		'type' => 'string',
		'default' => 'Local Name'
	],
	'emailTwoReplyTo' => [
		'type' => 'string',
		'default' => ''
	],
	'emailTwoCc' => [
		'type' => 'string',
		'default' => ''
	],
	'emailTwoBcc' => [
		'type' => 'string',
		'default' => ''
	],
	'emailTwoType' => [
		'type' => 'string',
		'default' => 'HTML'
	],
	// mailchimp
	'mailchimpOption' => [
		'type' => 'string',
		'default' => 'default'
	],

	'mailchimpApiKey' => [
		'type' => 'string',
		'default' => ''
	],
	'mailchimpValidateApi' => [
		'type' => 'boolean',
		'default' => false,
	],
	'mailchimpListIdOptions' => [
		'type' => 'array',
		'default' => [
			[
				'label' => 'select options',
				'value' => 'default',
			]
		]
	],
	'mailchimpMapFields' => [
		'type' => 'array',
		'default' => []
	],
	'mailchimpMapSelects' => [
		'type' => 'object',
	],
	'mailchimpListId' => [
		'type' => 'string',
		'default' => 'default'
	],
	'mailchimpgroupsOptions' => [
		'type' => 'array',
		'default' => []
	],
	'mailchimpgroupSelects' => [
		'type' => 'array',
		'default' => []
	],
	'mailchimpTags' => [
		'type' => 'string',
		'default' => ''
	],
	'mapEmailOptions' => [
		'type' => 'array',
		'default' => [
			[
				'label' => 'None',
				'value' => 'default'
			]
		]
	],
	'mailchimpEmailSelects' => [
		'type' => 'string',
		'default' => 'default'
	],
	'mapOtherOptions' => [
		'type' => 'array',
		'default' => [
			[
				'label' => 'None',
				'value' => 'default'
			]
		]
	],
	'showDoubleOpt' => [
		'type' => 'boolean',
		'default' => false
	],
	'mailchimpStatus' => [
		'type' => 'string',
		'default' => ''
	],

	// mailerlite attributes
	'mailerliteOption' => [
		'type' => 'string',
		'default' => 'default'
	],

	'mailerliteApiKey' => [
		'type' => 'string',
		'default' => ''
	],
	'mailerliteValidateApi' => [
		'type' => 'boolean',
		'default' => false,
	],
	'mailerliteGroupId' => [
		'type' => 'string',
		'default' => 'default'
	],
	'mailerliteGroupIdOptions' => [
		'type' => 'array',
		'default' => [
			[
				'label' => 'select options',
				'value' => 'default',
			]
		]
	],
	'mailerliteMapFields' => [
		'type' => 'array',
		'default' => []
	],
	'mailerliteMapSelects' => [
		'type' => 'object'
	],
	'allowReSubscribe' => [
		'type' => 'boolean',
		'default' => false
	],

	// drip attributes
	'dripOption' => [
		'type' => 'string',
		'default' => 'default'
	],

	'dripApiKey' => [
		'type' => 'string',
		'default' => ''
	],
	'dripValidateApi' => [
		'type' => 'boolean',
		'default' => false,
	],
	'dripAccountIdOptions' => [
		'type' => 'array',
		'default' => [
			[
				'label' => 'select options',
				'value' => 'default'
			]
		]
	],
	'dripAccountId' => [
		'type' => 'string',
		'default' => 'default'
	],
	'dripMapFields' => [
		'type' => 'array',
		'default' => []
	],
	'dripMapSelects' => [
		'type' => 'object'
	],
	'dripEmailSelects' => [
		'type' => 'string',
		'default' => 'default'
	],
	'dripTags' => [
		'type' => 'string',
		'default' => ''
	],
	'showFormFields' => [
		'type' => 'boolean',
		'default' => false
	],

	// getResponse attributes
	'getResponseOption' => [
		'type' => 'string',
		'default' => 'default'
	],

	'getResponseApiKey' => [
		'type' => 'string',
		'default' => ''
	],
	'getResponseValidateApi' => [
		'type' => 'boolean',
		'default' => false,
	],
	'getResponseListId' => [
		'type' => 'string',
		'default' => 'default'
	],
	'getResponseListIdOptions' => [
		'type' => 'array',
		'default' => [
			[
				'label' => 'select options',
				'value' => 'default'
			]
		]
	],
	'getResponseMapFields' => [
		'type' => 'array',
		'default' => []
	],
	'getResponseMapSelects' => [
		'type' => 'object'
	],
	'getResponseDayCycle' => [
		'type' => 'number',
		'default' => 1,
	],



	// getResponse attributes
	'convertkitOption' => [
		'type' => 'string',
		'default' => 'default'
	],

	'convertkitApiKey' => [
		'type' => 'string',
		'default' => ''
	],
	'convertkitValidateApi' => [
		'type' => 'boolean',
		'default' => false,
	],
	'convertkitFormId' => [
		'type' => 'string',
		'default' => 'default'
	],
	'convertkitFormIdOptions' => [
		'type' => 'array',
		'default' => [
			[
				'label' => 'select options',
				'value' => 'default',
			]
		]
	],
	'convertkitMapFields' => [
		'type' => 'array',
		'default' => []
	],
	'convertkitMapSelects' => [
		'type' => 'object'
	],
	'convertkitEmailSelects' => [
		'type' => 'string',
		'default' => 'default'
	],
	'convertkitFirstNameSelects' => [
		'type' => 'string',
		'default' => 'default'
	],
	'convertkitTagsOptions' => [
		'type' => 'array',
		'default' => []
	],
	'convertkitTagsSelects' => [
		'type' => 'array',
		'default' => []
	],
	'emailVerification' => [
		'type' => 'boolean',
		'default' => false
	],
	'submissionMetaData' => [
		'type' => 'array',
		'default' => []
	],
	'navigatorColor' => [
		'type' => 'string',
		'default' => '#74777C'
	],
	'navigatorIcon' => [
		'type' => 'object',
		'default' => [
			'viewBox' => '0 0 448 512',
			'path' => 'M257.5 445.1l-22.2 22.2c-9.4 9.4-24.6 9.4-33.9 0L7 273c-9.4-9.4-9.4-24.6 0-33.9L201.4 44.7c9.4-9.4 24.6-9.4 33.9 0l22.2 22.2c9.5 9.5 9.3 25-.4 34.3L136.6 216H424c13.3 0 24 10.7 24 24v32c0 13.3-10.7 24-24 24H136.6l120.5 114.8c9.8 9.3 10 24.8.4 34.3z'
		],
	],
	'navigatorIconShow' => [
		'type' => 'boolean',
		'default' => true
	],
	'forgetPasswordLabel' => array(
		'type' => 'string',
		'default' => 'Lost Your Password',
	),
	'loginLabel' => array(
		'type' => 'string',
		'default' => 'Log in',
	),
	'registerLabel' => array(
		'type' => 'string',
		'default' => 'Register',
	),
	'homeLabel' => array(
		'type' => 'string',
		'default' => 'Go to home',
	),
	'errorBackground' => array(
		'type' => 'string',
		'default' => '#D03739'
	),
	'errorColor' => array(
		'type' => 'string',
		'default' => '#ffffff'
	),
	'successColor' => array(
		'type' => 'string',
		'default' => '#fffff'
	),
	'successBackground' => array(
		'type' => 'string',
		'default' => '#00935B'
	),
	'showErrorDemo' => [
		'type' => 'boolean',
		'default' => false,
	],
	'showSuccessDemo' => [
		'type' => 'boolean',
		'default' => false,
	],
	'userRoles' => [
		'type' => 'array',
		'default' => []
	],
	'roleSlug' => [
		'type' => 'string',
		'default' => '',
	],
	'confirmationNotice' => [
		'type' => 'string',
		'default' => 'Form successfully submitted!'

	],
	'afterFormSubmission' => [
		'type' => 'string',
		'default' => 'reset'

	],
	'customUrl' => [
		'type' => 'string',
		'default' => ''

	],
	'confirmationType' => [
		'type' => 'string',
		'default' => 'success'

	],
];
$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'labelAlignment', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'inputAlignment', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'buttonTextAlignment', true, [ 'value' => 'center' ] ),
	Alignment::get_attribute( 'navigatorAlignment', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'buttonAlignment', true, [ 'value' => 'center' ] ),
	Alignment::get_attribute( 'successErrorAlignment', true, [ 'value' => 'center' ] ),
	Range::get_attribute( [
		'attributeName' => 'rowsSpacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
	] ),
	Range::get_attribute( [
		'attributeName' => 'buttonHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'labelSpacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
	] ),
	Range::get_attribute( [
		'attributeName' => 'helperTextSpacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 10,
	] ),
	Range::get_attribute( [
		'attributeName' => 'inputIconPosition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 75,
	] ),
	Range::get_attribute( [
		'attributeName' => 'navigatorSpacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 10,
	] ),
	Link::get_attribute( 'link' ),
	Link::get_attribute( 'webhookLink' ),
	Typography::get_attribute( 'labelTypography', true ),
	Typography::get_attribute( 'helperTextTypography', true ),
	Typography::get_attribute( 'inputTypography', true ),
	Typography::get_attribute( 'buttonTypography', true ),
	Typography::get_attribute( 'navigatorTypography', true ),
	Typography::get_attribute( 'successErrorTypography', true ),
	Border::get_attribute( 'inputBorder', true ),
	Border::get_attribute( 'buttonBorder', true ),
	Dimensions::get_attribute( 'inputPadding', true ),
	Dimensions::get_attribute( 'navigatorPadding', true ),
	Dimensions::get_attribute( 'successErrorPadding', true ),
	Dimensions::get_attribute( 'buttonPadding', true ),
	ButtonGroup::get_attribute( 'dir', true, [
		'value' => 'column',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

