<?php

namespace StoreEngine\Addons\Webhooks\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\classes\Exceptions\StoreEngineException;
use StoreEngine\Addons\Webhooks\Listeners\Product\{
	Created as ProductCreated,
	Updated as ProductUpdated,
	Deleted as ProductDeleted,
	Restored as ProductRestored
};
use StoreEngine\Addons\Webhooks\Listeners\Coupon\{
	Created as CouponCreated,
	Updated as CouponUpdated,
	Deleted as CouponDeleted,
	Restored as CouponRestored
};
use StoreEngine\Addons\Webhooks\Listeners\Order\{
	Created as OrderCreated,
	Updated as OrderUpdated,
	Deleted as OrderDeleted,
	Restored as OrderRestored,
};
use StoreEngine\Addons\Webhooks\Listeners\Customer\{
	Created as CustomerCreated,
	Updated as CustomerUpdated,
	Deleted as CustomerDeleted,
};
use StoreEngine\Addons\Webhooks\Listeners\Page\{
	Published as PagePublished,
	Updated as PageUpdated,
	Deleted as PageDeleted,
};
use StoreEngine\Addons\Webhooks\Listeners\Post\{
	Published as PostPublished,
	Updated as PostUpdated,
	Deleted as PostDeleted,
};
use StoreEngine\Addons\Webhooks\Listeners\Template\{
	Saved as TemplateSaved,
	PartSaved as TemplatePartSaved,
};
use StoreEngine\Addons\Webhooks\Listeners\Menu\Updated as NavMenuUpdated;
use StoreEngine\Addons\Webhooks\Listeners\GlobalStyles\Saved as GlobalStylesSaved;
use StoreEngine\Classes\StoreengineDatetime;

class Dispatch {
	protected array $listeners = [];

	public static function init() {
		$self = new self();

		$self->listeners = (array) apply_filters( 'storeengine/webhooks_event_listeners', [
			'product_created'  => ProductCreated::class,
			'product_updated'  => ProductUpdated::class,
			'product_deleted'  => ProductDeleted::class,
			'product_restored' => ProductRestored::class,
			'coupon_created'   => CouponCreated::class,
			'coupon_updated'   => CouponUpdated::class,
			'coupon_deleted'   => CouponDeleted::class,
			'coupon_restored'  => CouponRestored::class,
			'order_created'    => OrderCreated::class,
			'order_updated'    => OrderUpdated::class,
			'order_deleted'    => OrderDeleted::class,
			'order_restored'   => OrderRestored::class,
			'customer_created' => CustomerCreated::class,
			'customer_updated' => CustomerUpdated::class,
			'customer_deleted' => CustomerDeleted::class,

			// CMS content (pages, posts) — for headless storefronts.
			'page_published' => PagePublished::class,
			'page_updated'   => PageUpdated::class,
			'page_deleted'   => PageDeleted::class,
			'post_published' => PostPublished::class,
			'post_updated'   => PostUpdated::class,
			'post_deleted'   => PostDeleted::class,

			// Block-theme / Site Editor.
			'template_saved'      => TemplateSaved::class,
			'template_part_saved' => TemplatePartSaved::class,
			'global_styles_saved' => GlobalStylesSaved::class,

			// Navigation.
			'nav_menu_updated' => NavMenuUpdated::class,
		] );

		$self->init_webhooks();

		// Dispatch hook.
		add_action( 'storeengine/webhooks/async_delivery', [ $self, 'async_delivery' ], 10, 4 );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function async_delivery( int $id, string $topic, array $payload, string $triggeredAt ) {
		Dispatcher::get_instance( $id, $topic, $payload, $triggeredAt )->dispatch();
	}

	public function init_webhooks() {
		$published = GetPublished::all();

		// No published webhooks — skip listener registration entirely.
		if ( empty( $published ) ) {
			return;
		}

		foreach ( $published as ['id' => $id, 'events' => $events] ) {
			// Skip webhook with no delivery URL (saves an entire round-trip).
			$delivery_url = get_post_meta( $id, '_storeengine_webhook_delivery_url', true );
			if ( empty( $delivery_url ) ) {
				continue;
			}

			// Skip webhook that has crossed the failure threshold (default 5 — filterable).
			$failure_count     = (int) get_post_meta( $id, 'failure_count', true );
			$failure_threshold = (int) apply_filters( 'storeengine/webhooks/failure_threshold', 5, $id );
			if ( $failure_threshold > 0 && $failure_count >= $failure_threshold ) {
				continue;
			}

			foreach ( $events as $topic ) {
				if ( array_key_exists( $topic, $this->listeners ) ) {
					$cb = function ( int $id, array $payload ) use ( $topic ) {
						$args = [
							'webhook'     => $id,
							'topic'       => $topic,
							'payload'     => $payload,
							'triggeredAt' => (string) new StoreEngineDateTime(),
						];

						// In-queue dedup: if an identical pending delivery already exists
						// for this (webhook, topic, payload), skip enqueueing another.
						// Note: payload-equality match. triggeredAt is excluded from the
						// dedup signature via the 5th $unique parameter being checked
						// against full args — so we strip it for the lookup.
						$dedup_args = $args;
						unset( $dedup_args['triggeredAt'] );

						if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action(
							'storeengine/webhooks/async_delivery',
							$dedup_args,
							'storeengine-webhooks'
						) ) {
							return;
						}

						as_enqueue_async_action(
							'storeengine/webhooks/async_delivery',
							$args,
							'storeengine-webhooks',
							true
						);
					};

					call_user_func( [ $this->listeners[ $topic ], 'dispatch' ], $cb, $id );
				}
			}
		}
	}

}
