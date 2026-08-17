<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class PaymentFailed implements OrderStatus {
	const STATUS = 'payment_failed';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'hold_order':
				$context->set_order_status( new OnHold() );
				break;
			case 'process_order':
				$context->set_order_status( new Processing() );
				break;
			case 'confirm_payment':
				$context->set_order_status( new PaymentConfirmed() );
				break;
			case 'cancel_payment':
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
		return __( 'Payment Failed', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			PaymentConfirmed::STATUS,
			Cancelled::STATUS,
			OnHold::STATUS,
			Processing::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'confirm_payment',
			'cancel_payment',
			'hold_order',
			'process_order',
		];
	}
}
