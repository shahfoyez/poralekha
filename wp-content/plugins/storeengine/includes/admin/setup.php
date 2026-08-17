<?php

namespace StoreEngine\Admin;

use StoreEngine\Assets;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Setup {
	const PAGE_ID = 'storeengine-setup';

	private ?Assets $assets;

	public static function init() {
		$self         = new self();
		$self->assets = new Assets();
		if ( $self->is_current() ) {
			add_action( 'admin_init', [ $self, 'admin_init' ], 0 );
		}
		add_action( 'admin_menu', [ $self, 'register_admin_menu' ] );
	}

	public function register_admin_menu() {
		add_submenu_page(
			'options-writing.php', // make it hidden
			__( 'StoreEngine Setup', 'storeengine' ),
			__( 'StoreEngine Setup', 'storeengine' ),
			'manage_options',
			self::PAGE_ID
		);
	}


	public function admin_init() {
		do_action( 'storeengine/admin/setup/init', $this );

		$this->enqueue_assets();

		// Setup default heartbeat options
		add_filter( 'heartbeat_settings', function ( $settings ) {
			$settings['interval'] = 15;

			return $settings;
		} );

		$this->render();
		die;
	}

	public function is_current() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ( ! empty( $_GET['page'] ) && self::PAGE_ID === $_GET['page'] );
	}

	public function get_setup_scripts_data() {
		return apply_filters(
			'storeengine/admin/setup_scripts_data',
			array_merge(
				$this->assets->get_backend_script_data(),
				[
					'admin_url'     => admin_url(),
					'customize_url' => add_query_arg(
						'return',
						admin_url( 'admin.php?page=storeengine-settings&path=general' ),
						admin_url( 'customize.php' )
					),
				]
			)
		);
	}


	private function enqueue_assets() {
		wp_enqueue_style(
			'storeengine-admin-icon',
			STOREENGINE_ASSETS_URI . 'library/icons/storeengine-icons.css',
			[ 'wp-components' ],
			filemtime(
				STOREENGINE_ASSETS_DIR_PATH .
				'library/icons/storeengine-icons.css'
			),
		);
		if ( Helper::is_plugin_active( 'ablocks/ablocks.php' ) ) {
			$Assets = new \ABlocks\Assets();
			$Assets->demo_template_importer_scripts();
		}

		$dependencies = include STOREENGINE_ASSETS_DIR_PATH . sprintf( 'build/setup.%s.asset.php', STOREENGINE_VERSION );
		wp_enqueue_style( 'storeengine-admin-style', STOREENGINE_ASSETS_URI . 'build/setup.css', array( 'wp-components' ), $dependencies['version'], 'all' );
		wp_add_inline_style( 'storeengine-admin-style', $this->assets->get_dynamic_css() );
		wp_enqueue_script( 'updates' );
		// Load the JS-translation companion so w.org language packs apply here.
		$this->assets->register_i18n_strings();
		wp_enqueue_script(
			'storeengine-setup-scripts',
			STOREENGINE_ASSETS_URI . sprintf( 'build/setup.%s.js', STOREENGINE_VERSION ),
			array_merge( (array) $dependencies['dependencies'], wp_script_is( 'storeengine-i18n', 'registered' ) ? [ 'storeengine-i18n' ] : [] ),
			$dependencies['version'],
			true
		);
		wp_localize_script( 'storeengine-setup-scripts', 'StoreEngineGlobal', $this->get_setup_scripts_data() );
		wp_set_script_translations(
			'storeengine-setup-scripts',
			'storeengine',
			STOREENGINE_ROOT_DIR_PATH . 'i18n/languages'
		);
		// Enqueue emoji styles to prevent deprecation notices
		wp_enqueue_emoji_styles();
	}

	private function render() {
		require STOREENGINE_ROOT_DIR_PATH . 'includes/admin/views/setup.php';
	}
}
