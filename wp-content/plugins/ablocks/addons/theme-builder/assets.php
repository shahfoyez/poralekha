<?php
namespace ABlocksThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper as CoreHelper;
use ABlocks\Classes\FileUpload;
use ABlocks\Assets as CoreAssets;

class Assets {

	public static function init() {
		$self = new self();
		add_filter( 'ablocks/assets/dashboard_scripts_data', array( $self, 'dashboard_scripts_data' ) );
		add_action( 'wp_enqueue_scripts', [ $self, 'enqueue_frontend_assets' ], 99 );
	}

	public static function get_post_target_rule_options( $post_type, $taxonomy ) {
		$post_key    = str_replace( ' ', '-', strtolower( $post_type->label ) );
		$post_label  = ucwords( $post_type->label );
		$post_name   = $post_type->name;
		$options     = [];

		$options[] = [
			'value' => $post_name . '|all',
			/* translators: %s template */
			'label' => sprintf( __( 'All %s', 'ablocks' ), $post_label ),
		];

		if ( 'pages' !== $post_key ) {
			$options[] = [
				'value' => $post_name . '|all|archive',
				/* translators: %s template */
				'label' => sprintf( __( 'All %s Archive', 'ablocks' ), $post_label ),
			];
		}

		if ( in_array( $post_type->name, $taxonomy->object_type ) ) {
			$tax_label = ucwords( $taxonomy->label );
			$tax_name  = $taxonomy->name;

			$options[] = [
				'value' => $post_name . '|all|taxarchive|' . $tax_name,
				/* translators: %s template */
				'label' => sprintf( __( 'All %s Archive', 'ablocks' ), $tax_label ),
			];
		}

		return [
			'label'   => $post_label,
			'options' => $options,
		];
	}

	public static function get_location_selections() {
		$args = [
			'public' => true,
			'_builtin' => true
		];
		$post_types = get_post_types( $args, 'objects' );
		unset( $post_types['attachment'] );

		$args['_builtin'] = false;
		$custom_post_types = get_post_types( $args, 'objects' );
		$post_types = apply_filters( 'ablocks_theme_builder/location_rule_post_types', array_merge( $post_types, $custom_post_types ) );

		$groups = [];

		// Basic group
		$groups[] = [
			'label' => __( 'Basic', 'ablocks' ),
			'options' => [
				[
					'value' => 'basic-global',
					'label' => __( 'Entire Website', 'ablocks' )
				],
				[
					'value' => 'basic-singulars',
					'label' => __( 'All Singulars', 'ablocks' )
				],
				[
					'value' => 'basic-archives',
					'label' => __( 'All Archives', 'ablocks' )
				],
			],
		];

		// Special Pages group
		$special_pages = [
			[
				'value' => 'special-404',
				'label' => __( '404 Page', 'ablocks' )
			],
			[
				'value' => 'special-search',
				'label' => __( 'Search Page', 'ablocks' )
			],
			[
				'value' => 'special-blog',
				'label' => __( 'Blog / Posts Page', 'ablocks' )
			],
			[
				'value' => 'special-front',
				'label' => __( 'Front Page', 'ablocks' )
			],
			[
				'value' => 'special-date',
				'label' => __( 'Date Archive', 'ablocks' )
			],
			[
				'value' => 'special-author',
				'label' => __( 'Author Archive', 'ablocks' )
			],
		];

		if ( class_exists( 'WooCommerce' ) ) {
			$special_pages[] = [
				'value' => 'special-woo-shop',
				'label' => __( 'WooCommerce Shop Page', 'ablocks' )
			];
		}

		$groups[] = [
			'label' => __( 'Special Pages', 'ablocks' ),
			'options' => $special_pages,
		];

		// Custom post types and taxonomies
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( 'post_format' === $taxonomy->name ) {
				continue;
			}

			foreach ( $post_types as $post_type ) {
				if ( $post_type === 'ablocks_tb' ) {
					continue;
				}
				$group = self::get_post_target_rule_options( $post_type, $taxonomy );

				// Avoid duplicate labels
				$existing_group = array_filter( $groups, fn( $g) => $g['label'] === $group['label'] );
				if ( ! empty( $existing_group ) ) {
					foreach ( $groups as &$g ) {
						if ( $g['label'] === $group['label'] ) {
							$merged = array_merge( $g['options'], $group['options'] );

							// Remove duplicate options by unique 'value' field (or 'label', depending on your use case)
							$unique = [];
							foreach ( $merged as $option ) {
								$unique_key = $option['value']; // or use $option['label'] if needed
								$unique[ $unique_key ] = $option;
							}

							$g['options'] = array_values( $unique );
						}
					}
				} else {
					$groups[] = $group;
				}
			}//end foreach
		}//end foreach

