<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters;

use ABlocks\Helper;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Image extends Abstracts\Interpreter {
	protected string $field;

	public function __construct( string $name, array $setting, bool $is_richtext = false ) {
		parent::__construct( $name, $setting, $is_richtext );
		$this->field   = strval( $this->setting[0] ?? '' );
	}
	public function content() : string {

		switch ( $this->field ) {
			case 'featured-image':
				$this->content = get_the_post_thumbnail_url( get_the_ID(), 'full' );
				break;

			case 'site-logo':
				$this->content = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' )[0] ?? '';
				break;

			case 'author-profile-picture':
				$author_id = get_post_field( 'post_author', get_the_ID() );
				$this->content = get_avatar_url( $author_id );
				break;

			case 'user-profile-picture':
				$this->content = is_user_logged_in() ? get_avatar_url( get_current_user_id() ) : '';
				break;
		}
		return $this->content;
	}
}
