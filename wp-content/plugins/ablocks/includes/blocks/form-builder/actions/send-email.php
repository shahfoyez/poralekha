<?php
namespace ABlocks\Blocks\FormBuilder\Actions;

use ABlocks\Blocks\FormBuilder\Actions\Interfaces\FormSubmissionAction;
use ABlocks\Blocks\FormBuilder\ValidateFormData;
use ABlocks\Blocks\FormBuilder\EmailVerification;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Send email to admin
 */
final class SendEmail implements FormSubmissionAction {

	/** @var $validateformdata */
	private ValidateFormData $validateformdata;

	/** @var $admin_email */
	private string $admin_email;

	/** @var $user_email */
	private string $user_email;

	/** @var $form_type */
	private string $form_type;

	/** @var $config */
	private array $config;

	/**
	 * Contructor
	 *
	 * @param ValidateFormData $obj
	 */
	public function __construct( ValidateFormData $obj ) {
		$this->validateformdata = $obj;

		$this->admin_email = \get_option( 'admin_email' );

		$this->user_email = $this->validateformdata->form_info['info']['email'];
		$this->form_type  = $this->validateformdata->form_info['info']['type'];
		$this->config = $this->validateformdata->form_info['info']['config'];
	}

	/**
	 * Send email action
	 *
	 * @return void
	 */
	public function action() {

			// wp_send_json($this->validateformdata->form_info['info']);
		// if already subscribe, dont send any email
		if (
			$this->validateformdata->form_info['info']['type'] === 'subscription' &&
			array_key_exists( 'dont_send_subs_email', $this->validateformdata->state_data )
		) {
			return;
		}

		// send email verification email to user
		$this->send_verification_email();

		if ( $this->form_type === 'subscription' &&
			( $this->validateformdata->form_info['info']['config']['adminNotify'] ?? true )
		) {
			$this->subscription_form_send_email();
		}

		// $this->contact_form_send_email();
		// $this->contact_form_send_email( 'Two' );
	}

	private function send_verification_email() {
		$id = $this->validateformdata->state_data['submission_id'] ?? null;
		if (
			! boolval( $this->config['emailVerification'] ?? false ) ||
			array_key_exists( 'dont_send_subs_email', $this->validateformdata->state_data ) ||
			empty( $this->user_email ) ||
			empty( $id )
		) {
			return;
		}

		try {

			$verification_obj = new EmailVerification( $id, 'id' );
			$verification_url = $verification_obj->get_signed_url();

			$subject = __( 'Email Verification', 'ablocks' );

			ob_start();
				\ABlocks\Helper::get_template('email/email-verify.php', [
					'email' => $this->user_email,
					'url' => $verification_url,
				]);

			$data = ob_get_clean();
			$this->send_email( $subject, $data, $this->user_email );
		} catch ( Exception $e ) {
			wp_send_json_error( [ $e->getMessage() ] );
		}

	}

