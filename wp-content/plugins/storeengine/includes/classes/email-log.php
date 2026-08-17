<?php
/**
 * Email log entry — one row per email send attempt across the whole plugin
 * (orders, refunds, abandoned-cart recoveries, account, subscription, etc.).
 *
 * This is customer-communication audit. For technical diagnostics use the
 * separate `Logger` class + `storeengine_logs` table.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailLog extends AbstractEntity {
	protected string $table = 'storeengine_email_log';

	protected string $object_type = 'email_log';

	protected array $data = [
		'sent_at_gmt'         => null,
		'email_type'          => null,
		'recipient'           => null,
		'subject'             => null,
		'status'              => 'queued',
		'customer_id'         => null,
		'order_id'            => null,
		'related_entity_type' => null,
		'related_entity_id'   => null,
		'error_message'       => null,
		'payload'                => null,
	];

	protected array $data_format = [
		'sent_at_gmt'         => '%s',
		'email_type'          => '%s',
		'recipient'           => '%s',
		'subject'             => '%s',
		'status'              => '%s',
		'customer_id'         => '%d',
		'order_id'            => '%d',
		'related_entity_type' => '%s',
		'related_entity_id'   => '%d',
		'error_message'       => '%s',
		'payload'                => '%s',
	];

	protected array $valid_stati = [ 'queued', 'sent', 'failed' ];

	public function get_sent_at_gmt( string $context = 'view' ) {
		return $this->get_prop( 'sent_at_gmt', $context );
	}

	public function set_sent_at_gmt( $value ) {
		$this->set_prop( 'sent_at_gmt', $value );
	}

	public function get_email_type( string $context = 'view' ) {
		return $this->get_prop( 'email_type', $context );
	}

	public function set_email_type( $value ) {
		$this->set_prop( 'email_type', $value );
	}

	public function get_recipient( string $context = 'view' ) {
		return $this->get_prop( 'recipient', $context );
	}

	public function set_recipient( $value ) {
		$this->set_prop( 'recipient', $value );
	}

	public function get_subject( string $context = 'view' ) {
		return $this->get_prop( 'subject', $context );
	}

	public function set_subject( $value ) {
		$this->set_prop( 'subject', $value );
	}

	public function get_status( string $context = 'view' ): ?string {
		return $this->get_prop( 'status', $context );
	}

	public function set_status( $value ) {
		if ( ! in_array( $value, $this->valid_stati, true ) ) {
			$value = 'queued';
		}

		$this->set_prop( 'status', $value );
	}

	public function get_customer_id( string $context = 'view' ): int {
		return absint( $this->get_prop( 'customer_id', $context ) );
	}

	public function set_customer_id( $value ) {
		$value = absint( $value );
		$this->set_prop( 'customer_id', $value ?: null );
	}

	public function get_order_id( string $context = 'view' ): int {
		return absint( $this->get_prop( 'order_id', $context ) );
	}

	public function set_order_id( $value ) {
		$value = absint( $value );
		$this->set_prop( 'order_id', $value ?: null );
	}

	public function get_related_entity_type( string $context = 'view' ) {
		return $this->get_prop( 'related_entity_type', $context );
	}

	public function set_related_entity_type( $value ) {
		$this->set_prop( 'related_entity_type', $value ?: null );
	}

	public function get_related_entity_id( string $context = 'view' ): int {
		return absint( $this->get_prop( 'related_entity_id', $context ) );
	}

	public function set_related_entity_id( $value ) {
		$value = absint( $value );
		$this->set_prop( 'related_entity_id', $value ?: null );
	}

	public function get_error_message( string $context = 'view' ) {
		return $this->get_prop( 'error_message', $context );
	}

	public function set_error_message( $value ) {
		$this->set_prop( 'error_message', $value ?: null );
	}

	/**
	 * Raw JSON column accessor. Exists so AbstractEntity::prepare_for_db()
	 * can preserve the existing value across update() calls — without this
	 * getter, prepare_for_db falls back to `$raw_data['payload'] ?? null`
	 * when 'payload' isn't in the change set, nulling out the column.
	 */
	public function get_payload( string $context = 'view' ): ?string {
		$value = $this->get_prop( 'payload', $context );

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Return the meta JSON column as a decoded array.
	 *
	 * Sender-side context (headers list, attachment names, the source class
	 * that called wp_mail, resent_from_log_id, etc.) lives here. Decoupled
	 * from get_meta_data() on AbstractEntity which manages WP-style metadata
	 * tables we don't have for this entity.
	 */
	public function get_meta_payload(): array {
		$raw = $this->get_prop( 'payload', 'edit' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	public function set_meta_payload( array $value ) {
		$this->set_prop( 'payload', $value ? wp_json_encode( $value ) : null );
	}

	/**
	 * Merge an associative array into the existing meta payload.
	 * Used by the resend flow to tag the new row with provenance.
	 */
	public function merge_meta_payload( array $value ) {
		$this->set_meta_payload( array_merge( $this->get_meta_payload(), $value ) );
	}
}
