<?php
namespace ABlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ABlocks\traits\Importer;

class Helper {

	use Importer;

	public static function get_time() {
		return time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	}

	public static function get_settings( $key, $default = null ) {
		global $ablocks_settings;

		if ( isset( $ablocks_settings->{$key} ) ) {
			return $ablocks_settings->{$key};
		}

		return $default;
	}

	public static function get_page_permalink( $page, $fallback = null ) {
		$page_id   = self::get_settings( $page );
		$permalink = 0 < $page_id ? get_permalink( $page_id ) : '';
		if ( ! $permalink ) {
			$permalink = is_null( $fallback ) ? get_home_url() : $fallback;
		}

		return apply_filters( 'ablocks/get_' . $page . '_permalink', $permalink );
	}

	public static function get_logout_url( $redirect = '' ) {
		$redirect = $redirect ? $redirect : apply_filters( 'ablocks/logout_default_redirect_url', self::get_page_permalink( 'dashboard_page' ) );

		return wp_logout_url( $redirect );
	}
	public static function is_enabled_block( $block_name, $parent_block_name = '' ) {
		global $ablocks_blocks;
		$block_name = ! empty( $parent_block_name ) ? $parent_block_name : $block_name;
		if ( isset( $ablocks_blocks->{$block_name} ) ) {
			return (bool) $ablocks_blocks->{$block_name};
		}
		return false;
	}


	public static function is_plugin_installed( $path ) {
		$installed_plugins = get_plugins();
		return isset( $installed_plugins[ $path ] );
	}

	public static function is_active_academy() {
		$academy = 'academy/academy.php';
		return self::is_plugin_active( $academy );
	}


	public static function is_active_storeengine() {
		$storeengine = 'storeengine/storeengine.php';
		return self::is_plugin_active( $storeengine );
	}

	public static function is_active_ablocks_pro() {
		$ablocks = 'ablocks-pro/ablocks-pro.php';
		return self::is_plugin_active( $ablocks );
	}
	public static function is_active_wp_map_block() {
		$wp_map_block = 'wp-map-block/wp-map-block.php';
		return self::is_plugin_active( $wp_map_block );
	}
	public static function is_active_quizpress() {
		return class_exists( 'QuizPress' );
	}
	public static function is_active_easy_content_manager() {
		return class_exists( 'EasyContentManager' );
	}

	public static function is_enabled_assets_generation() {
		// Default OFF — combining/generating per-page asset files churns while a
		// site is still being built, so it's recommended (via the Performance tab
		// notice) once the site is complete rather than forced on. When enabled it
		// merges every block's CSS/JS into one per-page file, inlined when small
		// (see Assets::enqueue_frontend_assets), removing the per-block
		// render-blocking stylesheets.
		$flag = (bool) self::get_settings( 'enabled_assets_file_generation', false );
		return apply_filters( 'ablocks/is_enabled_assets_generation', $flag );
	}

