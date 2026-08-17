<?php

namespace StoreEngine\Addons\MultiVendor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vendor {

	const STATUS_PENDING   = 'pending';
	const STATUS_APPROVED  = 'approved';
	const STATUS_SUSPENDED = 'suspended';

	protected int $user_id = 0;
	protected string $store_name = '';
	protected string $store_slug = '';
	protected string $status = self::STATUS_PENDING;
	protected ?float $commission_rate = null;
	protected string $commission_type = 'percent';
	protected string $payout_email = '';
	protected ?string $date_registered = null;
	protected ?string $date_approved = null;
	protected bool $is_visible = true;
	protected bool $is_default = false;
	protected bool $auto_approve_products = false;
	protected array $badges = [];
	protected string $payment_method = 'paypal';
	protected array $payment_data = [];
	protected string $first_name = '';
	protected string $last_name = '';
	protected string $phone = '';
	protected string $address = '';
	protected string $store_description = '';
	protected string $business_type = 'individual';
	protected ?string $terms_accepted_at = null;

	protected bool $loaded = false;

	public function __construct( int $user_id = 0 ) {
		if ( $user_id > 0 ) {
			$this->user_id = $user_id;
			$this->load();
		}
	}

	protected static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storeengine_vendors';
	}

	public function load(): bool {
		global $wpdb;

		if ( ! $this->user_id ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d', self::table(), $this->user_id ),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $row ) {
			return false;
		}

		$this->set_props_from_row( $row );
		$this->loaded = true;
		return true;
	}

	protected function set_props_from_row( array $row ) {
		$this->user_id         = (int) $row['user_id'];
		$this->store_name      = (string) ( $row['store_name'] ?? '' );
		$this->store_slug      = (string) ( $row['store_slug'] ?? '' );
		$this->status          = (string) ( $row['status'] ?? self::STATUS_PENDING );
		$this->commission_rate = isset( $row['commission_rate'] ) && '' !== $row['commission_rate'] && null !== $row['commission_rate']
			? (float) $row['commission_rate']
			: null;
		$this->commission_type = (string) ( $row['commission_type'] ?? 'percent' );
		$this->payout_email          = (string) ( $row['payout_email'] ?? '' );
		$this->date_registered       = $row['date_registered'] ?? null;
		$this->date_approved         = $row['date_approved'] ?? null;
		$this->is_visible            = ! isset( $row['is_visible'] ) || (int) $row['is_visible'] === 1;
		$this->is_default            = isset( $row['is_default'] ) && (int) $row['is_default'] === 1;
		$this->auto_approve_products = isset( $row['auto_approve_products'] ) && (int) $row['auto_approve_products'] === 1;
		$badges                      = isset( $row['badges'] ) ? json_decode( (string) $row['badges'], true ) : [];
		$this->badges                = is_array( $badges ) ? array_values( array_filter( array_map( 'sanitize_key', $badges ) ) ) : [];
		$this->payment_method        = (string) ( $row['payment_method'] ?? 'paypal' );
		$payment_data                = isset( $row['payment_data'] ) ? json_decode( (string) $row['payment_data'], true ) : [];
		$this->payment_data          = is_array( $payment_data ) ? $payment_data : [];
		$this->first_name        = (string) ( $row['first_name'] ?? '' );
		$this->last_name         = (string) ( $row['last_name'] ?? '' );
		$this->phone             = (string) ( $row['phone'] ?? '' );
		$this->address           = (string) ( $row['address'] ?? '' );
		$this->store_description = (string) ( $row['store_description'] ?? '' );
		$business_type           = (string) ( $row['business_type'] ?? 'individual' );
		$this->business_type     = in_array( $business_type, [ 'individual', 'company' ], true ) ? $business_type : 'individual';
		$this->terms_accepted_at = $row['terms_accepted_at'] ?? null;
	}

	public function get_first_name(): string {
		return $this->first_name;
	}
	public function set_first_name( string $value ) {
		$this->first_name = sanitize_text_field( $value );
	}
	public function get_last_name(): string {
		return $this->last_name;
	}
	public function set_last_name( string $value ) {
		$this->last_name = sanitize_text_field( $value );
	}
	public function get_full_name(): string {
		return trim( $this->first_name . ' ' . $this->last_name );
	}
	public function get_phone(): string {
		return $this->phone;
	}
	public function set_phone( string $value ) {
		$this->phone = preg_replace( '/[^0-9+\-\s()]/', '', $value );
	}
	public function get_address(): string {
		return $this->address;
	}
	public function set_address( string $value ) {
		$this->address = sanitize_textarea_field( $value );
	}
	public function get_store_description(): string {
		return $this->store_description;
	}
	public function set_store_description( string $value ) {
		$this->store_description = sanitize_textarea_field( $value );
	}
	public function get_business_type(): string {
		return $this->business_type;
	}
	public function set_business_type( string $value ) {
		$this->business_type = in_array( $value, [ 'individual', 'company' ], true ) ? $value : 'individual';
	}
	public function get_terms_accepted_at(): ?string {
		return $this->terms_accepted_at;
	}
	public function set_terms_accepted_at( ?string $value ) {
		$this->terms_accepted_at = $value;
	}

	public function is_visible(): bool {
		return $this->is_visible;
	}
	public function set_is_visible( bool $value ) {
		$this->is_visible = $value;
	}
	public function is_default(): bool {
		return $this->is_default;
	}
	public function set_is_default( bool $value ) {
		$this->is_default = $value;
	}
	public function get_auto_approve_products(): bool {
		return $this->auto_approve_products;
	}
	public function set_auto_approve_products( bool $value ) {
		$this->auto_approve_products = $value;
	}
	public function get_badges(): array {
		return $this->badges;
	}
	public function set_badges( array $badges ) {
		$this->badges = array_values( array_unique( array_filter( array_map( 'sanitize_key', $badges ) ) ) );
	}
	public function get_payment_method(): string {
		return $this->payment_method;
	}
	public function set_payment_method( string $method ) {
		$this->payment_method = sanitize_key( $method );
	}
	public function get_payment_data(): array {
		return $this->payment_data;
	}
	/**
	 * Allowlist of payment-data keys we accept from the vendor. Non-string
	 * values (nested arrays, booleans) used to flow through unchanged, which
	 * meant any future template echoing `$payment_data['x']` without escape
	 * would XSS. Sticking to a typed allowlist also lets us drop unexpected
	 * keys before they get serialized into the vendor profile.
	 */
	const ALLOWED_PAYMENT_FIELDS = [
		'paypal_email',
		'account_holder',
		'account_number',
		'bank_name',
		'branch',
		'routing_number',
		'swift',
		'iban',
		'notes',
	];

	public function set_payment_data( array $data ) {
		$clean    = [];
		$allowed  = array_flip( self::ALLOWED_PAYMENT_FIELDS );
		foreach ( $data as $k => $v ) {
			$key = sanitize_key( $k );
			if ( ! isset( $allowed[ $key ] ) ) continue;
			// All known fields are simple strings — coerce to string and
			// sanitize. Refusing non-scalars here prevents an attacker from
			// smuggling raw markup via nested arrays.
			if ( is_array( $v ) || is_object( $v ) ) continue;
			$clean[ $key ] = sanitize_text_field( (string) $v );
		}
		$this->payment_data = $clean;
	}

	public function exists(): bool {
		return $this->loaded;
	}

	public function get_user_id(): int {
		return $this->user_id;
	}

	public function set_user_id( int $user_id ) {
		$this->user_id = $user_id;
	}

	public function get_store_name(): string {
		return $this->store_name;
	}

	public function set_store_name( string $name ) {
		$this->store_name = $name;
	}

	public function get_store_slug(): string {
		return $this->store_slug;
	}

	public function set_store_slug( string $slug ) {
		$this->store_slug = sanitize_title( $slug );
	}

	public function get_status(): string {
		return $this->status;
	}

	public function set_status( string $status ) {
		$allowed = [ self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_SUSPENDED ];
		if ( in_array( $status, $allowed, true ) ) {
			$this->status = $status;
		}
	}

	public function is_approved(): bool {
		return self::STATUS_APPROVED === $this->status;
	}

	public function get_commission_rate(): ?float {
		return $this->commission_rate;
	}

	public function set_commission_rate( ?float $rate ) {
		$this->commission_rate = $rate;
	}

	public function get_commission_type(): string {
		return $this->commission_type;
	}

	public function set_commission_type( string $type ) {
		if ( in_array( $type, [ 'percent', 'flat' ], true ) ) {
			$this->commission_type = $type;
		}
	}

	public function get_payout_email(): string {
		return $this->payout_email;
	}

	public function set_payout_email( string $email ) {
		$this->payout_email = sanitize_email( $email );
	}

	public function get_date_registered(): ?string {
		return $this->date_registered;
	}

	public function get_date_approved(): ?string {
		return $this->date_approved;
	}

	public function save(): bool {
		global $wpdb;

		if ( ! $this->user_id ) {
			return false;
		}

		$data = [
			'user_id'               => $this->user_id,
			'store_name'            => $this->store_name,
			'store_slug'            => $this->store_slug,
			'status'                => $this->status,
			'commission_rate'       => $this->commission_rate,
			'commission_type'       => $this->commission_type,
			'payout_email'          => $this->payout_email,
			'is_visible'            => $this->is_visible ? 1 : 0,
			'is_default'            => $this->is_default ? 1 : 0,
			'auto_approve_products' => $this->auto_approve_products ? 1 : 0,
			'badges'                => wp_json_encode( $this->badges ),
			'payment_method'        => $this->payment_method,
			'payment_data'          => wp_json_encode( $this->payment_data ),
			'first_name'            => $this->first_name,
			'last_name'             => $this->last_name,
			'phone'                 => $this->phone,
			'address'               => $this->address,
			'store_description'     => $this->store_description,
			'business_type'         => $this->business_type,
			'terms_accepted_at' => $this->terms_accepted_at,
		];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', self::table(), $this->user_id )
		);

		if ( $exists ) {
			$result = $wpdb->update( self::table(), $data, [ 'user_id' => $this->user_id ] );
		} else {
			if ( ! $this->date_registered ) {
				$data['date_registered'] = current_time( 'mysql', 1 );
				$this->date_registered   = $data['date_registered'];
			}
			$result = $wpdb->insert( self::table(), $data );
		}
		// phpcs:enable

		if ( false === $result ) {
			return false;
		}

		$this->loaded = true;
		return true;
	}

	public function approve() {
		$this->status        = self::STATUS_APPROVED;
		$this->date_approved = current_time( 'mysql', 1 );

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			self::table(),
			[
				'status'        => $this->status,
				'date_approved' => $this->date_approved,
			],
			[ 'user_id' => $this->user_id ]
		);
		// phpcs:enable

		do_action( 'storeengine/multi_vendor/vendor_approved', $this );
	}

	public function suspend() {
		$this->status = self::STATUS_SUSPENDED;

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			self::table(),
			[ 'status' => $this->status ],
			[ 'user_id' => $this->user_id ]
		);
		// phpcs:enable

		do_action( 'storeengine/multi_vendor/vendor_suspended', $this );
	}

	public function to_array(): array {
		return [
			'user_id'               => $this->user_id,
			'store_name'            => $this->store_name,
			'store_slug'            => $this->store_slug,
			'status'                => $this->status,
			'commission_rate'       => $this->commission_rate,
			'commission_type'       => $this->commission_type,
			'payout_email'          => $this->payout_email,
			'date_registered'       => $this->date_registered,
			'date_approved'         => $this->date_approved,
			'is_visible'            => $this->is_visible,
			'is_default'            => $this->is_default,
			'auto_approve_products' => $this->auto_approve_products,
			'badges'                => $this->badges,
			'payment_method'        => $this->payment_method,
			'payment_data'          => $this->payment_data,
			'first_name'            => $this->first_name,
			'last_name'             => $this->last_name,
			'phone'                 => $this->phone,
			'address'               => $this->address,
			'store_description'     => $this->store_description,
			'business_type'         => $this->business_type,
			'terms_accepted_at' => $this->terms_accepted_at,
		];
	}
}
