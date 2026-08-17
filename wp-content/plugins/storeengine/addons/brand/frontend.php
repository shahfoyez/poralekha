<?php
/**
 * Brand addon — storefront output.
 *
 * Two surfaces, both active only while the Brand addon is enabled:
 *
 *   1. Single product: the assigned brand(s) are shown (linked to the brand
 *      archive) right under the category/tag taxonomy block.
 *   2. Shop archive: a "Brands" filter widget is added alongside the existing
 *      Category / Tags filters. Selecting brands re-queries the archive (AJAX)
 *      and narrows products by the brand taxonomy.
 *
 * The archive filter reuses StoreEngine's existing widget pipeline:
 *   - `storeengine/archive/product_filter_widgets` registers the widget,
 *   - the global `storeengine_render_archive_product_filter_brand_widget()`
 *     renders it (the dispatcher in includes/frontend/functions.php builds that
 *     exact function name from the widget key),
 *   - `storeengine/product_filter_args` injects the brand tax_query into the
 *     archive AJAX query.
 */

namespace StoreEngine\Addons\Brand;

use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Frontend {
	use Singleton;

	public static function init() {
		$self = self::get_instance();

		add_action( 'storeengine/templates/single-product/header_right_content', [ $self, 'render_single_product_brands' ] );
		add_filter( 'storeengine/archive/product_filter_widgets', [ $self, 'register_archive_filter_widget' ] );
		add_filter( 'storeengine/product_filter_args', [ $self, 'apply_brand_filter_args' ] );
	}

	/**
	 * Output the brand(s) assigned to the current product on the single-product
	 * page. Mirrors templates/single-product/categories.php.
	 */
	public function render_single_product_brands() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$brands = get_the_terms( $product->get_id(), Brand::TAXONOMY );
		if ( ! $brands || is_wp_error( $brands ) ) {
			return;
		}

		$links = [];
		foreach ( $brands as $brand ) {
			$links[] = '<a href="' . esc_url( get_term_link( $brand ) ) . '">' . esc_html( $brand->name ) . '</a>';
		}
		?>
		<div class="storeengine-single-product-brands">
			<span class="storeengine-single-product-brands__label"><?php echo esc_html__( 'Brands:', 'storeengine' ); ?></span>
			<span class="storeengine-single-product-brands__items">
				<?php
				echo wp_kses( implode( ', ', $links ), [
					'a' => [
						'href'   => true,
						'target' => true,
						'title'  => true,
					],
				] );
				?>
			</span>
		</div>
		<?php
	}

	/**
	 * Register the "brand" archive filter widget so it shows in the shop
	 * sidebar alongside the Category / Tags filters.
	 *
	 * @param array $config Widget config keyed by widget slug.
	 * @return array
	 */
	public function register_archive_filter_widget( $config ) {
		if ( ! isset( $config['brand'] ) ) {
			$config['brand'] = (object) [
				'status' => true,
				'order'  => 3,
			];
		}

		return $config;
	}

	/**
	 * Render the brand filter widget markup. Flat list of brands (no hierarchy).
	 * The checkboxes use name="brand" — handled client-side by ssr.js, which
	 * pushes selected slugs into the archive AJAX payload.
	 */
	public function render_archive_filter_widget() {
		$brands = get_terms( [
			'taxonomy'   => Brand::TAXONOMY,
			'hide_empty' => true,
		] );

		if ( is_wp_error( $brands ) || empty( $brands ) ) {
			return;
		}
		?>
		<h4 class="storeengine-archive-product-widget__title"><?php esc_html_e( 'Brand', 'storeengine' ); ?></h4>
		<div class="storeengine-archive-product-widget__body">
			<?php foreach ( $brands as $brand ) : ?>
				<label class="parent-term">
					<input class="storeengine-archive-product-filter" type="checkbox" name="brand" value="<?php echo esc_attr( urldecode( $brand->slug ) ); ?>"/>
					<span class="checkmark"></span>
					<span><?php echo esc_html( $brand->name ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Narrow the archive query by the selected brand slugs. The brand selection
	 * arrives in the `storeengine/archive_product_filter` AJAX GET payload, so we
	 * read it from $_REQUEST and append a tax_query clause.
	 *
	 * @param array $args WP_Query args built by Helper::prepare_product_search_query_args().
	 * @return array
	 */
	public function apply_brand_filter_args( $args ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- archive filter is a public read-only GET; nonce checked by the AJAX dispatcher.
		if ( empty( $_REQUEST['brand'] ) ) {
			return $args;
		}

		$brands = map_deep( wp_unslash( $_REQUEST['brand'] ), 'sanitize_title' );
		$brands = is_array( $brands ) ? $brands : [ $brands ];
		$brands = array_filter( $brands );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( empty( $brands ) ) {
			return $args;
		}

		$brand_clause = [
			'taxonomy' => Brand::TAXONOMY,
			'field'    => 'slug',
			'terms'    => $brands,
		];

		if ( empty( $args['tax_query'] ) ) {
			$args['tax_query'] = [ $brand_clause ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		} else {
			$args['tax_query'][]          = $brand_clause; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query']['relation'] = 'AND';
		}

		return $args;
	}
}

/**
 * Global render callback for the brand archive filter widget.
 *
 * StoreEngine's widget dispatcher (storeengine_archive_header_filter_widget())
 * builds the function name `storeengine_render_archive_product_filter_{slug}_widget`
 * and calls it when the widget is enabled, so the brand widget MUST be exposed
 * under this exact name.
 */
if ( ! function_exists( 'storeengine_render_archive_product_filter_brand_widget' ) ) {
	function storeengine_render_archive_product_filter_brand_widget() {
		Frontend::get_instance()->render_archive_filter_widget();
	}
}

// End of file frontend.php
