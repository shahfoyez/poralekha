<?php

namespace ABlocks\Classes;

use Exception;
use stdClass;
use ABlocks\Classes\Exceptions\AblocksException;
use ABlocks\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractRequestHandler {
	/**
	 * Default Nonce Action.
	 *
	 * @var string
	 */
	protected string $nonce_action = 'ablocks_nonce';

	/**
	 * Request namespace.
	 *
	 * @var string
	 */
	protected $namespace = ABLOCKS_PLUGIN_SLUG;

	/**
	 * Actions to handle.
	 *
	 * @var array
	 */
	protected array $actions = array();

	protected static string $current_wp_action;

	protected ?bool $is_ajax = null;

	protected ?bool $is_unauthenticated = null;

	private array $safe_text_kses_rules = array(
		'br'   => true,
		'img'  => array(
			'alt'   => true,
			'class' => true,
			'src'   => true,
			'title' => true,
		),
		'p'    => array(
			'class' => true,
		),
		'span' => array(
			'class' => true,
			'title' => true,
		),
	);

	abstract public function __construct();

	/**
	 * Run action hook.
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

	protected function is_unauthenticated_request(): bool {
		if ( null === $this->is_unauthenticated ) {
			$this->is_unauthenticated = ( str_starts_with( static::$current_wp_action, 'wp_ajax_' ) || str_starts_with( static::$current_wp_action, 'admin_post_' ) ) && str_contains( static::$current_wp_action, '_nopriv_' );
		}

		return $this->is_unauthenticated;
	}

	/**
	 * Handle action callback.
	 *
	 * @return void
	 */
	final public function handle_request() {
		try {
			static::$current_wp_action = wp_unslash( current_action() );
			// No caching.
			nocache_headers();

			$response = $this->prepare_response();
			if ( $response && is_wp_error( $response ) ) {
				$this->respond_error( $response );
			}

			$this->respond_success( $response );
		} catch ( AblocksException $e ) {
			$this->respond_error( $e->toWpError() );
		} catch ( Exception $e ) {
			$this->respond_error( new WP_Error( 'something-went-wrong', $e->getMessage(), [ 'code' => 500 ] ) );
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
			} elseif ( is_string( $response ) && Helper::is_valid_site_url( $response ) ) {
				wp_safe_redirect( $response );
				die(); // don't use wp_die...
			} else {
				// @XXX maybe another handler or just void.
				wp_die( '', '', [ 'response' => null ] );
			}
		}
	}

	/**
	 * Prepare response for the request.
	 *
	 * @return WP_Error|array|stdClass|string
	 * @throws AblocksException
	 * @throws Exception
	 */
	protected function prepare_response() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		$action = explode( $this->namespace . '/', $action )[1];

		if ( ! isset( $this->actions[ $action ] ) ) {
			return new WP_Error(
				'invalid_action',
				__( 'Invalid action.', 'ablocks' ),
				[
					'status' => 400,
					'title'  => __( 'Invalid action.', 'ablocks' ),
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
				__( 'Invalid nonce.', 'ablocks' ),
				[
					'status' => rest_authorization_required_code(),
					'title'  => __( 'Invalid nonce.', 'ablocks' ),
				]
			);
		}

		$user_cap       = ! empty( $details['capability'] ) ? (string) $details['capability'] : 'manage_options';
		$allow_visitor  = ! empty( $details['allow_visitor_action'] ) && (bool) $details['allow_visitor_action'];
		$has_permission = $this->check_permission( $user_cap, $allow_visitor );

		if ( is_wp_error( $has_permission ) ) {
			return $has_permission;
		}

		if ( empty( $details['callback'] ) || ! is_callable( $details['callback'] ) ) {
			return new WP_Error(
				'not_implemented',
				__( 'Requested method not implemented.', 'ablocks' ),
				[
					'status' => 501,
					'title'  => __( 'Not implemented!', 'ablocks' ),
				]
			);
		}

		$fields = $details['fields'] ?? null;

		$payload = [];

		if ( is_array( $fields ) && ! empty( $fields ) ) {
			foreach ( $fields as $key => $type ) {
				if ( ! empty( $_REQUEST[ $key ] ) ) {
					if ( is_array( $type ) ) {
						foreach ( $type as $type_key => $type_value ) {
							if ( ! empty( $_REQUEST[ $key ][ $type_key ] ) ) {
								if ( is_array( $type_value ) ) {
									foreach ( $type_value as $type_value_key => $type_value_value ) {
										if ( ! empty( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) ) {
											$decode3_type = null;

											if ( str_contains( $type_value_value, '|' ) ) {
												list( $decode3_type, $type_value_value ) = explode( '|', $type_value_value, 2 );
											}

											switch ( strtolower( $type_value_value ) ) {
												case 'absint':
												case 'id':
													$payload[ $key ][ $type_key ][ $type_value_key ] = absint( sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) ) );
													break;
												case 'int':
												case 'integer':
													$payload[ $key ][ $type_key ][ $type_value_key ] = intval( sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) ) );
													break;
												case 'double':
												case 'float':
													$payload[ $key ][ $type_key ][ $type_value_key ] = floatval( sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) ) );
													break;
												case 'url':
													$payload[ $key ][ $type_key ][ $type_value_key ] = esc_url_raw( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'bool':
												case 'boolean':
													$payload[ $key ][ $type_key ][ $type_value_key ] = (bool) filter_var( sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) ), FILTER_VALIDATE_BOOLEAN );
													break;
												case 'post':
													$payload[ $key ][ $type_key ][ $type_value_key ] = wp_kses_post( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'slug':
													$payload[ $key ][ $type_key ][ $type_value_key ] = sanitize_title( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'email':
													$payload[ $key ][ $type_key ][ $type_value_key ] = sanitize_email( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'user':
													$payload[ $key ][ $type_key ][ $type_value_key ] = sanitize_user( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'textarea':
													$payload[ $key ][ $type_key ][ $type_value_key ] = sanitize_textarea_field( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'text':
												case 'string':
													$payload[ $key ][ $type_key ][ $type_value_key ] = sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'json':
													// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
													$payload[ $key ][ $type_key ][ $type_value_key ] = Sanitizer::sanitize_json_form_data( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] );
													break;
												case 'hex_color':
													$payload[ $key ][ $type_key ][ $type_value_key ] = sanitize_hex_color( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'hex_color_no_hash':
													$payload[ $key ][ $type_key ][ $type_value_key ] = sanitize_hex_color_no_hash( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'key':
													$payload[ $key ][ $type_key ][ $type_value_key ] = sanitize_key( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) );
													break;
												case 'safe_text':
													// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
													$payload[ $key ][ $type_key ][ $type_value_key ] = wp_kses( force_balance_tags( stripslashes( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) ) ), $this->safe_text_kses_rules );
													break;
												default:
													if ( is_array( $payload[ $key ][ $type_key ][ $type_value_key ] ) ) {
														$payload[ $key ][ $type_key ][ $type_value_key ] = wp_kses_post_deep( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
													} else {
														$payload[ $key ][ $type_key ][ $type_value_key ] = wp_kses_post( trim( wp_unslash( $_REQUEST[ $key ][ $type_key ][ $type_value_key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
													}
													break;
											}//end switch

											if ( $decode3_type && ! empty( $payload[ $key ][ $type_key ][ $type_value_key ] ) ) {
												$payload[ $key ] = $this->maybe_decode( $payload[ $key ][ $type_key ][ $type_value_key ], $decode3_type );
											}
										}//end if
									}//end foreach
								} else {
									$decode2_type = null;

									if ( str_contains( $type_value, '|' ) ) {
										list( $decode2_type, $type_value ) = explode( '|', $type_value, 2 );
									}

									switch ( strtolower( $type_value ) ) {
										case 'absint':
										case 'id':
											$payload[ $key ][ $type_key ] = absint( sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) ) );
											break;
										case 'int':
										case 'integer':
											$payload[ $key ][ $type_key ] = intval( sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) ) );
											break;
										case 'double':
										case 'float':
											$payload[ $key ][ $type_key ] = floatval( sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) ) );
											break;
										case 'url':
											$payload[ $key ][ $type_key ] = esc_url_raw( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'bool':
										case 'boolean':
											$payload[ $key ][ $type_key ] = (bool) filter_var( sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) ), FILTER_VALIDATE_BOOLEAN );
											break;
										case 'post':
											$payload[ $key ][ $type_key ] = wp_kses_post( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'slug':
											$payload[ $key ][ $type_key ] = sanitize_title( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'email':
											$payload[ $key ][ $type_key ] = sanitize_email( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'user':
											$payload[ $key ][ $type_key ] = sanitize_user( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'textarea':
											$payload[ $key ][ $type_key ] = sanitize_textarea_field( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'text':
										case 'string':
											$payload[ $key ][ $type_key ] = sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'json':
											// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
											$payload[ $key ][ $type_key ] = Sanitizer::sanitize_json_form_data( $_REQUEST[ $key ][ $type_key ] );
											break;
										case 'hex_color':
											$payload[ $key ][ $type_key ] = sanitize_hex_color( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'hex_color_no_hash':
											$payload[ $key ][ $type_key ] = sanitize_hex_color_no_hash( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'key':
											$payload[ $key ][ $type_key ] = sanitize_key( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) );
											break;
										case 'safe_text':
											// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
											$payload[ $key ][ $type_key ] = wp_kses( force_balance_tags( stripslashes( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) ) ), $this->safe_text_kses_rules );
											break;
										default:
											if ( is_array( $payload[ $key ][ $type_key ] ) ) {
												$payload[ $key ][ $type_key ] = wp_kses_post_deep( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
											} else {
												$payload[ $key ][ $type_key ] = wp_kses_post( trim( wp_unslash( $_REQUEST[ $key ][ $type_key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
											}
											break;
									}//end switch

									if ( $decode2_type && ! empty( $payload[ $key ][ $type_key ] ) ) {
										$payload[ $key ] = $this->maybe_decode( $payload[ $key ][ $type_key ], $decode2_type );
									}
								}//end if
							}//end if
						}//end foreach
					} else {
						$decode_type = null;

						if ( str_contains( $type, '|' ) ) {
							list( $decode_type, $type ) = explode( '|', $type, 2 );
						}

						switch ( strtolower( $type ) ) {
							case 'absint':
							case 'id':
								$payload[ $key ] = absint( sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) );
								break;
							case 'int':
							case 'integer':
								$payload[ $key ] = intval( sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) );
								break;
							case 'double':
							case 'float':
								$payload[ $key ] = floatval( sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) );
								break;
							case 'url':
								$payload[ $key ] = esc_url_raw( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'bool':
							case 'boolean':
								$payload[ $key ] = (bool) filter_var( sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ), FILTER_VALIDATE_BOOLEAN );
								break;
							case 'post':
								$payload[ $key ] = wp_kses_post( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'slug':
								$payload[ $key ] = sanitize_title( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'email':
								$payload[ $key ] = sanitize_email( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'user':
								$payload[ $key ] = sanitize_user( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'textarea':
								$payload[ $key ] = sanitize_textarea_field( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'text':
							case 'string':
								$payload[ $key ] = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'hex_color':
								$payload[ $key ] = sanitize_hex_color( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'hex_color_no_hash':
								$payload[ $key ] = sanitize_hex_color_no_hash( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'key':
								$payload[ $key ] = sanitize_key( wp_unslash( $_REQUEST[ $key ] ) );
								break;
							case 'safe_text':
								// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
								$payload[ $key ] = wp_kses( force_balance_tags( stripslashes( wp_unslash( $_REQUEST[ $key ] ) ) ), $this->safe_text_kses_rules );
								break;
							case 'array-string':
								$payload[ $key ] = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST[ $key ] ) );
								break;
							default:
								if ( is_array( $_REQUEST[ $key ] ) ) {
									$payload[ $key ] = wp_kses_post_deep( wp_unslash( $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
								} else {
									$payload[ $key ] = wp_kses_post( trim( wp_unslash( $_REQUEST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
								}
								break;
						}//end switch

						if ( $decode_type && ! empty( $payload[ $key ] ) ) {
							$payload[ $key ] = $this->maybe_decode( $payload[ $key ], $decode_type );
						}
					}//end if
				}//end if
			}//end foreach
		}//end if

		return $this->respond( $details['callback'], $payload );
	}

	protected function maybe_decode( $payload, $type ) {
		if ( 'serialize' === $type || 'unserialize' === $type || 'php' === $type ) {
			return maybe_unserialize( $payload );
		}

		if ( str_starts_with( $payload, '[' ) || str_starts_with( $payload, '{' ) ) {
			if ( 'array' === $type ) {
				return json_decode( $payload, true );
			}

			return json_decode( $payload );
		}

		return $payload;
	}

	/**
	 * Run action callback.
	 *
	 * @param array|string $callback
	 * @param array        $payload
	 *
	 * @return WP_Error|array|stdClass|string
	 *
	 * @throws StoreEngineException
	 * @throws Exception
	 */
	final protected function respond( $callback, array $payload ) {
		return call_user_func( $callback, $payload );
	}

	/**
	 * @param string $capability
	 * @param bool   $allow_visitors
	 *
	 * @return WP_Error|true
	 */
	protected function check_permission( string $capability, bool $allow_visitors = false ) {
		if ( ( ! is_user_logged_in() && ! $allow_visitors ) || ( is_user_logged_in() && $capability && ! current_user_can( $capability ) ) ) {
			return new WP_Error(
				'forbidden_action',
				__( 'You do not have permission to access this page.', 'ablocks' ),
				[
					'status' => rest_authorization_required_code(),
					'title'  => __( 'Insufficient permission!', 'ablocks' ),
				]
			);
		}

		return true;
	}
}
