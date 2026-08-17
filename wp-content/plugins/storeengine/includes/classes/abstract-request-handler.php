<?php

namespace StoreEngine\Classes;

defined( 'ABSPATH' ) || exit;

/**
 * AbstractRequestHandler
 *
 * Base request handler for StoreEngine actions.
 *
 * This class provides a unified, secure, and extensible way to:
 * - Register and dispatch WordPress actions (AJAX / admin_post)
 * - Verify nonces
 * - Enforce permission checks
 * - Sanitize and validate request payloads using declarative schemas
 * - Return consistent success/error responses
 *
 * ## Supported request types
 * - wp_ajax_*
 * - wp_ajax_nopriv_*
 * - admin_post_*
 * - admin_post_nopriv_*
 *
 * ## Payload schema features
 * - Scalar sanitization (string, int, float, bool, post, etc.)
 * - Nested objects
 * - Repeated fields (arrays of objects)
 * - Advanced validation & behavior:
 *   - enum
 *   - min / max (length or numeric)
 *   - regex
 *   - required
 *   - nullable
 *   - default values
 *   - cast-only (skip validation)
 *   - custom labels & per-field error messages
 *   - min_items / max_items (arrays)
 *   - custom sanitizer callbacks
 *   - custom validator callbacks
 *   - OpenAPI schema export
 *
 * ## Example schema
 * ```
 * 'fields' => [
 *   'id' => [
 *     'label'    => 'Product ID',
 *     'rules'    => 'absint|required',
 *   ],
 *   'title' => [
 *     'label'    => 'Product Title',
 *     'rules'    => 'string|min:3|max:100|required',
 *     'messages' => [
 *       'required' => '%s cannot be empty.',
 *       'min'      => '%s must be at least 3 characters.',
 *       'max'      => '%s must be shorter than 100 characters.',
 *     ],
 *   ],
 *   'status' => [
 *     'rules'   => 'string|enum:draft,published',
 *     'default' => 'draft',
 *   ],
 *   'price' => [
 *     'rules'    => 'float|min:0',
 *     'nullable' => true,
 *   ],
 *   'features' => [
 *     'min_items' => 1,
 *     'max_items' => 5,
 *     [
 *       'title' => [
 *         'rules' => 'string|required',
 *       ],
 *       'cost' => [
 *         'rules'   => 'float|min:0',
 *         'default' => 0,
 *       ],
 *     ]
 *   ],
 * ]
 * ```
 *
 * Concrete handlers should extend this class and:
 * - Define `$actions`
 * - Implement `dispatch_actions()`
 * - Implement callback methods
 *
 * @package StoreEngine\Classes
 */

use Exception;
use stdClass;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base abstract request handler.
 *
 * Each extending class represents a logical request group
 * (e.g. products, licenses, subscriptions).
 *
 * This class is intentionally opinionated:
 * - Declarative input schema
 * - Centralized sanitization & validation
 * - WordPress-native error handling
 *
 * Extend this class instead of directly using wp_ajax_* callbacks.
 */
abstract class AbstractRequestHandler {

	public const ABSINT = 'absint';
	public const ID = 'id';
	public const INT = 'int';
	public const INTEGER = 'integer';
	public const ABS_INTEGER = 'absint';
	public const ID_ARR = 'array-id';
	public const IDS = 'ids';
	public const DOUBLE = 'double';
	public const FLOAT = 'float';
	public const ABS_DOUBLE = 'abs-double';
	public const ABS_FLOAT = 'abs-float';
	public const URL = 'url';
	public const BOOLEAN = 'bool';
	public const POST = 'post';
	public const HTML = 'post';
	public const SLUG = 'slug';
	public const EMAIL = 'email';
	public const USER = 'user';
	public const USERNAME = 'username';
	public const TEXTAREA = 'textarea';
	public const TEXT = 'text';
	public const STRING = 'string';
	public const SAFE_TEXT = 'safe_text';
	public const SAFE_STR = 'safe_text';
	public const SAFE_HTML = 'safe_text';
	public const STR_ARR = 'array-string';
	public const STRINGS = 'strings';
	public const PASSWORD = 'password';
	public const COLOR = 'color';
	public const HEX_COLOR = 'hex_color';
	public const HEX_COLOR_NO_HASH = 'hex_color_no_hash';
	public const KEY = 'key';

	/**
	 * Default Nonce Action.
	 *
	 * @var string
	 */
	protected string $nonce_action = 'storeengine_nonce';

