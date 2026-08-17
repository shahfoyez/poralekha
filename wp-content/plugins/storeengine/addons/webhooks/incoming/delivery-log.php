<?php

namespace StoreEngine\Addons\Webhooks\Incoming;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Received-delivery audit trail for an incoming webhook.
 *
 * Stored as a single capped, most-recent-first array in post meta (no extra
 * table). Doubles as the idempotency ledger: a delivery id already present is a
 * replay and is not reprocessed. Because the list is capped, dedup only covers
 * the most recent {@see self::CAP} deliveries — acceptable for replay protection
 * against near-duplicate retries, which is the realistic threat.
 */
class DeliveryLog {

	const META = '_storeengine_incoming_deliveries';
	const CAP  = 50;

	/**
	 * Append a delivery record (most-recent-first) and trim to the cap.
	 *
	 * @param int   $webhook_id
	 * @param array $entry
	 */
	public static function record( int $webhook_id, array $entry ): void {
		if ( isset( $entry['payload'] ) ) {
			$entry['payload'] = self::cap_payload( $entry['payload'], $webhook_id );
		}

		$log = self::all( $webhook_id );

		array_unshift( $log, $entry );

		$cap = (int) apply_filters( 'storeengine/incoming_webhook/log_cap', self::CAP, $webhook_id );
		if ( count( $log ) > $cap ) {
			$log = array_slice( $log, 0, $cap );
		}

		update_post_meta( $webhook_id, self::META, $log );
	}

	/**
	 * Bound the audit copy of a payload.
	 *
	 * The whole log lives in ONE post-meta row and every inbound request does a
	 * read-modify-write of it, so storing full ~1 MB bodies (× {@see self::CAP})
	 * turned each request into a multi-MB serialize/unserialize. The idempotency
	 * ledger keys off delivery_id (a hash of the signed body), never the stored
	 * payload, so keeping only a short excerpt here is safe. Filter to 0 to keep
	 * the full payload.
	 *
	 * @param mixed $payload
	 * @return mixed
	 */
	protected static function cap_payload( $payload, int $webhook_id ) {
		$max = (int) apply_filters( 'storeengine/incoming_webhook/log_payload_bytes', 2048, $webhook_id );
		if ( $max <= 0 ) {
			return $payload;
		}

		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || strlen( $encoded ) <= $max ) {
			return $payload;
		}

		return [
			'_truncated' => true,
			'_bytes'     => strlen( $encoded ),
			'excerpt'    => substr( $encoded, 0, $max ),
		];
	}

	/**
	 * @param int $webhook_id
	 *
	 * @return array<int,array>
	 */
	public static function all( int $webhook_id ): array {
		$log = get_post_meta( $webhook_id, self::META, true );

		return is_array( $log ) ? $log : [];
	}

	/**
	 * @return array|null
	 */
	public static function find( int $webhook_id, string $delivery_id ) {
		foreach ( self::all( $webhook_id ) as $entry ) {
			if ( isset( $entry['delivery_id'] ) && hash_equals( (string) $entry['delivery_id'], $delivery_id ) ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Has this delivery id already been accepted (i.e. is this a replay)?
	 */
	public static function is_seen( int $webhook_id, string $delivery_id ): bool {
		return null !== self::find( $webhook_id, $delivery_id );
	}

	/**
	 * Merge a processing result into an already-recorded delivery (used by the
	 * deferred path where accept and process happen in two steps).
	 */
	public static function update_result( int $webhook_id, string $delivery_id, array $result ): void {
		$log = self::all( $webhook_id );

		foreach ( $log as $i => $entry ) {
			if ( isset( $entry['delivery_id'] ) && hash_equals( (string) $entry['delivery_id'], $delivery_id ) ) {
				$log[ $i ]['status']  = ! empty( $result['success'] ) ? 'processed' : 'failed';
				$log[ $i ]['message'] = (string) ( $result['message'] ?? '' );
				update_post_meta( $webhook_id, self::META, $log );

				return;
			}
		}
	}
}
