<?php
namespace ABlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Admin {
	public static function init() {
		$self = new self();
		$self->dispatch_hooks();

		add_filter( 'upload_mimes', [ $self, 'allow_lottie_json_uploads' ] );
		add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );

			if ( 'json' === $ext ) {
				$data['ext']  = 'json';
				$data['type'] = 'application/json';
			}

			return $data;
		}, 10, 3 );
	}

	function allow_lottie_json_uploads( $mimes ) {
		$mimes['json'] = 'application/json';
		$mimes['lottie'] = 'application/json';
		return $mimes;
	}

	public function dispatch_hooks() {
		Admin\Menu::init();
		\ABlocks\CreatePage\page\ShowPageState::init();
		Admin\Export::init();
		Admin\Notice::init();
		add_filter( 'allowed_redirect_hosts', array( $this, 'add_white_listed_redirect_hosts' ) );
		add_action( 'current_screen', array( $this, 'conditional_loaded' ) );
		add_filter( 'plugin_action_links_' . ABLOCKS_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_links' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'dispatch_activation_redirect' ), 99 );
	}
	public function add_white_listed_redirect_hosts( $hosts ) {
		$hosts[] = 'ablocks.pro';
		return $hosts;
	}

	public function conditional_loaded() {
		$screen = get_current_screen();

		if ( $screen && $screen->id == 'ablocks_page_ablocks-get-pro' ) {
			$link = add_query_arg(
				[
					'utm_source'   => 'ablocks-plugin',
					'utm_medium'   => 'admin-dashboard',
					'utm_campaign' => 'upgrade-to-pro',
					'utm_content'  => 'get-pro-button',
					'utm_term'     => 'free-user',
					'locale'       => get_locale(),
				],
				'https://ablocks.pro/pricing/'
			);

			wp_safe_redirect( $link );
			exit;
		}
	}

	public function add_plugin_links( $links, $file ) {
		if ( ABLOCKS_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$map_block_links = array(
			'docs'    => array(
				'url'        => 'https://ablocks.pro/docs/',
				'label'      => __( 'Docs', 'ablocks' ),
				'aria-label' => __( 'View aBlocks documentation', 'ablocks' ),
			),
			'video' => array(
				'url'        => 'https://www.youtube.com/@ablocksteam',
				'label'      => __( 'Video Tutorials', 'ablocks' ),
				'aria-label' => __( 'See Video Tutorials', 'ablocks' ),
			),
			'support' => array(
				'url'        => 'https://wordpress.org/support/plugin/ablocks/',
				'label'      => __( 'Community Support', 'ablocks' ),
				'aria-label' => __( 'Visit community forums', 'ablocks' ),
			),
			'review'  => array(
				'url'        => 'https://wordpress.org/support/plugin/ablocks/reviews/#new-post',
				'label'      => __( 'Rate the plugin ★★★★★', 'ablocks' ),
				'aria-label' => __( 'Rate the plugin.', 'ablocks' ),
			),
		);

		foreach ( $map_block_links as $key => $link ) {
			$links[ $key ] = sprintf(
				'<a target="_blank" href="%s" aria-label="%s">%s</a>',
				esc_url( $link['url'] ),
				esc_attr( $link['aria-label'] ),
				esc_html( $link['label'] )
			);
		}

		return $links;
	}
	public function plugin_action_links( $links ) {
		$settings_link = sprintf( '<a href="%1$s">%2$s</a>', admin_url( 'admin.php?page=ablocks' ), esc_html__( 'Settings', 'ablocks' ) );

		array_unshift( $links, $settings_link );

		if ( ! defined( 'ABLOCKS_PRO_VERSION' ) ) {
			$links['go_pro'] = sprintf( '<a href="%1$s" target="_blank" class="academy-plugins-gopro" style="color: #7b68ee; font-weight: bold;">%2$s</a>', 'https://ablocks.pro/pricing/', esc_html__( 'Get aBlocks Pro', 'ablocks' ) );
		}
		return $links;
	}
	public function dispatch_activation_redirect() {
		if ( get_option( 'ablocks_need_activation_redirect', false ) ) {
			delete_option( 'ablocks_need_activation_redirect' );
			wp_safe_redirect( admin_url( 'admin.php?page=ablocks' ) );
			exit;
		}
	}
}
