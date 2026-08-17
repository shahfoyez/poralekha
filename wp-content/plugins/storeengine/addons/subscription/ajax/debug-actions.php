<?php

namespace StoreEngine\Addons\Subscription\Ajax;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug-only AJAX actions to fire scheduled subscription events on demand.
 *
 * Both endpoints are gated by WP_DEBUG so they cannot be invoked in production
 * even by an authenticated administrator.
 */
class DebugActions extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'debug_process_renewal' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'process_renewal' ],
				'fields'     => [
					'subscription_id' => 'int',
				],
			],
			'debug_end_trial'       => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'end_trial' ],
				'fields'     => [
					'subscription_id' => 'int',
				],
			],
		];
	}

	public function process_renewal( array $payload ): void {
		$subscription = $this->resolve_subscription( $payload );

		try {
			do_action( 'storeengine/subscription/scheduled_payment', $subscription->get_id() );
			wp_send_json_success( __( 'Renewal processed', 'storeengine' ) );
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function end_trial( array $payload ): void {
		$subscription = $this->resolve_subscription( $payload );

		try {
			do_action( 'storeengine/subscription/schedule_trial_end', $subscription->get_id() );
			wp_send_json_success( __( 'Trial ended', 'storeengine' ) );
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );
			wp_send_json_error( $e->getMessage() );
		}
	}

	private function resolve_subscription( array $payload ): Subscription {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			wp_send_json_error( __( 'Debug actions are disabled.', 'storeengine' ), 403 );
		}

		$id = $payload['subscription_id'] ?? 0;

		if ( ! $id ) {
			wp_send_json_error( __( 'Subscription id is required', 'storeengine' ) );
		}

		$subscription = Subscription::get_subscription( (int) $id );

		if ( ! $subscription ) {
			wp_send_json_error( __( 'Subscription not found.', 'storeengine' ) );
		}

		return $subscription;
	}
}
