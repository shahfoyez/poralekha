<?php
namespace ABlocks\Blocks\FormBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Blocks\FormBuilder\EmailVerification;
use SplTempFileObject;
/**
 * @class Query
 */
class Query {
	public static function get_entries(
		$is_in_trash = 'no', // yes | no
		$search_by_email = null, // search entries by email
		$form_type = null, // form type, eg: contact
		$date = null, // all entries  of a month, eg: 2024-12 # dec, 2024
		$from_date = null,
		$to_date = null,
		$order_by = 'created_at',
		$order = 'desc',
		$status = 'all', // all | read | unread
		$entry_per_page = 15,
		$current_page = 1,
		$do_export_csv = false
	) {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';

		$conditions = [];
		$args = [];

		$conditions[] = 'is_email_verified  != %s';
		$args[]       = 'no';

		$conditions[] = 'is_in_trash = %s';
		$args[]       = in_array( $is_in_trash, [ 'yes', 'no' ], true ) ? $is_in_trash : 'no';

		if ( $form_type ) {
			$conditions[] = 'form_type = %s';
			$args[] = $form_type;
		}
		if ( $search_by_email ) {
			$conditions[] = 'user_email like %s';
			$args[] = '%' . $wpdb->esc_like( $search_by_email ) . '%';
		}

		// format: year-month : 2024-12
		if ( $date ) {
			$date = explode( '-', $date );
			$year = intval( $date[0] );
			$month = intval( $date[1] );
			if (
				count( $date ) === 2 &&
				$year &&
				$month
			) {
				$conditions[] = 'YEAR(created_at) = %s';
				$args[] = $year;
				$conditions[] = 'MONTH(created_at) = %s';
				$args[] = $month;
			}
		}
		if ( ! isset( $year ) && $from_date ) {
			$from_date = gmdate( 'y-m-d h:i:s', strtotime( $from_date ) );
			$conditions[] = 'created_at >= %s';
			$args[] = $from_date;
		}
		if ( ! isset( $year ) && $to_date ) {
			$to_date = gmdate( 'y-m-d h:i:s', strtotime( $to_date ) );
			$conditions[] = 'created_at <= %s';
			$args[] = $to_date;
		}
		if ( in_array( strtolower( $status ), [ 'read', 'unread' ], true ) ) {
			$conditions[] = 'status = %s';
			$args[] = $status;
		}

		$query = "SELECT * FROM {$table_entries} ";
		$count_query = "select count(*) from {$table_entries} ";

		if ( ! empty( $conditions ) ) {
			$query .= ' where ' . implode( ' and ', $conditions );
			$count_query .= ' where ' . implode( ' and ', $conditions );
		}

		if ( empty( $args ) && empty( $conditions ) ) {
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$num_of_entries = $wpdb->get_var( $count_query );
		} else {
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$num_of_entries = $wpdb->get_var( $wpdb->prepare( $count_query, ...$args ) );
		}

		// order by
		if ( ! in_array( $order_by, [ 'id', 'user_email', 'created_at' ], true ) ) {
			$order_by = 'created_at';
		}
		// order
		if (
			! in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true )
		) {
			$order = 'ASC';
		}
		$query .= " order by {$order_by} {$order}";

		// export entries in csv format
		if ( $do_export_csv ) {
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$entries_to_export = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A ) ?? [];

			// make a temporary file object
			$save_entries_as_csv = new SplTempFileObject();

			// rewind pointer at first line
			$save_entries_as_csv->rewind();

			// add header to csv
			$save_entries_as_csv->fputcsv([
				'email',
				'form',
				'page id',
				'submission date',
				'ip',
				'user agent'
			]);

			// add row to csv
			foreach ( $entries_to_export as $entry ) {
				$save_entries_as_csv->fputcsv([
					sanitize_text_field( $entry['user_email'] ),
					sanitize_text_field( strtoupper( $entry['form_type'] ) ),
					sanitize_text_field( '#' . $entry['post_id'] ),
					sanitize_text_field( gmdate( 'd m, y h:i:s', strtotime( $entry['created_at'] ) ) ),
					sanitize_text_field( $entry['ip'] ),
					sanitize_text_field( $entry['user_agent'] ),
				]);
			}
			// rewind pointer at top
			$save_entries_as_csv->rewind();

