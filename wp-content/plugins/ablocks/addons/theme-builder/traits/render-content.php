<?php
namespace ABlocksThemeBuilder\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait RenderContent {
	public function get_rendered_block_content_by_id( $post_id ) {
		$post = get_post( $post_id );

		if ( $post && $post->post_status === 'publish' ) {
			return apply_filters( 'the_content', $post->post_content );
		}

		return '';
	}
}
