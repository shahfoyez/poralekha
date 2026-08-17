<?php
namespace ABlocks\Blocks\FormBuilder\BlockAttrSanitizer;

use ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\interfaces\{
	SanitizerInterface,
	SanitizerBaseInterface
};
use ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Exceptions\BlockNotFoundException;
use ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Sanitizers\FormBuilder\RegistrationFormSanitizer;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitizer implements SanitizerBaseInterface {

	protected array $sanitizers = [
		RegistrationFormSanitizer::class
	];

	public function sanitize( int $post_id, WP_Post $post, bool $update ): void {
		try {
			foreach ( $this->sanitizers as $sanitizer ) {
				$instance = ( new $sanitizer( $post ) );
				if (
					class_exists( $sanitizer ) &&
					$instance instanceof SanitizerInterface
				) {
					$instance->sanitize();
				}
			}
		} catch ( BlockNotFoundException $e ) {
			$instance->log( $e->getMessage() );
		}
	}
	public static function init() : void {
		$instance = new self();
		add_action( 'save_post', [ $instance, 'sanitize' ], 10, 3 );
	}
}
