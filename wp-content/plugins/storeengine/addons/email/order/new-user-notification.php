<?php

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NewUserNotification {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	public function __construct() {
		$this->__EmailConstruct( 'new_user_notification' );
		add_action( 'storeengine/checkout/customer_created', [ $this, 'send_mail' ], 10, 2 );
	}

	public function send_mail( int $user_id, array $userdata ) {
		$settings = $this->get_settings( 'customer' );

		if ( ! is_array( $settings ) || ! $settings['is_enable'] ) {
			return;
		}

		$subject                = $this->get_email_subject( $user_id );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/new-user-notification-customer.php' );
		$body                   = $this->get_email_body( $userdata, $user_id, $body );

		$this->mail_send( get_userdata( $user_id )->user_email, $subject, $body, $headers, [
			'user_id' => $user_id,
		] );
	}

	private function get_email_subject( int $user_id ) {
		$user      = get_userdata( $user_id );
		$site_url  = get_bloginfo( 'url' );
		$site_name = get_bloginfo( 'name' );

		$replacements = [
			'{user_display_name}' => esc_html( $user->display_name ),
			'{site_title}'        => esc_html( $site_name ),
			'{site_url}'          => esc_html( $site_url ),
			'{store_name}'        => esc_html( Helper::get_settings( 'store_name' ) ),
		];

		$replacements = apply_filters( 'storeengine/email/' . $this->get_hook_name( 'subject-replacements' ), $replacements, $user );
		$settings     = $this->get_settings( 'customer' );

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email_subject'] );
	}

	private function get_email_body( array $userdata, int $user_id, string $body ) {
		$user      = get_userdata( $user_id );
		$login_url = storeengine_login_url( Helper::get_dashboard_url() );

		$replacements = [
			'{user_display_name}' => $user->display_name,
			'{user_email}'        => $user->user_email,
			'{user_password}'     => $userdata['user_pass'],
			'{user_first_name}'   => $user->first_name,
			'{user_last_name}'    => $user->last_name,
			'{store_name}'        => Helper::get_settings( 'store_name' ),
			'{sign_in_url}'       => "<a href='" . $login_url . "' target='_blank'>" . $login_url . '</a>',
		];

		$replacements = apply_filters( 'storeengine/email/' . $this->get_hook_name( 'content-replacements' ), $replacements, $user );

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $body );
	}

}
