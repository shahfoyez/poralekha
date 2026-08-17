<?php

namespace StoreEngine\Addons\Webhooks\Incoming;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs an authenticated, deduped payload: always fires the raw internal hooks
 * (automation seam) and, when the config maps to a structured action, runs that
 * handler. Shared by the synchronous receiver path and the Action Scheduler
 * deferred path.
 */
class Processor {

	/**
	 * @return array{success:bool,message:string,status?:int,data?:array}
	 */
	public static function process( int $webhook_id, string $delivery_id, string $action, array $payload ): array {
		$context = [
			'webhook_id'  => $webhook_id,
			'delivery_id' => $delivery_id,
			'action'      => $action,
		];

		/**
		 * Fires for every received incoming webhook, before structured handling.
		 * This is the seam no-code automations (Zapier/Make/n8n catch hooks) and
		 * custom code hook into.
		 *
		 * @param array $payload Decoded JSON body.
		 * @param array $context { webhook_id, delivery_id, action }.
		 */
		do_action( 'storeengine/incoming_webhook/received', $payload, $context );
		do_action( "storeengine/incoming_webhook/received/{$action}", $payload, $context );

		if ( '' === $action || Registry::AUTOMATION === $action ) {
			return [
				'success' => true,
				'message' => __( 'Payload received; automation hook fired.', 'storeengine' ),
			];
		}

		$handler = Registry::get_handler( $action );

		if ( ! $handler ) {
			return [
				'success' => false,
				'status'  => 422,
				/* translators: %s: action slug */
				'message' => sprintf( __( 'No handler registered for action "%s".', 'storeengine' ), $action ),
			];
		}

		try {
			$result = $handler->handle( $payload, $context );
		} catch ( \Throwable $e ) {
			return [
				'success' => false,
				'status'  => 500,
				'message' => $e->getMessage(),
			];
		}

		// Normalise loose handler returns into the documented shape.
		if ( ! is_array( $result ) ) {
			$result = [ 'success' => (bool) $result, 'message' => '' ];
		}
		$result['success'] = ! empty( $result['success'] );
		$result['message'] = (string) ( $result['message'] ?? '' );

		return $result;
	}

	/**
	 * Action Scheduler callback for the deferred path. Args arrive as a single
	 * associative array.
	 *
	 * @param array $data { webhook_id:int, delivery_id:string, action:string, payload:array }
	 */
	public static function process_scheduled( array $data ): void {
		$webhook_id  = (int) ( $data['webhook_id'] ?? 0 );
		$delivery_id = (string) ( $data['delivery_id'] ?? '' );
		$action      = (string) ( $data['action'] ?? Registry::AUTOMATION );
		$payload     = (array) ( $data['payload'] ?? [] );

		if ( ! $webhook_id ) {
			return;
		}

		$result = self::process( $webhook_id, $delivery_id, $action, $payload );
		DeliveryLog::update_result( $webhook_id, $delivery_id, $result );
	}
}
