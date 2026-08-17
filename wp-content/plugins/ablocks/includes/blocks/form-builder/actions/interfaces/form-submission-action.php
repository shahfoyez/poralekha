<?php
namespace ABlocks\Blocks\FormBuilder\Actions\Interfaces;

use ABlocks\Blocks\FormBuilder\ValidateFormData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface FormSubmissionAction {
	public function __construct( ValidateFormData $obj);
	public function action();
}
