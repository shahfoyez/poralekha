<?php

namespace StoreEngine\Addons\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Webhooks {

	/**
	 * Events the native-billing webhook handler reacts to.
	 */
	public static function events(): array {
		return [
			'invoice.paid',
			'invoice.payment_succeeded',
			'invoice.payment_failed',
			'customer.subscription.updated',
			'customer.subscription.deleted',
		];
	}

	/**
	 * Best-effort auto-registration of the Stripe webhook endpoint. The returned
	 * signing secret is stored in an option that WebhookHandler reads as a
	 * fallback when the admin hasn't pasted one into the settings.
	 *
	 * The reliable path is still manual: create the endpoint
	 * (…/wp-json/storeengine/v1/stripe/trigger) in the Stripe dashboard and paste
	 * its signing secret into the gateway's "Webhook Signing Secret" field.
	 */
	public static function push_webhook_to_stripe() {
		if ( get_option( 'storeengine_stripe_webhook_id' ) ) {
			return;
		}

		$stripe_service = StripeService::init();
		$api_url        = rest_url( 'storeengine/v1/stripe/trigger' );

		try {
			$webhook = $stripe_service->create_webhook( self::events(), $api_url );
		} catch ( \Throwable $e ) {
			\StoreEngine\Utils\Helper::log_error( $e );

			return;
		}

		update_option( 'storeengine_stripe_webhook_id', $webhook->id );

		// Stripe only returns the signing secret on creation — capture it.
		if ( ! empty( $webhook->secret ) ) {
			update_option( 'storeengine_stripe_webhook_secret', $webhook->secret );
		}
	}
}
