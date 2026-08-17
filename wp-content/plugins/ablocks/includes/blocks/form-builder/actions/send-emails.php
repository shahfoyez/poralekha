<?php
namespace ABlocks\Blocks\FormBuilder\Actions;

use ABlocks\Blocks\FormBuilder\Actions\Interfaces\FormSubmissionAction;
use ABlocks\Blocks\FormBuilder\ValidateFormData;
use ABlocks\Blocks\FormBuilder\EmailVerification;
use Exception;
use ABlocks\Classes\EmailTemplate;
use ABlocks\Blocks\FormBuilder\Query;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Send email to admin
 */
final class SendEmails implements FormSubmissionAction {

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

	private array $lists;

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

		$this->lists = EmailTemplate::ins(
			$this->validateformdata
					->get_block_data()['parentAttributes']['email_template_id'] ?? ''
		)
			->get_templates(
				'subscription' === $this->validateformdata->form_info['info']['type']
			);
	}

	/**
	 * Send email action
	 *
	 * @return void
	 */
	public function action() {
		if (
			$this->validateformdata->form_info['info']['type'] === 'subscription' &&
			Query::is_email_exists( $this->validateformdata->form_info['info']['email'], 'subscription' )
		) {
			return;
		}

		// wp_send_json($this->lists);
		foreach ( $this->lists ?? [] as $slug => $mail_templ ) {
			if (
				! $mail_templ['status'] ||
				empty( $mail_templ['to'] )
			) {
				continue;
			}
			$this->send_mail( $mail_templ );
		}

	}

	/**
	 * Send email to admin from contact form
	 *
	 * @param string $num
	 * @return void
	 */
	private function send_mail( array $config ): void {
		$this->user_email = $this->validateformdata->apply_vars( $this->user_email );

		$to_email = $this->validateformdata->apply_vars( sanitize_text_field( $config['to'] ) );

		$subject = $this->validateformdata->apply_vars( sanitize_text_field( $config['subject'] ?? '' ) );

		$type    = strtolower( sanitize_text_field( $config['format'] ?? 'html' ) );

		$message = $this->validateformdata->apply_vars( wp_kses_post( $config['body'] ?? '' ) );
		// check {all-fields} exists or not
		$message = empty( $message ) ? '{all-fields}' : $message;
		if ( preg_match( '|\{all\-fields\}|im', $message ) ) {
			// if exist then replace this text to data table
			$message = str_replace( '{all-fields}', $this->get_data_as_table_format( $type ), $message );
		}

		$headers = [];

		$from_email = $this->validateformdata->apply_vars( sanitize_text_field( $config['from'] ?? $this->admin_email ) );
		$from_name  = $this->validateformdata->apply_vars( sanitize_text_field( $config['from_name'] ?? '' ) );

		$reply_to   = $this->validateformdata->apply_vars( sanitize_text_field( $config['reply_to'] ?? '' ) );
		$cc        = $this->validateformdata->apply_vars( sanitize_text_field( $config['cc'] ?? '' ) );
		$bcc       = $this->validateformdata->apply_vars( sanitize_text_field( $config['bcc'] ?? '' ) );

		if ( $from_email ) {
			$headers[] = $from_name ? 'From: ' . $from_name . ' <' . $from_email . '>' : 'From: ' . $from_email;
		}

		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		if ( $cc ) {
			$headers[] = 'CC: ' . implode( ',', array_unique(
				preg_split(
					'|[,\s]|', $this->validateformdata->apply_vars( strval( $cc ) )
				)
			) );
		}
		if ( $bcc ) {
			$headers[] = 'BCC: ' . implode( ',', array_unique(
				preg_split(
					'|[,\s]|', $this->validateformdata->apply_vars( strval( $bcc ) )
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
