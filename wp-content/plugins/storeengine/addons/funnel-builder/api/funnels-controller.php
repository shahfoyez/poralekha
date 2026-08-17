<?php
/**
 * Funnels REST controller — CRUD over the storeengine_funnels table.
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

class FunnelsController {

	protected string $namespace = 'storeengine/v1';
	protected string $rest_base = 'funnels';

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => $this->item_args(),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => $this->item_args(),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permission' ],
			],
		] );
	}

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}

	protected function item_args(): array {
		return [
			'name'         => [ 'type' => 'string' ],
			'status'       => [ 'type' => 'string', 'enum' => [ 'draft', 'publish' ] ],
			'trigger_type' => [ 'type' => 'string', 'enum' => [ 'global_checkout', 'product', 'manual' ] ],
			'trigger_data' => [ 'type' => [ 'object', 'array' ] ],
			'settings'     => [ 'type' => 'object' ],
		];
	}

	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$args    = [];
		$status  = $request->get_param( 'status' );
		if ( $status ) {
			$args['status'] = sanitize_text_field( $status );
		}
		$funnels = array_map(
			static function ( Funnel $f ) {
				$data               = $f->to_array();
				$steps              = $f->steps();
				$data['step_count'] = count( $steps );
				// First live step page — the funnel's front-end entry point ("View").
				$data['view_url'] = '';
				foreach ( $steps as $s ) {
					if ( $s->is_page_live() ) {
						$data['view_url'] = $s->get_url();
						break;
					}
				}
				$summary            = FunnelStats::summary( $f->id );
				$data['revenue']    = $summary['totals']['revenue'] ?? 0;
				$data['orders']     = $summary['totals']['conversions'] ?? 0;
				$data['views']      = $summary['totals']['views'] ?? 0;

				return $data;
			},
			Funnel::all( $args )
		);

		return new WP_REST_Response( $funnels, 200 );
	}

	public function get_item( WP_REST_Request $request ) {
		$funnel = Funnel::find( (int) $request['id'] );
		if ( ! $funnel ) {
			return new WP_Error( 'funnel_not_found', __( 'Funnel not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		$data           = $funnel->to_array();
		$data['steps']  = array_map( static fn( $s ) => $s->to_array(), $funnel->steps() );

		return new WP_REST_Response( $data, 200 );
	}

	public function create_item( WP_REST_Request $request ): WP_REST_Response {
		$funnel = $this->fill( new Funnel(), $request );
		$funnel->save();

		return new WP_REST_Response( $funnel->to_array(), 201 );
	}

	public function update_item( WP_REST_Request $request ) {
		$funnel = Funnel::find( (int) $request['id'] );
		if ( ! $funnel ) {
			return new WP_Error( 'funnel_not_found', __( 'Funnel not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		$this->fill( $funnel, $request );
		$funnel->save();

		return new WP_REST_Response( $funnel->to_array(), 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$funnel = Funnel::find( (int) $request['id'] );
		if ( ! $funnel ) {
			return new WP_Error( 'funnel_not_found', __( 'Funnel not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		$funnel->delete();

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	protected function fill( Funnel $funnel, WP_REST_Request $request ): Funnel {
		if ( null !== $request->get_param( 'name' ) ) {
			$funnel->name = sanitize_text_field( $request->get_param( 'name' ) );
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$funnel->status = sanitize_text_field( $request->get_param( 'status' ) );
		}
		if ( null !== $request->get_param( 'trigger_type' ) ) {
			$funnel->trigger_type = sanitize_text_field( $request->get_param( 'trigger_type' ) );
		}
		if ( null !== $request->get_param( 'trigger_data' ) ) {
			$funnel->trigger_data = (array) $request->get_param( 'trigger_data' );
		}
		if ( null !== $request->get_param( 'settings' ) ) {
			$funnel->settings = (array) $request->get_param( 'settings' );
		}

		return $funnel;
	}
}