	/**
	 * Request namespace.
	 *
	 * @var string
	 */
	protected string $namespace = STOREENGINE_PLUGIN_SLUG;

	/**
	 * Action registry.
	 *
	 * Maps action names to permission, schema, and callback definitions.
	 *
	 * Structure:
	 * [
	 *   'action_name' => [
	 *     'capability'           => 'manage_options',
	 *     'allow_visitor_action' => false,
	 *     'callback'             => [ $this, 'method_name' ],
	 *     'fields'               => [ ...schema... ],
	 *   ],
	 * ]
	 *
	 * @var array<string, array>
	 */
	protected array $actions = array();

	protected static string $current_wp_action;

	protected ?bool $is_ajax = null;

	protected ?bool $is_admin_post = null;

	protected ?bool $is_unauthenticated = null;

	protected ?bool $is_visitor_action = null;

	private array $safe_text_kses_rules = array(
		'u'    => true,
		'i'    => true,
		'b'    => true,
		'br'   => true,
		'hr'   => true,
		'img'  => [
			'alt'   => true,
			'class' => true,
			'src'   => true,
			'title' => true,
		],
		'p'    => [
			'class' => true,
		],
		'ul'   => [
			'class' => true,
		],
		'li'   => [
			'class' => true,
		],
		'span' => [
			'class' => true,
			'title' => true,
		],
		'a'    => [
			'class'    => true,
			'title'    => true,
			'href'     => true,
			'target'   => true,
			'rel'      => true,
			'download' => true,
		],
	);

	abstract public function __construct();

	/**
	 * Register WordPress hooks for the defined actions.
	 *
	 * Implementations should bind `handle_request()` to the appropriate
	 * WordPress action hooks (wp_ajax_*, admin_post_*).
	 *
	 * Example:
	 * ```
	 * add_action( 'wp_ajax_storeengine/update_data', [ $this, 'handle_request' ] );
	 * ```
	 *
	 * @return void
	 */
	abstract public function dispatch_actions();

	protected function is_ajax_request(): bool {
		if ( null === $this->is_ajax ) {
			$this->is_ajax = str_starts_with( static::$current_wp_action, 'wp_ajax_' );
		}

		return $this->is_ajax;
	}

	protected function is_admin_post_request(): bool {
		if ( null === $this->is_admin_post ) {
			$this->is_admin_post = str_starts_with( static::$current_wp_action, 'admin_post_' );
		}

		return $this->is_admin_post;
	}

	protected function is_unauthenticated_request(): bool {
		if ( null === $this->is_unauthenticated ) {
			$this->is_unauthenticated =
				( $this->is_ajax_request() || $this->is_admin_post_request() ) &&
				str_contains( static::$current_wp_action, '_nopriv_' );
		}

		return $this->is_unauthenticated;
	}

	/**
	 * Main request entry point.
	 *
	 * This method:
	 * - Detects current WordPress action
	 * - Disables caching
	 * - Prepares and validates payload
	 * - Executes the mapped callback
	 * - Sends success or error response
	 *
	 * All exceptions are normalized into WP_Error responses.
	 *
	 * @return void
	 */
	public function handle_request() {
		try {
			static::$current_wp_action = wp_unslash( current_action() );
			// No caching Please.
			Caching::nocache_headers();

			$response = $this->prepare_response();

			if ( $response && is_wp_error( $response ) ) {
				$this->respond_error( $response );
			}

			$this->respond_success( $response );
		} catch ( StoreEngineException $e ) {
			$this->respond_error( $e->toWpError() );
		} catch ( Throwable $e ) {
			Helper::log_error( $e );
			$this->respond_error(
				new WP_Error(
					'something-went-wrong',
					sprintf(
					// translators: %s. Exception (error) message.
						__( 'Something went wrong. Error: %s', 'storeengine' ),
						wp_strip_all_tags( $e->getMessage() )
					),
					[
						'code' => $e->getCode(),
						'line' => $e->getLine(),
						'file' => $e->getFile(),
					]
				)
			);
		}
	}

