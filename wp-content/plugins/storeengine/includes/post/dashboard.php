<?php

namespace StoreEngine\Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractPostHandler;
use StoreEngine\Classes\Countries;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Validation;
use WP_Error;

class Dashboard extends AbstractPostHandler {
	public function __construct() {
		$fields = [ 'address_type' => 'string' ];
		foreach ( Countries::init()->get_default_address_fields() as $field => $data ) {
			$type = $data['type'] ?? 'string';
			if ( ! $type || 'country' === $field || 'state' === $field || 'tel' === $type ) {
				$type = 'string';
			}

			if ( 'checkbox' === $type ) {
				$type = 'boolean';
			}

			$fields[ 'billing_' . $field ]  = $type;
			$fields[ 'shipping_' . $field ] = $type;
		}

		$this->actions = [
			'frontend_dashboard_change_profile_account_details' => [
				'callback'   => [ $this, 'change_profile_account_details' ],
				'capability' => 'read',
				'fields'     => [
					'first_name' => 'string',
					'last_name'  => 'string',
					'email'      => 'email',
				],
			],
			'frontend_dashboard_change_password' => [
				'callback'   => [ $this, 'change_password' ],
				'capability' => 'read',
				'fields'     => [
					'current_password' => 'string',
					'new_password'     => 'string',
					'confirm_password' => 'string',
				],
			],
			'frontend_dashboard_edit_address'    => [
				'callback'   => [ $this, 'change_address' ],
				'capability' => 'read',
				'fields'     => $fields,
			],
			'frontend_dashboard_save_notifications' => [
				'callback'   => [ $this, 'save_notifications' ],
				'capability' => 'read',
				'fields'     => [
					'marketing'        => 'boolean',
					'vendor_new_order' => 'boolean',
				],
			],
			'frontend_dashboard_request_data_export' => [
				'callback'   => [ $this, 'request_data_export' ],
				'capability' => 'read',
				'fields'     => [],
			],
			'frontend_dashboard_request_account_erasure' => [
				'callback'   => [ $this, 'request_account_erasure' ],
				'capability' => 'read',
				'fields'     => [],
			],
		];
	}

