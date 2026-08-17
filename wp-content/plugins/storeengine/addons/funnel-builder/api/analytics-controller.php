<?php
/**
 * Funnel analytics REST controller — per-step roll-up for a funnel.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder\Api;

use StoreEngine\Addons\FunnelBuilder\Classes\Funnel;
use StoreEngine\Addons\FunnelBuilder\Classes\FunnelStats;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AnalyticsController {

	protected string $namespace = 'storeengine/v1';

	public function register_routes() {
		register_rest_route( $this->namespace, '/funnels/(?P<id>\d+)/stats', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_stats' ],
			'permission_callback' => [ $this, 'permission' ],
		] );
	}

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_stats( WP_REST_Request $request ) {
		$funnel = Funnel::find( (int) $request['id'] );
		if ( ! $funnel ) {
			return new WP_Error( 'funnel_not_found', __( 'Funnel not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$args = [
			'from' => $request->get_param( 'from' ),
			'to'   => $request->get_param( 'to' ),
		];

		return new WP_REST_Response( FunnelStats::summary( $funnel->id, $args ), 200 );
	}
}
