<?php
/**
 * Funnel steps REST controller.
 *
 * Steps are nested under a funnel. Creating a step optionally spins up the
 * backing aBlocks page; reorder accepts an ordered id list.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder\Api;

use StoreEngine\Addons\FunnelBuilder\Classes\Funnel;
use StoreEngine\Addons\FunnelBuilder\Classes\FunnelStep;
use StoreEngine\Addons\FunnelBuilder\Database;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StepsController {

	protected string $namespace = 'storeengine/v1';

	public function register_routes() {
		register_rest_route( $this->namespace, '/funnels/(?P<funnel_id>\d+)/steps', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/funnels/(?P<funnel_id>\d+)/steps/reorder', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'reorder' ],
			'permission_callback' => [ $this, 'permission' ],
			'args'                => [
				'order' => [ 'type' => 'array', 'required' => true ],
			],
		] );

		register_rest_route( $this->namespace, '/funnel-steps/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permission' ],
			],
		] );

		// Recover a step whose backing page was trashed or deleted.
		register_rest_route( $this->namespace, '/funnel-steps/(?P<id>\d+)/page', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'page_action' ],
			'permission_callback' => [ $this, 'permission' ],
			'args'                => [
				'action' => [ 'type' => 'string', 'required' => true, 'enum' => [ 'restore', 'create' ] ],
			],
		] );
	}

	/**
	 * Restore a trashed step page, or create a fresh one for a detached/deleted step.
	 */
	public function page_action( WP_REST_Request $request ) {
		$step = FunnelStep::find( (int) $request['id'] );
		if ( ! $step ) {
			return new WP_Error( 'step_not_found', __( 'Step not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$action = sanitize_text_field( (string) $request->get_param( 'action' ) );

		if ( 'restore' === $action ) {
			if ( $step->page_id && 'trash' === get_post_status( $step->page_id ) ) {
				wp_untrash_post( $step->page_id );
				// Untrashing returns a post to 'draft'; publish so the step is live.
				wp_update_post( [ 'ID' => $step->page_id, 'post_status' => 'publish' ] );
			}
		} elseif ( 'create' === $action ) {
			$page_id = Database::create_step_page( $step->funnel_id, $step->name, $step->type );
			if ( $page_id ) {
				$step->page_id = $page_id;
				$step->save();
				update_post_meta( $page_id, Database::PAGE_META_STEP, $step->id );
				update_post_meta( $page_id, Database::PAGE_META_FUNNEL, $step->funnel_id );
			}
		} else {
			return new WP_Error( 'invalid_action', __( 'Invalid page action.', 'storeengine' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( $step->to_array(), 200 );
	}

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$steps = array_map(
			static fn( FunnelStep $s ) => $s->to_array(),
			FunnelStep::for_funnel( (int) $request['funnel_id'] )
		);

		return new WP_REST_Response( $steps, 200 );
	}

	public function create_item( WP_REST_Request $request ) {
		$funnel_id = (int) $request['funnel_id'];
		$funnel    = Funnel::find( $funnel_id );
		if ( ! $funnel ) {
			return new WP_Error( 'funnel_not_found', __( 'Funnel not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$type = sanitize_text_field( (string) $request->get_param( 'type' ) );
		if ( ! in_array( $type, FunnelStep::TYPES, true ) ) {
			$type = 'landing';
		}
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) ) ?: ucfirst( $type );

		$step             = new FunnelStep();
		$step->funnel_id  = $funnel_id;
		$step->type       = $type;
		$step->name       = $name;
		$step->step_order = FunnelStep::next_order( $funnel_id );
		$step->settings   = (array) ( $request->get_param( 'settings' ) ?? [] );

		// Spin up the backing aBlocks page unless the client opted out or passed
		// an existing one.
		$page_id = (int) $request->get_param( 'page_id' );
		if ( $page_id ) {
			$step->page_id = $page_id;
		} elseif ( false !== $request->get_param( 'create_page' ) ) {
			// The blueprint id (if any) only tailors the seeded hero copy.
			$blueprint = sanitize_key( (string) $request->get_param( 'blueprint' ) );
			$created   = Database::create_step_page( $funnel_id, $name, $type, $blueprint );
			if ( $created ) {
				$step->page_id = $created;
			}
		}

		$step->save();

		if ( $step->page_id ) {
			update_post_meta( $step->page_id, Database::PAGE_META_STEP, $step->id );
			update_post_meta( $step->page_id, Database::PAGE_META_FUNNEL, $funnel_id );
		}

		return new WP_REST_Response( $step->to_array(), 201 );
	}

	public function update_item( WP_REST_Request $request ) {
		$step = FunnelStep::find( (int) $request['id'] );
		if ( ! $step ) {
			return new WP_Error( 'step_not_found', __( 'Step not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( null !== $request->get_param( 'name' ) ) {
			$step->name = sanitize_text_field( $request->get_param( 'name' ) );
		}
		if ( null !== $request->get_param( 'type' ) ) {
			$type = sanitize_text_field( $request->get_param( 'type' ) );
			if ( in_array( $type, FunnelStep::TYPES, true ) ) {
				$step->type = $type;
			}
		}
		if ( null !== $request->get_param( 'page_id' ) ) {
			$step->page_id = (int) $request->get_param( 'page_id' );
		}
		if ( null !== $request->get_param( 'ab_variant_group' ) ) {
			$step->ab_variant_group = sanitize_text_field( $request->get_param( 'ab_variant_group' ) );
		}
		if ( null !== $request->get_param( 'settings' ) ) {
			$step->settings = (array) $request->get_param( 'settings' );
		}

		$step->save();

		return new WP_REST_Response( $step->to_array(), 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$step = FunnelStep::find( (int) $request['id'] );
		if ( ! $step ) {
			return new WP_Error( 'step_not_found', __( 'Step not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		$step->delete();

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	public function reorder( WP_REST_Request $request ): WP_REST_Response {
		$order = (array) $request->get_param( 'order' );
		$pos   = 0;
		foreach ( $order as $step_id ) {
			$step = FunnelStep::find( (int) $step_id );
			if ( $step && $step->funnel_id === (int) $request['funnel_id'] ) {
				$step->step_order = $pos++;
				$step->save();
			}
		}

		return new WP_REST_Response( [ 'reordered' => true ], 200 );
	}
}
