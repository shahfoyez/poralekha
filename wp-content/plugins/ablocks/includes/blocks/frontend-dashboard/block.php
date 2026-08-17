<?php
namespace ABlocks\Blocks\FrontendDashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {
	protected $block_name = 'frontend-dashboard';

	public function __construct() {
		parent::__construct();

	}

	public function build_css( $attributes ) {

		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$get_gaps_css = $this->get_wrapper_gap_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--frontend-dashboard',
			$get_gaps_css,
			$this->get_wrapper_gap_css( $attributes, 'Tablet' ),
			$this->get_wrapper_gap_css( $attributes, 'Mobile' )
		);

		$get_setting_css = $this->get_setting_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-sidebar',
			$get_setting_css,
			$this->get_setting_css( $attributes, 'Tablet' ),
			$this->get_setting_css( $attributes, 'Mobile' )
		);

		$get_user_setting_css = $this->get_user_setting_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-user',
			$get_user_setting_css,
			$this->get_user_setting_css( $attributes, 'Tablet' ),
			$this->get_user_setting_css( $attributes, 'Mobile' )
		);

		$get_menu_list_css = $this->get_menu_list_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-menu__item a',
			$get_menu_list_css,
			$this->get_menu_list_css( $attributes, 'Tablet' ),
			$this->get_menu_list_css( $attributes, 'Mobile' )
		);

		$get_menu_hover_list_css = $this->get_menu_hover_list_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-menu__item a:hover',
			$get_menu_hover_list_css,
			$this->get_menu_hover_list_css( $attributes, 'Tablet' ),
			$this->get_menu_hover_list_css( $attributes, 'Mobile' )
		);

		$get_menu_active_list_css = $this->get_menu_active_list_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-menu__item--current > a',
			$get_menu_active_list_css,
			$this->get_menu_active_list_css( $attributes, 'Tablet' ),
			$this->get_menu_active_list_css( $attributes, 'Mobile' )
		);

		$get_breadcrumb_css = $this->get_breadcrumb_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-breadcrumb li',
			$get_breadcrumb_css,
			$this->get_breadcrumb_css( $attributes, 'Tablet' ),
			$this->get_breadcrumb_css( $attributes, 'Mobile' )
		);

		$get_breadcrumb_css = $this->get_breadcrumb_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-breadcrumb li + li::before',
			$get_breadcrumb_css,
			$this->get_breadcrumb_css( $attributes, 'Tablet' ),
			$this->get_breadcrumb_css( $attributes, 'Mobile' )
		);

		$get_content_css = $this->get_content_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-content',
			$get_content_css,
			$this->get_content_css( $attributes, 'Tablet' ),
			$this->get_content_css( $attributes, 'Mobile' )
		);

		$get_content_hover_css = $this->get_content_hover_css( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-frontend-dashboard-content:hover',
			$get_content_hover_css,
			$this->get_content_hover_css( $attributes, 'Tablet' ),
			$this->get_content_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}


	public function get_setting_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['sidebarBackground'] ) ) {
			$css['background'] = Color::get_css(
			isset( $attributes['sidebarBackground'] ) ? $attributes['sidebarBackground'] : '');
		}

		return array_merge(
			$css,
			Border::get_css( $attributes['sidebarBorder'] ?? [], '', $device )
		);
	}

	public function get_user_setting_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['userTextColor'] ) ) {
			$css['color'] = Color::get_css(
			isset( $attributes['userTextColor'] ) ? $attributes['userTextColor'] : '');
		}
		if ( ! empty( $attributes['sidebarUserBackground'] ) ) {
			$css['background'] = Color::get_css(
			isset( $attributes['sidebarUserBackground'] ) ? $attributes['sidebarUserBackground'] : '');
		}
		$userTypographyGlobal = ! empty( $attributes['userTypographyGlobal'] ) ? $attributes['userTypographyGlobal'] : '';
		return array_merge(
			$css,
			Border::get_css( $attributes['userSidebarBorder'] ?? [], '', $device ),
			Typography::get_css( $attributes['userTypography'], '', $device, $userTypographyGlobal ),
		);
	}

	public function get_menu_list_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['menuListTextColor'] ) ) {
			$css['color'] = Color::get_css(
			isset( $attributes['menuListTextColor'] ) ? $attributes['menuListTextColor'] : '');
		}
		if ( ! empty( $attributes['menuListBackground'] ) ) {
			$css['background'] = Color::get_css(
			isset( $attributes['menuListBackground'] ) ? $attributes['menuListBackground'] : '');
		}

		$userTypographyGlobal = ! empty( $attributes['userTypographyGlobal'] ) ? $attributes['userTypographyGlobal'] : '';

		return array_merge(
			$css,
			Border::get_css( $attributes['menuListBorder'] ?? [], '', $device ),
			Dimensions::get_css( $attributes['menuListPadding'] ?? [], 'padding', $device ),
			Typography::get_css( $attributes['menuListTypography'] ?? [], '', $device, $userTypographyGlobal )
		);
	}

	public function get_menu_hover_list_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['menuListHoverTextColor'] ) ) {
			$css['color'] = Color::get_css(
			isset( $attributes['menuListHoverTextColor'] ) ? $attributes['menuListHoverTextColor'] : '');
		}
		if ( ! empty( $attributes['menuListHoverBackground'] ) ) {
			$css['background'] = Color::get_css(
			isset( $attributes['menuListHoverBackground'] ) ? $attributes['menuListHoverBackground'] : '');
		}

		return array_merge(
			$css,
			Border::get_hover_css( $attributes['menuListBorder'] ?? [], '', $device )
		);
	}

	public function get_menu_active_list_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['menuListActiveTextColor'] ) ) {
			$css['color'] = Color::get_css(
			isset( $attributes['menuListActiveTextColor'] ) ? $attributes['menuListActiveTextColor'] : '');
		}
		if ( ! empty( $attributes['menuListActiveBackground'] ) ) {
			$css['background'] = Color::get_css(
			isset( $attributes['menuListActiveBackground'] ) ? $attributes['menuListActiveBackground'] : '');
		}

		return $css;
	}
	public function get_content_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['contentBackground'] ) ) {
			$css['background'] = Color::get_css(
			isset( $attributes['contentBackground'] ) ? $attributes['contentBackground'] : '');
		}

		return array_merge(
			$css,
			Border::get_css( $attributes['contentBorder'] ?? [], '', $device ),
			Dimensions::get_css( $attributes['contentPadding'] ?? [], 'padding', $device ),
		);
	}
	public function get_content_hover_css( $attributes, $device = '' ) {

		return array_merge(
			Border::get_hover_css( $attributes['contentBorder'] ?? [], '', $device ),
		);
	}

	public function get_breadcrumb_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['breadcrumbColor'] ) ) {
			$css['color'] = Color::get_css(
			isset( $attributes['breadcrumbColor'] ) ? $attributes['breadcrumbColor'] : '');
		}
		$breadcrumbtTypographyGlobal = ! empty( $attributes['breadcrumbtTypographyGlobal'] ) ? $attributes['breadcrumbtTypographyGlobal'] : '';

		return array_merge(
			$css,
			Typography::get_css( $attributes['breadcrumbtTypography'] ?? [], '', $device, $breadcrumbtTypographyGlobal )
		);
	}
	public function get_wrapper_gap_css( $attributes, $device = '' ) {
		$css = [];

			$css['display'] = 'flex';
		if ( $device === 'Tablet' ) {
			$css['flex-direction'] = 'column';
		} elseif ( $device === 'Mobile' ) {
			$css['flex-direction'] = 'column';
		} else {
			$css['flex-direction'] = 'row';
		}

		if ( ! empty( $attributes['bothGap'] ) ) {
			$css['gap'] = $attributes['bothGap'] . 'px';
		}
		return array_merge(
			$css
		);
	}

	// * Render the block content.
	public function render_block_content( $attributes, $content, $block_instance ) {

		global $wp;
		global $wpdb;

		$dashboard_page = isset( $attributes['dashboard_page'] ) && ! empty( $attributes['dashboard_page'] ) ? $attributes['dashboard_page'] : 'root';

		$dashboard_pages = json_decode( get_option( ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME, '{}' ), true );
		// print_r($dashboard_pages);
		$breadcrumbs = [];
		$breadcrumbs[] = [
			'label' => __( 'Dashboard', 'ablocks' ),
			'url'   => $this->get_frontend_dashboard_endpoint_url( '' ),
		];

		$current_page_slug = get_query_var( 'ablocks_dashboard_page', 'root' );
		$current_sub_page_slug = get_query_var( 'ablocks_dashboard_sub_page' );

		if ( $current_page_slug !== 'root' ) {
			$current_slug = empty( $current_sub_page_slug ) ? $current_page_slug : $current_sub_page_slug;
			// var_dump($current_slug);
			// Find the current page in the dashboard_pages array
			foreach ( $dashboard_pages as $page ) {
				if ( isset( $page['slug'] ) && $page['slug'] === $current_slug ) {
					$breadcrumbs[] = [
						'label' => $page['label'],
						'url'   => isset( $page['permalink'] ) ? $page['permalink'] : $this->get_frontend_dashboard_endpoint_url( $page['slug'] ),
					];
					break;
				}

				if ( is_array( $page['children'] ?? '' ) ) {
					if (
						 isset( $page['slug'] ) &&
						 $page['slug'] === $current_page_slug
					) {
						$breadcrumbs[] = [
							'label' => $page['label'],
							'url'   => isset( $page['permalink'] ) ? $page['permalink'] : $this->get_frontend_dashboard_endpoint_url( $page['slug'] ),
						];
					}

					foreach ( $page['children'] as $sub_page ) {
						// print_r([$sub_page['slug'], $current_slug]);
						if ( isset( $sub_page['slug'] ) && $sub_page['slug'] === $current_slug ) {
							$breadcrumbs[] = [
								'label' => $sub_page['label'],
								'url'   => isset( $sub_page['permalink'] ) ? $sub_page['permalink'] : $this->get_frontend_dashboard_endpoint_url( $sub_page['slug'] ),
							];
							break;
						}
					}
				}//end if
			}//end foreach
		}//end if
		?>
		<div class="ablocks-frontend-dashboard-sidebar">
			<div class="ablocks-frontend-dashboard-user">
				<div id="ablocks-frontend-dashboard-user-avatar">
					<?php echo get_avatar( get_current_user_id(), 96, '', wp_get_current_user()->display_name, [ 'loading' => 'lazy' ] ); ?>
					<span class="ablocks-frontend-dashboard-user__label"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
				</div>
			</div>
			<ul class="ablocks-frontend-dashboard-menu">
				<?php
				foreach ( $dashboard_pages as $menu ) :
					// print_r($menu);
					$endpoint = $menu['slug'] ?? $menu['link'];
					// print_r($endpoint);
					$menu['permalink'] = $menu['permalink'] ?? $menu['link'] ?? null;
					if ( $endpoint === 'root' ) {
						$menu['label'] = __( 'Dashboard', 'ablocks' );
						$menu['slug']  = '';
						$menu['priority'] = 0;
					} ?>
					<?php
						$is_parent_current = ( get_query_var( 'ablocks_dashboard_page' ) === $endpoint || ( get_query_var( 'ablocks_dashboard_page' ) === '' && 'index' === $endpoint ) ) && empty( get_query_var( 'ablocks_dashboard_sub_page' ) );
					?>
					<li class="ablocks-frontend-dashboard-menu__item ablocks-frontend-dashboard-menu__item--<?php echo esc_attr( $endpoint ); ?> <?php echo esc_attr( $menu['class_name'] ?? '' ); ?> <?php echo $is_parent_current ? 'ablocks-frontend-dashboard-menu__item--current' : ''; ?>">
						<a href="<?php echo esc_url( isset( $menu['permalink'] ) ? $menu['permalink'] : $this->get_frontend_dashboard_endpoint_url( $endpoint ) ); ?>">
							<i class="<?php if ( ! empty( $menu['icon'] ) ) {
								echo esc_html( $menu['icon'] );} ?>"></i>
							<span class="ablocks-frontend-dashboard-menu__item-label"><?php echo esc_html( $menu['label'] ); ?></span>
						</a>

						<?php if ( ! empty( $menu['children'] ) ) : ?>
							<ul class="ablocks-frontend-dashboard-menu__submenu">
								<?php foreach ( $menu['children'] as $child ) : ?>
									<?php
										$child['permalink'] = $child['permalink'] ?? $child['link'] ?? null;
										$is_current_child = get_query_var( 'ablocks_dashboard_sub_page' ) === ( $child['slug'] ?? null );
									?>
									<li class="<?php echo esc_attr( $child['class_name'] ?? '' ); ?> <?php echo $is_current_child ? 'ablocks-frontend-dashboard-menu__item--current' : ''; ?>"><a href="<?php echo esc_url( isset( $child['permalink'] ) ? $child['permalink'] : $this->get_frontend_dashboard_endpoint_url( $endpoint . '/' . ( $child['slug'] ?? '' ) ) ); ?>">
										<i class="<?php if ( ! empty( $child['icon'] ) ) {
											echo esc_html( $child['icon'] );} ?>"></i>
										<?php echo esc_html( $child['label'] ); ?>
									</a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

		</div>
		<div class="ablocks-frontend-dashboard-content">
			<!-- Breadcrumb -->
			<ul class="ablocks-frontend-dashboard-breadcrumb">
				<?php foreach ( $breadcrumbs as $crumb ) :
					?>
					<li>
						<?php echo esc_html( $crumb['label'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
			if ( Helper::is_gutenberg_editor() ) {
				self::render_dashboard_page_by_slug( $dashboard_page );
				// Show Notice for empty pages
				if ( ! is_array( $dashboard_pages ) || ! count( $dashboard_pages ) ) {
					echo 'Dashboard Page is empty. You haven\'t created any dashboard pages.';
				}
			} else {
				$is_resolved = false;
				if ( ! empty( $wp->query_vars ) ) {
					if ( isset( $wp->query_vars['ablocks_dashboard_page'] ) &&
						isset( $wp->query_vars['ablocks_dashboard_sub_page'] )
					) {
						self::render_dashboard_page_by_slug(
							get_query_var(
								'ablocks_dashboard_sub_page'
							)
						);
					} else {
						self::render_dashboard_page_by_slug(
							get_query_var(
								'ablocks_dashboard_page'
							)
						);

					}
					$is_resolved = true;
				}//end if
				if ( ! $is_resolved ) {
					// Render root
					self::render_dashboard_page_by_slug( 'root' );
				}
			}//end if
			?>
		</div>
		<?php
	}

	public static function get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {
		global $wp_query;
		if ( ! $permalink ) {
			$permalink = get_permalink();
		}

		// Map endpoint to options.
		$query_vars = $wp_query->query_vars;
		$endpoint = ! empty( $query_vars[ $endpoint ] ) ? $query_vars[ $endpoint ] : $endpoint;

		if ( get_option( 'permalink_structure' ) ) {
			if ( strstr( $permalink, '?' ) ) {
				$query_string = '?' . wp_parse_url( $permalink, PHP_URL_QUERY );
				$permalink = current( explode( '?', $permalink ) );
			} else {
				$query_string = '';
			}
			$url = trailingslashit( $permalink );

			if ( $value ) {
				$url .= trailingslashit( $endpoint ) . user_trailingslashit( $value );
			} else {
				$url .= user_trailingslashit( $endpoint );
			}

			$url .= $query_string;
		} else {
			$url = add_query_arg( $endpoint, $value, $permalink );
		}

		return apply_filters( 'ablocks/get_endpoint_url', $url, $endpoint, $value, $permalink );
	}

	public static function render_dashboard_page_by_slug( $slug ) {
		global $wpdb;
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$page = $wpdb->get_row( $wpdb->prepare(
			"SELECT post_content FROM $wpdb->posts WHERE post_name = %s AND post_type = 'page'",
			basename( $slug )
		) );

		if ( $page ) {
			// phpcs:ignore  WordPress.Security.EscapeOutput.OutputNotEscaped 
			echo apply_filters( 'the_content', $page->post_content );
			return;
		}
	}

	public static function get_frontend_dashboard_endpoint_url( $endpoint ) {
		if ( 'index' === $endpoint ) {
			return Helper::get_page_permalink( 'frontend_dashboard_page' );
		}

		if ( 'logout' === $endpoint ) {
			return Helper::get_logout_url();
		}

		return self::get_endpoint_url( $endpoint, '', Helper::get_page_permalink( 'frontend_dashboard_page' ) );
	}



}
