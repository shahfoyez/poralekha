<?php
/**
 * Manual-resend registry for failed (or just historical) email log entries.
 *
 * Each email_type maps to a handler that knows how to reload the source entity
 * from the EmailLog row's metadata and re-dispatch the email. The new send
 * passes through mail_send() → the universal EmailLogger → a fresh log row,
 * so the original failed row stays in the audit trail and the resend is
 * recorded as a separate entry.
 *
 * Addons register their own handlers via the `storeengine/email_log/resend_handlers`
 * filter so storeengine-pro types (license, installment, subscription) don't need
 * to live in core.
 *
 * Intentionally NOT registered:
 *  - password_reset — customers should re-request via the form; resending an
 *    old reset link could leak a token that's since been issued elsewhere.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\Email;

use StoreEngine\Addons\Email\order\AbandonedCartFirst;
use StoreEngine\Addons\Email\order\AbandonedCartSecond;
use StoreEngine\Addons\Email\order\AbandonedCartThird;
use StoreEngine\Addons\Email\order\Confirm;
use StoreEngine\Classes\EmailLog as EmailLogEntity;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ResendRegistry {
	use Singleton;

	/** @var array<string, callable> */
	protected array $handlers = [];

	protected function __construct() {
		$this->register_core_handlers();

		// Late init so storeengine-pro addons can append.
		add_action( 'storeengine/init', function () {
			$this->handlers = apply_filters( 'storeengine/email_log/resend_handlers', $this->handlers );
		}, 99 );
	}

	protected function register_core_handlers() {
		$this->handlers['order_confirmation'] = function ( EmailLogEntity $row ) {
			$order = Helper::get_order( $row->get_order_id() );
			if ( is_wp_error( $order ) || ! $order ) {
				return new WP_Error( 'storeengine_email_log_resend_entity_missing', __( 'Order no longer exists.', 'storeengine' ), [ 'status' => 410 ] );
			}

			// Confirm::send_order_email() short-circuits if the order has already
			// been marked as "new order email sent". We're explicitly resending,
			// so clear the flag, dispatch, then leave it set again. Without this
			// the call would silently NOOP for every order that's older than the
			// first confirmation email.
			$order->set_new_order_email_sent( false );
			$order->save();

			( new Confirm() )->send_order_email( $order );
		};

		// Other order email types (status change, refund, note, payment failed)
		// are NOT currently registered. Each of their concrete classes hangs off
		// a specific event (status_changed, refund_created, etc.) and re-firing
		// them out-of-context would either silently NOOP or send misleading
		// content. Add them when there's a clean public re-dispatch method.

		// Abandoned cart recovery — the AbstractAbandonedCartMail::send_email()
		// gate checks for `has_status('abandoned')`, so a recovered cart can't
		// be resent. Surface that as an explicit error instead of silently NOOP.
		$abandoned_factory = function ( string $type, string $class ) {
			return function ( EmailLogEntity $row ) use ( $type, $class ) {
				if ( 'abandoned_cart' !== $row->get_related_entity_type() || ! $row->get_related_entity_id() ) {
					return new WP_Error( 'storeengine_email_log_resend_entity_missing', __( 'Abandoned cart link missing on the log row.', 'storeengine' ), [ 'status' => 410 ] );
				}

				if ( ! class_exists( '\StoreEnginePro\Addons\AbandonedCart\Classes\AbandonedCart' ) ) {
					return new WP_Error( 'storeengine_email_log_resend_unavailable', __( 'Abandoned cart addon not available.', 'storeengine' ), [ 'status' => 503 ] );
				}

				// The AbandonedCart entity throws when reading a missing row instead
				// of zeroing the id, so catch that and translate to a clean 410.
				try {
					$abc = new \StoreEnginePro\Addons\AbandonedCart\Classes\AbandonedCart( $row->get_related_entity_id() );
				} catch ( \Throwable $e ) {
					return new WP_Error( 'storeengine_email_log_resend_entity_missing', __( 'Abandoned cart no longer exists.', 'storeengine' ), [ 'status' => 410 ] );
				}

				if ( ! $abc->get_id() ) {
					return new WP_Error( 'storeengine_email_log_resend_entity_missing', __( 'Abandoned cart no longer exists.', 'storeengine' ), [ 'status' => 410 ] );
				}

				if ( ! $abc->has_status( 'abandoned' ) ) {
					return new WP_Error( 'storeengine_email_log_resend_invalid_state', __( 'This cart is no longer in the abandoned state and cannot be resent.', 'storeengine' ), [ 'status' => 409 ] );
				}

				( new $class() )->send_email( $abc, $type );
			};
		};

		$this->handlers['abandoned_cart_first']  = $abandoned_factory( 'first_email', AbandonedCartFirst::class );
		$this->handlers['abandoned_cart_second'] = $abandoned_factory( 'second_email', AbandonedCartSecond::class );
		$this->handlers['abandoned_cart_third']  = $abandoned_factory( 'third_email', AbandonedCartThird::class );
	}

	public function get_handler( string $email_type ): ?callable {
		return $this->handlers[ $email_type ] ?? null;
	}

	public function get_resendable_types(): array {
		return array_keys( $this->handlers );
	}

	public function is_resendable( string $email_type ): bool {
		return isset( $this->handlers[ $email_type ] );
	}
}
