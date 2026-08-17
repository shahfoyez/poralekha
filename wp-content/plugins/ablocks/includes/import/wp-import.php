<?php

namespace ABlocks\import;

use ABlocks\Admin\Settings\Base;
use ABlocks\Helper;
use ABlocks\import\XmlParsers\WXR_Parser;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress importer class.
 */
class WP_Import extends \WP_Importer {

	public $id;

	public $version;
	public $authors = array();
	public $posts = array();
	public $terms = array();
	public $categories = array();
	public $tags = array();
	public $base_url = '';
	public $show_on_front = 'posts';
	public $page_on_front = 0;
	public $page_for_posts = 0;
	public $storeengine_pages = [];
	public $academy_pages = [];
	public $ablocks_pages = [];
	public $ablocks_options = [];

	public $processed_authors = array();
	public $author_mapping = array();
	public $processed_terms = array();
	public $processed_posts = array();
	public $post_orphans = array();
	public $processed_menu_items = array();
	public $menu_item_orphans = array();
	public $missing_menu_items = array();

	public $fetch_attachments = true;
	public $url_remap = array();
	public $featured_images = array();

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the WXR file for importing.
	 * @param bool   $with_attachments with attachments ?.
	 */
	public function import( string $file, bool $with_attachments = true ) {
		add_filter( 'import_post_meta_key', array( $this, 'is_valid_meta_key' ) );
		add_filter( 'http_request_timeout', array( &$this, 'bump_request_timeout' ) );

		$this->fetch_attachments = $with_attachments;

		$result = $this->import_start( $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		wp_suspend_cache_invalidation();
		$this->process_categories();
		$this->process_tags();
		$this->process_terms();
		$this->process_posts();
		wp_suspend_cache_invalidation( false );

		// update incorrect/missing information in the DB
		$this->backfill_parents();
		$this->backfill_attachment_urls();
		$this->remap_featured_images();
		$this->save_global_settings();

		$this->import_end();

		return true;
	}

	/**
	 * Parses the WXR file and prepares us for the task of processing parsed data
	 *
	 * @param string $file Path to the WXR file for importing.
	 */
	public function import_start( string $file ) {
		if ( ! is_file( $file ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'ablocks' ) );
		}

		Helper::emit_sse_message( [
			'action'  => 'log',
			'message' => __( 'Import process started...', 'ablocks' )
		] );

		$import_data = $this->parse( $file );

		if ( is_wp_error( $import_data ) ) {
			return $import_data;
		}

		$this->version = $import_data['version'];
		$this->get_authors_from_import( $import_data );
		$this->posts             = $import_data['posts'];
		$this->terms             = $import_data['terms'];
		$this->categories        = $import_data['categories'];
		$this->tags              = $import_data['tags'];
		$this->base_url          = esc_url( $import_data['base_url'] );
		$this->show_on_front     = $import_data['show_on_front'];
		$this->page_on_front     = $import_data['page_on_front'];
		$this->page_for_posts    = $import_data['page_for_posts'];
		$this->storeengine_pages = $import_data['storeengine_pages'];
		$this->academy_pages     = $import_data['academy_pages'];
		$this->ablocks_pages     = $import_data['ablocks_pages'];
		$this->ablocks_options   = $import_data['ablocks_options'];

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		do_action( 'ablocks/template_import_start' );

		return true;
	}

	/**
	 * Performs post-import cleanup of files and the cache
	 */
	public function import_end() {
		wp_import_cleanup( $this->id );

		wp_cache_flush();
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		do_action( 'ablocks/template_import_end' );

		Helper::emit_sse_message( [
			'action'  => 'log',
			'level'   => 'info',
			'message' => __( 'Import process Finished.', 'ablocks' ),
		] );
	}