	/**
	 * Send email to admin from contact form
	 *
	 * @param string $num
	 * @return void
	 */
	private function contact_form_send_email( string $num = 'One' ): void {
		$this->user_email = $this->apply_vars( $this->user_email );
		// check email notification is enabled or not
		$nums = [
			'One' => '',
			'Two' => 2,
		];

		if (
			! array_key_exists( $num, $nums ) ||
			! in_array( 'email' . $nums[ $num ], $this->validateformdata->form_info['info']['actions'], true )
		) {
			return;
		}
		$config = $this->validateformdata->form_info['info']['config'];

		$to_email = $this->apply_vars( sanitize_text_field( $config[ 'email' . $num . 'To' ] ?? $this->admin_email ) );

		$subject = $this->apply_vars( sanitize_text_field( $config[ 'email' . $num . 'Subject' ] ?? '' ) );
		// translators: %s is email
		$subject = $subject ? $subject : ( $this->user_email ? sprintf( __( 'You have a message from %s', 'ablocks' ), $this->user_email ) : __( 'You have a message', 'ablocks' ) );

		$type    = strtolower( sanitize_text_field( $config[ 'email' . $num . 'Type' ] ?? 'html' ) );
		$message = $this->apply_vars( sanitize_text_field( $config[ 'email' . $num . 'Message' ] ?? '' ) );
		// check {all-fields} exists or not
		$message = empty( $message ) ? '{all-fields}' : $message;
		if ( preg_match( '|\{all\-fields\}|im', $message ) ) {
			// if exist then replace this text to data table
			$message = str_replace( '{all-fields}', $this->get_data_as_table_format( $type ), $message );
		}
		// elseif ( $message ) {
		// if message is defined by user and {all-fields} is not available
		// then insert datatable at the end of msg
		// $message .= $this->get_data_as_table_format( $type );
		// } else {
		// if msg is not defined by user, the add only datatable
		// $message = $this->get_data_as_table_format( $type );
		// }
		$headers = [];

		$from_email = sanitize_text_field( $config[ 'email' . $num . 'FormEmail' ] ?? '' );
		$from_name  = sanitize_text_field( $config[ 'email' . $num . 'FormName' ] ?? '' );

		$reply_to   = sanitize_text_field( $config[ 'email' . $num . 'ReplyTo' ] ?? '' );
		$cc        = sanitize_text_field( $config[ 'email' . $num . 'Cc' ] ?? '' );
		$bcc       = sanitize_text_field( $config[ 'email' . $num . 'Bcc' ] ?? '' );

		if ( $from_email ) {
			$headers[] = $from_name ? 'From: ' . $from_name . ' <' . $from_email . '>' : 'From: ' . $from_email;
		}

		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		if ( $cc ) {
			$headers[] = 'CC: ' . implode( ',', array_unique(
				preg_split(
					'|[,\s]|', $this->apply_vars( strval( $cc ) )
				)
			) );
		}
		if ( $bcc ) {
			$headers[] = 'BCC: ' . implode( ',', array_unique(
				preg_split(
					'|[,\s]|', $this->apply_vars( strval( $bcc ) )
				)
			) );
		}

		if ( $type === 'plain' ) {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		}
		$template = $type === 'plain' ? 'email/plain-text/contact-email.php' : 'email/contact-email.php';

		ob_start();
			\ABlocks\Helper::get_template($template, [
				'email' => $this->user_email,
				'message' => $message,
			]);

		$data = ob_get_clean();
		foreach ( array_unique( preg_split( '|[,\s]|', strval( $to_email ) ) ) as $email ) {
			$email = trim( $email );
			if ( ! empty( $email ) ) {
				$this->send_email( $subject, $data, $email, $headers );
			}
		}

	}

	private function apply_vars( ?string $msg ): ?string {
		$admin_email = get_option( 'admin_email' );
		$current_user = wp_get_current_user();
		$current_user_email = $current_user->user_email;

		$fields = array_merge(
			$this->validateformdata->form_info['data'] ?? [],
			[
				'admin_email'  => [ 'value' => $admin_email ],
				'user_email'   => [ 'value' => $current_user_email ]
			]
		);
		// wp_send_json($fields);
		foreach ( $fields as $key => [ 'value' => $val ] ) {
			if ( is_array( $val ) ) {
				$val = implode( ', ', $val );
			}
			$msg = str_replace( "{{$key}}", $val, $msg );
		}
		return $msg;
	}

	/**
	 * Send email to admin from subscription form
	 *
	 * @return void
	 */
	private function subscription_form_send_email(): void {

		// detemine this request coming from subscription form & email is already exists
		if (
			$this->validateformdata->form_info['info']['type'] !== 'subscription' ||
			array_key_exists( 'dont_send_subs_email', $this->validateformdata->state_data )
		) {
			return;
		}

		// translators: %s is email
		$subject = sprintf( __( 'You have a new Subscriber  [%s]', 'ablocks' ), $this->user_email );

		ob_start();
			\ABlocks\Helper::get_template('email/new-subscriber.php', [
				'email' => $this->user_email,
			]);

		$data = ob_get_clean();
		$this->send_email( $subject, $data, $this->admin_email );
	}

	/**
	 * Send email
	 *
	 * @param string $subject
	 * @param string $data
	 * @param string $to
	 * @param array  $headers
	 * @return void
	 */
	private function send_email( string $subject, string $data, string $to, array $headers = [] ): void {

		$headers = array_merge( [ 'Content-Type: text/html; charset=UTF-8' ], $headers );
		if ( wp_mail( $to, $subject, $data, $headers ) ) {
			$this->validateformdata->set_message( __( 'Success!', 'ablocks' ) );
			return;
		}

		$this->validateformdata->set_error_message( __( 'Failed to send email', 'ablocks' ) );
	}
	/**
	 * Convert data as table
	 *
	 * @param string $type
	 * @return string $type
	 */
	private function get_data_as_table_format( string $type ): string {
		$template = $type === 'plain' ? 'email/plain-text/email-table.php' : 'email/email-table.php';
		ob_start();
			\ABlocks\Helper::get_template($template, [
				'data' => $this->validateformdata->form_info['data']
			]);

		$data = ob_get_clean();
		return $data;
	}
}
