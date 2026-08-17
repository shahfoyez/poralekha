<?php
namespace ABlocksThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Database {

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'create_ablocks_tb_post_type' ] );
		add_action( 'rest_api_init', [ $self, 'register_ablocks_tb_meta' ] );
	}

	public function create_ablocks_tb_post_type() {
		$post_type = 'ablocks_tb';
		register_post_type(
			$post_type,
			array(
				'labels'                => array(
					'name'                  => esc_html__( 'Theme Builder', 'ablocks' ),
					'singular_name'         => esc_html__( 'Theme Builder', 'ablocks' ),
					'search_items'          => esc_html__( 'Search Theme Builder', 'ablocks' ),
					'parent_item_colon'     => esc_html__( 'Parent Theme Builder:', 'ablocks' ),
					'not_found'             => esc_html__( 'No Theme Builder found.', 'ablocks' ),
					'not_found_in_trash'    => esc_html__( 'No Theme Builder found in Trash.', 'ablocks' ),
					'archives'              => esc_html__( 'Theme Builder archives', 'ablocks' ),
				),
				'public'                => true,
				'publicly_queryable'    => true,
				'show_ui'               => true,
				'show_in_menu'          => false,
				'show_in_admin_bar'     => false,
				'show_in_nav_menus'     => false,
				'hierarchical'          => false,
				'has_archive'           => false,
				'rewrite'               => false,
				'query_var'             => true,
				'delete_with_user'      => false,
				'supports'              => array( 'title', 'editor', 'custom-fields' ),
				'show_in_rest'          => true,
				'rest_base'             => $post_type,
				'rest_namespace'        => ABLOCKS_PLUGIN_SLUG . '/v1',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'capability_type'       => 'post',
				'map_meta_cap'          => true,
			)
		);
	}

	public function register_ablocks_tb_meta() {
		$course_meta = [
			'ablocks_tb_template_type'  => 'string',
		];

		foreach ( $course_meta as $meta_key => $meta_value_type ) {
			register_meta(
				'post',
				$meta_key,
				array(
					'object_subtype' => 'ablocks_tb',
					'type'           => $meta_value_type,
					'single'         => true,
					'show_in_rest'   => true,
				)
			);
		}
		// Display Rule
		register_meta(
			'post',
			'ablocks_tb_template_display_locations',
			array(
				'object_subtype' => 'ablocks_tb',
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => [
								'label'   => array(
									'type' => 'string',
								),
								'value' => array(
									'type' => 'string',
								),
								'specific'  => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'label' => array(
												'type' => 'string',
											),
											'value' => array(
												'type' => 'string',
											),
										),
									),
								),
							],
						),
					),
				],
			)
		);
		// Exclusion Rule
		register_meta(
			'post',
			'ablocks_tb_template_not_display_locations',
			array(
				'object_subtype' => 'ablocks_tb',
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => [
								'label'   => array(
									'type' => 'string',
								),
								'value' => array(
									'type' => 'string',
								),
								'specific'  => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'label' => array(
												'type' => 'string',
											),
											'value' => array(
												'type' => 'string',
											),
										),
									),
								),
							],
						),
					),
				],
			)
		);
		// Roles
		register_meta(
			'post',
			'ablocks_tb_template_target_user_roles',
			array(
				'object_subtype' => 'ablocks_tb',
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => [
								'label'   => array(
									'type' => 'string',
								),
								'value' => array(
									'type' => 'string',
								),
							],
						),
					),
				],
			)
		);

	}
}
