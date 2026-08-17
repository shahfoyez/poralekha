<?php
namespace ABlocks\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

class Menu {

	public static function init() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'admin_menu' ) );
		add_action( 'admin_head', array( $self, 'add_admin_menu_css' ) );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function admin_menu() {
		$icon_url = $this->get_toplevel_menu_icon_url();
		$page_title = $this->get_toplevel_menu_title();
		add_menu_page( $page_title, $page_title, 'manage_options', ABLOCKS_PLUGIN_SLUG, [ $this, 'load_main_template' ], $icon_url, 30 );
		foreach ( Helper::get_admin_menu_list() as $item_key => $item ) {
			add_submenu_page( $item['parent_slug'], $item['title'], $item['title'], $item['capability'], $item_key, [ $this, 'load_main_template' ] );
		}

		$this->register_theme_demo_importer_menu();
	}
	public function get_toplevel_menu_icon_url() {
		// phpcs:disable
		if ( isset( $_GET['page'] ) && 'ablocks' === $_GET['page'] ) {
			$icon_url = 'data:image/svg+xml;base64, ' . base64_encode( file_get_contents( ABLOCKS_ASSETS_PATH . 'images/menu-icon-expand.svg' ) );
			return apply_filters( 'ablocks/admin/toplevel_active_menu_icon', $icon_url );
		}
		$icon_url = 'data:image/svg+xml;base64, ' . base64_encode( file_get_contents( ABLOCKS_ASSETS_PATH . 'images/menu-icon.svg' ) );
		return apply_filters( 'ablocks/admin/toplevel_inactive_menu_icon', $icon_url );
	}
	public function load_main_template() {
		$preloader_html = apply_filters( 'ablocks/preloader', Helper::get_preloader_html() );
		echo '<div id="ablockswrap" class="ablockswrap">' . wp_kses_post( $preloader_html ) . '</div>';
	}
	public function get_toplevel_menu_title() {
		return apply_filters( 'ablocks/admin/toplevel_menu_title', __( 'aBlocks', 'ablocks' ) );
	}

	/**
	 * Register sub-menu for theme based demo-import.
	 *
	 * @return void
	 */
	public function register_theme_demo_importer_menu() {
		$demo_config = Helper::get_theme_demo_config();
		if ( empty($demo_config['demos']) && !$demo_config['preloaded_demo']) {
			return;
		}

		add_submenu_page(
			'themes.php',
			$demo_config['page_title'],
			$demo_config['menu_title'],
			'manage_options',
			$demo_config['menu_slug'],
			[ $this, 'load_demo_importer_template' ]
		);
	}

	public function load_demo_importer_template() {
		echo '<div id="ablocks-theme-demo-importer"></div>';
	}

	function add_admin_menu_css() {
		echo '<style>
			#adminmenu li.toplevel_page_ablocks a.toplevel_page_ablocks > .wp-menu-image { 
				display: flex;
				justify-content: center;
				align-items: center;
			}
			#adminmenu li.toplevel_page_ablocks a.toplevel_page_ablocks > .wp-menu-image img {
				max-width: 20px;
				height: auto;
				padding: 0 !important;
			}
			#adminmenu li.toplevel_page_ablocks ul li a, #adminmenu li.toplevel_page_ablocks .wp-submenu > li > a {
				padding: 7px 12px;
			}

			#adminmenu li.toplevel_page_ablocks ul.wp-submenu li {
				clear: both;
			}
			#adminmenu li.toplevel_page_ablocks ul.wp-submenu li.wp-first-item a[href^="admin.php?page=ablocks"]:after,
			#adminmenu li.toplevel_page_ablocks ul.wp-submenu li.wp-first-item a[href*="admin.php?page=ablocks"]:after,
			#adminmenu li.toplevel_page_ablocks ul.wp-submenu li a[href*="admin.php?page=ablocks-tools"]:after,
			#adminmenu li.toplevel_page_ablocks ul.wp-submenu li a[href^="admin.php?page=ablocks-tools"]:after {
				border-bottom: 1px solid hsla(0,0%,100%,.2);
				display: block;
				float: left;
				margin: 15px -15px 7px;
				content: "";
				width: calc(100% + 26px);
			}
		</style>';
	}
}
