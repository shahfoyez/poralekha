<?php
namespace ABlocks\Blocks\FormBuilder\Actions;

use ABlocks\Blocks\FormBuilder\Actions\Interfaces\FormSubmissionAction;
use ABlocks\Blocks\FormBuilder\ValidateFormData;
use ABlocks\Blocks\FormBuilder\Subscribe\Mailchimp;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Send email to admin
 */
final class Subscribe implements FormSubmissionAction {

	/** @var $validateformdata */
	private ValidateFormData $validateformdata;

	/** @var $user_email */
	private string $user_email;

	/** @var $api_key */
	private string $api_key;
	/** @var $list_id */
	private string $list_id;
	/** @var $settings */
	private object $settings;
	/** @var $config */
	private array $config;

	/**
	 * Contructor
	 *
	 * @param ValidateFormData $obj
	 */
	public function __construct( ValidateFormData $obj ) {
		$this->validateformdata = $obj;
		$this->user_email = $this->validateformdata->form_info['info']['email'];
		$this->config = $this->validateformdata->form_info['info']['config'];
		$this->settings = $GLOBALS['ablocks_settings'];
	}


	protected function get_data_by_field_mapping( $fields ) : array {
		if (
			empty( $fields ) ||
			empty( $this->validateformdata->form_info['data'] )
		) {
			return [];
		};

		$data = [];
		$value = $this->validateformdata->form_info['data'][ $target ]['value'] ?? '';
		foreach ( $fields as $field => $target ) {
			if (
				$target === 'default' ||
				empty( $value )
			) {
				continue;
			}
			$data[ $field ] = $value;
		}
		return $data;
	}

	/**
	 * Subscribe action
	 *
	 * @return void
	 */
	public function action() {

		$this->mailchimp();

	}

	public function mailchimp() {

		if (
			! in_array( __FUNCTION__, $this->validateformdata->form_info['info']['actions'], true )
		) {
			return;
		}

		if (
			! array_key_exists( 'mailchimpListId', $this->config )
		) {
			return;
		}

		if ( array_key_exists( 'mailchimpApiKey', $this->config ) ) {
			$api_key = $this->config['mailchimpApiKey'];
		} else {
			$api_key = property_exists( $this->settings, 'mailchimp_api_key' ) ? $this->settings->mailchimp_api_key : '';
		}
		$list_id          = $this->config['mailchimpListId'];
		$mailchimp_groups = $this->config['mailchimpgroupSelects'] ?? [];
		$status           = $this->config['mailchimp_status'] ?? 'subscribed';

		$email = $this->validateformdata->form_info['data'][ $this->config['mailchimpEmailSelects'] ?? '' ]['value'] ?? '';
		$email = is_email( $email ) ? $email : $this->user_email;
		if (
			empty( $api_key ) ||
			empty( $list_id ) ||
			empty( $email )
		) {
			return;
		}

		try {
			( new Mailchimp(
				$api_key,
				$list_id,
				$status
			) )
			->subscribe(
				$email,
				$mailchimp_groups,
				$this->get_data_by_field_mapping( $this->config['mailchimpMapSelects'] ?? [] ),
				explode( ',', $this->config['mailchimpTags'] ?? '' )
			);
		} catch ( Exception $e ) {
			return $e->getMessage();
		}

	}
}
