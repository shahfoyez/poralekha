<?php
/**
 * Trial Ending Soon Email.
 *
 * 3-day heads-up before a trial converts to a paid subscription. Reduces
 * refund requests caused by the "I didn't realise I'd be charged" surprise.
 *
 * Scheduling is per-subscription, not a daily scan: when the subscription
 * is created or moves to `active`, we queue a single Action Scheduler job
 * at `trial_end_date - 3d`. On any later status change to a non-active
 * state we unschedule it.
 */

namespace StoreEngine\Addons\Email\subscription;

use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\StoreengineDatetime;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrialEndingSoon {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'trial_ending_soon';
	const AS_HOOK = 'storeengine/subscription/trial_ending_soon';
	const AS_GROUP = 'storeengine-subscription';

	/** Days before trial_end_date to fire the email. Filterable. */
	const LEAD_DAYS = 3;

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		// `checkout_subscription_created` is the canonical "new subscription"
		// action — fires from CreateSubscription::create_subscription after
		// the row is saved with trial_end_date populated.
		add_action( 'storeengine/subscription/checkout_subscription_created', [ __CLASS__, 'on_subscription_created' ], 20 );
		add_action( 'storeengine/subscription/status_updated', [ __CLASS__, 'on_status_updated' ], 20, 3 );
		add_action( self::AS_HOOK, [ $this, 'send' ], 10, 1 );
	}

	public static function register_defaults( array $defaults ): array {
		if ( ! isset( $defaults[ self::SETTINGS_KEY ] ) ) {
			$defaults[ self::SETTINGS_KEY ] = self::default_template();
		}
		return $defaults;
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => true,
				'email_subject' => __( 'Your free trial for subscription #{order_id} ends in 3 days', 'storeengine' ),
				'email_heading' => __( 'Your trial is ending soon', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>Just a friendly heads-up: your free trial ends in 3 days. After that, your subscription will begin and your payment method on file will be charged.</p><p>If you\'d like to continue, you don\'t need to do anything. If you\'d rather cancel before the charge, you can do so from your account at any time.</p><p>Order Items:</p><p>{order_items}</p><p>Thanks for trying {site_title}.</p>',
					'storeengine'
				),
			],
		];
	}

	// ── Scheduling ───────────────────────────────────────────────────────────

	/**
	 * Wrapper so add_action's default 1-arg accept_args still binds the
	 * subscription cleanly. `checkout_subscription_created` fires with
	 * ($subscription, $order, $recurring_cart) — we only need the first.
	 */
	public static function on_subscription_created( $subscription ): void {
		self::schedule_for_subscription( $subscription );
	}

	public static function schedule_for_subscription( $subscription ): void {
		if ( ! is_a( $subscription, Subscription::class ) ) {
			return;
		}

		$trial_end = $subscription->get_trial_end_date();
		if ( ! $trial_end instanceof StoreengineDatetime ) {
			return;
		}

		$lead    = (int) apply_filters( 'storeengine/email/trial_ending_lead_days', self::LEAD_DAYS );
		$fire_at = $trial_end->getTimestamp() - ( $lead * DAY_IN_SECONDS );

		// Already-past trials: skip silently.
		if ( $fire_at <= time() ) {
			return;
		}

		self::unschedule_for_subscription( $subscription );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				$fire_at,
				self::AS_HOOK,
				[ 'subscription_id' => $subscription->get_id() ],
				self::AS_GROUP,
				true
			);
		}
	}

	public static function unschedule_for_subscription( $subscription ): void {
		if ( ! is_a( $subscription, Subscription::class ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( self::AS_HOOK, [ 'subscription_id' => $subscription->get_id() ], self::AS_GROUP );
	}

	public static function on_status_updated( $subscription, $new_status, $old_status ): void {
		if ( ! is_a( $subscription, Subscription::class ) ) {
			return;
		}

		// Active (re)schedules — covers cases where a trial was created in a
		// non-active state and only later moves to active.
		if ( 'active' === $new_status ) {
			self::schedule_for_subscription( $subscription );
			return;
		}

		// Anything terminal kills the queued notice so customers don't get
		// a "your trial ends soon" email after they've already cancelled.
		if ( in_array( $new_status, [ 'cancelled', 'expired', 'on_hold', 'pending_cancel', 'switched', 'trash' ], true ) ) {
			self::unschedule_for_subscription( $subscription );
		}
	}

	// ── Send ─────────────────────────────────────────────────────────────────

	public function send( $subscription_id ): void {
		$subscription_id = absint( $subscription_id );
		if ( ! $subscription_id ) {
			return;
		}

		$subscription = Helper::get_order( $subscription_id );
		if ( ! is_a( $subscription, Subscription::class ) ) {
			return;
		}

		// Don't email if the subscription is no longer active (the customer
		// already cancelled between scheduling and now).
		if ( ! $subscription->has_status( 'active' ) ) {
			return;
		}

		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$to = $subscription->get_billing_email();
		if ( ! $to ) {
			return;
		}

		$subject = $this->get_email_subject( $subscription, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-status-customer.php' );
		$body = $this->get_order_email_body( $subscription, $body );

		$this->mail_send( $to, $subject, $body, $headers, [ 'subscription_id' => $subscription_id ] );
	}
}
