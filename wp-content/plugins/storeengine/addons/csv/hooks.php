<?php

namespace StoreEngine\Addons\Csv;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	public static function init() {
		$self = new self();
		add_action( 'storeengine/csv/clean_tmp_csv', [ $self, 'clean_tmp_csv' ] );
		add_filter( 'storeengine/backend_scripts_data', [ $self, 'add_scripts_data' ] );
	}

	public function clean_tmp_csv( string $filepath ) {
		if ( file_exists( $filepath ) ) {
			wp_delete_file( $filepath );
		}
	}

	public function add_scripts_data( array $data ): array {
		return array_merge( $data, [
			'csv' => [
				'rcp_activated' => function_exists( 'rcp_get_membership_level' ),
			],
		] );
	}

}
