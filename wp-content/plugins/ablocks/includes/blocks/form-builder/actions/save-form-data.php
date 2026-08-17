<?php
namespace ABlocks\Blocks\FormBuilder\Actions;

use ABlocks\Blocks\FormBuilder\Actions\Interfaces\FormSubmissionAction;
use ABlocks\Blocks\FormBuilder\ValidateFormData;
use ABlocks\Blocks\FormBuilder\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Save Form data to db
 */
final class SaveFormData implements FormSubmissionAction {

	/** @var $validateformdata */
	private ValidateFormData $validateformdata;

	/**
	 * Contructor
	 *
	 * @param ValidateFormData $obj
	 */
	public function __construct( ValidateFormData $obj ) {
		$this->validateformdata = $obj;
	}

	public function action() {
		if (
			$this->validateformdata->form_info['info']['type'] === 'contact' &&
			! in_array( 'submission', $this->validateformdata->form_info['info']['actions'], true )
		) {
			return;
		}

		// detemine this request coming from subscription form & email is not exists
		if (
			$this->validateformdata->form_info['info']['type'] === 'subscription' &&
			Query::is_email_exists( $this->validateformdata->form_info['info']['email'], 'subscription' )
		) {
			$this->validateformdata->set_error_message( __( 'Already subscribed.', 'ablocks' ) );
			$this->validateformdata->state_data['dont_send_subs_email'] = true;
			return;
		}

		/**
		 * By default both userIp, userAgent are in submissionMetaData, but here it is not available, an empty array.
		 * when a single element is selected, the single element became available
		 * but when both are selected it is not available
		 */
		$settings = $this->validateformdata->form_info['info']['config']['submissionMetaData'] ?? [];

		$ip = '';
		if ( in_array( 'userIp', $settings, true ) ) {
			$ip = wp_unslash(
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 
				$_SERVER['HTTP_CF_CONNECTING_IP'] ??
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 
				$_SERVER['HTTP_CLIENT_IP'] ??
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 
				$_SERVER['HTTP_X_FORWARDED_FOR'] ??
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 
				$_SERVER['REMOTE_ADDR'] ?? ''
			);
			$ip = sanitize_text_field( $ip );
		}

		$user_agents = '';
		if ( in_array( 'userAgent', $settings, true ) ) {
			$user_agents = sanitize_text_field(
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 
				wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' )
			);
		}

		// insert data to entry
		$id = Query::add_new_entry(
			$this->validateformdata->form_info['info']['type'],
			$this->validateformdata->form_info['info']['postId'],
			$this->validateformdata->form_info['info']['email'],
			$ip,
			$user_agents,
			$this->validateformdata->form_info['data'],
			$this->validateformdata->form_info['info']['config']['block_id'],
			boolval( $this->validateformdata->form_info['info']['config']['emailVerification'] ?? false )
		);

		if ( $id ) {
			$this->validateformdata->state_data['submission_id'] = $id;
			$this->validateformdata->set_message( __( 'Success!', 'ablocks' ) );

			if (
				'subscription' === $this->validateformdata->form_info['info']['type'] &&
				isset($this->validateformdata->form_info['info']['config']['subscriberToUser']) &&
				(bool) $this->validateformdata->form_info['info']['config']['subscriberToUser'] === true
			) {
				$this->create_user(
					$this->validateformdata->form_info['data']
				);
			}
			return;
		}
		$this->validateformdata->set_error_message( __( 'Failed to save data', 'ablocks' ) );
	}

	function create_user( array $data ) : ?int {
		if ( ! isset( $data['email']['value'] ) || ! is_email( $data['email']['value'] ) ) {
			return null;
		}
		if ( email_exists( $data['email']['value'] ) ) {
			return null;
		}

		$username = sanitize_user( $data['email']['value'] );

		$user_data = [
			'user_login' => $username,
			'user_email' => $data['email']['value'],
			'user_pass'  => isset( $data['password']['value'] ) ? $data['password']['value'] : wp_generate_password(),
			'role'       => 'subscriber',
		];

		if ( isset( $data['first_name']['value'] ) ) {
			$user_data['first_name'] = sanitize_text_field( $data['first_name']['value'] );
		}

		if ( isset( $data['last_name']['value'] ) ) {
			$user_data['last_name'] = sanitize_text_field( $data['last_name']['value'] );
		}

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			return null;
		}

		$user = get_user_by( 'id', $user_id );
		$password = $user_data['user_pass'];

		$subject = 'Your New Account Credentials';
		$message = sprintf( 'Hello %s,

			Your account has been created successfully. Below are your login details:

			Username: %s
			Password: %s

			You can log in to your account here: %s

			If you did not create this account, please contact us immediately.',
			$user->first_name ? $user->first_name : 'User',
			$user->user_login,
			$password,
			wp_login_url()
		);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];

		$mail_sent = wp_mail( $user->user_email, $subject, nl2br( $message ), $headers );
		return $user_id;
	}

}
