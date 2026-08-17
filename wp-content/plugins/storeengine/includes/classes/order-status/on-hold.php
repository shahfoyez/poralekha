<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class OnHold implements OrderStatus {
	const STATUS = 'on_hold';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'payment_confirm':
				$context->set_order_status( new PaymentConfirmed() );
				break;
			case 'payment_fail':
			case 'payment_failed':
				$context->set_order_status( new PaymentFailed() );
				break;
			case 'pending_payment':
				$context->set_order_status( new PendingPayment() );
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
		return __( 'On Hold', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			PaymentConfirmed::STATUS,
			PaymentFailed::STATUS,
			PendingPayment::STATUS,
			Cancelled::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'payment_confirm',
			'payment_fail',
			'payment_failed',
			'pending_payment',
			'cancel',
		];
	}
}
