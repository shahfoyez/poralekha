<?php

namespace ABlocks\import\ThemeOptions;

use ABlocks\Helper;

class CodeStar {

	public static function import( string $file_path ) {
		Helper::emit_sse_message([
			'action' => 'log',
			'level' => 'info',
			'message' => __( 'Starting CodeStar theme options import process.', 'ablocks' ),
		]);
		if ( file_exists( $file_path ) ) {
			//phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$options_data = json_decode( file_get_contents( $file_path ), true );

			// Apply the options data using Codestar functions
			if ( function_exists( 'cs_set_option' ) ) {
				foreach ( $options_data as $option_key => $option_value ) {
					cs_set_option( $option_key, $option_value );
				}
			}
			Helper::emit_sse_message([
				'action' => 'log',
				'level' => 'info',
				'message' => __( 'Codestar theme options imported successfully.', 'ablocks' ),
			]);
		} else {
			Helper::emit_sse_message([
				'action' => 'log',
				'level' => 'warning',
				'message' => __( 'Codestar theme options import file not found!', 'ablocks' ),
			]);
		}//end if
	}

}
