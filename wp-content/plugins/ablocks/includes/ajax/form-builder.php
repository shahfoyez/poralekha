<?php

namespace ABlocks\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AbstractAjaxHandler;
use ABlocks\Helper;
use ABlocks\Classes\Sanitizer;

use ABlocks\Blocks\FormBuilder\ValidateFormData;
use ABlocks\Blocks\FormBuilder\Actions\SaveFormData;
use ABlocks\Blocks\FormBuilder\Actions\SendEmail;
use ABlocks\Blocks\FormBuilder\Actions\SendEmails;
use ABlocks\Blocks\FormBuilder\Actions\Subscribe;

use ABlocks\Blocks\FormBuilder\Subscribe\Mailchimp;
use Exception;

class FormBuilder extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = array(
			'form_builder_action_setting_data'      => array(
				'callback' => array( $this, 'action_setting_data' ),
				'capability' => 'edit_posts',
				'fields' => array(
					'type' => 'string',
					'api'  => 'string',
					'list_id' => 'string',
				)
			),
			'registration_form_setting_data'      => array(
				'callback' => array( $this, 'registration_form_setting_data' ),
				'capability' => 'edit_posts'
			),
		);

	}

	public function prepare_res( array $data, array $block_data, string $redirect_url ) : array {
		$formType = $block_data['parentAttributes']['formType'] ?? '';

		// confirmationType based on formType
		if ( $formType == 'login' ) {
			$confirmationType = ( $block_data['parentAttributes']['loginRedirect'] ?? false ) ? 'redirect' : 'success';
		} elseif ( $formType == 'registration' ) {
			$confirmationType = ( $block_data['parentAttributes']['registerRedirect'] ?? false ) ? 'redirect' : 'success';
		} else {
			$confirmationType = $block_data['parentAttributes']['confirmationType'] ?? 'success';
		}

		return array_merge(
			$data,
			[
				'afterFormSubmission' => $block_data['parentAttributes']['afterFormSubmission'] ?? 'reset',
				'confirmationType' => $confirmationType,
				'confirmationNotice' => $block_data['parentAttributes']['confirmationNotice'] ?? $data['message'] ?? __( 'Form successfully submitted!', 'ablocks' ),
				'redirect_url' => esc_url( $redirect_url ),
				'no_follow' => $block_data['parentAttributes']['link']['noFollow'] ?? '',
				'link_target' => $block_data['parentAttributes']['link']['linkTarget'] ?? '',
				'formType' => $formType,
			]
		);
	}



	public function registration_form_setting_data( $payload ) {
		$roles = [
			'default' => __( 'Default', 'ablocks' )
		];

		if ( current_user_can( 'manage_options' ) ) {
			foreach ( wp_roles()->roles ?? [] as $slug => ['name' => $name] ) {
				$roles[ $slug ] = $name;
			}
		}
		wp_send_json_success( $roles );
	}

	private function validate_registration_data( $payload ) {
		if ( empty( $payload['username'] ) || ! validate_username( $payload['username'] ) ) {
			return 'Invalid username.';
		} elseif ( username_exists( $payload['username'] ) ) {
			return 'Username already exists.';
		}

		if ( empty( $payload['email'] ) || ! is_email( $payload['email'] ) ) {
			return 'Invalid email address.';
		} elseif ( email_exists( $payload['email'] ) ) {
			return 'Email already exists.';
		}

		if ( empty( $payload['password'] ) || strlen( $payload['password'] ) < 6 ) {
			return 'Password must be at least 6 characters.';
		} elseif ( $payload['password'] !== $payload['confirm_password'] ) {
			return 'Passwords do not match.';
		}

		return null;
	}

	private function get_custom_fields( $form_data ) {
		$reserved_keys = [ 'username', 'email', 'password', 'confirm_password', 'current_post_id', 'block_id', 'security', 'action' ];
		return array_diff_key( $form_data, array_flip( $reserved_keys ) );
	}

	public function action_setting_data( $payload ) {
		$type         = $payload['type'] ?? '';
		$settings_key = $type . '_api_key';
		$api          = $payload['api'] ?? $GLOBALS['ablocks_settings']->{$settings_key} ?? '';
		$list_id      = $payload['list_id'] ?? '';

		$api_key      = $api;

		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'api key is required', 'ablocks' ) ] );
		}

		$action_config = apply_filters('ablocks/form_builder/action_config', [
			'mailchimp' => function( $api_key ) use ( $list_id ) {
				if ( ! class_exists( Mailchimp::class ) ) {
					return;
				}

				if ( empty( $list_id ) ) {
					$data = ( new Mailchimp(
						$api_key,
						'',
						''
					) )
					->get_lists();
					wp_send_json_success( $data );
				} else {

					$data = ( new Mailchimp(
						$api_key,
						'',
						''
					) )
					->get_groups( $list_id );
				}

				wp_send_json_success( $data );
			}
		]);

		if ( ! array_key_exists( $type, $action_config ) ) {
			wp_send_json_error( [ 'message' => __( 'Not a valid type', 'ablocks' ) ] );
		}

		try {
			if (
				array_key_exists( $type, $action_config ) &&
				is_callable( $action_config[ $type ] )
			) {
				$action_config[ $type ]( $api_key, $payload );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => esc_html( $e->getMessage() ) ], 400 );
		}

		wp_send_json_error( [ 'message' => __( 'Unspecified service type', 'ablocks' ) ], 400 );
	}
}
