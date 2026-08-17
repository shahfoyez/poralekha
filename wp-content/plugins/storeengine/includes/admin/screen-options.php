<?php
/**
 * Per-user "Screen Options" storage + AJAX handler.
 *
 * All of a user's screen preferences (per-page fetch limit, table column
 * visibility/order, …) for every admin screen live in ONE user-meta blob:
 *
 *   storeengine_screen_options => [ 'v' => 1, 'screens' => [ '<screenId>' => [...] ] ]
 *
 * The blob is seeded into `StoreEngineGlobal.screen_options` at page load (see
 * assets.php) so the React app has it on first paint with no extra request;
 * changes are saved back with a small debounced AJAX call.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Admin;

use StoreEngine\Classes\AbstractAjaxHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScreenOptions extends AbstractAjaxHandler {

	const META_KEY = 'storeengine_screen_options';

	public function __construct() {
		// `read` — every logged-in user manages only their OWN prefs; the
		// storeengine_nonce (only localized on StoreEngine admin pages) gates it.
		$this->actions = [
			'save_screen_options'  => [
				'capability' => 'read',
				'callback'   => [ $this, 'save' ],
				'fields'     => [
					'screen'  => 'string',
					'options' => 'string', // JSON blob for the one screen.
				],
			],
			'reset_screen_options' => [
				'capability' => 'read',
				'callback'   => [ $this, 'reset' ],
				'fields'     => [ 'screen' => 'string' ],
			],
		];
	}

	/**
	 * The current (or given) user's full screen-options blob, always normalised
	 * to `[ 'v' => 1, 'screens' => [...] ]`.
	 */
	public static function get_all( ?int $user_id = null ): array {
		$user_id = $user_id ?: get_current_user_id();
		$data    = $user_id ? get_user_meta( $user_id, self::META_KEY, true ) : [];
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$screens = isset( $data['screens'] ) && is_array( $data['screens'] ) ? $data['screens'] : [];

		return [ 'v' => 1, 'screens' => $screens ];
	}

	public function save( array $payload ) {
		$screen  = sanitize_key( $payload['screen'] ?? '' );
		$raw     = is_string( $payload['options'] ?? null ) ? wp_unslash( $payload['options'] ) : '';
		$options = json_decode( $raw, true );

		if ( '' === $screen || ! is_array( $options ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid screen options payload.', 'storeengine' ) ] );
		}

		$all                        = self::get_all();
		$all['screens'][ $screen ]  = self::sanitize_screen( $options );
		update_user_meta( get_current_user_id(), self::META_KEY, $all );

		wp_send_json_success( [ 'screen_options' => $all ] );
	}

	public function reset( array $payload ) {
		$screen = sanitize_key( $payload['screen'] ?? '' );
		$all    = self::get_all();

		if ( '' !== $screen && isset( $all['screens'][ $screen ] ) ) {
			unset( $all['screens'][ $screen ] );
			update_user_meta( get_current_user_id(), self::META_KEY, $all );
		}

		wp_send_json_success( [ 'screen_options' => $all ] );
	}

	/**
	 * Whitelist + clamp a single screen's options.
	 */
	protected static function sanitize_screen( array $options ): array {
		$out = [];

		if ( isset( $options['per_page'] ) ) {
			$out['per_page'] = max( 1, min( 200, (int) $options['per_page'] ) );
		}

		if ( isset( $options['columns'] ) && is_array( $options['columns'] ) ) {
			$columns = [];
			foreach ( $options['columns'] as $column ) {
				if ( ! is_array( $column ) || empty( $column['id'] ) ) {
					continue;
				}
				$columns[] = [
					'id'      => sanitize_text_field( (string) $column['id'] ),
					'visible' => ! empty( $column['visible'] ),
					'order'   => isset( $column['order'] ) ? (int) $column['order'] : 0,
				];
			}
			$out['columns'] = $columns;
		}

		return $out;
	}
}

// End of file screen-options.php.
