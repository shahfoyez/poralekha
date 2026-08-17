<?php
namespace ABlocks\Blocks\NewsTicker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'news-ticker';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker__label',
			$this->get_label_css( $attributes ),
			$this->get_label_css( $attributes, 'Tablet' ),
			$this->get_label_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker__label:hover',
			$this->get_label_color_hover_css( $attributes ),
			$this->get_label_color_hover_css( $attributes, 'Tablet' ),
			$this->get_label_color_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker',
			$this->get_ticker_content_css( $attributes ),
			$this->get_ticker_content_css( $attributes, 'Tablet' ),
			$this->get_ticker_content_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker__marquee',
			$this->get_ticker_color_css( $attributes ),
			$this->get_ticker_color_css( $attributes, 'Tablet' ),
			$this->get_ticker_color_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker__marquee:hover',
			$this->get_ticker_color_hover_css( $attributes ),
			$this->get_ticker_color_hover_css( $attributes, 'Tablet' ),
			$this->get_ticker_color_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker--icons',
			$this->get_navigator_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker--icons__prev',
			$this->get_navigator_color_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker--icons__next',
			$this->get_navigator_color_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker--icons__pause',
			$this->get_navigator_color_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker--icons__resume',
			$this->get_navigator_color_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker__list',
			$this->get_ticker_list_styles_css( $attributes ),
			$this->get_ticker_list_styles_css( $attributes, 'Tablet' ),
			$this->get_ticker_list_styles_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-news-ticker--date',
			$this->get_show_time_css( $attributes )
		);

		return $css_generator->generate_css();
	}

	public function get_label_css( $attributes, $device = '' ) {
		$label_css = [];
		if ( isset( $attributes['isShowLabel'] ) && $attributes['isShowLabel'] ) {
			$label_css['display'] = 'flex';
		} else {
			$label_css['display'] = 'none';
		}

		if ( isset( $attributes['labelPosition'] ) && $attributes['labelPosition'] === 'right' ) {
			$label_css['right'] = '0';
			$label_css['left'] = 'auto';
		} else {
			$label_css['left'] = '0';
			$label_css['right'] = 'auto';
		}

		if ( ! empty( $attributes['tickerLabelShape'] ) ) {
			switch ( $attributes['tickerLabelShape'] ) {
				case 'small':
					$label_css['clip-path'] = 'polygon(0% 0%, 80% 0%, 100% 50%, 80% 100%, 0% 100%)';
					break;

				case 'medium':
					$label_css['clip-path'] = 'polygon(0% 0%, 85% 0%, 100% 50%, 85% 100%, 0% 100%)';
					break;

				case 'large':
					$label_css['clip-path'] = 'polygon(0% 0%, 90% 0%, 100% 50%, 90% 100%, 0% 100%)';
					break;

				default:
					$label_css['clip-path'] = '';
					break;
			}
		}
		$typographyValueGlobal = ! empty( $attributes['labelTypographyGlobal'] ) ? $attributes['labelTypographyGlobal'] : array();
		$typography_css = ! empty( $attributes['labelTypography'] ) ? Typography::get_css( $attributes['labelTypography'], '', $device, $typographyValueGlobal ) : array();
		$textShadowCss = ! empty( $attributes['labelTextShadow'] ) ? TextShadow::get_css( $attributes['labelTextShadow'], '', $device ) : array();
		$textStrokeCss = ! empty( $attributes['labelTextStroke'] ) ? TextStroke::get_css( $attributes['labelTextStroke'], '', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['labelColor'] ) ? $attributes['labelColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['labelBackgroundColor'] ) ? $attributes['labelBackgroundColor'] : '' ) ],
			Range::get_css([
				'attributeValue'       => $attributes['labelPadding'],
				'attribute_object_key' => 'value',
				'isResponsive'         => true,
				'defaultValue'         => 10,
				'hasUnit'              => true,
				'unitDefaultValue'     => 'px',
				'property'             => 'padding',
				'device'               => $device,
			]),
			$label_css,
			$typography_css,
			$textShadowCss,
			$textStrokeCss
		);
	}


	public function get_label_color_hover_css( $attributes ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['labelColorH'] ) ? $attributes['labelColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['labelBackgroundColorH'] ) ? $attributes['labelBackgroundColorH'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['labelColorTransition'],
				'attribute_object_key' => 'value',
				'defaultValue' => 0,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
			]),
		);
	}

	public function get_ticker_content_css( $attributes, $device = '' ) {
		$ticker_content_css = [];

		if ( isset( $attributes['isPositionSticky'] ) && $attributes['isPositionSticky'] ) {
			if ( isset( $attributes['stickyPosition'] ) && $attributes['stickyPosition'] === 'up' ) {
				$ticker_content_css['position'] = 'fixed';
				$ticker_content_css['top'] = '32px';
				$ticker_content_css['left'] = '0';
				$ticker_content_css['width'] = '100%';

			} elseif ( isset( $attributes['stickyPosition'] ) && $attributes['stickyPosition'] === 'down' ) {
				$ticker_content_css['position'] = 'fixed';
				$ticker_content_css['bottom'] = '0';
				$ticker_content_css['left'] = '0';
				$ticker_content_css['width'] = '100%';
			}
		}

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['tickerHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 50,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			$ticker_content_css
		);
	}

	public function get_ticker_list_styles_css( $attributes, $device = '' ) {
		$ticker_list_styles_css = [];
		if ( isset( $attributes['tickerListStyle'] ) ) {
			switch ( $attributes['tickerListStyle'] ) {
				case 'none':
					$ticker_list_styles_css['list-style'] = 'none';
					break;
				case 'circle':
					$ticker_list_styles_css['list-style'] = 'circle';
					break;
				case 'box':
					$ticker_list_styles_css['list-style'] = 'square';
					break;
				default:
					$ticker_list_styles_css['list-style'] = 'none';
			}
		}
		$typographyValueGlobal = ! empty( $attributes['tickerTypographyGlobal'] ) ? $attributes['tickerTypographyGlobal'] : array();
		$typography_css = ! empty( $attributes['tickerTypography'] ) ? Typography::get_css( $attributes['tickerTypography'], '', $device, $typographyValueGlobal ) : array();
		$textShadowCss = ! empty( $attributes['tickerTextShadow'] ) ? TextShadow::get_css( $attributes['tickerTextShadow'], '', $device ) : array();
		$textStrokeCss = ! empty( $attributes['tickerTextStroke'] ) ? TextStroke::get_css( $attributes['tickerTextStroke'], '', $device ) : array();

		return array_merge(
			$ticker_list_styles_css,
			$typography_css,
			$textShadowCss,
		$textStrokeCss );
	}

	public function get_ticker_color_css( $attributes ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['tickerColor'] ) ? $attributes['tickerColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['tickerBgColor'] ) ? $attributes['tickerBgColor'] : '' ) ],
		);
	}

	public function get_ticker_color_hover_css( $attributes ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['tickerColorH'] ) ? $attributes['tickerColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['tickerBgColorH'] ) ? $attributes['tickerBgColorH'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['tickerColorTransition'],
				'attribute_object_key' => 'value',
				'defaultValue' => 0,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
			])
		);
	}

	public function get_navigator_css( $attributes ) {
		$navigator_css = [];

		if ( isset( $attributes['showTickerNavigator'] ) && $attributes['showTickerNavigator'] ) {
			$navigator_css['display'] = 'flex';
		} else {
			$navigator_css['display'] = 'none';
		}
		if ( isset( $attributes['navigatorPosition'] ) && $attributes['navigatorPosition'] === 'right' ) {
			$navigator_css['right'] = '0';
			$navigator_css['left'] = 'auto';
		} else {
			$navigator_css['left'] = '0';
			$navigator_css['right'] = 'auto';
		}

		return $navigator_css;
	}
	public function get_navigator_color_css( $attributes ) {
		return [ 'background' => Color::get_css( isset( $attributes['navigatorBgColor'] ) ? $attributes['navigatorBgColor'] : '' ) ];

	}

	public function get_show_time_css( $attributes ) {
		$time_show_css = [];

		if ( isset( $attributes['isShowTime'] ) && $attributes['isShowTime'] ) {
			$time_show_css['display'] = 'inline';
		} else {
			$time_show_css['display'] = 'none';
		}

		return $time_show_css;
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$sticky_label          = sanitize_text_field( $attributes['stickyLabel'] ?? 'Breaking News' );
		$sticky_label_tag      = sanitize_html_class( $attributes['stickyLabelTag'] ?? 'span' );
		$selected_posts        = array_map( 'absint', $attributes['selectedPosts'] ?? [] );
		$selected_pages        = array_map( 'absint', $attributes['selectedPages'] ?? [] );
		$slide_speed           = sanitize_text_field( $attributes['slideSpeed'] ?? '2' );
		$is_pause_on_over      = filter_var( $attributes['isPauseOnOver'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$show_ticker_navigator = filter_var( $attributes['showTickerNavigator'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$custom_text           = sanitize_text_field( $attributes['customText'] ?? '' );
		$query_type            = sanitize_text_field( $attributes['queryType'] ?? '' );
		$navigator_color = Color::get_css( isset( $attributes['navigatorColor'] ) ? $attributes['navigatorColor'] : '' );
		$slide_direction       = sanitize_text_field( $attributes['slideDirection'] ?? 'left' );
		$post_type      = ! empty( $selected_pages ) ? 'page' : 'post';
		$selected_items = ! empty( $selected_pages ) ? $selected_pages : $selected_posts;

		$args = [
			'post_type'      => $post_type,
			'post__in'       => $selected_items,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
		];

		$query = new \WP_Query( $args );

		ob_start();
		?>
	<div class="ablocks-block-news-ticker"
		 data-slide-speed="<?php echo esc_attr( $slide_speed ); ?>"
		 data-slide-direction="<?php echo esc_attr( $slide_direction ); ?>"
		 data-pause-on-hover="<?php echo esc_attr( $is_pause_on_over ? 'true' : 'false' ); ?>"
		 data-navigator-color="<?php echo esc_attr( $navigator_color ); ?>">
		<<?php echo esc_attr( $sticky_label_tag ); ?> class="ablocks-block-news-ticker__label">
		<?php echo esc_html( $sticky_label ); ?>
		</<?php echo esc_attr( $sticky_label_tag ); ?>>
		<div class="ablocks-block-news-ticker__marquee">
			<div class="ablocks-block-news-ticker_marquee--content">
				<ul class="ablocks-block-news-ticker__list">
					<?php if ( $query_type === 'customText' && ! empty( $attributes['lists'] ) ) : ?>
						<?php foreach ( $attributes['lists'] as $item ) : ?>
							<?php
							$text = sanitize_text_field( $item['text'] ?? '' );
							$href = esc_url( $item['link']['href'] ?? '' );
							$target = ! empty( $item['link']['linkTarget'] ) ? 'target="_blank" rel="noopener noreferrer"' : '';
							?>
							<li class="ablocks-block-news-ticker__item">
							<span class="ablocks-block-news-ticker__custom-text">
								<?php if ( ! empty( $href ) ) : ?>
									<a href="<?php echo esc_url( $href ); ?>" <?php echo esc_html( $target ); ?>>
										<?php echo esc_html( $text ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $text ); ?>
								<?php endif; ?>
							</span>
							</li>
						<?php endforeach; ?>
					<?php elseif ( ! empty( $selected_items ) ) : ?>
						<?php if ( $query->have_posts() ) :
							while ( $query->have_posts() ) :
								$query->the_post();
								$has_link    = ! empty( $attributes['pageLink'] ) || ! empty( $attributes['postLink'] );
								$post_link   = get_permalink();
								$link_target = '_blank';
								$title       = get_the_title();
								$date        = esc_html( $this->get_relative_time( get_the_date() ) );
								?>
								<?php if ( $has_link ) : ?>
								<a href="<?php echo esc_url( $post_link ); ?>" target="<?php echo esc_attr( $link_target ); ?>">
									<li class="ablocks-block-news-ticker__item">
										<?php echo esc_html( $title ); ?>
										<span class="ablocks-block-news-ticker--date"><?php echo esc_html( $date ); ?></span>
									</li>
								</a>
							<?php else : ?>
								<li class="ablocks-block-news-ticker__item">
									<?php echo esc_html( $title ); ?>
									<span class="ablocks-block-news-ticker--date"><?php echo esc_html( $date ); ?></span>
								</li>
							<?php endif; ?>
							<?php endwhile; ?>
						<?php else : ?>
							<li><?php
								/* translators: %s template */
								echo esc_html( sprintf( __( 'No %s found in the selection', 'ablocks' ), $post_type . 's' ) ); ?></li>
						<?php endif; ?>
					<?php else : ?>
						<li><?php esc_html_e( 'No items selected', 'ablocks' ); ?></li>
					<?php endif; ?>
				</ul>
			</div>

			<?php if ( $show_ticker_navigator ) : ?>
				<div class="ablocks-block-news-ticker--icons">
					<button class="ablocks-block-news-ticker--icons__prev">
						<svg
							width="24"
							height="50"
							viewBox="0 0 28 28"
							fill="none"
							xmlns="http://www.w3.org/2000/svg"
						>
							<path
								d="M18.119 22.1309C18.2003 22.2122 18.2648 22.3087 18.3088 22.415C18.3528 22.5212 18.3755 22.635 18.3755 22.75C18.3755 22.865 18.3528 22.9788 18.3088 23.085C18.2648 23.1913 18.2003 23.2878 18.119 23.3691C18.0378 23.4504 17.9412 23.5148 17.835 23.5588C17.7288 23.6028 17.615 23.6255 17.5 23.6255C17.385 23.6255 17.2712 23.6028 17.165 23.5588C17.0587 23.5148 16.9622 23.4504 16.8809 23.3691L8.13092 14.6191C8.04957 14.5378 7.98503 14.4413 7.941 14.3351C7.89696 14.2288 7.8743 14.115 7.8743 14C7.8743 13.885 7.89696 13.7712 7.941 13.6649C7.98503 13.5587 8.04957 13.4622 8.13092 13.3809L16.8809 4.63094C17.0451 4.46675 17.2678 4.37451 17.5 4.37451C17.7322 4.37451 17.9549 4.46675 18.119 4.63094C18.2832 4.79512 18.3755 5.01781 18.3755 5.25C18.3755 5.48219 18.2832 5.70488 18.119 5.86906L9.98702 14L18.119 22.1309Z"
								fill="<?php echo esc_attr( $navigator_color ); ?>"
							/>
						</svg>
					</button>
					<button
						aria-label="Pause Button"
						title="Pause Button"
						 class="ablocks-block-news-ticker--icons__pause">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="50" fill="currentColor">
							<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
						</svg>
					</button>
					<button
						aria-label="Next Button"
						title="Next Button"
						class="ablocks-block-news-ticker--icons__next">
						<svg
							width="24"
							height="50"
							viewBox="0 0 28 28"
							fill="none"
							xmlns="http://www.w3.org/2000/svg"
						>
							<path
								d="M19.8691 14.6191L11.1191 23.3691C11.0378 23.4504 10.9413 23.5148 10.835 23.5588C10.7288 23.6028 10.615 23.6255 10.5 23.6255C10.385 23.6255 10.2712 23.6028 10.165 23.5588C10.0587 23.5148 9.96223 23.4504 9.88094 23.3691C9.79964 23.2878 9.73515 23.1913 9.69115 23.085C9.64716 22.9788 9.62451 22.865 9.62451 22.75C9.62451 22.635 9.64716 22.5212 9.69115 22.415C9.73515 22.3087 9.79964 22.2122 9.88094 22.1309L18.013 14L9.88094 5.86906C9.71675 5.70488 9.62451 5.48219 9.62451 5.25C9.62451 5.01781 9.71675 4.79512 9.88094 4.63094C10.0451 4.46675 10.2678 4.37451 10.5 4.37451C10.7322 4.37451 10.9549 4.46675 11.1191 4.63094L19.8691 13.3809C19.9504 13.4622 20.015 13.5587 20.059 13.6649C20.103 13.7712 20.1257 13.885 20.1257 14C20.1257 14.115 20.103 14.2288 20.059 14.3351C20.015 14.4413 19.9504 14.5378 19.8691 14.6191Z"
								fill="<?php echo esc_attr( $navigator_color ); ?>"
							/>
						</svg>
					</button>
				</div>
			<?php endif; ?>
		</div>
		</div>
		<?php

		wp_reset_postdata();

		return ob_get_clean();
	}


	private function get_relative_time( $date_string ) {
		$post_date = new \DateTime( $date_string );
		$now = new \DateTime();
		$interval = $now->diff( $post_date );
		$weeks = floor( $interval->days / 7 );

		if ( $weeks >= 1 ) {
			return $weeks . ' week' . ( $weeks > 1 ? 's' : '' ) . ' ago';
		}
		return 'Less than a week ago';
	}

}