		// Specific Target group
		$groups[] = [
			'label' => __( 'Specific Target', 'ablocks' ),
			'options' => [
				[
					'value' => 'specifics',
					'label' => __( 'Specific Pages / Posts / Taxonomies, etc.', 'ablocks' )
				],
			],
		];

		return apply_filters( 'ablocks/theme_builder/display_location_lists', $groups );
	}

	public static function get_user_selections() {
		$groups = [];

		// Basic group
		$groups[] = [
			'label' => __( 'Basic', 'ablocks' ),
			'options' => [
				[
					'value' => 'all',
					'label' => __( 'All', 'ablocks' )
				],
				[
					'value' => 'logged-in',
					'label' => __( 'Logged In', 'ablocks' )
				],
				[
					'value' => 'logged-out',
					'label' => __( 'Logged Out', 'ablocks' )
				],
			],
		];

		// Advanced group
		$roles = get_editable_roles();
		$role_options = [];

		foreach ( $roles as $slug => $data ) {
			$role_options[] = [
				'value' => $slug,
				'label' => $data['name'],
			];
		}

		$groups[] = [
			'label' => __( 'Advanced', 'ablocks' ),
			'options' => $role_options,
		];

		return apply_filters( 'ablocks/theme_builder/user_roles', $groups );
	}

	public function dashboard_scripts_data( $data ) {
		$data['theme_builder'] = [
			'locations' => $this->get_location_selections(),
			'roles'     => $this->get_user_selections(),
		];
		return $data;
	}

	public function enqueue_frontend_assets() {
		if ( ! CoreHelper::is_enabled_assets_generation() ) {
			return;
		}
		if ( ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) ) {
			return;
		}

		$template_types = [
			'header',
			'footer'
		];

		$FileUpload = new FileUpload();
		foreach ( $template_types as $template_type ) {
			$post_id = Helper::get_template_id( $template_type );
			if ( $post_id ) {
				$css_file_path = $FileUpload->get_file_path( $post_id . '.min.css' );
				if ( file_exists( $css_file_path ) ) {
					$css_file_url = $FileUpload->get_file_url( $post_id . '.min.css' );
					// Enqueue google fonts
					wp_enqueue_style( 'ablocks-frontend-google-fonts' );
					// Combine CSS
					wp_enqueue_style( 'ablocks-blocks-' . $template_type . '-' . $post_id . '-combine-style', $css_file_url, array(), filemtime( $css_file_path ), 'all' );
				}
				$js_file_path = $FileUpload->get_file_path( $post_id . '.min.js' );
				if ( file_exists( $js_file_path ) ) {
					$js_file_url = $FileUpload->get_file_url( $post_id . '.min.js' );
					// One shared ABlocksGlobal for the whole page: without this each
					// template type (header/footer/single/archive) printed its own
					// identical copy on top of the page combine script's.
					wp_enqueue_script( 'ablocks-blocks-' . $template_type . '-' . $post_id . '-combine-script', $js_file_url, array( 'ablocks-globals' ), filemtime( $js_file_path ), true );
					CoreAssets::localize_globals_once();
				}
			}
		}//end foreach
	}
}
