<?php
/**
 * Class for the Redux importer.
 *
 * @see https://wordpress.org/plugins/redux-framework/
 * @package ABlocks
 */

namespace ABlocks\import\ThemeOptions;

use ABlocks\Helper;
use ReduxFrameworkInstances;

class Redux {

	public static function import( array $import_data ) {
		// Redux plugin is not active!
		if ( ! class_exists( 'ReduxFramework' ) ) {
			Helper::emit_sse_message([
				'action' => 'log',
				'level' => 'warning',
				'message' => __( 'The Redux plugin is not activated, so the Redux import was skipped!', 'ablocks' ),
			]);

			return;
		}

		Helper::emit_sse_message([
			'action' => 'log',
			'level' => 'info',
			'message' => __( 'Redux options import started.', 'ablocks' ),
		]);
		foreach ( $import_data as $redux_item ) {
			$file_path = Helper::remote_to_tmp_file( $redux_item['file_url'] );
			if ( ! file_exists( $file_path ) ) {
				Helper::emit_sse_message( [
					'action'  => 'log',
					'level'   => 'warning',
					'message' => __( 'Redux import file does not exist.', 'ablocks' ),
				] );
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$redux_options_raw_data = file_get_contents( $file_path );
			if ( ! $redux_options_raw_data ) {
				Helper::emit_sse_message( [
					'action'  => 'log',
					'level'   => 'warning',
					'message' => __( 'Redux import file could not be read.', 'ablocks' ),
				] );
				continue;
			}

			$redux_options_data = json_decode( $redux_options_raw_data, true );
			$redux_framework = ReduxFrameworkInstances::get_instance( $redux_item['option_name'] );

			if ( isset( $redux_framework->args['opt_name'] ) ) {
				// Import Redux settings.
				if ( ! empty( $redux_framework->options_class ) && method_exists( $redux_framework->options_class, 'set' ) ) {
					$redux_framework->options_class->set( $redux_options_data );
				} else {
					// Handle backwards compatibility.
					$redux_framework->set_options( $redux_options_data );
				}

				Helper::emit_sse_message( [
					'action'  => 'log',
					'level'   => 'info',
					/* translators: %s - the name of the Redux option. */
					'message' => sprintf( esc_html__(
					'Redux settings import for: %s finished successfully!', 'ablocks' ), $redux_item['option_name'] ),
				] );
			} else {
				Helper::emit_sse_message( [
					'action'  => 'log',
					'level'   => 'warning',
					/* translators: %s - the name of the Redux option. */
					'message' => sprintf( esc_html__(
					'The Redux option name: %s, was not found in this WP site, so it was not imported!', 'ablocks' ), $redux_item['option_name'] ),
				] );
			}//end if
		}//end foreach
	}
}
