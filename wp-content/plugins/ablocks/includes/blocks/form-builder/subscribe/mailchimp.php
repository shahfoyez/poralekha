<?php
namespace ABlocks\Blocks\FormBuilder\Subscribe;

use ABlocks\Classes\Request;
use Exception;

class Mailchimp {
	/** @var $api_key */
	private string $api_key;
	/** @var $list_id */
	private string $list_id;
	/** @var $status */
	private string $status;

	private Request $req;

	public function __construct( $api_key, $list_id, $status ) {
		$this->api_key = $api_key;
		$this->list_id = $list_id;
		$this->status = $status ? $status : 'subscribed';

		if ( empty( $this->api_key ) ) {
			throw new exception( 'api is not defined.' );
		}

		$pos = $this->api_key ? strpos( $this->api_key, '-' ) : 0;
		if ( intval( $pos ) === 0 ) {
			throw new exception( 'api is invalid.' );
		}

		$dc = substr( $this->api_key, $pos + 1 );

		$this->req = new Request('https://' . $dc . '.api.mailchimp.com/3.0', [
			'Authorization' => 'Basic ' . base64_encode( 'user:' . $this->api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'Content-Type' => 'application/json; charset=utf-8'
		]);
	}

	public function get_lists() {
		$res = $this->req->get( '/lists?count=100' );

		if ( $res['status_code'] === 401 ) {
			return 'invalid_api';
		} elseif (
			$res['error'] ||
			$res['status_code'] === 0 ||
			! is_array( $res['body'] )
		) {
			return 'error';
		}
		return $res['body'];
	}

	public function get_groups( $list_id ) {
		if ( empty( $list_id ) ) {
			return [];
		}
		$res = $this->req->get( "/lists/{$list_id}/interest-categories?count=999" );

		if ( $res['status_code'] === 401 ) {
			return 'invalid_api';
		} elseif (
			$res['error'] ||
			$res['status_code'] === 0 ||
			! is_array( $res['body'] )
		) {
			return 'error';
		}
		$categories = $res['body']['categories'] ?? [];
		if ( ! empty( $categories ) ) {
			$res['body']['_group_list'] = [];
			foreach ( $categories as ['id' => $c_id, 'title' => $c_title] ) {
				$detail = $this->get_group_detail( $list_id, $c_id );
				foreach ( $detail['interests'] as ['id' => $i_id,'name' => $i_name] ) {
					$res['body']['_group_list'][ $i_id ] = $i_name . '~' . $c_title;
				}
			}
		}
		$res['body']['_field_list'] = $this->get_fields( $list_id );
		return $res['body'];
	}

	public function get_fields( $list_id ) {
		$res = $this->req->get( "/lists/{$list_id}/merge-fields" );

		if ( $res['status_code'] === 401 ) {
			return [];
		} elseif (
			$res['error'] ||
			$res['status_code'] === 0 ||
			! is_array( $res['body'] )
		) {
			return [];
		}
		$data = $res['body']['merge_fields'] ?? [];
		return $data;
	}

	public function get_group_detail( $list_id, $category_id ) {
		$res = $this->req->get( "/lists/{$list_id}/interest-categories/{$category_id}/interests?count=999" );

		if ( $res['status_code'] === 401 ) {
			return 'invalid_api';
		} elseif (
			$res['error'] ||
			$res['status_code'] === 0 ||
			! is_array( $res['body'] )
		) {
			return 'error';
		}
		return $res['body'];
	}

	public function subscribe( string $user_email, array $groups, array $fields, array $tags ) {

		if ( empty( $this->list_id ) ) {
			throw new exception( 'list_id is not defined.' );
		}

		$email_hash = md5( strtolower( $user_email ) );

		$post_data = [
			'email_address' => $user_email,
			'status_if_new' => $this->status,
		];

		if ( ! empty( $groups ) ) {
			$post_data['interests']    = array_combine( $groups, array_fill( 0, count( $groups ), true ) );
		}

		if ( ! empty( $fields ) ) {
			$post_data['merge_fields'] = $fields;
		}

		$res = $this->req->put(
			'/lists/' . $this->list_id . '/members/' . $email_hash,
			wp_json_encode( $post_data )
		);

		if (
			( $res['error'] ?? true ) === false &&
			! empty( $tags )
		) {
			$tags_s = [];
			foreach ( $tags as $tag ) {
				$tags_s[] = [
					'name' => $tag,
					'status' => 'active',
				];
			}
			$res_tags = $this->req->post(
				'/lists/' . $this->list_id . '/members/' . $email_hash . '/tags',
				wp_json_encode([
					'tags'  => $tags_s,
				])
			);

		}

		return boolval( $res['body']['email_address'] ?? false );
	}
}
