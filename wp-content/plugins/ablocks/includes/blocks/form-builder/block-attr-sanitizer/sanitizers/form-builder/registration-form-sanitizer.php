<?php
namespace ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Sanitizers\FormBuilder;

use ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Abstracts\SanitizerAbstract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RegistrationFormSanitizer extends SanitizerAbstract {

	protected string $block = 'ablocks/form-builder';

	public function modify_attr( array $attr ) : array {

		if (
			! current_user_can( 'manage_options' ) &&
			array_key_exists( 'roleSlug', $attr ) &&
			array_key_exists( 'formType', $attr ) &&
			$attr['formType'] === 'registration'
		) {
			unset( $attr['roleSlug'] );
		}

		return $attr;
	}
	public function verify( array $attr ) : bool {
		return ( $attr['formType'] ?? '' ) === 'registration';
	}
}
