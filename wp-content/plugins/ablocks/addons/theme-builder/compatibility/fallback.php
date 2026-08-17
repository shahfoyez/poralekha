<?php
namespace ABlocksThemeBuilder\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocksThemeBuilder\AbstractCompatibilityBase;

class Fallback extends AbstractCompatibilityBase {

	public function render_header() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_rendered_block_content_by_id( $this->get_settings( 'header' ) );
	}

	public function render_footer() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_rendered_block_content_by_id( $this->get_settings( 'footer' ) );
	}

	public function render_before_footer() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_rendered_block_content_by_id( $this->get_settings( 'before-footer' ) );
	}
}
