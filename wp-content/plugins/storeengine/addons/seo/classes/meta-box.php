<?php
/**
 * MetaBox — per-product SEO overrides (classic editor).
 *
 * Writes the neutral `_storeengine_seo_*` postmeta that the FREE engine already
 * reads, so overrides flow into the PHP head, the bridge graft, and the REST
 * `seo` object without any extra coupling. The StoreEngine product SPA can wire
 * the same postmeta via the REST endpoints in Api\SeoController later.
 *
 * @since StoreEngine Pro 1.0.0
 */

namespace StoreEngine\Addons\Seo\Classes;

use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MetaBox {
	use Singleton;

	const NONCE = 'storeengine_seo_meta';

	protected function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'save_post_' . Helper::PRODUCT_POST_TYPE, [ $this, 'save' ], 10, 1 );
	}

	public function register(): void {
		add_meta_box(
			'storeengine-seo',
			__( 'SEO (StoreEngine)', 'storeengine' ),
			[ $this, 'render' ],
			Helper::PRODUCT_POST_TYPE,
			'normal',
			'default'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$title  = get_post_meta( $post->ID, '_storeengine_seo_title', true );
		$desc   = get_post_meta( $post->ID, '_storeengine_seo_desc', true );
		$robots = get_post_meta( $post->ID, '_storeengine_seo_robots', true );
		?>
		<p>
			<label for="storeengine_seo_title"><strong><?php esc_html_e( 'SEO title', 'storeengine' ); ?></strong></label><br />
			<input type="text" class="widefat" id="storeengine_seo_title" name="storeengine_seo_title" value="<?php echo esc_attr( $title ); ?>" maxlength="120" />
		</p>
		<p>
			<label for="storeengine_seo_desc"><strong><?php esc_html_e( 'Meta description', 'storeengine' ); ?></strong></label><br />
			<textarea class="widefat" rows="3" id="storeengine_seo_desc" name="storeengine_seo_desc" maxlength="320"><?php echo esc_textarea( $desc ); ?></textarea>
		</p>
		<p>
			<label>
				<input type="checkbox" name="storeengine_seo_robots" value="noindex" <?php checked( 'noindex', $robots ); ?> />
				<?php esc_html_e( 'Hide this product from search engines (noindex)', 'storeengine' ); ?>
			</label>
		</p>
		<?php
	}

	public function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::update_meta( $post_id, [
			'title'  => isset( $_POST['storeengine_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['storeengine_seo_title'] ) ) : '',
			'desc'   => isset( $_POST['storeengine_seo_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['storeengine_seo_desc'] ) ) : '',
			'robots' => ( isset( $_POST['storeengine_seo_robots'] ) && 'noindex' === $_POST['storeengine_seo_robots'] ) ? 'noindex' : '',
		] );
	}

	/**
	 * Shared writer used by both the meta box and the REST controller.
	 */
	public static function update_meta( int $post_id, array $data ): void {
		$map = [
			'title'  => '_storeengine_seo_title',
			'desc'   => '_storeengine_seo_desc',
			'robots' => '_storeengine_seo_robots',
		];

		foreach ( $map as $key => $meta_key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$value = (string) $data[ $key ];
			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	public static function get_meta( int $post_id ): array {
		return [
			'title'  => (string) get_post_meta( $post_id, '_storeengine_seo_title', true ),
			'desc'   => (string) get_post_meta( $post_id, '_storeengine_seo_desc', true ),
			'robots' => (string) get_post_meta( $post_id, '_storeengine_seo_robots', true ),
		];
	}
}
