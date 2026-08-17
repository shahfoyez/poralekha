<?php
namespace  StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

/**
 * [storeengine_single_product] — the full single-product view.
 *
 * Two modes:
 *
 * - With `id` (or `slug`): renders that product's complete single-product view
 *   (gallery, summary, add to cart, description, FAQ, reviews) anywhere — a
 *   page, a widget, a builder block. The queried post is swapped out for the
 *   duration and restored afterwards, so it is safe inside another loop.
 * - With no attributes: the original behaviour, rendering the queried product
 *   through the full `single-product.php` page template. Unchanged so existing
 *   content using the bare shortcode keeps working.
 */
class SingleProduct {

	/**
	 * Products currently mid-render, keyed by id.
	 *
	 * A product whose description embeds itself — or embeds a second product
	 * that embeds the first — would otherwise recurse until the process dies.
	 *
	 * @var array<int,bool>
	 */
	protected static array $rendering = [];

	public function __construct() {
		add_shortcode( 'storeengine_single_product', array( $this, 'render' ) );
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'id'   => 0,
				'slug' => '',
			],
			$atts,
			'storeengine_single_product'
		);

		$product_id = $this->resolve_product_id( $atts );

		if ( $product_id ) {
			return $this->render_product( $product_id );
		}

		// Legacy, attribute-less form: render the queried product.
		ob_start();
		Template::get_template( 'single-product.php' );
		$output = Helper::remove_line_break( ob_get_clean() );

		return Helper::remove_tag_space( $output );
	}

	/**
	 * Turn the `id` / `slug` attributes into a product id.
	 *
	 * @param array $atts Parsed attributes.
	 */
	protected function resolve_product_id( array $atts ): int {
		$id = absint( $atts['id'] );

		if ( $id ) {
			return $id;
		}

		$slug = sanitize_title( (string) $atts['slug'] );

		if ( ! $slug ) {
			return 0;
		}

		$post = get_page_by_path( $slug, OBJECT, Helper::PRODUCT_POST_TYPE );

		return $post ? (int) $post->ID : 0;
	}

	/**
	 * Render one product's single-product content, standalone.
	 *
	 * @param int $product_id Product id.
	 */
	protected function render_product( int $product_id ): string {
		$post = get_post( $product_id );

		if ( ! $post || Helper::PRODUCT_POST_TYPE !== $post->post_type ) {
			return '';
		}

		// Embedding must not become a way to read a draft/private/trashed
		// product that the visitor could not open directly.
		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $product_id ) ) {
			return '';
		}

		if ( isset( self::$rendering[ $product_id ] ) ) {
			return '';
		}

		$product = Helper::get_product( $product_id );

		if ( ! $product ) {
			return '';
		}

		self::$rendering[ $product_id ] = true;

		// Swap the globals the single-product templates read from, remembering
		// whatever loop we may have interrupted.
		$prev_post    = $GLOBALS['post'] ?? null;
		$prev_product = $GLOBALS['product'] ?? null;

		$GLOBALS['post']    = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		setup_postdata( $post );

		ob_start();
		echo '<div class="storeengine-single-product storeengine-single-product--embed">';
		Template::get_template_part( 'content', 'single-product' );
		echo '</div>';
		$html = ob_get_clean();

		$GLOBALS['post']    = $prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['product'] = $prev_product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

		if ( $prev_post instanceof \WP_Post ) {
			setup_postdata( $prev_post );
		} else {
			wp_reset_postdata();
		}

		unset( self::$rendering[ $product_id ] );

		// Deliberately not run through Helper::remove_line_break() like the
		// legacy path above: it collapses every whitespace run, which would
		// flatten <pre> blocks inside a merchant's product description.
		return $html;
	}
}