	/**
	 * Handles the WXR upload and initial parsing of the file to prepare for
	 * displaying author import options
	 *
	 * @return bool False if error uploading or invalid file, true otherwise
	 */
	public function handle_upload(): bool {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) || ! file_exists( $file['file'] ) ) {
			return false;
		}

		$this->id    = (int) $file['id'];
		$import_data = $this->parse( $file['file'] );
		if ( is_wp_error( $import_data ) ) {
			return false;
		}

		$this->version = $import_data['version'];
		$this->get_authors_from_import( $import_data );

		return true;
	}

	/**
	 * Retrieve authors from parsed WXR data
	 *
	 * Uses the provided author information from WXR 1.1 files
	 * or extracts info from each post for WXR 1.0 files
	 *
	 * @param array $import_data Data returned by a WXR parser.
	 */
	public function get_authors_from_import( $import_data ) {
		if ( ! empty( $import_data['authors'] ) ) {
			$this->authors = $import_data['authors'];
			// no author information, grab it from the posts
		} else {
			foreach ( $import_data['posts'] as $post ) {
				$login = sanitize_user( $post['post_author'], true );
				if ( empty( $login ) ) {
					continue;
				}

				if ( ! isset( $this->authors[ $login ] ) ) {
					$this->authors[ $login ] = array(
						'author_login'        => $login,
						'author_display_name' => $post['post_author'],
					);
				}
			}
		}
	}

	/**
	 * Create new categories based on import information
	 *
	 * Doesn't create a new category if its slug already exists
	 */
	public function process_categories() {
		$this->categories = apply_filters( 'ablocks/template_import_categories', $this->categories );

		if ( empty( $this->categories ) ) {
			return;
		}

		foreach ( $this->categories as $cat ) {
			// if the category already exists leave it alone
			$term_id = term_exists( $cat['category_nicename'], 'category' );
			if ( $term_id ) {
				if ( is_array( $term_id ) ) {
					$term_id = $term_id['term_id'];
				}
				if ( isset( $cat['term_id'] ) ) {
					$this->processed_terms[ intval( $cat['term_id'] ) ] = (int) $term_id;
				}
				continue;
			}

			$parent      = empty( $cat['category_parent'] ) ? 0 : category_exists( $cat['category_parent'] );
			$description = isset( $cat['category_description'] ) ? $cat['category_description'] : '';

			$data = array(
				'category_nicename'    => $cat['category_nicename'],
				'category_parent'      => $parent,
				'cat_name'             => wp_slash( $cat['cat_name'] ),
				'category_description' => wp_slash( $description ),
			);

			$id = wp_insert_category( $data, true );
			if ( ! is_wp_error( $id ) && $id > 0 ) {
				if ( isset( $cat['term_id'] ) ) {
					$this->processed_terms[ intval( $cat['term_id'] ) ] = $id;
				}
			} else {
				continue;
			}

			$this->process_termmeta( $cat, $id );
		}//end foreach

		Helper::emit_sse_message( [
			'action'  => 'log',
			'level'   => 'info',
			'message' => __( 'Categories imported', 'ablocks' ),
		] );

		unset( $this->categories );
	}

	/**
	 * Create new post tags based on import information
	 *
	 * Doesn't create a tag if its slug already exists
	 */
	public function process_tags() {
		$this->tags = apply_filters( 'ablocks/template_import_tags', $this->tags );

		if ( empty( $this->tags ) ) {
			return;
		}

		foreach ( $this->tags as $tag ) {
			// if the tag already exists leave it alone
			$term_id = term_exists( $tag['tag_slug'], 'post_tag' );
			if ( $term_id ) {
				if ( is_array( $term_id ) ) {
					$term_id = $term_id['term_id'];
				}
				if ( isset( $tag['term_id'] ) ) {
					$this->processed_terms[ intval( $tag['term_id'] ) ] = (int) $term_id;
				}
				continue;
			}

			$description = isset( $tag['tag_description'] ) ? $tag['tag_description'] : '';
			$args        = array(
				'slug'        => $tag['tag_slug'],
				'description' => wp_slash( $description ),
			);

			$id = wp_insert_term( wp_slash( $tag['tag_name'] ), 'post_tag', $args );
			if ( ! is_wp_error( $id ) ) {
				if ( isset( $tag['term_id'] ) ) {
					$this->processed_terms[ intval( $tag['term_id'] ) ] = $id['term_id'];
				}
			} else {
				continue;
			}

			$this->process_termmeta( $tag, $id['term_id'] );
		}//end foreach

		Helper::emit_sse_message( [
			'action'  => 'log',
			'level'   => 'info',
			'message' => __( 'Tags imported', 'ablocks' ),
		] );

		unset( $this->tags );
	}

	/**
	 * Create new terms based on import information
	 *
	 * Doesn't create a term its slug already exists
	 */
	public function process_terms() {
		$this->terms = apply_filters( 'ablocks/template_import_terms', $this->terms );

		if ( empty( $this->terms ) ) {
			return;
		}

		foreach ( $this->terms as $term ) {
			// if the term already exists in the correct taxonomy leave it alone
			$term_id = term_exists( $term['slug'], $term['term_taxonomy'] );
			if ( $term_id ) {
				if ( is_array( $term_id ) ) {
					$term_id = $term_id['term_id'];
				}
				if ( isset( $term['term_id'] ) ) {
					$this->processed_terms[ intval( $term['term_id'] ) ] = (int) $term_id;
				}
				continue;
			}

			if ( empty( $term['term_parent'] ) ) {
				$parent = 0;
			} else {
				$parent = term_exists( $term['term_parent'], $term['term_taxonomy'] );
				if ( is_array( $parent ) ) {
					$parent = $parent['term_id'];
				}
			}

			$description = isset( $term['term_description'] ) ? $term['term_description'] : '';
			$args        = array(
				'slug'        => $term['slug'],
				'description' => wp_slash( $description ),
				'parent'      => (int) $parent,
			);

			$id = wp_insert_term( wp_slash( $term['term_name'] ), $term['term_taxonomy'], $args );
			if ( ! is_wp_error( $id ) ) {
				if ( isset( $term['term_id'] ) ) {
					$this->processed_terms[ intval( $term['term_id'] ) ] = $id['term_id'];
				}
			} else {
				continue;
			}

			$this->process_termmeta( $term, $id['term_id'] );
		}//end foreach

		Helper::emit_sse_message( [
			'action'  => 'log',
			'level'   => 'info',
			'message' => __( 'Terms imported', 'ablocks' ),
		] );

		unset( $this->terms );
	}

	/**
	 * Add metadata to imported term.
	 *
	 * @param array $term Term data from WXR import.
	 * @param int   $term_id ID of the newly created term.
	 *
	 * @since 0.6.2
	 */
	protected function process_termmeta( $term, $term_id ) {
		if ( ! isset( $term['termmeta'] ) ) {
			$term['termmeta'] = array();
		}

		/**
		 * Filters the metadata attached to an imported term.
		 *
		 * @param array $termmeta Array of term meta.
		 * @param int $term_id ID of the newly created term.
		 * @param array $term Term data from the WXR import.
		 *
		 * @since 0.6.2
		 */
		$term['termmeta'] = apply_filters( 'ablocks/template_import_term_meta', $term['termmeta'], $term_id, $term );

		if ( empty( $term['termmeta'] ) ) {
			return;
		}

		foreach ( $term['termmeta'] as $meta ) {
			/**
			 * Filters the meta key for an imported piece of term meta.
			 *
			 * @param string $meta_key Meta key.
			 * @param int $term_id ID of the newly created term.
			 * @param array $term Term data from the WXR import.
			 *
			 * @since 0.6.2
			 */
			$key = apply_filters( 'import_term_meta_key', $meta['key'], $term_id, $term );
			if ( ! $key ) {
				continue;
			}

			// Export gets meta straight from the DB so could have a serialized string
			$value = maybe_unserialize( $meta['value'] );

			add_term_meta( $term_id, wp_slash( $key ), wp_slash_strings_only( $value ) );

			/**
			 * Fires after term meta is imported.
			 *
			 * @param int $term_id ID of the newly created term.
			 * @param string $key Meta key.
			 * @param mixed $value Meta value.
			 *
			 * @since 0.6.2
			 */
			do_action( 'import_term_meta', $term_id, $key, $value );
		}//end foreach
	}

	/**
	 * Create new posts based on import information
	 *
	 * Posts marked as having a parent which doesn't exist will become top level items.
	 * Doesn't create a new post if: the post type doesn't exist, the given post ID
	 * is already noted as imported or a post with the same title and date already exists.
	 * Note that new/updated terms, comments and meta are imported for the last of the above.
	 */
	public function process_posts() {
		$this->posts = apply_filters( 'ablocks/template_import_posts', $this->posts );

		foreach ( $this->posts as $post ) {
			$post = apply_filters( 'ablocks/template_import_post_data_raw', $post );

			if ( ! post_type_exists( $post['post_type'] ) ) {
				do_action( 'ablocks/template_import_post_exists', $post );
				continue;
			}

			if ( isset( $this->processed_posts[ $post['post_id'] ] ) && ! empty( $post['post_id'] ) ) {
				continue;
			}

			if ( 'auto-draft' === $post['status'] ) {
				continue;
			}

			if ( 'nav_menu_item' === $post['post_type'] ) {
				$this->process_menu_item( $post );
				continue;
			}

			$post_exists = post_exists( $post['post_title'], '', '', $post['post_type'] );
			if ( $post_exists && in_array( $post['post_type'], [ 'wp_template', 'wp_template_part' ], true ) ) {
				// remove existence template.
				wp_delete_post( $post_exists, true );
				$post_exists = false;
			}

			/**
			 * Filter ID of the existing post corresponding to post currently importing.
			 *
			 * Return 0 to force the post to be imported. Filter the ID to be something else
			 * to override which existing post is mapped to the imported post.
			 *
			 * @param int $post_exists Post ID, or 0 if post did not exist.
			 * @param array $post The post array to be inserted.
			 *
			 * @see post_exists()
			 * @since 0.6.2
			 */
			$post_exists = apply_filters( 'ablocks/template_import_existing_post', $post_exists, $post );

			if ( ! $this->check_for_known_page( $post ) && $post_exists ) {
				$comment_post_id                                     = $post_exists;
				$post_id                                             = $post_exists;
				$this->processed_posts[ intval( $post['post_id'] ) ] = intval( $post_exists );
			} else {
				$post_parent = (int) $post['post_parent'];
				if ( $post_parent ) {
					// if we already know the parent, map it to the new local ID
					if ( isset( $this->processed_posts[ $post_parent ] ) ) {
						$post_parent = $this->processed_posts[ $post_parent ];
						// otherwise record the parent for later
					} else {
						$this->post_orphans[ intval( $post['post_id'] ) ] = $post_parent;
						$post_parent                                      = 0;
					}
				}

				// map the post author
				$author = sanitize_user( $post['post_author'], true );
				if ( isset( $this->author_mapping[ $author ] ) ) {
					$author = $this->author_mapping[ $author ];
				} else {
					$author = (int) get_current_user_id();
				}

				$postdata = array(
					'import_id'      => $post['post_id'],
					'post_author'    => $author,
					'post_date'      => $post['post_date'],
					'post_date_gmt'  => $post['post_date_gmt'],
					'post_content'   => $post['post_content'],
					'post_excerpt'   => $post['post_excerpt'],
					'post_title'     => $post['post_title'],
					'post_status'    => $post['status'],
					'post_name'      => $post['post_name'],
					'comment_status' => $post['comment_status'],
					'ping_status'    => $post['ping_status'],
					'guid'           => $post['guid'],
					'post_parent'    => $post_parent,
					'menu_order'     => $post['menu_order'],
					'post_type'      => $post['post_type'],
					'post_password'  => $post['post_password'],
				);

				// remove existence global styles.
				if ( 'wp_global_styles' === $post['post_type'] ) {
					$this->remove_gutenberg_styles();
				}

				$original_post_id = $post['post_id'];
				$postdata         = apply_filters( 'ablocks/template_import_post_data_processed', $postdata, $post );

				$postdata = wp_slash( $postdata );

				if ( 'attachment' === $postdata['post_type'] && ! $post_exists ) {
					if ( ! $this->fetch_attachments ) {
						continue;
					}
					$remote_url = ! empty( $post['attachment_url'] ) ? $post['attachment_url'] : $post['guid'];

					// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
					// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
					$postdata['upload_date'] = $post['post_date'];
					if ( isset( $post['postmeta'] ) ) {
						foreach ( $post['postmeta'] as $meta ) {
							if ( '_wp_attached_file' === $meta['key'] ) {
								if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta['value'], $matches ) ) {
									$postdata['upload_date'] = $matches[0];
								}
								break;
							}
						}
					}

					$comment_post_id = $this->process_attachment( $postdata, $remote_url );
					$post_id         = $comment_post_id;
				} else {
					if ( $post_exists ) {
						$comment_post_id = wp_update_post( array_merge( $postdata, array( 'ID' => $post_exists ) ) );
					} else {
						$comment_post_id = wp_insert_post( $postdata, true );
					}
					$post_id = $comment_post_id;
					do_action( 'ablocks/template_import_insert_post', $post_id, $original_post_id, $postdata, $post );
				}//end if

				if ( is_wp_error( $post_id ) ) {
					Helper::emit_sse_message( [
						'action'  => 'log',
						'level'   => 'warning',
						'message' => "Failed to import: {$post['post_type']} - {$post['post_title']}",
					] );
					continue;
				}

				if ( 1 === (int) $post['is_sticky'] ) {
					stick_post( $post_id );
				}

				Helper::emit_sse_message( [
					'action'  => 'log',
					'level'   => 'info',
					'message' => "{$post['post_type']} - {$post['post_title']} imported.",
				] );
			}//end if

			// update reading settings.
			if ( 'page' === $this->show_on_front && 'page' === $post['post_type'] ) {
				update_option( 'show_on_front', 'page' );
				if ( $this->page_on_front === $post['post_id'] ) {
					update_option( 'page_on_front', $post_id );
					Helper::emit_sse_message( [
						'action'  => 'log',
						'level'   => 'info',
						'message' => __( 'Front page setting\'s updated.', 'ablocks' ),
					] );
				} elseif ( $post['post_id'] === $this->page_for_posts ) {
					update_option( 'page_for_posts', $post_id );
					Helper::emit_sse_message( [
						'action'  => 'log',
						'level'   => 'info',
						'message' => __( 'Posts page setting\'s updated.', 'ablocks' ),
					] );
				}
			}//end if

			// update storeengine page settings
			if ( ! empty( $this->storeengine_pages ) && class_exists( 'StoreEngine' ) ) {
				$se_page = array_search( $post['post_id'], $this->storeengine_pages, true );
				if ( $se_page ) {
					\StoreEngine\Admin\Settings\Base::save_settings( [
						$se_page => $post_id
					] );
				}
			}

			// update academy page settings
			if ( ! empty( $this->academy_pages ) && class_exists( 'Academy' ) ) {
				$academy_page = array_search( $post['post_id'], $this->academy_pages, true );
				if ( $academy_page ) {
					\Academy\Admin\Settings\Base::save_settings( [
						$academy_page => $post_id
					] );
				}
			}

			// update ablock page settings
			if ( ! empty( $this->ablocks_pages ) ) {
				$ablocks_page = array_search( $post['post_id'], $this->ablocks_pages, true );
				if ( $ablocks_page ) {
					Base::save_settings( [
						$ablocks_page => $post_id
					] );
				}
			}

			// map pre-import ID to local ID
			$this->processed_posts[ intval( $post['post_id'] ) ] = (int) $post_id;

			if ( ! isset( $post['terms'] ) ) {
				$post['terms'] = array();
			}

			$post['terms'] = apply_filters( 'ablocks/template_import_post_terms', $post['terms'], $post_id, $post );

			// add categories, tags and other terms
			if ( ! empty( $post['terms'] ) ) {
				$terms_to_set = array();
				foreach ( $post['terms'] as $term ) {
					// back compat with WXR 1.0 map 'tag' to 'post_tag'
					$taxonomy = ( 'tag' === $term['domain'] ) ? 'post_tag' : $term['domain'];
					if ( 'wp_theme' === $taxonomy ) {
						$term['name'] = wp_get_theme()->get_stylesheet();
						$term['slug'] = wp_get_theme()->get_stylesheet();
					}
					$term_exists = term_exists( $term['slug'], $taxonomy );
					$term_id     = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;

					if ( ! $term_id ) {
						$t = wp_insert_term( $term['name'], $taxonomy, array( 'slug' => $term['slug'] ) );
						if ( ! is_wp_error( $t ) ) {
							$term_id = $t['term_id'];
							do_action( 'ablocks/template_import_insert_term', $t, $term, $post_id, $post );
						} else {
							do_action( 'ablocks/template_import_insert_term_failed', $t, $term, $post_id, $post );
							continue;
						}
					}
					$terms_to_set[ $taxonomy ][] = intval( $term_id );
				}//end foreach

				foreach ( $terms_to_set as $tax => $ids ) {
					$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
					do_action( 'ablocks/template_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $post );
				}
				unset( $post['terms'], $terms_to_set );
			}//end if

			if ( ! isset( $post['comments'] ) ) {
				$post['comments'] = array();
			}

			$post['comments'] = apply_filters( 'ablocks/template_import_post_comments', $post['comments'], $post_id, $post );

			// add/update comments
			if ( ! empty( $post['comments'] ) ) {
				$num_comments      = 0;
				$inserted_comments = array();
				foreach ( $post['comments'] as $comment ) {
					$comment_id                                         = $comment['comment_id'];
					$newcomments[ $comment_id ]['comment_post_ID']      = $comment_post_id;
					$newcomments[ $comment_id ]['comment_author']       = $comment['comment_author'];
					$newcomments[ $comment_id ]['comment_author_email'] = $comment['comment_author_email'];
					$newcomments[ $comment_id ]['comment_author_IP']    = $comment['comment_author_IP'];
					$newcomments[ $comment_id ]['comment_author_url']   = $comment['comment_author_url'];
					$newcomments[ $comment_id ]['comment_date']         = $comment['comment_date'];
					$newcomments[ $comment_id ]['comment_date_gmt']     = $comment['comment_date_gmt'];
					$newcomments[ $comment_id ]['comment_content']      = $comment['comment_content'];
					$newcomments[ $comment_id ]['comment_approved']     = $comment['comment_approved'];
					$newcomments[ $comment_id ]['comment_type']         = $comment['comment_type'];
					$newcomments[ $comment_id ]['comment_parent']       = $comment['comment_parent'];
					$newcomments[ $comment_id ]['commentmeta']          = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : array();
					if ( isset( $this->processed_authors[ $comment['comment_user_id'] ] ) ) {
						$newcomments[ $comment_id ]['user_id'] = $this->processed_authors[ $comment['comment_user_id'] ];
					}
				}
				ksort( $newcomments );

				foreach ( $newcomments as $key => $comment ) {
					// if this is a new post we can skip the comment_exists() check
					if ( ! $post_exists || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
						if ( isset( $inserted_comments[ $comment['comment_parent'] ] ) ) {
							$comment['comment_parent'] = $inserted_comments[ $comment['comment_parent'] ];
						}

						$comment_data = wp_slash( $comment );
						unset( $comment_data['commentmeta'] ); // Handled separately, wp_insert_comment() also expects `comment_meta`.
						$comment_data = wp_filter_comment( $comment_data );

						$inserted_comments[ $key ] = wp_insert_comment( $comment_data );

						do_action( 'ablocks/template_import_insert_comment', $inserted_comments[ $key ], $comment, $comment_post_id, $post );

						foreach ( $comment['commentmeta'] as $meta ) {
							$value = maybe_unserialize( $meta['value'] );

							add_comment_meta( $inserted_comments[ $key ], wp_slash( $meta['key'] ), wp_slash_strings_only( $value ) );
						}

						++ $num_comments;
					}//end if
				}//end foreach
				unset( $newcomments, $inserted_comments, $post['comments'] );
			}//end if

			if ( ! isset( $post['postmeta'] ) ) {
				$post['postmeta'] = array();
			}

			$post['postmeta'] = apply_filters( 'ablocks/template_import_post_meta', $post['postmeta'], $post_id, $post );

			// add/update post meta
			if ( ! empty( $post['postmeta'] ) ) {
				foreach ( $post['postmeta'] as $meta ) {
					$key   = apply_filters( 'import_post_meta_key', $meta['key'], $post_id, $post );
					$value = false;

					if ( '_edit_last' === $key ) {
						if ( isset( $this->processed_authors[ intval( $meta['value'] ) ] ) ) {
							$value = $this->processed_authors[ intval( $meta['value'] ) ];
						} else {
							$key = false;
						}
					}

					if ( $key ) {
						// export gets meta straight from the DB so could have a serialized string
						if ( ! $value ) {
							$value = maybe_unserialize( $meta['value'] );
						}

						add_post_meta( $post_id, wp_slash( $key ), wp_slash_strings_only( $value ) );

						do_action( 'import_post_meta', $post_id, $key, $value );

						// if the post has a featured image, take note of this in case of remap
						if ( '_thumbnail_id' === $key ) {
							$this->featured_images[ $post_id ] = (int) $value;
						}
					}
				}//end foreach
			}//end if
		}//end foreach

		unset( $this->posts );
	}

	private function remove_gutenberg_styles() {
		$args   = array(
			'post_type'      => 'wp_global_styles',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'fields'         => 'ids',
		);
		$styles = get_posts( $args );
		foreach ( $styles as $style ) {
			wp_delete_post( $style );
		}
		Helper::emit_sse_message( [
			'action'  => 'log',
			'level'   => 'info',
			'message' => __( 'Existence gutenberg styles removed.', 'ablocks' ),
		] );
	}

	private function check_for_known_page( array $post ) {
		if ( ! empty( $this->storeengine_pages ) && class_exists( 'StoreEngine' ) ) {
			return (bool) array_search( $post['post_id'], $this->storeengine_pages, true );
		}

		if ( ! empty( $this->academy_pages ) && class_exists( 'Academy' ) ) {
			return (bool) array_search( $post['post_id'], $this->academy_pages, true );
		}

		if ( ! empty( $this->ablocks_pages ) ) {
			return (bool) array_search( $post['post_id'], $this->ablocks_pages, true );
		}

		return false;
	}

	/**
	 * Attempt to create a new menu item from import data
	 *
	 * Fails for draft, orphaned menu items and those without an associated nav_menu
	 * or an invalid nav_menu term. If the post type or term object which the menu item
	 * represents doesn't exist then the menu item will not be imported (waits until the
	 * end of the import to retry again before discarding).
	 *
	 * @param array $item Menu item details from WXR file.
	 */
	public function process_menu_item( $item ) {
		// skip draft, orphaned menu items
		if ( 'draft' === $item['status'] ) {
			return;
		}

		$menu_slug = false;
		if ( isset( $item['terms'] ) ) {
			// loop through terms, assume first nav_menu term is correct menu
			foreach ( $item['terms'] as $term ) {
				if ( 'nav_menu' === $term['domain'] ) {
					$menu_slug = $term['slug'];
					break;
				}
			}
		}

		// no nav_menu term associated with this menu item
		if ( ! $menu_slug ) {
			return;
		}

		$menu_id = term_exists( $menu_slug, 'nav_menu' );
		if ( ! $menu_id ) {
			return;
		} else {
			$menu_id = is_array( $menu_id ) ? $menu_id['term_id'] : $menu_id;
		}

		foreach ( $item['postmeta'] as $meta ) {
			${$meta['key']} = $meta['value'];
		}

		if ( 'taxonomy' === $_menu_item_type && isset( $this->processed_terms[ intval( $_menu_item_object_id ) ] ) ) {
			$_menu_item_object_id = $this->processed_terms[ intval( $_menu_item_object_id ) ];
		} elseif ( 'post_type' === $_menu_item_type && isset( $this->processed_posts[ intval( $_menu_item_object_id ) ] ) ) {
			$_menu_item_object_id = $this->processed_posts[ intval( $_menu_item_object_id ) ];
		} elseif ( 'custom' !== $_menu_item_type ) {
			// associated object is missing or not imported yet, we'll retry later
			$this->missing_menu_items[] = $item;

			return;
		}

		if ( isset( $this->processed_menu_items[ intval( $_menu_item_menu_item_parent ) ] ) ) {
			$_menu_item_menu_item_parent = $this->processed_menu_items[ intval( $_menu_item_menu_item_parent ) ];
		} elseif ( $_menu_item_menu_item_parent ) {
			$this->menu_item_orphans[ intval( $item['post_id'] ) ] = (int) $_menu_item_menu_item_parent;
			$_menu_item_menu_item_parent                           = 0;
		}

		// wp_update_nav_menu_item expects CSS classes as a space separated string
		$_menu_item_classes = maybe_unserialize( $_menu_item_classes );
		if ( is_array( $_menu_item_classes ) ) {
			$_menu_item_classes = implode( ' ', $_menu_item_classes );
		}

		$args = array(
			'menu-item-object-id'   => $_menu_item_object_id,
			'menu-item-object'      => $_menu_item_object,
			'menu-item-parent-id'   => $_menu_item_menu_item_parent,
			'menu-item-position'    => intval( $item['menu_order'] ),
			'menu-item-type'        => $_menu_item_type,
			'menu-item-title'       => $item['post_title'],
			'menu-item-url'         => $_menu_item_url,
			'menu-item-description' => $item['post_content'],
			'menu-item-attr-title'  => $item['post_excerpt'],
			'menu-item-target'      => $_menu_item_target,
			'menu-item-classes'     => $_menu_item_classes,
			'menu-item-xfn'         => $_menu_item_xfn,
			'menu-item-status'      => $item['status'],
		);

		$id = wp_update_nav_menu_item( $menu_id, 0, $args );
		if ( $id && ! is_wp_error( $id ) ) {
			$this->processed_menu_items[ intval( $item['post_id'] ) ] = (int) $id;
		}
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array  $post Attachment post details from WXR.
	 * @param string $url URL to fetch attachment from.
	 *
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	public function process_attachment( $post, $url ) {
		if ( ! $this->fetch_attachments ) {
			return;
		}

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) ) {
			$url = rtrim( $this->base_url, '/' ) . $url;
		}

		$upload = $this->fetch_remote_file( $url, $post );
		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		$info = wp_check_filetype( $upload['file'] );
		if ( $info ) {
			$post['post_mime_type'] = $info['type'];
		} else {
			return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'ablocks' ) );
		}

		$post['guid'] = $upload['url'];

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		// remap resized image URLs, works by stripping the extension and remapping the URL stub.
		if ( preg_match( '!^image/!', $info['type'] ) ) {
			$parts = pathinfo( $url );
			$name  = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

			$parts_new = pathinfo( $upload['url'] );
			$name_new  = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

			$this->url_remap[ $parts['dirname'] . '/' . $name ] = $parts_new['dirname'] . '/' . $name_new;
		}

		return $post_id;
	}

	/**
	 * Attempt to download a remote file attachment
	 *
	 * @param string $url URL of item to fetch.
	 * @param array  $post Attachment details.
	 *
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise
	 */
	public function fetch_remote_file( $url, $post ) {
		// Extract the file name from the URL.
		$path      = wp_parse_url( $url, PHP_URL_PATH );
		$file_name = '';
		if ( is_string( $path ) ) {
			$file_name = basename( $path );
		}

		if ( ! $file_name ) {
			$file_name = md5( $url );
		}

		$tmp_file_name = wp_tempnam( $file_name );
		if ( ! $tmp_file_name ) {
			return new WP_Error( 'import_no_file', __( 'Could not create temporary file.', 'ablocks' ) );
		}

		// Fetch the remote URL and write it to the placeholder file.
		$remote_response = wp_safe_remote_get(
			$url,
			array(
				'timeout'  => 300,
				'stream'   => true,
				'filename' => $tmp_file_name,
				'headers'  => array(
					'Accept-Encoding' => 'identity',
				),
			)
		);

		if ( is_wp_error( $remote_response ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink 
			@unlink( $tmp_file_name );

			return new WP_Error(
				'import_file_error',
				sprintf(
				/* translators: 1: The WordPress error message. 2: The WordPress error code. */
					__( 'Request failed due to an error: %1$s (%2$s)', 'ablocks' ),
					esc_html( $remote_response->get_error_message() ),
					esc_html( $remote_response->get_error_code() )
				)
			);
		}

		$remote_response_code = (int) wp_remote_retrieve_response_code( $remote_response );

		// Make sure the fetch was successful.
		if ( 200 !== $remote_response_code ) {
			if ( file_exists( $tmp_file_name ) ) {
				// phpcs:ignore  WordPress.WP.AlternativeFunctions.unlink_unlink 
				unlink( $tmp_file_name );
			}

			return new WP_Error(
				'import_file_error',
				sprintf(
				/* translators: 1: The HTTP error message. 2: The HTTP error code. */
					__( 'Remote server returned the following unexpected result: %1$s (%2$s)', 'ablocks' ),
					get_status_header_desc( $remote_response_code ),
					esc_html( $remote_response_code )
				)
			);
		}

		$headers = wp_remote_retrieve_headers( $remote_response );

		// Request failed.
		if ( ! $headers ) {
			if ( file_exists( $tmp_file_name ) ) {
				// phpcs:ignore  WordPress.WP.AlternativeFunctions.unlink_unlink 
				unlink( $tmp_file_name );
			}

			return new WP_Error( 'import_file_error', __( 'Remote server did not respond', 'ablocks' ) );
		}

		$filesize = (int) filesize( $tmp_file_name );

		if ( 0 === $filesize ) {
			if ( file_exists( $tmp_file_name ) ) {
				// phpcs:ignore  WordPress.WP.AlternativeFunctions.unlink_unlink 
				unlink( $tmp_file_name );
			}

			return new WP_Error( 'import_file_error', __( 'Zero size file downloaded', 'ablocks' ) );
		}

		if ( ! isset( $headers['content-encoding'] ) && isset( $headers['content-length'] ) && $filesize !== (int) $headers['content-length'] ) {
			if ( file_exists( $tmp_file_name ) ) {
				// phpcs:ignore  WordPress.WP.AlternativeFunctions.unlink_unlink 
				unlink( $tmp_file_name );
			}

			return new WP_Error( 'import_file_error', __( 'Downloaded file has incorrect size', 'ablocks' ) );
		}

		$max_size = (int) $this->max_attachment_size();
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			if ( file_exists( $tmp_file_name ) ) {
				// phpcs:ignore  WordPress.WP.AlternativeFunctions.unlink_unlink 
				unlink( $tmp_file_name );
			}

			// Translators: %s is the file size limit for the remote file import
			return new WP_Error( 'import_file_error', sprintf( __( 'Remote file is too large, limit is %s', 'ablocks' ), size_format( $max_size ) ) );
		}

		// Override file name with Content-Disposition header value.
		if ( ! empty( $headers['content-disposition'] ) ) {
			$file_name_from_disposition = self::get_filename_from_disposition( (array) $headers['content-disposition'] );
			if ( $file_name_from_disposition ) {
				$file_name = $file_name_from_disposition;
			}
		}

		// Set file extension if missing.
		$file_ext = pathinfo( $file_name, PATHINFO_EXTENSION );
		if ( ! $file_ext && ! empty( $headers['content-type'] ) ) {
			$extension = self::get_file_extension_by_mime_type( $headers['content-type'] );
			if ( $extension ) {
				$file_name = "{$file_name}.{$extension}";
			}
		}

		// Handle the upload like _wp_handle_upload() does.
		$wp_filetype     = wp_check_filetype_and_ext( $tmp_file_name, $file_name );
		$ext             = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
		$type            = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
		$proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];

		// Check to see if wp_check_filetype_and_ext() determined the filename was incorrect.
		if ( $proper_filename ) {
			$file_name = $proper_filename;
		}

		if ( ( ! $type || ! $ext ) && ! current_user_can( 'unfiltered_upload' ) ) {
			return new WP_Error( 'import_file_error', __( 'Sorry, this file type is not permitted for security reasons.', 'ablocks' ) );
		}

		$uploads = wp_upload_dir( $post['upload_date'] );
		if ( ! ( $uploads && false === $uploads['error'] ) ) {
			return new WP_Error( 'upload_dir_error', $uploads['error'] );
		}

		// Move the file to the uploads dir.
		$file_name     = wp_unique_filename( $uploads['path'], $file_name );
		$new_file      = $uploads['path'] . "/$file_name";
		$move_new_file = copy( $tmp_file_name, $new_file );

		if ( ! $move_new_file ) {
			if ( file_exists( $tmp_file_name ) ) {
				// phpcs:ignore  WordPress.WP.AlternativeFunctions.unlink_unlink 
				unlink( $tmp_file_name );
			}

			return new WP_Error( 'import_file_error', __( 'The uploaded file could not be moved', 'ablocks' ) );
		}

		// Set correct file permissions.
		$stat  = stat( dirname( $new_file ) );
		$perms = $stat['mode'] & 0000666;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod 
		chmod( $new_file, $perms );

		$upload = array(
			'file'  => $new_file,
			'url'   => $uploads['url'] . "/$file_name",
			'type'  => $wp_filetype['type'],
			'error' => false,
		);

		// keep track of the old and new urls so we can substitute them later
		$this->url_remap[ $url ]          = $upload['url'];
		$this->url_remap[ $post['guid'] ] = $upload['url']; // r13735, really needed?
		// keep track of the destination if the remote url is redirected somewhere else
		if ( isset( $headers['x-final-location'] ) && $headers['x-final-location'] !== $url ) {
			$this->url_remap[ $headers['x-final-location'] ] = $upload['url'];
		}

		return $upload;
	}

	/**
	 * Attempt to associate posts and menu items with previously missing parents
	 *
	 * An imported post's parent may not have been imported when it was first created
	 * so try again. Similarly, for child menu items and menu items which were missing
	 * the object (e.g. post) they represent in the menu
	 */
	public function backfill_parents() {
		global $wpdb;

		// find parents for post orphans
		foreach ( $this->post_orphans as $child_id => $parent_id ) {
			$local_child_id  = false;
			$local_parent_id = false;
			if ( isset( $this->processed_posts[ $child_id ] ) ) {
				$local_child_id = $this->processed_posts[ $child_id ];
			}
			if ( isset( $this->processed_posts[ $parent_id ] ) ) {
				$local_parent_id = $this->processed_posts[ $parent_id ];
			}

			if ( $local_child_id && $local_parent_id ) {
				// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $wpdb->posts, array( 'post_parent' => $local_parent_id ), array( 'ID' => $local_child_id ), '%d', '%d' );
				clean_post_cache( $local_child_id );
			}
		}

		// all other posts/terms are imported, retry menu items with missing associated object
		$missing_menu_items = $this->missing_menu_items;
		foreach ( $missing_menu_items as $item ) {
			$this->process_menu_item( $item );
		}

		// find parents for menu item orphans
		foreach ( $this->menu_item_orphans as $child_id => $parent_id ) {
			$local_child_id  = 0;
			$local_parent_id = 0;
			if ( isset( $this->processed_menu_items[ $child_id ] ) ) {
				$local_child_id = $this->processed_menu_items[ $child_id ];
			}
			if ( isset( $this->processed_menu_items[ $parent_id ] ) ) {
				$local_parent_id = $this->processed_menu_items[ $parent_id ];
			}

			if ( $local_child_id && $local_parent_id ) {
				update_post_meta( $local_child_id, '_menu_item_menu_item_parent', (int) $local_parent_id );
			}
		}
	}

	/**
	 * Use stored mapping information to update old attachment URLs
	 */
	public function backfill_attachment_urls() {
		global $wpdb;
		// make sure we do the longest urls first, in case one is a substring of another
		uksort( $this->url_remap, array( &$this, 'cmpr_strlen' ) );

		foreach ( $this->url_remap as $from_url => $to_url ) {
			// remap urls in post_content
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $from_url, $to_url ) );
			// remap enclosure urls
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $from_url, $to_url ) );
		}

		if ( ! empty( $this->url_remap ) ) {
			Helper::emit_sse_message( [
				'action'  => 'log',
				'level'   => 'info',
				'message' => __( 'Old attachment URLs have been updated.', 'ablocks' ),
			] );
		}
	}

	/**
	 * Update _thumbnail_id meta to new, imported attachment IDs
	 */
	public function remap_featured_images() {
		// cycle through posts that have a featured image
		foreach ( $this->featured_images as $post_id => $value ) {
			if ( isset( $this->processed_posts[ $value ] ) ) {
				$new_id = $this->processed_posts[ $value ];
				// only update if there's a difference
				if ( $new_id !== $value ) {
					update_post_meta( $post_id, '_thumbnail_id', $new_id );
				}
			}
		}
		Helper::emit_sse_message( [
			'action'  => 'log',
			'level'   => 'info',
			'message' => __( 'Imported posts `_thumbnail_id` updated.', 'ablocks' ),
		] );
	}

	public function save_global_settings() {
		if ( ! empty( $this->ablocks_options ) ) {
			$settings = $this->ablocks_options;
			if ( ! empty( $settings['frontend_dashboard_sub_pages'] ) ) {
				update_option( 'ablocks_frontend_dashboard_sub_pages', wp_json_encode( $settings['frontend_dashboard_sub_pages'] ) );
				unset( $settings['frontend_dashboard_sub_pages'] );
			}

			Base::save_settings( $settings );
		}
	}

	/**
	 * Parse a WXR file
	 *
	 * @param string $file Path to WXR file for parsing.
	 *
	 * @return array|WP_Error Information gathered from the WXR file.
	 */
	public function parse( string $file ) {
		$parser = new WXR_Parser();

		return $parser->parse( $file );
	}

	/**
	 * Decide if the given meta key maps to information we will want to import
	 *
	 * @param string $key The meta key to check.
	 *
	 * @return string|bool The key if we do want to import, false if not
	 */
	public function is_valid_meta_key( $key ) {
		// skip attachment metadata since we'll regenerate it from scratch
		// skip _edit_lock as not relevant for import
		if ( in_array( $key, array( '_wp_attached_file', '_wp_attachment_metadata', '_edit_lock' ), true ) ) {
			return false;
		}

		return $key;
	}

	/**
	 * Decide what the maximum file size for downloaded attachments is.
	 * Default is 0 (unlimited), can be filtered via import_attachment_size_limit
	 *
	 * @return int Maximum attachment file size to import
	 */
	public function max_attachment_size(): int {
		return apply_filters( 'import_attachment_size_limit', 0 );
	}

	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import
	 *
	 * @param mixed $val Value.
	 *
	 * @return int 60
	 */
	public function bump_request_timeout( $val ): int {
		return 60;
	}

	public function cmpr_strlen( $a, $b ): int {
		// return the difference in length between two strings.
		return strlen( $b ) - strlen( $a );
	}

	/**
	 * Parses filename from a Content-Disposition header value.
	 *
	 * As per RFC6266:
	 *
	 *     content-disposition = "Content-Disposition" ":"
	 *                            disposition-type *( ";" disposition-parm )
	 *
	 *     disposition-type    = "inline" | "attachment" | disp-ext-type
	 *                         ; case-insensitive
	 *     disp-ext-type       = token
	 *
	 *     disposition-parm    = filename-parm | disp-ext-parm
	 *
	 *     filename-parm       = "filename" "=" value
	 *                         | "filename*" "=" ext-value
	 *
	 *     disp-ext-parm       = token "=" value
	 *                         | ext-token "=" ext-value
	 *     ext-token           = <the characters in token, followed by "*">
	 *
	 * @param string[] $disposition_header List of Content-Disposition header values.
	 *
	 * @return string|null Filename if available, or null if not found.
	 * @link http://tools.ietf.org/html/rfc2388
	 * @link http://tools.ietf.org/html/rfc6266
	 *
	 * @since 0.7.0
	 *
	 * @see WP_REST_Attachments_Controller::get_filename_from_disposition()
	 */
	protected static function get_filename_from_disposition( $disposition_header ) {
		// Get the filename.
		$filename = null;

		foreach ( $disposition_header as $value ) {
			$value = trim( $value );

			if ( strpos( $value, ';' ) === false ) {
				continue;
			}

			list( $type, $attr_parts ) = explode( ';', $value, 2 );

			$attr_parts = explode( ';', $attr_parts );
			$attributes = array();

			foreach ( $attr_parts as $part ) {
				if ( strpos( $part, '=' ) === false ) {
					continue;
				}

				list( $key, $value ) = explode( '=', $part, 2 );

				$attributes[ trim( $key ) ] = trim( $value );
			}

			if ( empty( $attributes['filename'] ) ) {
				continue;
			}

			$filename = trim( $attributes['filename'] );

			// Unquote quoted filename, but after trimming.
			if ( substr( $filename, 0, 1 ) === '"' && substr( $filename, - 1, 1 ) === '"' ) {
				$filename = substr( $filename, 1, - 1 );
			}
		}//end foreach

		return $filename;
	}

	/**
	 * Retrieves file extension by mime type.
	 *
	 * @param string $mime_type Mime type to search extension for.
	 *
	 * @return string|null File extension if available, or null if not found.
	 * @since 0.7.0
	 */
	protected static function get_file_extension_by_mime_type( $mime_type ) {
		static $map = null;

		if ( is_array( $map ) ) {
			return isset( $map[ $mime_type ] ) ? $map[ $mime_type ] : null;
		}

		$mime_types = wp_get_mime_types();
		$map        = array_flip( $mime_types );

		// Some types have multiple extensions, use only the first one.
		foreach ( $map as $type => $extensions ) {
			$map[ $type ] = strtok( $extensions, '|' );
		}

		return isset( $map[ $mime_type ] ) ? $map[ $mime_type ] : null;
	}
}
