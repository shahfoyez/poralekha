<?php
namespace ABlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class API {
	public static function init() {
		$self = new self();
		$self->register_routes();
	}
	public static function register_routes() {
		add_action( 'rest_api_init', function () {
			( new \ABlocks\API\FormBuilderController() )->register_routes();
			( new \ABlocks\API\SearchController() )->register_routes();
			( new \ABlocks\API\LoopBuilderController() )->register_routes();
		});
	}
}
