<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters;

use ABlocks\Helper;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class UserInfo extends Abstracts\Interpreter {
	protected string $field;
	protected string $pre;
	protected string $post;
	protected string $default;

	public function __construct( string $name, array $setting, bool $is_richtext = false ) {
		parent::__construct( $name, $setting, $is_richtext );
		$this->field     = strval( $this->setting[0] ?? '' );
		$this->pre     = strval( $this->setting[3] ?? '' );
		$this->post    = strval( $this->setting[4] ?? '' );
		$this->default = strval( $this->setting[5] ?? '' );
	}
	public function content() : string {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$current_user = wp_get_current_user();
		switch ( $this->field ) {
			case 'id':
					$this->content = $current_user->ID;
				break;
			case 'display-name':
					$this->content = $current_user->display_name;
				break;
			case 'username':
					$this->content = $current_user->user_login;
				break;
			case 'first-name':
					$this->content = $current_user->first_name;
				break;
			case 'last-name':
					$this->content = $current_user->last_name;
				break;
			case 'bio':
					$this->content = $current_user->description;
				break;
			case 'email':
					$this->content = $current_user->user_email;
				break;
			case 'website':
					$this->content = $current_user->user_url;
				break;
			case 'user-meta':
					$this->content = get_user_meta( $current_user->ID, $this->setting[1] ?? '', true );
				break;
		}//end switch
		return $this->pre . ( $this->content === '' ? $this->default : $this->content ) . $this->post;
	}
}
