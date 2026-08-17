<?php

namespace StoreEngine\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Template {

	public static function template_path() {
		return apply_filters( 'storeengine/template_path', 'storeengine/' );
	}

	public static function plugin_path() {
		return apply_filters( 'storeengine/plugin_path', STOREENGINE_ROOT_DIR_PATH );
	}

	public static function get_template_part( $slug, $name = '' ) {
		$template = false;
		if ( $name ) {
			$template = STOREENGINE_TEMPLATE_DEBUG_MODE ? '' : locate_template(
				array(
					"{$slug}-{$name}.php",
					self::template_path() . "{$slug}-{$name}.php",
				)
			);

			if ( ! $template ) {
				$fallback = self::plugin_path() . "/templates/{$slug}-{$name}.php";
				$template = file_exists( $fallback ) ? $fallback : '';
			}
		}

		if ( ! $template ) {
			// If template file doesn't exist, look in yourtheme/slug.php and yourtheme/storeengine/slug.php.
			if ( STOREENGINE_TEMPLATE_DEBUG_MODE ) {
				$template = '';
			} else {
				$template = locate_template( [ "{$slug}.php", self::template_path() . "{$slug}.php" ] );
			}
		}

		// Allow 3rd party plugins to filter template file from their plugin.
		$template = apply_filters( 'storeengine/get_template_part', $template, $slug, $name );

		if ( $template ) {
			load_template( $template, false );
		}
	}

	public static function locate_template( $template_name, $template_path = '', $default_path = '' ) {
		if ( ! $template_path ) {
			$template_path = self::template_path();
		}

		if ( ! $default_path ) {
			$default_path = self::plugin_path() . 'templates/';
		}

		// Look within passed path within the theme - this is priority.
		if ( false !== strpos( $template_name, 'storeengine_product_category' ) || false !== strpos( $template_name, 'storeengine_product_tag' ) ) {
			$cs_template = str_replace( '_', '-', $template_name );
			$template    = locate_template(
				array(
					trailingslashit( $template_path ) . $cs_template,
					$cs_template,
				)
			);
		}

		if ( empty( $template ) ) {
			$template = locate_template(
				array(
					trailingslashit( $template_path ) . $template_name,
					$template_name,
				)
			);
		}

		// Get default template/.
		if ( ! $template || STOREENGINE_TEMPLATE_DEBUG_MODE ) {
			if ( empty( $cs_template ) ) {
				$template = $default_path . $template_name;
			} else {
				$template = $default_path . $cs_template;
			}
		}

		// Return what we found.
		return apply_filters( 'storeengine/locate_template', $template, $template_name, $template_path );
	}

	public static function get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		$template = self::locate_template( $template_name, $template_path, $default_path );

		// Allow 3rd party plugin filter template file from their plugin.
		$filter_template = apply_filters( 'storeengine/get_template', $template, $template_name, $args, $template_path, $default_path );

		if ( $filter_template !== $template ) {
			if ( ! file_exists( $filter_template ) ) {
				/* translators: %s template */
				_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( '%s does not exist.', 'storeengine' ), '<code>' . esc_html( $filter_template ) . '</code>' ), '1.0.0' );

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
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						// translators: %s. Class-Method name.
						esc_html__( 'The “action_args” can not be overwritten when calling %s.', 'storeengine' ),
						esc_html( __METHOD__ )
					),
					'1.0.0'
				);
				unset( $args['action_args'] );
			}

			/**
			 * This use of extract() cannot be removed. There are many possible ways that
			 * templates could depend on variables that it creates existing, and no way to
			 * detect and deprecate it.
			 *
			 * Passing the EXTR_SKIP flag is the safest option, ensuring globals and
			 * function variables cannot be overwritten.
			 * @see load_template()
			 */
			extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		do_action( 'storeengine/before_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );

		include $action_args['located'];

		do_action( 'storeengine/after_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );
	}

	/**
	 * Returns rendered content for the template.
	 *
	 * @param string $template_name Template file name/path.
	 * @param array $args Args to decode (will be available as variable inside the template).
	 * @param string $template_path Template path (relative from default/root path).
	 * @param string $default_path Template root path (default to plugins template directory).
	 *
	 * @return string
	 */
	public static function get_template_content( string $template_name, array $args = [], string $template_path = '', string $default_path = '' ): string {
		ob_start();
		self::get_template( $template_name, $args, $template_path, $default_path );
		$rendered = (string) ob_get_clean();

		return apply_filters( 'storeengine/template/get_content', $rendered, $template_name, $args, $template_path, $default_path );
	}
}
