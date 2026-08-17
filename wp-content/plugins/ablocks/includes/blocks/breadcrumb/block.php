<?php
namespace ABlocks\Blocks\Breadcrumb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Helper;
use ABlocks\Controls\Color;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Dimensions;

class Block extends BlockBaseAbstract {
	protected $block_name = 'breadcrumb';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-breadcrumbs',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		// Normal link color
		$css_generator->add_class_styles(
			'{{WRAPPER}} div.ablocks-breadcrumbs span a',
			$this->get_breadcrumb_normal_title_css( $attributes ),
			$this->get_breadcrumb_normal_title_css( $attributes, 'Tablet' ),
			$this->get_breadcrumb_normal_title_css( $attributes, 'Mobile' )
		);

		// Hover link color
		$css_generator->add_class_styles(
			'{{WRAPPER}} div.ablocks-breadcrumbs span a:hover',
			$this->get_breadcrumb_hover_title_css( $attributes ),
			$this->get_breadcrumb_hover_title_css( $attributes, 'Tablet' ),
			$this->get_breadcrumb_hover_title_css( $attributes, 'Mobile' )
		);

		// Title (typography + color + direction)
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-breadcrumbs__item',
			$this->get_breadcrumb_title_css( $attributes ),
			$this->get_breadcrumb_title_css( $attributes, 'Tablet' ),
			$this->get_breadcrumb_title_css( $attributes, 'Mobile' )
		);

		// Separator (color + font-size)
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-breadcrumbs__separator',
			$this->get_breadcrumb_separator_css( $attributes ),
			$this->get_breadcrumb_separator_css( $attributes, 'Tablet' ),
			$this->get_breadcrumb_separator_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-breadcrumbs___before-image , {{WRAPPER}} .ablocks-breadcrumbs__before-text',
			$this->get_breadcrumb_before_text_image_css( $attributes ),
			$this->get_breadcrumb_before_text_image_css( $attributes, 'Tablet' ),
			$this->get_breadcrumb_before_text_image_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();

	}


	public function get_wrapper_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['breadcrumbSpaceBetween'] ) ) {
			$css['gap'] = $attributes['breadcrumbSpaceBetween'] . 'px';
		}

		if ( isset( $attributes['positionBreadcrumb'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['positionBreadcrumb'][ 'value' . $device ];
		}
		return array_merge(
			$css,
		);

	}

	public function get_breadcrumb_title_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['breadcrumbTitlecolor'] ) ) {
			$css['color'] = Color::get_css( $attributes['breadcrumbTitlecolor'] );
		}

		$dir     = isset( $attributes['taxonomyTitleDirection'] ) ? $attributes['taxonomyTitleDirection'] : null;
		$dirCss  = ! is_null( $dir ) ? get_alignment_css( $dir, 'justify-content', $device ) : [];
		if ( ! empty( $attributes['breadcrumbItemBackground'] ) ) {
			$css['background-color'] = Color::get_css( $attributes['breadcrumbItemBackground'] );
		}
		$typographyValueGlobal = ! empty( $attributes['breadcrumbTitleTypographyGlobal'] ) ? $attributes['breadcrumbTitleTypographyGlobal'] : '';
		return array_merge(
			$css, (array) $dirCss,
			Typography::get_css( $attributes['breadcrumbTitleTypography'] ?? [], '', $device, $typographyValueGlobal ),
			Dimensions::get_css( $attributes['breadcrumbItemPadding'] ?? [], 'padding', $device ),
			Dimensions::get_css( $attributes['BreadcrumbBorderRadius'] ?? [], 'border-radius', $device ),
		);
	}

	public function get_breadcrumb_normal_title_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['breadcrumbLinkcolor'] ) ) {
			$css['color'] = Color::get_css( $attributes['breadcrumbLinkcolor'] );
		}

		return $css;
	}

	public function get_breadcrumb_hover_title_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['breadcrumbHoverLinkcolor'] ) ) {
			$css['color'] = Color::get_css( $attributes['breadcrumbHoverLinkcolor'] );
		}

		return $css;
	}

	public function get_breadcrumb_separator_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['breadcrumbseparatorcolor'] ) ) {
			$css['color'] = Color::get_css( $attributes['breadcrumbseparatorcolor'] );
		}

		if ( ! empty( $attributes['breadcrumbseparsize'] ) ) {
			// IMPORTANT: CSS property is kebab-case
			$css['font-size'] = $attributes['breadcrumbseparsize'] . 'px';
		}

		return $css;
	}
	public function get_breadcrumb_before_text_image_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['beforeBreadcrumbBackgroundcolor'] ) ) {
			$css['background-color'] = Color::get_css( $attributes['beforeBreadcrumbBackgroundcolor'] );
		}

		return array_merge(
			$css,
			Dimensions::get_css( $attributes['beforeBreadcrumbPaddingcolor'] ?? [], 'padding', $device ),
			Dimensions::get_css( $attributes['beforeBreadcrumbBorderRadius'] ?? [], 'border-radius', $device ),
		);
	}


	public function render( $attributes = [] ) {
		$breadcrumb_normalize     = $this->normalize( $attributes );
		$breadcrumb_items = $this->build_items( $breadcrumb_normalize );
		if ( empty( $breadcrumb_items ) ) {
			return;
		}
		$breadcrumb_items = $this->limit_items( $breadcrumb_items, (int) $breadcrumb_normalize['max'] );
		// phpcs:ignore  WordPress.Security.EscapeOutput.OutputNotEscaped 
		echo $this->render_html_breadcrumbs( $breadcrumb_items, $breadcrumb_normalize );
	}

	/**
	 * Defaults
	 */
	private function normalize( $attributes ) {
		$separator = isset( $attributes['SeparatorChange'] ) && $attributes['SeparatorChange'] !== ''
				? (string) $attributes['SeparatorChange']
				: '>';

		$home_text = ( isset( $attributes['homeBreadcrumbs'] ) && $attributes['homeBreadcrumbs'] !== '' )
				? (string) $attributes['homeBreadcrumbs']
				: __( 'Home', 'ablocks' );

		$beforeBreadcrumbImage = ( isset( $attributes['beforeBreadcrumbImage'] ) && $attributes['beforeBreadcrumbImage'] !== '' )
				? (string) $attributes['beforeBreadcrumbImage']
				: ''; // __( '', 'ablocks' );

		$beforeBreadcrumbText = ( isset( $attributes['beforeBreadcrumbText'] ) && $attributes['beforeBreadcrumbText'] !== '' )
				? (string) $attributes['beforeBreadcrumbText']
				: __( 'Before Text', 'ablocks' );

		$beforeBreadcrumbTextImage = ( isset( $attributes['beforeBreadcrumbTextImage'] ) && $attributes['beforeBreadcrumbTextImage'] !== '' )
				? (string) $attributes['beforeBreadcrumbTextImage']
				: ''; // __( '', 'ablocks' );

		$beforeTextImage = ( isset( $attributes['beforeTextImage'] ) && $attributes['beforeTextImage'] !== '' )
				? (string) $attributes['beforeTextImage']
				: ''; // __( '', 'ablocks' );

		$beforeSeparator = ( isset( $attributes['beforeSeparator'] ) && $attributes['beforeSeparator'] !== '' )
					? (string) $attributes['beforeSeparator']
					: ''; // __( '', 'ablocks' );

		$enable_faq_schema = false;
		if ( isset( $attributes['enableFaqSchema'] ) ) {
			$parsed_enable_faq_schema = filter_var( $attributes['enableFaqSchema'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			$enable_faq_schema = ( null === $parsed_enable_faq_schema ) ? false : $parsed_enable_faq_schema;
		}

		return wp_parse_args( (array) $attributes, [
			'separator'       => $separator,
			'home_text'       => $home_text,
			'before_breadcrumb_image'       => $beforeBreadcrumbImage,
			'before_breadcrumb_text'       => $beforeBreadcrumbText,
			'before_breadcrumb_text_image'       => $beforeBreadcrumbTextImage,
			'before_text_image'       => $beforeTextImage,
			'before_separator'       => $beforeSeparator,
			'enableFaqSchema'       => $enable_faq_schema,
			'include_current' => true,
			'tax'             => '',
			'max'             => 0,
		] );
	}

	/**
	 * Detect editor (post/site editor, AJAX, REST block render)
	 */
	private function in_editor() : bool {
		// More precise REST check: block renderer routes
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 
			$uri = $_SERVER['REQUEST_URI'] ?? '';
			if ( strpos( $uri, '/wp/v2/block-renderer' ) !== false
					|| strpos( $uri, '/wp-block-editor/v1/block-renderer' ) !== false ) {
				return true;
			}
		}

		if ( is_admin() ) {
			if ( function_exists( 'get_current_screen' ) ) {
				$scr = get_current_screen();
				if ( $scr ) {
					if ( ! empty( $scr->is_block_editor ) ) {
						return true;
					}
					if ( isset( $scr->base ) && $scr->base === 'site-editor' ) {
						return true;
					}
				}
			}
			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build breadcrumb items (editor-aware)
	 * Each: ['label' => string, 'url' => string|false]
	 */
	private function build_items( array $breadcrumb_normalize ) {
		if ( $this->in_editor() ) {
			return $this->build_items_editor( $breadcrumb_normalize );
		}
		$breadcrumb_items = [];

		// Front page
		if ( is_front_page() ) {
			$this->add_item( $breadcrumb_items, $breadcrumb_normalize['home_text'] );
			return $breadcrumb_items;
		}

		// Home
		$this->add_item( $breadcrumb_items, $breadcrumb_normalize['home_text'], home_url( '/' ) );

		// Posts page (static front)
		if ( is_home() && ! is_front_page() ) {
			$pid = (int) get_option( 'page_for_posts' );
			$this->add_item( $breadcrumb_items, $pid ? get_the_title( $pid ) : __( 'Blog', 'ablocks' ) );
			return $breadcrumb_items;
		}

		// Singular
		if ( is_singular() ) {
			global $post;

			if ( ! $post ) {
				if ( ! empty( $breadcrumb_normalize['include_current'] ) ) {
					$this->add_item( $breadcrumb_items, $this->current_title_safe() );
				}
				return $breadcrumb_items;
			}

			$breadcrumb_pt  = get_post_type( $post );
			$breadcrumb_pto = $breadcrumb_pt ? get_post_type_object( $breadcrumb_pt ) : null;

			// WooCommerce: prepend Shop for product
			$is_wc_product = ( 'product' === $breadcrumb_pt ) && function_exists( 'wc_get_page_id' ) && is_singular( 'product' );
			if ( $is_wc_product ) {
				$shop_id = wc_get_page_id( 'shop' );
				if ( $shop_id && 'publish' === get_post_status( $shop_id ) ) {
					$this->add_item( $breadcrumb_items, get_the_title( $shop_id ), get_permalink( $shop_id ) );
				}
			}

			// Post type label/archive (avoid for product since Shop already added)
			if ( ! $is_wc_product ) {
				$this->add_post_type_item( $breadcrumb_items, $breadcrumb_pt, $breadcrumb_pto, [ 'post', 'page' ] );
			}

			// Hierarchical (page / hierarchical CPT)
			if ( is_page() || ( $breadcrumb_pto && is_post_type_hierarchical( $breadcrumb_pt ) ) ) {
				$this->add_post_ancestors( $breadcrumb_items, $post );

				// Also include taxonomy trail (categories/tags) on single pages if available.
				$this->append_taxonomy_trail( $breadcrumb_items, $post->ID, $breadcrumb_pt, $breadcrumb_normalize['tax'] );
			} else {
				// Non-hierarchical: taxonomy trail (if any).
				$this->append_taxonomy_trail( $breadcrumb_items, $post->ID, $breadcrumb_pt, $breadcrumb_normalize['tax'] );
			}//end if

			if ( ! empty( $breadcrumb_normalize['include_current'] ) ) {
				$this->add_item( $breadcrumb_items, $this->current_title_safe( $post ) );
			}
			return $breadcrumb_items;
		}//end if

		// Tax archives
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			return ( $term && ! is_wp_error( $term ) )
				? $this->build_taxonomy_archive_items( $breadcrumb_items, $term )
				: $breadcrumb_items;
		}//end if

		// Post type archive
		if ( is_post_type_archive() ) {
			$breadcrumb_pt = get_query_var( 'post_type' );
			if ( is_array( $breadcrumb_pt ) ) {
				$breadcrumb_pt = reset( $breadcrumb_pt ); }
			$po = $breadcrumb_pt ? get_post_type_object( $breadcrumb_pt ) : null;
			if ( $po ) {
				$this->add_item( $breadcrumb_items, $po->labels->name );
				return $breadcrumb_items;
			}
		}

		// Author
		if ( is_author() ) {
			$breadcrumb_u = get_queried_object();
			if ( $breadcrumb_u ) {
				$this->add_item( $breadcrumb_items, sprintf( __( 'Author: %s', 'ablocks' ), $breadcrumb_u->display_name ) );
				return $breadcrumb_items;
			}
		}

		// Date
		if ( is_year() || is_month() || is_day() ) {
			$this->add_item( $breadcrumb_items, get_the_date( is_year() ? 'Y' : ( is_month() ? 'F Y' : 'F j, Y' ) ) );
			return $breadcrumb_items;
		}

		// Search
		if ( is_search() ) {
			$this->add_item( $breadcrumb_items, sprintf( __( 'Search: %s', 'ablocks' ), get_search_query() ) );
			return $breadcrumb_items;
		}

		// 404
		if ( is_404() ) {
			$this->add_item( $breadcrumb_items, __( '404 Not Found', 'ablocks' ) );
			return $breadcrumb_items;
		}

		return $breadcrumb_items;
	}

	/**
	 * Editor builder (mirrors frontend; robust current title)
	 */
	private function build_items_editor( array $breadcrumb_normalize ) : array {
		$breadcrumb_items   = [];
		$home_label = (string) $breadcrumb_normalize['home_text']; // attributes → fallback "Home"
		$this->add_item( $breadcrumb_items, $home_label, home_url( '/' ) );

		global $post;
		if ( $post instanceof \WP_Post ) {
			$breadcrumb_pt  = get_post_type( $post );
			$breadcrumb_pto = $breadcrumb_pt ? get_post_type_object( $breadcrumb_pt ) : null;

			if ( is_post_type_hierarchical( $breadcrumb_pt ) ) {
				$this->add_post_ancestors( $breadcrumb_items, $post );

				// Also include taxonomy trail (categories/tags) on single pages if available.
				$this->append_taxonomy_trail( $breadcrumb_items, $post->ID, $breadcrumb_pt, $breadcrumb_normalize['tax'] );
			} else {
				$this->add_post_type_item( $breadcrumb_items, $breadcrumb_pt, $breadcrumb_pto, [ 'post', 'page' ] );
				$this->append_taxonomy_trail( $breadcrumb_items, $post->ID, $breadcrumb_pt, $breadcrumb_normalize['tax'] );
			}//end if

			if ( ! empty( $breadcrumb_normalize['include_current'] ) ) {
				$this->add_item( $breadcrumb_items, $this->current_title_safe( $post ) );
			}
			return $breadcrumb_items;
		}//end if

		// No $post in editor (e.g., site editor template) → sample trail
		$this->add_item( $breadcrumb_items, __( 'Template', 'ablocks' ), home_url( '/Template/' ) );
		if ( ! empty( $breadcrumb_normalize['include_current'] ) ) {
			$this->add_item( $breadcrumb_items, __( 'Currents Page', 'ablocks' ) );
		}
		return $breadcrumb_items;
	}

	/**
	 * Safe current title (fallbacks when get_the_title() empty in editor)
	 */
	private function current_title_safe( $p = null ) : string {
		if ( $p instanceof \WP_Post ) {
			$breadcrumb_t = get_the_title( $p );
			if ( $breadcrumb_t === '' ) {
				$breadcrumb_t = $p->post_title;
			}
			if ( $breadcrumb_t === '' ) {
				$breadcrumb_t = __( '(no title)', 'ablocks' );
			}
			return $breadcrumb_t;
		}
		$breadcrumb_t = single_post_title( '', false );
		if ( $breadcrumb_t === '' ) {
			$breadcrumb_t = __( '(no title)', 'ablocks' );
		}
		return $breadcrumb_t;
	}

	/**
	 * Add a breadcrumb item.
	 */
	private function add_item( array &$breadcrumb_items, $label, $url = false ) : void {
		$breadcrumb_items[] = [
			'label' => (string) $label,
			'url' => $url
		];
	}

	/**
	 * Add a term breadcrumb item (linked).
	 */
	private function add_term_item( array &$breadcrumb_items, $term ) : void {
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}
		$link = get_term_link( $term );
		$this->add_item( $breadcrumb_items, $term->name, is_wp_error( $link ) ? false : $link );
	}

	/**
	 * Add post ancestors (linked).
	 */
	private function add_post_ancestors( array &$breadcrumb_items, $post ) : void {
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $aid ) {
			if ( (int) $aid === (int) get_option( 'page_on_front' ) ) {
				continue;
			}
			$this->add_item( $breadcrumb_items, get_the_title( $aid ), get_permalink( $aid ) );
		}
	}

	/**
	 * Add a post type item.
	 */
	private function add_post_type_item( array &$breadcrumb_items, $post_type, $post_type_obj = null, array $skip_types = [] ) : bool {
		if ( $post_type && in_array( $post_type, $skip_types, true ) ) {
			return false;
		}
		$breadcrumb_pto = $post_type_obj ?: ( $post_type ? get_post_type_object( $post_type ) : null );
		if ( ! $breadcrumb_pto ) {
			return false;
		}
		$url = ! empty( $breadcrumb_pto->has_archive ) ? get_post_type_archive_link( $post_type ) : false;
		$this->add_item( $breadcrumb_items, $breadcrumb_pto->labels->name, $url );
		return true;
	}

	/**
	 * Add term ancestors (linked).
	 */
	private function add_term_ancestors( array &$breadcrumb_items, $term ) : void {
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $id ) {
			$this->add_term_item( $breadcrumb_items, get_term( $id, $term->taxonomy ) );
		}
	}

	/**
	 * Append taxonomy trail (category + tag when available) to breadcrumb items.
	 * Returns true if any taxonomy items were appended.
	 */
	private function append_taxonomy_trail( array &$breadcrumb_items, int $post_id, string $post_type, string $preferred_tax = '' ) : bool {
		$breadcrumb_tax = $this->preferred_tax( $post_type, $preferred_tax );
		if ( ! $breadcrumb_tax ) {
			return false;
		}

		$secondary = ( $preferred_tax === '' ) ? $this->secondary_tax( $post_type, $breadcrumb_tax ) : '';
		$taxes = array_filter( array_unique( [ $breadcrumb_tax, $secondary ] ) );
		$seen_terms = [];
		foreach ( $taxes as $taxonomy ) {
			$primary = $this->primary_term( $post_id, $taxonomy );
			if ( ! $primary ) {
				continue;
			}

			foreach ( array_reverse( get_ancestors( $primary->term_id, $primary->taxonomy, 'taxonomy' ) ) as $id ) {
				$breadcrumb_t = get_term( $id, $primary->taxonomy );
				$key = $breadcrumb_t ? $breadcrumb_t->taxonomy . ':' . $breadcrumb_t->term_id : '';
				if ( ! $breadcrumb_t || is_wp_error( $breadcrumb_t ) || isset( $seen_terms[ $key ] ) ) {
					continue;
				}
				$seen_terms[ $key ] = true;
				$this->add_term_item( $breadcrumb_items, $breadcrumb_t );
			}
			$key = $primary->taxonomy . ':' . $primary->term_id;
			if ( ! isset( $seen_terms[ $key ] ) ) {
				$seen_terms[ $key ] = true;
				$this->add_term_item( $breadcrumb_items, $primary );
			}
		}//end foreach
		return ! empty( $seen_terms );
	}

	/**
	 * Preferred taxonomy
	 */
	private function preferred_tax( $post_type, $preferred = '' ) {
		if ( $preferred && taxonomy_exists( $preferred ) ) {
			return $preferred;
		}
		$taxes = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxes as $breadcrumb_t ) {
			if ( $breadcrumb_t->public && $breadcrumb_t->hierarchical ) {
				return $breadcrumb_t->name;
			}
		}
		foreach ( $taxes as $breadcrumb_t ) {
			if ( $breadcrumb_t->public ) {
				return $breadcrumb_t->name;
			}
		}
		return false;
	}

	/**
	 * Secondary taxonomy (first public non-hierarchical) when primary is hierarchical.
	 */
	private function secondary_tax( $post_type, $primary_tax = '' ) {
		$primary = $primary_tax ? get_taxonomy( $primary_tax ) : null;
		if ( $primary && empty( $primary->hierarchical ) ) {
			return false;
		}

		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $breadcrumb_t ) {
			if ( $breadcrumb_t->public && ! $breadcrumb_t->hierarchical && $breadcrumb_t->name !== $primary_tax ) {
				return $breadcrumb_t->name;
			}
		}
		return false;
	}

	/**
	 * Get a representative post type for a taxonomy.
	 */
	private function taxonomy_post_type( $taxonomy ) {
		$tax = $taxonomy ? get_taxonomy( $taxonomy ) : null;
		if ( ! $tax || empty( $tax->object_type ) ) {
			return false;
		}
		$object_types = (array) $tax->object_type;
		if ( in_array( 'listing', $object_types, true ) ) {
			return 'listing';
		}
		return reset( $object_types );
	}

	/**
	 * Build taxonomy archive items: Post Type > Term ancestors > Term (linked).
	 */
	private function build_taxonomy_archive_items( array $breadcrumb_items, $term ) : array {
		$breadcrumb_pt = $this->taxonomy_post_type( $term->taxonomy );
		$breadcrumb_pto = $breadcrumb_pt ? get_post_type_object( $breadcrumb_pt ) : null;
		$this->add_post_type_item( $breadcrumb_items, $breadcrumb_pt, $breadcrumb_pto );

		$this->add_term_ancestors( $breadcrumb_items, $term );
		$this->add_term_item( $breadcrumb_items, $term );

		return $breadcrumb_items;
	}

	/**
	 * Primary term (Yoast) or first term
	 */
	private function primary_term( $post_id, $taxonomy ) {
		if ( class_exists( 'WPSEO_Primary_Term' ) ) {
			$yoast = new \WPSEO_Primary_Term( $taxonomy, $post_id );
			$id    = (int) $yoast->get_primary_term();
			if ( $id ) {
				$breadcrumb_t = get_term( $id, $taxonomy );
				if ( $breadcrumb_t && ! is_wp_error( $breadcrumb_t ) ) {
					return $breadcrumb_t;
				}
			}
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		return ( $terms && ! is_wp_error( $terms ) ) ? array_shift( $terms ) : null;
	}

	/**
	 * Tail limit
	 */
	private function limit_items( array $breadcrumb_items, int $max ) {
		if ( $max > 0 && count( $breadcrumb_items ) > $max ) {
			return array_slice( $breadcrumb_items, - $max );
		}
		return $breadcrumb_items;
	}

	/**
	 * Best-effort current request URL for schema item fallback.
	 */
	private function current_request_url() : string {
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
			$request_uri = esc_url_raw( $request_uri );

			return home_url( $request_uri );
		}

		$permalink = get_permalink();
		if ( ! empty( $permalink ) ) {
			return $permalink;
		}

		return home_url( '/' );
	}

	/**
	 * Breadcrumbs-style HTML (matches Breadcrumbs classes; includes microdata)
	 */
	private function render_html_breadcrumbs( array $breadcrumb_items, array $breadcrumb_normalize ) {
		$enable_schema = ! empty( $breadcrumb_normalize['enableFaqSchema'] );
		ob_start();
		?>
		<div aria-label="<?php esc_attr_e( 'breadcrumb', 'ablocks' ); ?>"
			 class="ablocks-breadcrumbs"
			 role="navigation"
			<?php if ( $enable_schema ) : ?>
			 itemscope
			 itemtype="https://schema.org/BreadcrumbList"
			<?php endif; ?>>

			<?php

			if ( $breadcrumb_normalize['beforeTextImage'] == 'true' ) : ?>
				<?php if ( $breadcrumb_normalize['beforeBreadcrumbTextImage'] == 'text' ) : ?>
					<span class="ablocks-breadcrumbs__before-text ablocks-breadcrumbs__item">
						<?php echo esc_html( $breadcrumb_normalize['beforeBreadcrumbText'] ); ?>
					</span>
					<?php if ( $breadcrumb_normalize['beforeSeparator'] == 'true' ) : ?>
					<span class="ablocks-breadcrumbs__separator"><?php echo esc_html( $breadcrumb_normalize['separator'] ); ?></span>
					<?php endif; ?>
				<?php elseif ( $breadcrumb_normalize['beforeBreadcrumbTextImage'] == 'image' ) : ?>
					<span class="ablocks-breadcrumbs___before-image"><img src="<?php echo esc_url( $breadcrumb_normalize['before_breadcrumb_image'] ); ?>" alt="">
					</span>
					<?php if ( $breadcrumb_normalize['beforeSeparator'] == 'true' ) : ?>
						<span class="ablocks-breadcrumbs__separator"><?php echo esc_html( $breadcrumb_normalize['separator'] ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>

					<?php foreach ( $breadcrumb_items as $i => $breadcrumb_it ) : ?>
								<span class="ablocks-breadcrumbs__item"
									<?php if ( $enable_schema ) : ?>
										itemprop="itemListElement"
										itemscope
										itemtype="https://schema.org/ListItem"
									<?php endif; ?>>

							<?php if ( ! empty( $breadcrumb_it['url'] ) ) : ?>
								<a <?php if ( $enable_schema ) : ?>itemprop="item"<?php endif; ?> href="<?php echo esc_url( $breadcrumb_it['url'] ); ?>">
									<span <?php if ( $enable_schema ) : ?>itemprop="name"<?php endif; ?>><?php echo esc_html( $breadcrumb_it['label'] ); ?></span>
								</a>
							<?php else : ?>
								<span class="ablocks-breadcrumbs__item-current" <?php if ( $enable_schema ) : ?>itemprop="name"<?php endif; ?>><?php echo esc_html( $breadcrumb_it['label'] ); ?></span>
								<?php $current_item_url = $this->current_request_url(); ?>
								<?php if ( $enable_schema && ! empty( $current_item_url ) ) : ?>
									<meta itemprop="item" content="<?php echo esc_url( $current_item_url ); ?>" />
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( $enable_schema ) : ?>
								<meta itemprop="position" content="<?php echo (int) ( $i + 1 ); ?>" />
							<?php endif; ?>
						</span>
				<?php if ( $i < count( $breadcrumb_items ) - 1 ) : ?>
					<span class="ablocks-breadcrumbs__separator"><?php echo esc_html( $breadcrumb_normalize['separator'] ); ?></span>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
		return trim( ob_get_clean() );
	}


	/**
	 * Block → PHP SSR entry (use in render_callback)
	 */
	public function render_block_content( $attributes, $content = '', $block_instance = null ) {
		$breadcrumb_normalize = $this->normalize( $attributes );

		// --- Ensure correct $post in editor/preview (REST) ---
		$restore_post = null;

		if ( $block_instance && ! empty( $block_instance->context['postId'] ) ) {
			$maybe = get_post( (int) $block_instance->context['postId'] );

			if ( $maybe instanceof \WP_Post ) {
				global $post;
				$restore_post = $post ?? null;
				$post = $maybe;
				setup_postdata( $post );
			}
		}

		$breadcrumb_items = $this->build_items( $breadcrumb_normalize );

		if ( $restore_post !== null ) {
			wp_reset_postdata();
			global $post;
			$post = $restore_post;
		}

		if ( empty( $breadcrumb_items ) ) {
			return '';
		}
		$breadcrumb_items = $this->limit_items( $breadcrumb_items, (int) $breadcrumb_normalize['max'] );

		return $this->render_html_breadcrumbs( $breadcrumb_items, $breadcrumb_normalize );
	}


}
