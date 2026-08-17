<?php
/**
 * EmailLogger — universal capture for every email StoreEngine sends.
 *
 * Hooks the three send paths:
 *  - `storeengine/email/mail_send_arguments` filter in the Email trait,
 *    which fires before every wp_mail() call from a StoreEngine email class.
 *    Covers 11 of 13 email types (order, abandoned cart, subscription,
 *    license, installment, etc.).
 *  - `retrieve_password_notification_email` filter, which is how the
 *    PasswordReset class ships its message off to WP core. It never hits
 *    mail_send() so we record it separately.
 *  - WordPress core `wp_mail_succeeded` / `wp_mail_failed` actions to flip
 *    each pending row from 'queued' to its terminal state once wp_mail()
 *    itself reports back.
 *
 * Direct wp_mail() calls outside any StoreEngine email class (gateway-enabled
 * notifications, for example) are intentionally NOT captured here — hooking
 * wp_mail globally would log every WordPress email on the site, including
 * those owned by other plugins. Retrofit those call sites individually if
 * they need to appear in the audit trail.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\Email;

use StoreEngine\Classes\EmailLog;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailLogger {
	use Singleton;

	/**
	 * The EmailLog row created by the most recent chokepoint filter call.
	 * Consumed by wp_mail_succeeded / wp_mail_failed in the same request.
	 *
	 * wp_mail() is synchronous and there are no nested filter calls between
	 * our chokepoint filter and wp_mail() itself, so a single-slot pending
	 * tracker is sufficient (no need for a stack keyed by recipient + subject).
	 *
	 * @var EmailLog|null
	 */
	protected ?EmailLog $pending = null;

	protected function __construct() {
		// Priority 999 — run AFTER any other code that might tweak the args
		// (e.g. address overrides, attachment additions) so we capture what
		// actually goes to wp_mail.
		add_filter( 'storeengine/email/mail_send_arguments', [ $this, 'capture_send' ], 999, 3 );

		// WP core actions, fired by wp_mail() itself.
		add_action( 'wp_mail_succeeded', [ $this, 'mark_sent' ], 999, 1 );
		add_action( 'wp_mail_failed', [ $this, 'mark_failed' ], 999, 1 );

		// Password-reset bypass — the PasswordReset class returns args via this
		// filter for WP core to dispatch. Same priority as the chokepoint so
		// we run after StoreEngine's own modifications.
		add_filter( 'retrieve_password_notification_email', [ $this, 'capture_password_reset' ], 999, 4 );
	}

	/**
	 * Chokepoint filter handler.
	 *
	 * @param array $arguments mail arguments: to, subject, body, headers, attachments.
	 * @param string $email_name Email type slug (e.g. 'order_confirmation').
	 * @param array|mixed $context Extra args passed to mail_send() — typically carries order_id, abandoned_cart, subscription_id, etc.
	 *
	 * @return array Unmodified arguments.
	 */
	public function capture_send( array $arguments, string $email_name, $context = [] ): array {
		try {
			$context = is_array( $context ) ? $context : [];

			$log = new EmailLog();
			$log->set_sent_at_gmt( gmdate( 'Y-m-d H:i:s' ) );
			$log->set_email_type( $email_name );
			$log->set_recipient( $this->normalize_recipient( $arguments['to'] ?? '' ) );
			$log->set_subject( wp_specialchars_decode( $arguments['subject'] ?? '' ) );
			$log->set_status( 'queued' );

			[ $entity_type, $entity_id, $order_id, $customer_id ] = $this->resolve_context( $context );

			if ( $entity_type ) {
				$log->set_related_entity_type( $entity_type );
				$log->set_related_entity_id( $entity_id );
			}

			if ( $order_id ) {
				$log->set_order_id( $order_id );
			}

			if ( $customer_id ) {
				$log->set_customer_id( $customer_id );
			}

			$payload = [
				'context'         => $context,
				'has_attachments' => ! empty( $arguments['attachments'] ),
			];

			// One-shot enrichment for manual resends. Caller (the resend endpoint)
			// added a filter to tag the next captured row with "resent from id N"
			// metadata. Apply + immediately deregister so subsequent unrelated
			// sends don't inherit it.
			$extra = apply_filters( 'storeengine/email_log/next_capture_payload', [] );
			if ( is_array( $extra ) && $extra ) {
				$payload = array_merge( $payload, $extra );
				remove_all_filters( 'storeengine/email_log/next_capture_payload' );
			}

			$log->set_meta_payload( $payload );

			$saved = $log->save();
			if ( ! is_wp_error( $saved ) && $log->get_id() ) {
				$this->pending = $log;
			}
		} catch ( \Throwable $e ) {
			// Logging is best-effort; never let a logger failure block a real
			// customer email from going out.
			$this->pending = null;
			Helper::log_error( $e );
		}

		return $arguments;
	}

	/**
	 * Flip the pending row to 'sent' once wp_mail returns true.
	 */
	public function mark_sent( $mail_data ) {
		if ( ! $this->pending ) {
			return;
		}

		try {
			$this->pending->set_status( 'sent' );
			$this->pending->save();
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );
		}

		$this->pending = null;
	}

	/**
	 * Flip the pending row to 'failed' and record the WP_Error message.
	 */
	public function mark_failed( $wp_error ) {
		if ( ! $this->pending ) {
			return;
		}

		try {
			$this->pending->set_status( 'failed' );

			if ( $wp_error instanceof \WP_Error ) {
				$this->pending->set_error_message( $wp_error->get_error_message() );
			}

			$this->pending->save();
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );
		}

		$this->pending = null;
	}

	/**
	 * Password reset filter handler. WP core dispatches the email after this
	 * filter, then fires wp_mail_succeeded / wp_mail_failed — so we just stage
	 * the row and let the same terminal-state actions update it.
	 */
	public function capture_password_reset( $defaults, $key = '', $user_login = '', $user_data = null ): array {
		if ( ! is_array( $defaults ) ) {
			return $defaults;
		}

		$args = [];
		if ( $user_data && isset( $user_data->ID ) ) {
			$args['user_id'] = (int) $user_data->ID;
		}

		// Convert WP's $defaults shape into the chokepoint shape and reuse the
		// same recording path so we get the same data layout.
		$arguments = [
			'to'          => $defaults['to'] ?? '',
			'subject'     => $defaults['subject'] ?? '',
			'body'        => $defaults['message'] ?? '',
			'headers'     => $defaults['headers'] ?? '',
			'attachments' => [],
		];

		$this->capture_send( $arguments, 'password_reset', $args );

		return $defaults;
	}

	/**
	 * Map the chokepoint's $context array into entity links.
	 *
	 * @return array [ related_entity_type, related_entity_id, order_id, customer_id ]
	 */
	protected function resolve_context( array $context ): array {
		$entity_type = null;
		$entity_id   = 0;
		$order_id    = isset( $context['order_id'] ) ? (int) $context['order_id'] : 0;
		$customer_id = isset( $context['user_id'] ) ? (int) $context['user_id'] : 0;

		if ( ! empty( $context['abandoned_cart'] ) ) {
			$entity_type = 'abandoned_cart';
			$entity_id   = (int) $context['abandoned_cart'];
		} elseif ( ! empty( $context['subscription_id'] ) ) {
			$entity_type = 'subscription';
			$entity_id   = (int) $context['subscription_id'];
		} elseif ( ! empty( $context['license_id'] ) ) {
			$entity_type = 'license';
			$entity_id   = (int) $context['license_id'];
		} elseif ( ! empty( $context['installment_plan_id'] ) ) {
			$entity_type = 'installment_plan';
			$entity_id   = (int) $context['installment_plan_id'];
		}

		// If we have an order_id but no customer_id, try to derive one. Saves the
		// admin UI from making N follow-up lookups for "who got this email."
		if ( $order_id && ! $customer_id ) {
			$order = Helper::get_order( $order_id );
			if ( ! is_wp_error( $order ) && $order ) {
				$customer_id = (int) $order->get_customer_id();
			}
		}

		return [ $entity_type, $entity_id, $order_id, $customer_id ];
	}

	/**
	 * Reduce array / comma-separated / single-address recipients to one canonical
	 * string. The 'recipient' column is indexed for fast per-customer lookups;
	 * storing the original (comma-separated) form keeps the audit truthful, but
	 * we strip whitespace so the index doesn't drift.
	 */
	protected function normalize_recipient( $to ): string {
		if ( is_array( $to ) ) {
			$to = implode( ',', array_filter( array_map( 'trim', $to ) ) );
		}

		return is_string( $to ) ? trim( $to ) : '';
	}
}
