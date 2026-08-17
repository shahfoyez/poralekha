<?php
/**
 * Singleton that can be used inside abstract classes.
 *
 * @version 1.0.0
 * @since StoreEngine v1.6.7
 */

namespace StoreEngine\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AbstractSingleton {

	protected static array $instances = [];

	public static function init() {
		return self::get_instance();
	}

	public static function get_instance() {
		$called = static::class;

		if ( ! isset( self::$instances[ $called ] ) ) {
			self::$instances[ $called ] = new $called();
		}

		return self::$instances[ $called ];
	}

	protected function __construct() {
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'storeengine' ), '1.6.7' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'storeengine' ), '1.6.7' );
	}
}
