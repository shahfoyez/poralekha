<?php

namespace ABlocks\Blocks\taxonomyListing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;

use WP_Query;

class Block extends BlockBaseAbstract {


	protected $block_name = 'taxonomy-listing';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$wrapper_styles = $this->get_wrapper_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$wrapper_styles,
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		$card_styles = $this->get_card_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-listing-item',
			$card_styles,
			$this->get_card_css( $attributes, 'Tablet' ),
			$this->get_card_css( $attributes, 'Mobile' )
		);

		$card_hover_styles = $this->get_card_hover_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-listing-item:hover',
			$card_hover_styles,
			$this->get_card_hover_css( $attributes, 'Tablet' ),
			$this->get_card_hover_css( $attributes, 'Mobile' )
		);

		$card_padding_styles = $this->get_card_padding_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-listing-item_content',
			$card_padding_styles,
			$this->get_card_padding_css( $attributes, 'Tablet' ),
			$this->get_card_padding_css( $attributes, 'Mobile' )
		);

		$taxonomy_title_styles = $this->get_taxonomy_title_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-title',
			$taxonomy_title_styles,
			$this->get_taxonomy_title_css( $attributes, 'Tablet' ),
			$this->get_taxonomy_title_css( $attributes, 'Mobile' )
		);
		$taxonomy_title_hover_styles = $this->get_taxonomy_hover_title_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-title:hover',
			$taxonomy_title_hover_styles,
			$this->get_taxonomy_hover_title_css( $attributes, 'Tablet' ),
			$this->get_taxonomy_hover_title_css( $attributes, 'Mobile' )
		);
		// Icon style
		$icons_style = $this->get_icons( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-icon',
			$icons_style,
			$this->get_icons( $attributes, 'Tablet' ),
			$this->get_icons( $attributes, 'Mobile' )
		);
		// Icon style
		$icons_svg_style = $this->get_icons_svg( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-icon svg',
			$icons_svg_style,
			$this->get_icons_svg( $attributes, 'Tablet' ),
			$this->get_icons_svg( $attributes, 'Mobile' )
		);
		// Icon style
		$icon_svg_path_style = $this->get_icon_svg_path( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-icon svg path',
			$icon_svg_path_style,
			$this->get_icon_svg_path( $attributes, 'Tablet' ),
			$this->get_icon_svg_path( $attributes, 'Mobile' )
		);
		// Post Title Start
		$post_title_styles = $this->get_post_title_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-posts a',
			$post_title_styles,
			$this->get_post_title_css( $attributes, 'Tablet' ),
			$this->get_post_title_css( $attributes, 'Mobile' )
		);

		$post_hover_styles = $this->get_post_hover_title_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-posts a:hover',
			$post_hover_styles,
			$this->get_post_hover_title_css( $attributes, 'Tablet' ),
			$this->get_post_hover_title_css( $attributes, 'Mobile' )
		);
		$post_active_styles = $this->get_post_active_title_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-posts a.active-post-link',
			$post_active_styles,
			$this->get_post_active_title_css( $attributes, 'Tablet' ),
			$this->get_post_active_title_css( $attributes, 'Mobile' )
		);
		// Post Title End

		$post_count_styles = $this->get_post_title_count_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-title__post-count',
			$post_count_styles,
			$this->get_post_title_count_css( $attributes, 'Tablet' ),
			$this->get_post_title_count_css( $attributes, 'Mobile' )
		);

		$post_count_hover_styles = $this->get_post_title_hover_count_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-taxonomy-title__post-count:hover',
			$post_count_hover_styles,
			$this->get_post_title_hover_count_css( $attributes, 'Tablet' ),
			$this->get_post_title_hover_count_css( $attributes, 'Mobile' )
		);

		$button_styles = $this->get_button_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-reload-button',
			$button_styles,
			$this->get_button_css( $attributes, 'Tablet' ),
			$this->get_button_css( $attributes, 'Mobile' )
		);

		$button_hover_styles = $this->get_button_hover_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-reload-button:hover',
			$button_hover_styles,
			$this->get_button_hover_css( $attributes, 'Tablet' ),
			$this->get_button_hover_css( $attributes, 'Mobile' )
		);
		// Icon Style

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' )
		);
		// Layout grid

		if ( $attributes['taxonomyLayout'] === 'flex' ) {
			$main_wrapper_css = $this->get_card_wrapper( $attributes );
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-taxonomy-listing_flex',
				$main_wrapper_css,
				$this->get_card_wrapper( $attributes, 'Tablet' ),
				$this->get_card_wrapper( $attributes, 'Mobile' )
			);
		}

		return $css_generator->generate_css();
	}

	public function get_wrapper_css( $attributes, $device = '' ) {
		return isset( $attributes['alignment'] )
			? Alignment::get_css( $attributes['alignment'], 'text-align', $device )
			: [];
	}


	public function get_taxonomy_title_css( $attributes, $device = '' ) {
		$css = [];

		// Color
		if ( ! empty( $attributes['taxonomyTitlecolor'] ) ) {
			$css['color'] = $attributes['taxonomyTitlecolor'];
		}

		// Background
		if ( ! empty( $attributes['taxonomyTitletBgColor'] ) ) {
			$css['background'] = $attributes['taxonomyTitletBgColor'];
		}

		// Justify content (alignment)
		if ( isset( $attributes['taxonomyTitlePosition'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['taxonomyTitlePosition'][ 'value' . $device ];
		}

		// Flex direction
		if ( isset( $attributes['taxonomyTitleDirection'][ 'value' . $device ] ) ) {
			$css['flex-direction'] = $attributes['taxonomyTitleDirection'][ 'value' . $device ];
		}
		$typographyValueGlobal = ! empty( $attributes['taxonomyTitleTypographyGlobal'] ) ? $attributes['taxonomyTitleTypographyGlobal'] : [];
		return array_merge(
			Typography::get_css( $attributes['taxonomyTitleTypography'] ?? [], '', $device, $typographyValueGlobal ),
			Border::get_css( $attributes['taxonomyTitleBorder'] ?? [], '', $device ),
			Dimensions::get_css( $attributes['taxonomyTitlePadding'] ?? [], 'padding', $device ),
			$css
		);
	}

	public function get_taxonomy_hover_title_css( $attributes, $device = '' ) {
		$css = [];

		$taxonomy_title_border = ! empty( $attributes['taxonomyTitleBorder'] ) ? Border::get_hover_css( $attributes['taxonomyTitleBorder'], '', $device ) : array();

		return array_merge(
			$taxonomy_title_border
		);
	}

	public function get_post_title_count_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['postCountColor'] ) ) {
			$css['color'] = $attributes['postCountColor'];
		}
		if ( ! empty( $attributes['postCountBgColor'] ) ) {
			$css['background'] = $attributes['postCountBgColor'];
		}
		if ( ! empty( $attributes['postCountWidth'] ) ) {
			$css['width'] = $attributes['postCountWidth'] . 'px';
		}
		if ( ! empty( $attributes['postCountWidth'] ) ) {
			$css['height'] = $attributes['postCountWidth'] . 'px';
		}
		$typographyValueGlobal = ! empty( $attributes['postCountTypographyGlobal'] ) ? $attributes['postCountTypographyGlobal'] : [];
		return array_merge(
			Border::get_css( $attributes['postCountBorder'] ?? [], '', $device ),
			Dimensions::get_css( $attributes['padding'] ?? [], 'padding', $device ),
			Typography::get_css( $attributes['postCountTypography'] ?? [], '', $device, $typographyValueGlobal ),
			$css
		);
	}

	public function get_post_title_hover_count_css( $attributes, $device = '' ) {
		$post_count_hover_border_style = ! empty( $attributes['postCountBorder'] ) ? Border::get_hover_css( $attributes['postCountBorder'], '', $device ) : array();
		return array_merge(
			$post_count_hover_border_style
		);
	}

	public function get_card_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['cardBgColor'] ) ) {
			$css['background'] = $attributes['cardBgColor'];
		}

		return array_merge(
			Border::get_css( $attributes['cardBorder'] ?? [], '', $device ),
			BoxShadow::get_css( $attributes['cardBoxShadow'] ?? [], '', $device ),
			$css
		);
	}

	public function get_card_hover_css( $attributes, $device = '' ) {
		$css = [];
		$card_hover_border = ! empty( $attributes['cardBorder'] ) ? Border::get_hover_css( $attributes['cardBorder'], '', $device ) : array();
		if ( ! empty( $attributes['cardBgHoverColor'] ) ) {
			$css['background'] = $attributes['cardBgHoverColor'];
		}

		return array_merge(
			$css,
			$card_hover_border,
			BoxShadow::get_hover_css( $attributes['cardBoxShadow'], '', $device ),
		);
	}

	public function get_card_padding_css( $attributes, $device = '' ) {
		return Dimensions::get_css( $attributes['cardPadding'] ?? [], 'padding', $device );
	}

	// Taxonomy Title Start
	public function get_post_title_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['postTitleColor'] ) ) {
			$css['color'] = $attributes['postTitleColor'];
		}

		if ( ! empty( $attributes['postBackgroundColor'] ) ) {
			$css['background'] = $attributes['postBackgroundColor'];
		}
		$typographyValueGlobal = ! empty( $attributes['postTitleTypographyGlobal'] ) ? $attributes['postTitleTypographyGlobal'] : [];

		$typography = Typography::get_css($attributes['postTitleTypography'] ?? [], [
			'weight' => '500',
		], $device, $typographyValueGlobal);

		$postpadding = Dimensions::get_css( $attributes['postPadding'] ?? [], 'padding', $device );

		$border = Border::get_css($attributes['postBorder'] ?? [], [
			'unitWidth' => 'px',
			'unitRadius' => 'px',
		], $device);

		return array_merge(
			$css,
			$typography,
			$postpadding,
			$border
		);
	}

	public function get_post_hover_title_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['postTitleHoverColor'] ) ) {
			$css['color'] = $attributes['postTitleHoverColor'];
		}

		if ( ! empty( $attributes['hoverPostBackgroundColor'] ) ) {
			$css['background'] = $attributes['hoverPostBackgroundColor'];
		}

		return $css;
	}

	public function get_post_active_title_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['postTitleActiveColor'] ) ) {
			$css['color'] = $attributes['postTitleActiveColor'];
		}

		if ( ! empty( $attributes['activePostBackgroundColor'] ) ) {
			$css['background'] = $attributes['activePostBackgroundColor'];
		}

		$border = Border::get_css($attributes['activePostBorder'] ?? [], [
			'unitWidth' => 'px',
			'unitRadius' => 'px',
		], $device);

		return array_merge(
			$css,
			$border
		);
	}

	// Post Title End
	public function get_button_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['buttonColor'] ) ) {
			$css['color'] = $attributes['buttonColor'];
		}

		if ( ! empty( $attributes['buttonBgColor'] ) ) {
			$css['background'] = $attributes['buttonBgColor'];
		}

		// Display
		if ( isset( $attributes['buttonPosition'][ 'value' . $device ] ) ) {
			$css['display'] = $attributes['buttonPosition'][ 'value' . $device ];
		}
		$typographyValueGlobal = ! empty( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : [];

		return array_merge(
			Typography::get_css( $attributes['buttonTypography'] ?? [], '', $device, $typographyValueGlobal ),
			Border::get_css( $attributes['buttonBorder'] ?? [], '', $device ),
			Dimensions::get_css( $attributes['buttonPadding'] ?? [], 'padding', $device ),
			$css
		);
	}

	public function get_button_hover_css( $attributes, $device = '' ) {
		$css = [];
		$button_hover_border = ! empty( $attributes['buttonBorder'] ) ? Border::get_hover_css( $attributes['buttonBorder'], '', $device ) : array();
		if ( ! empty( $attributes['buttonHoverColor'] ) ) {
			$css['color'] = $attributes['buttonHoverColor'];
		}
		if ( ! empty( $attributes['buttonHoverBgColor'] ) ) {
			$css['background'] = $attributes['buttonHoverBgColor'];
		}

		return array_merge(
			$button_hover_border,
			$css
		);
	}

	// Icon Style

	public function get_icons( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['iconBgColor'] ) ) {
			$css['background'] = $attributes['iconBgColor'];
		}

		return array_merge(
			Dimensions::get_css( $attributes['iconPadding'] ?? [], 'padding', $device ),
			Dimensions::get_css( $attributes['iconBorderRadius'] ?? [], 'border-radius', $device ),
			$css
		);
	}

	public function get_icons_svg( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['iconsSize'] ) ) {
			$css['width'] = $attributes['iconsSize'] . 'px';
		}
		if ( ! empty( $attributes['iconsSize'] ) ) {
			$css['height'] = $attributes['iconsSize'] . 'px';
		}

		return array_merge(
			$css
		);
	}

	public function get_icon_svg_path( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['iconColor'] ) ) {
			$css['fill'] = $attributes['iconColor'];
		}

		return array_merge(
			$css
		);
	}

	public function get_card_wrapper( $attributes, $device = '' ) {
		$css = [];
		if ( $attributes['taxonomyLayout'] === 'flex' ) {
			$css['display'] = 'grid';
			$css['gap'] = '1.5em';
			if ( $device === 'Tablet' ) {
				$css['grid-template-columns'] = 'repeat(2, 1fr)';
			} elseif ( $device === 'Mobile' ) {
				$css['grid-template-columns'] = 'repeat(1, 1fr)';
			} elseif ( ! empty( $attributes['itemsPerRow'] ) ) {
				$css['grid-template-columns'] = 'repeat(' . $attributes['itemsPerRow'] . ', 1fr)';
			}
		}
		return array_merge(
			$css
		);
	}

	public function render_callback( $attributes, $content, $block_instance ) {
		$content = '';
		return parent::render_callback( $attributes, $content, $block_instance );
	}


	// Markup Start

	protected function inherit_selected_terms( array $active_taxonomy, array $selected_terms, string $selected_post_type, bool $show_inherit ): array {
		if ( ! $show_inherit || empty( $active_taxonomy ) ) {
			return $selected_terms;
		}

		$queried_object = get_queried_object();

		if ( is_tax() || is_post_type_archive() ) {
			foreach ( $active_taxonomy as $taxonomy ) {
				if ( isset( $queried_object->taxonomy ) && $queried_object->taxonomy === $taxonomy ) {
					$selected_terms[ $taxonomy ] = [ $queried_object->term_id ];
				} else {
					$terms = get_terms([
						'taxonomy' => $taxonomy,
						'hide_empty' => true
					]);
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$selected_terms[ $taxonomy ] = wp_list_pluck( $terms, 'term_id' );
					}
				}
			}
		} elseif ( is_singular( $selected_post_type ) && $queried_object instanceof \WP_Post ) {
			foreach ( $active_taxonomy as $taxonomy ) {
				$terms = get_the_terms( $queried_object->ID, $taxonomy );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$selected_terms[ $taxonomy ] = wp_list_pluck( $terms, 'term_id' );
				}
			}
		} else {
			foreach ( $active_taxonomy as $taxonomy ) {
				$terms = get_terms([
					'taxonomy' => $taxonomy,
					'hide_empty' => true
				]);
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$selected_terms[ $taxonomy ] = wp_list_pluck( $terms, 'term_id' );
				}
			}
		}//end if

		return $selected_terms;
	}

	/**
	 * Taxonomy Wise Query
	 */
	protected function build_term_query( string $taxonomy, int $term_id, string $post_type, int $per_page, string $orderby, string $order ): \WP_Query {
		return new \WP_Query([
			'post_type' => $post_type,
			'post_status' => 'publish',
			'posts_per_page' => $per_page,
			'orderby' => $orderby,
			'order' => $order,
			'tax_query' => [
				[
					'taxonomy' => $taxonomy,
					'field' => 'term_id',
					'terms' => $term_id,
				]
			],
		]);
	}

	/**
	 * Accordion Open Use current post ID Check
	 */
	protected function should_open_section( \WP_Query $query, int $current_post_id ): bool {
		if ( ! $current_post_id || ! $query->have_posts() ) {
			return false;
		}
		$open = false;
		while ( $query->have_posts() ) {
			$query->the_post();
			if ( get_the_ID() === $current_post_id ) {
				$open = true;
				break;
			}
		}
		$query->rewind_posts();
		wp_reset_postdata();
		return $open;
	}

	/**
	 * Post ID Use Active class
	 */
	protected function render_posts_list( \WP_Query $query, bool $show_post, int $current_post_id ): void {
		if ( ! $query->have_posts() || ! $show_post ) {
			return;
		}
		echo '<ul class="ablocks-taxonomy-posts">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_link = get_permalink();
			$is_active = ( get_the_ID() === $current_post_id ) ? ' active-post-link' : '';
			echo '<a class="ablocks-post-link' . esc_attr( $is_active ) . '" href="' . esc_url( $post_link ) . '" rel="noreferrer">' . esc_html( get_the_title() ) . '</a>';
		}
		wp_reset_postdata();
		echo '</ul>';
	}

	/**
	 * Reload Button
	 */
	protected function render_reload_button( bool $enable_reload, $term_link, string $button_text ): void {
		if ( ! $enable_reload || is_wp_error( $term_link ) ) {
			return;
		}
		echo '<div class="ablocks-reload-button-wrapper">';
		echo '<a href="' . esc_url( $term_link ) . '" class="ablocks-reload-button">' . esc_html( $button_text ) . '</a>';
		echo '</div>';
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$taxonomy_layout = $attributes['taxonomyLayout'] ?? 'list';
		$page_links = $attributes['pageLinks'] ?? 'singlePagelink';
		$items_per_row = $attributes['itemsPerRow'] ?? 3;
		$active_taxonomy = $attributes['activeTaxonomy'] ?? [];
		$selected_terms = $attributes['selectedTerms'] ?? [];
		$selected_post_type = $attributes['selectedPostType'] ?? 'post';
		$number_of_posts = $attributes['numberOfPosts'] ?? 6;
		$post_order = $attributes['postOrder'] ?? 'desc';
		$post_order_by = $attributes['postOrderBy'] ?? 'date';
		$show_term_count = $attributes['showTermCount'] ?? false;
		$enable_reload = $attributes['enableReloadButton'] ?? false;
		$allow_icon = $attributes['allowIcon'] ?? false;
		$Button_text = $attributes['buttonText'] ?? 'View More';
		$tab_view = $attributes['showTab'] ?? false;
		$showTabScrolling = $attributes['showTabScrolling'] ?? false;
		$icon_class = $attributes['iconClass'] ?? 'far fa-copy';
		$icon_svg = $attributes['iconSvgPath'] ?? '';
		$icon_url = $attributes['iconImageUrl'] ?? '';
		$show_post_link = $attributes['showPostLink'] ?? false;
		$show_post = $attributes['showPost'] ?? false;
		$show_inherit = $attributes['showInherit'] ?? false;
		$show_Count_Prefix = $attributes['showTermCountPrefix'] ?? false;
		$showIconDffent = $attributes['showIconDffent'] ?? false;
		$count_prefix_text = $attributes['countPrefixText'] ?? 'Items';

		$selected_terms = $this->inherit_selected_terms( $active_taxonomy, $selected_terms, $selected_post_type, $show_inherit );

		ob_start();

		$inline_style = '';
		$use_accordion = ( $taxonomy_layout === 'list' && $tab_view );

		$current_post_id = get_queried_object_id();
		$current_term_id = 0;
		$current_taxonomy = '';
		if ( is_category() || is_tag() || is_tax() ) {
			$current_term = get_queried_object();
			if ( $current_term && ! is_wp_error( $current_term ) ) {
				$current_term_id = (int) $current_term->term_id;
				$current_taxonomy = (string) $current_term->taxonomy;
			}
		}

		$showTabScrollingtrue = $showTabScrolling ? 'ablocks-taxonomy-listing-scrolling' : '';

		?>
		<div class="ablocks-taxonomy-listing_<?php echo esc_attr( $taxonomy_layout ); ?> <?php echo esc_attr( $showTabScrollingtrue ); ?>" <?php
		// phpcs:ignore  WordPress.Security.EscapeOutput.OutputNotEscaped 
		echo $inline_style; ?>>

			<?php
			$accordion_index = 1;
			$accordion_name  = 'ablocks-blower-group-' . ( $attributes['block_id'] ?? '' );

			// Prime term caches for every listed term in one query so the per-term
			// get_term()/get_term_link()/get_taxonomy() calls below are served from
			// cache instead of hitting the DB per term.
			$all_term_ids = [];
			foreach ( $active_taxonomy as $prime_taxonomy ) {
				foreach ( ( $selected_terms[ $prime_taxonomy ] ?? [] ) as $prime_term_id ) {
					$all_term_ids[] = (int) $prime_term_id;
				}
			}
			if ( ! empty( $all_term_ids ) ) {
				_prime_term_caches( array_values( array_unique( $all_term_ids ) ) );
			}

			foreach ( $active_taxonomy as $taxonomy ) :
				$term_ids = $selected_terms[ $taxonomy ] ?? [];

				foreach ( $term_ids as $term_id ) :
					$term = get_term( $term_id, $taxonomy );

					if ( ! $term || is_wp_error( $term ) ) {
						continue; }

					$query = $this->build_term_query( $taxonomy, (int) $term_id, $selected_post_type, (int) $number_of_posts, $post_order_by, $post_order );
					$term_public_count = (int) $query->found_posts;

					$input_id    = 'ablocks-blower-' . $accordion_index++;
					$should_open = $this->should_open_section( $query, (int) $current_post_id );
					if (
						$use_accordion &&
						$showTabScrolling &&
						$current_term_id &&
						$current_taxonomy === $taxonomy &&
						(int) $term_id === $current_term_id
					) {
						$should_open = true;
					}

					$term_link  = get_term_link( $term );

					$taxonomy   = $term->taxonomy;
					$tax_obj    = get_taxonomy( $taxonomy );
					$post_types = ( $tax_obj && ! empty( $tax_obj->object_type ) )
						? array_values( array_unique( $tax_obj->object_type ) )
						: 'any';

					$post_ids = get_posts( [
						'post_type'           => $post_types,
						'fields'              => 'ids',
						'post_status'         => 'publish',
						'posts_per_page'      => 1,
						'orderby'             => $post_order_by,
						'order'               => $post_order,
						'ignore_sticky_posts' => true,
						'no_found_rows'       => true,
						'tax_query'           => [
							[
								'taxonomy' => $taxonomy,
								'field'    => 'term_id',
								'terms'    => (int) $term->term_id,
							],
						],
					] );

					$first_post_id   = ! empty( $post_ids ) ? (int) $post_ids[0] : 0;
					$first_post_link = $first_post_id ? get_permalink( $first_post_id ) : '';

					$link_url = '';
					if ( $show_post_link && ! is_wp_error( $term_link ) ) {
						if ( $page_links === 'singlePagelink' && $first_post_link ) {
							$link_url = $first_post_link;
						} elseif ( $page_links === 'archivePagelink' ) {
							$link_url = $term_link;
						}
					}
					?>

					<?php if ( $use_accordion ) : ?>
						<div class="ablocks-taxonomy-blower ablocks-taxonomy-listing-item">
						<input type="checkbox" name="<?php echo esc_attr( $accordion_name ); ?>" id="<?php echo esc_attr( $input_id ); ?>" <?php echo $should_open ? 'checked' : ''; ?>>
						<?php if ( $link_url ) :
							?><a class="ablocks-taxonomy-title__link" href="<?php echo esc_url( $link_url ); ?>"><?php endif; ?>
						<label for="<?php echo esc_attr( $input_id ); ?>" class="ablocks-taxonomy-blower__label ablocks-taxonomy-title">
					<?php else : ?>
						<div class="ablocks-taxonomy-listing-item">
						<?php if ( $link_url ) :
							?><a class="ablocks-taxonomy-title__link" href="<?php echo esc_url( $link_url ); ?>"><?php endif; ?>
						<h3 class="ablocks-taxonomy-title">
					<?php endif; ?>

						<?php if ( $allow_icon && ( $showIconDffent == true ) ) : ?>
							<span class="ablocks-taxonomy-icon">
								<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M245,110.64A16,16,0,0,0,232,104H216V88a16,16,0,0,0-16-16H130.67L102.94,51.2a16.14,16.14,0,0,0-9.6-3.2H40A16,16,0,0,0,24,64V208h0a8,8,0,0,0,8,8H211.1a8,8,0,0,0,7.59-5.47l28.49-85.47A16.05,16.05,0,0,0,245,110.64ZM93.34,64,123.2,86.4A8,8,0,0,0,128,88h72v16H69.77a16,16,0,0,0-15.18,10.94L40,158.7V64Zm112,136H43.1l26.67-80H232Z"></path></svg>
							</span>
						<?php endif; ?>

						<span class="<?php echo $showIconDffent === true ? 'ablocks-taxonomy-listing_direction' : ''; ?>">
							<?php if ( $allow_icon && ( $showIconDffent == false ) ) : ?>
								<span class="ablocks-taxonomy-icon">
									<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M245,110.64A16,16,0,0,0,232,104H216V88a16,16,0,0,0-16-16H130.67L102.94,51.2a16.14,16.14,0,0,0-9.6-3.2H40A16,16,0,0,0,24,64V208h0a8,8,0,0,0,8,8H211.1a8,8,0,0,0,7.59-5.47l28.49-85.47A16.05,16.05,0,0,0,245,110.64ZM93.34,64,123.2,86.4A8,8,0,0,0,128,88h72v16H69.77a16,16,0,0,0-15.18,10.94L40,158.7V64Zm112,136H43.1l26.67-80H232Z"></path></svg>
								</span>
							<?php endif; ?>

							<?php
								echo esc_html( $term->name );

							if ( $show_term_count && ( $showIconDffent == true ) ) :
								?>
								<span class="ablocks-taxonomy-title__post-count">
									
								<?php echo $term_public_count; ?><?php if ( $show_Count_Prefix ) : ?>
										<span><?php echo esc_html( $count_prefix_text ); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</span>

						<?php if ( $show_term_count && ( $showIconDffent == false ) ) : ?>
							<span class="ablocks-taxonomy-title__post-count">
								<?php echo $term_public_count; ?><?php if ( $show_Count_Prefix ) : ?>
									<span><?php echo esc_html( $count_prefix_text ); ?></span>
								<?php endif; ?>
							</span>
						<?php endif; ?>

					<?php
					echo $use_accordion ? '</label>' : '</h3>';

					if ( $link_url ) {
						echo '</a>';
					}
					?>
					<?php if ( $show_post ) :

						echo $use_accordion ? '<div class="ablocks-taxonomy-blower__content ablocks-taxonomy-listing-item_content">' : '<div class="ablocks-taxonomy-listing-item_content">'; ?>

						<?php if ( $query->have_posts() ) : ?>

							<?php

							$this->render_posts_list( $query, $show_post, (int) $current_post_id );

							$this->render_reload_button( (bool) $enable_reload, $term_link, (string) $Button_text );
							?>

				<?php else : ?>
					<p><?php echo esc_html__( 'No posts found.', 'ablocks' ); ?></p>
				<?php endif; ?>

					</div>
					<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>


		</div>
		<?php
		return ob_get_clean();
	}

}
