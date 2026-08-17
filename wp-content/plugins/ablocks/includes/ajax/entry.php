<?php

namespace ABlocks\Ajax;

use ABlocks\Classes\Sanitizer;
use ABlocks\Blocks\FormBuilder\Query;
use ABlocks\Classes\AbstractAjaxHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @class Entry
 * Ajax handler for entries
 */
class Entry extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			/** Get entries with pagination */
			'get_entries' => [
				'callback' => [ $this, 'get_entries' ],
				'capability'    => 'manage_options',
				'fields'    => array(
					'is_in_trash'  => 'string', // yes/no; default: no
					'search'       => 'string', // name of form
					'form_type'    => 'string', // name of form
					'order'        => 'string', // asc/desc
					'order_by'     => 'string', // created_at, id,
					'date'         => 'string', // date :  format : y-m; eg: 2024-12,  get entries by a month of year;
					'from_date'    => 'string', // date : y-m-d
					'to_date'      => 'string', // date : y-m-d
					'status'       => 'string', // all/read/unread
					'per_page'     => 'integer', // int: default = 15
					'current_page' => 'integer', // int
					'export_csv'   => 'integer', // int
				)
			],
			/** Get single entry */
			'get_entry' => [
				'callback' => [ $this, 'get_entry' ],
				'capability'    => 'manage_options',
				'fields'    => array(
					'entry_id' => 'integer', // int : entry id
				)
			],
			/** Edit entry */
			'edit_entry' => [
				'callback' => [ $this, 'edit_entry' ],
				'capability'    => 'manage_options',
				'fields'    => array(
					'entry_id' => 'integer', // int : entry id
					'data'     => 'array',
				)
			],
			/** Delete entries */
			'delete_entries' => [
				'callback' => [ $this, 'delete_entries' ],
				'capability'    => 'manage_options',
				'fields'    => array(
					'entry_id_array'     => 'array',
				)
			],
			/** Mark entries as read */
			'mark_entries_as_read' => [
				'callback' => [ $this, 'mark_entries_as_read' ],
				'capability'    => 'manage_options',
				'fields'    => array(
					'entry_id_array'     => 'array',
				)
			],
			/** Mark entries as unread */
			'mark_entries_as_unread' => [
				'callback' => [ $this, 'mark_entries_as_unread' ],
				'capability'    => 'manage_options',
				'fields'    => array(
					'entry_id_array'     => 'array',
				)
			],
			/** Send one or more entries to trash */
			'send_entries_to_trash' => [
				'callback' => [ $this, 'send_entries_to_trash' ],
				'capability'    => 'manage_options',
				'fields'    => array(
					'entry_id' => 'integer', // int : entry id
				)
			],
			/** Send one or more entries to trash */
			'restore_entries_from_trash' => [
				'callback' => [ $this, 'restore_entries_from_trash' ],
				'capability'    => 'manage_options',
				'fields'    => array(
					'entry_id' => 'integer', // int : entry id
				)
			],
		];
	}

	/**
	 * Get entries with pagination & filter
	 * Available filter options:
	 *  -> by a month of year, from a date, to a date, within two date
	 *  -> by status: read, unread, all
	 *  -> form_type : name of form
	 * Response like:
	 * Array
	 *   (
	 *       [total_entries] => Integer
	 *       [current_page] => Integer
	 *       [per_page] => Integer
	 *       [available_dates] => Array
	 *           (
	 *               [0] => Array
	 *                   (
	 *                       [total_entries] => Integer: total number of entries that month
	 *                       [year] => Integer: eg: 2024
	 *                       [month] => Integer: range: 1-12
	 *                   )
	 *
	 *           )
	 *       [available_forms] => Array
	 *           (
	 *               [0] => Array
	 *                   (Form Nametotal number of entries that month
	 *                   )
	 *
	 *           )
	 *
	 *       [data] => Array: a associative array
	 *   )
	 * Processes the form data.
	 *
	 * @param array $form_data The form data submitted by the user.
	 * @return void
	 */
	public function get_entries( $payload ) {
		wp_send_json_success(Query::get_entries(
			$payload['is_in_trash'] ?? 'no',
			$payload['search'] ?? null,
			$payload['form_type'] ?? null,
			$payload['date'] ?? null,
			$payload['from_date'] ?? null,
			$payload['to_date'] ?? null,
			$payload['order_by'] ?? 'created_at',
			$payload['order'] ?? 'DESC',
			$payload['status'] ?? 'all',
			$payload['per_page'] ?? 10,
			$payload['current_page'] ?? 1,
			$payload['export_csv'] ?? 0,
		));
	}

	public function get_entry( $payload ) {
		if ( ! isset( $payload['entry_id'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Error', 'ablocks' ) ] );
		}

		$entry = Query::get_entry( $payload['entry_id'] );

		if ( ! empty( $entry ) ) {
			wp_send_json_success( $entry );
		}
		wp_send_json_error( [ 'message' => __( 'Entry not found', 'ablocks' ) ] );
	}

	/**
	 * Edit metadata of a single entry.
	 *
	 * @param array $form_data The form data containing metadata details.
	 * @return void
	 */
	public function edit_entry( $payload ) {
		if ( ! isset( $payload['entry_id'] ) || ! isset( $payload['data'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Error', 'ablocks' ) ] );
		}

		if ( Query::edit_entry( $payload['entry_id'], (array) $payload['data'] ) ) {
			wp_send_json_success( [ 'message' => __( 'Success!', 'ablocks' ) ] );
		}

		wp_send_json_error( [ 'message' => __( 'Error', 'ablocks' ) ] );
	}

	/**
	 * Delete one or more entries by entry ID.
	 *
	 * @param array $form_data The form data containing entry IDs to delete.
	 * @return void
	 */
	public function delete_entries( $payload ) {
		$deleted_entries = Query::delete_entries( $payload['entry_id_array'] ?? [] );
		$num_of_entries_deleted = count( $deleted_entries );
		if ( $num_of_entries_deleted === 0 ) {
			wp_send_json_error([
				'message' => __( 'No entries deleted.', 'ablocks' ),
				'num_of_deleted_entries' => 0,
				'id_of_deleted_entries' => $deleted_entries,
			]);
		}
		wp_send_json_success([
			// translators: %d is the number of entries deleted
			'message' => sprintf( __( '%d entries deleted.', 'ablocks' ), $num_of_entries_deleted ),
			'num_of_deleted_entries' => $num_of_entries_deleted,
			'id_of_deleted_entries' => $deleted_entries,
		]);
	}

	/**
	 * Mark one or more entries as updated by entry ID.
	 *
	 * @param array $form_data The form data containing entry IDs to mark as updated.
	 * @return void
	 */
	public function mark_entries_as_read( $payload ) {

		$updated_entries = Query::update_status_of_entries( $payload['entry_id_array'] ?? [], 'read' );
		$num_of_entries_updated = count( $updated_entries );
		if ( $num_of_entries_updated === 0 ) {
			wp_send_json_error([
				'message' => __( 'No entries updated.', 'ablocks' ),
				'num_of_updated_entries' => 0,
				'id_of_updated_entries' => $updated_entries,
			]);
		}
		wp_send_json_success([
			// translators: %d is the number of entries updated
			'message' => sprintf( __( '%d entries updated.', 'ablocks' ), $num_of_entries_updated ),
			'num_of_updated_entries' => $num_of_entries_updated,
			'id_of_updated_entries' => $updated_entries,
		]);
	}

	/**
	 * Mark one or more entries as updated by entry ID.
	 *
	 * @param array $form_data The form data containing entry IDs to mark as updated.
	 * @return void
	 */
	public function mark_entries_as_unread( $payload ) {
		$updated_entries = Query::update_status_of_entries( $payload['entry_id_array'] ?? [], 'unread' );
		$num_of_entries_updated = count( $updated_entries );
		if ( $num_of_entries_updated === 0 ) {
			wp_send_json_error([
				'message' => __( 'No entries updated.', 'ablocks' ),
				'num_of_updated_entries' => 0,
				'id_of_updated_entries' => $updated_entries,
			]);
		}
		wp_send_json_success([
			// translators: %d is the number of entries updated
			'message' => sprintf( __( '%d entries updated.', 'ablocks' ), $num_of_entries_updated ),
			'num_of_updated_entries' => $num_of_entries_updated,
			'id_of_updated_entries' => $updated_entries,
		]);
	}

	/**
	 * Send one or more entries to trash.
	 *
	 * @param array $form_data The form data containing entry IDs to move to trash.
	 * @return void
	 */
	public function send_entries_to_trash( $payload ) {
		$updated = Query::update_trash_status_of_entries( $payload['entry_id'] ?? 0, 'yes' );
		if ( empty( $updated ) ) {
			wp_send_json_error([
				'message' => __( 'No entries updated.', 'ablocks' ),
			]);
		}
		wp_send_json_success([
			'message' => __( 'Updated.', 'ablocks' ),
			'data' => $updated,
		]);
	}

	/**
	 * Restore one or more entries from trash.
	 *
	 * @param array $form_data The form data containing entry IDs to restore.
	 * @return void
	 */
	public function restore_entries_from_trash( $payload ) {
		$updated = Query::update_trash_status_of_entries( $payload['entry_id'] ?? 0, 'no' );

		if ( empty( $updated ) ) {
			wp_send_json_error([
				'message' => __( 'No entries updated.', 'ablocks' ),
			]);
		}
		wp_send_json_success([
			'message' => __( 'Entry updated.', 'ablocks' ),
			'data' => $updated,
		]);
	}
}
