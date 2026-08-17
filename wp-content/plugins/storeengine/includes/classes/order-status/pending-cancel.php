<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class PendingCancel implements OrderStatus {
	const STATUS = 'pending_cancel';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'active':
			case 'activate':
				$context->set_order_status( new Active() );
				break;
			case 'cancel':
				$context->set_order_status( new Cancelled() );
				break;
			default:
				throw new StoreEngineInvalidArgumentException(
					sprintf(
					/* translators: %1$s. Requested status transition, %2$s. Current Status */
						esc_html__( 'Invalid trigger (%1$s) for next status from %2$s', 'storeengine' ),
						esc_html( self::STATUS ),
						esc_html( $trigger )
					),
					'invalid-trigger'
				);
		}
	}

	public function get_status(): string {
		return self::STATUS;
	}

	public function get_status_title(): string {
		return __( 'Pending Cancellation', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			Active::STATUS,
			Cancelled::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'active',
			'activate',
			'cancel',
		];
	}
}