	/**
	 * Prepare error response.
	 *
	 * @param WP_Error $response
	 *
	 * @return void
	 */
	protected function respond_error( WP_Error $response ) {
		if ( $this->is_ajax_request() ) {
			$data = $response->get_error_data();
			wp_send_json_error( $response, $data['code'] ?? 400 );
		} else {
			wp_die( $response ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Prepare success response.
	 *
	 * @param $response
	 *
	 * @return void
	 */
	protected function respond_success( $response ) {
		if ( $response ) {
			if ( $this->is_ajax_request() ) {
				wp_send_json_success( $response );
			} elseif ( is_string( $response ) && Helper::is_url( $response ) && Helper::is_valid_site_url( $response ) ) {
				wp_safe_redirect( $response );
				die(); // don't use wp_die...
			} else {
				// @XXX maybe another handler or just void.
				wp_die( '', '', [ 'response' => 200 ] );
			}
		}
	}

	/**
	 * Prepare response for the request.
	 *
	 * @return WP_Error|array|stdClass|string
	 * @throws StoreEngineException
	 */
	protected function prepare_response() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		$action = explode( $this->namespace . '/', $action )[1];

		if ( ! isset( $this->actions[ $action ] ) ) {
			return new WP_Error(
				'invalid_action',
				__( 'Invalid action.', 'storeengine' ),
				[
					'status' => 400,
					'title'  => __( 'Invalid action.', 'storeengine' ),
				]
			);
		}

		$details = $this->actions[ $action ];
		$nonce   = isset( $_REQUEST['security'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['security'] ) ) : '';

		if ( empty( $nonce ) && isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}

		if ( ! $nonce || ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Invalid nonce.', 'storeengine' ),
				[
					'status' => rest_authorization_required_code(),
					'title'  => __( 'Invalid nonce.', 'storeengine' ),
				]
			);
		}

		$user_cap                = ! empty( $details['capability'] ) ? (string) $details['capability'] : '';
		$this->is_visitor_action = isset( $details['allow_visitor_action'] ) && $details['allow_visitor_action'];
		$has_permission          = $this->check_permission( $user_cap, $this->is_visitor_action );

		if ( is_wp_error( $has_permission ) ) {
			return $has_permission;
		}

		/**
		 * Secondary authorization gate.
		 *
		 * Runs AFTER the base capability + nonce checks pass. A handler declares a
		 * single coarse `capability` (usually `manage_options`) per action, which
		 * cannot express per-user, per-action policy. This filter lets addons apply
		 * that finer authorization — notably the Role & Permission addon mapping a
		 * staff user's granted permissions onto individual actions so a view-only
		 * user can't invoke writes (mark-as-paid, status change, refunds, …).
		 *
		 * The full handler instance is passed so listeners can disambiguate
		 * identically named actions across different handler classes (e.g. `import`,
		 * `settings`, `delete` exist in several). Return a WP_Error to deny.
		 *
		 * @param true|WP_Error $authorized Current decision (true = allowed).
		 * @param array         $context    {
		 *     @type string                 $action     Action name (namespace-stripped).
		 *     @type AbstractRequestHandler $handler    The dispatching handler instance.
		 *     @type string                 $namespace  Handler namespace.
		 *     @type string                 $capability Declared capability for the action.
		 *     @type bool                   $is_visitor Whether the action allows visitors.
		 *     @type array                  $details    Full action definition.
		 * }
		 */
		$authorized = apply_filters( 'storeengine/request/authorize', true, [
			'action'     => $action,
			'handler'    => $this,
			'namespace'  => $this->namespace,
			'capability' => $user_cap,
			'is_visitor' => $this->is_visitor_action,
			'details'    => $details,
		] );

		if ( is_wp_error( $authorized ) ) {
			return $authorized;
		}

		if ( empty( $details['callback'] ) || ! is_callable( $details['callback'] ) ) {
			return new WP_Error(
				'not_implemented',
				__( 'Requested method not implemented.', 'storeengine' ),
				[
					'status' => 501,
					'title'  => __( 'Not implemented!', 'storeengine' ),
				]
			);
		}

		return $this->respond( $details['callback'], $this->prepare_payload( $details['fields'] ?? null ) );
	}

	/**
	 * Prepare and sanitize request payload using a declarative schema.
	 *
	 * This method:
	 * - Iterates over defined fields
	 * - Recursively sanitizes input
	 * - Applies validation rules
	 * - Supports nested and repeated fields
	 *
	 * Invalid values are silently discarded (returned as null).
	 *
	 * @param array|null $fields Schema definition from `$actions`.
	 *
	 * @return array Sanitized payload ready for callback consumption.
	 * @throws StoreEngineException
	 */
	protected function prepare_payload( ?array $fields = null ): array {
		$payload = [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce verified before this function call.
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return $payload;
		}

		foreach ( $fields as $key => $schema ) {
			if ( ! isset( $_REQUEST[ $key ] ) ) {
				continue;
			}

			$payload[ $key ] = $this->sanitize_by_schema( wp_unslash( $_REQUEST[ $key ] ), $schema, $key ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input sanitized inside this function.
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $payload;
	}

	/**
	 * Recursively sanitize data based on schema definition.
	 *
	 * Supported schema shapes:
	 *
	 * 1. Scalar:
	 *    'title' => 'string|min:3|max:100'
	 *
	 * 2. Nested object:
	 *    'meta' => [
	 *      'color' => 'hex_color',
	 *      'size'  => 'int|min:1',
	 *    ]
	 *
	 * 3. Repeated fields:
	 *    'features' => [
	 *      [
	 *        'title' => 'string',
	 *        'cost'  => 'float',
	 *      ]
	 *    ]
	 *
	 * @param mixed $value Raw request value.
	 * @param mixed $schema Field schema definition.
	 *
	 * @return mixed Sanitized value.
	 * @throws StoreEngineException
	 */
	protected function sanitize_by_schema( $value, $schema, string $path = '' ) {
		// Repeated fields (numeric array schema)
		if ( is_array( $schema ) && array_is_list( $schema ) ) {

			$item_schema = $schema[0] ?? null;

			if ( ! is_array( $value ) ) {
				return [];
			}

			$minItems = $schema['min_items'] ?? null;
			$maxItems = $schema['max_items'] ?? null;

			if ( null !== $minItems && count( $value ) < (int) $minItems ) {
				throw new StoreEngineException(
					esc_html(
						sprintf(
						// translators: %1$s: Field name/path. %2$d. Minimum (count) item required.
							__( '%1$s must contain at least %2$d items.', 'storeengine' ),
							$path,
							$minItems
						)
					),
					'min_items_failed',
					400
				);
			}

			if ( null !== $maxItems && count( $value ) > (int) $maxItems ) {
				throw new StoreEngineException(
					esc_html(
						sprintf(
						// translators: %1$s: Field name/path. %2$d. Max (count) item allowed.
							__( '%1$s must not exceed %2$d items.', 'storeengine' ),
							$path,
							$maxItems
						)
					),
					'max_items_failed',
					400
				);
			}

			$result = [];
			foreach ( $value as $index => $item ) {
				$result[ $index ] = $this->sanitize_by_schema( $item, $item_schema, $path . '[' . $index . ']' );
			}

			return $result;
		}

		// Support schema array with 'rules' and optional 'label'
		if ( is_string( $schema ) ) {
			return $this->sanitize_scalar( $value, $schema, $path );
		}

		if ( is_array( $schema ) && isset( $schema['rules'] ) ) {

			$label    = $schema['label'] ?? $path;
			$rules    = (string) $schema['rules'];
			$nullable = ! empty( $schema['nullable'] );
			$required = str_contains( $rules, 'required' );
			$castOnly = ! empty( $schema['cast_only'] );
			$message  = $schema['message'] ?? null; // fallback
			$messages = $schema['messages'] ?? [];

			// Custom sanitizer callback
			if ( isset( $schema['sanitize'] ) && is_callable( $schema['sanitize'] ) ) {
				$value = call_user_func( $schema['sanitize'], $value, $path );
			}

			// Handle missing value
			if ( $value === null || $value === '' ) {
				if ( array_key_exists( 'default', $schema ) ) {
					return $schema['default'];
				}

				if ( $nullable ) {
					return null;
				}

				if ( $required ) {
					throw new StoreEngineException(
						esc_html(
							$messages['required'] ??
							$message ??
							sprintf(
							// translators: %s. Field name/label.
								__( '%s is required.', 'storeengine' ),
								$label
							)
						),
						'required_field_missing',
						400
					);
				}

				return null;
			}

			$sanitized = $this->sanitize_scalar(
				$value,
				$rules,
				$path,
				$label,
				$message,
				$castOnly,
				$messages
			);

			// Custom validator callback
			if ( isset( $schema['validate'] ) && is_callable( $schema['validate'] ) ) {
				$result = call_user_func( $schema['validate'], $sanitized, $path );
				if ( $result !== true ) {
					throw new StoreEngineException(
						esc_html( $message ?: ( is_string( $result ) ? $result : __( 'Validation failed.', 'storeengine' ) ) ),
						'custom_validation_failed',
						400
					);
				}
			}

			return $sanitized;
		}

		// Nested object
		if ( is_array( $schema ) && is_array( $value ) ) {
			$result = [];
			foreach ( $schema as $field => $field_schema ) {
				if ( isset( $value[ $field ] ) ) {
					$result[ $field ] = $this->sanitize_by_schema( $value[ $field ], $field_schema, $path ? $path . '.' . $field : $field );
				}
			}

			return $result;
		}

		return null;
	}

	/**
	 * Sanitize and validate scalar values.
	 *
	 * Supports extended rule syntax using pipe separators.
	 *
	 * Supported behaviors:
	 * - required      → field must be present and non-empty
	 * - nullable      → allows null values
	 * - default       → applied when value is missing
	 * - cast-only     → sanitize & cast without validation
	 *
	 * Examples:
	 * - string|min:3|max:50|required
	 * - float|min:0
	 * - string|enum:draft,published
	 * - slug|regex:/^[a-z0-9-]+$/
	 *
	 * @param mixed $value
	 * @param string $type
	 * @param string $field_path
	 * @param string|null $custom_label
	 * @param string|null $custom_message
	 * @param bool $cast_only
	 * @param array $rule_messages
	 *
	 * @return mixed
	 * @throws StoreEngineException
	 */
	protected function sanitize_scalar(
		$value,
		string $type,
		string $field_path,
		?string $custom_label = null,
		?string $custom_message = null,
		bool $cast_only = false,
		array $rule_messages = []
	) {
		if ( is_callable( $type ) ) {
			return call_user_func_array( $type, [ $value, $custom_label, $custom_message, $cast_only ] );
		}

		$rules = array_map( 'trim', explode( '|', $type ) );
		$base  = strtolower( array_shift( $rules ) );

		// @TODO add support for file.

		// --- Sanitize first ---
		switch ( $base ) {
			case self::ID:
			case self::ABSINT:
			case self::ABS_INTEGER:
			case 'absint':
			case 'id':
				$value = absint( sanitize_text_field( $value ) );
				break;

			case self::ID_ARR:
			case self::IDS:
			case 'array-id':
			case 'id-array':
			case 'ids':
				$value = is_string( $value ) ? explode( ',', $value ) : $value;
				$value = array_map( fn( $id ) => is_scalar( $id ) ? absint( $id ) : null, $value );
				$value = array_unique( array_filter( $value ) );
				break;

			case self::INT:
			case self::INTEGER:
			case 'int':
			case 'integer':
				$value = intval( sanitize_text_field( $value ) );
				break;

			case self::DOUBLE:
			case self::FLOAT:
			case 'double':
			case 'float':
				$value = floatval( sanitize_text_field( $value ) );
				break;

			case self::ABS_DOUBLE:
			case self::ABS_FLOAT:
			case 'abs-double':
			case 'abs-float':
				$value = abs( floatval( sanitize_text_field( $value ) ) );
				break;

			case self::URL:
			case 'url':
				$value = sanitize_url( $value );
				break;

			case self::BOOLEAN:
			case 'bool':
			case 'boolean':
				$value = Formatting::string_to_bool( $value );
				break;

			case self::POST:
			case self::HTML:
			case 'post':
				$value = wp_kses_post( $value );
				break;

			case self::SLUG:
			case 'slug':
				$value = sanitize_title( $value );
				break;

			case self::EMAIL:
			case 'email':
				$value = sanitize_email( $value );
				break;

			case self::USER:
			case self::USERNAME:
			case 'user':
				$value = sanitize_user( $value, str_contains( $type, 'strict' ) );
				break;

			case self::TEXTAREA:
			case 'textarea':
				$value = sanitize_textarea_field( $value );
				break;

			case self::TEXT:
			case self::STRING:
			case 'text':
			case 'string':
				$value = sanitize_text_field( $value );
				break;

			case self::SAFE_TEXT:
			case self::SAFE_STR:
			case self::SAFE_HTML:
			case 'safe_text':
				$value = wp_kses( force_balance_tags( stripslashes( $value ) ), $this->safe_text_kses_rules );
				break;

			case self::KEY:
			case 'key':
				$value = sanitize_key( $value );
				break;

			case self::STR_ARR:
			case self::STRINGS:
			case 'array-string':
			case 'strings':
				$value = is_string( $value ) ? explode( ',', $value ) : $value;
				$value = array_map( fn( $s ) => is_scalar( $s ) ? trim( sanitize_text_field( $s ) ) : null, $value );
				$value = array_unique( array_filter( $value ) );
				break;

			case self::PASSWORD:
			case 'password':
				// Do not apply sanitizer or strip-slashes as passwords can contain special characters.
				$value = trim( $value );
				break;

			case self::COLOR:
			case self::HEX_COLOR:
			case 'color':
			case 'hex_color':
				$value = sanitize_hex_color( $value );
				break;

			case self::HEX_COLOR_NO_HASH:
			case 'hex_color_no_hash':
				$value = sanitize_hex_color_no_hash( $value );
				break;

			default:
				if ( is_callable( $base ) ) {
					$value = call_user_func( $base, $value );
				} else {
					$value = is_array( $value ) ? wp_kses_post_deep( map_deep( $value, 'trim' ) ) : wp_kses_post( trim( $value ) );
				}
				break;
		}

		// --- Apply validation rules ---
		if ( ! $cast_only ) {
			$field_label = $custom_label ?: ucwords( str_replace( [ '.', '_' ], ' ', $field_path ) );

			$get_message = function ( string $rule_key, ?string $fallback = null ) use ( $rule_messages, $custom_message, $field_label ) {
				if ( isset( $rule_messages[ $rule_key ] ) ) {
					return sprintf( $rule_messages[ $rule_key ], $field_label );
				}

				if ( $custom_message ) {
					return $custom_message;
				}

				return $fallback;
			};

			foreach ( $rules as $rule ) {
				// enum:a,b,c
				if ( str_starts_with( $rule, 'enum:' ) ) {
					$allowed = array_map( 'trim', explode( ',', substr( $rule, 5 ) ) );
					if ( ! in_array( (string) $value, $allowed, true ) ) {
						throw new StoreEngineException(
							esc_html(
								$get_message(
									'enum',
									sprintf(
									// translators: %1$s: Field label, %2$s: allowed items.
										__( '%1$s must be one of: %2$s', 'storeengine' ),
										$field_label,
										Helper::implode_with( $allowed, 'or' )
									)
								)
							),
							'invalid_enum',
							400
						);
					}
				}

				// min:3
				if ( str_starts_with( $rule, 'min:' ) ) {
					$min = (int) substr( $rule, 4 );
					if (
						( is_string( $value ) && mb_strlen( $value ) < $min ) ||
						( is_numeric( $value ) && $value < $min )
					) {
						throw new StoreEngineException(
							esc_html(
								$get_message(
									'min',
									sprintf(
									// translators: %1$s: Field label, %2$s: Min required characters.
										__( '%1$s must be at least %2$d characters.', 'storeengine' ),
										$field_label,
										$min
									)
								)
							),
							'min_validation_failed',
							400
						);
					}
				}

				// max:20
				if ( str_starts_with( $rule, 'max:' ) ) {
					$max = (int) substr( $rule, 4 );
					if (
						( is_string( $value ) && mb_strlen( $value ) > $max ) ||
						( is_numeric( $value ) && $value > $max )
					) {
						throw new StoreEngineException(
							esc_html(
								$get_message(
									'max',
									sprintf(
									// translators: %1$s: Field label, %2$s: Max allowed characters.
										__( '%1$s must not exceed %2$d', 'storeengine' ),
										$field_label,
										$max
									)
								)
							),
							'max_validation_failed',
							400
						);
					}
				}

				// regex:/pattern/
				if ( str_starts_with( $rule, 'regex:' ) ) {
					$pattern = substr( $rule, 6 );
					if ( is_string( $value ) && @preg_match( $pattern, $value ) !== 1 ) {
						throw new StoreEngineException(
							esc_html(
								$get_message(
									'regex',
									sprintf(
									// translators: %s: Field label.
										__( '%s format is invalid.', 'storeengine' ),
										$field_label
									)
								)
							),
							'regex_validation_failed',
							400
						);
					}
				}
			}
		}

		return $value;
	}

	/**
	 * Execute action callback safely.
	 *
	 * Any thrown exception is converted into a StoreEngineException
	 * and later returned as a WP_Error response.
	 *
	 * @param callable|string|array $callback Action callback.
	 * @param array $payload Sanitized payload.
	 *
	 * @return WP_Error|array|stdClass|string
	 *
	 * @throws StoreEngineException
	 */
	final protected function respond( $callback, array $payload ) {
		try {
			return call_user_func( $callback, $payload );
		} catch ( StoreEngineException $e ) {
			throw $e;
		} catch ( Throwable $e ) {
			throw StoreEngineException::convert_exception( $e );// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * Check user permission for the current action.
	 *
	 * Visitor actions may bypass login checks when explicitly allowed.
	 *
	 * @param string $capability Required capability.
	 * @param bool $allow_visitors Whether unauthenticated users are allowed.
	 *
	 * @return true|WP_Error True on success, WP_Error otherwise.
	 */
	protected function check_permission( string $capability, bool $allow_visitors = false ) {
		if ( ( ! is_user_logged_in() && ! $allow_visitors ) || ( is_user_logged_in() && $capability && ! current_user_can( $capability ) ) ) {
			return new WP_Error(
				'forbidden_action',
				__( 'You do not have permission to access this page.', 'storeengine' ),
				[
					'status' => rest_authorization_required_code(),
					'title'  => __( 'Insufficient permission!', 'storeengine' ),
				]
			);
		}

		return true;
	}

	/**
	 * Export action schema to OpenAPI-compatible structure.
	 * Generate Schema for frontend devs to use.
	 *
	 * @param string $action
	 *
	 * @return array|null
	 */
	public function export_openapi_schema( ?string $action = null ): ?array {
		if ( ! $action ) {
			$actions = array_keys( $this->actions );

			return [
				'type'       => 'object',
				'namespace'  => get_class( $this ),
				'properties' => array_combine(
					array_map( fn( $a ) => $this->namespace . '/' . $a, $actions ),
					array_map( [ $this, 'export_openapi_schema' ], $actions )
				),
			];
		}

		$fields = $this->actions[ $action ]['fields'] ?? null;

		return [
			'type'       => 'object',
			'methods'    => [ 'POSTS' ],
			'is_public'  => isset( $this->actions[ $action ]['allow_visitor_action'] ) && $this->actions[ $action ]['allow_visitor_action'],
			'capability' => $this->actions[ $action ]['capability'] ?? 'all',
			'namespace'  => $this->namespace . '/' . $action,
			'properties' => $fields ? $this->build_openapi_properties( $fields ) : null,
		];
	}

	/**
	 * Build OpenAPI properties recursively.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	protected function build_openapi_properties( array $fields ): array {
		return array_map( fn( $schema ) => $this->convert_schema_to_openapi( $schema ), $fields );
	}

	/**
	 * Convert internal schema to OpenAPI format.
	 *
	 * @param mixed $schema
	 *
	 * @return array
	 */
	protected function convert_schema_to_openapi( $schema ): array {

		// Repeated field
		if ( is_array( $schema ) && array_is_list( $schema ) ) {
			return [
				'type'     => 'array',
				'items'    => $this->convert_schema_to_openapi( $schema[0] ),
				'minItems' => $schema['min_items'] ?? null,
				'maxItems' => $schema['max_items'] ?? null,
			];
		}

		// Scalar with rules
		if ( is_array( $schema ) && isset( $schema['rules'] ) ) {
			$rules = explode( '|', $schema['rules'] );
			$type  = array_shift( $rules );

			$openapi = [
				'type'        => in_array( $type, [ 'int', 'absint', 'float', 'double' ], true ) ? 'number' : 'string',
				'description' => $schema['label'] ?? '',
			];

			foreach ( $rules as $rule ) {
				if ( str_starts_with( $rule, 'enum:' ) ) {
					$openapi['enum'] = explode( ',', substr( $rule, 5 ) );
				}
				if ( str_starts_with( $rule, 'min:' ) ) {
					$openapi['minLength'] = (int) substr( $rule, 4 );
				}
				if ( str_starts_with( $rule, 'max:' ) ) {
					$openapi['maxLength'] = (int) substr( $rule, 4 );
				}
			}

			if ( array_key_exists( 'default', $schema ) ) {
				$openapi['default'] = $schema['default'];
			}

			return $openapi;
		}

		// Nested object
		if ( is_array( $schema ) ) {
			return [
				'type'       => 'object',
				'properties' => $this->build_openapi_properties( $schema ),
			];
		}

		return [ 'type' => 'string' ];
	}
}

// End of file abstract-request-handler.php.