	public static function is_plugin_active( $basename ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $basename );
	}

	public static function is_dev_mode_enable() {
		$environment = wp_get_environment_type();
		if ( 'local' === $environment || 'development' === $environment ) {
			return true;
		}
	}

	public static function get_admin_menu_list() {
		$menu                                     = [];
		$menu[ ABLOCKS_PLUGIN_SLUG ]              = [
			'parent_slug' => ABLOCKS_PLUGIN_SLUG,
			'title'       => __( 'Dashboard', 'ablocks' ),
			'capability'  => 'manage_options',
		];
		if ( self::is_enabled_block( 'form-builder' ) ) {
			$menu[ ABLOCKS_PLUGIN_SLUG . '-submissions' ]   = [
				'parent_slug' => ABLOCKS_PLUGIN_SLUG,
				'title'       => __( 'Submissions', 'ablocks' ),
				'capability'  => 'manage_options',
			];
		}
		if ( self::get_addon_active_status( 'theme-builder' ) ) {
			$menu[ ABLOCKS_PLUGIN_SLUG . '-theme-builder' ]   = [
				'parent_slug' => ABLOCKS_PLUGIN_SLUG,
				'title'       => __( 'Theme Builder', 'ablocks' ),
				'capability'  => 'manage_options',
			];
		}
		$menu[ ABLOCKS_PLUGIN_SLUG . '-addons' ]   = [
			'parent_slug' => ABLOCKS_PLUGIN_SLUG,
			'title'       => __( 'Add-ons', 'ablocks' ),
			'capability'  => 'manage_options',
		];
		$menu[ ABLOCKS_PLUGIN_SLUG . '-scanner' ]   = [
			'parent_slug' => ABLOCKS_PLUGIN_SLUG,
			'title'       => __( 'Site Scanner', 'ablocks' ),
			'capability'  => 'manage_options',
		];
		$menu[ ABLOCKS_PLUGIN_SLUG . '-settings' ]   = [
			'parent_slug' => ABLOCKS_PLUGIN_SLUG,
			'title'       => __( 'Settings', 'ablocks' ),
			'capability'  => 'manage_options',
		];
		if ( ! defined( 'ABLOCKS_PRO_VERSION' ) ) {
			$menu[ ABLOCKS_PLUGIN_SLUG . '-get-pro' ] = [
				'parent_slug' => ABLOCKS_PLUGIN_SLUG,
				'title'       => '<span class="dashicons dashicons-awards academy-blue-color"></span> ' . __( 'Get Pro', 'ablocks' ),
				'capability'  => 'manage_options',
			];
		}
		return apply_filters( 'ablocks/admin_menu_list', $menu );
	}

	public static function get_preloader_html() {
		ob_start();
		?>
			<div class="ablocks-initial-preloader"><?php esc_html_e( 'Loading...', 'ablocks' ); ?></div>
		<?php
		return ob_get_clean();
	}
	public static function has_value( $value ) {
		return isset( $value ) && ! empty( $value );
	}

	public static function get_array_value( $array, $key, $default ) {
		return ( isset( $array[ $key ] ) && ! empty( $array[ $key ] ) ) ? $array[ $key ] : $default;
	}

	public static function get_responsive_value( $attribute, $attribute_object_key, $device, $attribute_default_value = [] ) {
		// Closure to "clean" a value (similar to your JS clean() helper)
		$clean = function( $value ) {
			return self::has_value( $value ) ? $value : null;
		};

		// Desktop value (fallback to default, or false if nothing found)
		$desktop_value =
			$clean( $attribute[ $attribute_object_key ] ?? null ) ??
			$clean( $attribute_default_value[ $attribute_object_key ] ?? null ) ??
			false;

		// Tablet value (fallback to desktop if nothing found)
		$tablet_key = $attribute_object_key . 'Tablet';
		$tablet_value =
			$clean( $attribute[ $tablet_key ] ?? null ) ??
			$clean( $attribute_default_value[ $tablet_key ] ?? null ) ??
			$desktop_value;

		// Mobile value (fallback to tablet if nothing found)
		$mobile_key = $attribute_object_key . 'Mobile';
		$mobile_value =
			$clean( $attribute[ $mobile_key ] ?? null ) ??
			$clean( $attribute_default_value[ $mobile_key ] ?? null ) ??
			$tablet_value;

		// Return based on device
		switch ( $device ) {
			case 'Mobile':
				return $mobile_value;
			case 'Tablet':
				return $tablet_value;
			default:
				return $desktop_value;
		}
	}

	public static function is_gutenberg_editor() {
		global $pagenow;
		if ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ( isset( $_GET['context'] ) && 'edit' === $_GET['context'] ) || ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] );
	}
	public static function attr_shortcode( $attr_array ) {
		$html_attr = '';
		foreach ( $attr_array as $attr_name => $attr_val ) {
			if ( empty( $attr_val ) ) {
				continue;
			}
			if ( is_array( $attr_val ) ) {
				$html_attr .= $attr_name . '="' . implode( ',', $attr_val ) . '" ';
			} else {
				$html_attr .= $attr_name . '="' . $attr_val . '" ';
			}
		}
		return $html_attr;
	}

	public static function get_attribute_value( $attributes, $attribute_name ) {
		return isset( $attributes[ $attribute_name ] ) ? $attributes[ $attribute_name ] : '';
	}

	public static function get_terms_list( $taxonomy = 'category' ) {
		$options = [];
		$terms   = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		] );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = [
					'label' => $term->name,
					'value' => $term->term_id,
				];
			}
		}

		return $options;
	}

	public static function get_author_data( $post_id, $author_id = false ) {
		$author_id = $author_id ?: get_post_field( 'post_author', $post_id );
		$user = get_userdata( $author_id );

		if ( ! $user ) {
			wp_send_json_error( 'Author not found.', 404 );
		}

		$data = [
			'author_id' => $author_id,
			'author_name' => sanitize_text_field( $user->display_name ),
			'author_posts_count' => count_user_posts( $author_id ),
			'author_posts_url' => esc_url( get_author_posts_url( $author_id ) ),
			'author_profile_picture_url' => esc_url( get_avatar_url( $author_id ) ),
			'author_bio' => sanitize_text_field( get_user_meta( $author_id, 'description', true ) ),
			'author_email' => sanitize_email( $user->user_email ),
			'author_website' => esc_url( $user->user_url ),
			'author_first_name' => sanitize_text_field( get_user_meta( $author_id, 'first_name', true ) ),
			'author_last_name' => sanitize_text_field( get_user_meta( $author_id, 'last_name', true ) )
		];

		return $data;
	}

	public static function get_icon_picker_attribute( $attributePrefix = 'icon', $defaultValue = [] ) {
		$svgPathKey = $attributePrefix . 'SvgPath';
		$svgViewBoxKey = $attributePrefix . 'SvgViewBox';
		$svgClassKey = $attributePrefix . 'Class';

		$attribute = [
			$svgPathKey => [
				'type' => 'string',
				'source' => 'attribute',
				'selector' => 'svg.ablocks-svg-icon path',
				'attribute' => 'd',
			],
			$svgViewBoxKey => [
				'type' => 'string',
				'source' => 'attribute',
				'selector' => 'svg.ablocks-svg-icon',
				'attribute' => 'viewBox',
			],
			$svgClassKey => [
				'type' => 'string',
			],
		];

		if ( isset( $defaultValue['path'] ) && isset( $defaultValue['viewBox'] ) ) {
			$attribute[ $svgPathKey ]['default'] = $defaultValue['path'];
			$attribute[ $svgViewBoxKey ]['default'] = $defaultValue['viewBox'];
		}
		if ( isset( $defaultValue['className'] ) ) {
			$attribute[ $svgClassKey ]['default'] = $defaultValue['className'];
		}
		return $attribute;
	}

	public static function get_terms_for_post( $taxonomy, $post_id ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'all' ] );

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		return array_map( function( $term ) {
			return [
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			];
		}, $terms );
	}

	public static function get_taxonomies_data_for_post_type( $post_type ) {
		$all_taxonomies = get_object_taxonomies( $post_type, 'objects' );
		return array_values(array_map( function( $taxonomy ) {
			return [
				'value' => $taxonomy->name,
				'label' => $taxonomy->label,
			];
		}, $all_taxonomies ));
	}

	public static function get_post_excerpt( $post_id, $length = false ) {
		$excerpt = get_the_excerpt( $post_id );
		if ( $length ) {
			return wp_trim_words( $excerpt, $length, '...' );
		}
		return $excerpt;
	}

	public static function get_post_terms_as_string( $post_id, $taxonomy, $separator = ', ' ) {
		$terms = wp_get_post_terms( $post_id, $taxonomy );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$term_names = wp_list_pluck( $terms, 'name' );
			return implode( $separator, $term_names );
		}

		return '';
	}

	public static function get_post_time_date( $post_id, $dynamicContentAttribute, $is_time = false ) {
		$dateTimeType = $dynamicContentAttribute['dateTimeType'];
		$dateTimeFormat = $dynamicContentAttribute['dateTimeFormat'];
		$customDateTimeFormat = $dynamicContentAttribute['customDateTimeFormat'];

		$format = $dateTimeFormat;
		$defaultFormat = $is_time ? 'g:i a' : 'j M, Y';
		if ( ! $dateTimeFormat ) {
			$format = $defaultFormat;
		} elseif ( 'custom' === $dateTimeFormat ) {
			$format = $customDateTimeFormat || $defaultFormat;
		}

		$date_time = '';
		if ( ! $dateTimeType || 'post_published' === $dateTimeType ) {
			$date_time = get_the_date( $format, $post_id );
		} elseif ( 'post_modified' === $dateTimeType ) {
			$date_time = get_the_modified_date( $format, $post_id );
		}

		return $date_time;
	}

	public static function is_fse_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	public static function check_post_type_from_admin( $post_type ) {
		global $post;
		if ( is_admin() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $post && get_post_type( $post ) === $post_type ) {
				return true;
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) {
				return true;
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_GET['post'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$queried_post_type = get_post_type( sanitize_text_field( wp_unslash( $_GET['post'] ) ) );
				if ( $queried_post_type === $post_type ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function get_content_by_object_id( string $id_or_fse_slug ) : ?string {
		if ( is_numeric( $id_or_fse_slug ) ) {
			if (
				! current_user_can( 'manage_options' ) &&
				get_post_status( $id_or_fse_slug ) !== 'publish'
			) {
				return null;
			}
			return get_post_field( 'post_content', intval( $id_or_fse_slug ) );
		} elseif (
			! empty( $template = get_block_template( $id_or_fse_slug, 'wp_template_part' ) ) ||
			! empty( $template = get_block_template( $id_or_fse_slug ) )
		) {
			return $template->content;
		}
		return null;
	}

	public static function get_block_attributes( string $post_id, string $block_id, string $block_name ) : array {
		// Cache parsed blocks per object id for the request — this is called once
		// per loop/REST lookup and would otherwise re-fetch + re-parse the whole
		// post content every time.
		static $parsed_cache = [];
		if ( ! array_key_exists( $post_id, $parsed_cache ) ) {
			$post_content = self::get_content_by_object_id( $post_id );
			$parsed_cache[ $post_id ] = ( ! is_null( $post_content ) && is_array( $blocks = parse_blocks( $post_content ) ) )
				? $blocks
				: null;
		}
		if ( is_array( $parsed_cache[ $post_id ] ) ) {
			return self::get_block_attributes_recursive( $block_id, $block_name, $parsed_cache[ $post_id ] );
		}
		return [];
	}

	public static function get_block_attributes_recursive( string $block_id, string $block_name, array $blocks ) : array {
		foreach ( $blocks as $block ) {

			if (
				( $block['attrs']['block_id'] ?? '' ) === $block_id &&
				( $block['blockName'] ?? '' ) === $block_name
			) {
				return [
					'parentAttributes' => $block['attrs'] ?? [],
					'innerBlocks'      => self::extract_inner_blocks( $block['innerBlocks'] ?? [] ),
				];
			}

			if (
				array_key_exists( 'innerBlocks', $block ) &&
				is_array( $block['innerBlocks'] ) &&
				count( $block['innerBlocks'] ) > 0
			) {
				$data = self::get_block_attributes_recursive(
					$block_id,
					$block_name,
					$block['innerBlocks']
				);
				if ( ! empty( $data ) ) {
					return $data;
				}
			}

			if (
				isset( $block['blockName'] ) &&
				$block['blockName'] === 'core/template-part' &&
				! empty( $block['attrs']['slug'] )
			) {
				$part_slug = $block['attrs']['theme'] . '//' . $block['attrs']['slug'];
				$data = self::get_block_attributes( $part_slug, $block_id, $block_name );
				if ( ! empty( $data ) ) {
					return $data;
				}
			}
		}//end foreach
		return [];
	}

	public static function extract_inner_blocks( $innerBlocks ) {
		return array_map(function ( $inner_block ) {
			$block_data = [
				'blockName' => $inner_block['blockName'],
				'attributes' => $inner_block['attrs'],
			];
			if ( ! empty( $inner_block['innerBlocks'] ) ) {
				$block_data['innerBlocks'] = self::extract_inner_blocks( $inner_block['innerBlocks'] );
			}
			return $block_data;
		}, $innerBlocks);
	}

	public static function generate_schema_using_form_data( $customFields ) {
		$schema = [];

		foreach ( $customFields as $inputField ) {
			// Check if 'name' exists in the input field
			if ( isset( $inputField['name'] ) ) {
				$field_name = $inputField['name'];
				$input_type = $inputField['inputType'] ?? 'text'; // Default to 'text' if not specified

				// Map the input types to schema types
				switch ( $input_type ) {
					case 'Text':
						$schema[ $field_name ] = 'string';
						break;
					case 'Email':
						$schema[ $field_name ] = 'email';
						break;
					case 'Password':
						$schema[ $field_name ] = 'string';
						break;
					case 'Number':
						$schema[ $field_name ] = 'number';
						break;
					case 'Url':
						$schema[ $field_name ] = 'url';
						break;
					case 'Boolean':
						$schema[ $field_name ] = 'boolean';
						break;
					case 'Textarea':
						$schema[ $field_name ] = 'textarea';
						break;
					default:
						$schema[ $field_name ] = 'string'; // Default to string for unknown types
						break;
				}//end switch
			}//end if
		}//end foreach

		return $schema;
	}

	public static function sorted_input_fields_by_input_type( $blockData ) {
		$sorted_custom_fields = [];
		foreach ( $blockData as $block ) {
			$name = $block['attributes']['name'] ?? null;
			$inputType = $block['attributes']['inputType'] ?? null;

			if ( $name && isset( $custom_fields[ $name ] ) ) {
				$sorted_custom_fields[] = [
					'name' => $name,
					'inputType' => $inputType,
					'value' => $custom_fields[ $name ]
				];
			}
		}

		return $sorted_custom_fields;
	}


	public static function render_svg_icon_using_attr( $attributes = array() ) {
		$default_attributes = array(
			'path' => '',
			'viewBox' => '0 0 24 24',
			'className' => 'icon-class',
			'width' => '24',
			'height' => '24',
		);

		// Merge passed attributes with default values
		$attributes = array_merge( $default_attributes, $attributes );

		// Sanitize attributes for safety
		$path = esc_attr( $attributes['path'] );
		$viewBox = esc_attr( $attributes['viewBox'] );
		$className = esc_attr( $attributes['className'] );
		$width = esc_attr( $attributes['width'] );
		$height = esc_attr( $attributes['height'] );

		// Output the SVG
		return '
		<svg 
			xmlns="http://www.w3.org/2000/svg" 
			viewBox="' . $viewBox . '" 
			class="' . $className . '" 
			width="' . $width . '" 
			height="' . $height . '">
			<path d="' . $path . '"></path>
		</svg>';
	}

	public static function get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		$template = false;

		if ( ! $template ) {
			$template = self::locate_template( $template_name, $template_path, $default_path );
		}

		// Allow 3rd party plugin filter template file from their plugin.
		$filter_template = apply_filters( 'ablocks/get_template', $template, $template_name, $args, $template_path, $default_path );

		if ( $filter_template !== $template ) {
			if ( ! file_exists( $filter_template ) ) {
				/* translators: %s template */
				wc_doing_it_wrong( __FUNCTION__, sprintf( __( '%s does not exist.', 'ablocks' ), '<code>' . $filter_template . '</code>' ), '1.0.0' );

				return;
			}
			$template = $filter_template;
		}

		$action_args = array(
			'template_name' => $template_name,
			'template_path' => $template_path,
			'located'       => $template,
			'args'          => $args,
		);

		if ( ! empty( $args ) && is_array( $args ) ) {
			if ( isset( $args['action_args'] ) ) {
				wc_doing_it_wrong(
					__FUNCTION__,
					__( 'action_args should not be overwritten when calling ablocks/get_template.', 'ablocks' ),
					'1.0.0'
				);
				unset( $args['action_args'] );
			}
			extract( $args ); // @codingStandardsIgnoreLine
		}

		do_action( 'ablocks/before_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );
		include $action_args['located'];

		do_action( 'ablocks/after_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );
	}

	public static function locate_template( $template_name, $template_path = '', $default_path = '' ) {
		if ( ! $template_path ) {
			$template_path = self::template_path();
		}

		if ( ! $default_path ) {
			$default_path = self::plugin_path() . 'templates/';
		}

		if ( empty( $template ) ) {
			$template = locate_template(
				array(
					trailingslashit( $template_path ) . $template_name,
					$template_name,
				)
			);
		}
		if ( ! $template ) {
			$template = $default_path . $template_name;
		}

		// Return what we found.
		return apply_filters( 'ablocks/locate_template', $template, $template_name, $template_path );
	}

	public static function template_path() {
		return apply_filters( 'ablocks/template_path', 'ablocks/' );
	}
	public static function plugin_path() {
		return apply_filters( 'ablocks/plugin_path', ABLOCKS_ROOT_DIR_PATH );
	}

	public static function get_addon_active_status( $addon_name, $is_pro = false ) {
		global $ablocks_addons;
		if ( $is_pro && ! self::is_active_ablocks_pro() ) {
			return false;
		}
		if ( isset( $ablocks_addons->{$addon_name} ) ) {
			return (bool) $ablocks_addons->{$addon_name};
		}

		return false;
	}

	public static function sanitize_checkbox_field( $boolean ) {
		return filter_var( sanitize_text_field( $boolean ), FILTER_VALIDATE_BOOLEAN );
	}

	public static function is_valid_site_url( string $url ): bool {
		return str_starts_with( $url, get_option( 'siteurl' ) );
	}

	public static function get_script_loading_strategy() {
		return 'defer';
	}

	public static function is_static_front_page( $post_id ) {
		$show_on_front = get_option( 'show_on_front' );
		$page_on_front = (int) get_option( 'page_on_front' );
		return ( $show_on_front === 'page' && $page_on_front === (int) $post_id );
	}

	public static function get_public_post_type_options() {
		$args = [
			'public'   => true,
			'_builtin' => true,
		];
		$post_types = get_post_types( $args, 'objects' );
		unset( $post_types['attachment'] ); // Remove 'attachment' if present

		// Get custom post types
		$args['_builtin'] = false;
		$custom_post_types = get_post_types( $args, 'objects' );

		// Allow filters to modify the combined post types
		$all_post_types = apply_filters(
			'ablocks_theme_builder/location_rule_post_types',
			array_merge( $post_types, $custom_post_types )
		);

		// Format result
		$result = [];
		foreach ( $all_post_types as $post_type => $post_type_obj ) {
			$result[] = [
				'label' => $post_type_obj->label,
				'value' => $post_type,
			];
		}

		return $result;
	}

	public static function clear_third_party_plugin_cache() {

		// Breeze
		try {
			if ( class_exists( 'Breeze_Purge' ) ) {
				\Breeze_Purge::breeze_cache_flush();
			}
		} catch ( \Throwable $e ) {
			// phpcs:ignore  WordPress.PHP.DevelopmentFunctions.error_log_error_log 
			error_log( 'Breeze Cache Clear Failed: ' . $e->getMessage() );
		}

		// W3 Total Cache
		try {
			if ( function_exists( 'w3tc_flush_all' ) ) {
				\w3tc_flush_all();
			}
		} catch ( \Throwable $e ) {
			// phpcs:ignore  WordPress.PHP.DevelopmentFunctions.error_log_error_log 
			error_log( 'W3 Total Cache Clear Failed: ' . $e->getMessage() );
		}

		// WP Super Cache
		try {
			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				\wp_cache_clear_cache();
			}
		} catch ( \Throwable $e ) {
			// phpcs:ignore  WordPress.PHP.DevelopmentFunctions.error_log_error_log 
			error_log( 'WP Super Cache Clear Failed: ' . $e->getMessage() );
		}

		// LiteSpeed Cache
		try {
			if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
				\LiteSpeed_Cache_API::purge_all();
			}
		} catch ( \Throwable $e ) {
			// phpcs:ignore  WordPress.PHP.DevelopmentFunctions.error_log_error_log 
			error_log( 'LiteSpeed Cache Clear Failed: ' . $e->getMessage() );
		}

		// WP Fastest Cache
		try {
			if ( class_exists( 'WpFastestCache' ) ) {
				$wpfc = new \WpFastestCache();
				$wpfc->deleteCache();
			}
		} catch ( \Throwable $e ) {
			// phpcs:ignore  WordPress.PHP.DevelopmentFunctions.error_log_error_log 
			error_log( 'WP Fastest Cache Clear Failed: ' . $e->getMessage() );
		}

		// Autoptimize
		try {
			if ( function_exists( 'autoptimize_clearall' ) ) {
				\autoptimize_clearall();
			}
		} catch ( \Throwable $e ) {
			// phpcs:ignore  WordPress.PHP.DevelopmentFunctions.error_log_error_log 
			error_log( 'Autoptimize Clear Failed: ' . $e->getMessage() );
		}
	}
}
