<?php
namespace ABlocks\Blocks\FormBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
/**
 * @class EmailVerification
 * This class is user to verify forms email
 */
class EmailVerification {
	/** Link expiration time */
	const EXPIRE_IN = 3600;
	private int $id;
	private string $email;
	private string $verification_token;

	private string $signature;
	private bool $expiration_check = false;
	private int $expire_at;

	public function __construct( $id_or_email, ?string $search_key = null ) {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';

		if ( $search_key === 'id' ) {
			$query = "SELECT id, user_email, email_verification_token, expire, is_email_verified FROM {$table_entries}  WHERE id = %d";
		} else {
			$query = "SELECT id, user_email, email_verification_token, expire, is_email_verified FROM {$table_entries}  WHERE user_email = %s";
		}
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$entry = $wpdb->get_row( $wpdb->prepare( $query, $id_or_email ), ARRAY_A );

		if (
			! array_key_exists( 'email_verification_token', $entry ) ||
			$entry['is_email_verified'] === 'yes'
		) {
			throw new Exception( 'Invalid ID or Email/ Already verified.' );
		}

		$this->id                 = $entry['id'];
		$this->email              = $entry['user_email'];
		$this->verification_token = $entry['email_verification_token'];
		$this->expire_at          = $entry['expire'];
	}

	public function verify_signature( string $signature ) {
		$saved_signature = $this->get_signature();
		if ( $saved_signature ) {
			return hash_equals( $saved_signature, $signature );
		}
		return false;
	}

	public function get_signature() {
		if ( $this->verification_token ) {
			return hash_hmac( 'sha256', $this->email . $this->expire_at, $this->verification_token );
		}
		return false;
	}

	public function get_signed_url() {
		$signature = $this->get_signature();
		if ( $signature ) {
			return add_query_arg(
				[
					'id' => $this->id,
					'signature' => $signature,
					'expire' => $this->expire_at,
				],
				home_url( '/' )
			);
		}
	}

	public function mark_email_as_verified() {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->update( $table_entries, [ 'is_email_verified' => 'yes' ], [ 'id' => $this->id ] ) ) {
			return true;
		}
		return false;
	}



	public static function verify( $post_data ) {
		if (
			! isset( $post_data['id'] ) ||
			! isset( $post_data['signature'] ) ||
			! isset( $post_data['expire'] )
		) {
			return 'no data';
		}

		$id         = intval( sanitize_text_field( $post_data['id'] ) );
		$expire     = intval( sanitize_text_field( $post_data['expire'] ) );
		$signature  = sanitize_text_field( $post_data['signature'] );

		try {

			$verification_obj = new self( $id, 'id' );

			if (
				$verification_obj->expiration_check &&
				$verification_obj->expire_at !== $expire &&
				time() > $expire
			) {
				return 'Link expired.';
			}

			if ( $verification_obj->verify_signature( $signature ) ) {
				return $verification_obj->mark_email_as_verified() ? [ 'success' => true ] : 'error';
			}
		} catch ( Exception $e ) {
			return 'Error';
		}
		return 'Error';
	}
}
