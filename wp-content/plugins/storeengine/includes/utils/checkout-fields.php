<?php
/**
 * Checkout Fields helper.
 *
 * Single source of truth for the checkout-form schema. Reads the
 * `checkout_fields` setting (managed from StoreEngine → Settings →
 * Checkout Fields) and exposes a normalised list both the traditional
 * PHP templates and the React embedded checkout consume.
 *
 *   $fields = CheckoutFields::all();         // every field with metadata
 *   $fields = CheckoutFields::for_group('billing');
 *   $rule   = CheckoutFields::get( 'billing_state' );
 *   $req    = CheckoutFields::required_payload_keys();
 *   $key    = CheckoutFields::payload_key( 'billing_address_line' ); // 'billing_address_1'
 */

namespace StoreEngine\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CheckoutFields {

	const SETTING = 'checkout_fields';

	/**
	 * Schema for every supported field. Defines:
	 *   - id                settings key
	 *   - payload_key       key used in the place_order POST/REST payload
	 *   - group             contact|billing|shipping
	 *   - label             translated label
	 *   - system            true → user can't disable; always required
	 *   - default_enabled   default enabled state when admin hasn't configured this field
	 *   - default_required  default required state when admin hasn't configured this field
	 *
	 * System fields are always enabled & required regardless of defaults below.
	 * Non-system fields fall back to these defaults when the admin hasn't saved a
	 * preference yet — without this, every non-system field would silently default
	 * to disabled, causing Address line 2 / State / Postcode / Phone to vanish
	 * from the rendered form (which also breaks shipping calc because
	 * has_full_shipping_address() requires state + postcode to be present).
	 */
	public static function schema(): array {
		/**
		 * Filter the raw checkout-field schema. Addons can flip the `system`
		 * flag or adjust per-field defaults per store mode (e.g. demote email
		 * and require phone in phone-only checkout).
		 *
		 * @param array $schema
		 */
		return apply_filters( 'storeengine/checkout/fields_schema', [
			// Contact
			'email' => [
				'payload_key' => 'user_email',
				'group'       => 'contact',
				'label'       => __( 'Email', 'storeengine' ),
				'system'      => true,
			],
			// Billing
			'billing_first_name'      => [ 'payload_key' => 'billing_first_name', 'group' => 'billing', 'label' => __( 'First name', 'storeengine' ),     'default_enabled' => true,  'default_required' => true ],
			'billing_last_name'       => [ 'payload_key' => 'billing_last_name',  'group' => 'billing', 'label' => __( 'Last name', 'storeengine' ),      'default_enabled' => true,  'default_required' => true ],
			'billing_address_line'    => [ 'payload_key' => 'billing_address_1',  'group' => 'billing', 'label' => __( 'Address line', 'storeengine' ),   'default_enabled' => true,  'default_required' => true ],
			'billing_address_line_2'  => [ 'payload_key' => 'billing_address_2',  'group' => 'billing', 'label' => __( 'Address line 2', 'storeengine' ), 'default_enabled' => true,  'default_required' => false ],
			'billing_country'         => [ 'payload_key' => 'billing_country',    'group' => 'billing', 'label' => __( 'Country', 'storeengine' ),        'default_enabled' => true,  'default_required' => true ],
			'billing_city'            => [ 'payload_key' => 'billing_city',       'group' => 'billing', 'label' => __( 'City', 'storeengine' ),           'default_enabled' => true,  'default_required' => true ],
			'billing_state'           => [ 'payload_key' => 'billing_state',      'group' => 'billing', 'label' => __( 'State / Region', 'storeengine' ), 'default_enabled' => true,  'default_required' => false ],
			'billing_apt'             => [ 'payload_key' => 'billing_apt',        'group' => 'billing', 'label' => __( 'Apt / Suite', 'storeengine' ),    'default_enabled' => false, 'default_required' => false ],
			'billing_post_code'       => [ 'payload_key' => 'billing_postcode',   'group' => 'billing', 'label' => __( 'Postcode', 'storeengine' ),       'default_enabled' => true,  'default_required' => true ],
			'billing_phone'           => [ 'payload_key' => 'billing_phone',      'group' => 'billing', 'label' => __( 'Phone', 'storeengine' ),          'default_enabled' => true,  'default_required' => false ],
			// Shipping
			'shipping_first_name'     => [ 'payload_key' => 'shipping_first_name',     'group' => 'shipping', 'label' => __( 'First name', 'storeengine' ), 'system' => true ],
			'shipping_last_name'      => [ 'payload_key' => 'shipping_last_name',      'group' => 'shipping', 'label' => __( 'Last name', 'storeengine' ),  'system' => true ],
			'shipping_address_line'   => [ 'payload_key' => 'shipping_address_1',      'group' => 'shipping', 'label' => __( 'Address line', 'storeengine' ),   'default_enabled' => true,  'default_required' => true ],
			'shipping_address_line_2' => [ 'payload_key' => 'shipping_address_2',      'group' => 'shipping', 'label' => __( 'Address line 2', 'storeengine' ), 'default_enabled' => true,  'default_required' => false ],
			'shipping_country'        => [ 'payload_key' => 'shipping_country',        'group' => 'shipping', 'label' => __( 'Country', 'storeengine' ),        'default_enabled' => true,  'default_required' => true ],
			'shipping_city'           => [ 'payload_key' => 'shipping_city',           'group' => 'shipping', 'label' => __( 'City', 'storeengine' ),           'default_enabled' => true,  'default_required' => true ],
			'shipping_state'          => [ 'payload_key' => 'shipping_state',          'group' => 'shipping', 'label' => __( 'State / Region', 'storeengine' ), 'default_enabled' => true,  'default_required' => false ],
			'shipping_apt'            => [ 'payload_key' => 'shipping_apt',            'group' => 'shipping', 'label' => __( 'Apt / Suite', 'storeengine' ),    'default_enabled' => false, 'default_required' => false ],
			'shipping_post_code'      => [ 'payload_key' => 'shipping_postal_code',    'group' => 'shipping', 'label' => __( 'Postcode', 'storeengine' ),       'default_enabled' => true,  'default_required' => true ],
			'shipping_phone'          => [ 'payload_key' => 'shipping_phone',          'group' => 'shipping', 'label' => __( 'Phone', 'storeengine' ),          'default_enabled' => true,  'default_required' => false ],
		] );
	}