	public function change_profile_account_details( $payload ) {
		$customer = Helper::get_customer();

		if ( empty( $payload['email'] ) ) {
			wp_die( esc_html__( 'Email address is required.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), 400 );
		}

		$customer->set_email( $payload['email'] );

		if ( ! empty( $payload['first_name'] ) ) {
			$customer->set_first_name( $payload['first_name'] );
		}
		if ( ! empty( $payload['last_name'] ) ) {
			$customer->set_last_name( $payload['last_name'] );
		}

		$customer->save();

		wp_safe_redirect( Helper::sanitize_referer_url( wp_get_referer() ) );
	}

	public function change_password( $payload ) {
		$user = wp_get_current_user();

		if ( empty( $payload['current_password'] ) ) {
			wp_die( esc_html__( 'Your password is required.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), 403 );
		}

		if ( ! wp_check_password( $payload['current_password'], $user->user_pass, $user->ID ) ) {
			wp_die( esc_html__( 'Invalid Password!', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), 403 );
		}

		if ( empty( $payload['new_password'] ) || empty( $payload['confirm_password'] ) ) {
			wp_die( esc_html__( 'Both new password & confirm password is required.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), 400 );
		}

		// Check if the new password and confirm password are the same
		if ( $payload['new_password'] !== $payload['confirm_password'] ) {
			wp_die( esc_html__( 'Password Mismatch!', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), 400 );
		}

		wp_update_user( [
			'ID'        => $user->ID,
			'user_pass' => $payload['new_password'],
		] );

		wp_safe_redirect( Helper::sanitize_referer_url( wp_get_referer() ) );
	}

	/**
	 * Persist the customer's email-notification opt-ins as user_meta. Read
	 * at email send-time via Helper::should_send_notification(). Order-status
	 * is intentionally not in the form — it's transactional and always on.
	 */
	public function save_notifications( $payload ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( esc_html__( 'You must be logged in to save preferences.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), 403 );
		}

		$marketing        = ! empty( $payload['marketing'] ) ? '1' : '0';
		$vendor_new_order = ! empty( $payload['vendor_new_order'] ) ? '1' : '0';

		update_user_meta( $user_id, '_storeengine_notif_marketing', $marketing );

		// Only persist the vendor flag for users with the vendor role —
		// avoids polluting customer profiles with a meta they can't toggle.
		$user = wp_get_current_user();
		if ( $user && in_array( 'storeengine_vendor', (array) $user->roles, true ) ) {
			update_user_meta( $user_id, '_storeengine_notif_vendor_new_order', $vendor_new_order );
		}

		wp_safe_redirect(
			add_query_arg( 'notif_saved', '1', Helper::sanitize_referer_url( wp_get_referer() ) )
		);
		exit;
	}

	/**
	 * Trigger WP core's privacy export flow for the current user. WP sends
	 * a confirmation email; once the user confirms, WP cron generates the
	 * ZIP and emails the download link. We don't reimplement any of that.
	 */
	public function request_data_export( $payload ) {
		$user_id = get_current_user_id();
		$user    = $user_id ? get_user_by( 'id', $user_id ) : null;
		if ( ! $user ) {
			wp_die( esc_html__( 'You must be logged in.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), 403 );
		}

		$result = wp_create_user_request( $user->user_email, 'export_personal_data' );
		$msg    = is_wp_error( $result ) ? 'error' : 'export_requested';
		if ( ! is_wp_error( $result ) ) {
			wp_send_user_request( $result );
		}

		wp_safe_redirect(
			add_query_arg( 'privacy_msg', $msg, Helper::sanitize_referer_url( wp_get_referer() ) )
		);
		exit;
	}

	/**
	 * Trigger WP core's account erasure flow. Same shape as the export —
	 * WP handles the confirmation email + cron-driven erasure.
	 */
	public function request_account_erasure( $payload ) {
		$user_id = get_current_user_id();
		$user    = $user_id ? get_user_by( 'id', $user_id ) : null;
		if ( ! $user ) {
			wp_die( esc_html__( 'You must be logged in.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), 403 );
		}

		$result = wp_create_user_request( $user->user_email, 'remove_personal_data' );
		$msg    = is_wp_error( $result ) ? 'error' : 'erasure_requested';
		if ( ! is_wp_error( $result ) ) {
			wp_send_user_request( $result );
		}

		wp_safe_redirect(
			add_query_arg( 'privacy_msg', $msg, Helper::sanitize_referer_url( wp_get_referer() ) )
		);
		exit;
	}

	/**
	 * @param string $field
	 *
	 * @return never-returns
	 */
	private function die_required( string $field ) {
		wp_die(
			sprintf(
			// translators: %s. Field name/label.
				esc_html__( '%s is required.', 'storeengine' ),
				'<strong>' . esc_html( $field ) . '</strong>'
			),
			[
				'code'      => 400,
				'back_link' => true,
			]
		);
	}

	public function change_address( $payload ) {
		$valid_types = [ 'billing', 'shipping' ];

		if ( empty( $payload['address_type'] ) ) {
			$this->die_required( __( 'Address Type', 'storeengine' ) );
		}

		if ( ! in_array( $payload['address_type'], $valid_types, true ) ) {
			wp_die(
				sprintf(
					/* translators: %s: Address type in form data. */
					esc_html__( 'Invalid address type! Address type must be one of billing or shipping, %s given', 'storeengine' ),
					esc_html( $payload['address_type'] )
				),
				esc_html__( 'Error! Invalid request!', 'storeengine' ),
				400
			);
		}

		$address_type = $payload['address_type'];
		$type_label   = ( 'billing' === $address_type ) ? __( 'Billing', 'storeengine' ) : __( 'Shipping', 'storeengine' );
		$country      = $payload[ $address_type . '_country' ] ?? '';

		if ( ! $country ) {
			/* translators: %s Address type (Billing/Shipping) */
			$this->die_required( sprintf( __( '%s Country', 'storeengine' ), $type_label ) );
		}

		$customer = Helper::get_customer();
		$fields   = Countries::init()->get_address_fields( $country, $address_type . '_' );
		$errors   = new WP_Error();

		foreach ( $fields as $key => $field ) {
			if ( ! isset( $field['type'] ) ) {
				$field['type'] = 'text';
			}

			// Get Value.
			if ( 'checkbox' === $field['type'] ) {
				$value = (int) isset( $payload[ $key ] );
			} else {
				$value = $payload[ $key ] ?? null;
			}

			// Hook to allow modification of value.
			$value = apply_filters( 'storeengine/frontend/dashboard/edit-account/process_field_' . $key, $value );

			if ( isset( $field['required'] ) && $field['required'] && empty( $value ) ) {
				// translators: %s. Field name/label.
				$errors->add( 'missing-' . $key . '-field', sprintf( esc_html__( '%s is required.', 'storeengine' ), $field['label'] ), [ 'id' => $key ] );
			}

			if ( ! empty( $value ) ) {
				if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) ) {
					foreach ( $field['validate'] as $rule ) {
						switch ( $rule ) {
							case 'postcode':
								$value = Formatting::format_postcode( $value, $country );
								if ( '' !== $value && ! Validation::is_postcode( $value, $country ) ) {
									switch ( $country ) {
										case 'IE':
											$postcode_validation_notice = __( 'Please enter a valid Eircode.', 'storeengine' );
											break;
										default:
											$postcode_validation_notice = __( 'Please enter a valid postcode / ZIP.', 'storeengine' );
									}

									$errors->add( 'invalid-' . $key, $postcode_validation_notice );
								}
								break;
							case 'phone':
								if ( '' !== $value && ! Validation::is_phone( $value ) ) {
									/* translators: %s: Phone number. */
									$errors->add( 'invalid-' . $key, sprintf( __( '%s is not a valid phone number.', 'storeengine' ), '<strong>' . $field['label'] . '</strong>' ), [ 'id' => $key ] );
								}
								break;
							case 'email':
								$value = strtolower( $value );

								if ( ! is_email( $value ) ) {
									/* translators: %s: Email address. */
									$errors->add( 'invalid-' . $key, sprintf( __( '%s is not a valid email address.', 'storeengine' ), '<strong>' . $field['label'] . '</strong>' ), [ 'id' => $key ] );
								}
								break;
						}
					}
				}
			}

			if ( is_callable( [ $customer, "set_$key" ] ) ) {
				$customer->{"set_$key"}( $value );
			} else {
				update_user_meta( $customer->get_id(), $key, $value );
			}
		}

		if ( $errors->has_errors() ) {
			wp_die(
				$errors, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_Error object.
				esc_html__( 'Missing required fields', 'storeengine' ),
				[
					'code'      => 400,
					'back_link' => true,
				]
			);
		}

		$customer->save();

		wp_safe_redirect( Helper::get_account_endpoint_url( 'edit-address' ) );
		die();
	}
}
