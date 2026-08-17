<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class Cancelled implements OrderStatus {
	const STATUS = 'cancelled';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'hold_order':
			case 'hold_payment':
				$context->set_order_status( new OnHold() );
				break;
			case 'process_order':
			case 'processing':
				$context->set_order_status( new Processing() );
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
		return __( 'Cancelled', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			OnHold::STATUS,
			Processing::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'hold_order',
			'hold_payment',
			'process_order',
			'processing',
		];
	}
}
