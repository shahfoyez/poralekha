<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters;

use ABlocks\Helper;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class PostType extends Abstracts\Interpreter {
	protected int $ID;
	protected string $post_type;
	protected string $field;
	protected string $pre;
	protected string $post;
	protected string $default;
	protected string $m_key = '';

	public function __construct( string $name, array $setting, bool $is_richtext = false, $context = [] ) {
		parent::__construct( $name, $setting, $is_richtext );
		if ( ! $this->is_richtext || $this->name === 'post-type' ) {
			$this->ID        = isset( $context['postId'] ) ? $context['postId'] : intval( $this->setting[1] ?? 0 );
			$this->post_type = isset( $context['postType'] ) ? $context['postType'] : strval( $this->setting[0] ?? '' );
			$this->field     = strval( $this->setting[2] ?? '' );
			$this->pre     = $this->is_richtext ? '' : strval( $this->setting[3] ?? '' );
			$this->post    = $this->is_richtext ? '' : strval( $this->setting[4] ?? '' );
			$this->default = $this->is_richtext ? strval( $this->setting[3] ?? '' ) : strval( $this->setting[5] ?? '' );
			$this->m_key   = $this->is_richtext ? ( $this->setting[4] ?? '' ) : '';
		} else {
			$this->ID        = isset( $context['postId'] ) ? $context['postId'] : get_the_ID();
			$this->post_type = isset( $context['postType'] ) ? $context['postType'] : get_post_type();
			$this->field     = strval( $this->setting[0] ?? '' );
			$this->pre     = '';
			$this->post    = '';
			$this->default = strval( $this->setting[1] ?? '' );
		}
	}
	public function content() : string {
		$thumbnail_id = (int) get_post_thumbnail_id( $this->ID );

		switch ( $this->field ) {
			case 'post-custom-field':
				$key = $this->is_richtext ? ( empty( $this->m_key ) ? ( $this->setting[2] ?? '' ) : $this->m_key ) : ( $this->setting[6] ?? '' );
				$this->default = $this->is_richtext ? ( $this->setting[1] ?? '' ) : $this->default;
				$meta_data = empty( $key ) ? '' : get_post_meta( $this->ID, $key, true );
				$this->content = is_string( $meta_data ) || is_numeric( $meta_data ) ? $meta_data : '';
				break;

			case 'post-date':
			case 'post-time':
				if ( $this->is_richtext ) {
					if ( $this->name === 'post-type' ) {
						$type   = $this->setting[4] ?? 'post_modified';
						$format = ( ( $this->setting[5] ?? '' ) === 'custom' ) ? ( $this->setting[6] ?? 'g:i A' ) : ( $this->setting[5] ?? '' );
						$custom_format = $format;
					} else {
						$type   = $this->setting[2] ?? 'post_modified';
						$format = ( ( $this->setting[3] ?? '' ) === 'custom' ) ? ( $this->setting[4] ?? 'g:i A' ) : ( $this->setting[3] ?? '' );
						$custom_format = $format;
					}
				} else {
					$type   = $this->setting[6] ?? '';
					$format = isset( $this->setting[8] ) ? $this->setting[8] : $this->setting[7];
					$custom_format = isset( $this->setting[8] ) ? $this->setting[7] : $this->setting[6];
				}
				$this->content = Helper::get_post_time_date( $this->ID, [
					'dateTimeType' => $type,
					'dateTimeFormat' => $format,
					'customDateTimeFormat' => $custom_format,
				], 'post-time' === $this->field );

				break;

			case 'post-title':
				$this->content = get_the_title( $this->ID );
				break;

			case 'post-excerpt':
				$this->default = $this->is_richtext ? ( $this->name === 'post-type' ? ( $this->setting[3] ?? 10 ) : ( $this->setting[1] ?? 10 ) ) : $this->default;
				$this->content = $this->get_excerpt( strval( get_the_excerpt( $this->ID ) ), $this->is_richtext ? ( $this->name === 'post-type' ? intval( $this->setting[4] ?? 10 ) : intval( $this->setting[2] ?? 10 ) ) : intval( $this->setting[6] ?? 40 ) );
				break;

			case 'post-id':
				$this->content = $this->ID;
				break;

			case 'post-terms':
				if ( $this->is_richtext ) {
					if ( $this->name === 'post-type' ) {
						$type = $this->setting[4] ?? 'category';
						$sep  = $this->setting[5] ?? ', ';
					} else {
						$type = $this->setting[2] ?? 'category';
						$sep  = $this->setting[3] ?? ', ';
					}
				} else {
					$type = $this->setting[6] ?? 'category';
					$sep  = $this->setting[7] ?? ', ';
				}
				$this->content = $this->get_post_terms( $type, $sep );
				break;

			case 'author-name':
				$this->content = $this->get_author_data( 'display_name' );
				break;

			case 'author-id':
				$this->content = get_post_field( 'post_author', $this->ID );
				break;

			case 'author-posts-count':
				$this->content = count_user_posts( get_post_field( 'post_author', $this->ID ) );
				break;

			case 'author-posts-url':
				$this->content = esc_url( get_author_posts_url( get_post_field( 'post_author', $this->ID ) ) );
				break;

			case 'author-profile-picture-url':
				$this->content = esc_url( get_avatar_url( get_post_field( 'post_author', $this->ID ) ) );
				break;

			case 'author-bio':
				$this->content = get_user_meta( get_post_field( 'post_author', $this->ID ), 'description', true );
				break;

			case 'author-email':
				$this->content = $this->get_author_data( 'user_email' );
				break;
			case 'author-website':
				$this->content = $this->get_author_data( 'user_url' );
				break;

			case 'author-meta':
				$this->content = $this->get_author_data( $this->is_richtext ? ( $this->setting[2] ?? '' ) : ( $this->setting[6] ?? '' ), true );
				break;

			case 'author-first-name':
				$this->content = $this->get_author_data( 'first_name', true );
				break;

			case 'author-last-name':
				$this->content = $this->get_author_data( 'last_name', true );
				break;

			case 'comments-number':
				$this->content = (int) get_comments_number( $this->ID );
				break;

			case 'featured-image-title':
				$this->content = $this->get_attachment_data( 'post_title' );
				break;

			case 'featured-image-alt':
				$this->content = $thumbnail_id ? get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '';
				break;

			case 'featured-image-caption':
				$this->content = $this->get_attachment_data( 'post_excerpt' );
				break;

			case 'featured-image-description':
				$this->content = $this->get_attachment_data( 'post_content' );
				break;

			case 'featured-image-file-url':
				$this->content = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : '';
				break;

			case 'featured-image-attachment-url':
				$this->content = $thumbnail_id ? get_permalink( $thumbnail_id ) : '';
				break;
		}//end switch

		// Academy LMS
		if ( Helper::is_active_academy() ) {
			switch ( $this->field ) {
				case 'numberOfTopics':
					$curriculums = \Academy\Helper::get_course_curriculums_number_of_counts( $this->ID );
					$this->content = (int) isset( $curriculums['total_topics'] ) ? $curriculums['total_topics'] : 0;
					break;
				case 'numberOfReviews':
					$Analytics = new \Academy\Classes\Analytics();
					$this->content = (int) $Analytics->get_total_number_of_reviews_by_course_id( $this->ID );
					break;
				case 'numberOfEnrolled':
						$Analytics = new \Academy\Classes\Analytics();
						$this->content = (int) $Analytics->get_total_number_of_enrolled_by_course_id( $this->ID );
					break;
				case 'totalDuration':
						$this->content = (int) \Academy\Helper::get_course_duration( $this->ID );
					break;
			}
		}

		return $this->pre . ( $this->content === '' ? $this->default : $this->content ) . $this->post;
	}

	public function get_author_data( string $field, bool $meta = false ) : string {
		$id = (int) get_post_field( 'post_author', $this->ID );
		if ( $meta ) {
			return get_the_author_meta( $field, $id );
		}

		if ( $id ) {
			$user_data = get_userdata( $id );
			if ( ! property_exists( $user_data, 'data' ) ) {
				return '';
			}
			$user_data = $user_data->data;
			return property_exists( $user_data, $field ) ? $user_data->{$field} : '';
		}
		return '';
	}

	public function get_attachment_data( string $field ) : string {
		$id = (int) get_post_thumbnail_id( $this->ID );
		if ( $id ) {
			if ( $attacment = get_post( $id ) ) {
				return property_exists( $attacment, $field ) ? $attacment->{$field} : '';
			}
		}
		return '';
	}
	public function get_post_terms( string $type, string $sep ) : string {
		$terms = get_the_terms( $this->ID, $type );
		$bag = [];
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$bag[] = $term->name;
			}
			return implode( $sep, $bag );
		}
		return '';
	}
	protected function get_excerpt( string $str, int $n ) : string {
		if ( $n < 1 ) {
			$n = -1;
		}
		$str = wp_strip_all_tags( $str ); // Remove HTML tags
		$str = trim( $str ); // Trim whitespace
		$words = preg_split( '/\s+/', $str ); // Split by any whitespace
		if ( count( $words ) <= $n ) {
			return implode( ' ', $words );
		}
		$excerpt = array_slice( $words, 0, $n );
		return implode( ' ', $excerpt ) . '';
	}
}