			// get csv data as string
			$csv_data = '';
			foreach ( $save_entries_as_csv as $line ) {
				$csv_data .= $line;
			}
			return [ 'csv_data' => $csv_data ];
		}//end if
		// END

		// pagination
		$current_page = absint( $current_page );
		$entry_per_page = absint( $entry_per_page );
		if ( $current_page < 1 || $current_page > $num_of_entries ) {
			$current_page = 1;
		}
		if ( $entry_per_page < 1 ) {
			$entry_per_page = 15;
		}
		$offset = ( $current_page - 1 ) * $entry_per_page;

		$query .= ' limit %d offset %d';
		$args[] = $entry_per_page;
		$args[] = $offset;

		$get_available_date_query = "select 
			count(created_at) as total_entries,
			year(created_at) as year,
			month(created_at) as month
		from {$table_entries} 
			group by year, month
			order by year, month
			";
		$get_available_forms_query = "select 
			form_type 
		from {$table_entries} 
			group by form_type
			order by form_type asc
			";
		$data = [];
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A ) ?? [] as $item ) {
			if ( $item['is_in_trash'] === 'yes' ) {
				$item['status'] = 'trash';
			}
			$data[] = $item;
		}

		return [
			'total_entries'   => $num_of_entries,
			'current_page'    => $current_page,
			'per_page'        => $entry_per_page,
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'available_dates' => $wpdb->get_results( $get_available_date_query, ARRAY_A ) ?? [],
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'available_forms' => $wpdb->get_results( $get_available_forms_query, ARRAY_A ) ?? [],
			'data'            => $data,
		];
	}

	public static function get_block_attributes( $post_id, $block_id ) {
		$post_content = get_post_field( 'post_content', $post_id );
		$blocks = parse_blocks( $post_content );
		$block_data = self::get_block_attributes_recursive( $block_id, $blocks );

		$fields = [];
		if ( ( $block_data['innerBlocks'][0]['blockName'] ?? '' ) === 'ablocks/form-multi-step' ) {

			foreach ( $block_data['innerBlocks'][0]['innerBlocks'] ?? [] as $block ) {
				foreach ( $block['innerBlocks'] as [ 'blockName' => $block_name, 'attributes' => $attr ] ) {
					$name       = $attr['name'] ?? false;
					$input_type = $attr['inputType'] ?? false;
					$label      = $attr['label'] ?? $attr['placeholder'] ?? false;
					$radio_arr  = $attr['radioArr'] ?? false;
					// check current input name exists in $all_fields
					if ( $name ) {

						$fields[ $name ] = [
							'inputType' => sanitize_text_field( $input_type ), // sanitize value
							'label'     => strtolower( sanitize_text_field( $label ) ), // sanitize value
						];

						if ( is_array( $radio_arr ) ) {
							$fields[ $name ]['radioArr'] = $radio_arr;
						}
					} elseif ( $block_name === 'ablocks/form-hidden' ) {
						$name = strtolower( sanitize_text_field( $attr['hiddenname'] ?? '' ) );
						$fields[ $name ] = [
							'inputType' => 'textarea', // sanitize value
							'label'     => ucfirst( preg_replace( '/[_-]/m', ' ', $name ) ), // sanitize value
						];
					}
				}//end foreach
			}//end foreach
		} elseif (
			isset( $block_data['innerBlocks'] )
		) {
			$fields = [];
			foreach ( $block_data['innerBlocks'] as $input_element_data ) {
				$name       = $input_element_data['attributes']['name'] ?? false;
				$input_type = $input_element_data['attributes']['inputType'] ?? false;
				$label      = $input_element_data['attributes']['label'] ?? $input_element_data['attributes']['placeholder'] ?? false;
				$radio_arr  = $input_element_data['attributes']['radioArr'] ?? false;
				// check current input name exists in $all_fields
				if ( $name ) {

					$fields[ $name ] = [
						'inputType' => strtolower( sanitize_text_field( $input_type ) ), // sanitize value
						'label'     => sanitize_text_field( $label ), // sanitize value
					];

					if ( is_array( $radio_arr ) ) {
						$fields[ $name ]['radioArr'] = $radio_arr;
					}
				} elseif ( $input_element_data['blockName'] === 'ablocks/form-hidden' ) {
					$name = strtolower( sanitize_text_field( $input_element_data['attributes']['hiddenname'] ?? '' ) );
					$fields[ $name ] = [
						'inputType' => 'textarea', // sanitize value
						'label'     => ucfirst( preg_replace( '/[_-]/m', ' ', $name ) ), // sanitize value
					];
				}
			}//end foreach
		}//end if
		return $fields;
	}

	public static function get_block_attributes_recursive( $block_id, $blocks ) {

		foreach ( $blocks as $block ) {

			if (
				( $block['attrs']['block_id'] ?? '' ) === $block_id
			) {
				return [
					'parentAttributes' => $block['attrs'] ?? [],
					'innerBlocks'      => \ABlocks\Helper::extract_inner_blocks( $block['innerBlocks'] ?? [] ),
				];
			}

			if (
				array_key_exists( 'innerBlocks', $block ) &&
				is_array( $block['innerBlocks'] ) &&
				count( $block['innerBlocks'] ) > 0
			) {
				$data = self::get_block_attributes_recursive(
					$block_id,
					$block['innerBlocks']
				);
				if (
					$data
				) {
					return $data;
				}
			}
		}//end foreach
		return [];
	}

	public static function get_entry( int $id ) {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
		$table_meta = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_meta';

		$query = "SELECT * FROM {$table_entries}  WHERE id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row( $wpdb->prepare( $query, $id ), ARRAY_A );
		if ( $entry ) {
			// mark this entry as reas
			if ( count( self::update_status_of_entries( [ $id ] ) ) > 0 ) {
				$entry['status'] = 'read';
			}
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
			$meta = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
					"SELECT meta_key, meta_value FROM $table_meta WHERE entry_id = %d ",
					$id
				),
				ARRAY_A
			);

			$fields = self::get_block_attributes( $entry['post_id'], $entry['block_id'] );
			$found = [];
			foreach ( $meta as $field ) {
				if ( array_key_exists( $field['meta_key'], $fields ) ) {
					$found[] = $field['meta_key'];

					if (
						isset( $fields[ $field['meta_key'] ]['radioArr'] )
					) {
						$decoded_value = json_decode( $field['meta_value'], true );
						if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_value ) ) {
							$field['meta_value'] = array_values( $decoded_value );
						} else {
							$field['meta_value'] = [];
						}
					} elseif (
						! isset( $fields[ $field['meta_key'] ]['radioArr'] ) &&
						( $fields[ $field['meta_key'] ]['inputType'] ?? '' ) === 'checkbox'
					) {
						$field['meta_value'] = strtolower( $field['meta_value'] ) === 'on' ? true : false;
					}

					$entry['meta'][] = apply_filters( 'ablocks/form_builder/meta_output', array_merge(
						$fields[ $field['meta_key'] ],
						$field
					) );

				} else {
					$entry['meta'][] = apply_filters( 'ablocks/form_builder/meta_output', array_merge(
						$field,
						[
							'inputType' => 'text',
							'label'     => ucfirst( preg_replace( '/[_-]/m', ' ', $field['meta_key'] ) ),
						]
					) );
				}//end if
			}//end foreach

			foreach ( array_diff( array_keys( $fields ), $found ) as $key ) {

				$fields[ $key ]['meta_key']   = $key;

				if (
					isset( $fields[ $key ]['radioArr'] )
				) {
					$fields[ $key ]['meta_value'] = [];
				} elseif (
					! isset( $fields[ $key ]['radioArr'] ) &&
					( $fields[ $key ]['inputType'] ?? '' ) === 'checkbox'
				) {
					$fields[ $key ]['meta_value'] = false;
				} else {
					$fields[ $key ]['meta_value'] = null;
				}

				$entry['meta'][] = apply_filters( 'ablocks/form_builder/meta_output', $fields[ $key ] );
			}//end foreach

			return $entry;
		}//end if
		return [];
	}

	public static function edit_entry( int $id, array $data ) {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
		$table_meta    = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_meta';

		// Check if the entry exists
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$is_entry_exists = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table_entries} WHERE id = %d", $id )// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) > 0;

		if ( ! $is_entry_exists ) {
			return false;
		}

		// Get all meta keys for this entry
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$get_meta_keys = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT meta_key FROM {$table_meta} WHERE entry_id = %d ",
				$id
			),
			ARRAY_A
		);

		if ( empty( $get_meta_keys ) ) {
			return false;
		}

		$total_updated = 0;

		foreach ( $get_meta_keys as $key ) {
			$meta_key = $key['meta_key'];

			// Check if the input data has this meta key
			if ( array_key_exists( $meta_key, $data ) ) {
				// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
				$is_updated = $wpdb->update(
					$table_meta,
					[ 'meta_value' => sanitize_text_field( $data[ $meta_key ] ) ],
					[
						'entry_id' => $id,
						'meta_key' => $meta_key,
					],
					[ '%s' ], // Data format
					[ '%d', '%s' ] // Where format
				);

				if ( false !== $is_updated && $is_updated > 0 ) {
					$total_updated++;
				}
			}
		}//end foreach

		return $total_updated > 0;
	}

	public static function delete_entries( array $entry_id_array ): array {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
		$table_meta = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_meta';

		$total_deleted = [];
		if ( ! empty( $entry_id_array ) ) {
			foreach ( $entry_id_array as $entry_id ) {

				// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->delete( $table_entries, [ 'id' => $entry_id ], [ '%d' ] ) ) {

					// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->delete( $table_meta, [ 'entry_id' => $entry_id ], [ '%d' ] );
					$total_deleted[] = $entry_id;
				}
			}
		}
		return $total_deleted;
	}
	public static function update_status_of_entries( array $entry_id_array, string $status = 'read' ): array {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';

		$total = [];
		if ( ! empty( $entry_id_array ) ) {
			foreach ( $entry_id_array as $entry_id ) {

				// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->update( $table_entries, [ 'status' => $status ], [ 'id' => $entry_id ] ) ) {
					self::update_trash_status_of_entries( $entry_id, 'no' );
					$total[] = $entry_id;
				}
			}
		}
		return $total;
	}

	public static function update_trash_status_of_entries( int $id, string $send_to_trash = 'yes' ): array {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';

		// Retrieve the current status
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$table_entries} WHERE id = %d", $id )// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Initialize response array
		$total = [];

		if ( $id > 0 ) {
			// Update the trash status
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
			$is_updated = $wpdb->update(
				$table_entries,
				[ 'is_in_trash' => sanitize_text_field( $send_to_trash ) ],
				[ 'id' => $id ],
				[ '%s' ],
				[ '%d' ]
			);

			if ( false !== $is_updated && $is_updated > 0 ) {
				$total = [
					'id'     => $id,
					'status' => ( 'yes' === $send_to_trash ) ? 'trash' : $status,
				];
			}
		}

		return $total;
	}

	public static function add_new_entry(
		string $form_type,
		string $post_id,
		string $user_email,
		string $ip,
		string $user_agent,
		array $meta_data,
		string $block_id,
		bool $is_verification_needed = false
	) {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
		$table_meta = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_meta';
		$token = wp_generate_password( 22, false );
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert($table_entries,
			[
				'form_type'  => $form_type,
				'post_id'    => $post_id,
				'user_email' => $user_email,
				'ip'         => $ip,
				'user_agent' => $user_agent,
				'block_id'   => $block_id,
				'email_verification_token' => $token,
				'expire'     => time() + EmailVerification::EXPIRE_IN,
				'is_email_verified' => $is_verification_needed ? 'no' : '-'
			]
		);
		$entry_id = $wpdb->insert_id;

		if ( $entry_id ) {
			foreach ( $meta_data as $input_id => $attr ) {
				// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert($table_meta,
					[
						'entry_id'   => $entry_id,
						'meta_key'   => $input_id,
						'meta_value' => is_array( $attr['value'] ) ? json_encode( $attr['value'] ) : $attr['value'],
					]
				);
			}
		}
		return $entry_id;
	}

	public static function get_token_by_email( string $email ) {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT email_verification_token FROM {$table_entries} WHERE user_email = %s",
				sanitize_email( $email )
			),
			ARRAY_A
		);

		return ! empty( $entry['email_verification_token'] ) ? $entry['email_verification_token'] : false;
	}

	public static function is_email_exists( string $email, string $form_type ) {
		global $wpdb;
		$table_entries = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';

		$query = "select count(user_email) from {$table_entries} where form_type = %s and user_email = %s";
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_var( $wpdb->prepare( $query, $form_type, $email ) ) > 0;
	}

}