	/**
	 * Resolved list with the saved enabled/required flags merged in.
	 * Returns array<string $id, array> with keys: id, payload_key, group, label, system, enabled, required.
	 */
	public static function all(): array {
		$schema = self::schema();
		$saved  = (array) Helper::get_settings( self::SETTING, [] );

		// Helper::get_settings can return objects when settings are JSON-decoded
		// without assoc; coerce to array for predictable iteration.
		$saved = json_decode( wp_json_encode( $saved ), true ) ?: [];

		// When shipping is live, the address details the rate calculator relies on
		// must be mandatory — otherwise a shopper can leave them blank and silently
		// get no shipping options (zone matching + has_full_shipping_address() both
		// need them). Promote the *enabled* shipping location fields to required.
		// Memoised per request; the field list is filterable for stores with
		// unusual locale needs. The truly-impossible case (a destination country
		// with no states) is relaxed in required_payload_keys() / validate().
		static $force_required_ids = null;
		if ( null === $force_required_ids ) {
			$shipping_live      = ShippingUtils::is_shipping_enabled() && ShippingUtils::get_shipping_methods_count( true ) > 0;
			$force_required_ids = $shipping_live
				? (array) apply_filters( 'storeengine/checkout/required_shipping_fields', [ 'shipping_city', 'shipping_state', 'shipping_post_code' ] )
				: [];
		}

		$out = [];
		foreach ( $schema as $id => $meta ) {
			$saved_row  = is_array( $saved[ $id ] ?? null ) ? $saved[ $id ] : [];
			$system     = ! empty( $meta['system'] );
			// Per-field defaults from the schema; fall back to false only when the
			// schema itself didn't declare a preference. System fields ignore both.
			$default_enabled  = ! empty( $meta['default_enabled'] );
			$default_required = ! empty( $meta['default_required'] );
			// System fields are always enabled & always required. For the rest,
			// use a tolerant boolean coercer because form-encoded payloads send
			// `false` as the literal string `"false"`, and `(bool) "false" === true`.
			// When the admin hasn't saved a preference yet, fall back to the
			// per-field schema default rather than blanket-disabling everything.
			$enabled  = $system ? true : self::to_bool( $saved_row['enabled']  ?? $default_enabled );
			$required = $system ? true : self::to_bool( $saved_row['required'] ?? $default_required );

			// Force-require the shipping fields shipping calc depends on, but only
			// when they're actually shown — never resurrect a disabled field.
			if ( $enabled && in_array( $id, $force_required_ids, true ) ) {
				$required = true;
			}

			$out[ $id ] = [
				'id'          => $id,
				'payload_key' => $meta['payload_key'],
				'group'       => $meta['group'],
				'label'       => $meta['label'],
				'system'      => $system,
				'enabled'     => $enabled,
				'required'    => $required,
			];
		}

		return $out;
	}

	/**
	 * Tolerant boolean coercer. Treats "false", "0", "", "no", "off" as false.
	 * Standard `(bool)` casts treat any non-empty string as true, which silently
	 * breaks settings that come from form-encoded POST data.
	 */
	public static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value > 0;
		}
		if ( is_string( $value ) ) {
			$v = strtolower( trim( $value ) );
			if ( in_array( $v, [ '', '0', 'false', 'no', 'off', 'null' ], true ) ) {
				return false;
			}
			return true;
		}

		return (bool) $value;
	}

	public static function for_group( string $group ): array {
		return array_filter( self::all(), static fn( $f ) => $f['group'] === $group );
	}

	public static function get( string $id ): ?array {
		$all = self::all();

		return $all[ $id ] ?? null;
	}

	/**
	 * Map a settings id (e.g. 'billing_address_line') to the payload key the
	 * place_order endpoint understands (e.g. 'billing_address_1').
	 */
	public static function payload_key( string $id ): ?string {
		$row = self::get( $id );

		return $row['payload_key'] ?? null;
	}

	/**
	 * Required payload keys, accounting for the contextual `needs_shipping` flag:
	 * shipping fields are only required when the cart actually needs shipping.
	 */
	public static function required_payload_keys( bool $needs_shipping = true, string $shipping_country = '' ): array {
		// Don't demand a State for a destination that simply has none — otherwise
		// the force-required promotion in all() would make those orders
		// un-completable. get_states() returns an empty array for stateless
		// countries (and a populated map for US/BD/etc.).
		$skip_shipping_state = false;
		if ( $shipping_country ) {
			$states              = \StoreEngine\Classes\Countries::init()->get_states( $shipping_country );
			$skip_shipping_state = is_array( $states ) && empty( $states );
		}

		$keys = [];
		foreach ( self::all() as $row ) {
			if ( ! $row['enabled'] || ! $row['required'] ) {
				continue;
			}
			if ( ! $needs_shipping && 'shipping' === $row['group'] ) {
				continue;
			}
			if ( $skip_shipping_state && 'shipping_state' === $row['id'] ) {
				continue;
			}
			$keys[] = $row['payload_key'];
		}

		return array_values( array_unique( $keys ) );
	}
}
