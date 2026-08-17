<?php
namespace ABlocksThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocksThemeBuilder\Compatibility\Astra;
use ABlocksThemeBuilder\Compatibility\Fallback;

class CompatibilityManager {
	protected $theme;
	protected $compat_class;

	public static function init() {
		$self = new self();
		$self->theme = wp_get_theme()->get( 'TextDomain' ); // or use get_stylesheet()
		add_action( 'wp', [ $self, 'dispatch_hooks' ] );
	}
	public function dispatch_hooks() {
		switch ( strtolower( $this->theme ) ) {
			case 'astra':
				$this->compat_class = new Astra( $this->theme );
				break;
			default:
				$this->compat_class = new Fallback( '' );
		}
		$this->compat_class->init();
	}
}
