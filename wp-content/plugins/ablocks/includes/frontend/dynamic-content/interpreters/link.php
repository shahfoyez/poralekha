<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters;

use ABlocks\Helper;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Link extends Abstracts\Interpreter {
	protected int $post_id;
	protected string $field;

	public function __construct( string $name, array $setting, bool $is_richtext = false ) {
		parent::__construct( $name, $setting, $is_richtext );
		$this->field   = strval( $this->setting[0] ?? '' );
		$this->post_id = get_the_ID();
	}
	public function content() : string {
		switch ( $this->field ) {
			case 'internal-url-content':
				$this->content = get_permalink( $this->setting[2] );
				break;

			case 'internal-url-taxonomy':
				$this->content = get_term_link( (int) $this->setting[2] );
				break;

			case 'internal-url-media':
				$this->content = get_attachment_link( $this->setting[2] );
				break;

			case 'internal-url-author':
				$this->content = get_author_posts_url( $this->setting[2] );
				break;

			case 'post-url':
				$this->content = get_permalink( $this->post_id );
				break;

			case 'site-url':
				$this->content = get_site_url();
				break;

			case 'author-url':
				$this->content = get_author_posts_url( get_the_author_meta( 'ID' ) );
				break;

			case 'comments-url':
				$this->content = get_comments_link( $this->post_id );
				break;

			case 'featured-image-file-url':
				$this->content = get_the_post_thumbnail_url( $this->post_id, 'full' );
				break;

			case 'featured-image-attachment-url':
				$attachment_id = get_post_meta( $this->post_id, '_thumbnail_id', true );
				$this->content = $attachment_id ? get_permalink( $attachment_id ) : '';
				break;
		}//end switch
		return $this->content;
	}
}
