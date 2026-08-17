<?php

namespace StoreEngine;

use ReflectionClass;
use StoreEngine;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineRuntimeException;
use StoreEngine\Classes\Order;
use StoreEngine\Integrations\CourseBundle;
use StoreEngine\Integrations\TutorBooking;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Integrations\AbstractIntegration;
use StoreEngine\Integrations\AcademyLms;
use StoreEngine\Integrations\MembershipAddon;

class Integrations {
	use Singleton;

	/**
	 * Container registry.
	 *
	 * @var array<string, AbstractIntegration>
	 */
	private array $integrations = [];

	protected function __construct() {
		add_action( 'init', [ $this, 'register_integrations' ], - 1 );
		add_filter( 'storeengine/backend_scripts_data', [ $this, 'add_scripts_data' ] );
	}

	/**
	 * Initialize & register integration classes.
	 *
	 * @return void
	 * @throws StoreEngineRuntimeException
	 */
	public function register_integrations() {
		$integrations = [
			AcademyLms::ID      => AcademyLms::class,
			MembershipAddon::ID => MembershipAddon::class,
			TutorBooking::ID    => TutorBooking::class,
			CourseBundle::ID    => CourseBundle::class,
		];

		$integrations = apply_filters( 'storeengine/integrations/registry', $integrations );

		foreach ( $integrations as $id => $integration ) {
			/** @var AbstractIntegration $integration */
			$integration = new $integration();

			if ( ! $integration instanceof AbstractIntegration ) {
				throw new StoreEngineRuntimeException( esc_html( sprintf(
					// translators: %s. Integration Identifier.
					__( 'Integration “%1$s” (%2$s) must be a valid subclass of “%3$s”.', 'storeengine' ),
					$id,
					get_class( $integration ),
					AbstractIntegration::class
				) ) );
			}

			// Add to repository.
			$this->integrations[ $id ] = $integration;
		}

		do_action( 'storeengine/integrations/loaded' );
	}

	public function add_scripts_data( array $data ): array {
		$data['integrations'] = array_values( array_map( fn( $integration ) => [
			'id'        => $integration->get_id(),
			'label'     => $integration->get_label(),
			'value'     => $integration->get_id(),
			'icon'      => $integration->get_logo(),
			'isEnabled' => $integration->enabled(),
		], $this->get_integrations() ) );

		return $data;
	}

	/**
	 * Returns an instance of the specified integration class
	 *
	 * @template Provider of AbstractIntegration
	 * @param key-of<self::integrations> $provider
	 * @phpstan-param key-of<self::integrations> $provider
	 *
	 * @return Provider Object instance
	 * @throws StoreEngineException
	 */
	public function get_integration( string $provider ): AbstractIntegration {
		if ( ! did_action( 'storeengine/integrations/loaded' ) ) {
			_doing_it_wrong( __CLASS__ . '::integrations', 'Trying to get integrations before integrations are loaded', '1.0.0' );
		}

		if ( array_key_exists( $provider, $this->integrations ) ) {
			return $this->integrations[ $provider ];
		}

		throw new StoreEngineException( esc_html__( 'Integration does not exist.', 'storeengine' ), 'unknown-integration', [ 'provider' => esc_html( $provider ) ] );
	}

	/**
	 * @return AbstractIntegration[]
	 */
	public function get_integrations(): array {
		if ( ! did_action( 'storeengine/integrations/loaded' ) ) {
			_doing_it_wrong( __CLASS__ . '::integrations', 'Trying to get integrations before integrations are loaded', '1.0.0' );
		}

		return $this->integrations;
	}
}
