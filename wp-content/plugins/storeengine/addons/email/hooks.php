<?php

namespace StoreEngine\Addons\Email;

use StoreEngine\Addons\Email\Admin\Settings;
use StoreEngine\Addons\Email\account\PasswordReset;
use StoreEngine\Addons\Email\account\RegistrationWelcome;
use StoreEngine\Addons\Email\order\Confirm;
use StoreEngine\Addons\Email\order\NewUserNotification;
use StoreEngine\Addons\Email\order\PaymentFailed;
use StoreEngine\Addons\Email\order\ItemShipped;
use StoreEngine\Addons\Email\order\Delivered;
use StoreEngine\Addons\Email\order\Cancelled as OrderCancelled;
use StoreEngine\Addons\Email\order\Refund;
use StoreEngine\Addons\Email\order\Status;
use StoreEngine\Addons\Email\order\Note;
use StoreEngine\Addons\Email\subscription\Cancelled;
use StoreEngine\Addons\Email\subscription\Renewed;
use StoreEngine\Addons\Email\subscription\RenewalFailed;
use StoreEngine\Addons\Email\subscription\TrialEndingSoon;
use StoreEngine\Addons\Email\affiliate\Registered as AffiliateRegistered;
use StoreEngine\Addons\Email\affiliate\Approved as AffiliateApproved;
use StoreEngine\Addons\Email\affiliate\Rejected as AffiliateRejected;
use StoreEngine\Addons\Email\affiliate\Suspended as AffiliateSuspended;
use StoreEngine\Addons\Email\affiliate\CommissionApproved as AffiliateCommissionApproved;
use StoreEngine\Addons\Email\affiliate\PayoutCompleted as AffiliatePayoutCompleted;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	public function __construct() {
		// Default-data filters need to be in place *before* Settings runs the
		// merge. They use class-method callbacks (PHP doesn't evaluate the
		// __() inside default_template() until the filter actually fires), so
		// registering them at construct time is safe — translation strings
		// only resolve when save_settings() invokes the filter chain.
		add_filter( 'storeengine/email/settings_default_data', [ PaymentFailed::class, 'register_defaults' ] );
		add_filter( 'storeengine/email/settings_default_data', [ PasswordReset::class, 'register_defaults' ] );
		add_filter( 'storeengine/email/settings_default_data', [ RegistrationWelcome::class, 'register_defaults' ] );

		if ( class_exists( '\StoreEngine\Addons\Subscription\Classes\Subscription' ) ) {
			add_filter( 'storeengine/email/settings_default_data', [ Cancelled::class, 'register_defaults' ] );
			add_filter( 'storeengine/email/settings_default_data', [ Renewed::class, 'register_defaults' ] );
			add_filter( 'storeengine/email/settings_default_data', [ RenewalFailed::class, 'register_defaults' ] );
			add_filter( 'storeengine/email/settings_default_data', [ TrialEndingSoon::class, 'register_defaults' ] );
		}

		if ( class_exists( '\StoreEngine\Addons\Affiliate\Affiliate' ) ) {
			add_filter( 'storeengine/email/settings_default_data', [ AffiliateRegistered::class, 'register_defaults' ] );
			add_filter( 'storeengine/email/settings_default_data', [ AffiliateApproved::class, 'register_defaults' ] );
			add_filter( 'storeengine/email/settings_default_data', [ AffiliateRejected::class, 'register_defaults' ] );
			add_filter( 'storeengine/email/settings_default_data', [ AffiliateSuspended::class, 'register_defaults' ] );
			add_filter( 'storeengine/email/settings_default_data', [ AffiliateCommissionApproved::class, 'register_defaults' ] );
			add_filter( 'storeengine/email/settings_default_data', [ AffiliatePayoutCompleted::class, 'register_defaults' ] );
		}

		// Item-shipped notice — fired by OrderShipment::record() from both the
		// admin order screen and the multi-vendor dashboard, so it's relevant for
		// single- and multi-vendor stores alike.
		add_filter( 'storeengine/email/settings_default_data', [ ItemShipped::class, 'register_defaults' ] );
		add_filter( 'storeengine/email/settings_default_data', [ Delivered::class, 'register_defaults' ] );
		add_filter( 'storeengine/email/settings_default_data', [ OrderCancelled::class, 'register_defaults' ] );

		// Existing emails — their constructors don't call __() (no fallback
		// branch), so they're safe to instantiate now. Leaving them here
		// preserves WordPress hook registration order with the rest of the
		// codebase that may already depend on it.
		new Settings();
		new Confirm();
		new Refund();
		new Status();
		new Note();
		new NewUserNotification();

		// The *new* email classes call __() inside default_template() as a
		// fallback when their option key isn't in the persisted settings
		// (which is the state on existing installs that activated the email
		// addon before these emails existed). Instantiating them here would
		// resolve __() before WP's init action — WP 6.7+ throws a
		// _load_textdomain_just_in_time notice and breaks headers.
		//
		// Defer to `init` priority 20 so:
		//   1. Settings::save_settings() runs first and backfills the option,
		//   2. constructors then read the populated option and never hit the
		//      __()-touching fallback,
		//   3. even if they did, init has already loaded the textdomain.
		add_action( 'init', [ $this, 'boot_deferred_emails' ], 20 );
	}

	public function boot_deferred_emails(): void {
		// Backfill `storeengine_email_settings` so new email keys exist before
		// the deferred email classes read them. update_option is a no-op when
		// the merged value matches the stored one — cheap on subsequent loads.
		Settings::save_settings();

		new PaymentFailed();
		new PasswordReset();
		new RegistrationWelcome();

		if ( class_exists( '\StoreEngine\Addons\Subscription\Classes\Subscription' ) ) {
			new Cancelled();
			new Renewed();
			new RenewalFailed();
			new TrialEndingSoon();
		}

		new ItemShipped();
		new Delivered();
		new OrderCancelled();

		if ( class_exists( '\StoreEngine\Addons\Affiliate\Affiliate' ) ) {
			new AffiliateRegistered();
			new AffiliateApproved();
			new AffiliateRejected();
			new AffiliateSuspended();
			new AffiliateCommissionApproved();
			new AffiliatePayoutCompleted();
		}
	}

}
