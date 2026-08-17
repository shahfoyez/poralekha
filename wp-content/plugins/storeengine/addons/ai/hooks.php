<?php
/**
 * AI Hooks.
 *
 * Surfaces a single, dismissible-free admin notice on StoreEngine screens when
 * AI generation can't run yet — either WordPress is older than 7.0 (no AI
 * Client) or no provider is connected. The notice points the user at the right
 * fix; every Generate button independently hides itself via /ai/status.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai;

use StoreEngine\AI\AiService;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	use Singleton;

	protected function __construct() {
		add_action( 'admin_notices', [ $this, 'maybe_render_unavailable_notice' ] );
	}

	/**
	 * Only nudge on StoreEngine admin screens, and only while unavailable.
	 */
	public function maybe_render_unavailable_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'storeengine' ) ) {
			return;
		}

		if ( AiService::is_available() ) {
			return;
		}

		if ( ! AiService::is_supported() ) {
			$message = __( 'StoreEngine AI needs WordPress 7.0 or newer (the built-in AI Client) to generate content.', 'storeengine' );
			$action  = '';
		} else {
			$message = __( 'Connect an AI provider to start generating product and SEO content with StoreEngine.', 'storeengine' );
			$action  = sprintf(
				' <a href="%s">%s</a>',
				esc_url( AiService::connectors_url() ),
				esc_html__( 'Set up Connectors', 'storeengine' )
			);
		}
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'StoreEngine AI', 'storeengine' ); ?>:</strong>
				<?php echo esc_html( $message ) . wp_kses_post( $action ); ?></p>
		</div>
		<?php
	}
}

// End of file hooks.php.
