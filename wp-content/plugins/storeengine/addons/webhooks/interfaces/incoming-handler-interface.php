<?php

namespace StoreEngine\Addons\Webhooks\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for an incoming-webhook action handler.
 *
 * A handler receives the decoded, already-authenticated JSON payload and turns
 * it into a StoreEngine side effect (update an order, adjust stock, upsert a
 * customer, …). Pro addons register their own via the
 * `storeengine/incoming_webhook/handlers` filter.
 */
interface IncomingHandlerInterface {

	/**
	 * Handle a received payload.
	 *
	 * Implementations should NOT throw for expected validation problems — return
	 * a failure result instead. Uncaught throwables are trapped by the processor
	 * and turned into a 500 result.
	 *
	 * @param array $payload Decoded JSON body sent by the caller.
	 * @param array $context { webhook_id:int, delivery_id:string, action:string }.
	 *
	 * @return array{success:bool,message:string,status?:int,data?:array}
	 */
	public function handle( array $payload, array $context ): array;
}
