<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FontLoadLocally {

	const FONTS_FOLDER = 'ablocks-fonts';

	/**
	 * Process a list of fonts and weights
	 *
	 * @param array $fonts Example: ["Roboto" => ["400","700"]]
	 */
	public function process_font_queue( $fonts ) {
		if ( ! is_array( $fonts ) || empty( $fonts ) ) {
			return;
		}
		foreach ( $fonts as $family => $weights ) {
			if ( ! is_array( $weights ) ) {
				continue;
			}

			// Only download missing weights
			$weights_to_download = [];
			foreach ( $weights as $weight ) {
				if ( ! $this->is_font_weight_available( $family, $weight ) ) {
					$weights_to_download[] = $weight;
				}
			}

			if ( ! empty( $weights_to_download ) ) {
				$this->download_font_family( $family, $weights_to_download );
			}
		}
	}

	/**
	 * Check if a specific font family and weight exists locally
	 *
	 * @param string $family
	 * @param string $weight
	 * @return bool
	 */
	public function is_font_weight_available( $family, $weight ) {
		$upload_dir = wp_upload_dir();
		$folder     = trailingslashit( $upload_dir['basedir'] ) . self::FONTS_FOLDER . '/' . $this->sanitize_family( $family );

		if ( ! is_dir( $folder ) ) {
			return false;
		}

		$weight_key = $weight === '400' || $weight === 'normal' ? '400' : $weight;
		$files = glob( $folder . '/' . $weight_key . '-*.woff2' );

		return ! empty( $files );
	}

	/**
	 * Download a Google Font family via CSS2 API
	 *
	 * @param string $family
	 * @param array  $weights
	 */
	public function download_font_family( $family, $weights ) {
		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] ) . self::FONTS_FOLDER;
		wp_mkdir_p( $base_dir );

		$family_dir = $base_dir . '/' . $this->sanitize_family( $family );
		wp_mkdir_p( $family_dir );

		$css_url    = $this->build_css_url( $family, $weights );
		$woff2_list = $this->get_woff2_urls( $css_url );

		foreach ( $woff2_list as $woff ) {
			$weight   = $woff['weight'];
			$file_url = $woff['url'];
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url 
			$filename = $weight . '-' . basename( parse_url( $file_url, PHP_URL_PATH ) );
			$filepath = $family_dir . '/' . $filename;

			if ( file_exists( $filepath ) ) {
				continue;
			}

			$response = wp_remote_get( $file_url, [ 'timeout' => 15 ] );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore  WordPress.PHP.DevelopmentFunctions.error_log_error_log  
					error_log( "Failed to download font file: {$file_url}" );
				}
				continue;
			}

			file_put_contents( $filepath, wp_remote_retrieve_body( $response ) );
		}//end foreach
	}

	/**
	 * Build Google Fonts CSS2 API URL
	 *
	 * @param string $family
	 * @param array  $weights
	 * @return string
	 */
	public function build_css_url( $family, $weights ) {
		$family_encoded = str_replace( ' ', '+', $family );

		// Remove duplicates & sort weights
		$weights = array_unique( array_map( 'strval', $weights ) );
		sort( $weights );

		// Correct Google Fonts format: wght@200;400;700;900
		$weights_str = 'wght@' . implode( ';', $weights );

		return "https://fonts.googleapis.com/css2?family={$family_encoded}:{$weights_str}&display=swap";
	}


	/**
	 * Extract .woff2 URLs and corresponding weight from Google Fonts CSS
	 *
	 * @param string $css_url
	 * @return array [['weight'=>'400','url'=>'https://...'], ...]
	 */
	public function get_woff2_urls( $css_url ) {
		$args = [
			'timeout' => 15,
			'headers' => [
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
			],
		];

		$response = wp_remote_get( $css_url, $args );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore  WordPress.PHP.DevelopmentFunctions.error_log_error_log  
				error_log( 'Failed to fetch Google Fonts CSS: ' . $css_url );
			}
			return [];
		}

		$css = wp_remote_retrieve_body( $response );
		$matches = [];
		preg_match_all(
			'/@font-face\s*{[^}]*font-weight:\s*(\d+);[^}]*src:\s*url\(([^)]+\.woff2)\)/i',
			$css,
			$matches,
			PREG_SET_ORDER
		);

		$results = [];
		foreach ( $matches as $m ) {
			$results[] = [
				'weight' => $m[1],
				'url'    => $m[2],
			];
		}

		return $results;
	}

	/**
	 * Enqueue local fonts if available, otherwise enqueue Google Fonts
	 *
	 * @param array $fonts
	 */
	public function enqueue_fonts( $fonts ) {
		if ( ! is_array( $fonts ) || empty( $fonts ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$css = '';

		$missing_fonts = []; // <-- Store missing fonts here

		foreach ( $fonts as $family => $weights ) {

			$local_available = true;

			// Check each requested weight
			foreach ( $weights as $weight ) {
				if ( ! $this->is_font_weight_available( $family, $weight ) ) {
					$local_available = false;

					// Store missing for Google Fonts fallback
					if ( ! isset( $missing_fonts[ $family ] ) ) {
						$missing_fonts[ $family ] = [];
					}
					$missing_fonts[ $family ][] = $weight;
				}
			}

			// If local font exists, register @font-face rules
			if ( $local_available ) {
				$folder = trailingslashit( $upload_dir['baseurl'] ) .
						self::FONTS_FOLDER . '/' . $this->sanitize_family( $family );

				$files = glob(
					trailingslashit( $upload_dir['basedir'] ) .
					self::FONTS_FOLDER . '/' . $this->sanitize_family( $family ) . '/*.woff2'
				);

				foreach ( $files as $file_path ) {
					$file_name = basename( $file_path );

					if ( preg_match( '/^(\d+)-/', $file_name, $m ) ) {
						$font_weight = $m[1];
					} else {
						$font_weight = '400';
					}

					$css .= "@font-face {
						font-family: '{$family}';
						font-style: normal;
						font-weight: {$font_weight};
						font-display: swap;
						src: url('{$folder}/{$file_name}') format('woff2');
					}";
				}
			}//end if
		}//end foreach

		/**
		 * 1. ENQUEUE LOCAL FONTS IF ANY FOUND
		 */
		if ( ! empty( $css ) ) {
			wp_register_style( 'ablocks-local-fonts', false );
			wp_enqueue_style( 'ablocks-local-fonts' );
			wp_add_inline_style( 'ablocks-local-fonts', AssetsGenerator::minify_css( $css ) );
		}

		/**
		 * 2. ENQUEUE ONLY MISSING GOOGLE FONTS
		 */
		if ( ! empty( $missing_fonts ) ) {
			$family_strings = [];

			foreach ( $missing_fonts as $family => $weights ) {

				// sanitize weights
				$weights = array_unique( array_map( 'strval', $weights ) );
				sort( $weights );

				$family_encoded = str_replace( ' ', '+', $family );
				$family_strings[] = "{$family_encoded}:wght@" . implode( ';', $weights );
			}

			$google_url = 'https://fonts.googleapis.com/css2?family=' .
				implode( '&family=', $family_strings ) . '&display=swap';

			wp_enqueue_style( 'ablocks-google-fonts', esc_url( $google_url ), [], null );
		}
	}


	/**
	 * Return locally-available @font-face descriptors for a family.
	 *
	 * @param string $family
	 * @param array  $weights Requested weights.
	 * @return array [ [ 'weight' => '400', 'src' => 'https://.../400-xxx.woff2' ], ... ]
	 */
	public function get_local_font_faces( $family, $weights = [] ) {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . self::FONTS_FOLDER . '/' . $this->sanitize_family( $family );
		$url_base   = trailingslashit( $upload_dir['baseurl'] ) . self::FONTS_FOLDER . '/' . $this->sanitize_family( $family );

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$faces = [];
		foreach ( (array) glob( $dir . '/*.woff2' ) as $file_path ) {
			$file_name   = basename( $file_path );
			$font_weight = preg_match( '/^(\d+)-/', $file_name, $m ) ? $m[1] : '400';

			// If specific weights were requested, only include those.
			if ( ! empty( $weights ) && ! in_array( (string) $font_weight, array_map( 'strval', $weights ), true ) ) {
				continue;
			}

			$faces[] = [
				'weight' => (string) $font_weight,
				'src'    => $url_base . '/' . $file_name,
			];
		}

		return $faces;
	}

	/**
	 * From a [family => weights] map, return the subset not available locally.
	 *
	 * @param array $fonts
	 * @return array [family => [missing weights]]
	 */
	public function get_missing( $fonts ) {
		$missing = [];
		if ( ! is_array( $fonts ) ) {
			return $missing;
		}
		foreach ( $fonts as $family => $weights ) {
			foreach ( (array) $weights as $weight ) {
				if ( ! $this->is_font_weight_available( $family, $weight ) ) {
					$missing[ $family ][] = (string) $weight;
				}
			}
		}
		return $missing;
	}

	/**
	 * Sanitize font family for folder names
	 *
	 * @param string $family
	 * @return string
	 */
	public function sanitize_family( $family ) {
		return str_replace( ' ', '-', strtolower( $family ) );
	}
}
