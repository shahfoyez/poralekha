<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use Exception;
class EmailTemplate {
	public string $email_template_id;
	public array $templates;

	public function __construct( string $email_template_id ) {
		$this->email_template_id  = $email_template_id;
		$this->templates = $this->get_templates();
	}

	public static function ins( string $email_template_id ) : self {
		return new self( $email_template_id );
	}

	public function get_option_name() : string {
		return $this->email_template_id;
	}

	public function get_templates( bool $status = false ) : array {
		return get_option(
			$this->get_option_name(),
			[
				'default' => [
					'from'      => '{admin_email}',
					'from_name' => '{site_name}',
					'to'        => ! $status ? '{admin_email}' : \get_option( 'admin_email' ),
					'subject'   => 'New message',
					'body'      => 'Tables: {all-fields}',
					'reply_to'   => '',
					'cc'         => '',
					'bcc'        => '',
					'format'     => 'html',
					'status'     => ! $status,
				]
			]
		);
	}

	public function update_template( string $slug, array $data ) : bool {
		$data = wp_parse_args( $data, [
			'from'      => '',
			'from_name' => '',
			'to'        => '',
			'subject'   => '',
			'body'      => '',
			'reply_to'   => '',
			'cc'         => '',
			'bcc'        => '',
			'format'     => 'html',
			'status'     => false,
		] );

		if (
			empty( $slug ) ||
			empty( $data['to'] ) ||
			empty( $data['subject'] ) ||
			empty( $data['body'] )
		) {
			throw new Exception(
				esc_html__( 'Slug/Subject/Body/To is required', 'ablocks' )
			);
		}

		$this->templates[ $slug ] = $data;

		update_option(
			$this->get_option_name(),
			$this->templates,
			false
		);

		return true;
	}

	public function delete_template( string $slug ) : bool {

		if (
			! array_key_exists( $slug, $this->templates )
		) {
			throw new Exception(
				esc_html__( 'Template does not exist', 'ablocks' )
			);
		}

		unset( $this->templates[ $slug ] );

		update_option(
			$this->get_option_name(),
			$this->templates,
			false
		);

		return true;
	}
}
