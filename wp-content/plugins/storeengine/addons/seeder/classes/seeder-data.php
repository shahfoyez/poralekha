<?php
/**
 * Tiny fake-data helper for seeders. Deliberately dependency-free.
 *
 * Everything is obviously fake (emails on @example.test, "Sample" product
 * names) so seeded records are easy to spot. Randomness uses wp_rand().
 *
 * @package StoreEngine\Addons\Seeder\Classes
 */

namespace StoreEngine\Addons\Seeder\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeederData {

	/**
	 * Meta key stamped on every seeded post/user as a secondary safety marker.
	 * The run manifest is the source of truth for cleanup; this is just so a
	 * human (or a stray query) can recognise seeded rows.
	 */
	const MARKER_META = '_storeengine_seeded';

	private static array $first_names = [
		'Alex', 'Bianca', 'Carlos', 'Dana', 'Elena', 'Farid', 'Grace', 'Hassan',
		'Ingrid', 'Jamal', 'Keiko', 'Liam', 'Mira', 'Noah', 'Olga', 'Priya',
		'Quentin', 'Rosa', 'Sven', 'Tara', 'Umar', 'Vera', 'Wendy', 'Yusuf', 'Zoe',
	];

	private static array $last_names = [
		'Adler', 'Bennett', 'Costa', 'Dewan', 'Eriksson', 'Ferreira', 'Gupta',
		'Hoffman', 'Ibrahim', 'Jensen', 'Kowalski', 'Lopez', 'Mensah', 'Novak',
		'Owusu', 'Petrov', 'Quintero', 'Rossi', 'Singh', 'Tanaka', 'Ueda', 'Vargas',
	];

	private static array $product_adjectives = [
		'Classic', 'Premium', 'Eco', 'Compact', 'Deluxe', 'Smart', 'Pro', 'Lite',
		'Vintage', 'Modern', 'Essential', 'Ultra', 'Everyday', 'Signature',
	];

	private static array $product_nouns = [
		'Headphones', 'Notebook', 'Backpack', 'Coffee Mug', 'Desk Lamp', 'Water Bottle',
		'T-Shirt', 'Sneakers', 'Wireless Mouse', 'Keyboard', 'Course Bundle', 'E-Book',
		'Sticker Pack', 'Membership', 'Toolkit', 'Planner',
	];

	private static array $cities = [
		[ 'Austin', 'TX', '73301', 'US' ],
		[ 'Denver', 'CO', '80014', 'US' ],
		[ 'Seattle', 'WA', '98101', 'US' ],
		[ 'Brooklyn', 'NY', '11201', 'US' ],
		[ 'Miami', 'FL', '33101', 'US' ],
		[ 'Portland', 'OR', '97201', 'US' ],
	];

	private static array $streets = [
		'Maple Ave', 'Oak Street', 'Sunset Blvd', 'River Road', 'Hillcrest Dr',
		'Park Lane', 'Birch Court', 'Cedar Way', 'Lincoln St', 'Market Street',
	];

	/**
	 * Pick a random element from a non-empty array.
	 *
	 * @param array $list
	 *
	 * @return mixed
	 */
	public static function pick( array $list ) {
		return $list[ array_rand( $list ) ];
	}

	public static function first_name(): string {
		return self::pick( self::$first_names );
	}

	public static function last_name(): string {
		return self::pick( self::$last_names );
	}

	/**
	 * A unique, obviously-fake email. The random suffix avoids collisions across
	 * runs (WordPress rejects duplicate user emails).
	 */
	public static function email( string $first, string $last ): string {
		return strtolower( $first . '.' . $last . '.' . wp_rand( 1000, 999999 ) . '@example.test' );
	}

	public static function phone(): string {
		return sprintf( '+1 (555) %03d-%04d', wp_rand( 100, 999 ), wp_rand( 0, 9999 ) );
	}

	public static function product_name(): string {
		return self::pick( self::$product_adjectives ) . ' ' . self::pick( self::$product_nouns );
	}

	/**
	 * @return array{address_1:string,city:string,state:string,postcode:string,country:string}
	 */
	public static function address(): array {
		[ $city, $state, $postcode, $country ] = self::pick( self::$cities );

		return [
			'address_1' => wp_rand( 100, 9999 ) . ' ' . self::pick( self::$streets ),
			'city'      => $city,
			'state'     => $state,
			'postcode'  => $postcode,
			'country'   => $country,
		];
	}

	/**
	 * A plausible retail price ending in .99 / .00 / .49.
	 */
	public static function price(): float {
		$whole = wp_rand( 5, 480 );
		$cents = self::pick( [ 0.0, 0.49, 0.95, 0.99 ] );

		return round( $whole + $cents, 2 );
	}

	/**
	 * A unix timestamp within the last $max_days_ago days.
	 */
	public static function past_timestamp( int $max_days_ago = 120 ): int {
		return time() - wp_rand( 0, $max_days_ago * DAY_IN_SECONDS );
	}
}
