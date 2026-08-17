<?php
namespace ABlocks\Blocks\FormBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Blocks\FormBuilder\EmailVerification;
use SplTempFileObject;
/**
 * @class Query
 */
class Helper {
	public static function merge_query_params(
		string $url,
		string $attr = '',
		bool $check_alloed_host = false
	) : string {
		$parsed_url = wp_parse_url( $url );
		$curr_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$parsed_url['query'] = $parsed_url['query'] ?? '';
		$parsed_url['scheme'] = $parsed_url['scheme'] ?? '';
		$parsed_url['host'] = $parsed_url['host'] ?? $curr_host;
		$parsed_url['path'] = $parsed_url['path'] ?? '';

		// wp_send_json($parsed_url);
		if (
			$check_alloed_host &&
			! in_array(
				$parsed_url['host'],
				array_merge(
					apply_filters( 'allowed_redirect_hosts', [] ),
					[
						$curr_host
					]
				),
				true
			)
		) {
			return home_url( '/' );
		}

		parse_str( $parsed_url['query'], $query_params );

		foreach ( explode( ',', $attr ) ?? [] as $param ) {
			$q = explode( '|', $param );
			if (
				count( $q ?? [] ) === 2
			) {
				$query_params[ $q[0] ] = $q[1];
			}
		}

		$new_query = http_build_query( $query_params );

		$new_url = '';
		if ( isset( $parsed_url['scheme'] ) && ! empty( $parsed_url['scheme'] ) ) {
			$new_url .= $parsed_url['scheme'] . '://';
			$new_url .= $parsed_url['host'];
			$new_url .= ( isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '' );
		}
		$new_url .= $parsed_url['path'] . ( empty( $new_query ) ? '' : '?' . $new_query );

		return empty( $new_url ) ? home_url( '/' ) : $new_url;
	}


	public static function form_builder_default_data( array $data ) : array {
		$form_builder_default = [];
		$form_builder_default['site_title']  = get_bloginfo( 'name' );
		$form_builder_default['admin_email'] = 'contact@example.com';

		if ( current_user_can( 'manage_options' ) ) {
			$form_builder_default['admin_email']      = get_option( 'admin_email' );
		}

		$data['form_builder'] = $form_builder_default;
		return $data;
	}
}
