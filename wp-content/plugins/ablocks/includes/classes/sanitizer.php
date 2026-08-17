<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitizer {
	public static function sanitize_payload( $schema, $payload ) {
		$sanitized_payload = array();
		foreach ( $schema as $key => $type ) {
			if ( isset( $payload[ $key ] ) ) {
				switch ( $type ) {
					case 'integer':
						$sanitized_payload[ $key ] = (int) absint( sanitize_text_field( $payload[ $key ] ) );
						break;
					case 'float':
						$sanitized_payload[ $key ] = (float) floatval( sanitize_text_field( $payload[ $key ] ) );
						break;
					case 'string':
						$sanitized_payload[ $key ] = (string) sanitize_text_field( $payload[ $key ] );
						break;
					case 'url':
						$sanitized_payload[ $key ] = esc_url_raw( $payload[ $key ] );
						break;
					case 'email':
						$sanitized_payload[ $key ] = sanitize_email( $payload[ $key ] );
						break;
					case 'textarea':
						$sanitized_payload[ $key ] = sanitize_textarea_field( $payload[ $key ] );
						break;
					case 'boolean':
						$sanitized_payload[ $key ] = self::sanitize_checkbox_field( $payload[ $key ] );
						break;
					case 'array':
						$sanitized_payload[ $key ] = self::sanitize_array_field( $payload[ $key ] );
						break;
					case 'json':
						$sanitized_payload[ $key ] = self::sanitize_json_form_data( $payload[ $key ] );
						break;
					case 'post':
						$sanitized_payload[ $key ] = wp_kses_post( $payload[ $key ] );
						break;
					default:
						$sanitized_payload[ $key ] = sanitize_text_field( $payload[ $key ] );
						break;
				}//end switch
			}//end if
		}//end foreach
		return $sanitized_payload;
	}
	public static function sanitize_json_form_data( $data, $schema = [] ) {
		$data = is_array( $data ) ? $data : json_decode( stripslashes( $data ), true );

		if ( ! is_array( $data ) ) {
			return self::cast_value_type( sanitize_text_field( $data ) );
		}

		$sanitize_recursive = function ( $item, $child_schema = [] ) use ( &$sanitize_recursive ) {
			if ( is_array( $item ) ) {
				$result = [];
				foreach ( $item as $key => $value ) {
					$sanitized_key = sanitize_text_field( $key );

					if ( is_array( $value ) || is_object( $value ) ) {
						// Recursively sanitize nested arrays/objects
						$result[ $sanitized_key ] = $sanitize_recursive( (array) $value, $child_schema[ $sanitized_key ]['schema'] ?? [] );
					} else {
						// sanitize value and auto-cast type
						$clean_value     = sanitize_text_field( $value );
						$result[ $sanitized_key ]  = self::cast_value_type( $clean_value );
					}
				}
				return $result;
			}

			return self::cast_value_type( sanitize_text_field( $item ) );
		};

		return $sanitize_recursive( $data, $schema );
	}

	private static function cast_value_type( $value ) {
		// Check for boolean
		if ( is_string( $value ) ) {
			$lower = strtolower( $value );
			if ( $lower === 'true' ) {
				return true;
			}
			if ( $lower === 'false' ) {
				return false;
			}
		}

		// Check for numeric
		if ( is_numeric( $value ) ) {
			return strpos( $value, '.' ) !== false ? (float) $value : (int) $value;
		}

		// Default: string
		return (string) $value;
	}

	public static function sanitize_array_field( $array_data ) {
		$array_data = is_array( $array_data ) ? $array_data : json_decode( stripslashes( $array_data ) );
		$boolean = [ 'true', 'false', '1', '0' ];
		if ( is_array( $array_data ) ) {
			foreach ( $array_data as $key => &$value ) {
				if ( is_array( $value ) ) {
					$value = self::sanitize_array_field( $value );
				} else {
					$value = in_array( $value, $boolean, true ) || is_bool( $value ) ? self::sanitize_checkbox_field( $value ) : sanitize_text_field( $value );
				}
			}
		}
		return $array_data;
	}
	public static function sanitize_checkbox_field( $boolean ) {
		return (bool) filter_var( sanitize_text_field( $boolean ), FILTER_VALIDATE_BOOLEAN );
	}
}
