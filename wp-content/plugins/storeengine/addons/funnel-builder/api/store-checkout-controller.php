<?php
/**
 * Store Checkout REST controller.
 *
 * The Store Checkout is a single, dedicated funnel (trigger_type =
 * global_checkout) that overrides the whole site's checkout flow — the
 * CartFlows "Store Checkout" analogue. There is at most one; this controller
 * resolves it and creates it (seeded with a Checkout + Thank-You step) on first
 * setup.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder\Api;

use StoreEngine\Addons\FunnelBuilder\Classes\Funnel;
use StoreEngine\Addons\FunnelBuilder\Classes\FunnelStep;
use StoreEngine\Addons\FunnelBuilder\Database;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StoreCheckoutController {

	protected string $namespace = 'storeengine/v1';

	public function register_routes() {
		register_rest_route( $this->namespace, '/store-checkout', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_store_checkout' ],
				'permission_callback' => [ $this, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'ensure_store_checkout' ],
				'permission_callback' => [ $this, 'permission' ],
			],
		] );
	}

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_store_checkout(): WP_REST_Response {
		$funnel = $this->find();

		return new WP_REST_Response( $funnel ? $this->with_steps( $funnel ) : null, 200 );
	}

	/**
	 * Return the store-checkout funnel, creating + seeding it if it doesn't
	 * exist yet (idempotent — safe to POST repeatedly).
	 */
	public function ensure_store_checkout(): WP_REST_Response {
		$funnel = $this->find();
		if ( $funnel ) {
			return new WP_REST_Response( $this->with_steps( $funnel ), 200 );
		}

		$funnel               = new Funnel();
		$funnel->name         = __( 'Store Checkout', 'storeengine' );
		$funnel->trigger_type = 'global_checkout';
		$funnel->status       = 'draft';
		$funnel->save();

		$this->seed_step( $funnel->id, 'checkout', __( 'Checkout', 'storeengine' ), 0 );
		$this->seed_step( $funnel->id, 'thankyou', __( 'Thank You', 'storeengine' ), 1 );

		return new WP_REST_Response( $this->with_steps( $funnel ), 201 );
	}

	protected function find(): ?Funnel {
		// Prefer the funnel the storefront actually serves (published, newest —
		// the same resolver the checkout override uses), so the admin always edits
		// the live Store Checkout. Fall back to any match (e.g. a freshly set-up
		// draft that hasn't been published yet).
		$published = Funnel::get_global_checkout_funnel();
		if ( $published ) {
			return $published;
		}

		$all = Funnel::all( [ 'trigger_type' => 'global_checkout' ] );

		return $all[0] ?? null;
	}

	protected function seed_step( int $funnel_id, string $type, string $name, int $order ): void {
		$step             = new FunnelStep();
		$step->funnel_id  = $funnel_id;
		$step->type       = $type;
		$step->name       = $name;
		$step->step_order = $order;
		$step->page_id    = Database::create_step_page( $funnel_id, $name, $type );
		$step->save();

		if ( $step->page_id ) {
			update_post_meta( $step->page_id, Database::PAGE_META_STEP, $step->id );
			update_post_meta( $step->page_id, Database::PAGE_META_FUNNEL, $funnel_id );
		}
	}

	protected function with_steps( Funnel $funnel ): array {
		$data          = $funnel->to_array();
		$data['steps'] = array_map( static fn( $s ) => $s->to_array(), $funnel->steps() );

		return $data;
	}
}
