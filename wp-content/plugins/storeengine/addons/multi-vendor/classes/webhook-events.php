<?php

namespace StoreEngine\Addons\MultiVendor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges multi-vendor lifecycle actions into the webhooks addon's event
 * registry. The webhooks addon exposes `storeengine/webhooks_event_listeners`
 * for exactly this kind of extension — adding vendor topics here means
 * external systems can subscribe without webhooks needing to import any
 * multi-vendor code directly.
 */
final class WebhookEvents {

	const TOPIC_VENDOR_REGISTERED          = 'vendor_registered';
	const TOPIC_VENDOR_APPROVED            = 'vendor_approved';
	const TOPIC_VENDOR_SUSPENDED           = 'vendor_suspended';
	const TOPIC_COMMISSION_COMPUTED        = 'commission_computed';
	const TOPIC_COMMISSION_REVERSED        = 'commission_reversed';
	const TOPIC_WITHDRAWAL_REQUESTED       = 'withdrawal_requested';
	const TOPIC_WITHDRAWAL_STATUS_CHANGED  = 'withdrawal_status_changed';
	const TOPIC_PAYOUT_RECORDED            = 'payout_recorded';
	const TOPIC_VENDOR_PRODUCTS_ASSIGNED   = 'vendor_products_assigned';

	public static function init(): void {
		add_filter( 'storeengine/webhooks_event_listeners', [ __CLASS__, 'register' ] );
		// Surface the vendor topics in the admin UI's event picker (the React
		// Webhooks form reads this list from PHP via backend_scripts_data).
		add_filter( 'storeengine/webhooks_events', [ __CLASS__, 'register_event_slugs' ] );
		add_filter( 'storeengine/webhooks_event_labels', [ __CLASS__, 'register_event_labels' ] );
		// Group all vendor events under a single "Vendor" group in the
		// react-select grouped event picker.
		add_filter( 'storeengine/webhooks_event_groups', [ __CLASS__, 'register_event_group' ] );
	}

	public static function register_event_slugs( array $slugs ): array {
		return array_merge( $slugs, [
			self::TOPIC_VENDOR_REGISTERED,
			self::TOPIC_VENDOR_APPROVED,
			self::TOPIC_VENDOR_SUSPENDED,
			self::TOPIC_COMMISSION_COMPUTED,
			self::TOPIC_COMMISSION_REVERSED,
			self::TOPIC_WITHDRAWAL_REQUESTED,
			self::TOPIC_WITHDRAWAL_STATUS_CHANGED,
			self::TOPIC_PAYOUT_RECORDED,
			self::TOPIC_VENDOR_PRODUCTS_ASSIGNED,
		] );
	}

	public static function register_event_labels( array $labels ): array {
		return array_merge( $labels, [
			[ 'label' => __( 'Vendor: Registered', 'storeengine' ),               'value' => self::TOPIC_VENDOR_REGISTERED ],
			[ 'label' => __( 'Vendor: Approved', 'storeengine' ),                 'value' => self::TOPIC_VENDOR_APPROVED ],
			[ 'label' => __( 'Vendor: Suspended', 'storeengine' ),                'value' => self::TOPIC_VENDOR_SUSPENDED ],
			[ 'label' => __( 'Vendor: Commission Computed', 'storeengine' ),      'value' => self::TOPIC_COMMISSION_COMPUTED ],
			[ 'label' => __( 'Vendor: Commission Reversed', 'storeengine' ),      'value' => self::TOPIC_COMMISSION_REVERSED ],
			[ 'label' => __( 'Vendor: Withdrawal Requested', 'storeengine' ),     'value' => self::TOPIC_WITHDRAWAL_REQUESTED ],
			[ 'label' => __( 'Vendor: Withdrawal Status Changed', 'storeengine' ),'value' => self::TOPIC_WITHDRAWAL_STATUS_CHANGED ],
			[ 'label' => __( 'Vendor: Payout Recorded', 'storeengine' ),          'value' => self::TOPIC_PAYOUT_RECORDED ],
			[ 'label' => __( 'Vendor: Products Assigned', 'storeengine' ),        'value' => self::TOPIC_VENDOR_PRODUCTS_ASSIGNED ],
		] );
	}

	public static function register_event_group( array $groups ): array {
		$groups[] = [
			'label'   => __( 'Vendor', 'storeengine' ),
			'options' => [
				[ 'label' => __( 'Registered', 'storeengine' ),                'value' => self::TOPIC_VENDOR_REGISTERED ],
				[ 'label' => __( 'Approved', 'storeengine' ),                  'value' => self::TOPIC_VENDOR_APPROVED ],
				[ 'label' => __( 'Suspended', 'storeengine' ),                 'value' => self::TOPIC_VENDOR_SUSPENDED ],
				[ 'label' => __( 'Commission Computed', 'storeengine' ),       'value' => self::TOPIC_COMMISSION_COMPUTED ],
				[ 'label' => __( 'Commission Reversed', 'storeengine' ),       'value' => self::TOPIC_COMMISSION_REVERSED ],
				[ 'label' => __( 'Withdrawal Requested', 'storeengine' ),      'value' => self::TOPIC_WITHDRAWAL_REQUESTED ],
				[ 'label' => __( 'Withdrawal Status Changed', 'storeengine' ), 'value' => self::TOPIC_WITHDRAWAL_STATUS_CHANGED ],
				[ 'label' => __( 'Payout Recorded', 'storeengine' ),           'value' => self::TOPIC_PAYOUT_RECORDED ],
				[ 'label' => __( 'Products Assigned', 'storeengine' ),         'value' => self::TOPIC_VENDOR_PRODUCTS_ASSIGNED ],
			],
		];
		return $groups;
	}

	public static function register( array $listeners ): array {
		$listeners[ self::TOPIC_VENDOR_REGISTERED ]         = Webhooks\VendorRegistered::class;
		$listeners[ self::TOPIC_VENDOR_APPROVED ]           = Webhooks\VendorApproved::class;
		$listeners[ self::TOPIC_VENDOR_SUSPENDED ]          = Webhooks\VendorSuspended::class;
		$listeners[ self::TOPIC_COMMISSION_COMPUTED ]       = Webhooks\CommissionComputed::class;
		$listeners[ self::TOPIC_COMMISSION_REVERSED ]       = Webhooks\CommissionReversed::class;
		$listeners[ self::TOPIC_WITHDRAWAL_REQUESTED ]      = Webhooks\WithdrawalRequested::class;
		$listeners[ self::TOPIC_WITHDRAWAL_STATUS_CHANGED ] = Webhooks\WithdrawalStatusChanged::class;
		$listeners[ self::TOPIC_PAYOUT_RECORDED ]           = Webhooks\PayoutRecorded::class;
		$listeners[ self::TOPIC_VENDOR_PRODUCTS_ASSIGNED ]  = Webhooks\VendorProductsAssigned::class;
		return $listeners;
	}
}
