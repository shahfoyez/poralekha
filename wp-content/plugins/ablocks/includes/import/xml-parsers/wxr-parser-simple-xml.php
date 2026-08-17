<?php

namespace ABlocks\import\XmlParsers;

use SimpleXMLElement;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WXR Parser that makes use of the SimpleXML PHP extension.
 */
class WXR_Parser_SimpleXML {
	public function parse( $file ) {
		$authors    = array();
		$posts      = array();
		$categories = array();
		$tags       = array();
		$terms      = array();

		$internal_errors = libxml_use_internal_errors( true );

		$dom       = new \DOMDocument();
		$old_value = null;
		if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
			// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
			$old_value = libxml_disable_entity_loader( true );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$success = $dom->loadXML( file_get_contents( $file ) );
		if ( ! is_null( $old_value ) ) {
			// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
			libxml_disable_entity_loader( $old_value );
		}

		if ( ! $success || isset( $dom->doctype ) ) {
			return new WP_Error( 'SimpleXML_parse_error', __( 'There was an error when reading this WXR file', 'ablocks' ), libxml_get_errors() );
		}

		$xml = simplexml_import_dom( $dom );
		unset( $dom );

		// halt if loading produces an error
		if ( ! $xml ) {
			return new WP_Error( 'SimpleXML_parse_error', __( 'There was an error when reading this WXR file', 'ablocks' ), libxml_get_errors() );
		}

		$wxr_version = $xml->xpath( '/rss/channel/wp:wxr_version' );
		if ( ! $wxr_version ) {
			return new WP_Error( 'WXR_parse_error', __( 'This does not appear to be a WXR file, missing/invalid WXR version number', 'ablocks' ) );
		}

		$wxr_version = (string) trim( $wxr_version[0] );
		// confirm that we are dealing with the correct file format
		if ( ! preg_match( '/^\d+\.\d+$/', $wxr_version ) ) {
			return new WP_Error( 'WXR_parse_error', __( 'This does not appear to be a WXR file, missing/invalid WXR version number', 'ablocks' ) );
		}

		$base_url = $xml->xpath( '/rss/channel/wp:base_site_url' );
		$base_url = trim( isset( $base_url[0] ) ? $base_url[0] : '' );

		$base_blog_url = $xml->xpath( '/rss/channel/wp:base_blog_url' );
		if ( $base_blog_url ) {
			$base_blog_url = trim( $base_blog_url[0] );
		} else {
			$base_blog_url = $base_url;
		}

		$show_on_front = $xml->xpath( '/rss/channel/ablocks_options/show_on_front' );
		if ( $show_on_front ) {
			$show_on_front = trim( $show_on_front[0] );
		}

		$page_on_front = $xml->xpath( '/rss/channel/ablocks_options/page_on_front' );
		if ( $page_on_front ) {
			$page_on_front = (int) $page_on_front[0];
		}

		$page_for_posts = $xml->xpath( '/rss/channel/ablocks_options/page_for_posts' );
		if ( $page_for_posts ) {
			$page_for_posts = (int) $page_for_posts[0];
		}

		$namespaces = $xml->getDocNamespaces();
		if ( ! isset( $namespaces['wp'] ) ) {
			$namespaces['wp'] = 'http://wordpress.org/export/1.1/';
		}
		if ( ! isset( $namespaces['excerpt'] ) ) {
			$namespaces['excerpt'] = 'http://wordpress.org/export/1.1/excerpt/';
		}

		// grab authors
		foreach ( $xml->xpath( '/rss/channel/wp:author' ) as $author_arr ) {
			$a                 = $author_arr->children( $namespaces['wp'] );
			$login             = (string) $a->author_login;
			$authors[ $login ] = array(
				'author_id'           => (int) $a->author_id,
				'author_login'        => $login,
				'author_email'        => (string) $a->author_email,
				'author_display_name' => (string) $a->author_display_name,
				'author_first_name'   => (string) $a->author_first_name,
				'author_last_name'    => (string) $a->author_last_name,
			);
		}

		// grab cats, tags and terms
		foreach ( $xml->xpath( '/rss/channel/wp:category' ) as $term_arr ) {
			$t        = $term_arr->children( $namespaces['wp'] );
			$category = array(
				'term_id'              => (int) $t->term_id,
				'category_nicename'    => (string) $t->category_nicename,
				'category_parent'      => (string) $t->category_parent,
				'cat_name'             => (string) $t->cat_name,
				'category_description' => (string) $t->category_description,
			);

			foreach ( $t->termmeta as $meta ) {
				$category['termmeta'][] = array(
					'key'   => (string) $meta->meta_key,
					'value' => (string) $meta->meta_value,
				);
			}

			$categories[] = $category;
		}

		foreach ( $xml->xpath( '/rss/channel/wp:tag' ) as $term_arr ) {
			$t   = $term_arr->children( $namespaces['wp'] );
			$tag = array(
				'term_id'         => (int) $t->term_id,
				'tag_slug'        => (string) $t->tag_slug,
				'tag_name'        => (string) $t->tag_name,
				'tag_description' => (string) $t->tag_description,
			);

			foreach ( $t->termmeta as $meta ) {
				$tag['termmeta'][] = array(
					'key'   => (string) $meta->meta_key,
					'value' => (string) $meta->meta_value,
				);
			}

			$tags[] = $tag;
		}

		foreach ( $xml->xpath( '/rss/channel/wp:term' ) as $term_arr ) {
			$t    = $term_arr->children( $namespaces['wp'] );
			$term = array(
				'term_id'          => (int) $t->term_id,
				'term_taxonomy'    => (string) $t->term_taxonomy,
				'slug'             => (string) $t->term_slug,
				'term_parent'      => (string) $t->term_parent,
				'term_name'        => (string) $t->term_name,
				'term_description' => (string) $t->term_description,
			);

			foreach ( $t->termmeta as $meta ) {
				$term['termmeta'][] = array(
					'key'   => (string) $meta->meta_key,
					'value' => (string) $meta->meta_value,
				);
			}

			$terms[] = $term;
		}

		// grab posts
		foreach ( $xml->channel->item as $item ) {
			$post = array(
				'post_title' => (string) $item->title,
				'guid'       => (string) $item->guid,
			);

			$dc                  = $item->children( 'http://purl.org/dc/elements/1.1/' );
			$post['post_author'] = (string) $dc->creator;

			$wp      = $item->children( $namespaces['wp'] );
			$content = $item->children( 'http://purl.org/rss/1.0/modules/content/' );
			$excerpt = $item->children( $namespaces['excerpt'] );
			$content = (string) $content->encoded;
			if ( in_array( (string) $wp->post_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
				$content = preg_replace(
					'/\s*,?\s*"theme"\s*:\s*"[^"]+"/',
					'',
					$content
				);
			}
			$post['post_content'] = $content;
			$post['post_excerpt'] = (string) $excerpt->encoded;

			$post['post_id']        = (int) $wp->post_id;
			$post['post_date']      = (string) $wp->post_date;
			$post['post_date_gmt']  = (string) $wp->post_date_gmt;
			$post['comment_status'] = (string) $wp->comment_status;
			$post['ping_status']    = (string) $wp->ping_status;
			$post['post_name']      = (string) $wp->post_name;
			$post['status']         = (string) $wp->status;
			$post['post_parent']    = (int) $wp->post_parent;
			$post['menu_order']     = (int) $wp->menu_order;
			$post['post_type']      = (string) $wp->post_type;
			$post['post_password']  = (string) $wp->post_password;
			$post['is_sticky']      = (int) $wp->is_sticky;

			if ( isset( $wp->attachment_url ) ) {
				$post['attachment_url'] = (string) $wp->attachment_url;
			}

			foreach ( $item->category as $c ) {
				$att = $c->attributes();
				if ( isset( $att['nicename'] ) ) {
					$post['terms'][] = array(
						'name'   => (string) $c,
						'slug'   => (string) $att['nicename'],
						'domain' => (string) $att['domain'],
					);
				}
			}

			foreach ( $wp->postmeta as $meta ) {
				$post['postmeta'][] = array(
					'key'   => (string) $meta->meta_key,
					'value' => (string) $meta->meta_value,
				);
			}

			foreach ( $wp->comment as $comment ) {
				$meta = array();
				if ( isset( $comment->commentmeta ) ) {
					foreach ( $comment->commentmeta as $m ) {
						$meta[] = array(
							'key'   => (string) $m->meta_key,
							'value' => (string) $m->meta_value,
						);
					}
				}

				$post['comments'][] = array(
					'comment_id'           => (int) $comment->comment_id,
					'comment_author'       => (string) $comment->comment_author,
					'comment_author_email' => (string) $comment->comment_author_email,
					'comment_author_IP'    => (string) $comment->comment_author_IP,
					'comment_author_url'   => (string) $comment->comment_author_url,
					'comment_date'         => (string) $comment->comment_date,
					'comment_date_gmt'     => (string) $comment->comment_date_gmt,
					'comment_content'      => (string) $comment->comment_content,
					'comment_approved'     => (string) $comment->comment_approved,
					'comment_type'         => (string) $comment->comment_type,
					'comment_parent'       => (string) $comment->comment_parent,
					'comment_user_id'      => (int) $comment->comment_user_id,
					'commentmeta'          => $meta,
				);
			}//end foreach

			$posts[] = $post;
		}//end foreach

		return array(
			'authors'           => $authors,
			'posts'             => $posts,
			'categories'        => $categories,
			'tags'              => $tags,
			'terms'             => $terms,
			'base_url'          => $base_url,
			'base_blog_url'     => $base_blog_url,
			'version'           => $wxr_version,
			'show_on_front'     => $show_on_front,
			'page_on_front'     => $page_on_front,
			'page_for_posts'    => $page_for_posts,
			'storeengine_pages' => $this->get_storeengine_pages( $xml ),
			'academy_pages'     => $this->get_academy_pages( $xml ),
			'ablocks_pages'     => $this->get_ablocks_pages( $xml ),
			'ablocks_options'   => $this->get_ablocks_options( $xml ),
		);
	}

	private function get_storeengine_pages( SimpleXMLElement $xml ): array {
		$storeengine_pages = [
			'shop_page'                   => 0,
			'cart_page'                   => 0,
			'checkout_page'               => 0,
			'thankyou_page'               => 0,
			'dashboard_page'              => 0,
			'membership_pricing_page'     => 0,
			'affiliate_registration_page' => 0,
		];

		foreach ( array_keys( $storeengine_pages ) as $page_key ) {
			$value = $xml->xpath( '/rss/channel/ablocks_options/storeengine_' . $page_key );
			if ( $value ) {
				$storeengine_pages[ $page_key ] = (int) $value[0];
			}
		}

		return $storeengine_pages;
	}

	private function get_academy_pages( SimpleXMLElement $xml ): array {
		$academy_pages = [
			'frontend_dashboard_page'      => 0,
			'frontend_student_reg_page'    => 0,
			'password_reset_page'          => 0,
			'lessons_page'                 => 0,
			'course_page'                  => 0,
			'frontend_instructor_reg_page' => 0,
			'tutor_booking_page'           => 0,
		];

		foreach ( array_keys( $academy_pages ) as $page_key ) {
			$value = $xml->xpath( '/rss/channel/ablocks_options/academy_' . $page_key );
			if ( $value ) {
				$academy_pages[ $page_key ] = (int) $value[0];
			}
		}

		return $academy_pages;
	}

	private function get_ablocks_pages( SimpleXMLElement $xml ): array {
		$ablocks_pages = [
			'login_page'           => 0,
			'registration_page'    => 0,
			'forget_password_page' => 0,
		];

		foreach ( array_keys( $ablocks_pages ) as $page_key ) {
			$value = $xml->xpath( '/rss/channel/ablocks_options/ablocks_' . $page_key );
			if ( $value ) {
				$ablocks_pages[ $page_key ] = (int) $value[0];
			}
		}

		return $ablocks_pages;
	}

	private function get_ablocks_options( SimpleXMLElement $xml ): array {
		$ablocks_options = [
			'default_container_width'      => null,
			'container_padding'            => null,
			'container_element_gap'        => null,
			'global_color'                 => 'json',
			'global_typography'            => 'json',
			'global_font_family_fallback'  => null,
			'global_body_text_color'       => null,
			'global_body_typography'       => 'json',
			'global_body_paragraph_space'  => 'json',
			'global_link_color'            => null,
			'global_link_hover_color'      => null,
			'global_link_typography'       => 'json',
			'global_link_hover_typography' => 'json',
			'global_h1_color'              => null,
			'global_h1_typography'         => 'json',
			'global_h2_color'              => null,
			'global_h2_typography'         => 'json',
			'global_h3_color'              => null,
			'global_h3_typography'         => 'json',
			'global_h4_color'              => null,
			'global_h4_typography'         => 'json',
			'global_h5_color'              => null,
			'global_h5_typography'         => 'json',
			'global_h6_color'              => null,
			'global_h6_typography'         => 'json',
			'frontend_dashboard_page'      => null,
			'frontend_dashboard_sub_pages' => 'json',
		];

		foreach ( array_keys( $ablocks_options ) as $page_key ) {
			$value = $xml->xpath( '/rss/channel/ablocks_options/ablocks_' . $page_key );
			if ( $value ) {
				$ablocks_options[ $page_key ] = 'json' === $ablocks_options[ $page_key ] ? json_decode(
					base64_decode( (string) $value[0] ), true
				) : (string) $value[0];
			} else {
				unset( $ablocks_options[ $page_key ] );
			}
		}

		return $ablocks_options;
	}
}
