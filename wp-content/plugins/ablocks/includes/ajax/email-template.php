<?php

namespace ABlocks\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AbstractAjaxHandler;
use ABlocks\Helper;
use ABlocks\Classes\{ Sanitizer, EmailTemplate as EmailTemplateModel };
use Exception;
class EmailTemplate extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'get_templates'      => [
				'callback' => [ $this, 'get_templates' ],
				'capability' => 'manage_options',
				'fields' => [
					'email_template_id' => 'string',
					'isSubscription' => 'boolean',
				]
			],
			'update_template'      => [
				'callback' => [ $this, 'update_template' ],
				'capability' => 'manage_options',
				'fields' => [
					'from' => 'string',
					'email_template_id' => 'string',
					'slug'     => 'string',
					'from_name' => 'string',
					'to' => 'string',
					'subject' => 'string',
					'body' => 'post',
					'reply_to' => 'string',
					'cc' => 'string',
					'bcc' => 'string',
					'format' => 'string',
					'status' => 'boolean',
				]
			],
			'delete_template'      => [
				'callback' => [ $this, 'delete_template' ],
				'capability' => 'manage_options',
				'fields' => [
					'email_template_id' => 'string',
					'slug'     => 'string',
				]
			],
			'remove_templates'      => [
				'callback' => [ $this, 'remove_templates' ],
				'capability' => 'manage_options',
				'fields' => [
					'email_template_id' => 'string',
				]
			],
		];
	}

	public function get_templates( array $payload ) : void {
		$email_template_id = $payload['email_template_id'] ?? '';
		$isSubscription = boolval( $payload['isSubscription'] ?? false );
		try {
			wp_send_json_success(
				EmailTemplateModel::ins( $email_template_id )
					->get_templates( $isSubscription )
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				$e->getMessage()
			);
		}
	}

	public function update_template( array $payload ) : void {
		$email_template_id = $payload['email_template_id'] ?? '';
		$slug     = $payload['slug'] ?? '';
		unset( $payload['email_template_id'], $payload['slug'] );
		try {
			wp_send_json_success(
				EmailTemplateModel::ins( $email_template_id )
					->update_template( $slug, $payload )
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				$e->getMessage()
			);
		}
	}

	public function delete_template( array $payload ) : void {
		$email_template_id = $payload['email_template_id'] ?? '';
		$slug     = $payload['slug'] ?? '';
		try {
			wp_send_json_success(
				EmailTemplateModel::ins( $email_template_id )
					->delete_template( $slug )
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				$e->getMessage()
			);
		}
	}

	public function remove_templates( array $payload ) : void {
		$email_template_id = $payload['email_template_id'] ?? '';
		delete_option( $email_template_id );
		wp_send_json_success( [
			'message' => __( 'Templates deleted.', 'ablocks' )
		] );
	}
}
