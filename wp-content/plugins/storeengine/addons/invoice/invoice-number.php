<?php
/**
 * Sequential, gap-free invoice numbering (§14 UStG).
 *
 * A real invoice number must be unique and sequential — the order ID is not
 * (drafts/failed orders create gaps). A number is assigned once, on payment,
 * and stored immutably on the order so re-rendering the PDF never changes it.
 *
 * Concurrency: assignment runs inside a MySQL named lock so two simultaneous
 * payments can't claim the same number or skip one.
 *
 * @package StoreEngine\Addons\Invoice
 */

namespace StoreEngine\Addons\Invoice;

use StoreEngine\Classes\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InvoiceNumber {

	const META_KEY     = '_storeengine_invoice_number';
	const META_SEQ     = '_storeengine_invoice_number_seq';
	const OPT_NEXT     = 'storeengine_invoice_next_number';
	const OPT_YEAR     = 'storeengine_invoice_number_year';
	const LOCK_NAME    = 'storeengine_invoice_number';

	/**
	 * Register hooks. Called from the addon's init_addon().
	 */
	public static function init(): void {
		add_action( 'storeengine/payment_complete', [ __CLASS__, 'on_payment_complete' ] );
	}

	/**
	 * @param int $order_id Order being paid.
	 */
	public static function on_payment_complete( $order_id ): void {
		$order = storeengine_get_order( (int) $order_id );
		if ( $order instanceof Order ) {
			self::assign( $order );
		}
	}

	/**
	 * Return the stored invoice number, falling back to the order ID for legacy
	 * orders that were paid before this feature existed.
	 */
	public static function get( Order $order ): string {
		$num = (string) $order->get_meta( self::META_KEY );

		return '' !== $num ? $num : (string) $order->get_id();
	}

	/**
	 * Assign a number once, immutably. No-op if one already exists.
	 */
	public static function assign( Order $order ): void {
		if ( '' !== (string) $order->get_meta( self::META_KEY ) ) {
			return;
		}

		global $wpdb;

		// Serialize assignment across requests (reset-check + increment).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared MySQL advisory lock (GET_LOCK) to serialize number assignment; not a cacheable read.
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::LOCK_NAME, 10 ) );

		try {
			$reset = (string) HelperAddon::get_setting( 'invoice_number_reset', 'none' );
			$start = max( 1, (int) HelperAddon::get_setting( 'invoice_number_next', 1 ) );
			$year  = (int) current_time( 'Y' );

			$stored_year = (int) get_option( self::OPT_YEAR, $year );
			$next        = (int) get_option( self::OPT_NEXT, $start );

			if ( 'yearly' === $reset && $stored_year !== $year ) {
				$next = $start;
				update_option( self::OPT_YEAR, $year, false );
			} elseif ( false === get_option( self::OPT_YEAR, false ) ) {
				update_option( self::OPT_YEAR, $year, false );
			}

			$seq = $next;
			update_option( self::OPT_NEXT, $seq + 1, false );

			$order->update_meta_data( self::META_KEY, self::format( $seq, $year ) );
			$order->update_meta_data( self::META_SEQ, $seq );
			$order->save_meta_data();
		} finally {
			if ( $locked ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared MySQL advisory lock release (RELEASE_LOCK); not a cacheable read.
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::LOCK_NAME ) );
			}
		}
	}

	/**
	 * Apply the configured format: prefix + optional year + zero-padded sequence.
	 * e.g. "RE-2026-0001" (yearly) or "RE-0001".
	 */
	public static function format( int $seq, int $year ): string {
		$prefix  = (string) HelperAddon::get_setting( 'invoice_number_prefix', '' );
		$padding = max( 1, (int) HelperAddon::get_setting( 'invoice_number_padding', 4 ) );
		$reset   = (string) HelperAddon::get_setting( 'invoice_number_reset', 'none' );

		$year_part = 'yearly' === $reset ? $year . '-' : '';
		$number    = str_pad( (string) $seq, $padding, '0', STR_PAD_LEFT );

		return $prefix . $year_part . $number;
	}
}
